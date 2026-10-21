<?php

namespace App\Services\AI;

use App\Exceptions\AiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client HTTP minimaliste vers le fournisseur d'IA (API compatible OpenAI).
 *
 * Responsabilités :
 *  - construire l'appel HTTP (Http::) avec timeout / connect-timeout,
 *  - exiger une réponse JSON stricte,
 *  - nettoyer les fences markdown (```json ... ```),
 *  - traduire quota / auth / timeout / réponse invalide en AiException
 *    portant un message clair pour l'utilisateur, sans jamais faire
 *    remonter d'exception brute.
 *
 * Aucune donnée personnelle (e-mail, mot de passe, identifiant utilisateur)
 * ne doit être transmise au modèle : seules les données métier (zones,
 * alertes, incidents) sont envoyées par les services appelants.
 */
class AiClient
{
    /**
     * L'API est-elle configurée (clé présente dans .env) ?
     */
    public function isConfigured(): bool
    {
        $key = config('ai.key');

        return is_string($key) && strlen(trim($key)) > 5;
    }

    /**
     * Appel « chat/completions » attendu en JSON strict.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     *
     * @throws AiException
     *
     * @return array<string, mixed>
     */
    public function chatJson(array $messages, ?float $temperature = null): array
    {
        if (! $this->isConfigured()) {
            throw new AiException(
                'AI API key is not configured.',
                503,
                "La clé API IA (AI_API_KEY) n'est pas configurée sur le serveur. Ajoutez-la au fichier .env puis réessayez."
            );
        }

        $payload = [
            'model' => config('ai.model'),
            'messages' => $messages,
            'temperature' => $temperature ?? (float) config('ai.temperature', 0.2),
            'response_format' => ['type' => 'json_object'],
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.config('ai.key'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
                ->connectTimeout((int) config('ai.connect_timeout', 10))
                ->timeout((int) config('ai.timeout', 45))
                ->post(rtrim((string) config('ai.base_url'), '/').'/chat/completions', $payload);
        } catch (ConnectionException $e) {
            Log::error('AI connection error', ['error' => $e->getMessage()]);

            throw new AiException(
                'AI connection timed out or failed.',
                504,
                "Délai d'attente dépassé lors de la connexion au service IA. Vérifiez la réseau ou réessayez dans un instant.",
                $e
            );
        }

        if ($response->status() === 429) {
            Log::warning('AI API rate limit / quota exceeded.');

            throw new AiException(
                'AI rate limit exceeded.',
                429,
                "Le quota ou la limite de requêtes de l'API IA a été atteint. Patientez un instant avant de relancer l'analyse."
            );
        }

        if (in_array($response->status(), [401, 403], true)) {
            Log::error('AI authentication failed (invalid API key).');

            throw new AiException(
                'AI authentication failed.',
                401,
                'Authentification auprès du service IA impossible : vérifiez la clé AI_API_KEY.'
            );
        }

        if (! $response->successful()) {
            Log::error('AI API HTTP error', [
                'status' => $response->status(),
                'error' => $response->json('error.message') ?? substr($response->body(), 0, 300),
            ]);

            throw new AiException(
                'AI API returned HTTP '.$response->status(),
                $response->status(),
                "Le service d'analyse IA a rencontré une erreur de traitement (code {$response->status()})."
            );
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            Log::error('AI returned empty response content.');

            throw new AiException(
                'AI returned empty content.',
                500,
                "Le modèle d'IA a renvoyé une réponse vide."
            );
        }

        return $this->decodeJson($content);
    }

    /**
     * L'API de l'assistant conversationnel est-elle joignable (URL renseignée) ?
     *
     * La clé est facultative côté Ollama local (valeur par défaut « ollama »),
     * seule la présence d'une URL de base est donc exigée.
     */
    public function isChatConfigured(): bool
    {
        $url = config('ai.chat.base_url');

        return is_string($url) && trim($url) !== '';
    }

    /**
     * Appel « chat/completions » générique renvoyant la réponse JSON brute.
     *
     * Sert à l'assistant conversationnel : permet de transmettre des options
     * additionnelles (tools, tool_choice, …) et de surcharger la base URL,
     * la clé, le modèle ou le timeout APPEL PAR APPEL, sans altérer le
     * comportement de chatJson() utilisé par l'analyse des zones/alertes.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<string, mixed>  $extra        clés additionnelles du payload (tools, …)
     * @param  array{base_url?: string, key?: string, model?: string, timeout?: int, connect_timeout?: int, temperature?: float}  $overrides
     *
     * @throws AiException
     *
     * @return array<string, mixed>
     */
    public function chatRaw(array $messages, array $extra = [], array $overrides = []): array
    {
        $baseUrl = rtrim((string) ($overrides['base_url'] ?? config('ai.base_url')), '/');
        $key = (string) ($overrides['key'] ?? config('ai.key'));
        $model = (string) ($overrides['model'] ?? config('ai.model'));
        $timeout = (int) ($overrides['timeout'] ?? config('ai.timeout', 45));
        $connectTimeout = (int) ($overrides['connect_timeout'] ?? config('ai.connect_timeout', 10));

        if ($baseUrl === '') {
            throw new AiException(
                'AI base URL is not configured.',
                503,
                "L'URL du service d'intelligence artificielle n'est pas configurée. Vérifiez le fichier .env."
            );
        }

        $payload = array_merge([
            'model' => $model,
            'messages' => $messages,
            'temperature' => (float) ($overrides['temperature'] ?? config('ai.temperature', 0.2)),
        ], $extra);

        // Limite optionnelle de tokens de sortie (payload max_tokens). Utilisée
        // par le chatbot local pour borner la durée de génération.
        if (isset($overrides['max_tokens']) && (int) $overrides['max_tokens'] > 0) {
            $payload['max_tokens'] = (int) $overrides['max_tokens'];
        }

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if (trim($key) !== '') {
            $headers['Authorization'] = 'Bearer '.$key;
        }

        try {
            $response = Http::withHeaders($headers)
                ->connectTimeout($connectTimeout)
                ->timeout($timeout)
                ->post($baseUrl.'/chat/completions', $payload);
        } catch (ConnectionException $e) {
            Log::error('AI connection error (chatRaw)', ['error' => $e->getMessage(), 'base_url' => $baseUrl]);

            throw new AiException(
                'AI connection timed out or failed.',
                504,
                "Le service d'intelligence artificielle est injoignable (connexion refusée ou délai dépassé).",
                $e
            );
        }

        if ($response->status() === 429) {
            Log::warning('AI rate limit / quota exceeded (chatRaw).');

            throw new AiException(
                'AI rate limit exceeded.',
                429,
                "Le quota ou la limite de requêtes du service IA a été atteint. Patientez un instant."
            );
        }

        if (in_array($response->status(), [401, 403], true)) {
            Log::error('AI authentication failed (chatRaw).');

            throw new AiException(
                'AI authentication failed.',
                401,
                'Authentification auprès du service IA impossible : vérifiez la configuration de la clé.'
            );
        }

        if (! $response->successful()) {
            Log::error('AI API HTTP error (chatRaw)', [
                'status' => $response->status(),
                'error' => $response->json('error.message') ?? substr($response->body(), 0, 300),
            ]);

            throw new AiException(
                'AI API returned HTTP '.$response->status(),
                $response->status(),
                "Le service d'intelligence artificielle a renvoyé une erreur (code {$response->status()})."
            );
        }

        $decoded = $response->json();

        if (! is_array($decoded)) {
            throw new AiException(
                'AI returned a non-JSON response.',
                500,
                "Le service d'intelligence artificielle a renvoyé une réponse illisible."
            );
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * Décode la réponse du modèle en retirant d'éventuelles fences markdown.
     *
     * @throws AiException
     *
     * @return array<string, mixed>
     */
    protected function decodeJson(string $content): array
    {
        $clean = trim($content);

        if (str_starts_with($clean, '```json')) {
            $clean = substr($clean, 7);
        } elseif (str_starts_with($clean, '```')) {
            $clean = substr($clean, 3);
        }

        if (str_ends_with($clean, '```')) {
            $clean = substr($clean, 0, -3);
        }

        $clean = trim($clean);

        $decoded = json_decode($clean, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            Log::error('AI returned invalid JSON', [
                'json_error' => json_last_error_msg(),
                'raw_content' => substr($clean, 0, 400),
            ]);

            throw new AiException(
                'Failed to decode JSON from AI response.',
                500,
                "La structure de données retournée par l'IA n'est pas au format JSON valide."
            );
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
