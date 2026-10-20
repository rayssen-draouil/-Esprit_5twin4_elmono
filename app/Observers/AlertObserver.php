<?php

namespace App\Observers;

use App\Jobs\AnalyzeAlertJob;
use App\Models\Alert;
use App\Services\AI\AlertAiService;
use App\Services\Telegram\ZoneAlertNotifier;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Lorsqu'une alerte est créée :
 *  1. classification de gravité immédiate (info / warning / critical) + clé de
 *     groupe pour la détection de doublons (déterministe, aucun appel réseau) ;
 *  2. enrichissement IA complet (explication, cause probable, actions)
 *     envoyé en file d'attente pour ne pas ralentir la création.
 *
 * Tout est encapsulé try/catch : une panne du module IA ne doit jamais
 * empêcher la création d'une alerte (rétro-compatibilité CRUD / Telegram).
 */
class AlertObserver
{
    public function created(Alert $alert): void
    {
        try {
            app(AlertAiService::class)->classifyOnCreate($alert);
        } catch (Throwable $e) {
            Log::warning('Alert AI classification failed on creation', [
                'alert_id' => $alert->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            app(ZoneAlertNotifier::class)->notify($alert);
        } catch (Throwable $e) {
            Log::warning('Zone alert notification failed', [
                'alert_id' => $alert->id,
                'error' => $e->getMessage(),
            ]);
        }

        if (! config('ai.auto_analyze_on_create', true)) {
            return;
        }

        try {
            AnalyzeAlertJob::dispatch($alert->id);
        } catch (Throwable $e) {
            Log::warning('Alert AI job dispatch failed', [
                'alert_id' => $alert->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
