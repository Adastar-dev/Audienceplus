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

    'orange' => [
        'client_id' => env('ORANGE_CLIENT_ID'),
        'client_secret' => env('ORANGE_CLIENT_SECRET'),
        // Format tel:+221XXXXXXXXX, fourni par Orange lors de l'approbation de l'app.
        'sender_address' => env('ORANGE_SENDER_ADDRESS'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
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
