<script setup lang="ts">
import { Check, Copy } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    value: string;
    label: string;
}>();

const copied = ref(false);

async function copy(): Promise<void> {
    try {
        await navigator.clipboard.writeText(props.value);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        copied.value = false;
    }
}
</script>

<template>
    <div class="relative">
        <pre
            class="overflow-x-auto rounded-md border bg-muted/50 p-3 pr-12 font-mono text-xs break-all whitespace-pre-wrap"
            :aria-label="label"
            >{{ value }}</pre>
        <Button
            type="button"
            variant="ghost"
            size="icon"
            class="absolute top-1.5 right-1.5 size-8"
            :aria-label="copied ? `${label} copied` : `Copy ${label}`"
            @click="copy"
        >
            <Check v-if="copied" class="size-4 text-emerald-600" />
            <Copy v-else class="size-4" />
        </Button>
    </div>
</template>
