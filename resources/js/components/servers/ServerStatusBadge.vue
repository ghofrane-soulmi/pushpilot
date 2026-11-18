<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { ServerStatus } from '@/types';

const props = defineProps<{
    status: ServerStatus;
    lastCheckedAt?: string | null;
}>();

const label = computed(
    () =>
        ({
            reachable: 'Reachable',
            unreachable: 'Unreachable',
            unknown: 'Not checked',
        })[props.status],
);

const dotClass = computed(
    () =>
        ({
            reachable: 'bg-emerald-500',
            unreachable: 'bg-red-500',
            unknown: 'bg-muted-foreground/50',
        })[props.status],
);

const title = computed(() =>
    props.lastCheckedAt
        ? `Last checked ${new Date(props.lastCheckedAt).toLocaleString()}`
        : 'Never checked',
);
</script>

<template>
    <Badge variant="outline" :title="title" data-test="server-status">
        <span :class="['size-2 rounded-full', dotClass]" aria-hidden="true" />
        {{ label }}
    </Badge>
</template>
