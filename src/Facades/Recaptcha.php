<?php

declare(strict_types=1);

namespace Labidi\Recaptcha\Facades;

use Illuminate\Support\Facades\Facade;
use Labidi\Recaptcha\Recaptcha as RecaptchaService;
use Labidi\Recaptcha\RecaptchaResponse;
use Labidi\Recaptcha\Rules\RecaptchaRule;
use Labidi\Recaptcha\Testing\RecaptchaFake;

/**
 * @method static RecaptchaResponse verify(?string $token, ?string $action = null, ?string $ip = null)
 * @method static RecaptchaResponse verifyOrFail(?string $token, ?string $action = null, ?string $ip = null)
 * @method static RecaptchaRule rule(?string $action = null)
 * @method static bool shouldSkip(?string $ip = null)
 * @method static bool shouldRender()
 * @method static bool inSkippedEnvironment()
 * @method static float thresholdFor(?string $action = null)
 * @method static bool isEnabled()
 * @method static string version()
 * @method static bool isV2()
 * @method static bool isV3()
 * @method static bool isInvisible()
 * @method static string|null siteKey()
 * @method static string fieldName()
 * @method static string verifyUrl()
 * @method static string scriptUrl()
 * @method static array<string, mixed> toArray()
 * @method static mixed config(?string $key = null, mixed $default = null)
 * @method static void assertVerified(?string $action = null, ?\Closure $callback = null)
 * @method static void assertVerifiedTimes(int $times)
 * @method static void assertNothingVerified()
 *
 * @see RecaptchaService
 */
class Recaptcha extends Facade
{
    /**
     * Replace the bound instance with a fake that never calls Google.
     */
    public static function fake(): RecaptchaFake
    {
        $fake = new RecaptchaFake;

        static::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return RecaptchaService::class;
    }
}
