<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import ProjectController from '@/actions/App/Http/Controllers/ProjectController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { create, index } from '@/routes/projects';
import { create as createServer } from '@/routes/servers';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Projects', href: index() },
            { title: 'New project', href: create() },
        ],
    },
});

const props = defineProps<{
    servers: { id: number; name: string; host: string }[];
    selectedServerId: number | null;
}>();

const name = ref('');
const deployPath = ref('');
const deployPathTouched = ref(false);

// Suggest /var/www/<name> until the user edits the path themselves.
watch(name, (value) => {
    if (!deployPathTouched.value) {
        const slug = value
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
        deployPath.value = slug ? `/var/www/${slug}` : '';
    }
});

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';
</script>

<template>
    <Head title="New project" />

    <div class="flex max-w-2xl flex-col gap-6 p-4">
        <Heading
            title="New project"
            description="Pushpilot generates a read-only deploy key for the repository. You will add it to GitHub on the next screen."
        />

        <div
            v-if="props.servers.length === 0"
            class="rounded-xl border border-dashed p-6 text-sm"
        >
            Add a server first.
            <Link
                :href="createServer()"
                class="font-medium underline underline-offset-4"
            >
                Add server
            </Link>
        </div>

        <Form
            v-else
            v-bind="ProjectController.store.form()"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    v-model="name"
                    name="name"
                    required
                    maxlength="100"
                    v-focus
                    placeholder="My app"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="server_id">Server</Label>
                <select
                    id="server_id"
                    name="server_id"
                    required
                    :class="selectClass"
                    :value="props.selectedServerId ?? props.servers[0]?.id"
                >
                    <option
                        v-for="server in props.servers"
                        :key="server.id"
                        :value="server.id"
                    >
                        {{ server.name }} ({{ server.host }})
                    </option>
                </select>
                <InputError :message="errors.server_id" />
            </div>

            <div class="grid gap-2">
                <Label for="repository_url">Repository (SSH URL)</Label>
                <Input
                    id="repository_url"
                    name="repository_url"
                    required
                    maxlength="255"
                    spellcheck="false"
                    autocomplete="off"
                    placeholder="git@github.com:acme/my-app.git"
                />
                <InputError :message="errors.repository_url" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="branch">Branch</Label>
                    <Input
                        id="branch"
                        name="branch"
                        required
                        maxlength="255"
                        spellcheck="false"
                        default-value="main"
                    />
                    <InputError :message="errors.branch" />
                </div>

                <div class="grid gap-2">
                    <Label for="deploy_path">Deploy path</Label>
                    <Input
                        id="deploy_path"
                        v-model="deployPath"
                        name="deploy_path"
                        required
                        maxlength="255"
                        spellcheck="false"
                        placeholder="/var/www/my-app"
                        @input="deployPathTouched = true"
                    />
                    <InputError :message="errors.deploy_path" />
                </div>
            </div>

            <div class="flex items-center gap-3">
                <Button
                    type="submit"
                    :disabled="processing"
                    data-test="save-project"
                >
                    <Spinner v-if="processing" />
                    Create project
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="index()">Cancel</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
