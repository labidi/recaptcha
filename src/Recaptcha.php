<?php

declare(strict_types=1);

namespace Labidi\Recaptcha;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Labidi\Recaptcha\Exceptions\InvalidConfigurationException;
use Labidi\Recaptcha\Exceptions\RecaptchaException;
use Labidi\Recaptcha\Rules\RecaptchaRule;
use Symfony\Component\HttpFoundation\IpUtils;
use Throwable;

/**
 * Verifies reCAPTCHA tokens and exposes the settings the front end needs.
 *
 * Verification goes through Laravel's HTTP client, so applications can drive
 * it with Http::fake() in their own tests.
 */
class Recaptcha
{
    public const V2_CHECKBOX = 'v2-checkbox';

    public const V2_INVISIBLE = 'v2-invisible';

    public const V3 = 'v3';

    public const VERSIONS = [self::V2_CHECKBOX, self::V2_INVISIBLE, self::V3];

    /**
     * Global callback Google calls once api.js is ready. Blade output and the
     * shipped JS module share it, so they cooperate on one page.
     */
    public const ONLOAD_CALLBACK = '__labidiRecaptchaOnload';

    /**
     * The field Google's v2 widget always posts its token under.
     */
    public const V2_FIELD_NAME = 'g-recaptcha-response';

    /**
     * @param  array<string, mixed>|null  $config  settings to use instead of
     *                                             the live "recaptcha" config
     */
    public function __construct(protected ?array $config = null) {}

    /**
     * Verify a token against Google.
     *
     * Transport problems yield a failed response rather than an exception, so
     * a form never returns a 500 because Google was briefly unreachable.
     *
     * @param  string|null  $action  the expected v3 action, if any
     * @param  string|null  $ip  the end user's IP address, passed to Google
     */
    public function verify(?string $token, ?string $action = null, ?string $ip = null): RecaptchaResponse
    {
        if ($this->shouldSkip($ip)) {
            return RecaptchaResponse::skipped();
        }

        if ($token === null || trim($token) === '') {
            return RecaptchaResponse::failed(RecaptchaResponse::E_MISSING_TOKEN);
        }

        $response = $this->callGoogle($token, $ip);

        if ($response->isFailure()) {
            return $response;
        }

        return $this->applyLocalChecks($response, $action);
    }

    /**
     * Verify a token, throwing when it does not pass.
     *
     * @throws RecaptchaException
     */
    public function verifyOrFail(?string $token, ?string $action = null, ?string $ip = null): RecaptchaResponse
    {
        $response = $this->verify($token, $action, $ip);

        if ($response->isFailure()) {
            throw RecaptchaException::forResponse($response);
        }

        return $response;
    }

    /**
     * Send the token to Google's siteverify endpoint.
     */
    protected function callGoogle(string $token, ?string $ip): RecaptchaResponse
    {
        $secret = $this->secretKey();

        if ($secret === null || $secret === '') {
            throw InvalidConfigurationException::missingSecretKey();
        }

        $parameters = array_filter([
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $ip,
        ], static fn (?string $value): bool => $value !== null && $value !== '');

        try {
            $response = Http::asForm()
                ->timeout($this->intConfig('http.timeout', 5))
                ->connectTimeout($this->intConfig('http.connect_timeout', 3))
                ->retry(
                    // Laravel counts attempts, so one retry means two attempts.
                    max($this->intConfig('http.retries', 1), 0) + 1,
                    $this->intConfig('http.retry_delay', 100),
                    // Tokens are single use: only retry when the request
                    // cannot have reached Google, or a retry would be
                    // rejected as a duplicate.
                    static fn (Throwable $e): bool => $e instanceof ConnectionException,
                    throw: false,
                )
                ->post($this->verifyUrl(), $parameters);
        } catch (ConnectionException|RequestException) {
            return RecaptchaResponse::failed(RecaptchaResponse::E_CONNECTION_FAILED);
        }

        if (! $response->successful()) {
            return RecaptchaResponse::failed(RecaptchaResponse::E_CONNECTION_FAILED);
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return RecaptchaResponse::failed(RecaptchaResponse::E_INVALID_JSON);
        }

        /** @var array<string, mixed> $payload */
        return RecaptchaResponse::fromArray($payload);
    }

    /**
     * Apply the checks Google leaves to the integrator: score, action,
     * hostname and freshness.
     */
    protected function applyLocalChecks(RecaptchaResponse $response, ?string $action): RecaptchaResponse
    {
        if (! $response->passesThreshold($this->thresholdFor($action))) {
            $response = $response->failWith(RecaptchaResponse::E_SCORE_THRESHOLD_NOT_MET);
        }

        if ($this->boolConfig('verify.action', true) && ! $response->matchesAction($action)) {
            $response = $response->failWith(RecaptchaResponse::E_ACTION_MISMATCH);
        }

        if ($this->boolConfig('verify.hostname', false) && ! $this->hostnameMatches($response->hostname())) {
            $response = $response->failWith(RecaptchaResponse::E_HOSTNAME_MISMATCH);
        }

        $timeout = $this->config('verify.challenge_timeout');

        if (is_numeric($timeout) && $response->isExpired((int) $timeout)) {
            $response = $response->failWith(RecaptchaResponse::E_CHALLENGE_TIMEOUT);
        }

        return $response;
    }

    /**
     * Compare Google's hostname against the application host.
     */
    protected function hostnameMatches(?string $hostname): bool
    {
        if ($hostname === null) {
            return true;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! is_string($appHost) || $appHost === '') {
            return true;
        }

        return strcasecmp($hostname, $appHost) === 0;
    }

