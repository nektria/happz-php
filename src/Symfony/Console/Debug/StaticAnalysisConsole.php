<?php

declare(strict_types=1);

namespace Xgc\Symfony\Console\Debug;

use Symfony\Component\Process\Process;
use Xgc\Exception\BaseException;
use Xgc\Symfony\Console\Console;
use Xgc\Utils\JsonUtil;

use function count;

class StaticAnalysisConsole extends Console
{
    public function __construct()
    {
        parent::__construct('debug:static-analysis');
    }

    protected function play(): void
    {
        $command = new Process(array_merge(['bin/console', 'debug:router', '--format=json']));
        $command->run();

        /**
         * @var array<string, array{
         *     path: string,
         *     pathRegex: string,
         *     host: string,
         *     hostRegex: string,
         *     scheme: string,
         *     method: string,
         *     class: string,
         *     defaults: array{
         *         _controller: string,
         *         _format: string,
         *     },
         *     requirements: array{
         *         code: string,
         *         _locale: string,
         *     },
         *     options: array{
         *         compiler_class: string,
         *         utf8: bool,
         *     },
         * }> $data
         */
        $data = JsonUtil::decode($command->getOutput());
        $messages = [];

        $failed = false;
        foreach ($data as $endpoint) {
            [$controller, $method] = explode('::', $endpoint['defaults']['_controller']);

            $errors = $this->analyseEndpoint($endpoint);
            if (count($errors) === 0) {
                continue;
            }

            $failed = true;
            $messages[$controller] ??= [];
            $messages[$controller][$method] ??= [];
            $messages[$controller][$method] = $errors;
        }

        foreach ($messages as $controller => $methods) {
            $this->output()->writeln("<white1>{$controller}</white1>");
            foreach ($methods as $method => $errors) {
                foreach ($errors as $error) {
                    $this->output()->writeln("    <red>{$method}:</red> {$error}");
                }
            }
            $this->output()->writeln('');
        }

        if ($failed) {
            throw new BaseException('Some endpoints are not correctly configured');
        }
    }

    /**
     * @param array{
     *     path: string,
     *     pathRegex: string,
     *     host: string,
     *     hostRegex: string,
     *     scheme: string,
     *     method: string,
     *     class: string,
     *     defaults: array{
     *         _controller: string,
     *         _format: string,
     *     },
     *     requirements: array{
     *         code: string,
     *         _locale: string,
     *     },
     *     options: array{
     *         compiler_class: string,
     *         utf8: bool,
     *     },
     * } $endpoint
     * @return string[]
     */
    private function analyseEndpoint(array $endpoint): array
    {
        if (str_starts_with($endpoint['path'], '/_')) {
            return [];
        }

        if (str_starts_with($endpoint['defaults']['_controller'], 'Nektria')) {
            return [];
        }

        $messages = [];

        // Path cannot end with '/'
        if ($endpoint['path'] !== '/' && str_ends_with($endpoint['path'], '/')) {
            $messages[] = 'Path cannot end with "/"';
        }

        // every '{' can only be after '/' in path
        if (str_contains($endpoint['path'], '{')) {
            $parts = explode('/', $endpoint['path']);
            foreach ($parts as $part) {
                $pos = strpos($part, '{');
                if ($pos !== false && $pos !== 0) {
                    $messages[] = 'A variable in path must no be mixed with other characters';

                    break;
                }
            }
        }

        // every '}' must be followed '/' except for the last one
        if (str_contains($endpoint['path'], '}')) {
            $parts = explode('/', $endpoint['path']);
            foreach ($parts as $part) {
                $pos = strpos($part, '}');
                if ($pos !== false && !str_ends_with($part, '}')) {
                    $messages[] = 'A variable in path must no be mixed with other characters';

                    break;
                }
            }
        }

        $pathParts = explode('{', $endpoint['path']);
        foreach ($pathParts as $part) {
            if (!str_contains($part, '}')) {
                continue;
            }

            $variable = explode('}', $part)[0];

            if (
                $variable !== 'shopperCode'
                && $variable !== 'orderNumber'
                && $variable !== 'ref'
                && $variable !== 'date'
                && !str_contains($variable, 'Id')
            ) {
                $messages[] = 'Path variable name must end by "Id" or be "shopperCode" or "orderNumber" or "date"';
            }
        }

        if ($endpoint['method'] === 'ANY') {
            $function = explode('::', $endpoint['defaults']['_controller'])[1];
            if (!str_contains($function, 'fallback')) {
                $messages[] = 'Method must be defined';
            }
        } elseif ($endpoint['method'] === 'GET') {
            $function = explode('::', $endpoint['defaults']['_controller'])[1];
            if (!str_starts_with($function, 'get') && !str_starts_with($function, 'download')) {
                $messages[] = 'Function must start by either "get" or "download"';
            }
        } elseif ($endpoint['method'] === 'PUT') {
            $function = explode('::', $endpoint['defaults']['_controller'])[1];
            if (!str_starts_with($function, 'save')) {
                $messages[] = 'Function must start by "save"';
            }
        } elseif ($endpoint['method'] === 'DELETE') {
            $function = explode('::', $endpoint['defaults']['_controller'])[1];
            if (!str_starts_with($function, 'delete')) {
                $messages[] = 'Function must start by "delete"';
            }
        } elseif ($endpoint['method'] === 'PATCH') {
            $function = explode('::', $endpoint['defaults']['_controller'])[1];
            if (!str_starts_with($function, 'execute')) {
                $messages[] = 'Function must start by "execute"';
            }
        } elseif ($endpoint['method'] === 'POST') {
            $function = explode('::', $endpoint['defaults']['_controller'])[1];
            if (
                str_starts_with($function, 'delete')
                || str_starts_with($function, 'get')
                || str_starts_with($function, 'execute')
                || str_starts_with($function, 'list')
                || str_starts_with($function, 'save')
            ) {
                $messages[] = 'Function must not start by "delete", "get", "list", "execute" or "save"';
            }
        } else {
            $messages[] = "Method '{$endpoint['method']}' not supported";
        }

        return $messages;
    }
}
