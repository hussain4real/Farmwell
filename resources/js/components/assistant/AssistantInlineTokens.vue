<script setup lang="ts">
import { parseInlineMarkdown } from '@/lib/assistantMarkdown';

type Props = {
    text: string;
    allowLinks?: boolean;
    codeClass?: string;
    strongClass?: string;
};

withDefaults(defineProps<Props>(), {
    allowLinks: true,
    codeClass: 'rounded bg-muted px-1 py-0.5 font-mono text-[12px]',
    strongClass: 'font-semibold',
});
</script>

<template>
    <template
        v-for="(token, tokenIndex) in parseInlineMarkdown(text)"
        :key="tokenIndex"
    >
        <strong v-if="token.type === 'strong'" :class="strongClass">{{
            token.content
        }}</strong>
        <em v-else-if="token.type === 'em'" class="italic">{{
            token.content
        }}</em>
        <code v-else-if="token.type === 'code'" :class="codeClass">{{
            token.content
        }}</code>
        <a
            v-else-if="token.type === 'link' && allowLinks"
            :href="token.href"
            target="_blank"
            rel="noopener noreferrer"
            class="text-primary underline underline-offset-2 hover:text-primary/80"
            >{{ token.label }}</a
        >
        <template v-else-if="token.type === 'link'">{{ token.label }}</template>
        <template v-else>{{ token.content }}</template>
    </template>
</template>
