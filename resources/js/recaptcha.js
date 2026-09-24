/**
 * labidi/recaptcha - framework-agnostic browser helpers.
 *
 * Every function takes the config object the package shares with Inertia
 * (the "recaptcha" prop by default) or embeds via Recaptcha::toArray():
 *
 *   { enabled, version, site_key, script_url, field_name, theme, size, badge, language }
 *
 * The module shares its readiness queue with the package's Blade output, so a
 * page that already loads api.js through @recaptchaScript is reused rather
 * than loaded twice.
 */

const ONLOAD_CALLBACK = '__labidiRecaptchaOnload';
const WIDGET_KEY = '__labidiRecaptchaWidget';

let loading = null;

function registry() {
    window.__labidiRecaptcha = window.__labidiRecaptcha || {
        loaded: false,
        queue: [],
        ready(callback) {
            this.loaded ? callback() : this.queue.push(callback);
        },
    };

    window[ONLOAD_CALLBACK] = window[ONLOAD_CALLBACK] || function () {
        const r = window.__labidiRecaptcha;
        r.loaded = true;
        r.queue.splice(0).forEach((callback) => callback());
    };

    return window.__labidiRecaptcha;
}

/**
 * Whether reCAPTCHA should run with this config, in this environment.
 */
export function isRecaptchaEnabled(config) {
    return typeof window !== 'undefined' && Boolean(config && config.enabled && config.site_key);
}

/**
 * Load Google's api.js once and resolve with window.grecaptcha.
 *
 * Resolves with null when reCAPTCHA is disabled or during SSR, so callers can
 * treat "disabled" as "nothing to do".
 */
export function loadRecaptcha(config) {
    if (!isRecaptchaEnabled(config)) {
        return Promise.resolve(null);
    }

    if (loading) {
        return loading;
    }

    const r = registry();

    loading = new Promise((resolve, reject) => {
        r.ready(() => resolve(window.grecaptcha));

        if (r.loaded || document.querySelector('script[src*="/recaptcha/api.js"]')) {
            return;
        }

        const script = document.createElement('script');
        script.src = config.script_url;
        script.async = true;
        script.defer = true;
        script.onerror = () => {
            loading = null;
            script.remove();
            reject(new Error('Unable to load reCAPTCHA from ' + config.script_url));
        };

        document.head.appendChild(script);
    });

    return loading;
}

/**
 * Get a v3 token for the given action.
 */
export async function executeRecaptcha(config, action = 'submit') {
    if (config && config.version !== 'v3') {
        throw new Error('executeRecaptcha() is for reCAPTCHA v3. Use renderRecaptcha() for v2 widgets.');
    }

    const grecaptcha = await loadRecaptcha(config);

    return grecaptcha ? grecaptcha.execute(config.site_key, { action }) : null;
}

/**
 * Render a v2 widget (checkbox or invisible) into an element.
 *
 * Resolves with a handle, or null when reCAPTCHA is disabled:
 *
 *   {
 *     id,            // Google's widget id
 *     execute(),     // Promise<token>: runs the invisible challenge, or
 *                    // returns the checkbox response
 *     getResponse(), // the current token, or ''
 *     reset(),       // clear the widget, e.g. after a failed submit
 *   }
 *
 * Options override the shared config: theme, size, badge, tabindex, plus
 * callback(token), expiredCallback() and errorCallback().
 */
export async function renderRecaptcha(config, element, options = {}) {
    const grecaptcha = await loadRecaptcha(config);

    if (!grecaptcha || !element) {
        return null;
    }

    // Rendering twice into one element throws inside Google's code; this
    // happens with React StrictMode and hot module reloading.
    if (element[WIDGET_KEY]) {
        return element[WIDGET_KEY];
    }

    const invisible = config.version === 'v2-invisible';
    let pending = null;

    const settle = (method, value) => {
        if (pending) {
            pending[method](value);
            pending = null;
        }
    };

    const params = {
        sitekey: config.site_key,
        theme: options.theme ?? config.theme,
        size: invisible ? 'invisible' : (options.size ?? config.size),
        badge: invisible ? (options.badge ?? config.badge) : undefined,
        tabindex: options.tabindex,
        callback: (token) => {
            options.callback?.(token);
            settle('resolve', token);
        },
        'expired-callback': () => options.expiredCallback?.(),
        'error-callback': () => {
            options.errorCallback?.();
            settle('reject', new Error('reCAPTCHA failed to verify.'));
        },
    };

    Object.keys(params).forEach((key) => {
        if (params[key] === undefined || params[key] === null) {
            delete params[key];
        }
    });

    const id = grecaptcha.render(element, params);

    const handle = {
        id,
        getResponse: () => grecaptcha.getResponse(id),
        reset: () => grecaptcha.reset(id),
        execute: () => {
            if (!invisible) {
                return Promise.resolve(grecaptcha.getResponse(id) || null);
            }

            return new Promise((resolve, reject) => {
                pending = { resolve, reject };
                grecaptcha.execute(id);
            });
        },
    };

    element[WIDGET_KEY] = handle;

    return handle;
}

/**
 * Reset a v2 widget by id, or the first widget when no id is given.
 */
export function resetRecaptcha(widgetId) {
    if (typeof window !== 'undefined' && window.grecaptcha) {
        window.grecaptcha.reset(widgetId);
    }
}

/**
 * Get a token whichever version is configured: a fresh v3 token for the
 * action, or the response of the given v2 widget handle.
 */
export async function getRecaptchaToken(config, { action = 'submit', widget = null } = {}) {
    if (!isRecaptchaEnabled(config)) {
        return null;
    }

    if (config.version === 'v3') {
        return executeRecaptcha(config, action);
    }

    return widget ? widget.execute() : null;
}
