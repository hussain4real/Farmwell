<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    Check,
    CheckCircle2,
    Copy,
    Loader2,
    MessageSquarePlus,
    Send,
    Sparkles,
    Trash2,
    XCircle,
} from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import AssistantApprovalCard from '@/components/assistant/AssistantApprovalCard.vue';
import type { PendingApprovalItem } from '@/components/assistant/AssistantApprovalCard.vue';
import AssistantMessageContent from '@/components/assistant/AssistantMessageContent.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { readXsrfToken } from '@/lib/offlineLogQueue';
import assistant from '@/routes/assistant';
import assistantApprovals from '@/routes/assistant/approvals';
import assistantConversations from '@/routes/assistant/conversations';
import assistantMessages from '@/routes/assistant/messages';

type ToolCallSummary = {
    id: string;
    name: string;
    successful: boolean;
    denied: boolean;
    answered: boolean;
};

type AssistantMessage = {
    id: string;
    role: string;
    content: string;
    tool_calls: ToolCallSummary[];
};

type AssistantConversation = {
    id: string;
    title: string | null;
    updated_at: string | null;
};

const WRITE_TOOLS = new Set([
    'RecordExpenseTool',
    'CreateFundingPhaseTool',
    'RecordFundingReleaseTool',
    'UpdateAgreementFundingTool',
    'RecordDailyLogTool',
    'RecordFarmActivityTool',
    'DecideApprovalRequestTool',
]);

const page = usePage();
const currentTeam = computed(() => page.props.currentTeam);
const canUseAssistant = computed(
    () =>
        Boolean(currentTeam.value) &&
        !page.props.platformAccess &&
        page.props.currentTeamFeatures?.plan?.allowsAssistant === true,
);

const open = ref(false);
const loadingHistory = ref(false);
const streaming = ref(false);
const deleting = ref(false);
const promptInput = ref('');
const errorMessage = ref<string | null>(null);
const conversations = ref<AssistantConversation[]>([]);
const activeConversationId = ref<string | null>(null);
const messages = ref<AssistantMessage[]>([]);
const pendingApprovals = ref<PendingApprovalItem[]>([]);
const starterPrompts = ref<string[]>([]);
const messagesViewport = ref<HTMLElement | null>(null);
const copiedMessageId = ref<string | null>(null);

let copyResetTimer: ReturnType<typeof setTimeout> | null = null;

async function copyMessageContent(id: string, text: string): Promise<void> {
    if (!text || typeof navigator === 'undefined' || !navigator.clipboard) {
        return;
    }

    try {
        await navigator.clipboard.writeText(text);
        copiedMessageId.value = id;

        if (copyResetTimer) {
            clearTimeout(copyResetTimer);
        }

        copyResetTimer = setTimeout(() => {
            if (copiedMessageId.value === id) {
                copiedMessageId.value = null;
            }
        }, 1800);
    } catch {
        // Clipboard permission denied
    }
}

const canSend = computed(
    () =>
        !streaming.value &&
        !loadingHistory.value &&
        pendingApprovals.value.length === 0 &&
        promptInput.value.trim().length > 0,
);

function formatToolName(name: string): string {
    return name
        .replace(/Tool$/, '')
        .replace(/([a-z])([A-Z])/g, '$1 $2')
        .trim();
}

async function scrollToBottom(): Promise<void> {
    await nextTick();

    if (messagesViewport.value) {
        messagesViewport.value.scrollTop = messagesViewport.value.scrollHeight;
    }
}

watch(
    () => currentTeam.value?.slug,
    () => {
        conversations.value = [];
        activeConversationId.value = null;
        messages.value = [];
        pendingApprovals.value = [];
        starterPrompts.value = [];
        errorMessage.value = null;
    },
);

watch(open, (isOpen) => {
    if (isOpen && canUseAssistant.value) {
        void loadHistory(activeConversationId.value);
    }
});

