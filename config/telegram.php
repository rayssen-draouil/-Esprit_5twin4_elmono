<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Jeton du bot Telegram
    |--------------------------------------------------------------------------
    |
    | Jeton fourni par @BotFather (format : 123456789:AAF...).
    | Laisser vide désactive le bot : la commande `php artisan telegram:bot`
    | affichera un message d'erreur explicite.
    |
    */

    'token' => env('TELEGRAM_BOT_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Délai d'attente du long polling (secondes)
    |--------------------------------------------------------------------------
    */

    'polling_timeout' => 30,

    /*
    |--------------------------------------------------------------------------
    | Durée de vie d'une conversation (secondes)
    |--------------------------------------------------------------------------
    |
    | État d'une conversation en cours (/signaler, association du compte…).
    | Passé ce délai, l'utilisateur doit relancer la commande.
    |
    */

    'state_ttl' => 1800,
];
