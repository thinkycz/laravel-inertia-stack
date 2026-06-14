import { reactive, readonly } from 'vue';
import { useI18n } from 'vue-i18n';

interface ConfirmDialogState {
    open: boolean;
    title: string;
    message: string;
    confirmLabel: string;
    cancelLabel: string;
    variant: 'default' | 'danger';
    resolver: ((value: boolean) => void) | null;
}

const state = reactive<ConfirmDialogState>({
    open: false,
    title: '',
    message: '',
    confirmLabel: '',
    cancelLabel: '',
    variant: 'default',
    resolver: null,
});

interface ConfirmOptions {
    title?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: 'default' | 'danger';
}

/**
 * Promise-based confirmation dialog. Renders a modal that can be
 * mounted once at the layout level and resolves to `true` on
 * confirm, `false` on cancel.
 */
export function useConfirmDialog() {
    const { t } = useI18n();

    function confirm(
        message: string,
        options: ConfirmOptions = {},
    ): Promise<boolean> {
        state.title = options.title ?? t('common.confirm_title');
        state.message = message;
        state.confirmLabel = options.confirmLabel ?? t('common.confirm');
        state.cancelLabel = options.cancelLabel ?? t('common.cancel');
        state.variant = options.variant ?? 'default';
        state.open = true;

        return new Promise<boolean>((resolve) => {
            state.resolver = resolve;
        });
    }

    function accept(): void {
        const resolver = state.resolver;
        state.open = false;
        state.resolver = null;
        resolver?.(true);
    }

    function dismiss(): void {
        const resolver = state.resolver;
        state.open = false;
        state.resolver = null;
        resolver?.(false);
    }

    return {
        confirm,
        accept,
        dismiss,
        state: readonly(state),
    };
}