async function loadHistory(
    conversationId: string | null = null,
): Promise<void> {
    const slug = currentTeam.value?.slug;

    if (!slug || !canUseAssistant.value) {
        return;
    }

    loadingHistory.value = true;
    errorMessage.value = null;

    try {
        const url = assistant.index(slug, {
            query: conversationId ? { conversation_id: conversationId } : {},
        }).url;

        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            throw new Error('Failed to load assistant history.');
        }

        const payload = (await response.json()) as {
            conversations: AssistantConversation[];
            activeConversationId: string | null;
            messages: AssistantMessage[];
            pendingApprovals: PendingApprovalItem[];
            starterPrompts: string[];
        };

        conversations.value = payload.conversations ?? [];
        activeConversationId.value = payload.activeConversationId ?? null;
        messages.value = payload.messages ?? [];
        pendingApprovals.value = payload.pendingApprovals ?? [];
        starterPrompts.value = payload.starterPrompts ?? [];
        await scrollToBottom();
    } catch {
        errorMessage.value =
            'Unable to load assistant conversations right now.';
    } finally {
        loadingHistory.value = false;
    }
}

function startNewConversation(): void {
    activeConversationId.value = null;
    messages.value = [];
    pendingApprovals.value = [];
    errorMessage.value = null;
}

async function selectConversation(id: string): Promise<void> {
    if (!id || id === activeConversationId.value) {
        return;
    }

    await loadHistory(id);
}

async function deleteActiveConversation(): Promise<void> {
    const slug = currentTeam.value?.slug;
    const conversationId = activeConversationId.value;

    if (!slug || !conversationId || deleting.value) {
        return;
    }

    deleting.value = true;
    errorMessage.value = null;

    try {
        const response = await fetch(
            assistantConversations.destroy({
                current_team: slug,
                conversation: conversationId,
            }).url,
            {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': readXsrfToken(),
                },
            },
        );

        if (!response.ok) {
            throw new Error('Failed to delete conversation.');
        }

        activeConversationId.value = null;
        messages.value = [];
        pendingApprovals.value = [];
        await loadHistory(null);
    } catch {
        errorMessage.value = 'Unable to delete this conversation.';
    } finally {
        deleting.value = false;
    }
}

async function sendPrompt(overridePrompt?: string): Promise<void> {
    const slug = currentTeam.value?.slug;
    const text = (overridePrompt ?? promptInput.value).trim();

    if (
        !slug ||
        !text ||
        streaming.value ||
        pendingApprovals.value.length > 0
    ) {
        return;
    }

    if (!overridePrompt) {
        promptInput.value = '';
    }

    errorMessage.value = null;
    streaming.value = true;

    messages.value.push({
        id: `user-${Date.now()}`,
        role: 'user',
        content: text,
        tool_calls: [],
    });

    const assistantDraft: AssistantMessage = {
        id: `assistant-${Date.now()}`,
        role: 'assistant',
        content: '',
        tool_calls: [],
    };
    messages.value.push(assistantDraft);
    await scrollToBottom();

    try {
        const response = await fetch(assistantMessages.store(slug).url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'text/event-stream, application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': readXsrfToken(),
            },
            body: JSON.stringify({
                message: text,
                conversation_id: activeConversationId.value,
            }),
        });

        await handleAssistantResponse(response, assistantDraft, true);
    } catch {
        errorMessage.value =
            'Connection lost while talking to Farmwell Assistant.';
    } finally {
        streaming.value = false;
    }
}

