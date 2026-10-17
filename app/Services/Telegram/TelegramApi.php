<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Client minimal de l'API Telegram (aucune dépendance externe).
 *
 * Toutes les méthodes lèvent une TelegramException en cas d'échec,
 * avec le message d'erreur renvoyé par Telegram.
 */
class TelegramApi
{
    private ?string $token;

    public function __construct(?string $token = null)
    {
        $this->token = $token ?? config('telegram.token');
    }

    public function isConfigured(): bool
    {
        return filled($this->token);
    }

    /**
     * Appel générique à une méthode de l'API Bot.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     *
     * @throws TelegramException
     */
    public function call(string $method, array $payload = [], int $timeout = 60): array
    {
        if (! $this->isConfigured()) {
            throw new TelegramException('TELEGRAM_BOT_TOKEN manquant dans le fichier .env (obtenu via @BotFather).');
        }

        $response = Http::asForm()
            ->connectTimeout(15)
            ->timeout($timeout)
            ->post("https://api.telegram.org/bot{$this->token}/{$method}", $payload);

        return $this->unwrap($response, $method);
    }

    /**
     * Récupère les mises à jour en « long polling ».
     *
     * @return list<array<string, mixed>>
     *
     * @throws TelegramException
     */
    public function getUpdates(?int $offset = null, ?int $timeout = null): array
    {
        $timeout ??= (int) config('telegram.polling_timeout', 30);

        $payload = [
            'timeout' => max(0, $timeout),
            'allowed_updates' => json_encode(['message', 'callback_query']),
        ];

        if ($offset !== null) {
            $payload['offset'] = $offset;
        }

        // Le délai HTTP doit dépasser le long polling côté Telegram.
        return array_values($this->call('getUpdates', $payload, $timeout + 20));
    }

    /**
     * @param  array<string, mixed>  $options  ex. ['reply_markup' => ['inline_keyboard' => [...]]]
     *
     * @throws TelegramException
     */
    public function sendMessage(int|string $chatId, string $text, array $options = []): array
    {
        $payload = array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => 'true',
        ], $options);

        if (isset($payload['reply_markup']) && is_array($payload['reply_markup'])) {
            $payload['reply_markup'] = json_encode($payload['reply_markup'], JSON_UNESCAPED_UNICODE);
        }

        return $this->call('sendMessage', $payload);
    }

    public function sendChatAction(int|string $chatId, string $action = 'typing'): array
    {
        return $this->call('sendChatAction', ['chat_id' => $chatId, 'action' => $action]);
    }

    /**
     * @throws TelegramException
     */
    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null): void
    {
        $payload = ['callback_query_id' => $callbackQueryId];

        if ($text !== null) {
            $payload['text'] = $text;
            $payload['show_alert'] = 'true';
        }

        $this->call('answerCallbackQuery', $payload, 20);
    }

    /**
     * @param  list<array{command: string, description: string}>  $commands
     *
     * @throws TelegramException
     */
    public function setMyCommands(array $commands): void
    {
        $this->call('setMyCommands', [
            'commands' => json_encode($commands, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Supprime un éventuel webhook (le bot fonctionne en polling).
     *
     * @throws TelegramException
     */
    public function deleteWebhook(): void
    {
        $this->call('deleteWebhook', ['drop_pending_updates' => 'false'], 20);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws TelegramException
     */
    public function getFile(string $fileId): array
    {
        return $this->call('getFile', ['file_id' => $fileId], 30);
    }

    /**
     * Télécharge le contenu brut d'un fichier Telegram.
     *
     * @throws TelegramException
     */
    public function download(string $filePath): string
    {
        if (! $this->isConfigured()) {
            throw new TelegramException('TELEGRAM_BOT_TOKEN manquant dans le fichier .env.');
        }

        $response = Http::connectTimeout(15)->timeout(60)
            ->get("https://api.telegram.org/file/bot{$this->token}/{$filePath}");

        if ($response->failed()) {
            throw new TelegramException("Téléchargement du fichier impossible (HTTP {$response->status()}).");
        }

        return $response->body();
    }

    /**
     * @return array<string, mixed>
     *
     * @throws TelegramException
     */
    private function unwrap(Response $response, string $method): array
    {
        $body = $response->json();

        if ($response->failed() || ! ($body['ok'] ?? false)) {
            throw new TelegramException(
                $body['description'] ?? "Appel Telegram `{$method}` échoué (HTTP {$response->status()})."
            );
        }

        $result = $body['result'] ?? [];

        return is_array($result) ? $result : ['result' => $result];
    }
}
