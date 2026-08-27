<?php

declare(strict_types=1);

namespace Xgc\Symfony\Listener;

use DomainException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;
use Xgc\Alert\AlertInterface;
use Xgc\Dto\ArrayDocument;
use Xgc\Dto\Clock;
use Xgc\Dto\ContextInterface;
use Xgc\Dto\DocumentCollection;
use Xgc\Dto\FileDocument;
use Xgc\Dto\ThrowableDocument;
use Xgc\Enums\LogLevel;
use Xgc\Exception\RedirectWebException;
use Xgc\Log\LoggerInterface;
use Xgc\Log\ProcessRegistry;
use Xgc\Symfony\Controller\DocumentResponse;
use Xgc\Utils\ContainerBoxTrait;
use Xgc\Utils\JsonUtil;

use function in_array;
use function is_string;

abstract class RequestListener implements EventSubscriberInterface
{
    use ContainerBoxTrait;

    private float $executionTime;

    private ?Response $originalResponse;

    public function __construct()
    {
        $this->executionTime = 0;
        $this->originalResponse = null;
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 4096],
            KernelEvents::TERMINATE => 'onKernelTerminate',
            KernelEvents::RESPONSE => ['onKernelResponse', 4096],
            KernelEvents::EXCEPTION => ['onKernelException', 4096],
            KernelEvents::CONTROLLER => ['onKernelController', 4096],
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $this->onRequestReceived($event->getRequest());
        $request = $event->getRequest();
        $method = $request->getMethod();

        if (
            $method === 'GET'
            || $method === 'PUT'
            || $method === 'PATCH'
            || $method === 'POST'
        ) {
            $content = $request->getContent();

            try {
                if ($content === '') {
                    $request->request->replace();
                } elseif ($content[0] === '[') {
                    $data = JsonUtil::decode($content);
                    $request->request->replace(['*' => $data]);
                } else {
                    /** @var array<string, mixed> $data */
                    $data = JsonUtil::decode($content);
                    $request->request->replace($data);
                }
            } catch (Throwable) {
                try {
                    $out = [];
                    parse_str($content, $out);
                    $fixedOut = [];
                    foreach ($out as $key => $value) {
                        $fixedOut[(string) $key] = $value;
                    }
                    $request->request->replace($fixedOut);
                } catch (Throwable) {
                    throw new DomainException('Bad request body.');
                }
            }
        }