    /**
     * Whether verification should be skipped for this request.
     */
    public function shouldSkip(?string $ip = null): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        if ($this->inSkippedEnvironment()) {
            return true;
        }

        /** @var list<string> $ips */
        $ips = (array) $this->config('skip_ips', []);

        return $ip !== null && $ips !== [] && IpUtils::checkIp($ip, $ips);
    }

    /**
     * Whether the current environment is listed in "skip_environments".
     */
    public function inSkippedEnvironment(): bool
    {
        /** @var list<string> $environments */
        $environments = (array) $this->config('skip_environments', []);

        return $environments !== [] && app()->environment($environments);
    }

    /**
     * Whether the Blade helpers should output markup.
     *
     * A missing site key is a configuration error, except in skipped
     * environments where applications commonly run without keys.
     *
     * @throws InvalidConfigurationException
     */
    public function shouldRender(): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        if ($this->siteKey() !== null) {
            return true;
        }

        if ($this->inSkippedEnvironment()) {
            return false;
        }

        throw InvalidConfigurationException::missingSiteKey();
    }

    /**
     * The score a token must reach, taking per action overrides into account.
     */
    public function thresholdFor(?string $action = null): float
    {
        if ($action !== null) {
            // Read the map directly: action names may contain dots, which
            // Arr::get() would treat as nesting.
            $actions = $this->config('score.actions', []);
            $override = is_array($actions) ? ($actions[$action] ?? null) : null;

            if (is_numeric($override)) {
                return (float) $override;
            }
        }

        $threshold = $this->config('score.threshold', 0.5);

        return is_numeric($threshold) ? (float) $threshold : 0.5;
    }

    /**
     * A validation rule bound to the given v3 action.
     */
    public function rule(?string $action = null): RecaptchaRule
    {
        return new RecaptchaRule($action);
    }

    public function isEnabled(): bool
    {
        return (bool) $this->config('enabled', true);
    }

    public function version(): string
    {
        $version = (string) $this->config('version', self::V3);

        if (! in_array($version, self::VERSIONS, strict: true)) {
            throw InvalidConfigurationException::unsupportedVersion($version);
        }

        return $version;
    }

    public function isV3(): bool
    {
        return $this->version() === self::V3;
    }

    public function isV2(): bool
    {
        return ! $this->isV3();
    }

    public function isInvisible(): bool
    {
        return $this->version() === self::V2_INVISIBLE;
    }

    public function siteKey(): ?string
    {
        $key = $this->config('site_key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    public function secretKey(): ?string
    {
        $key = $this->config('secret_key');

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * The request field carrying the token.
     *
     * Google's v2 widget always posts "g-recaptcha-response", so the
     * configured name only applies to v3.
     */
    public function fieldName(): string
    {
        if ($this->isV2()) {
            return self::V2_FIELD_NAME;
        }

        $name = $this->config('field_name');

        return is_string($name) && $name !== '' ? $name : self::V2_FIELD_NAME;
    }

    public function verifyUrl(): string
    {
        return (string) $this->config('verify_url', 'https://www.google.com/recaptcha/api/siteverify');
    }

    /**
     * The URL of Google's api.js.
     *
     * v3 passes the site key as "render"; v2 uses explicit rendering so
     * widgets work when they are added after the page loads (as in an
     * Inertia app). Both report readiness through the shared onload callback.
     */
    public function scriptUrl(): string
    {
        $url = (string) $this->config('script_url', 'https://www.google.com/recaptcha/api.js');

        $query = array_filter([
            'render' => $this->isV3() ? $this->siteKey() : 'explicit',
            'onload' => self::ONLOAD_CALLBACK,
            'hl' => $this->config('attributes.language'),
        ], static fn (mixed $value): bool => is_string($value) && $value !== '');

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($query);
    }

    /**
     * The public settings shared with the browser.
     *
     * The secret key is deliberately absent: this payload is embedded in HTML
     * and in Inertia props.
     *
     * @return array{enabled: bool, version: string, site_key: string|null, script_url: string, field_name: string, theme: string|null, size: string|null, badge: string|null, language: string|null}
     */
    public function toArray(): array
    {
        return [
            'enabled' => $this->isEnabled() && $this->siteKey() !== null,
            'version' => $this->version(),
            'site_key' => $this->siteKey(),
            'script_url' => $this->scriptUrl(),
            'field_name' => $this->fieldName(),
            'theme' => $this->stringConfig('attributes.theme'),
            'size' => $this->isInvisible() ? 'invisible' : $this->stringConfig('attributes.size'),
            'badge' => $this->stringConfig('attributes.badge'),
            'language' => $this->stringConfig('attributes.language'),
        ];
    }

    /**
     * Read a package setting, or the whole configuration when no key is given.
     */
    public function config(?string $key = null, mixed $default = null): mixed
    {
        // Read the live repository by default so runtime changes (tests,
        // Octane workers, per-tenant setups) are always honoured.
        $config = $this->config ?? (array) config('recaptcha', []);

        if ($key === null) {
            return $config;
        }

        return Arr::get($config, $key, $default);
    }

    protected function intConfig(string $key, int $default): int
    {
        $value = $this->config($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    protected function boolConfig(string $key, bool $default): bool
    {
        $value = $this->config($key, $default);

        return is_bool($value) ? $value : (bool) $value;
    }

    protected function stringConfig(string $key): ?string
    {
        $value = $this->config($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
