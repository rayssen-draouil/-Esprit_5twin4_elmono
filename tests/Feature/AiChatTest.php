<?php

namespace Tests\Feature;

use App\Http\Controllers\Back\AiChatController;
use App\Models\Alert;
use App\Models\User;
use App\Models\Zone;
use App\Services\AI\ChatAiService;
use App\Services\AI\ChatTools;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tests de l'assistant conversationnel AquaSecure (lecture seule, zones + alertes).
 *
 * Ollama est systématiquement mocké via Http::fake :
 *  - flux « tool mode » (tool_calls -> étape finale),
 *  - intent-router (JSON strict, sur JSON invalide),
 *  - repli mots-clés sur connexion refusée.
 */
class AiChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Aucune auto-analyse IA parasite : la création d'alertes reste locale.
        config(['ai.auto_analyze_on_create' => false]);
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => 'manager']);
    }

    private function technician(): User
    {
        return User::factory()->create(['role' => 'technician']);
    }

    private function citizen(): User
    {
        return User::factory()->create(['role' => 'citizen']);
    }

    /* =========================================================================
     |  Helpers de mock Ollama
     * ====================================================================== */

    private function toolCallPayload(string $name, array $args): array
    {
        return [
            'choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'content' => '',
                    'tool_calls' => [[
                        'id' => 'call_test_1',
                        'type' => 'function',
                        'function' => [
                            'name' => $name,
                            'arguments' => json_encode($args),
                        ],
                    ]],
                ],
            ]],
        ];
    }

    private function textPayload(string $content): array
    {
        return [
            'choices' => [[
                'message' => ['role' => 'assistant', 'content' => $content],
            ]],
        ];
    }

    private function fakeOllamaOffline(): void
    {
        Http::fake([
            '*/chat/completions' => fn () => throw new ConnectionException('cURL error 7: Connection refused'),
        ]);
    }

    /* =========================================================================
     |  Autorisation et rôles
     * ====================================================================== */

    public function test_guest_cannot_use_chat(): void
    {
        $this->postJson(route('back.ai.chat'), ['message' => 'bonjour'])
            ->assertStatus(401);
    }

    public function test_citizen_cannot_use_chat(): void
    {
        $this->actingAs($this->citizen())
            ->postJson(route('back.ai.chat'), ['message' => 'bonjour'])
            ->assertStatus(403);
    }

    public function test_technician_can_use_chat(): void
    {
        $this->fakeOllamaOffline();

        $this->actingAs($this->technician())
            ->postJson(route('back.ai.chat'), ['message' => 'quelles alertes cette semaine ?'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('source', 'keyword');
    }

    public function test_manager_can_use_chat(): void
    {
        $this->fakeOllamaOffline();

        $this->actingAs($this->manager())
            ->postJson(route('back.ai.chat'), ['message' => 'combien d alertes ?'])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    /* =========================================================================
     |  Validation
     * ====================================================================== */

    public function test_message_is_required(): void
    {
        $this->actingAs($this->manager())
            ->postJson(route('back.ai.chat'), [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');
    }

    public function test_message_cannot_exceed_500_characters(): void
    {
        $this->actingAs($this->manager())
            ->postJson(route('back.ai.chat'), ['message' => str_repeat('a', 501)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');
    }

    public function test_message_of_exactly_500_characters_is_accepted(): void
    {
        $this->fakeOllamaOffline();

        $this->actingAs($this->manager())
            ->postJson(route('back.ai.chat'), ['message' => str_repeat('a', 500)])
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    /* =========================================================================
     |  Niveau 1 — Tool mode
     * ====================================================================== */

    public function test_tool_call_flow_executes_whitelisted_tool(): void
    {
        Zone::factory()->create(['name' => 'Zone Test Nord', 'ai_risk_score' => 82]);

        Http::fake([
            '*/chat/completions' => Http::sequence()
                ->push($this->toolCallPayload('get_top_risk_zones', ['limit' => 5]))
                ->push($this->textPayload('La zone la plus risquée est la Zone Test Nord avec un score de 82.')),
        ]);

        $response = $this->actingAs($this->manager())
            ->postJson(route('back.ai.chat'), ['message' => 'quelles zones sont les plus risquées ?'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('source', 'tool')
            ->assertJsonPath('tools_used.0', 'get_top_risk_zones');

        $this->assertNotEmpty($response->json('reply'));
        $this->assertStringContainsString('Zone Test Nord', json_encode($response->json('links'), JSON_UNESCAPED_UNICODE));

        // Premier appel : définitions d'outils au format OpenAI + modèle Ollama.
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/chat/completions')
                && ($request['model'] ?? null) === config('ai.chat.model')
                && is_array($request['tools'] ?? null)
                && count($request['tools']) === count(ChatTools::NAMES);
        });

        // Deuxième appel : outils exécutés transmis pour la réponse finale.
        Http::assertSent(function ($request) {
            $roles = array_column($request['messages'] ?? [], 'role');

            return in_array('tool', $roles, true);
        });
    }

    public function test_model_cannot_call_a_non_whitelisted_tool(): void
    {
        $result = app(ChatTools::class)->execute('delete_all_users', []);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('non autorisé', $result['error']);
    }

    public function test_tools_whitelist_contains_only_read_only_zone_alert_tools(): void
    {
        $expected = [
            'get_top_risk_zones',
            'get_zone_summary',
            'get_alerts',
            'count_alerts',
            'get_priority_alerts',
        ];

        $this->assertSame($expected, ChatTools::NAMES);
    }

    /* =========================================================================
     |  Niveau 2 — Intent router
     * ====================================================================== */

    public function test_intent_router_runs_when_no_tool_call_is_returned(): void
    {
        Zone::factory()->count(2)->create();
        Alert::factory()->count(3)->create(['severity' => 'critical', 'created_at' => now()]);

        Http::fake([
            '*/chat/completions' => Http::sequence()
                ->push($this->textPayload('Voici la classification de votre demande.'))
                ->push($this->textPayload('{"intent":"count_alerts","severity":"critical","period_days":null,"limit":null,"zone":null}'))
                ->push($this->textPayload('3 alertes critiques sont enregistrées.')),
        ]);

        $this->actingAs($this->manager())
            ->postJson(route('back.ai.chat'), ['message' => 'combien d alertes critiques ?'])
            ->assertOk()
            ->assertJsonPath('source', 'intent')
            ->assertJsonPath('tools_used.0', 'count_alerts');

        Http::assertSentCount(3);
    }

    public function test_intent_router_falls_back_to_keywords_on_invalid_json(): void
    {
        $this->fakeOllamaOfflineSequence();

        $response = $this->actingAs($this->manager())
            ->postJson(route('back.ai.chat'), ['message' => 'alertes récentes ?'])
            ->assertOk()
            ->assertJsonPath('source', 'keyword');

        $this->assertNotNull($response->json('notice'));
        $this->assertNotEmpty($response->json('reply'));

        Http::assertSentCount(2);
    }

    public function test_off_topic_question_is_refused_without_executing_tools(): void
    {
        Http::fake([
            '*/chat/completions' => Http::sequence()
                ->push($this->textPayload('Quelle est la recette de la tarte aux pommes ?'))
                ->push($this->textPayload('{"intent":"off_topic","zone":null,"period_days":null,"severity":null,"limit":null}')),
        ]);

        $response = $this->actingAs($this->manager())
            ->postJson(route('back.ai.chat'), ['message' => 'quelle est la recette de la tarte ?'])
            ->assertOk()
            ->assertJsonPath('source', 'intent');

        $this->assertSame([], $response->json('tools_used'));
        $this->assertStringContainsString('limité aux zones', $response->json('reply'));

        // Aucun appel d'outil : uniquement la classification puis le refus.
        Http::assertSentCount(2);
    }

    private function fakeOllamaOfflineSequence(): void
    {
        Http::fake([
            '*/chat/completions' => Http::sequence()
                ->push($this->textPayload('Désolé, je ne comprends pas cette demande.'))
                ->push($this->textPayload('ceci n est pas du json')),
        ]);
    }

    /* =========================================================================
     |  Niveau 3 — Repli mots-clés sur panne d'Ollama
     * ====================================================================== */

    public function test_keyword_fallback_when_ollama_is_unreachable(): void
    {
        Zone::factory()->create(['name' => 'Zone Repli']);
        $this->fakeOllamaOffline();

        $response = $this->actingAs($this->manager())
            ->postJson(route('back.ai.chat'), ['message' => 'quelles zones sont les plus risquées ?'])
            ->assertOk()
            ->assertJsonPath('source', 'keyword')
            ->assertJsonPath('tools_used.0', 'get_top_risk_zones');

        $this->assertStringContainsString('Ollama', $response->json('notice'));
        $this->assertStringContainsString('Zone Repli', $response->json('reply'));
    }

    public function test_keyword_fallback_detects_zone_name_in_question(): void
    {
        Zone::factory()->create(['name' => 'Zone Sfax Centre', 'ai_risk_score' => 61, 'ai_summary' => 'Surveillance renforcée en cours.']);
        $this->fakeOllamaOffline();

        $response = $this->actingAs($this->manager())
            ->postJson(route('back.ai.chat'), ['message' => 'résumé de la zone sfax ?'])
            ->assertOk()
            ->assertJsonPath('source', 'keyword')
            ->assertJsonPath('tools_used.0', 'get_zone_summary');

        $this->assertStringContainsString('Sfax', $response->json('reply'));
    }

    /* =========================================================================
     |  Bornage des arguments (limite / période / gravité)
     * ====================================================================== */

    public function test_alert_tool_arguments_are_clamped(): void
    {
        Zone::factory()->create();

        $result = app(ChatTools::class)->execute('get_alerts', [
            'limit' => 999,
            'period_days' => 500,
            'severity' => 'warning',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame(90, $result['data']['filters']['period_days']);
        $this->assertSame('medium', $result['data']['filters']['severity']);
        $this->assertLessThanOrEqual(ChatTools::LIMIT_MAX, $result['data']['returned']);
    }

    public function test_top_risk_zones_limit_is_clamped(): void
    {
        Zone::factory()->count(3)->create();

        $result = app(ChatTools::class)->execute('get_top_risk_zones', ['limit' => 999]);

        $this->assertTrue($result['ok']);
        $this->assertSame(3, $result['data']['count']);
        $this->assertLessThanOrEqual(ChatTools::LIMIT_MAX, $result['data']['count']);
    }

    public function test_intent_schema_is_validated_and_clamped(): void
    {
        $service = app(ChatAiService::class);

        $this->assertNull($service->decodeIntent('pas du tout du json'));
        $this->assertNull($service->decodeIntent('{"intent":"inconnu"}'));

        $intent = $service->decodeIntent(json_encode([
            'intent' => 'list_alerts',
            'zone' => str_repeat('z', 120),
            'period_days' => 500,
            'severity' => 'warning',
            'limit' => 999,
        ]));

        $this->assertNotNull($intent);
        $this->assertSame('list_alerts', $intent['intent']);
        $this->assertSame(80, strlen($intent['zone']));
        $this->assertSame(90, $intent['period_days']);
        $this->assertSame('medium', $intent['severity']);
        $this->assertSame(20, $intent['limit']);
    }

    public function test_unknown_zone_returns_actionable_error(): void
    {
        Zone::factory()->create(['name' => 'Zone Delta']);

        $result = app(ChatTools::class)->execute('get_zone_summary', ['zone' => 'zone inexistante xyz']);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Zone introuvable', $result['error']);
        $this->assertStringContainsString('Zone Delta', $result['error']);
    }

    /* =========================================================================
     |  Confidentialité : aucune donnée personnelle vers le modèle
     * ====================================================================== */

    public function test_no_personal_data_is_sent_to_the_model(): void
    {
        $manager = User::factory()->create([
            'role' => 'manager',
            'email' => 'personne.confidentielle@example.tn',
            'password' => 'MotDePasseSecret123!',
        ]);

        $zone = Zone::factory()->create(['name' => 'Zone Confidentialité']);
        Alert::factory()->create([
            'zone_id' => $zone->id,
            'message' => 'Fuite détectée sur la conduite principale.',
            'severity' => 'high',
        ]);

        Http::fake([
            '*/chat/completions' => Http::sequence()
                ->push($this->toolCallPayload('get_zone_summary', ['zone' => 'Zone Confidentialité']))
                ->push($this->textPayload('La zone présente une fuite sur la conduite principale.')),
        ]);

        $this->actingAs($manager)
            ->postJson(route('back.ai.chat'), ['message' => 'résumé de la zone confidentialité ?'])
            ->assertOk();

        $sentBodies = [];
        foreach (Http::recorded() as [$request]) {
            $sentBodies[] = json_encode($request->data(), JSON_UNESCAPED_UNICODE);
        }

        $this->assertNotEmpty($sentBodies);

        foreach ($sentBodies as $body) {
            $this->assertStringNotContainsString('personne.confidentielle@example.tn', $body);
            $this->assertStringNotContainsString('MotDePasseSecret123!', $body);
            $this->assertStringNotContainsString('password123', $body);
        }

        // Les données métier (zones/alertes) sont bien présentes : le périmètre est respecté.
        $this->assertStringContainsString('Zone Confidentialité', implode("\n", $sentBodies));
    }

    /* =========================================================================
     |  Historique de session et effacement
     * ====================================================================== */

    public function test_conversation_history_is_persisted_and_limited(): void
    {
        config(['ai.chat.max_history' => 4]);
        $this->fakeOllamaOffline();

        $acting = $this->actingAs($this->manager());

        $acting->postJson(route('back.ai.chat'), ['message' => 'question une'])->assertOk();
        $second = $acting->postJson(route('back.ai.chat'), ['message' => 'question deux'])->assertOk();

        // 4 messages maximum : deux dernières questions/réponses conservées.
        $this->assertSame(4, $second->json('history_size'));

        $this->assertIsArray(session(AiChatController::SESSION_KEY));
        $this->assertCount(4, session(AiChatController::SESSION_KEY));
    }

    public function test_clear_conversation_empties_the_history(): void
    {
        $this->fakeOllamaOffline();

        $acting = $this->actingAs($this->manager());
        $acting->postJson(route('back.ai.chat'), ['message' => 'question'])->assertOk();

        $acting->postJson(route('back.ai.chat'), ['message' => 'effacer', 'clear' => true])
            ->assertOk()
            ->assertJsonPath('cleared', true);

        $next = $acting->postJson(route('back.ai.chat'), ['message' => 'nouvelle question'])->assertOk();

        $this->assertSame(2, $next->json('history_size'));
    }

    /* =========================================================================
     |  Endpoint de santé (Ollama)
     * ====================================================================== */

    public function test_status_endpoint_reports_online_ollama_with_installed_model(): void
    {
        Cache::flush();
        Http::fake([
            '*/api/tags' => Http::response(['models' => [['name' => 'llama3.1:8b']]], 200),
        ]);

        $this->actingAs($this->manager())
            ->getJson(route('back.ai.chat.status'))
            ->assertOk()
            ->assertJsonPath('online', true)
            ->assertJsonPath('model_installed', true)
            ->assertJsonPath('model', 'llama3.1:8b');
    }

    public function test_status_endpoint_reports_offline_ollama(): void
    {
        Cache::flush();
        Http::fake([
            '*/api/tags' => fn () => throw new ConnectionException('Connection refused'),
        ]);

        $this->actingAs($this->manager())
            ->getJson(route('back.ai.chat.status'))
            ->assertOk()
            ->assertJsonPath('online', false)
            ->assertJsonPath('model_installed', false);
    }

    public function test_status_endpoint_requires_authentication(): void
    {
        $this->getJson(route('back.ai.chat.status'))->assertStatus(401);
    }

    /* =========================================================================
     |  Widget (rendu côté serveur)
     * ====================================================================== */

    public function test_widget_is_rendered_on_back_office_for_manager(): void
    {
        $this->actingAs($this->manager())
            ->get('/back')
            ->assertOk()
            ->assertSee('ai-chat-root')
            ->assertSee(route('back.ai.chat'));
    }

    public function test_widget_is_rendered_for_technician_pages(): void
    {
        $this->actingAs($this->technician())
            ->get('/technicien/interventions')
            ->assertOk()
            ->assertSee('ai-chat-root');
    }

    public function test_widget_is_not_rendered_for_citizen(): void
    {
        $this->actingAs($this->citizen())
            ->get('/zones')
            ->assertOk()
            ->assertDontSee('ai-chat-root');
    }

    /* =========================================================================
     |  Throttle
     * ====================================================================== */

    public function test_chat_is_throttled_after_fifteen_requests(): void
    {
        $this->fakeOllamaOffline();

        $acting = $this->actingAs($this->manager());

        for ($i = 0; $i < 15; $i++) {
            $acting->postJson(route('back.ai.chat'), ['message' => 'question '.$i])->assertOk();
        }

        $acting->postJson(route('back.ai.chat'), ['message' => 'question seize'])->assertStatus(429);
    }

    /* =========================================================================
     |  Garantie read-only
     * ====================================================================== */

    public function test_chat_execution_never_modifies_zone_or_alert_rows(): void
    {
        $zone = Zone::factory()->create([
            'name' => 'Zone Immuable',
            'risk_level' => 'medium',
            'ai_risk_score' => null,
        ]);
        $alert = Alert::factory()->create(['zone_id' => $zone->id, 'severity' => 'high']);

        $zoneBefore = $zone->fresh()->getAttributes();
        $alertBefore = $alert->fresh()->getAttributes();

        $this->fakeOllamaOffline();

        $this->actingAs($this->manager())
            ->postJson(route('back.ai.chat'), ['message' => 'résumé de la zone immuable ?'])
            ->assertOk();

        $this->assertEquals($zoneBefore, $zone->fresh()->getAttributes());
        $this->assertEquals($alertBefore, $alert->fresh()->getAttributes());
    }
}
