<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import ServerController from '@/actions/App/Http/Controllers/ServerController';
import Heading from '@/components/Heading.vue';
import ServerFormFields from '@/components/servers/ServerFormFields.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { index, show } from '@/routes/servers';
import type { Server } from '@/types';

defineProps<{
    server: Server;
}>();

defineOptions({
    layout: (props: { server: Server }) => ({
        breadcrumbs: [
            { title: 'Servers', href: index() },
            { title: props.server.name, href: show(props.server.id) },
            { title: 'Edit', href: '#' },
        ],
    }),
});
</script>

<template>
    <Head :title="`Edit ${server.name}`" />

    <div class="flex max-w-2xl flex-col gap-6 p-4">
        <Heading
            title="Edit server"
            description="The SSH key stays the same. Changing the address resets the status until the next check."
        />

        <Form
            v-bind="ServerController.update.form(server.id)"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <ServerFormFields :errors="errors" :server="server" />

            <div class="flex items-center gap-3">
                <Button
                    type="submit"
                    :disabled="processing"
                    data-test="save-server"
                >
                    <Spinner v-if="processing" />
                    Save
                </Button>
                <Button variant="ghost" as-child>
                    <Link :href="show(server.id)">Cancel</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
