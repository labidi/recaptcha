import type { ComputedRef, Ref, ShallowRef } from 'vue';
import type { RecaptchaConfig, RecaptchaWidget, RecaptchaWidgetOptions } from './recaptcha';

export interface UseRecaptchaOptions extends RecaptchaWidgetOptions {
    action?: string;
    prop?: string;
    config?: RecaptchaConfig;
}

export function useRecaptcha(options?: UseRecaptchaOptions): {
    config: ComputedRef<RecaptchaConfig>;
    enabled: ComputedRef<boolean>;
    fieldName: ComputedRef<string>;
    container: Ref<HTMLElement | null>;
    widget: ShallowRef<RecaptchaWidget | null>;
    ready: Ref<boolean>;
    error: Ref<unknown>;
    execute(action?: string): Promise<string | null>;
    reset(): void;
};
