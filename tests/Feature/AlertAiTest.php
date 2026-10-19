<?php

namespace Tests\Feature;

use App\Jobs\AnalyzeAlertJob;
use App\Models\Alert;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Tests du module IA des alertes (classification automatique, explication,
 * actions, doublons), avec API IA mockée (Http::fake).
 */
class AlertAiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => 'manager']);
    }

    private function citizen(): User
    {
        return User::factory()->create(['role' => 'citizen']);
    }

    private function lowRiskZone(): Zone
    {
        return Zone::factory()->create(['risk_level' => 'low']);
    }

    private function fakeAiResponse(array $overrides = [])
    {
        $payload = array_merge([
            'severity' => 'warning',
            'explanation' => 'Une baisse de pression anormale a été détectée sur le réseau, susceptible d\'indiquer une fuite.',
            'probable_cause' => 'Rupture localisée d\'une canalisation secondaire en aval de la zone.',
            'recommended_actions' => [
                ['action' => 'Se rendre sur le point de mesure', 'reason' => 'Confirmer la baisse', 'priority' => 'high'],
                ['action' => 'Isoler le tronçon concerné', 'reason' => 'Limiter les pertes en eau', 'priority' => 'medium'],
                ['action' => 'Consigner les relevés', 'reason' => 'Traçabilité', 'priority' => 'low'],
            ],
            'confidence' => 'high',
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

    public function test_guest_cannot_analyze_alert(): void
    {
        $alert = Alert::factory()->create(['zone_id' => $this->lowRiskZone()->id]);

        $this->postJson(route('back.alerts.ai-analyze', $alert))->assertStatus(401);
    }

    public function test_citizen_cannot_analyze_alert(): void
    {
        $alert = Alert::factory()->create(['zone_id' => $this->lowRiskZone()->id]);

        $this->actingAs($this->citizen())
            ->postJson(route('back.alerts.ai-analyze', $alert))
            ->assertStatus(403);
    }

    /* =========================================================================
     * Classification automatique à la création (AlertObserver)
     * ========================================================================= */

    public function test_critical_alert_is_auto_classified_on_creation(): void
    {
        $zone = $this->lowRiskZone();

        $alert = Alert::factory()->create([
            'zone_id' => $zone->id,
            'type' => 'Fuite',
            'severity' => 'critical',
            'message' => 'Rupture urgente de la conduite principale avec écoulement important.',
        ]);

        $alert->refresh();

        $this->assertSame('critical', $alert->ai_severity);
        $this->assertNotEmpty($alert->ai_group_key);
        $this->assertNotNull($alert->ai_analyzed_at);
        $this->assertNotEmpty($alert->ai_explanation);
        $this->assertNotEmpty($alert->ai_recommended_actions);
    }

    public function test_low_severity_alert_is_classified_as_info(): void
    {
        $zone = $this->lowRiskZone();

        $alert = Alert::factory()->create([
            'zone_id' => $zone->id,
            'type' => 'Capteur',
            'severity' => 'low',
            'message' => 'Lecture de niveau légèrement hors plage sur le capteur secondaire.',
        ]);

        $this->assertSame('info', $alert->refresh()->ai_severity);
    }

    /* =========================================================================
     * Analyse avec IA mockée
     * ========================================================================= */

    public function test_manager_can_analyze_alert_with_mocked_ai(): void
    {
        $manager = $this->manager();
        $zone = $this->lowRiskZone();

        $alert = Alert::factory()->create([
            'zone_id' => $zone->id,
            'type' => 'Pression',
            'severity' => 'medium',
            'message' => 'Baisse de pression anormale détectée sur le réseau.',
        ]);

        config(['ai.key' => 'test-key']);
        Http::fake(['api.groq.com/**' => $this->fakeAiResponse()]);

        $response = $this->actingAs($manager)
            ->postJson(route('back.alerts.ai-analyze', $alert), ['force' => true]);

        $response->assertStatus(200);

        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertSame('ai', $data['analysis']['source']);
        $this->assertSame('warning', $data['analysis']['severity']);
        $this->assertSame('Avertissement', $data['analysis']['severity_label']);
        $this->assertNotEmpty($data['analysis']['explanation']);
        $this->assertNotEmpty($data['analysis']['probable_cause']);
        $this->assertGreaterThanOrEqual(3, count($data['analysis']['recommended_actions']));
        $this->assertLessThanOrEqual(5, count($data['analysis']['recommended_actions']));

        // Persistance des colonnes NULLABLE de la table alerts
        $alert->refresh();
        $this->assertSame('warning', $alert->ai_severity);
        $this->assertSame('Une baisse de pression anormale a été détectée sur le réseau, susceptible d\'indiquer une fuite.', $alert->ai_explanation);
        $this->assertSame('Rupture localisée d\'une canalisation secondaire en aval de la zone.', $alert->ai_probable_cause);
        $this->assertCount(3, $alert->ai_recommended_actions);
        $this->assertNotEmpty($alert->ai_group_key);
        $this->assertNotNull($alert->ai_analyzed_at);
    }

    public function test_alert_analysis_falls_back_when_ai_returns_invalid_json(): void
    {
        $manager = $this->manager();
        $alert = Alert::factory()->create(['zone_id' => $this->lowRiskZone()->id]);

        config(['ai.key' => 'test-key']);
        Http::fake([
            'api.groq.com/**' => Http::response([
                'choices' => [['message' => ['content' => '```json\n{payload invalide']]],
            ], 200),
        ]);

        $response = $this->actingAs($manager)
            ->postJson(route('back.alerts.ai-analyze', $alert), ['force' => true]);

        // Repli gracieux, jamais d'exception brute.
        $response->assertStatus(200);

        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertSame('deterministic', $data['analysis']['source']);
        $this->assertContains($data['analysis']['severity'], ['info', 'warning', 'critical']);
        $this->assertNotEmpty($data['analysis']['explanation']);
        $this->assertNotEmpty($data['analysis']['notice']);
    }

    /* =========================================================================
     * Détection de doublons
     * ========================================================================= */

    public function test_similar_alerts_in_the_same_zone_are_grouped(): void
    {
        $zone = $this->lowRiskZone();

        $first = Alert::factory()->create([
            'zone_id' => $zone->id,
            'type' => 'Fuite',
            'severity' => 'medium',
            'message' => 'Fuite importante sur la conduite principale du secteur nord.',
        ]);

        $second = Alert::factory()->create([
            'zone_id' => $zone->id,
            'type' => 'Fuite',
            'severity' => 'medium',
            'message' => 'Fuite importante sur la conduite principale du secteur nord.',
        ]);

        // Même clé de groupe = même phénomène regroupé.
        $this->assertSame($first->fresh()->ai_group_key, $second->fresh()->ai_group_key);

        $other = Alert::factory()->create([
            'zone_id' => $zone->id,
            'type' => 'Contamination',
            'severity' => 'high',
            'message' => 'Dépassement de turbidité relevé à l\'usine de traitement.',
        ]);

        $this->assertNotSame($first->fresh()->ai_group_key, $other->fresh()->ai_group_key);

        $response = $this->actingAs($this->manager())
            ->postJson(route('back.alerts.ai-analyze', $second), ['force' => true]);

        $response->assertStatus(200);

        $analysis = $response->json('analysis');
        $this->assertGreaterThanOrEqual(1, $analysis['duplicate_count']);
        $this->assertSame($first->id, $analysis['duplicates'][0]['id']);
    }

    /* =========================================================================
     * Cache, file d'attente, endpoint de lecture, commandes
     * ========================================================================= */

    public function test_alert_analysis_can_be_dispatched_to_queue(): void
    {
        Queue::fake();

        $alert = Alert::factory()->create(['zone_id' => $this->lowRiskZone()->id]);

        $response = $this->actingAs($this->manager())
            ->postJson(route('back.alerts.ai-analyze', $alert), ['async' => true]);

        $response->assertStatus(200);
        $this->assertTrue($response->json('queued'));

        Queue::assertPushed(
            AnalyzeAlertJob::class,
            fn (AnalyzeAlertJob $job) => $job->alertId === $alert->id
        );
    }

    public function test_get_analysis_returns_persisted_alert_analysis(): void
    {
        $manager = $this->manager();
        $zone = $this->lowRiskZone();

        $alert = Alert::factory()->create(['zone_id' => $zone->id, 'type' => 'Fuite']);

        $response = $this->actingAs($manager)->getJson(route('back.alerts.get-ai-analysis', $alert));

        $response->assertStatus(200);

        $analysis = $response->json('analysis');
        $this->assertNotNull($analysis);
        $this->assertContains($analysis['severity'], ['info', 'warning', 'critical']);
        $this->assertNotEmpty($analysis['explanation']);
        $this->assertNotEmpty($analysis['group_key']);
        $this->assertNotNull($analysis['analyzed_at_display']);
    }

    public function test_artisan_analyze_alerts_backfills_pending_alerts(): void
    {
        config(['ai.auto_analyze_on_create' => false]);

        $alert = Alert::factory()->create(['zone_id' => $this->lowRiskZone()->id]);

        $this->assertNull($alert->ai_analyzed_at);

        $this->artisan('ai:analyze-alerts')->assertExitCode(0);

        $alert->refresh();
        $this->assertNotNull($alert->ai_analyzed_at);
        $this->assertContains($alert->ai_severity, ['info', 'warning', 'critical']);
    }

    /* =========================================================================
     * Intégration UI
     * ========================================================================= */

    public function test_alert_detail_page_shows_analyze_with_ai_button(): void
    {
        $alert = Alert::factory()->create(['zone_id' => $this->lowRiskZone()->id]);

        $this->actingAs($this->manager())
            ->get(route('back.alerts.show', $alert))
            ->assertOk()
            ->assertSee('Analyser avec l\'IA', false)
            ->assertSee('INTELLIGENCE ARTIFICIELLE', false);
    }
}
