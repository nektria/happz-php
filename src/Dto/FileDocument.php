<?php

declare(strict_types=1);

namespace Xgc\Dto;

use function count;

use const DIRECTORY_SEPARATOR;

readonly class FileDocument extends Document
{
    public string $filename;

    public string $mime;

    public int $size;

    public function __construct(
        public string $file,
        public ?int $maxAge = null,
        ?string $filename = null,
        ?string $mime = null,
    ) {
        $size = filesize($file);
        if ($size === false) {
            $size = 0;
        }

        $this->size = $size;
        $parts = explode(DIRECTORY_SEPARATOR, $file);
        $this->filename = $filename ?? $parts[count($parts) - 1];
        if ($mime === null) {
            $autoMime = mime_content_type($file);

            if ($autoMime === false) {
                $autoMime = 'application/octet-stream';
            }

            $mime = $autoMime;
        }
        $this->mime = $mime;
    }

    public function json(?ContextInterface $context = null): array
    {
        return [
            'path' => $this->file,
            'name' => $this->filename,
            'size' => $this->size,
            'mime' => $this->mime,
        ];
    }
}
