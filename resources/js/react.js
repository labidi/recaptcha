/**
 * labidi/recaptcha - React hook for Inertia.js.
 *
 *   const { containerRef, execute, reset, fieldName } = useRecaptcha({ action: 'login' });
 *
 *   <div ref={containerRef} />   // only needed for v2 widgets
 */

import { useCallback, useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { getRecaptchaToken, isRecaptchaEnabled, loadRecaptcha, renderRecaptcha } from './recaptcha.js';

/**
 * @param {object} [options]
 * @param {string} [options.action]  v3 action, default "submit"
 * @param {string} [options.prop]    shared prop name, default "recaptcha"
 * @param {object} [options.config]  explicit config instead of the shared prop
 * @param {string} [options.theme]   v2 widget overrides: theme, size, badge, tabindex
 */
export function useRecaptcha(options = {}) {
    const { props } = usePage();

    const config = options.config ?? props[options.prop ?? 'recaptcha'] ?? { enabled: false };
    const enabled = isRecaptchaEnabled(config);
    const fieldName = config.field_name ?? 'g-recaptcha-response';

    const containerRef = useRef(null);
    const widgetRef = useRef(null);
    const configRef = useRef(config);
    const optionsRef = useRef(options);
    const [ready, setReady] = useState(false);
    const [error, setError] = useState(null);

    configRef.current = config;
    optionsRef.current = options;

    useEffect(() => {
        let cancelled = false;

        (async () => {
            try {
                const grecaptcha = await loadRecaptcha(configRef.current);

                if (!grecaptcha || cancelled) {
                    return;
                }

                if (configRef.current.version !== 'v3' && containerRef.current) {
                    widgetRef.current = await renderRecaptcha(configRef.current, containerRef.current, optionsRef.current);
                }

                if (!cancelled) {
                    setReady(true);
                }
            } catch (e) {
                if (!cancelled) {
                    setError(e);
                }
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [config.enabled, config.site_key, config.version]);

    /**
     * Resolve a token to send with the form, or null when reCAPTCHA is
     * disabled.
     */
    const execute = useCallback(
        (action = optionsRef.current.action ?? 'submit') =>
            getRecaptchaToken(configRef.current, { action, widget: widgetRef.current }),
        [],
    );

    /**
     * Clear a v2 widget, e.g. in an Inertia onError callback. Tokens are
     * single use, so a rejected submit needs a new challenge.
     */
    const reset = useCallback(() => widgetRef.current?.reset(), []);

    return { config, enabled, fieldName, containerRef, ready, error, execute, reset };
}
