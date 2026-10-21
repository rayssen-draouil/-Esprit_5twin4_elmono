<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Services\Telegram\TelegramApi;
use Illuminate\Contracts\Console\Kernel;

$api = new TelegramApi;

// Reproduit le clavier de /signaler : 1 bouton par ligne
$rows = [];
foreach (['Fuite d\'eau', 'Contamination'] as $i => $t) {
    $rows[] = [['text' => $t, 'callback_data' => 'sgt:'.$i]];
}

try {
    $r = $api->sendMessage(1211804312, '🧭 Test clavier', ['reply_markup' => ['inline_keyboard' => $rows]]);
    echo 'inline_keyboard OK: message_id='.$r['message_id']."\n";
} catch (Throwable $e) {
    echo 'inline_keyboard ERREUR: '.$e->getMessage()."\n";
}
