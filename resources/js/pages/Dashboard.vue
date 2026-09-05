<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import AgentChatPanel from '@/components/agent/AgentChatPanel.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ChatConversation } from '@/types';
defineProps<{ conversation?: ChatConversation }>();
const { t } = useI18n();
</script>

<template>
    <AppLayout :title="conversation?.title ?? t('dashboard.title')">
        <AgentChatPanel
            :key="conversation?.id ?? 'new-chat'"
            :conversation="conversation"
        />
    </AppLayout>
</template>
