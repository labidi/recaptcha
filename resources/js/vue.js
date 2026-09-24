/**
 * labidi/recaptcha - Vue 3 composable for Inertia.js.
 *
 *   const { container, execute, reset, fieldName } = useRecaptcha({ action: 'login' });
 *
 *   <div ref="container" />   <!-- only needed for v2 widgets -->
 */

import { computed, onMounted, ref, shallowRef } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { getRecaptchaToken, isRecaptchaEnabled, loadRecaptcha, renderRecaptcha } from './recaptcha.js';

/**
 * @param {object} [options]
 * @param {string} [options.action]  v3 action, default "submit"
 * @param {string} [options.prop]    shared prop name, default "recaptcha"
 * @param {object} [options.config]  explicit config instead of the shared prop
 * @param {string} [options.theme]   v2 widget overrides: theme, size, badge, tabindex
 */
export function useRecaptcha(options = {}) {
    const page = usePage();

    const config = computed(() => options.config ?? page.props[options.prop ?? 'recaptcha'] ?? { enabled: false });
    const enabled = computed(() => isRecaptchaEnabled(config.value));
    const fieldName = computed(() => config.value?.field_name ?? 'g-recaptcha-response');

    const container = ref(null);
    const widget = shallowRef(null);
    const ready = ref(false);
    const error = ref(null);

    onMounted(async () => {
        try {
            const grecaptcha = await loadRecaptcha(config.value);

            if (!grecaptcha) {
                return;
            }

            if (config.value.version !== 'v3' && container.value) {
                widget.value = await renderRecaptcha(config.value, container.value, options);
            }

            ready.value = true;
        } catch (e) {
            error.value = e;
        }
    });

    /**
     * Resolve a token to send with the form, or null when reCAPTCHA is
     * disabled.
     */
    function execute(action = options.action ?? 'submit') {
        return getRecaptchaToken(config.value, { action, widget: widget.value });
    }

    /**
     * Clear a v2 widget, e.g. in an Inertia onError callback. Tokens are
     * single use, so a rejected submit needs a new challenge.
     */
    function reset() {
        widget.value?.reset();
    }

    return { config, enabled, fieldName, container, widget, ready, error, execute, reset };
}
