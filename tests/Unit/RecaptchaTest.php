<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Labidi\Recaptcha\Exceptions\InvalidConfigurationException;
use Labidi\Recaptcha\Exceptions\RecaptchaException;
use Labidi\Recaptcha\Facades\Recaptcha;
use Labidi\Recaptcha\RecaptchaResponse;
use Labidi\Recaptcha\Tests\TestCase;

it('verifies a valid v3 token and sends the expected form fields', function () {
    fakeGoogle(v3Success());

    $response = Recaptcha::verify('token-123', 'login', '203.0.113.9');

    expect($response->isSuccess())->toBeTrue()
        ->and($response->score())->toBe(0.9);

    Http::assertSent(fn (Request $request) => $request->url() === TestCase::VERIFY_URL
        && $request->method() === 'POST'
        && $request->isForm()
        && $request['secret'] === TestCase::SECRET_KEY
        && $request['response'] === 'token-123'
        && $request['remoteip'] === '203.0.113.9');
});

it('omits remoteip when no IP is known', function () {
    fakeGoogle(v3Success());

    Recaptcha::verify('token', 'login');

    Http::assertSent(fn (Request $request) => ! array_key_exists('remoteip', $request->data()));
});

it('rejects a score below the default threshold', function () {
    fakeGoogle(v3Success(score: 0.3));

    $response = Recaptcha::verify('token', 'login');

    expect($response->isFailure())->toBeTrue()
        ->and($response->errorCodes())->toBe([RecaptchaResponse::E_SCORE_THRESHOLD_NOT_MET]);
});

it('applies per-action score thresholds', function () {
    config(['recaptcha.score.actions' => ['login' => 0.7]]);

    fakeGoogleSequence(
        v3Success(score: 0.6, action: 'login'),
        v3Success(score: 0.6, action: 'contact'),
    );

    expect(Recaptcha::verify('token', 'login')->isFailure())->toBeTrue()
        ->and(Recaptcha::verify('token', 'contact')->isSuccess())->toBeTrue();
});

it('reads per-action thresholds for action names containing dots', function () {
    config(['recaptcha.score.actions' => ['account.delete' => 0.9]]);

    expect(Recaptcha::thresholdFor('account.delete'))->toBe(0.9)
        ->and(Recaptcha::thresholdFor('other'))->toBe(0.5)
        ->and(Recaptcha::thresholdFor())->toBe(0.5);
});

it('rejects a token issued for a different action', function () {
    fakeGoogle(v3Success(action: 'register'));

    $response = Recaptcha::verify('token', 'login');

    expect($response->hasErrorCode(RecaptchaResponse::E_ACTION_MISMATCH))->toBeTrue();
});

it('can skip the action check', function () {
    config(['recaptcha.verify.action' => false]);
    fakeGoogle(v3Success(action: 'register'));

    expect(Recaptcha::verify('token', 'login')->isSuccess())->toBeTrue();
});

it('does not check the action when none is expected', function () {
    fakeGoogle(v3Success(action: 'anything'));

    expect(Recaptcha::verify('token')->isSuccess())->toBeTrue();
});

it('returns the error codes Google reports', function () {
    fakeGoogle(['success' => false, 'error-codes' => ['timeout-or-duplicate']]);

    $response = Recaptcha::verify('token', 'login');

    expect($response->isFailure())->toBeTrue()
        ->and($response->errorCodes())->toBe(['timeout-or-duplicate']);
});

it('fails without calling Google when the token is missing', function (?string $token) {
    Http::fake();

    $response = Recaptcha::verify($token, 'login');

    expect($response->errorCodes())->toBe([RecaptchaResponse::E_MISSING_TOKEN]);
    Http::assertNothingSent();
})->with([null, '', '   ']);

it('fails gracefully when Google is unreachable', function () {
    Http::fake(fn () => throw new ConnectionException('Connection timed out'));

    $response = Recaptcha::verify('token', 'login');

    expect($response->errorCodes())->toBe([RecaptchaResponse::E_CONNECTION_FAILED]);
});

