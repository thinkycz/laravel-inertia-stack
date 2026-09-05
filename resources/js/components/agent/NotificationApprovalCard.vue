<script setup lang="ts">
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';
import type { NotificationApprovalArguments } from '@/types';

const props = defineProps<{
    argumentsValue: NotificationApprovalArguments;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    approve: [argumentsValue: NotificationApprovalArguments];
    reject: [result: string];
}>();

const { t } = useI18n();
const subject = ref(props.argumentsValue.subject);
const message = ref(props.argumentsValue.message);
const rejectionReason = ref('');

function approve(): void {
    if (!subject.value.trim() || !message.value.trim()) {
        return;
    }

    emit('approve', {
        subject: subject.value,
        message: message.value,
    });
}
</script>

<template>
    <section
        class="mt-3 space-y-3 rounded-xl border border-primary/20 bg-primary/5 p-4"
        :aria-label="t('agent.approval.title')"
    >
        <div>
            <p class="text-xs font-bold text-on-surface">
                {{ t('agent.approval.title') }}
            </p>
            <p class="mt-1 text-[11px] text-on-surface-variant">
                {{ t('agent.approval.description') }}
            </p>
        </div>

        <label class="block space-y-1 text-[11px] font-semibold">
            <span>{{ t('agent.approval.subject') }}</span>
            <input
                v-model="subject"
                :disabled="disabled"
                maxlength="255"
                class="w-full rounded-lg border border-outline-glass bg-surface-container-lowest px-3 py-2 text-xs outline-none focus:border-primary"
            />
        </label>

        <label class="block space-y-1 text-[11px] font-semibold">
            <span>{{ t('agent.approval.message') }}</span>
            <textarea
                v-model="message"
                :disabled="disabled"
                maxlength="5000"
                rows="4"
                class="w-full resize-y rounded-lg border border-outline-glass bg-surface-container-lowest px-3 py-2 text-xs outline-none focus:border-primary"
            ></textarea>
        </label>

        <label class="block space-y-1 text-[11px] font-semibold">
            <span>{{ t('agent.approval.rejection_reason') }}</span>
            <input
                v-model="rejectionReason"
                :disabled="disabled"
                maxlength="5000"
                :placeholder="t('agent.approval.rejection_placeholder')"
                class="w-full rounded-lg border border-outline-glass bg-surface-container-lowest px-3 py-2 text-xs outline-none focus:border-primary"
            />
        </label>

        <div class="flex justify-end gap-2">
            <button
                type="button"
                :disabled="disabled"
                class="rounded-lg border border-outline-glass px-3 py-2 text-[11px] font-bold text-on-surface-variant disabled:opacity-50"
                @click="emit('reject', rejectionReason)"
            >
                {{ t('agent.approval.reject') }}
            </button>
            <button
                type="button"
                :disabled="disabled || !subject.trim() || !message.trim()"
                class="rounded-lg bg-primary px-3 py-2 text-[11px] font-bold text-white disabled:opacity-50"
                @click="approve"
            >
                {{ t('agent.approval.approve') }}
            </button>
        </div>
    </section>
</template>
