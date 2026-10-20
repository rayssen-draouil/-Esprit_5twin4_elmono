<?php

namespace App\Services;

use App\Exceptions\GeminiAiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiAiService
{
    protected ?string $apiKey;

    protected string $model;

    protected string $baseUrl;

    protected int $timeout;

    protected int $connectTimeout;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
        $this->model = config('services.gemini.model', 'gemini-1.5-flash');
        $this->baseUrl = rtrim(config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $this->timeout = (int) config('services.gemini.timeout', 60);
        $this->connectTimeout = (int) config('services.gemini.connect_timeout', 15);
    }

    public function isConfigured(): bool
    {
        return ! empty($this->apiKey) && is_string($this->apiKey) && strlen(trim($this->apiKey)) > 5;
    }

    /**
     * Generate a text completion via the Google Gemini API.
     *
     * @param  array  $contents  Array of ['role' => 'user'|'model', 'parts' => [['text' => '...']]]
     * @param  string  $systemInstruction  System instruction text
     * @return string Generated text
     *
     * @throws GeminiAiException
     */
    public function generateText(array $contents, string $systemInstruction, float $temperature = 0.2, int $maxOutputTokens = 2048): string
    {
        if (! $this->isConfigured()) {
            throw new GeminiAiException(
                'Gemini API key is not configured in environment.',
                503,
                'La clé API Gemini n\'est pas configurée sur le serveur. Veuillez configurer GEMINI_API_KEY dans le fichier .env.'
            );
        }

        $payload = [
            'systemInstruction' => ['parts' => [['text' => $systemInstruction]]],
            'contents' => array_values($contents),
            'generationConfig' => [
                'temperature' => $temperature,
                'maxOutputTokens' => $maxOutputTokens,
            ],
        ];

        try {
            $response = Http::withHeaders([
                'x-goog-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
                ->connectTimeout($this->connectTimeout)
                ->timeout($this->timeout)
                ->post("{$this->baseUrl}/models/{$this->model}:generateContent", $payload);

            if ($response->status() === 429) {
                Log::warning('Gemini AI API rate limit reached.');
                throw new GeminiAiException(
                    'Gemini AI rate limit exceeded.',
                    429,
                    'Le quota ou la limite de requêtes de l\'API Gemini a été atteint. Veuillez patienter avant de retenter l\'analyse.'
                );
            }

            if ($response->status() === 401 || $response->status() === 403) {
                Log::warning('Gemini AI authentication failed.');
                throw new GeminiAiException(
                    'Invalid Gemini API authentication.',
                    401,
                    'Authentification auprès du service Gemini impossible. Vérifiez la clé GEMINI_API_KEY.'
                );
            }

            if (! $response->successful()) {
                Log::error('Gemini AI API request error', [
                    'status' => $response->status(),
                    'error' => $response->json('error.message') ?? $response->body(),
                ]);

                throw new GeminiAiException(
                    'Gemini API returned HTTP error '.$response->status(),
                    $response->status(),
                    'Le service d\'analyse Gemini a rencontré une erreur de traitement (Code '.$response->status().').'
                );
            }

            $body = $response->json();
            $candidates = $body['candidates'] ?? [];
            $content = '';

            foreach ($candidates as $candidate) {
                $parts = $candidate['content']['parts'] ?? [];
                foreach ($parts as $part) {
                    if (isset($part['text']) && is_string($part['text'])) {
                        $content .= $part['text'];
                    }
                }
            }

            $content = trim($content);

            if ($content === '') {
                throw new GeminiAiException(
                    'Gemini returned empty response content.',
                    500,
                    'Le modèle d\'intelligence artificielle a renvoyé une réponse vide.'
                );
            }

            return $content;
        } catch (ConnectionException $e) {
            Log::error('Gemini AI connection exception', ['message' => $e->getMessage()]);
            throw new GeminiAiException(
                'Gemini AI connection timed out or failed.',
                504,
                'Délai d\'attente dépassé lors de la connexion au service Gemini. Veuillez vérifier la connexion réseau.',
                $e
            );
        } catch (GeminiAiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Unexpected exception during Gemini AI call', ['message' => $e->getMessage()]);
            throw new GeminiAiException(
                'Unexpected error during Gemini AI call: '.$e->getMessage(),
                500,
                'Une erreur inattendue est survenue lors de l\'interaction avec le service Gemini.',
                $e instanceof \Exception ? $e : null
            );
        }
    }
}
