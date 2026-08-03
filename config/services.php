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

    /*
    |--------------------------------------------------------------------------
    | Cloudflare (Module 9)
    |--------------------------------------------------------------------------
    |
    | Per-website zone tokens live encrypted on the `cloudflare_zones` table
    | (each website connects its own token/zone). Tunnels are account-scoped
    | in Cloudflare's own API (not zone-scoped), so a single account-level
    | token/ID pair is configured here instead - there's one Cloudflare
    | account per MTP Deploy installation, not one per website.
    |
    */

    'cloudflare' => [
        'base_url' => env('CLOUDFLARE_API_BASE_URL', 'https://api.cloudflare.com/client/v4'),
        'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
        'account_api_token' => env('CLOUDFLARE_ACCOUNT_API_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Docker (Module 19)
    |--------------------------------------------------------------------------
    |
    | Docker Engine's own REST API (https://docs.docker.com/engine/api/) -
    | normally reachable only via a local Unix socket
    | (/var/run/docker.sock), which this app talks to over a TCP endpoint
    | instead (e.g. via a `socat` bridge, or Docker's own `-H tcp://...`
    | flag) since Laravel's HTTP client speaks HTTP, not raw Unix sockets.
    | Exposing the Docker socket over TCP without TLS is a real root-
    | equivalent privilege escalation risk if it's reachable from anywhere
    | but localhost - see docs/Security.md.
    |
    */

    'docker' => [
        'base_url' => env('DOCKER_API_BASE_URL', 'http://localhost:2375'),
    ],

];
