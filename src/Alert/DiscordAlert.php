<?php

declare(strict_types=1);

namespace Xgc\Alert;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use Xgc\Cache\InternalVariableCache;
use Xgc\Client\RequestClient;
use Xgc\Dto\ArrayDocument;
use Xgc\Dto\ContextInterface;
use Xgc\Dto\DocumentInterface;
use Xgc\Dto\ThrowableDocument;
use Xgc\Exception\BaseException;
use Xgc\Exception\RequestException;
use Xgc\Utils\JsonUtil;

/**
 * @phpstan-type DiscordMessage array{
 *      content?: string,
 *      embeds?: array<array{
 *          title?: string,
 *          type?: string,
 *          description?: string,
 *          url?: string,
 *          timestamp?: string,
 *          color?: int,
 *          footer?: array{
 *              text?: string,
 *              icon_url?: string
 *          },
 *          image?: array{
 *              url?: string,
 *              height?: int,
 *              width?: int
 *          },
 *          thumbnail?: array{
 *              url?: string,
 *              height?: int,
 *              width?: int
 *          },
 *          author?: array{
 *              name?: string,
 *          },
 *          fields?: array<array{
 *              name?: string,
 *              value?: string,
 *              inline?: bool
 *          }>,
 *      }>,
 *      components?: array<array{
 *          type: int,
 *          components: array<array{
 *              type: int,
 *              style: int,
 *              label: string,
 *              custom_id: string
 *          }>
 *      }>,
 *      flags: int,
 *  }
 *
 * @phpstan-type Message array{
 *     body: DiscordMessage,
 *     channel: string,
 * }
 */
readonly class DiscordAlert implements AlertInterface
{
    public const string EMPTY_LINE = "‎\n‎";

    /** @var string[] */
    private array $tokens;

    public function __construct(
        protected ContextInterface $context,
        protected RequestClient $requestClient,
        protected InternalVariableCache $internalVariableCache,
        protected string $alertsToken,
        protected string $discordErrorChannelId,
    ) {
        $this->tokens = explode(',', $this->alertsToken);
    }

    public function publishDebugMessage(
        string $channelId,
        string $message,
    ): void {
        if (!$this->context->isDebug()) {
            return;
        }

        $this->publishMessage($channelId, $message);
    }

    public function publishMessage(
        string $channelId,
        string $message,
    ): void {
        try {
            $this->internalPublishMessage($channelId, $message);
        } catch (RequestException) {
            // do nothing
        }
    }

    public function publishThrowable(
        Throwable $throwable,
        ?DocumentInterface $input = null,
    ): void {
        $input ??= new ArrayDocument([]);
        $error = BaseException::extend($throwable);

        if ($error->getPrevious() instanceof NotFoundHttpException) {
            return;
        }

        if (!$this->context->isDev()) {
            if (!$error->convertToAlert || $this->internalVariableCache->hasKey($error->hash)) {
                return;
            }
        }

        $this->internalVariableCache->saveKey($error->hash, ttl: 300);

        $rawExceptionBody = JsonUtil::encode(new ThrowableDocument($error)->json($this->context));
        $rawDocumentBody = JsonUtil::encode($input->json($this->context), true);
        $exceptionType = $error::class;
        if ($error->getPrevious() !== null) {
            $exceptionType = $error->getPrevious()::class;
        }

        $content = <<<MESSAGE
            {$exceptionType}
            {$error->getMessage()}

            ```json
            {$rawDocumentBody}
            ```

            ```json
            {$rawExceptionBody}
            ```

            Trace: {$this->context->traceId()}
        MESSAGE;

        try {
            $this->internalPublishMessage($this->discordErrorChannelId, $content);
        } catch (RequestException) {
            // TODO only if body too large
            $this->publishShortThrowable($error);
        }
    }

    private function internalPublishMessage(
        string $channelId,
        string $message,
    ): void {
        if ($channelId === 'none') {
            return;
        }

        $eol = self::EMPTY_LINE;
        $content = <<<MESSAGE
            {$eol}
            **{$this->context->project()}**
            {$eol}
            {$message}
            {$eol}
        MESSAGE;

        $discordMessage = [
            'content' => $content,
        ];

        $token = $this->tokens[array_rand($this->tokens)];

        $this->requestClient->post(
            "https://discord.com/api/channels/{$channelId}/messages",
            data: $discordMessage,
            headers: [
                'Authorization' => "Bot {$token}",
                'Content-Type' => 'application/json',
            ],
            options: ['errorIfFails' => false],
        );
    }

    private function publishEvenShorterThrowable(BaseException $error): void
    {
        $errorDocument = new ThrowableDocument($error)->json($this->context);
        unset($errorDocument['trace']);
        $rawExceptionBody = JsonUtil::encode($errorDocument);
        $exceptionType = $error::class;

        if ($error->getPrevious() !== null) {
            $exceptionType = $error->getPrevious()::class;
        }

        $content = <<<MESSAGE
            {$exceptionType}
            {$error->getMessage()}

            ```json
            {$rawExceptionBody}
            ```

            Trace: {$this->context->traceId()}
        MESSAGE;

        $this->publishMessage($this->discordErrorChannelId, $content);
    }

    private function publishShortThrowable(BaseException $error): void
    {
        $rawExceptionBody = JsonUtil::encode(new ThrowableDocument($error)->json($this->context));
        $exceptionType = $error::class;
        if ($error->getPrevious() !== null) {
            $exceptionType = $error->getPrevious()::class;
        }

        $content = <<<MESSAGE
            {$exceptionType}
            {$error->getMessage()}

            ```json
            {$rawExceptionBody}
            ```

            Trace: {$this->context->traceId()}
        MESSAGE;

        try {
            $this->internalPublishMessage($this->discordErrorChannelId, $content);
        } catch (RequestException) {
            // TODO only if body too large
            $this->publishEvenShorterThrowable($error);
        }
    }
}
