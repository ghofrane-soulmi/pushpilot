<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { KeyRound, Lock, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import ProjectEnvironmentController from '@/actions/App/Http/Controllers/ProjectEnvironmentController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import ProjectNav from '@/components/projects/ProjectNav.vue';
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
import { index, show } from '@/routes/projects';
import { edit as editEnvironment } from '@/routes/projects/environment';
import type { ProjectDetails } from '@/types';

const props = defineProps<{
    project: ProjectDetails;
}>();

defineOptions({
    layout: (props: { project: ProjectDetails }) => ({
        breadcrumbs: [
            { title: 'Projects', href: index() },
            { title: props.project.name, href: show(props.project.id) },
            { title: 'Environment', href: editEnvironment(props.project.id) },
        ],
    }),
});

const variable = useForm({ key: '', value: '' });
const replacement = useForm({ content: '' });
const replaceOpen = ref(false);

const syncedAt = computed(() =>
    props.project.environment.synced_at
        ? new Date(props.project.environment.synced_at).toLocaleString()
        : null,
);

function setVariable(): void {
    variable.submit(ProjectEnvironmentController.set(props.project.id), {
        preserveScroll: true,
        onSuccess: () => variable.reset(),
    });
}

function editVariable(key: string): void {
    variable.key = key;
    variable.value = '';
    document.getElementById('env-value')?.focus();
}

function removeVariable(key: string): void {
    router.visit(
        ProjectEnvironmentController.remove({ project: props.project.id, key }),
        {
            preserveScroll: true,
        },
    );
}

function replaceFile(): void {
    replacement.submit(ProjectEnvironmentController.replace(props.project.id), {
        preserveScroll: true,
        onSuccess: () => {
            replacement.reset();
            replaceOpen.value = false;
        },
    });
}
</script>

<template>
    <Head :title="`${project.name} environment`" />

    <div class="flex max-w-4xl flex-col gap-6 p-4">
        <Heading
            :title="project.name"
            :description="`${project.repository_url} @ ${project.branch}`"
        />

        <ProjectNav :project-id="project.id" current="environment" />

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <Lock class="size-4" aria-hidden="true" /> Environment
                    (.env)
                </CardTitle>
                <CardDescription>
                    Stored encrypted and written to <code>shared/.env</code> on
                    the server. Values are write-only: once saved they are never
                    shown again. To change one, set it again.
                    <span v-if="syncedAt" class="block pt-1"
                        >Last written to the server {{ syncedAt }}.</span
                    >
                    <span
                        v-else-if="project.environment.keys.length"
                        class="block pt-1"
                    >
                        Not written to the server yet{{
                            project.initialized_at
                                ? ''
                                : ' — initialize the project first'
                        }}.
                    </span>
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-6">
                <p
                    v-if="project.environment.keys.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    No variables yet. Add them one by one, or paste a whole .env
                    file.
                </p>
                <ul
                    v-else
                    class="divide-y rounded-md border"
                    data-test="env-keys"
                >
                    <li
                        v-for="key in project.environment.keys"
                        :key="key"
                        class="flex items-center justify-between gap-2 px-3 py-2"
                    >
                        <span class="font-mono text-sm">
                            {{ key }}
                            <span
                                class="text-muted-foreground"
                                aria-label="value hidden"
                                >= ••••••••</span
                            >
                        </span>
                        <span class="flex gap-1">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="editVariable(key)"
                            >
                                <KeyRound class="size-4" /> Change
                            </Button>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :aria-label="`Remove ${key}`"
                                @click="removeVariable(key)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </span>
                    </li>
                </ul>

                <form
                    class="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-start"
                    @submit.prevent="setVariable"
                >
                    <div class="grid gap-1">
                        <Label for="env-key">Name</Label>
                        <Input
                            id="env-key"
                            v-model="variable.key"
                            placeholder="APP_URL"
                            spellcheck="false"
                            autocomplete="off"
                            class="font-mono"
                            required
                        />
                        <InputError :message="variable.errors.key" />
                    </div>
                    <div class="grid gap-1">
                        <Label for="env-value">Value</Label>
                        <PasswordInput
                            id="env-value"
                            v-model="variable.value"
                            autocomplete="off"
                            spellcheck="false"
                            class="font-mono"
                        />
                        <InputError :message="variable.errors.value" />
                    </div>
                    <Button
                        type="submit"
                        class="sm:mt-6"
                        :disabled="variable.processing"
                        data-test="set-variable"
                    >
                        <Spinner v-if="variable.processing" />
                        Save variable
                    </Button>
                </form>

                <Dialog v-model:open="replaceOpen">
                    <DialogTrigger as-child>
                        <Button variant="outline" data-test="replace-env"
                            >Replace the whole file…</Button
                        >
                    </DialogTrigger>
                    <DialogContent class="sm:max-w-2xl">
                        <DialogHeader>
                            <DialogTitle>Replace .env</DialogTitle>
                            <DialogDescription>
                                Every current variable is replaced by what you
                                paste here.
                            </DialogDescription>
                        </DialogHeader>
                        <form class="grid gap-3" @submit.prevent="replaceFile">
                            <Label for="env-content" class="sr-only"
                                >.env content</Label
                            >
                            <textarea
                                id="env-content"
                                v-model="replacement.content"
                                rows="14"
                                spellcheck="false"
                                autocomplete="off"
                                class="w-full rounded-md border border-input bg-transparent p-3 font-mono text-xs shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30"
                                placeholder="APP_NAME=MyApp&#10;APP_ENV=production&#10;APP_KEY=base64:..."
                            />
                            <InputError :message="replacement.errors.content" />
                            <DialogFooter class="gap-2">
                                <DialogClose as-child>
                                    <Button type="button" variant="secondary"
                                        >Cancel</Button
                                    >
                                </DialogClose>
                                <Button
                                    type="submit"
                                    :disabled="replacement.processing"
                                    data-test="confirm-replace-env"
                                >
                                    <Spinner v-if="replacement.processing" />
                                    Replace
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </CardContent>
        </Card>
    </div>
</template>