it('fails gracefully on an HTTP error status', function () {
    fakeGoogle([], 500);

    expect(Recaptcha::verify('token', 'login')->errorCodes())->toBe([RecaptchaResponse::E_CONNECTION_FAILED]);
});

it('fails gracefully on a body that is not JSON', function () {
    Http::fake([TestCase::VERIFY_URL => Http::response('<html>oops</html>', 200)]);

    expect(Recaptcha::verify('token', 'login')->errorCodes())->toBe([RecaptchaResponse::E_INVALID_JSON]);
});

it('skips verification when disabled', function () {
    config(['recaptcha.enabled' => false]);
    Http::fake();

    expect(Recaptcha::verify(null, 'login')->isSuccess())->toBeTrue();
    Http::assertNothingSent();
});

it('skips verification in configured environments', function () {
    config(['recaptcha.skip_environments' => ['testing']]);
    Http::fake();

    expect(Recaptcha::verify(null)->isSuccess())->toBeTrue();
    Http::assertNothingSent();
});

it('skips verification for configured IP addresses', function () {
    config(['recaptcha.skip_ips' => ['10.0.0.5']]);
    fakeGoogle(['success' => false]);

    expect(Recaptcha::verify(null, null, '10.0.0.5')->isSuccess())->toBeTrue()
        ->and(Recaptcha::verify('token', null, '10.0.0.6')->isFailure())->toBeTrue();
});

it('checks the hostname when enabled', function () {
    config(['recaptcha.verify.hostname' => true]);

    fakeGoogleSequence(
        v3Success(overrides: ['hostname' => 'evil.test']),
        v3Success(overrides: ['hostname' => 'EXAMPLE.com']),
    );

    expect(Recaptcha::verify('token', 'login')->hasErrorCode(RecaptchaResponse::E_HOSTNAME_MISMATCH))->toBeTrue()
        ->and(Recaptcha::verify('token', 'login')->isSuccess())->toBeTrue();
});

it('ignores the hostname by default', function () {
    fakeGoogle(v3Success(overrides: ['hostname' => 'elsewhere.test']));

    expect(Recaptcha::verify('token', 'login')->isSuccess())->toBeTrue();
});

it('rejects challenges older than the configured timeout', function () {
    config(['recaptcha.verify.challenge_timeout' => 60]);
    fakeGoogle(v3Success(overrides: ['challenge_ts' => gmdate('Y-m-d\TH:i:s\Z', time() - 300)]));

    expect(Recaptcha::verify('token', 'login')->hasErrorCode(RecaptchaResponse::E_CHALLENGE_TIMEOUT))->toBeTrue();
});

it('uses the configured verify URL', function () {
    $mirror = 'https://www.recaptcha.net/recaptcha/api/siteverify';
    config(['recaptcha.verify_url' => $mirror]);
    Http::fake([$mirror => Http::response(v3Success())]);

    expect(Recaptcha::verify('token', 'login')->isSuccess())->toBeTrue();
    Http::assertSent(fn (Request $request) => $request->url() === $mirror);
});

it('throws a clear error when the secret key is missing', function () {
    config(['recaptcha.secret_key' => null]);

    Recaptcha::verify('token');
})->throws(InvalidConfigurationException::class, 'RECAPTCHA_SECRET_KEY');

it('throws from verifyOrFail when verification fails', function () {
    fakeGoogle(['success' => false, 'error-codes' => ['invalid-input-response']]);

    Recaptcha::verifyOrFail('token');
})->throws(RecaptchaException::class, 'invalid-input-response');

it('returns the response from verifyOrFail when verification passes', function () {
    fakeGoogle(v3Success());

    expect(Recaptcha::verifyOrFail('token', 'login')->score())->toBe(0.9);
});

it('builds the v3 script URL with the site key', function () {
    expect(Recaptcha::scriptUrl())
        ->toBe('https://www.google.com/recaptcha/api.js?render=test-site-key&onload=__labidiRecaptchaOnload');
});

