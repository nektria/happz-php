<?php

declare(strict_types=1);

namespace Xgc\Symfony\Service;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Throwable;
use Xgc\Dto\Document;
use Xgc\Utils\JsonUtil;

readonly class WSService
{
    public function __construct(
        private HubInterface $hub,
        private string $mercureHost,
        private string $mercureToken,
    ) {
    }

    public function publish(string $topic, Document $data): void
    {
        if ($this->mercureToken === 'none' || $this->mercureHost === 'none') {
            return;
        }

        try {
            $data = $data->json();
            $this->hub->publish(new Update("/{$topic}", JsonUtil::encode([
                'payload' => $data,
                'topic' => $data['topic'] ?? $topic,
            ]), true));
        } catch (Throwable $e) {
            // throw BaseException::extend($e);
        }
    }
}
