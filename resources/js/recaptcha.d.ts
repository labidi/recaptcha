export type RecaptchaVersion = 'v2-checkbox' | 'v2-invisible' | 'v3';

/** The public settings shared by labidi/recaptcha (never the secret key). */
export interface RecaptchaConfig {
    enabled: boolean;
    version: RecaptchaVersion;
    site_key: string | null;
    script_url: string;
    field_name: string;
    theme: string | null;
    size: string | null;
    badge: string | null;
    language: string | null;
}

export interface RecaptchaWidgetOptions {
    theme?: 'light' | 'dark';
    size?: 'normal' | 'compact';
    badge?: 'bottomright' | 'bottomleft' | 'inline';
    tabindex?: number;
    callback?: (token: string) => void;
    expiredCallback?: () => void;
    errorCallback?: () => void;
}

export interface RecaptchaWidget {
    id: number;
    execute(): Promise<string | null>;
    getResponse(): string;
    reset(): void;
}

export function isRecaptchaEnabled(config: RecaptchaConfig | null | undefined): boolean;

export function loadRecaptcha(config: RecaptchaConfig | null | undefined): Promise<unknown | null>;

export function executeRecaptcha(config: RecaptchaConfig, action?: string): Promise<string | null>;

export function renderRecaptcha(
    config: RecaptchaConfig,
    element: HTMLElement | null,
    options?: RecaptchaWidgetOptions,
): Promise<RecaptchaWidget | null>;

export function resetRecaptcha(widgetId?: number): void;

export function getRecaptchaToken(
    config: RecaptchaConfig,
    options?: { action?: string; widget?: RecaptchaWidget | null },
): Promise<string | null>;
