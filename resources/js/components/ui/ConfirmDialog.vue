<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import ModalOverlay from '@/components/ui/ModalOverlay.vue';
import { useConfirmDialog } from '@/composables/useConfirmDialog';

const { t } = useI18n();
const { state, accept, dismiss } = useConfirmDialog();
</script>

<template>
    <ModalOverlay
        :open="state.open"
        panel-class="max-w-sm space-y-3"
        @close="dismiss"
    >
        <h3 class="font-heading text-sm font-bold text-on-surface">
            {{ state.title }}
        </h3>
        <p class="text-xs text-on-surface-variant">
            {{ state.message }}
        </p>
        <div class="flex justify-end gap-2 pt-2">
            <button
                type="button"
                @click="dismiss"
                class="inline-flex h-8 items-center rounded-lg border border-outline-glass bg-white px-3 text-xs font-semibold text-on-surface hover:bg-surface-container-low"
            >
                {{ state.cancelLabel || t('common.cancel') }}
            </button>
            <button
                type="button"
                @click="accept"
                :class="[
                    'inline-flex h-8 items-center rounded-lg px-3 text-xs font-semibold text-white',
                    state.variant === 'danger'
                        ? 'border border-rose-200 bg-rose-600 hover:bg-rose-700'
                        : 'border border-primary/20 bg-gradient-to-b from-primary-container to-primary hover:brightness-105',
                ]"
            >
                {{ state.confirmLabel || t('common.confirm') }}
            </button>
        </div>
    </ModalOverlay>
</template>
