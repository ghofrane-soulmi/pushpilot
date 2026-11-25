<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { CircleCheck, CircleX, FolderCog, Rocket } from '@lucide/vue';
import { computed } from 'vue';
import DeploymentController from '@/actions/App/Http/Controllers/DeploymentController';
import ProjectWebhookSecretController from '@/actions/App/Http/Controllers/ProjectWebhookSecretController';
import ProjectInitializationController from '@/actions/App/Http/Controllers/ProjectInitializationController';
import CopyableCode from '@/components/CopyableCode.vue';
import DeploymentStatusBadge from '@/components/deployments/DeploymentStatusBadge.vue';
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
import { show as showDeployment } from '@/routes/deployments';
import { index, show } from '@/routes/projects';
import { show as showServer } from '@/routes/servers';
import { formatDuration } from '@/types';
import type { ActionResultFlash, Deployment, ProjectDetails } from '@/types';

const props = defineProps<{
    project: ProjectDetails;
    deployments: Deployment[];
    hasActiveDeployment: boolean;
    webhookUrl: string;
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

// Shown only on the page view right after it was generated (spec 9.1).
const webhookSecret = computed(
    () => (page.flash as { webhookSecret?: string }).webhookSecret,
);

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

        <!-- DEP-1: deploy -->
        <Card v-if="project.initialized_at">
            <CardHeader>
                <CardTitle>Deployments</CardTitle>
                <CardDescription>
                    Deploys the latest commit of
                    <code>{{ project.branch }}</code
                    >. The site keeps running on the current release until the
                    new one is fully built.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <Form
                    v-bind="DeploymentController.store.form(project.id)"
                    v-slot="{ processing }"
                >
                    <Button
                        type="submit"
                        :disabled="processing"
                        data-test="deploy-now"
                    >
                        <Spinner v-if="processing" />
                        <Rocket v-else class="size-4" />
                        Deploy now
                    </Button>
                    <span
                        v-if="hasActiveDeployment"
                        class="ml-3 text-sm text-muted-foreground"
                    >
                        A deployment is in progress; a new one will wait for it.
                    </span>
                </Form>

                <p
                    v-if="deployments.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No deployments yet.
                </p>
                <ul
                    v-else
                    class="divide-y rounded-md border"
                    data-test="deployments"
                >
                    <li v-for="deployment in deployments" :key="deployment.id">
                        <Link
                            :href="showDeployment(deployment.id)"
                            class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 text-sm hover:bg-muted/40 focus-visible:bg-muted/40"
                        >
                            <DeploymentStatusBadge
                                :status="deployment.status"
                            />
                            <span class="font-mono text-xs">{{
                                deployment.commit_hash?.slice(0, 7) ??
                                '#' + deployment.id
                            }}</span>
                            <span class="min-w-0 flex-1 truncate">{{
                                deployment.commit_message ??
                                'Waiting for commit details'
                            }}</span>
                            <span class="text-muted-foreground">{{
                                formatDuration(deployment.duration)
                            }}</span>
                            <span class="text-muted-foreground">
                                {{
                                    deployment.created_at
                                        ? new Date(
                                              deployment.created_at,
                                          ).toLocaleString()
                                        : ''
                                }}
                            </span>
                        </Link>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <!-- DEP-2: deploy on push -->
        <Card v-if="project.initialized_at">
            <CardHeader>
                <CardTitle>Deploy on push</CardTitle>
                <CardDescription>
                    Add a webhook in GitHub so every push to
                    <code>{{ project.branch }}</code> deploys automatically. In
                    the repository: Settings → Webhooks → Add webhook.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <ol class="list-decimal space-y-3 pl-5 text-sm">
                    <li class="space-y-1.5">
                        <p><strong>Payload URL</strong></p>
                        <CopyableCode :value="webhookUrl" label="payload URL" />
                    </li>
                    <li>
                        <strong>Content type:</strong>
                        <code>application/json</code>
                    </li>
                    <li class="space-y-2">
                        <p>
                            <strong>Secret:</strong> generate one here and paste
                            it in GitHub. It is shown only once; generating a
                            new one replaces it.
                        </p>
                        <div
                            v-if="webhookSecret"
                            class="space-y-1.5"
                            data-test="webhook-secret"
                        >
                            <CopyableCode
                                :value="webhookSecret"
                                label="webhook secret"
                            />
                            <p class="text-muted-foreground">
                                Copy it now: it will not be shown again.
                            </p>
                        </div>
                        <Form
                            v-else
                            v-bind="
                                ProjectWebhookSecretController.form(project.id)
                            "
                            v-slot="{ processing }"
                        >
                            <Button
                                type="submit"
                                variant="outline"
                                size="sm"
                                :disabled="processing"
                                data-test="generate-webhook-secret"
                            >
                                <Spinner v-if="processing" />
                                Generate webhook secret
                            </Button>
                        </Form>
                    </li>
                    <li>
                        <strong>Events:</strong> "Just the push event", then
                        <strong>Add webhook</strong>. GitHub sends a ping, which
                        should show a green check.
                    </li>
                </ol>
            </CardContent>
        </Card>

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
