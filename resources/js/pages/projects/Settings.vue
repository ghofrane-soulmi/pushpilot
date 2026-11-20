<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from '@lucide/vue';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ProjectNav from '@/components/projects/ProjectNav.vue';
import ToggleField from '@/components/ToggleField.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { edit, index, show } from '@/routes/projects';
import type { HookStageValue, ProjectDetails } from '@/types';

const props = defineProps<{
    project: ProjectDetails;
    hookStages: { value: HookStageValue; label: string }[];
}>();

defineOptions({
    layout: (props: { project: ProjectDetails }) => ({
        breadcrumbs: [
            { title: 'Projects', href: index() },
            { title: props.project.name, href: show(props.project.id) },
            { title: 'Settings', href: edit(props.project.id) },
        ],
    }),
});

const form = useForm({
    name: props.project.name,
    repository_url: props.project.repository_url,
    branch: props.project.branch,
    deploy_path: props.project.deploy_path,
    shared_paths: [...props.project.shared_paths],
    run_composer: props.project.run_composer,
    run_npm_build: props.project.run_npm_build,
    run_migrations: props.project.run_migrations,
    queue_restart: props.project.queue_restart,
    php_fpm_service: props.project.php_fpm_service ?? '',
    keep_releases: props.project.keep_releases,
    health_check_url: props.project.health_check_url ?? '',
    health_check_status: props.project.health_check_status,
    auto_rollback: props.project.auto_rollback,
    hooks: props.project.hooks.map((hook) => ({ ...hook })),
});

const deleteForm = useForm({});

// Kept out of the template: a literal "//" in a template attribute trips vue-tsc.
const healthCheckExample = 'https://my-app.example.com/up';

function submit(): void {
    form.submit(ProjectController.update(props.project.id), {
        preserveScroll: true,
    });
}

function addHook(): void {
    form.hooks.push({ stage: 'after_build', command: '' });
}

function errorAt(key: string): string | undefined {
    return (form.errors as Record<string, string | undefined>)[key];
}

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';
</script>

