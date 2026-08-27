<?php

declare(strict_types=1);

namespace Xgc\Dto;

readonly class Metadata extends Document
{
    /** @var mixed[] */
    private array $data;

    /**
     * @param mixed[] $data
     */
    final public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * @return mixed[]
     */
    public function data(): array
    {
        return $this->data;
    }

    public function getField(string $field): mixed
    {
        return $this->data[$field] ?? null;
    }

    public function merge(?self $metadata): static
    {
        return $this->mergeData($metadata?->data());
    }

    /**
     * @param mixed[]|null $data
     */
    public function mergeData(?array $data): static
    {
        if ($data === null) {
            return $this;
        }

        $newMetadata = [...$this->data];
        foreach ($data as $key => $value) {
            $newMetadata[$key] = $value;
        }

        return new static($newMetadata);
    }

    public function json(?ContextInterface $context = null): array
    {
        return $this->data;
    }

    public function updateField(string $field, mixed $value): static
    {
        if ($this->getField($field) === $value) {
            return $this;
        }

        $data = [...$this->data];
        $data[$field] = $value;

        return new static($data);
    }
}
