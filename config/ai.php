<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AquaSecure AI — Configuration du fournisseur d'IA
    |--------------------------------------------------------------------------
    |
    | Toutes les valeurs proviennent du fichier .env. Aucune clé n'est jamais
    | codée en dur dans le code applicatif.
    |
    */

    // Clé API du fournisseur (ex: GROQ). Le repli sur GROQ_API_KEY conserve
    // la rétro-compatibilité avec la configuration existante du projet.
    'key' => env('AI_API_KEY', env('GROQ_API_KEY')),

    'model' => env('AI_MODEL', env('GROQ_MODEL', 'openai/gpt-oss-120b')),

    'base_url' => env('AI_BASE_URL', env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1')),

    // Délai maximal d'attente de la réponse IA (secondes).
    'timeout' => (int) env('AI_TIMEOUT', 45),

    'connect_timeout' => (int) env('AI_CONNECT_TIMEOUT', 10),

    'temperature' => (float) env('AI_TEMPERATURE', 0.2),

    // Durée de vie du cache des analyses (secondes).
    'cache_ttl' => (int) env('AI_CACHE_TTL', 600),

    // Déclenche automatiquement l'enrichissement IA lors de la création d'une alerte.
    'auto_analyze_on_create' => (bool) env('AI_AUTO_ANALYZE_ON_CREATE', true),

    /*
    |--------------------------------------------------------------------------
    | AquaSecure AI — Assistant conversationnel (chatbot)
    |--------------------------------------------------------------------------
    |
    | Serveur Ollama local exposant l'API compatible OpenAI (/v1). Section
    | indépendante de l'analyse IA ci-dessus : elle ne réutilise jamais la
    | clé du fournisseur cloud (Groq) et ne la remplace pas non plus.
    |
    */

    'chat' => [
        'base_url' => env('CHAT_AI_BASE_URL', 'http://localhost:11434/v1'),

        'key' => env('CHAT_AI_API_KEY', 'ollama'),

        'model' => env('CHAT_AI_MODEL', 'llama3.1:8b'),

        // Ollama local est parfois lent au premier jeton (chargement du modèle).
        'timeout' => (int) env('CHAT_AI_TIMEOUT', 120),

        // Nombre maximal de messages d'historique conservés en session.
        'max_history' => (int) env('CHAT_AI_MAX_HISTORY', 10),
    ],

];