async function resolveApproval(payload: {
    id: string;
    action: 'approve' | 'edit' | 'reject';
    arguments?: Record<string, unknown>;
    reason?: string;
}): Promise<void> {
    const slug = currentTeam.value?.slug;
    const conversationId = activeConversationId.value;

    if (!slug || !conversationId || streaming.value) {
        return;
    }

    errorMessage.value = null;
    streaming.value = true;

    const assistantDraft: AssistantMessage = {
        id: `assistant-resume-${Date.now()}`,
        role: 'assistant',
        content: '',
        tool_calls: [],
    };
    messages.value.push(assistantDraft);
    await scrollToBottom();

    try {
        const response = await fetch(
            assistantApprovals.store({
                current_team: slug,
                conversation: conversationId,
            }).url,
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'text/event-stream, application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': readXsrfToken(),
                },
                body: JSON.stringify({
                    decisions: {
                        [payload.id]: {
                            action: payload.action,
                            ...(payload.arguments
                                ? { arguments: payload.arguments }
                                : {}),
                            ...(payload.reason
                                ? { reason: payload.reason }
                                : {}),
                        },
                    },
                }),
            },
        );

        await handleAssistantResponse(response, assistantDraft, false);
    } catch {
        errorMessage.value = 'Unable to submit your confirmation decision.';
    } finally {
        streaming.value = false;
    }
}

async function handleAssistantResponse(
    response: Response,
    assistantDraft: AssistantMessage,
    removeDraftOnError: boolean,
): Promise<void> {
    if (response.status === 409) {
        const payload = (await response.json()) as {
            message?: string;
            pendingApprovals?: PendingApprovalItem[];
        };
        pendingApprovals.value = payload.pendingApprovals ?? [];
        errorMessage.value =
            payload.message ??
            'Please confirm or cancel the pending action first.';

        if (removeDraftOnError) {
            messages.value = messages.value.filter(
                (msg) => msg.id !== assistantDraft.id,
            );
        }

        return;
    }

    if (!response.ok || !response.body) {
        let message = 'Unable to complete that assistant request.';

        try {
            const payload = (await response.json()) as { message?: string };

            if (payload.message) {
                message = payload.message;
            }
        } catch {
            // Non-JSON error body
        }

        errorMessage.value = message;

        if (removeDraftOnError && !assistantDraft.content) {
            messages.value = messages.value.filter(
                (msg) => msg.id !== assistantDraft.id,
            );
        }

        return;
    }

    let mutatingToolSucceeded = false;
    let receivedApprovalRequest = false;
    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    while (true) {
        const { done, value } = await reader.read();

        if (done) {
            break;
        }

        buffer += decoder
            .decode(value, { stream: true })
            .replace(/\r\n/g, '\n');
        const frames = buffer.split('\n\n');
        buffer = frames.pop() ?? '';

        for (const frame of frames) {
            const dataLines = frame
                .split('\n')
                .filter((line) => line.startsWith('data:'))
                .map((line) => line.slice(5).trimStart());

            if (dataLines.length === 0) {
                continue;
            }

            const raw = dataLines.join('\n').trim();

            if (!raw || raw === '[DONE]') {
                continue;
            }

            const event = JSON.parse(raw) as Record<string, unknown>;
            const type = event.type;

            if (
                type === 'conversation' &&
                typeof event.conversation_id === 'string'
            ) {
                activeConversationId.value = event.conversation_id;
            } else if (
                type === 'text_delta' &&
                typeof event.delta === 'string'
            ) {
                assistantDraft.content += event.delta;
                await scrollToBottom();
            } else if (type === 'tool_call') {
                assistantDraft.tool_calls.push({
                    id: String(event.tool_id ?? ''),
                    name: String(event.tool_name ?? ''),
                    successful: true,
                    denied: false,
                    answered: false,
                });
            } else if (type === 'tool_result') {
                const toolId = String(event.tool_id ?? '');
                const toolName = String(event.tool_name ?? '');
                const successful = event.successful === true;
                const denied = event.denied === true;
                const existing = assistantDraft.tool_calls.find(
                    (call) => call.id === toolId,
                );

                if (existing) {
                    existing.successful = successful;
                    existing.denied = denied;
                    existing.answered = true;
                } else if (toolName) {
                    assistantDraft.tool_calls.push({
                        id: toolId,
                        name: toolName,
                        successful,
                        denied,
                        answered: true,
                    });
                }

                if (successful && WRITE_TOOLS.has(toolName)) {
                    mutatingToolSucceeded = true;
                }
            } else if (type === 'tool_approval_request') {
                receivedApprovalRequest = true;
                pendingApprovals.value =
                    (event.approvals as PendingApprovalItem[]) ?? [];
                await scrollToBottom();
            } else if (type === 'approval_mismatch') {
                receivedApprovalRequest = true;
                pendingApprovals.value =
                    (event.approvals as PendingApprovalItem[]) ?? [];
                errorMessage.value =
                    typeof event.message === 'string'
                        ? event.message
                        : 'Pending actions changed; please review again.';
            } else if (type === 'error') {
                errorMessage.value =
                    typeof event.message === 'string'
                        ? event.message
                        : 'The assistant could not complete this request.';
            }
        }
    }

    if (
        !receivedApprovalRequest &&
        pendingApprovals.value.length > 0 &&
        !errorMessage.value
    ) {
        pendingApprovals.value = [];
    }

    if (activeConversationId.value) {
        await loadHistory(activeConversationId.value);
    }

    if (mutatingToolSucceeded) {
        router.reload();
    }
}

function handleComposerKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        void sendPrompt();
    }
}
</script>

<template>
    <div v-if="canUseAssistant" data-slot="farmwell-assistant">
        <Button
            type="button"
            size="lg"
            class="fixed right-4 bottom-[calc(5.25rem+env(safe-area-inset-bottom))] z-40 gap-2 rounded-full shadow-lg md:right-6 md:bottom-6"
            data-testid="open-farmwell-assistant"
            @click="open = true"
        >
            <Sparkles class="size-4" />
            <span>Ask Farmwell</span>
            <Badge
                v-if="pendingApprovals.length > 0"
                variant="secondary"
                class="ml-0.5 size-5 justify-center rounded-full p-0 text-[11px]"
            >
                {{ pendingApprovals.length }}
            </Badge>
        </Button>

        <Sheet v-model:open="open">
            <SheetContent
                side="right"
                class="flex h-full w-full flex-col gap-0 p-0 sm:max-w-lg"
            >
                <SheetHeader
                    class="border-b border-border/80 bg-linear-to-b from-primary/[0.07] to-transparent px-4 py-3.5 pr-12"
                >
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex min-w-0 items-center gap-2.5">
                            <div
                                class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-primary/12 text-primary ring-1 ring-primary/20"
                            >
                                <Sparkles class="size-4" />
                            </div>
                            <div class="min-w-0">
                                <SheetTitle class="text-base leading-tight">
                                    Farmwell Assistant
                                </SheetTitle>
                                <SheetDescription class="truncate text-xs">
                                    {{ currentTeam?.name }} · Actions require
                                    your confirmation
                                </SheetDescription>
                            </div>
                        </div>
                        <div class="flex items-center gap-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                title="New conversation"
                                :disabled="streaming"
                                @click="startNewConversation"
                            >
                                <MessageSquarePlus class="size-4" />
                                <span class="sr-only">New conversation</span>
                            </Button>
                            <Button
                                v-if="activeConversationId"
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                title="Delete conversation"
                                class="text-muted-foreground hover:text-destructive"
                                :disabled="streaming || deleting"
                                @click="deleteActiveConversation"
                            >
                                <Trash2 class="size-4" />
                                <span class="sr-only">Delete conversation</span>
                            </Button>
                        </div>
                    </div>

                    <div v-if="conversations.length > 0" class="mt-2.5">
                        <select
                            :value="activeConversationId ?? ''"
                            aria-label="Conversation history"
                            class="h-8 w-full rounded-lg border border-input bg-background/80 px-2.5 text-xs font-medium text-foreground transition-colors outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/30"
                            :disabled="streaming || loadingHistory"
                            @change="
                                selectConversation(
                                    ($event.target as HTMLSelectElement).value,
                                )
                            "
                        >
                            <option value="" disabled>
                                Select a conversation…
                            </option>
                            <option
                                v-for="conversation in conversations"
                                :key="conversation.id"
                                :value="conversation.id"
                            >
                                {{
                                    conversation.title ||
                                    'Untitled conversation'
                                }}
                            </option>
                        </select>
                    </div>
                </SheetHeader>

                <div
                    ref="messagesViewport"
                    class="flex-1 space-y-5 overflow-y-auto px-4 py-4"
                >
                    <div
                        v-if="loadingHistory && messages.length === 0"
                        class="space-y-3"
                    >
                        <div
                            class="h-12 w-3/4 animate-pulse rounded-xl bg-muted"
                        />
                        <div
                            class="ml-auto h-10 w-2/3 animate-pulse rounded-xl bg-muted"
                        />
                    </div>

                    <div
                        v-else-if="messages.length === 0"
                        class="space-y-4 py-3"
                    >
                        <div
                            class="rounded-2xl border border-primary/15 bg-linear-to-br from-primary/[0.07] via-card to-card p-4 text-xs leading-relaxed text-muted-foreground shadow-xs"
                        >
                            <div
                                class="mb-1.5 flex items-center gap-1.5 font-medium text-foreground"
                            >
                                <Sparkles class="size-3.5 text-primary" />
                                <span>Workspace Copilot</span>
                            </div>
                            Ask about your farm operations, finance ledger,
                            funding phases, or investor portal. Whenever a
                            change is ready to record, you&apos;ll see a
                            confirmation card to approve, edit, or cancel first.
                        </div>

                        <div v-if="starterPrompts.length > 0" class="space-y-2">
                            <p
                                class="text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                            >
                                Try asking
                            </p>
                            <div class="flex flex-col gap-2">
                                <Button
                                    v-for="prompt in starterPrompts"
                                    :key="prompt"
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="h-auto justify-start rounded-xl border-border/80 bg-card/70 px-3.5 py-2.5 text-left text-xs font-medium whitespace-normal transition-colors hover:border-primary/40 hover:bg-primary/[0.06]"
                                    :disabled="streaming"
                                    @click="sendPrompt(prompt)"
                                >
                                    {{ prompt }}
                                </Button>
                            </div>
                        </div>
                    </div>

                    <div
                        v-for="message in messages"
                        :key="message.id"
                        class="flex flex-col gap-2"
                        :class="
                            message.role === 'user'
                                ? 'items-end'
                                : 'items-start'
                        "
                    >
                        <div
                            v-if="message.role === 'user'"
                            class="max-w-[85%] rounded-2xl rounded-br-sm bg-primary px-3.5 py-2.5 text-sm leading-relaxed font-medium whitespace-pre-wrap text-primary-foreground shadow-xs"
                        >
                            {{ message.content }}
                        </div>

                        <template v-else>
                            <div
                                class="flex w-full max-w-[96%] items-center justify-between gap-2 px-0.5"
                            >
                                <div
                                    class="flex flex-wrap items-center gap-1.5"
                                >
                                    <span
                                        class="inline-flex items-center gap-1 text-[11px] font-medium text-muted-foreground"
                                    >
                                        <Sparkles class="size-3 text-primary" />
                                        <span>Farmwell AI</span>
                                    </span>

                                    <Badge
                                        v-for="call in message.tool_calls"
                                        :key="call.id || call.name"
                                        variant="outline"
                                        class="gap-1 rounded-full border-border/70 bg-muted/40 px-2 py-0 text-[11px] font-normal"
                                        :class="
                                            call.answered &&
                                            !call.denied &&
                                            call.successful
                                                ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                                : call.denied ||
                                                    !call.successful
                                                  ? 'border-destructive/30 bg-destructive/10 text-destructive'
                                                  : ''
                                        "
                                    >
                                        <Loader2
                                            v-if="streaming && !call.answered"
                                            class="size-2.5 animate-spin"
                                        />
                                        <XCircle
                                            v-else-if="
                                                call.denied || !call.successful
                                            "
                                            class="size-2.5"
                                        />
                                        <CheckCircle2 v-else class="size-2.5" />
                                        <span>{{
                                            formatToolName(call.name)
                                        }}</span>
                                    </Badge>
                                </div>

                                <Button
                                    v-if="message.content && !streaming"
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    class="size-6 rounded-md text-muted-foreground hover:text-foreground"
                                    :title="
                                        copiedMessageId === message.id
                                            ? 'Copied'
                                            : 'Copy response'
                                    "
                                    @click="
                                        copyMessageContent(
                                            message.id,
                                            message.content,
                                        )
                                    "
                                >
                                    <Check
                                        v-if="copiedMessageId === message.id"
                                        class="size-3 text-emerald-600 dark:text-emerald-400"
                                    />
                                    <Copy v-else class="size-3" />
                                    <span class="sr-only">Copy response</span>
                                </Button>
                            </div>

                            <div
                                v-if="message.content"
                                class="w-full max-w-[96%] rounded-2xl rounded-tl-sm border border-border/75 bg-card/95 px-4 py-3.5 shadow-xs dark:bg-card/85"
                            >
                                <AssistantMessageContent
                                    :content="message.content"
                                />
                            </div>

                            <div
                                v-else-if="streaming"
                                class="flex items-center gap-2 rounded-2xl rounded-tl-sm border border-border/70 bg-card/70 px-3.5 py-2.5 text-xs text-muted-foreground"
                            >
                                <Loader2
                                    class="size-3.5 animate-spin text-primary"
                                />
                                <span>Thinking…</span>
                            </div>
                        </template>
                    </div>

                    <div v-if="pendingApprovals.length > 0" class="space-y-3">
                        <AssistantApprovalCard
                            v-for="approval in pendingApprovals"
                            :key="approval.id"
                            :approval="approval"
                            :disabled="streaming"
                            @resolve="resolveApproval"
                        />
                    </div>

                    <Alert v-if="errorMessage" variant="destructive">
                        <AlertCircle class="size-4" />
                        <AlertDescription class="text-xs">
                            {{ errorMessage }}
                        </AlertDescription>
                    </Alert>
                </div>

                <form
                    class="border-t border-border/80 bg-background/95 p-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] backdrop-blur-xs"
                    @submit.prevent="sendPrompt()"
                >
                    <p
                        v-if="pendingApprovals.length > 0"
                        class="mb-2 text-xs text-amber-700 dark:text-amber-300"
                    >
                        Confirm or cancel the pending action above before
                        sending another message.
                    </p>
                    <div
                        class="flex items-end gap-2 rounded-xl border border-input bg-card/80 p-1.5 transition-colors focus-within:border-primary/50 focus-within:ring-2 focus-within:ring-primary/20"
                    >
                        <textarea
                            v-model="promptInput"
                            rows="2"
                            placeholder="Ask Farmwell or request a change…"
                            aria-label="Message Farmwell Assistant"
                            class="min-h-10 flex-1 resize-none bg-transparent px-2 py-1.5 text-sm outline-none placeholder:text-muted-foreground disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="streaming || pendingApprovals.length > 0"
                            @keydown="handleComposerKeydown"
                        />
                        <Button
                            type="submit"
                            size="icon"
                            class="shrink-0 rounded-lg"
                            :disabled="!canSend"
                        >
                            <Loader2
                                v-if="streaming"
                                class="size-4 animate-spin"
                            />
                            <Send v-else class="size-4" />
                            <span class="sr-only">Send message</span>
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    </div>
</template>