        $this->checkAccess($request);
    }

    public function onKernelException(ExceptionEvent $event): void
    {

        $throwable = $event->getThrowable();
        if ($throwable instanceof RedirectWebException) {
            $event->setResponse(new RedirectResponse(
                $throwable->path,
                $throwable->status,
            ));
        } else {
            $document = new ThrowableDocument($throwable);

            $event->setResponse(new DocumentResponse(
                $document,
                $this->service(ContextInterface::class),
                $document->status,
            ));
        }

        $this->setHeaders($event);
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();

        if (
            Request::METHOD_OPTIONS === $request->getRealMethod()
            || Request::METHOD_OPTIONS === $request->getMethod()
        ) {
            $response = new Response();
            $event->setResponse($response);
        }

        $tracer = $request->headers->get('X-Trace');
        if ($tracer !== null) {
            $this->service(ContextInterface::class)->setTraceId($tracer);
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();

        if ($response instanceof DocumentResponse && $response->document instanceof FileDocument) {
            $this->originalResponse = $response;
            $fileResponse = new BinaryFileResponse(
                $response->document->file,
            );
            $fileResponse->deleteFileAfterSend();
            $fileResponse->headers->set('Content-Type', $response->document->mime);
            if ($response->document->maxAge !== null) {
                $fileResponse->headers->set('Cache-Control', "public, max-age={$response->document->maxAge}");
                $clock = Clock::now()->add($response->document->maxAge);
                $fileResponse->headers->set('Expires', $clock->rfc1123String());
            }
            $fileResponse->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                $response->document->filename,
            );
            $event->setResponse($fileResponse);
        }

        if (!$event->isMainRequest()) {
            return;
        }

        $this->setHeaders($event);
        /** @var float $serverTime */
        $serverTime = $event->getRequest()->server->get('REQUEST_TIME_FLOAT');
        $this->executionTime = microtime(true) - $serverTime;

        if ($response instanceof DocumentResponse) {
            $this->onResponseCreated($response);
        }
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        $route = $event->getRequest()->attributes->get('_route') ?? '';

        if (!is_string($route)) {
            return;
        }

        if ($route === '') {
            return;
        }

        $response = $this->originalResponse ?? $event->getResponse();
        $status = $response->getStatusCode();
        $document = null;
        if ($response instanceof DocumentResponse) {
            $document = $response->document;

            if (!$document instanceof ThrowableDocument) {
                $status = $response->getStatusCode();
            }
        }

        if ($this->service(ContextInterface::class)->isTest()) {
            return;
        }

        $logLevel = $this->assignLogLevel($event->getRequest());

        $responseContentRaw = ($this->originalResponse ?? $event->getResponse())->getContent();
        $length = 0;

        $response = $event->getResponse();
        if (
            $response instanceof DocumentResponse
        ) {
            $document = $response->document;
            if ($document instanceof DocumentCollection) {
                $length = $document->count();
            }
        }

        if ($responseContentRaw === false || $responseContentRaw === '') {
            $responseContentRaw = '[]';
        }

        $requestContentRaw = $event->getRequest()->getContent();
        if ($requestContentRaw === '') {
            $requestContentRaw = '[]';
        }

        try {
            /** @var array<string, mixed> $requestContent */
            $requestContent = JsonUtil::decode($requestContentRaw);
        } catch (Throwable) {
            return;
        }

        if ($document === null) {
            try {
                $responseContent = JsonUtil::decode($responseContentRaw);
            } catch (Throwable) {
                $responseContent = [];
            }
        } else {
            $responseContent = $document->json($this->service(ContextInterface::class));
        }

        $queryBody = [];
        $queryString = $event->getRequest()->getQueryString() !== null
            ? '?' . $event->getRequest()->getQueryString()
            : '';
        parse_str($event->getRequest()->getQueryString() ?? '', $queryBody);

        $requestContent = array_merge($queryBody, $requestContent);

        $path = $event->getRequest()->getPathInfo();
        $resume = "{$path}{$queryString}";
        $rawHeadersKeys = $event->getRequest()->headers->keys();
        $headers = [];
        foreach ($rawHeadersKeys as $key) {
            $header = strtolower($key);

            if ($header === 'x-authorization' || $header === 'x-api-id') {
                $headers[$key] = '********';

                continue;
            }

            $headers[$key] = $event->getRequest()->headers->get($key);
        }

        if (isset($requestContent['email'])) {
            $requestContent['email'] = '********';
        }
        if (isset($requestContent['password'])) {
            $requestContent['password'] = '********';
        }
        if (isset($requestContent['newPassword'])) {
            $requestContent['newPassword'] = '********';
        }
        if (isset($requestContent['oldPassword'])) {
            $requestContent['oldPassword'] = '********';
        }
        if (isset($requestContent['dniNie'])) {
            $requestContent['dniNie'] = '********';
        }

        /** @var array<string, string> $routeParams */
        $routeParams = $event->getRequest()->attributes->get('_route_params', []);

        foreach ($routeParams as $key => $value) {
            if (str_ends_with($key, 'Id')) {
                $key = substr($key, 0, -2);
            }

            $this->service(ProcessRegistry::class)->addValue($key, $value);
        }

        $this->service(ProcessRegistry::class)->addValue('path', $route);
        $this->service(ProcessRegistry::class)->addValue('context', 'request');

        if ($logLevel !== LogLevel::NONE) {
            if ($status < 400) {
                if ($event->getRequest()->getMethod() !== Request::METHOD_GET) {
                    $isDebug = false;
                } else {
                    $isDebug = $this->service(ContextInterface::class)->isDebug();
                }

                if ($logLevel !== null) {
                    $isDebug = $logLevel === LogLevel::DEBUG;
                }

                if ($isDebug) {
                    $this->service(LoggerInterface::class)->debug(
                        [
                            'headers' => $headers,
                            'httpRequest' => [
                                'requestMethod' => $event->getRequest()->getMethod(),
                                'requestUrl' => $path,
                                'status' => ($this->originalResponse ?? $event->getResponse())->getStatusCode(),
                                'latency' => round($this->executionTime, 3) . 's',
                            ],
                            'request' => $requestContent,
                            'response' => $responseContent,
                            'size' => $length,
                        ],
                        [],
                        $resume,
                        in_array($route, $this->ignoreLogs(), true),
                    );
                } else {
                    $this->service(LoggerInterface::class)->info([
                        'headers' => $headers,
                        'httpRequest' => [
                            'requestMethod' => $event->getRequest()->getMethod(),
                            'requestUrl' => $path,
                            'status' => ($this->originalResponse ?? $event->getResponse())->getStatusCode(),
                            'latency' => round($this->executionTime, 3) . 's',
                        ],
                        'request' => $requestContent,
                        'response' => $responseContent,
                        'size' => $length,
                    ], [], $resume);
                }
            } elseif ($status < 500) {
                $this->service(LoggerInterface::class)->warning([
                    'headers' => $headers,
                    'httpRequest' => [
                        'requestMethod' => $event->getRequest()->getMethod(),
                        'requestUrl' => $path,
                        'status' => ($this->originalResponse ?? $event->getResponse())->getStatusCode(),
                        'latency' => round($this->executionTime, 3) . 's',
                    ],
                    'request' => $requestContent,
                    'response' => $responseContent,
                    'size' => $length,
                ], [], $resume);
            } else {
                $this->service(LoggerInterface::class)->temporalLogs();
                $this->service(LoggerInterface::class)->error([
                    'headers' => $headers,
                    'httpRequest' => [
                        'requestMethod' => $event->getRequest()->getMethod(),
                        'requestUrl' => $path,
                        'status' => ($this->originalResponse ?? $event->getResponse())->getStatusCode(),
                        'latency' => round($this->executionTime, 3) . 's',
                    ],
                    'request' => $requestContent,
                    'response' => $responseContent,
                    'size' => $length,
                ], [], $resume);
            }
        }

        if ($response instanceof DocumentResponse) {
            $document = $response->document;

            if (!$document instanceof ThrowableDocument) {
                return;
            }

            if ($document->status >= 500) {
                $this->service(AlertInterface::class)->publishThrowable(
                    $document->throwable,
                    input: new ArrayDocument($requestContent),
                );
            }
        }
    }

    protected function assignLogLevel(Request $request): ?LogLevel
    {
        return null;
    }

    protected function checkAccess(Request $request): void
    {
    }

    /**
     * @return string[]
     */
    protected function exposedHeaders(): array
    {
        return [];
    }

    /**
     * @return string[]
     */
    protected function getAllowedCorsHeaders(): array
    {
        return [];
    }

    /**
     * @return string[]
     */
    protected function ignoreLogs(): array
    {
        return [];
    }

    protected function onRequestReceived(Request $request): void
    {
    }

    protected function onResponseCreated(DocumentResponse $response): void
    {
    }

    protected function setHeaders(RequestEvent | ResponseEvent $event): void
    {
        $response = $event->getResponse();

        if (!$this->isCorsNeeded($event)) {
            return;
        }

        if ($response !== null) {
            /** @var string $httpOrigin */
            $httpOrigin = $event->getRequest()->server->get('HTTP_ORIGIN');
            $response->headers->set('Access-Control-Allow-Origin', $httpOrigin);
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Expose-Headers', implode(', ', $this->exposedHeaders()));
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', implode(', ', $this->getAllowedCorsHeaders()));
        }
    }

    private function isCorsNeeded(RequestEvent | ResponseEvent $event): bool
    {
        /** @var string|null $origin */
        $origin = $event->getRequest()->server->get('HTTP_ORIGIN');

        if ($origin === null) {
            return true;
        }

        $rawAllowedCors = $this->parameter('allowed_cors') ?? '[]';
        $allowedCors = [];
        if (is_string($rawAllowedCors)) {
            /** @var string[] $allowedCors */
            $allowedCors = JsonUtil::decode($rawAllowedCors);
        }

        return in_array('*', $allowedCors, true) || in_array($origin, $allowedCors, true);
    }
}
