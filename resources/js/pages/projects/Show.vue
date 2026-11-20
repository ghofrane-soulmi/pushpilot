<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { CircleCheck, CircleX, FolderCog } from '@lucide/vue';
import { computed } from 'vue';
import ProjectInitializationController from '@/actions/App/Http/Controllers/ProjectInitializationController';
import CopyableCode from '@/components/CopyableCode.vue';
import Heading from '@/components/Heading.vue';
import ProjectNav from '@/components/projects/ProjectNav.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import { index, show } from '@/routes/projects';
import { show as showServer } from '@/routes/servers';
import type { ActionResultFlash, ProjectDetails } from '@/types';

const props = defineProps<{
    project: ProjectDetails;
}>();

defineOptions({
    layout: (props: { project: ProjectDetails }) => ({
        breadcrumbs: [
            { title: 'Projects', href: index() },
            { title: props.project.name, href: show(props.project.id) },
        ],
    }),
});

const page = usePage();

const initialization = computed(
    () => (page.flash as { initialization?: ActionResultFlash }).initialization,
);

const githubDeployKeysUrl = computed(() => {
    const match = props.project.repository_url.match(
        /github\.com[:/]([^/]+\/[^/]+?)(\.git)?$/,
    );

    return match ? `https://github.com/${match[1]}/settings/keys/new` : null;
});
</script>

<template>
    <Head :title="project.name" />

    <div class="flex max-w-4xl flex-col gap-6 p-4">
        <div class="space-y-2">
            <Heading
                :title="project.name"
                :description="`${project.repository_url} @ ${project.branch}`"
            />
            <div
                class="-mt-6 flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
            >
                <Badge
                    :variant="project.initialized_at ? 'secondary' : 'outline'"
                >
                    {{
                        project.initialized_at
                            ? 'Initialized'
                            : 'Not initialized'
                    }}
                </Badge>
                <span>
                    on
                    <Link
                        :href="showServer(project.server.id)"
                        class="underline underline-offset-4"
                    >
                        {{ project.server.name }}
                    </Link>
                    in <code class="font-mono">{{ project.deploy_path }}</code>
                </span>
            </div>
        </div>

        <ProjectNav :project-id="project.id" current="overview" />

        <!-- PRJ-2: deploy key -->
        <Card>
            <CardHeader>
                <CardTitle>1. Add the deploy key to the repository</CardTitle>
                <CardDescription>
                    This key gives the server read-only access to this
                    repository only. In GitHub: Settings → Deploy keys → Add
                    deploy key, paste the key and leave "Allow write access"
                    unchecked.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-3">
                <CopyableCode
                    :value="project.deploy_public_key"
                    label="deploy key"
                />
                <a
                    v-if="githubDeployKeysUrl"
                    :href="githubDeployKeysUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-block text-sm font-medium underline underline-offset-4"
                >
                    Open the repository's deploy keys page on GitHub
                </a>
            </CardContent>
        </Card>

        <!-- PRJ-10: initialize -->
        <Card>
            <CardHeader>
                <CardTitle>2. Initialize the server</CardTitle>
                <CardDescription>
                    Creates <code>releases/</code> and <code>shared/</code> in
                    <code>{{ project.deploy_path }}</code
                    >, installs the deploy key and checks that the repository is
                    readable. Safe to run again.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <Form
                    v-bind="ProjectInitializationController.form(project.id)"
                    v-slot="{ processing }"
                    :options="{ preserveScroll: true }"
                >
                    <Button
                        type="submit"
                        :disabled="processing"
                        data-test="initialize-project"
                    >
                        <Spinner v-if="processing" />
                        <FolderCog v-else class="size-4" />
                        {{
                            project.initialized_at
                                ? 'Initialize again'
                                : 'Initialize'
                        }}
                    </Button>
                </Form>

                <Alert
                    v-if="initialization"
                    :variant="
                        initialization.successful ? 'default' : 'destructive'
                    "
                    role="status"
                    data-test="initialization-result"
                >
                    <CircleCheck
                        v-if="initialization.successful"
                        class="size-4"
                    />
                    <CircleX v-else class="size-4" />
                    <AlertTitle>{{ initialization.message }}</AlertTitle>
                    <AlertDescription v-if="initialization.steps.length">
                        <ul class="mt-2 space-y-1">
                            <li
                                v-for="step in initialization.steps"
                                :key="step.label"
                                class="flex gap-2"
                            >
                                <span aria-hidden="true">{{
                                    step.ok ? '✓' : '✗'
                                }}</span>
                                <span>
                                    <span class="sr-only">{{
                                        step.ok ? 'Done:' : 'Failed:'
                                    }}</span>
                                    {{ step.label }}
                                    <span
                                        v-if="step.message"
                                        class="block text-muted-foreground"
                                        >{{ step.message }}</span
                                    >
                                </span>
                            </li>
                        </ul>
                    </AlertDescription>
                </Alert>
            </CardContent>
        </Card>
    </div>
</template>
