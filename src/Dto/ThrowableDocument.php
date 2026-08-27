<?php

declare(strict_types=1);

namespace Xgc\Dto;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;
use Xgc\Exception\BaseException;

use function count;
use function in_array;

class ThrowableDocument implements DocumentInterface
{
    public readonly int $status;

    public readonly Throwable $throwable;

    /**
     * @var string[]
     */
    public static array $validPrefixTraceFiles = [
        '/app/src',
        '/app/vendor/nektria/php-tools/src',
    ];

    public function __construct(
        Throwable $throwable,
    ) {
        $realThrowable = $throwable;
        while ($realThrowable instanceof BaseException) {
            $tmp = $realThrowable->getPrevious();
            if ($tmp === null) {
                break;
            }
            $realThrowable = $tmp;
        }

        if ($throwable instanceof BaseException) {
            $this->status = $throwable->status;
        } elseif ($realThrowable instanceof HttpException) {
            $this->status = $realThrowable->getStatusCode();
        } else {
            $this->status = Response::HTTP_INTERNAL_SERVER_ERROR;
        }
        $this->throwable = $realThrowable;
    }

    public static function addValidPrefixTraceFile(string $filePrefix): void
    {
        if (in_array($filePrefix, self::$validPrefixTraceFiles, true)) {
            return;
        }

        self::$validPrefixTraceFiles[] = $filePrefix;
    }

    public function json(?ContextInterface $context = null): array
    {
        $message = $this->throwable->getMessage();
        $extras = null;

        if ($this->throwable instanceof BaseException) {
            $extras = $this->throwable->extras;
        }

        if (
            $this->status >= 500
            && $context?->isProd() === true
            && $context->traceId() !== '00000000-0000-4000-8000-000000000000'
        ) {
            $extras = null;
            $message = 'Internal Server Error';
        }

        $data = [
            'extras' => $extras,
            'message' => $message,
        ];

        if ($context?->isDebug() === true) {
            $data['file'] = str_replace('/app/', '', $this->throwable->getFile());
            $data['line'] = $this->throwable->getLine();
            $data['trace'] = $this->trace();
        }

        return $data;
    }

    /**
     * @return array{
     *     file: string,
     *     line: int
     * }[]
     */
    public function trace(): array
    {
        $trace = $this->throwable->getTrace();
        $finalTrace = [];
        foreach ($trace as $item) {
            $file = $item['file'] ?? '';
            $line = $item['line'] ?? 0;

            if (count(self::$validPrefixTraceFiles) === 0) {
                $isValid = true;
            } else {
                $isValid = false;
                foreach (self::$validPrefixTraceFiles as $prefix) {
                    if (str_starts_with($file, $prefix)) {
                        $isValid = true;

                        break;
                    }
                }
            }
            if ($isValid) {
                $finalTrace[] = [
                    'file' => $file,
                    'line' => $line,
                ];
            }
        }

        return $finalTrace;
    }
}
