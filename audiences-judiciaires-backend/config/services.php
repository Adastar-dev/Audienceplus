<?php

return [

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Fournisseur d'IA pour la transcription audio (Whisper) et la lecture du
    // numéro de CNI (modèle de vision). Toute API au format OpenAI convient :
    // Groq par défaut (offre gratuite, sans carte bancaire) ; pour revenir à
    // OpenAI, IA_BASE_URL=https://api.openai.com/v1 et les modèles
    // whisper-1 / gpt-4o-mini.
    'ia' => [
        'base_url' => rtrim(env('IA_BASE_URL', 'https://api.groq.com/openai/v1'), '/'),
        'api_key' => env('IA_API_KEY'),
        'modele_transcription' => env('IA_MODELE_TRANSCRIPTION', 'whisper-large-v3'),
        'modele_vision' => env('IA_MODELE_VISION', 'qwen/qwen3.8-27b'),
    ],

    // Serveur Jitsi auto-heberge avec authentification JWT. Sans secret, on
    // retombe sur meet.jit.si (sans controle du role de modérateur).
    'jitsi' => [
        'domain' => env('JITSI_DOMAIN', 'localhost:8443'),
        'app_id' => env('JITSI_APP_ID', 'audiences_judiciaires'),
        'app_secret' => env('JITSI_APP_SECRET'),
        'audience' => env('JITSI_JWT_AUDIENCE', 'jitsi'),
        'xmpp_domain' => env('JITSI_XMPP_DOMAIN', 'meet.jitsi'),
    ],

];