it('builds the v2 script URL with explicit rendering and a language', function () {
    config(['recaptcha.version' => 'v2-checkbox', 'recaptcha.attributes.language' => 'fr']);

    expect(Recaptcha::scriptUrl())
        ->toBe('https://www.google.com/recaptcha/api.js?render=explicit&onload=__labidiRecaptchaOnload&hl=fr');
});

it('always uses the widget field name for v2', function () {
    config(['recaptcha.field_name' => 'captcha_token']);
    expect(Recaptcha::fieldName())->toBe('captcha_token');

    config(['recaptcha.version' => 'v2-invisible']);
    expect(Recaptcha::fieldName())->toBe('g-recaptcha-response');
});

it('rejects an unsupported version', function () {
    config(['recaptcha.version' => 'v4']);

    Recaptcha::version();
})->throws(InvalidConfigurationException::class, 'v4');

it('shares public settings without the secret key', function () {
    $settings = Recaptcha::toArray();

    expect($settings)->toMatchArray([
        'enabled' => true,
        'version' => 'v3',
        'site_key' => TestCase::SITE_KEY,
        'field_name' => 'g-recaptcha-response',
    ])
        ->and(json_encode($settings))->not->toContain(TestCase::SECRET_KEY);
});

it('reports the invisible size to the front end', function () {
    config(['recaptcha.version' => 'v2-invisible']);

    expect(Recaptcha::toArray()['size'])->toBe('invisible');
});

it('reports itself disabled to the front end when the site key is missing', function () {
    config(['recaptcha.site_key' => null]);

    expect(Recaptcha::toArray()['enabled'])->toBeFalse();
});

it('reads configuration changes made at runtime', function () {
    expect(Recaptcha::siteKey())->toBe(TestCase::SITE_KEY);

    config(['recaptcha.site_key' => 'rotated-key']);

    expect(Recaptcha::siteKey())->toBe('rotated-key');
});

it('appends its parameters to a script URL that already has a query string', function () {
    config(['recaptcha.script_url' => 'https://proxy.example.com/api.js?tenant=acme']);

    expect(Recaptcha::scriptUrl())
        ->toBe('https://proxy.example.com/api.js?tenant=acme&render=test-site-key&onload=__labidiRecaptchaOnload');
});

it('retries once after a connection failure by default', function () {
    config(['recaptcha.http.retry_delay' => 0]);
    Http::fake([TestCase::VERIFY_URL => Http::sequence()->pushFailedConnection()->push(v3Success())]);

    expect(Recaptcha::verify('token', 'login')->isSuccess())->toBeTrue();
    Http::assertSentCount(2);
});

it('can disable retries', function () {
    config(['recaptcha.http.retries' => 0]);
    Http::fake([TestCase::VERIFY_URL => Http::sequence()->pushFailedConnection()->push(v3Success())]);

    expect(Recaptcha::verify('token', 'login')->errorCodes())->toBe([RecaptchaResponse::E_CONNECTION_FAILED]);
});

it('never resends a token Google may already have consumed', function () {
    config(['recaptcha.http.retries' => 3, 'recaptcha.http.retry_delay' => 0]);
    Http::fake([TestCase::VERIFY_URL => Http::sequence()->push([], 500)->push(v3Success())]);

    expect(Recaptcha::verify('token', 'login')->errorCodes())->toBe([RecaptchaResponse::E_CONNECTION_FAILED]);
    Http::assertSentCount(1);
});

it('skips verification for IP ranges', function () {
    config(['recaptcha.skip_ips' => ['10.0.0.0/8', '2001:db8::/32']]);
    fakeGoogle(['success' => false]);

    expect(Recaptcha::verify(null, null, '10.20.30.40')->isSuccess())->toBeTrue()
        ->and(Recaptcha::verify(null, null, '2001:db8::1')->isSuccess())->toBeTrue()
        ->and(Recaptcha::verify('token', null, '192.168.1.1')->isFailure())->toBeTrue();
});