<template>
    <Head :title="`${project.name} settings`" />

    <div class="flex max-w-4xl flex-col gap-6 p-4">
        <Heading
            :title="project.name"
            :description="`${project.repository_url} @ ${project.branch}`"
        />

        <ProjectNav :project-id="project.id" current="settings" />

        <form class="flex flex-col gap-6" @submit.prevent="submit">
            <Card>
                <CardHeader>
                    <CardTitle>General</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4">
                    <div class="grid gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            required
                            maxlength="100"
                        />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="repository_url">Repository (SSH URL)</Label>
                        <Input
                            id="repository_url"
                            v-model="form.repository_url"
                            required
                            spellcheck="false"
                        />
                        <InputError :message="form.errors.repository_url" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="branch">Branch</Label>
                            <Input
                                id="branch"
                                v-model="form.branch"
                                required
                                spellcheck="false"
                            />
                            <InputError :message="form.errors.branch" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="deploy_path">Deploy path</Label>
                            <Input
                                id="deploy_path"
                                v-model="form.deploy_path"
                                required
                                spellcheck="false"
                            />
                            <InputError :message="form.errors.deploy_path" />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- PRJ-3 -->
            <Card>
                <CardHeader>
                    <CardTitle>Shared paths</CardTitle>
                    <CardDescription>
                        Kept across releases in <code>shared/</code> and linked
                        into each release. End a directory with <code>/</code>.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-2">
                    <div
                        v-for="(_, i) in form.shared_paths"
                        :key="i"
                        class="flex items-start gap-2"
                    >
                        <div class="grid flex-1 gap-1">
                            <Input
                                v-model="form.shared_paths[i]"
                                :aria-label="`Shared path ${i + 1}`"
                                spellcheck="false"
                                class="font-mono"
                            />
                            <InputError
                                :message="errorAt(`shared_paths.${i}`)"
                            />
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            :aria-label="`Remove ${form.shared_paths[i] || 'shared path'}`"
                            @click="form.shared_paths.splice(i, 1)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                    <InputError :message="form.errors.shared_paths" />
                    <div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="form.shared_paths.push('')"
                        >
                            <Plus class="size-4" /> Add path
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- PRJ-5 / PRJ-7 -->
            <Card>
                <CardHeader>
                    <CardTitle>Build and reload</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4">
                    <ToggleField
                        id="run_composer"
                        v-model="form.run_composer"
                        label="Install Composer dependencies"
                        description="composer install --no-dev --optimize-autoloader"
                    />
                    <ToggleField
                        id="run_npm_build"
                        v-model="form.run_npm_build"
                        label="Build front-end assets"
                        description="npm ci && npm run build (needs Node.js on the server)"
                    />
                    <ToggleField
                        id="run_migrations"
                        v-model="form.run_migrations"
                        label="Run database migrations"
                        description="php artisan migrate --force. Rollbacks switch code only, not the database."
                    />
                    <ToggleField
                        id="queue_restart"
                        v-model="form.queue_restart"
                        label="Restart queue workers"
                        description="php artisan queue:restart after activation"
                    />
                    <div class="grid gap-2 sm:max-w-xs">
                        <Label for="php_fpm_service"
                            >PHP-FPM service to reload</Label
                        >
                        <Input
                            id="php_fpm_service"
                            v-model="form.php_fpm_service"
                            placeholder="php8.3-fpm"
                            spellcheck="false"
                            class="font-mono"
                        />
                        <p class="text-sm text-muted-foreground">
                            Leave empty to skip the reload.
                        </p>
                        <InputError :message="form.errors.php_fpm_service" />
                    </div>
                </CardContent>
            </Card>

            <!-- PRJ-6 -->
            <Card>
                <CardHeader>
                    <CardTitle>Hooks</CardTitle>
                    <CardDescription>
                        Your own commands, run in the release directory as the
                        SSH user. A failing hook stops the deployment.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-3">
                    <p
                        v-if="form.hooks.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No hooks.
                    </p>
                    <div
                        v-for="(hook, i) in form.hooks"
                        :key="i"
                        class="grid gap-2 rounded-md border p-3 sm:grid-cols-[12rem_1fr_auto] sm:items-start"
                    >
                        <div class="grid gap-1">
                            <Label :for="`hook-stage-${i}`" class="sr-only"
                                >Stage</Label
                            >
                            <select
                                :id="`hook-stage-${i}`"
                                v-model="hook.stage"
                                :class="selectClass"
                            >
                                <option
                                    v-for="stage in hookStages"
                                    :key="stage.value"
                                    :value="stage.value"
                                >
                                    {{ stage.label }}
                                </option>
                            </select>
                        </div>
                        <div class="grid gap-1">
                            <Label :for="`hook-command-${i}`" class="sr-only"
                                >Command</Label
                            >
                            <Input
                                :id="`hook-command-${i}`"
                                v-model="hook.command"
                                placeholder="php artisan storage:link"
                                spellcheck="false"
                                class="font-mono"
                            />
                            <InputError
                                :message="
                                    errorAt(`hooks.${i}.command`) ??
                                    errorAt(`hooks.${i}.stage`)
                                "
                            />
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label="Remove hook"
                            @click="form.hooks.splice(i, 1)"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </div>
                    <div>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addHook"
                        >
                            <Plus class="size-4" /> Add hook
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <!-- PRJ-8 / PRJ-9 -->
            <Card>
                <CardHeader>
                    <CardTitle>Releases and health check</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4">
                    <div class="grid gap-2 sm:max-w-xs">
                        <Label for="keep_releases">Releases to keep</Label>
                        <Input
                            id="keep_releases"
                            :model-value="form.keep_releases"
                            @update:model-value="
                                form.keep_releases = Number($event)
                            "
                            type="number"
                            min="1"
                            max="50"
                        />
                        <InputError :message="form.errors.keep_releases" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_8rem]">
                        <div class="grid gap-2">
                            <Label for="health_check_url"
                                >Health-check URL</Label
                            >
                            <Input
                                id="health_check_url"
                                v-model="form.health_check_url"
                                type="url"
                                :placeholder="healthCheckExample"
                            />
                            <InputError
                                :message="form.errors.health_check_url"
                            />
                        </div>
                        <div class="grid gap-2">
                            <Label for="health_check_status"
                                >Expected status</Label
                            >
                            <Input
                                id="health_check_status"
                                :model-value="form.health_check_status"
                                @update:model-value="
                                    form.health_check_status = Number($event)
                                "
                                type="number"
                                min="100"
                                max="599"
                            />
                            <InputError
                                :message="form.errors.health_check_status"
                            />
                        </div>
                    </div>
                    <ToggleField
                        id="auto_rollback"
                        v-model="form.auto_rollback"
                        label="Roll back automatically when the health check fails"
                    />
                    <InputError :message="form.errors.auto_rollback" />
                </CardContent>
            </Card>

            <div class="flex items-center gap-3">
                <Button
                    type="submit"
                    :disabled="form.processing"
                    data-test="save-settings"
                >
                    <Spinner v-if="form.processing" />
                    Save settings
                </Button>
                <span
                    v-if="form.recentlySuccessful"
                    class="text-sm text-muted-foreground"
                    role="status"
                    >Saved.</span
                >
            </div>
        </form>

        <Card class="border-destructive/40">
            <CardHeader>
                <CardTitle>Delete project</CardTitle>
                <CardDescription>
                    Removes the project from Pushpilot. Files on the server and
                    the deploy key in GitHub are not touched.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Dialog>
                    <DialogTrigger as-child>
                        <Button
                            variant="destructive"
                            data-test="delete-project"
                        >
                            <Trash2 class="size-4" /> Delete project
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle
                                >Delete {{ project.name }}?</DialogTitle
                            >
                            <DialogDescription
                                >This cannot be undone.</DialogDescription
                            >
                        </DialogHeader>
                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button type="button" variant="secondary"
                                    >Cancel</Button
                                >
                            </DialogClose>
                            <Button
                                variant="destructive"
                                :disabled="deleteForm.processing"
                                data-test="confirm-delete-project"
                                @click="
                                    deleteForm.submit(
                                        ProjectController.destroy(project.id),
                                    )
                                "
                            >
                                <Spinner v-if="deleteForm.processing" />
                                Delete
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </CardContent>
        </Card>
    </div>
</template>
