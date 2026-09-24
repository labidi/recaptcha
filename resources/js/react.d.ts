import type { RefObject } from 'react';
import type { RecaptchaConfig, RecaptchaWidgetOptions } from './recaptcha';

export interface UseRecaptchaOptions extends RecaptchaWidgetOptions {
    action?: string;
    prop?: string;
    config?: RecaptchaConfig;
}

export function useRecaptcha(options?: UseRecaptchaOptions): {
    config: RecaptchaConfig;
    enabled: boolean;
    fieldName: string;
    containerRef: RefObject<HTMLDivElement | null>;
    ready: boolean;
    error: unknown;
    execute(action?: string): Promise<string | null>;
    reset(): void;
};
