import { ref } from 'vue';

/**
 * Module-level singleton ref that holds the ID of a conversation that was
 * just created and is not yet reflected in the Inertia page URL.
 * Dashboard.vue sets this when `bridge.start()` returns a new conversation_id
 * and optimistically inserts the conversation into the sidebar.
 * AppLayout.vue reads it to highlight the correct sidebar item immediately,
 * without waiting for a full Inertia navigation to `/conversations/:id`.
 */
const pendingConversationId = ref<string | null>(null);

export function useActiveConversation() {
    return { pendingConversationId };
}
