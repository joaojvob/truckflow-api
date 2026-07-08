<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google_maps' => [
        'api_key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Provedor de geolocalização (roteamento e places)
    |--------------------------------------------------------------------------
    |
    | google_maps — chamada direta à Google Maps API (padrão)
    | java        — microserviço truckflow-geo (Spring Boot)
    |
    */
    'geo' => [
        // google_maps | java | haversine (fallback offline, sem API externa)
        'driver'   => env('GEO_ROUTING_DRIVER', 'haversine'),
        'java_url' => env('GEO_JAVA_SERVICE_URL', 'http://truckflow-geo:8081'),
        // Fator de sinuosidade aplicado à distância em linha reta (Haversine).
        'road_factor'      => (float) env('GEO_ROAD_FACTOR', 1.3),
        // Velocidade média (km/h) usada para estimar duração no modo Haversine.
        'avg_speed_kmh'    => (float) env('GEO_AVG_SPEED_KMH', 65),
    ],

    /*
    |--------------------------------------------------------------------------
    | Geocodificação de CEP (endereço + coordenadas)
    |--------------------------------------------------------------------------
    |
    | brasilapi — BrasilAPI/ViaCEP (padrão, gratuito)
    |
    */
    'geocoding' => [
        'driver' => env('GEOCODING_DRIVER', 'brasilapi'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Microserviço fiscal (CT-e)
    |--------------------------------------------------------------------------
    |
    | java — truckflow-fiscal (Spring Boot), emissão mock de CT-e
    |
    */
    'fiscal' => [
        'driver'   => env('FISCAL_DRIVER', 'java'),
        'java_url' => env('FISCAL_JAVA_SERVICE_URL', 'http://truckflow-fiscal:8082'),
    ],

];
