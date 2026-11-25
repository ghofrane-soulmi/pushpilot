<script setup lang="ts">
import { Form, Head, Link, usePoll } from '@inertiajs/vue3';
import { CircleCheck, CircleDashed, CircleX, LoaderCircle, TriangleAlert } from '@lucide/vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import DeploymentController from '@/actions/App/Http/Controllers/DeploymentController';
import DeploymentStatusBadge from '@/components/deployments/DeploymentStatusBadge.vue';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { show as showDeployment } from '@/routes/deployments';
import { index, show } from '@/routes/projects';
import { formatDuration, isActive } from '@/types';
import type { DeploymentDetails } from '@/types';

const props = defineProps<{
    deployment: DeploymentDetails;
}>();

defineOptions({
    layout: (props: { deployment: DeploymentDetails }) => ({
        breadcrumbs: [
            { title: 'Projects', href: index() },
            { title: props.deployment.project.name, href: show(props.deployment.project.id) },
            { title: `Deployment #${props.deployment.id}`, href: showDeployment(props.deployment.id) },
        ],
    }),
});

const active = computed(() => isActive(props.deployment.status));

// Refresh every 2s while the deployment runs. Milestone 5 replaces this with live WebSocket lines.
const { start, stop } = usePoll(2000, { only: ['deployment'] }, { autoStart: false });

watch(
    active,
    (running) => (running ? start() : stop()),
    { immediate: true },
);

const terminal = ref<HTMLElement | null>(null);
const followLog = ref(true);

function scrollToEnd(): void {
    if (followLog.value && terminal.value) {
        terminal.value.scrollTop = terminal.value.scrollHeight;
    }
}

function onScroll(): void {
    const el = terminal.value;

    if (el) {
        followLog.value = el.scrollHeight - el.scrollTop - el.clientHeight < 40;
    }
}

onMounted(scrollToEnd);
watch(
    () => props.deployment.log.length,
    () => nextTick(scrollToEnd),
);

function stepState(step: { name: string; ran: boolean }): 'done' | 'current' | 'failed' | 'skipped' | 'pending' {
    if (props.deployment.current_step === step.name) {
        return 'current';
    }

    if (!step.ran) {
        return active.value ? 'pending' : 'skipped';
    }

    const failed = props.deployment.status === 'failed' || props.deployment.status === 'cancelled';
    const lastRan = [...props.deployment.steps].reverse().find((s) => s.ran)?.name;

    return failed && lastRan === step.name ? 'failed' : 'done';
}

const startedAt = computed(() =>
    props.deployment.started_at ? new Date(props.deployment.started_at).toLocaleString() : null,
);
</script>

<template>
    <Head :title="`Deployment #${deployment.id}`" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <Heading
                    :title="`Deployment #${deployment.id}`"
                    :description="
                        deployment.commit_message
                            ? `${deployment.commit_hash?.slice(0, 7)} · ${deployment.commit_message}`
                            : `${deployment.project.name} @ ${deployment.project.branch}`
                    "
                />
                <div class="-mt-6 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
                    <DeploymentStatusBadge :status="deployment.status" />
                    <span v-if="deployment.commit_author">by {{ deployment.commit_author }}</span>
                    <span>{{ deployment.trigger === 'webhook' ? 'via git push' : `started by ${deployment.user ?? 'someone'}` }}</span>
                    <span v-if="startedAt">{{ startedAt }}</span>
                    <span v-if="deployment.duration !== null">{{ formatDuration(deployment.duration) }}</span>
                    <span v-if="deployment.release_name" class="font-mono text-xs">release {{ deployment.release_name }}</span>
                </div>
            </div>

            <div class="flex gap-2">
                <Button variant="outline" as-child>
                    <Link :href="show(deployment.project.id)">Back to project</Link>
                </Button>
                <Form
                    v-if="active && !deployment.cancel_requested"
                    v-bind="DeploymentController.cancel.form(deployment.id)"
                    v-slot="{ processing }"
                >
                    <Button type="submit" variant="destructive" :disabled="processing" data-test="cancel-deployment">
                        <Spinner v-if="processing" />
                        Cancel
                    </Button>
                </Form>
            </div>
        </div>

        <Alert v-if="deployment.error_message" variant="destructive" role="status">
            <CircleX class="size-4" />
            <AlertTitle>{{ deployment.status === 'cancelled' ? 'Cancelled' : 'Deployment failed' }}</AlertTitle>
            <AlertDescription>{{ deployment.error_message }}</AlertDescription>
        </Alert>

        <Alert v-for="warning in deployment.warnings" :key="warning" role="status">
            <TriangleAlert class="size-4" />
            <AlertTitle>Warning</AlertTitle>
            <AlertDescription>{{ warning }}</AlertDescription>
        </Alert>

        <p v-if="deployment.cancel_requested && active" class="text-sm text-muted-foreground" role="status">
            Cancelling: the deployment stops before its next step.
        </p>

        <div class="grid gap-6 lg:grid-cols-[16rem_minmax(0,1fr)]">
            <ol class="flex flex-col gap-1 text-sm" aria-label="Pipeline steps">
                <li
                    v-for="step in deployment.steps"
                    :key="step.name"
                    class="flex items-center gap-2 rounded-md px-2 py-1"
                    :class="{ 'bg-muted font-medium': stepState(step) === 'current', 'text-muted-foreground': ['skipped', 'pending'].includes(stepState(step)) }"
                >
                    <LoaderCircle v-if="stepState(step) === 'current'" class="size-4 animate-spin text-sky-500 motion-reduce:animate-none" aria-label="Running" />
                    <CircleCheck v-else-if="stepState(step) === 'done'" class="size-4 text-emerald-600" aria-label="Done" />
                    <CircleX v-else-if="stepState(step) === 'failed'" class="size-4 text-red-600" aria-label="Failed" />
                    <CircleDashed v-else class="size-4" :aria-label="stepState(step) === 'pending' ? 'Pending' : 'Skipped'" />
                    {{ step.label }}
                </li>
            </ol>

            <div class="min-w-0">
                <div
                    ref="terminal"
                    class="h-[60vh] min-h-80 overflow-auto rounded-lg border bg-neutral-950 p-4 font-mono text-xs leading-relaxed text-neutral-100"
                    role="log"
                    aria-live="polite"
                    aria-label="Deployment log"
                    tabindex="0"
                    data-test="deployment-log"
                    @scroll="onScroll"
                >
                    <p v-if="deployment.log.length === 0" class="text-neutral-400">
                        {{ deployment.status === 'queued' ? 'Waiting for a worker…' : 'No output.' }}
                    </p>
                    <div
                        v-for="line in deployment.log"
                        :key="line.id"
                        class="break-all whitespace-pre-wrap"
                        :class="{
                            'text-red-300': line.stream === 'stderr',
                            'mt-2 font-semibold text-sky-300': line.line.startsWith('▶'),
                        }"
                    >{{ line.line || ' ' }}</div>
                </div>
            </div>
        </div>
    </div>
</template>
