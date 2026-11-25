<script setup lang="ts">
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import type { DeploymentStatus } from '@/types';

const props = defineProps<{
    status: DeploymentStatus;
}>();

const appearance = computed(
    () =>
        ({
            queued: { label: 'Queued', dot: 'bg-muted-foreground/60' },
            running: {
                label: 'Running',
                dot: 'bg-sky-500 animate-pulse motion-reduce:animate-none',
            },
            succeeded: { label: 'Succeeded', dot: 'bg-emerald-500' },
            failed: { label: 'Failed', dot: 'bg-red-500' },
            cancelled: { label: 'Cancelled', dot: 'bg-amber-500' },
            rolled_back: { label: 'Rolled back', dot: 'bg-violet-500' },
        })[props.status],
);
</script>

<template>
    <Badge variant="outline" data-test="deployment-status">
        <span
            :class="['size-2 rounded-full', appearance.dot]"
            aria-hidden="true"
        />
        {{ appearance.label }}
    </Badge>
</template>
