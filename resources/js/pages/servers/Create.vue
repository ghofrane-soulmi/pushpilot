<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import ServerController from '@/actions/App/Http/Controllers/ServerController';
import Heading from '@/components/Heading.vue';
import ServerFormFields from '@/components/servers/ServerFormFields.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { create, index } from '@/routes/servers';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Servers', href: index() },
            { title: 'Add server', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="Add server" />

    <div class="flex max-w-2xl flex-col gap-6 p-4">
        <Heading
            title="Add server"
            description="Pushpilot will generate an ed25519 key pair for this server. You will authorize it on the next screen."
        />

        <Form
            v-bind="ServerController.store.form()"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <ServerFormFields :errors="errors" />

            <div class="flex items-center gap-3">
                <Button
                    type="submit"
                    :disabled="processing"
                    data-test="save-server"
                >
                    <Spinner v-if="processing" />
                    Add server
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="index()">Cancel</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
