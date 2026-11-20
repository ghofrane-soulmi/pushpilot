<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { edit, show } from '@/routes/projects';
import { edit as editEnvironment } from '@/routes/projects/environment';
import { cn } from '@/lib/utils';

const props = defineProps<{
    projectId: number;
    current: 'overview' | 'settings' | 'environment';
}>();

const tabs = [
    { key: 'overview', label: 'Overview', href: show(props.projectId) },
    { key: 'settings', label: 'Settings', href: edit(props.projectId) },
    {
        key: 'environment',
        label: 'Environment',
        href: editEnvironment(props.projectId),
    },
] as const;
</script>

<template>
    <nav aria-label="Project sections" class="flex gap-1 border-b">
        <Link
            v-for="tab in tabs"
            :key="tab.key"
            :href="tab.href"
            :aria-current="tab.key === current ? 'page' : undefined"
            :class="
                cn(
                    '-mb-px border-b-2 px-3 py-2 text-sm font-medium transition-colors focus-visible:outline-2',
                    tab.key === current
                        ? 'border-primary text-foreground'
                        : 'border-transparent text-muted-foreground hover:text-foreground',
                )
            "
        >
            {{ tab.label }}
        </Link>
    </nav>
</template>
