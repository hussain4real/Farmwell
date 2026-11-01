<script setup lang="ts">
defineProps<{ html: string }>();
</script>

<template>
    <div
        class="space-y-2 break-words [&_a]:underline [&_h1]:font-semibold [&_h2]:font-semibold [&_h3]:font-semibold [&_li]:ml-5 [&_ol]:list-decimal [&_ul]:list-disc"
        v-html="html"
    ></div>
</template>
