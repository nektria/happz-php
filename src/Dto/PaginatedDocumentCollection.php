<?php

declare(strict_types=1);

namespace Xgc\Dto;

/**
 * @template T of Document
 */
readonly class PaginatedDocumentCollection extends Document
{
    /**
     * @param DocumentCollection<T> $items
     */
    public function __construct(
        public DocumentCollection $items,
        public int $page,
        public int $pageSize,
        public int $totalItems,
    ) {
    }

    public function json(?ContextInterface $context = null): array
    {
        $data = $this->items->json($context);
        $data['page'] = $this->page;
        $data['pageSize'] = $this->pageSize;
        $data['totalItems'] = $this->totalItems;
        $data['totalPages'] = (int) ceil($this->totalItems / $this->pageSize);

        return $data;
    }
}
