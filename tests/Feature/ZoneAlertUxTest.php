<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nouvelles capacités Zones & Alertes : export CSV, rafraîchissement live,
 * actions groupées, workflow (acquitter / résoudre / réouvrir), filtres et pagination.
 */
class ZoneAlertUxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'manager']));
    }

    /* =========================================================================
     * Exports CSV
     * ========================================================================= */

    public function test_zones_export_streams_filtered_csv(): void
    {
        Zone::factory()->create(['name' => 'Zone Export Test', 'risk_level' => 'high']);

        $response = $this->get(route('back.zones.export'));

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Zone Export Test', $response->streamedContent());
        $this->assertStringContainsString('Nom', $response->streamedContent());
    }

    public function test_alerts_export_streams_csv(): void
    {
        Alert::factory()->create(['type' => 'Fuite Export', 'message' => 'Message exporté.']);

        $response = $this->get(route('back.alerts.export'));

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Fuite Export', $response->streamedContent());
    }

    public function test_exports_are_forbidden_for_citizens(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'citizen']));

        $this->get(route('back.zones.export'))->assertForbidden();
        $this->get(route('back.alerts.export'))->assertForbidden();
    }

    /* =========================================================================
     * Endpoint live
     * ========================================================================= */

    public function test_live_endpoint_returns_kpis_and_new_alerts(): void
    {
        Alert::factory()->create(['severity' => 'critical', 'resolved_at' => null]);

        $response = $this->getJson(route('back.alerts.live', [
            'since' => now()->subMinute()->toIso8601String(),
        ]));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['kpi' => ['open', 'critical', 'resolved_today', 'average_age']]);

        $this->assertGreaterThanOrEqual(1, $response->json('new_count'));
        $this->assertGreaterThanOrEqual(1, $response->json('new_critical_count'));
    }

    public function test_live_endpoint_is_forbidden_for_citizens(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'citizen']));

        $this->getJson(route('back.alerts.live'))->assertForbidden();
    }

    /* =========================================================================
     * Actions groupées
     * ========================================================================= */

    public function test_bulk_action_acknowledges_selected_alerts(): void
    {
        $first = Alert::factory()->create(['acknowledged_at' => null]);
        $second = Alert::factory()->create(['acknowledged_at' => null]);
        $untouched = Alert::factory()->create(['acknowledged_at' => null]);

        $response = $this->postJson(route('back.alerts.bulk'), [
            'action' => 'acknowledged',
            'ids' => [$first->id, $second->id],
        ]);

        $response->assertOk()->assertJsonPath('success', true)->assertJsonPath('updated', 2);

        $this->assertNotNull($first->fresh()->acknowledged_at);
        $this->assertNotNull($second->fresh()->acknowledged_at);
        $this->assertNull($untouched->fresh()->acknowledged_at);
    }

    public function test_bulk_action_validates_payload(): void
    {
        $this->postJson(route('back.alerts.bulk'), ['action' => 'destroy', 'ids' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['action', 'ids']);
    }

    /* =========================================================================
     * Workflow individuel
     * ========================================================================= */

    public function test_acknowledge_resolve_and_reopen_cycle(): void
    {
        $alert = Alert::factory()->create([
            'read_at' => null,
            'acknowledged_at' => null,
            'resolved_at' => null,
        ]);

        $this->post(route('back.alerts.acknowledge', $alert))->assertRedirect();
        $alert->refresh();
        $this->assertNotNull($alert->acknowledged_at);
        $this->assertNull($alert->resolved_at);
        $this->assertSame('acknowledged', $alert->status());

        $this->post(route('back.alerts.resolve', $alert))->assertRedirect();
        $alert->refresh();
        $this->assertNotNull($alert->resolved_at);
        $this->assertSame('resolved', $alert->status());

        $this->post(route('back.alerts.reopen', $alert))->assertRedirect();
        $alert->refresh();
        $this->assertNull($alert->resolved_at);
        $this->assertNull($alert->acknowledged_at);
        $this->assertSame('open', $alert->status());
    }

    /* =========================================================================
     * Filtres, regroupement et pagination
     * ========================================================================= */

    public function test_alerts_index_filters_by_severity(): void
    {
        Alert::factory()->create(['type' => 'Alpha Critique', 'severity' => 'critical']);
        Alert::factory()->create(['type' => 'Zeta Faible', 'severity' => 'low']);

        $response = $this->get(route('back.alerts.index', ['severity' => 'critical']));

        $response->assertOk()->assertSee('Alpha Critique')->assertDontSee('Zeta Faible');
    }

    public function test_alerts_index_supports_grouped_view(): void
    {
        Alert::factory()->create(['type' => 'Fuite Groupée', 'severity' => 'medium']);

        $response = $this->get(route('back.alerts.index', ['group' => 1]));

        $response->assertOk()->assertSee('aq-group', false)->assertSee('Fuite Groupée');
    }

    public function test_alerts_index_paginates_results(): void
    {
        Alert::factory()->count(20)->create();

        $response = $this->get(route('back.alerts.index'));

        $response->assertOk()->assertViewHas('alerts', function ($alerts) {
            // La pagination limite la page courante à 15 éléments.
            return $alerts->perPage() === 15 && $alerts->count() === 15 && $alerts->total() >= 20;
        });
    }

    public function test_zones_index_paginates_results(): void
    {
        Zone::factory()->count(15)->create();

        $response = $this->get(route('back.zones.index'));

        $response->assertOk()->assertViewHas('zones', function ($zones) {
            return $zones->total() === 15 && $zones->count() === 12;
        });
    }
}
