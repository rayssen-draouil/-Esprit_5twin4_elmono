<?php

namespace App\Services\Telegram;

use App\Models\Alert;
use App\Models\User;
use App\Models\UserZoneSubscription;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Prévient les citoyens qui suivent une zone lorsqu'une nouvelle alerte y est
 * créée.
 *
 * Le dispositif est désactivé par défaut (`aquasecure.telegram_notify`) et
 * n'expose que des informations publiques. Toute erreur est absorbée : une
 * panne Telegram ne doit jamais empêcher la création d'une alerte.
 */
class ZoneAlertNotifier
{
    public function notify(Alert $alert): void
    {
        if (! config('aquasecure.telegram_notify', false)) {
            return;
        }

        if (! $alert->zone_id) {
            return;
        }

        try {
            $api = app(TelegramApi::class);

            if (! $api->isConfigured()) {
                return;
            }

            $subscriberIds = UserZoneSubscription::query()
                ->where('zone_id', $alert->zone_id)
                ->pluck('user_id');

            if ($subscriberIds->isEmpty()) {
                return;
            }

            $chatIds = User::query()
                ->whereIn('id', $subscriberIds)
                ->whereNotNull('telegram_chat_id')
                ->pluck('telegram_chat_id')
                ->filter()
                ->unique()
                ->values();

            if ($chatIds->isEmpty()) {
                return;
            }

            $alert->loadMissing('zone');
            $message = $this->message($alert);

            foreach ($chatIds as $chatId) {
                try {
                    $api->sendMessage((string) $chatId, $message);
                } catch (Throwable $e) {
                    Log::warning('Telegram zone alert notification failed', [
                        'alert_id' => $alert->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (Throwable $e) {
            Log::warning('Telegram zone alert notifier failed', [
                'alert_id' => $alert->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function message(Alert $alert): string
    {
        $zone = $alert->zone?->name ?? 'Zone non définie';
        $type = $alert->type ?: 'Alerte réseau';
        $severity = $alert->severity_label;
        $summary = \Illuminate\Support\Str::limit((string) $alert->message, 300);
        $url = route('front.alerts.show', $alert);

        return "🚨 <b>Alerte AquaSecure</b>\n"
            .'Zone : <b>'.e($zone)."</b>\n"
            .'Type : '.e($type)."\n"
            .'Gravité : '.e($severity)."\n\n"
            .($summary !== '' ? e($summary)."\n\n" : '')
            .'<a href="'.e($url).'">Voir l’alerte et les consignes</a>';
    }
}
