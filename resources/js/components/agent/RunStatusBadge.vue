<script setup lang="ts">
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { LoaderCircle, X, Ban } from '@lucide/vue';
import { useConfirmDialog } from '@/composables/useConfirmDialog';
import { cn } from '@/lib/utils';
import type { AgentRunSnapshot } from '@/types';

const props = defineProps<{
    run: AgentRunSnapshot | null;
    currentTool: string | null;
    cancelling?: boolean;
}>();

const emit = defineEmits<{
    cancel: [];
}>();

const { t } = useI18n();
const confirmDialog = useConfirmDialog();

const visible = computed<boolean>(() => {
    if (props.run === null) {
        return false;
    }

    return (
        props.run.status === 'queued' ||
        props.run.status === 'running' ||
        props.run.status === 'failed' ||
        props.run.status === 'cancelled'
    );
});

const statusLabel = computed<string>(() => {
    if (props.run === null) {
        return '';
    }

    return t(`agent.${props.run.status}`);
});

const isActive = computed<boolean>(
    () =>
        props.run !== null &&
        (props.run.status === 'queued' || props.run.status === 'running'),
);

async function onCancel(): Promise<void> {
    const accepted = await confirmDialog.confirm(
        t('agent.cancel_run_confirm'),
        { variant: 'danger', confirmLabel: t('agent.cancel_run') },
    );

    if (!accepted) {
        return;
    }

    emit('cancel');
}
</script>

<template>
    <div v-if="visible" class="max-w-3xl w-full mx-auto mb-2">
        <!-- Active run: status + tool indicator + cancel button -->
        <div
            v-if="isActive"
            :class="
                cn(
                    'flex items-center gap-3 rounded-2xl border border-outline-glass bg-surface-container-lowest px-4 py-2.5 shadow-sm',
                )
            "
        >
            <LoaderCircle
                :size="16"
                class="shrink-0 animate-spin text-primary"
            />
            <div class="flex-1 min-w-0">
                <p class="text-xs font-semibold text-on-surface truncate">
                    {{ statusLabel }}
                </p>
                <p
                    v-if="currentTool !== null"
                    class="text-[11px] text-on-surface-variant truncate"
                >
                    {{
                        t('agent.tool_running', {
                            tool: t('agent.clarification_tool'),
                        })
                    }}
                </p>
            </div>
            <button
                type="button"
                :aria-label="t('agent.cancel_run')"
                @click="onCancel"
                :disabled="cancelling"
                class="inline-flex h-7 items-center gap-1.5 rounded-lg border border-outline-glass bg-surface-container px-2.5 text-[11px] font-semibold text-on-surface-variant transition hover:border-error-red/40 hover:text-error-red cursor-pointer"
            >
                <X :size="12" />
                <span>{{
                    t(cancelling ? 'agent.cancelling' : 'agent.cancel_run')
                }}</span>
            </button>
        </div>

        <!-- Failed run: red error message -->
        <div
            v-else-if="props.run?.status === 'failed'"
            class="flex items-start gap-2 rounded-xl border border-error-red/30 bg-error-red/10 px-4 py-2.5 text-xs text-error-red"
            role="alert"
        >
            <Ban :size="14" class="mt-0.5 shrink-0" />
            <div class="flex-1 min-w-0">
                <p class="font-semibold">{{ statusLabel }}</p>
                <p
                    v-if="props.run.error"
                    class="text-[11px] mt-0.5 opacity-90 break-words"
                >
                    {{ t(props.run.error) }}
                </p>
            </div>
        </div>

        <!-- Cancelled run: muted pill -->
        <div
            v-else-if="props.run?.status === 'cancelled'"
            class="flex items-center gap-2 rounded-xl border border-outline-glass bg-surface-container-low px-4 py-2 text-[11px] font-medium text-on-surface-variant"
        >
            <Ban :size="12" />
            <span>{{ statusLabel }}</span>
        </div>
    </div>
</template>
