<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeZoneJob;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Tests du module IA des zones (endpoints + service), avec API IA mockée (Http::fake).
 */
class ZoneAiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Isole les tests du module IA des zones : l'auto-analyse des alertes
        // (déclenchée à la création d'une alerte) n'est pas le sujet ici.
        config(['ai.auto_analyze_on_create' => false]);
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => 'manager']);
    }

    private function citizen(): User
    {
        return User::factory()->create(['role' => 'citizen']);
    }

    /**
     * Réponse HTTP mockée du fournisseur IA (contenu JSON entouré d'une fence markdown).
     */
    private function fakeAiResponse(array $overrides = [])
    {
        $payload = array_merge([
            'risk_score' => 42,
            'risk_level' => 'medium',
            'summary' => 'La zone présente une activité d\'alerte modérée avec deux points de surveillance renforcés.',
            'recommendations' => [
                ['action' => 'Renforcer la surveillance du secteur nord', 'reason' => 'Hausse des alertes', 'priority' => 'high'],
                ['action' => 'Contrôler les vannes de la zone', 'reason' => 'Prévention', 'priority' => 'medium'],
                ['action' => 'Mettre à jour la fiche de risque', 'reason' => 'Traçabilité', 'priority' => 'low'],
            ],
        ], $overrides);

        return Http::response([
            'choices' => [
                ['message' => ['content' => "```json\n".json_encode($payload)."\n```"]],
            ],
        ], 200);
    }

    /* =========================================================================
     * Autorisation
     * ========================================================================= */

    public function test_guest_cannot_analyze_zone(): void
    {
        $zone = Zone::factory()->create();

        $this->postJson(route('back.zones.ai-analyze', $zone))->assertStatus(401);
    }

    public function test_citizen_cannot_analyze_zone(): void
    {
        $zone = Zone::factory()->create();

        $this->actingAs($this->citizen())
            ->postJson(route('back.zones.ai-analyze', $zone))
            ->assertStatus(403);
    }

    /* =========================================================================
     * Analyse avec IA mockée
     * ========================================================================= */

    public function test_manager_can_analyze_zone_with_mocked_ai(): void
    {
        $manager = $this->manager();
        $zone = Zone::factory()->create(['risk_level' => 'medium', 'description' => 'Zone de test.']);

        config(['ai.key' => 'test-key']);
        Http::fake(['api.groq.com/**' => $this->fakeAiResponse()]);

        $response = $this->actingAs($manager)->postJson(route('back.zones.ai-analyze', $zone));

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertFalse($data['queued']);
        $this->assertSame('ai', $data['analysis']['source']);
        $this->assertTrue($data['analysis']['is_ai_generated']);
        $this->assertSame(42, $data['analysis']['risk_score']);
        $this->assertCount(3, $data['analysis']['recommendations']);

        // Persistance des colonnes NULLABLE de la table zones
        $zone->refresh();
        $this->assertSame(42, $zone->ai_risk_score);
        $this->assertSame('medium', $zone->ai_risk_level);
        $this->assertNotEmpty($zone->ai_summary);
        $this->assertCount(3, $zone->ai_recommendations);
        $this->assertNotNull($zone->ai_analyzed_at);
    }

    public function test_zone_analysis_falls_back_when_ai_hits_rate_limit(): void
    {
        $manager = $this->manager();
        $zone = Zone::factory()->create(['risk_level' => 'low']);

        config(['ai.key' => 'test-key']);
        Http::fake(['api.groq.com/**' => Http::response(['error' => ['message' => 'Rate limit exceeded']], 429)]);

        $response = $this->actingAs($manager)->postJson(route('back.zones.ai-analyze', $zone));

        // Repli gracieux : jamais d'exception brute, résultats déterministes.
        $response->assertStatus(200);

        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertSame('deterministic', $data['analysis']['source']);
        $this->assertFalse($data['analysis']['is_ai_generated']);
        $this->assertNotEmpty($data['analysis']['notice']);
        $this->assertIsInt($data['analysis']['risk_score']);

        $zone->refresh();
        $this->assertNotNull($zone->ai_analyzed_at);
    }

    public function test_zone_analysis_falls_back_when_ai_returns_invalid_json(): void
    {
        $manager = $this->manager();
        $zone = Zone::factory()->create();

        config(['ai.key' => 'test-key']);
        Http::fake([
            'api.groq.com/**' => Http::response([
                'choices' => [['message' => ['content' => "```json\n{ceci n'est pas du json"]]],
            ], 200),
        ]);

        $response = $this->actingAs($manager)->postJson(route('back.zones.ai-analyze', $zone));

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertSame('deterministic', $data['analysis']['source']);
        $this->assertNotEmpty($data['analysis']['summary']);
        $this->assertGreaterThanOrEqual(3, count($data['analysis']['recommendations']));
        $this->assertLessThanOrEqual(5, count($data['analysis']['recommendations']));
    }

    /* =========================================================================
     * Cache, file d'attente, endpoint de lecture
     * ========================================================================= */

    public function test_zone_analysis_is_cached_between_two_calls(): void
    {
        $manager = $this->manager();
        $zone = Zone::factory()->create(['risk_level' => 'medium']);

        config(['ai.key' => 'test-key']);
        Http::fake(['api.groq.com/**' => $this->fakeAiResponse()]);

        $this->actingAs($manager)->postJson(route('back.zones.ai-analyze', $zone))->assertStatus(200);
        $this->actingAs($manager)->postJson(route('back.zones.ai-analyze', $zone))->assertStatus(200);

        // Le second appel est servi depuis le cache : une seule requête API.
        Http::assertSentCount(1);
    }

    public function test_zone_analysis_can_be_dispatched_to_queue(): void
    {
        Queue::fake();

        $manager = $this->manager();
        $zone = Zone::factory()->create();

        $response = $this->actingAs($manager)
            ->postJson(route('back.zones.ai-analyze', $zone), ['async' => true]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('queued'));

        Queue::assertPushed(AnalyzeZoneJob::class, fn (AnalyzeZoneJob $job) => $job->zoneId === $zone->id);
    }

    public function test_get_analysis_returns_persisted_zone_analysis(): void
    {
        $manager = $this->manager();
        $zone = Zone::factory()->create();

        $zone->forceFill([
            'ai_risk_score' => 73,
            'ai_risk_level' => 'high',
            'ai_summary' => 'Synthèse de test.',
            'ai_recommendations' => [
                ['action' => 'Action 1', 'reason' => '', 'priority' => 'high'],
                ['action' => 'Action 2', 'reason' => '', 'priority' => 'medium'],
                ['action' => 'Action 3', 'reason' => '', 'priority' => 'low'],
            ],
            'ai_analyzed_at' => now(),
        ])->save();

        $response = $this->actingAs($manager)->getJson(route('back.zones.get-ai-analysis', $zone));

        $response->assertStatus(200);
        $analysis = $response->json('analysis');

        $this->assertSame(73, $analysis['risk_score']);
        $this->assertSame('high', $analysis['risk_level']);
        $this->assertSame('Élevé', $analysis['risk_level_label']);
        $this->assertSame('Synthèse de test.', $analysis['summary']);
        $this->assertCount(3, $analysis['recommendations']);
    }

    public function test_get_analysis_returns_null_when_zone_was_never_analyzed(): void
    {
        $manager = $this->manager();
        $zone = Zone::factory()->create();

        $this->actingAs($manager)
            ->getJson(route('back.zones.get-ai-analysis', $zone))
            ->assertStatus(200)
            ->assertJson(['analysis' => null]);
    }

    public function test_artisan_analyze_zones_backfills_pending_zones(): void
    {
        $zone = Zone::factory()->create(['risk_level' => 'medium']);

        $this->assertNull($zone->ai_analyzed_at);

        $this->artisan('ai:analyze-zones')->assertExitCode(0);

        $zone->refresh();
        $this->assertNotNull($zone->ai_analyzed_at);
        $this->assertIsInt($zone->ai_risk_score);
        $this->assertContains($zone->ai_risk_level, ['low', 'medium', 'high', 'critical']);
    }

    /* =========================================================================
     * Intégration UI + confidentialité
     * ========================================================================= */

    public function test_zone_detail_page_shows_analyze_with_ai_button(): void
    {
        $zone = Zone::factory()->create();

        $this->actingAs($this->manager())
            ->get(route('back.zones.show', $zone))
            ->assertOk()
            ->assertSee('Analyser avec l\'IA', false)
            ->assertSee('INTELLIGENCE ARTIFICIELLE', false);
    }

    public function test_no_personal_data_is_sent_to_the_ai_provider(): void
    {
        $manager = $this->manager();
        $zone = Zone::factory()->create(['name' => 'Zone Sans Données Personnelles']);

        config(['ai.key' => 'test-key']);
        Http::fake(['api.groq.com/**' => $this->fakeAiResponse()]);

        $this->actingAs($manager)->postJson(route('back.zones.ai-analyze', $zone))->assertStatus(200);

        $recorded = Http::recorded();
        $this->assertCount(1, $recorded);

        $body = $recorded[0][0]->body();
        $this->assertStringNotContainsString($manager->email, $body);
        $this->assertStringNotContainsString('password', $body);
        // La zone est bien envoyée (les accents sont échappés en \uXXXX par le JSON).
        $this->assertStringContainsString('Zone Sans Donn', $body);
    }
}
