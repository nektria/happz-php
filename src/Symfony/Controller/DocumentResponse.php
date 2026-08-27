<?php

declare(strict_types=1);

namespace Xgc\Symfony\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Xgc\Dto\ContextInterface;
use Xgc\Dto\DocumentInterface;
use Xgc\Dto\ThrowableDocument;

class DocumentResponse extends JsonResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly DocumentInterface $document,
        ContextInterface $context,
        int $status = 200,
        array $headers = [],
    ) {
        if ($this->document instanceof ThrowableDocument) {
            parent::__construct($this->document->json($context), $this->document->status);
        } else {
            parent::__construct($this->document->json($context), $status);
        }

        $this->headers->add($headers);
    }
}
