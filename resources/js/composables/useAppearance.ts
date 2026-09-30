import { ref } from 'vue';
export type Appearance = 'system' | 'light' | 'dark';
const appearance = ref<Appearance>('system');
let initialized = false;
function apply(): void {
    document.documentElement.classList.toggle(
        'dark',
        appearance.value === 'dark' ||
            (appearance.value === 'system' &&
                matchMedia('(prefers-color-scheme: dark)').matches),
    );
}
export function useAppearance() {
    if (!initialized && typeof window !== 'undefined') {
        try {
            const saved = localStorage.getItem('appearance');
            if (saved === 'dark' || saved === 'light') appearance.value = saved;
        } catch {
            /* Storage can be unavailable in private contexts. */
        }
        matchMedia('(prefers-color-scheme: dark)').addEventListener(
            'change',
            apply,
        );
        initialized = true;
        apply();
    }
    function setAppearance(value: Appearance): void {
        appearance.value = value;
        try {
            localStorage.setItem('appearance', value);
        } catch {
            /* Keep the in-memory preference. */
        }
        apply();
    }
    return { appearance, setAppearance };
}
