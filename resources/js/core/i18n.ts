type I18nMap = Record<string, string>;

const i18nMap: I18nMap = (window as unknown as { APP_I18N?: I18nMap }).APP_I18N ?? {};

export const t = (key: string, fallback: string) => i18nMap[key] ?? fallback;

export const tFormat = (key: string, fallback: string, vars: Record<string, string | number>) => {
    const template = t(key, fallback);
    return template.replace(/:([a-zA-Z_]+)/g, (_, token) => {
        const value = vars[token];
        return value === undefined ? `:${token}` : String(value);
    });
};
