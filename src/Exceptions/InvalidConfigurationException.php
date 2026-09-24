<?php

declare(strict_types=1);

namespace Labidi\Recaptcha\Exceptions;

use InvalidArgumentException;

class InvalidConfigurationException extends InvalidArgumentException
{
    public static function missingSecretKey(): self
    {
        return new self(
            'The reCAPTCHA secret key is not set. Add RECAPTCHA_SECRET_KEY to your .env file, '
            .'or set RECAPTCHA_ENABLED=false to disable verification.'
        );
    }

    public static function missingSiteKey(): self
    {
        return new self(
            'The reCAPTCHA site key is not set. Add RECAPTCHA_SITE_KEY to your .env file, '
            .'or set RECAPTCHA_ENABLED=false to disable reCAPTCHA.'
        );
    }

    public static function unsupportedVersion(string $version): self
    {
        return new self(sprintf(
            'Unsupported reCAPTCHA version [%s]. Supported versions are: v2-checkbox, v2-invisible, v3.',
            $version,
        ));
    }
}
