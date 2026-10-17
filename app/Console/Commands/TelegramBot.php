<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramApi;
use App\Services\Telegram\TelegramBotService;
use App\Services\Telegram\TelegramException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramBot extends Command
{
    protected $signature = 'telegram:bot
        {--once : Traiter un seul lot de mises à jour puis quitter}
        {--register : Enregistrer le menu de commandes auprès de Telegram puis quitter}';

    protected $description = 'Démarre le bot Telegram AquaSecure (long polling)';

    public function handle(TelegramApi $api, TelegramBotService $service): int
    {
        if (! $api->isConfigured()) {
            $this->error('TELEGRAM_BOT_TOKEN est manquant dans le fichier .env.');
            $this->line('1. Créez un bot avec @BotFather sur Telegram et copiez le jeton.');
            $this->line('2. Ajoutez TELEGRAM_BOT_TOKEN=<votre_jeton> au fichier .env');
            $this->line('3. Relancez : php artisan telegram:bot');

            return self::FAILURE;
        }

        // Menu de commandes proposé par Telegram (Suggestions de commande).
        try {
            $api->setMyCommands(
                collect(TelegramBotService::COMMANDS)
                    ->map(fn (string $description, string $command) => [
                        'command' => $command,
                        'description' => $description,
                    ])
                    ->values()
                    ->all()
            );
            $api->deleteWebhook();
        } catch (TelegramException $exception) {
            $this->warn('Menu de commandes non enregistré : '.$exception->getMessage());
        }

        if ($this->option('register')) {
            $this->info('Menu de commandes enregistré auprès de Telegram.');

            return self::SUCCESS;
        }

        $timeout = $this->option('once') ? 0 : (int) config('telegram.polling_timeout', 30);

        $this->info("🤖 Bot Telegram AquaSecure démarré (polling, timeout {$timeout}s).");
        $this->line('   Commandes : '.implode(', ', array_map(fn ($c) => '/'.$c, array_keys(TelegramBotService::COMMANDS))));
        $this->line('   Arrêt : Ctrl+C');

        $offset = null;

        while (true) {
            try {
                $updates = $api->getUpdates($offset, $timeout);
            } catch (Throwable $exception) {
                Log::error('Telegram getUpdates: '.$exception->getMessage());
                $this->error('Erreur de polling : '.$exception->getMessage());

                if ($this->option('once')) {
                    return self::FAILURE;
                }

                sleep(5);

                continue;
            }

            foreach ($updates as $update) {
                $offset = ((int) ($update['update_id'] ?? 0)) + 1;

                try {
                    $service->handle($update);
                } catch (Throwable $exception) {
                    Log::error('Telegram handle update: '.$exception->getMessage(), [
                        'update' => $update,
                    ]);
                }
            }

            if ($this->option('once')) {
                $this->line('Batch traité : '.count($updates).' mise(s) à jour.');

                return self::SUCCESS;
            }
        }
    }
}
