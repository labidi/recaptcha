<?php

declare(strict_types=1);

namespace Labidi\Recaptcha\View\Components;

use Labidi\Recaptcha\Exceptions\InvalidConfigurationException;

/**
 * Renders a v2 widget: the checkbox, or the invisible widget.
 *
 *     <x-recaptcha::widget theme="dark" />   or   @recaptchaWidget
 *
 * Prefer <x-recaptcha::field>, which follows the configured version. This
 * component exists for pages that want v2-specific options and refuses to
 * render against a v3 key, which Google would reject.
 */
class Widget extends Field
{
    public function shouldRender(): bool
    {
        if (! parent::shouldRender()) {
            return false;
        }

        if ($this->recaptcha()->isV3()) {
            throw new InvalidConfigurationException(
                'The reCAPTCHA widget renders v2 challenges, but RECAPTCHA_VERSION is "v3". '
                .'Use <x-recaptcha::field> or @recaptchaField, which follow the configured version.'
            );
        }

        return true;
    }
}
