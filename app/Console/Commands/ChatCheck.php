<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Vérifie la santé de l'assistant conversationnel AquaSecure (Ollama local).
 *
 * Exemples :
 *   php artisan ai:chat-check
 *   php artisan ai:chat-check --timeout=10
 *
 * En cas de modèle absent, imprime la commande corrective exacte :
 *   ollama pull <modèle>
 */
class ChatCheck extends Command
{
    protected $signature = 'ai:chat-check
        {--timeout=5 : Délai maximal du ping en secondes}';

    protected $description = "Vérifie que le serveur Ollama local répond et que le modèle de l'assistant est installé";

    public function handle(): int
    {
        $base = rtrim((string) config('ai.chat.base_url', 'http://localhost:11434/v1'), '/');
        $model = (string) config('ai.chat.model', 'llama3.1:8b');
        $root = (string) preg_replace('#/v\d+$#', '', $base);
        $timeout = max(1, (int) $this->option('timeout'));

        $this->info('Configuration de l\'assistant :');
        $this->line('  - CHAT_AI_BASE_URL : '.$base);
        $this->line('  - CHAT_AI_MODEL    : '.$model);
        $this->line('  - CHAT_AI_TIMEOUT  : '.(int) config('ai.chat.timeout', 120).' s');
        $this->line('  - Historique       : '.(int) config('ai.chat.max_history', 10).' messages');
        $this->newLine();

        try {
            $response = Http::withHeaders(['Accept' => 'application/json'])
                ->connectTimeout($timeout)
                ->timeout($timeout)
                ->get($root.'/api/tags');
        } catch (ConnectionException $e) {
            $this->error("Ollama injoignable sur {$root}/api/tags (connexion refusée ou délai dépassé).");

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->error("Ollama a répondu avec le code HTTP {$response->status()}.");

            return self::FAILURE;
        }

        $models = array_values(array_map(
            fn ($entry): string => (string) ($entry['name'] ?? ''),
            is_array($response->json('models')) ? $response->json('models') : []
        ));

        $this->info('Ollama connecté ('.$root.').');

        $installed = collect($models)->contains(
            fn (string $name): bool => $name === $model || str_starts_with($name, $model.':') || $name === $model.':latest'
        );

        if ($installed) {
            $this->info("Modèle disponible : {$model}.");

            return self::SUCCESS;
        }

        $this->warn("Modèle MANQUANT : {$model}.");
        $this->newLine();
        $this->line('Correctif à exécuter :');
        $this->comment('ollama pull '.$model);
        $this->newLine();

        if ($models !== []) {
            $this->line('Modèles actuellement installés : '.implode(', ', $models));
        } else {
            $this->line('Aucun modèle installé.');
        }

        return self::FAILURE;
    }
}
