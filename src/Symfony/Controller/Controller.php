<?php

declare(strict_types=1);

namespace Xgc\Symfony\Controller;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Throwable;
use Twig\Environment;
use Xgc\Dto\ArrayDocument;
use Xgc\Dto\ContextInterface;
use Xgc\Dto\Document;
use Xgc\Dto\DocumentInterface;
use Xgc\Exception\BaseException;
use Xgc\Exception\MissingArgumentException;
use Xgc\Message\BusInterface;
use Xgc\Message\Command;
use Xgc\Message\Query;
use Xgc\Message\RetryStamp;
use Xgc\Utils\ArrayDataFetcher;
use Xgc\Utils\ContainerBoxTrait;
use Xgc\Utils\JsonUtil;

use function sprintf;

use const ENT_QUOTES;

readonly class Controller
{
    use ContainerBoxTrait;

    protected Request $request;

    protected ArrayDataFetcher $requestData;

    public function __construct(
        RequestStack $requestStack,
    ) {
        $this->request = $requestStack->getCurrentRequest() ?? new Request();

        $all = $this->request->request->all();

        try {
            $data = [];
            $content = $this->request->getContent();
            if ($content === '') {
                $this->request->request->replace();
            } elseif ($content[0] === '[') {
                /** @var array<string, mixed> $data */
                $data = JsonUtil::decode($content);
            } else {
                /** @var array<string, mixed> $data */
                $data = JsonUtil::decode($content);
            }

            $queryData = $this->request->query->all();
            $this->requestData = new ArrayDataFetcher([...$queryData, ...$data, ...$all]);
        } catch (Throwable $e) {
            throw BaseException::extend($e);
        }
    }

    protected function command(
        Command $command,
        ?string $transport = null,
        ?DelayStamp $delayMs = null,
        ?RetryStamp $retryOptions = null,
    ): void {
        $this->service(BusInterface::class)->dispatchCommand($command, $transport, $delayMs, $retryOptions);
    }

    protected function documentResponse(DocumentInterface $document, int $status = 200): DocumentResponse
    {
        return $this->response($document, $status);
    }

    protected function emptyResponse(): DocumentResponse
    {
        return new DocumentResponse(
            new ArrayDocument(),
            $this->service(ContextInterface::class),
            Response::HTTP_NO_CONTENT,
        );
    }

    protected function getDeviceType(): string
    {
        $ua = $this->request->headers->get('User-Agent') ?? '';

        if (preg_match('/tablet|ipad|playbook|silk/i', $ua) !== false) {
            return 'tablet';
        }

        if (preg_match('/mobi|android|touch|iphone/i', $ua) !== false) {
            return 'mobile';
        }

        return 'desktop';
    }

    protected function getFile(string $field): ?UploadedFile
    {
        /** @var UploadedFile|null $file */
        $file = $this->request->files->get($field);

        if ($file === null) {
            return null;
        }

        return $file;
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T
     */
    protected function getService(string $class): object
    {
        /** @var T $service */
        $service = $this->service($class);

        return $service;
    }

    /**
     * @template T of Document
     * @param Query<T> $query
     */
    protected function queryResponse(Query $query): DocumentResponse
    {
        return $this->documentResponse($this->service(BusInterface::class)->dispatchQuery($query));
    }

    protected function redirect(string $url, bool $permanent = true): WebResponse
    {
        $content = sprintf(
            '<!DOCTYPE html><html><head><meta charset="UTF-8" /><meta http-equiv="refresh" content="0;url=\'%1$s\'" />
            <title>Redirecting to %1$s</title></head><body>Redirecting to <a href="%1$s">%1$s</a>.</body></html>',
            htmlspecialchars($url, ENT_QUOTES, 'UTF-8'),
        );

        $response = new WebResponse(
            $content,
            status: $permanent ? Response::HTTP_MOVED_PERMANENTLY : Response::HTTP_FOUND,
        );
        $response->headers->set('Location', $url);
        $response->headers->set('Content-Type', 'text/html; charset=utf-8');

        return $response;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    protected function render(string $view, array $parameters = [], bool $ignoreContext = false): WebResponse
    {
        $fixedParameters = [];
        foreach ($parameters as $key => $value) {
            if ($value instanceof DocumentInterface) {
                $fixedParameters[$key] = $value->json(
                    $ignoreContext ?
                        null :
                        $this->service(ContextInterface::class),
                );
            } else {
                $fixedParameters[$key] = $value;
            }
        }

        $fixedParameters['__locale__'] = $this->request->getLocale();
        $fixedParameters['__message__'] = '';
        $fixedParameters['__warning__'] = '';
        $fixedParameters['__error__'] = '';

        if ($this->request->getSession()->has('__message__')) {
            $fixedParameters['__message__'] = $this->request->getSession()->get('__message__');
        }

        if ($this->request->getSession()->has('__warning__')) {
            $fixedParameters['__warning__'] = $this->request->getSession()->get('__warning__');
        }

        if ($this->request->getSession()->has('__error__')) {
            $fixedParameters['__error__'] = $this->request->getSession()->get('__error__');
        }

        $this->request->getSession()->remove('__message__');
        $this->request->getSession()->remove('__warning__');
        $this->request->getSession()->remove('__error__');

        try {
            return new WebResponse(
                $this->service(Environment::class)->render($view, $fixedParameters),
                parameters: $fixedParameters,
            );
        } catch (Throwable $e) {
            throw BaseException::extend($e);
        }
    }

    protected function response(DocumentInterface $document, int $status = 200): DocumentResponse
    {
        return new DocumentResponse($document, $this->service(ContextInterface::class), $status);
    }

    protected function retrieveFile(string $field): UploadedFile
    {
        $file = $this->getFile($field);

        if ($file === null) {
            throw new MissingArgumentException($field);
        }

        return $file;
    }

    protected function setError(string $message): void
    {
        $this->request->getSession()->set('__error__', $message);
    }

    protected function setMessage(string $message): void
    {
        $this->request->getSession()->set('__message__', $message);
    }

    protected function setWarning(string $message): void
    {
        $this->request->getSession()->set('__warning__', $message);
    }
}
