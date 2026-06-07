import { usePage } from '@inertiajs/vue3';
import { computed, watchEffect } from 'vue';
import { useI18n } from 'vue-i18n';
import { isSupportedLocale } from '@/i18n';
import type { SharedProps } from '@/types';

/**
 * Bind the active vue-i18n locale to the locale shared by the
 * server (`app.locale` from `SetPreferredLanguageMiddleware`, or
 * the logged-in user's preferred locale). When the user changes
 * their locale via `<LocaleSwitcher>` and Inertia re-renders,
 * the watcher picks up the new value and updates the i18n
 * instance so the UI strings flip without a hard reload.
 */
export function useBoundLocale(): void {
    const i18n = useI18n();
    const page = usePage<SharedProps>();

    const requestedLocale = computed<string>(() => {
        const user = page.props.auth?.user?.locale;
        const app = page.props.app?.locale;
        const raw = user ?? app ?? 'en';
        return isSupportedLocale(raw) ? raw : 'en';
    });

    watchEffect(() => {
        const next = requestedLocale.value;
        if (i18n.locale.value !== next) {
            i18n.locale.value = next;
        }
    });
}
