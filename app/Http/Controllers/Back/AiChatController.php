<?php

namespace App\Http\Controllers\Back;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Models\Zone;
use App\Services\AI\ChatAiService;
use App\Services\AI\ChatTools;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\ConnectionException;

/**
 * Assistant conversationnel AquaSecure (lecture seule, zones + alertes).
 *
 *  - POST /back/ai/chat        : question (max 500 car.) + historique en session,
 *                                throttle 15/min, CSRF + rôles admin/manager/technician ;
 *  - GET  /back/ai/chat/status : santé du serveur Ollama local (ping /api/tags).
 *
 * Aucune écriture métier : la réponse provient uniquement des outils de lecture
 * ChatTools. Le journal de bord conserve l'identifiant utilisateur, les outils
 * utilisés et la durée — jamais le contenu des messages.
 */
class AiChatController extends Controller
{
    /** Clé de session de l'historique de conversation. */
    public const SESSION_KEY = 'ai_chat_history';

    public function __construct(
        protected ChatAiService $service,
        protected ChatTools $tools
    ) {}

    /**
     * POST /back/ai/chat
     */
    public function chat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:500'],
            'clear' => ['sometimes', 'boolean'],
        ], [
            'message.required' => 'Le message est obligatoire.',
            'message.string' => 'Le message doit être du texte.',
            'message.max' => 'Le message ne peut pas dépasser 500 caractères.',
        ]);

        // Effacement de la conversation (bouton « Effacer » du widget).
        if ($request->boolean('clear')) {
            $request->session()->forget(self::SESSION_KEY);

            return response()->json([
                'success' => true,
                'cleared' => true,
                'reply' => 'Conversation effacée. Posez une nouvelle question.',
                'notice' => null,
                'source' => 'local',
                'tools_used' => [],
                'links' => [],
                'source_note' => 'Source : données AquaSecure',
            ]);
        }

        /** @var array<int, array{role: string, content: string}> $history */
        $history = $request->session()->get(self::SESSION_KEY, []);

        $startedAt = microtime(true);
        $result = $this->service->answer($validated['message'], $history);
        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        // Historique limité aux N derniers messages (CHAT_AI_MAX_HISTORY).
        $history[] = ['role' => 'user', 'content' => $validated['message']];
        $history[] = ['role' => 'assistant', 'content' => $result['reply']];

        $max = max(2, (int) config('ai.chat.max_history', 10));
        $history = array_slice($history, -$max);

        $request->session()->put(self::SESSION_KEY, $history);

        // Journalisation sans le contenu des messages (données potentiellement sensibles).
        Log::info('AI chat request', [
            'user_id' => $request->user()?->id,
            'source' => $result['source'],
            'tools_used' => $result['tools_used'],
            'duration_ms' => $durationMs,
            'reply_length' => mb_strlen($result['reply']),
        ]);

        return response()->json([
            'success' => true,
            'reply' => $result['reply'],
            'notice' => $result['notice'],
            'source' => $result['source'],
            'tools_used' => $result['tools_used'],
            'links' => $this->buildLinks($result['zone_ids'], $result['alert_ids']),
            'source_note' => 'Source : données AquaSecure',
            'history_size' => count($history),
        ]);
    }

    /**
     * GET /back/ai/chat/status — ping du serveur Ollama (mise en cache 60 s).
     */
    public function status(): JsonResponse
    {
        $model = (string) config('ai.chat.model', 'llama3.1:8b');
        $root = $this->ollamaRootUrl();

        $payload = Cache::remember('ai:chat:status:'.md5($root.$model), 60, function () use ($root): array {
            try {
                $response = Http::withHeaders([
                    'Accept' => 'application/json',
                ])
                    ->connectTimeout(3)
                    ->timeout(5)
                    ->get($root.'/api/tags');

                if (! $response->successful()) {
                    return ['online' => false, 'models' => [], 'error' => 'HTTP '.$response->status()];
                }

                $models = array_values(array_map(
                    fn ($model): string => (string) ($model['name'] ?? ''),
                    is_array($response->json('models')) ? $response->json('models') : []
                ));

                return ['online' => true, 'models' => $models, 'error' => null];
            } catch (ConnectionException $e) {
                return ['online' => false, 'models' => [], 'error' => 'connexion refusée'];
            } catch (\Throwable $e) {
                Log::warning('AI chat status error', ['error' => $e->getMessage()]);

                return ['online' => false, 'models' => [], 'error' => 'erreur inconnue'];
            }
        });

        $installed = collect($payload['models'])->contains(
            fn (string $name): bool => $name === $model
                || str_starts_with($name, $model.':')
                || $name === $model.':latest'
        );

        $message = match (true) {
            ! $payload['online'] => 'Ollama hors ligne (service local injoignable).',
            ! $installed => "Ollama connecté — modèle {$model} manquant (ollama pull {$model}).",
            default => 'Ollama connecté',
        };

        return response()->json([
            'success' => true,
            'online' => (bool) $payload['online'],
            'model' => $model,
            'model_installed' => (bool) $installed,
            'message' => $message,
        ]);
    }

    /**
     * Racine du serveur Ollama (http://localhost:11434) à partir de
     * CHAT_AI_BASE_URL (http://localhost:11434/v1).
     */
    private function ollamaRootUrl(): string
    {
        $base = rtrim((string) config('ai.chat.base_url', 'http://localhost:11434/v1'), '/');

        $root = (string) preg_replace('#/v\d+$#', '', $base);

        return $root !== '' ? $root : 'http://localhost:11434';
    }

    /**
     * Liens de navigation vers les zones/alertes mentionnées dans la réponse,
     * adaptés au rôle (le technicien n'a pas accès au back-office des zones).
     *
     * @param  array<int, int>  $zoneIds
     * @param  array<int, int>  $alertIds
     * @return array<int, array{label: string, url: string}>
     */
    private function buildLinks(array $zoneIds, array $alertIds): array
    {
        $links = [];

        $role = strtolower((string) (auth()->user()?->role ?? ''));
        $role = ['gestionnaire' => 'manager', 'citoyen' => 'citizen'][$role] ?? $role;
        $canAccessBack = in_array($role, ['admin', 'manager'], true);

        foreach (array_slice($zoneIds, 0, 4) as $zoneId) {
            $zone = Zone::query()->find($zoneId);

            if ($zone === null) {
                continue;
            }

            $links[] = [
                'label' => 'Zone : '.$zone->name,
                'url' => $canAccessBack
                    ? route('back.zones.show', $zone)
                    : route('front.zones.index'),
            ];
        }

        foreach (array_slice($alertIds, 0, 4) as $alertId) {
            $alert = Alert::query()->find($alertId);

            if ($alert === null) {
                continue;
            }

            $links[] = [
                'label' => 'Alerte #'.$alert->id.' — '.$alert->type,
                'url' => $canAccessBack
                    ? route('back.alerts.show', $alert)
                    : route('front.alerts.index'),
            ];
        }

        return array_slice($links, 0, 6);
    }

    /**
     * Rôles autorisés à utiliser l'assistant (contrôle serveur complémentaire
     * au middleware de route, réutilisé par le partial du widget).
     *
     * @return array<int, string>
     */
    public static function allowedRoles(): array
    {
        return ['admin', 'manager', 'gestionnaire', 'technician'];
    }

    /**
     * Utilisateur courant autorisé à afficher/utiliser le widget ?
     */
    public static function canUse(?string $role): bool
    {
        return $role !== null && in_array(strtolower($role), self::allowedRoles(), true);
    }
}
