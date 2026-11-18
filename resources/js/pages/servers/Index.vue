<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus, Server as ServerIcon } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import ServerStatusBadge from '@/components/servers/ServerStatusBadge.vue';
import { Button } from '@/components/ui/button';
import { create, index, show } from '@/routes/servers';
import type { Server } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Servers', href: index() }],
    },
});

defineProps<{
    servers: Server[];
}>();
</script>

<template>
    <Head title="Servers" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Servers"
                description="Machines Pushpilot deploys to over SSH."
            />
            <Button as-child>
                <Link :href="create()" data-test="add-server">
                    <Plus class="size-4" /> Add server
                </Link>
            </Button>
        </div>

        <div
            v-if="servers.length === 0"
            class="flex flex-col items-center gap-3 rounded-xl border border-dashed p-10 text-center"
        >
            <ServerIcon
                class="size-8 text-muted-foreground"
                aria-hidden="true"
            />
            <p class="font-medium">No servers yet</p>
            <p class="max-w-sm text-sm text-muted-foreground">
                Add a VPS. Pushpilot generates a dedicated SSH key for it and
                checks that git, PHP, Composer and PHP-FPM are installed.
            </p>
        </div>

        <div v-else class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th scope="col" class="px-4 py-2 font-medium">Name</th>
                        <th scope="col" class="px-4 py-2 font-medium">
                            Address
                        </th>
                        <th scope="col" class="px-4 py-2 font-medium">
                            Status
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="server in servers"
                        :key="server.id"
                        class="border-t hover:bg-muted/30"
                    >
                        <td class="px-4 py-3 font-medium">
                            <Link
                                :href="show(server.id)"
                                class="underline-offset-4 hover:underline focus-visible:underline"
                            >
                                {{ server.name }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs">
                            {{ server.username }}@{{ server.host }}:{{
                                server.port
                            }}
                        </td>
                        <td class="px-4 py-3">
                            <ServerStatusBadge
                                :status="server.status"
                                :last-checked-at="server.last_checked_at"
                            />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
