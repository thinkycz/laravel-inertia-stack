import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { SharedProps } from '@/types';

export function useSharedProps() {
    const page = usePage<SharedProps>();

    return {
        features: computed(
            () => page.props.features ?? { ai: false, ai_ready: false },
        ),
        app: computed(() => page.props.app),
        auth: computed(() => page.props.auth),
        user: computed(() => page.props.auth?.user ?? null),
        conversations: computed(() => page.props.conversations ?? []),
        flash: computed(() => page.props.flash),
        flashSuccess: computed(() => page.props.flash?.success ?? null),
        flashError: computed(() => page.props.flash?.error ?? null),
        errors: computed(() => page.props.errors),
        activeUrl: computed(() => page.url),
    };
}
