<?php

declare(strict_types=1);

namespace App\Document;

use Xgc\Dto\Clock;
use Xgc\Dto\ContextInterface;
use Xgc\Dto\Document;

readonly class __ENTITY__ extends Document
{
    public function __construct(
        public string $id,
        public Clock $createdAt,
        public Clock $updatedAt,
    ) {}

    public function json(?ContextInterface $context = null): array
    {
        return [
            'id' => $this->id,
            'createdAt' => $this->createdAt->iso8601String(),
            'updatedAt' => $this->updatedAt->iso8601String(),
        ];
    }
}
