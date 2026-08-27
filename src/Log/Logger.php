<?php

declare(strict_types=1);

namespace Xgc\Log;

use Throwable;
use Xgc\Dto\ArrayDocument;
use Xgc\Dto\ContextInterface;
use Xgc\Dto\DocumentInterface;
use Xgc\Dto\ThrowableDocument;
use Xgc\Enums\LogLevel;
use Xgc\Exception\BaseException;
use Xgc\Utils\JsonUtil;

use const PHP_EOL;

readonly class Logger implements LoggerInterface
{
    /** @var mixed[] */
    private array $data;

    public function __construct(
        private ContextInterface $context,
        private SharedLogCache $sharedLogCache,
        private ProcessRegistry $registry,
    ) {
        if ($this->context->isDev()) {
            $this->data = ['channel' => false];
        } else {
            $this->data = ['channel' => fopen('php://stderr', 'wb')];
        }
    }

    public function debug(
        array $payload,
        array $labels,
        string $message,
        bool $ignoreRedis = false,
    ): void {
        if (!$ignoreRedis && !$this->context->isDebug()) {
            $this->sharedLogCache->addLog([
                'labels' => $labels,
                'message' => $message,
                'payload' => $payload,
                'project' => $this->context->project(),
            ]);

            return;
        }

        if ($this->data['channel'] === false || !$this->context->isDebug()) {
            return;
        }

        $data = $this->build($payload, $labels, $message, LogLevel::DEBUG);
        fwrite($this->data['channel'], JsonUtil::encode($data) . PHP_EOL);
    }

    public function error(array $payload, array $labels, string $message): void
    {
        if ($this->data['channel'] === false) {
            return;
        }
        $data = $this->build($payload, $labels, $message, LogLevel::ERROR);
        fwrite($this->data['channel'], JsonUtil::encode($data) . PHP_EOL);
    }

    public function exception(Throwable $throwable, ?DocumentInterface $input = null, bool $asWarning = false): void
    {
        if ($this->data['channel'] === false) {
            return;
        }

        $error = BaseException::extend($throwable);

        if (!$error->convertToLog) {
            return;
        }

        $tmp = new ThrowableDocument($throwable);
        $clearTrace = $tmp->trace();

        try {
            $data = [
                'message' => $throwable->getMessage(),
                'logName' => 'projects/nektria/logs/error',
                'severity' => $asWarning ? LogLevel::WARNING : 'EMERGENCY',
                'logging.googleapis.com/labels' => [
                    ...$this->registry->getMetadata()->data(),
                    ...[
                        'app' => $this->context->project(),
                        'env' => $this->context->env(),
                    ],
                ],
                'logging.googleapis.com/trace_sampled' => false,
            ];

            $data['logging.googleapis.com/trace'] = $this->context->traceId();

            $payload = [
                'error' => [
                    'type' => $throwable::class,
                    'file' => $throwable->getFile(),
                    'line' => $throwable->getLine(),
                    'trace' => $clearTrace,
                ],
                'input' => ($input ?? new ArrayDocument([]))->json($this->context),
            ];

            $data = array_merge($payload, $data);
            fwrite($this->data['channel'], JsonUtil::encode($data, pretty: true) . PHP_EOL);
        } catch (Throwable) {
        }
    }

    public function info(array $payload, array $labels, string $message): void
    {
        if ($this->data['channel'] === false) {
            return;
        }
        $data = $this->build($payload, $labels, $message, LogLevel::INFO);
        fwrite($this->data['channel'], JsonUtil::encode($data) . PHP_EOL);
    }

    /**
     * @param mixed[] $payload
     */
    public function log(
        LogLevel $level,
        array $payload,
        array $labels,
        string $message,
    ): void {
        match ($level) {
            LogLevel::INFO => $this->info($payload, $labels, $message),
            LogLevel::WARNING => $this->warning($payload, $labels, $message),
            LogLevel::DEBUG => $this->debug($payload, $labels, $message),
            LogLevel::ERROR => $this->error($payload, $labels, $message),
            default => false,
        };
    }

    public function temporalLogs(): void
    {
        if ($this->data['channel'] === false) {
            return;
        }

        $logs = $this->sharedLogCache->getLogs();

        foreach ($logs as $log) {
            $data = [
                'message' => $log['message'],
                'logName' => "projects/nektria/logs/{$log['project']}",
                'severity' => self::DEBUG,
                'logging.googleapis.com/labels' => $log['labels'] ?? [],
                'logging.googleapis.com/trace' => $this->context->traceId(),
                'logging.googleapis.com/trace_sampled' => false,
            ];

            $data = array_merge($log['payload'], $data);
            fwrite($this->data['channel'], JsonUtil::encode($data) . PHP_EOL);
        }
    }

    public function warning(array $payload, array $labels, string $message): void
    {
        if ($this->data['channel'] === false) {
            return;
        }
        $data = $this->build($payload, $labels, $message, LogLevel::WARNING);
        fwrite($this->data['channel'], JsonUtil::encode($data) . PHP_EOL);
    }

    /**
     * @param mixed[] $payload
     * @param array<string, string> $labels
     * @return mixed[]
     */
    private function build(
        array $payload,
        array $labels,
        string $message,
        LogLevel $level,
    ): array {
        $data = [
            'message' => $message,
            'logName' => "projects/nektria/logs/{$this->context->project()}",
            'severity' => $level->name,
            'logging.googleapis.com/labels' => [...$labels, ...$this->registry->getMetadata()->data(), ...[
                'app' => $this->context->project(),
                'env' => $this->context->env(),
            ]],
            'logging.googleapis.com/trace' => $this->context->traceId(),
            'logging.googleapis.com/trace_sampled' => false,
        ];

        return array_merge($payload, $data);
    }
}
