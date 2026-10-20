<?php

namespace App\Services;

use App\Exceptions\GroqAiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AnthropicAiService
{
    private ?string $apiKey;

    private string $model;

    private string $baseUrl;

    private int $timeout;

    private int $connectTimeout;

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.key');
        $this->model = config('services.anthropic.model', 'claude-3-5-haiku-latest');
        $this->baseUrl = rtrim(config('services.anthropic.base_url', 'https://api.anthropic.com/v1'), '/');
        $this->timeout = (int) config('services.anthropic.timeout', 60);
        $this->connectTimeout = (int) config('services.anthropic.connect_timeout', 10);
    }

    public function isConfigured(): bool
    {
        return is_string($this->apiKey) && trim($this->apiKey) !== '';
    }

    public function messageJson(string $systemPrompt, string $userPrompt): array
    {
        if (! $this->isConfigured()) {
            throw new GroqAiException(
                'Anthropic API key is not configured.',
                503,
                'La clé API Anthropic n’est pas configurée. Ajoutez ANTHROPIC_API_KEY au fichier .env.'
            );
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
                'accept' => 'application/json',
            ])
                ->connectTimeout($this->connectTimeout)
                ->timeout($this->timeout)
                ->post($this->baseUrl.'/messages', [
                    'model' => $this->model,
                    'max_tokens' => 1024,
                    'temperature' => 0.1,
                    'system' => $systemPrompt,
                    'messages' => [
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                ]);

            if ($response->status() === 429) {
                throw new GroqAiException(
                    'Anthropic API rate limit reached.',
                    429,
                    'La limite de requêtes de l’API Anthropic a été atteinte. Réessayez plus tard.'
                );
            }

            if (in_array($response->status(), [401, 403], true)) {
                throw new GroqAiException(
                    'Anthropic API authentication failed.',
                    $response->status(),
                    'L’authentification auprès d’Anthropic a échoué. Vérifiez ANTHROPIC_API_KEY.'
                );
            }

            if (! $response->successful()) {
                Log::error('Anthropic API request failed.', [
                    'status' => $response->status(),
                    'error' => $response->json('error.message') ?? $response->body(),
                ]);

                throw new GroqAiException(
                    'Anthropic API returned HTTP '.$response->status(),
                    $response->status(),
                    'Le service Anthropic a rencontré une erreur (code '.$response->status().').'
                );
            }

            $content = collect($response->json('content', []))
                ->where('type', 'text')
                ->pluck('text')
                ->implode("\n");

            $json = trim($content);
            if (str_starts_with($json, '```')) {
                $json = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $json) ?? $json;
            }

            $decoded = json_decode(trim($json), true);
            if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                throw new GroqAiException(
                    'Anthropic returned invalid JSON.',
                    500,
                    'La réponse reçue de l’API Anthropic n’est pas un JSON valide.'
                );
            }

            return $decoded;
        } catch (ConnectionException $exception) {
            Log::error('Anthropic API connection failed.', ['message' => $exception->getMessage()]);

            throw new GroqAiException(
                'Anthropic API connection failed.',
                504,
                'La connexion à l’API Anthropic a échoué ou a expiré.',
                $exception
            );
        }
    }
}
