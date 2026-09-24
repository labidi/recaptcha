<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Enable reCAPTCHA
    |--------------------------------------------------------------------------
    |
    | When disabled, every verification passes and the Blade helpers render
    | nothing. This lets local and CI environments run without valid keys.
    |
    */

    'enabled' => env('RECAPTCHA_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | reCAPTCHA Version
    |--------------------------------------------------------------------------
    |
    | Supported: "v2-checkbox", "v2-invisible", "v3".
    |
    | Switching this value changes both the widget the Blade components render
    | and the way tokens are verified, so an application can move between the
    | score based (v3) and challenge based (v2) flows by editing one line.
    |
    */

    'version' => env('RECAPTCHA_VERSION', 'v3'),

    /*
    |--------------------------------------------------------------------------
    | Credentials
    |--------------------------------------------------------------------------
    |
    | Register a site at https://www.google.com/recaptcha/admin to obtain a
    | key pair. The site key is public and is exposed to the browser; the
    | secret key must never leave the server.
    |
    */

    'site_key' => env('RECAPTCHA_SITE_KEY'),

    'secret_key' => env('RECAPTCHA_SECRET_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Endpoints
    |--------------------------------------------------------------------------
    |
    | Where tokens are verified and where the browser loads the widget from.
    | In regions where google.com is unreachable, swap the host for
    | "www.recaptcha.net", which Google serves as an official mirror:
    |
    |   RECAPTCHA_VERIFY_URL=https://www.recaptcha.net/recaptcha/api/siteverify
    |   RECAPTCHA_SCRIPT_URL=https://www.recaptcha.net/recaptcha/api.js
    |
    */

    'verify_url' => env('RECAPTCHA_VERIFY_URL', 'https://www.google.com/recaptcha/api/siteverify'),

    'script_url' => env('RECAPTCHA_SCRIPT_URL', 'https://www.google.com/recaptcha/api.js'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client
    |--------------------------------------------------------------------------
    |
    | Timeouts applied when talking to Google. Keep them short: a slow
    | verification call blocks the request that is being validated.
    |
    | "retries" only applies to connection failures. Tokens are single use,
    | so a request that reached Google is never sent twice.
    |
    */

    'http' => [
        'timeout' => (int) env('RECAPTCHA_TIMEOUT', 5),
        'connect_timeout' => (int) env('RECAPTCHA_CONNECT_TIMEOUT', 3),
        'retries' => (int) env('RECAPTCHA_RETRIES', 1),
        'retry_delay' => (int) env('RECAPTCHA_RETRY_DELAY', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Score Thresholds (v3 only)
    |--------------------------------------------------------------------------
    |
    | reCAPTCHA v3 returns a score between 0.0 (very likely a bot) and 1.0
    | (very likely a human). Tokens scoring below the threshold are rejected.
    |
    | The "actions" array overrides the default per action name, so sensitive
    | endpoints can demand more confidence than the rest of the application.
    |
    */

    'score' => [
        'threshold' => (float) env('RECAPTCHA_SCORE_THRESHOLD', 0.5),

        'actions' => [
            // 'login' => 0.7,
            // 'register' => 0.6,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Additional Verification
    |--------------------------------------------------------------------------
    |
    | Checks performed locally on Google's response.
    |
    | "action"   - v3 only: the action returned must match the expected one.
    | "hostname" - the hostname must match the application host. Disabled by
    |              default because proxies and multi domain setups break it.
    | "challenge_timeout" - reject tokens older than this many seconds, or
    |              null to accept any token Google still considers valid.
    |
    */

    'verify' => [
        'action' => env('RECAPTCHA_VERIFY_ACTION', true),
        'hostname' => env('RECAPTCHA_VERIFY_HOSTNAME', false),
        'challenge_timeout' => env('RECAPTCHA_CHALLENGE_TIMEOUT'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Request Field
    |--------------------------------------------------------------------------
    |
    | The input name carrying the token. "g-recaptcha-response" is the name the
    | official widget uses, so change it only for custom v3 forms.
    |
    */

    'field_name' => env('RECAPTCHA_FIELD_NAME', 'g-recaptcha-response'),

    /*
    |--------------------------------------------------------------------------
    | Widget & Script Attributes
    |--------------------------------------------------------------------------
    |
    | Defaults for the rendered markup. Every value can be overridden per use
    | on the Blade component, e.g. <x-recaptcha::widget theme="dark" />.
    |
    | "language" is Google's "hl" parameter (e.g. "fr", "ar"); null uses the
    | visitor's browser language.
    |
    */

    'attributes' => [
        'theme' => env('RECAPTCHA_THEME', 'light'),
        'size' => env('RECAPTCHA_SIZE', 'normal'),
        'badge' => env('RECAPTCHA_BADGE', 'bottomright'),
        'language' => env('RECAPTCHA_LANGUAGE'),
        'nonce' => null,
        'defer' => true,
        'async' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Inertia.js
    |--------------------------------------------------------------------------
    |
    | When "share" is enabled and Inertia is installed, the public settings
    | (site key, version, script URL - never the secret) are shared with every
    | Inertia response under the given prop name, so Vue and React components
    | read the same configuration as Blade.
    |
    */

    'inertia' => [
        'share' => env('RECAPTCHA_INERTIA_SHARE', true),
        'prop' => env('RECAPTCHA_INERTIA_PROP', 'recaptcha'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bypass Rules
    |--------------------------------------------------------------------------
    |
    | Environments and IP addresses that skip verification entirely. Keeping
    | "testing" here means feature tests do not need to fake HTTP calls.
    |
    | "skip_ips" accepts single addresses and CIDR ranges, for IPv4 and IPv6,
    | e.g. ['127.0.0.1', '10.0.0.0/8', '2001:db8::/32'].
    |
    */

    'skip_environments' => ['testing'],

    'skip_ips' => [],

];
