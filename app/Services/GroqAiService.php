<?php

namespace App\Services;

use App\Exceptions\GroqAiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqAiService
{
    protected ?string $apiKey;
    protected string $model;
    protected string $baseUrl;
    protected int $timeout;
    protected int $connectTimeout;

    public function __construct()
    {
        $this->apiKey = config('services.groq.key');
        $this->model = config('services.groq.model', 'openai/gpt-oss-120b');
        $this->baseUrl = rtrim(config('services.groq.base_url', 'https://api.groq.com/openai/v1'), '/');
        $this->timeout = (int) config('services.groq.timeout', 60);
        $this->connectTimeout = (int) config('services.groq.connect_timeout', 10);
    }

    /**
     * Check if Groq API credentials are configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && is_string($this->apiKey) && strlen(trim($this->apiKey)) > 5;
    }

    /**
     * Call the Groq chat completion API with structured JSON output expectation.
     *
     * @param array $messages Array of ['role' => '...', 'content' => '...']
     * @param float $temperature
     * @param array|null $responseSchema Optional JSON schema definition
     * @return array Decoded JSON response
     *
     * @throws GroqAiException
     */
    public function chatCompletionJson(array $messages, float $temperature = 0.2, ?array $responseSchema = null): array
    {
        if (!$this->isConfigured()) {
            throw new GroqAiException(
                'Groq API key is not configured in environment.',
                503,
                'La clé API Groq n\'est pas configurée sur le serveur. Veuillez configurer GROQ_API_KEY dans le fichier .env.'
            );
        }

        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => $temperature,
            'response_format' => ['type' => 'json_object'],
        ];

        // If JSON schema is provided and supported
        if ($responseSchema !== null) {
            $payload['response_format'] = [
                'type' => 'json_schema',
                'json_schema' => $responseSchema,
            ];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->connectTimeout($this->connectTimeout)
            ->timeout($this->timeout)
            ->post("{$this->baseUrl}/chat/completions", $payload);

            if ($response->status() === 429) {
                Log::warning('Groq AI API rate limit reached.');
                throw new GroqAiException(
                    'Groq AI rate limit exceeded.',
                    429,
                    'Le quota ou la limite de requêtes de l\'API IA a été atteint. Veuillez patienter un instant avant de retenter l\'analyse.'
                );
            }

            if ($response->status() === 401 || $response->status() === 403) {
                Log::error('Groq AI authentication failed. Invalid API key.');
                throw new GroqAiException(
                    'Invalid Groq API authentication.',
                    401,
                    'Authentification auprès du service IA impossible. Vérifiez la clé GROQ_API_KEY.'
                );
            }

            if (!$response->successful()) {
                Log::error('Groq AI API request error', [
                    'status' => $response->status(),
                    'error' => $response->json('error.message') ?? $response->body(),
                ]);

                throw new GroqAiException(
                    'Groq API returned HTTP error ' . $response->status(),
                    $response->status(),
                    'Le service d\'analyse IA a rencontré une erreur de traitement (Code ' . $response->status() . ').'
                );
            }

            $body = $response->json();
            $content = $body['choices'][0]['message']['content'] ?? null;

            if (empty($content) || !is_string($content)) {
                throw new GroqAiException(
                    'Groq returned empty response content.',
                    500,
                    'Le modèle d\'intelligence artificielle a renvoyé une réponse vide.'
                );
            }

            // Clean any potential markdown triple backticks formatting (e.g. ```json ... ```)
            $cleanContent = trim($content);
            if (str_starts_with($cleanContent, '```json')) {
                $cleanContent = substr($cleanContent, 7);
            } elseif (str_starts_with($cleanContent, '```')) {
                $cleanContent = substr($cleanContent, 3);
            }
            if (str_ends_with($cleanContent, '```')) {
                $cleanContent = substr($cleanContent, 0, -3);
            }
            $cleanContent = trim($cleanContent);

            $decoded = json_decode($cleanContent, true);

            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                Log::error('Groq returned invalid JSON', [
                    'json_error' => json_last_error_msg(),
                    'raw_content' => substr($cleanContent, 0, 500),
                ]);

                throw new GroqAiException(
                    'Failed to decode JSON from Groq response.',
                    500,
                    'La structure de données retournée par l\'IA n\'est pas au format JSON valide.'
                );
            }

            return $decoded;

        } catch (ConnectionException $e) {
            Log::error('Groq AI connection exception', ['message' => $e->getMessage()]);
            throw new GroqAiException(
                'Groq AI connection timed out or failed.',
                504,
                'Délai d\'attente dépassé lors de la connexion au service IA Groq. Veuillez vérifier la connexion réseau.',
                $e
            );
        } catch (GroqAiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Unexpected exception during Groq AI call', ['message' => $e->getMessage()]);
            throw new GroqAiException(
                'Unexpected error during Groq AI call: ' . $e->getMessage(),
                500,
                'Une erreur inattendue est survenue lors de l\'interaction avec le service IA.',
                $e instanceof \Exception ? $e : null
            );
        }
    }
}
