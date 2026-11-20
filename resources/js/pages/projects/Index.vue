<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { FolderGit2, Plus } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, index, show } from '@/routes/projects';
import type { ProjectSummary } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Projects', href: index() }],
    },
});

defineProps<{
    projects: ProjectSummary[];
}>();
</script>

<template>
    <Head title="Projects" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                title="Projects"
                description="Repositories Pushpilot deploys, each to a path on one server."
            />
            <Button as-child>
                <Link :href="create()" data-test="add-project">
                    <Plus class="size-4" /> New project
                </Link>
            </Button>
        </div>

        <div
            v-if="projects.length === 0"
            class="flex flex-col items-center gap-3 rounded-xl border border-dashed p-10 text-center"
        >
            <FolderGit2
                class="size-8 text-muted-foreground"
                aria-hidden="true"
            />
            <p class="font-medium">No projects yet</p>
            <p class="max-w-sm text-sm text-muted-foreground">
                A project is a Git repository and branch deployed to a path on
                one of your servers.
            </p>
        </div>

        <div v-else class="overflow-x-auto rounded-xl border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th scope="col" class="px-4 py-2 font-medium">Name</th>
                        <th scope="col" class="px-4 py-2 font-medium">
                            Repository
                        </th>
                        <th scope="col" class="px-4 py-2 font-medium">
                            Server
                        </th>
                        <th scope="col" class="px-4 py-2 font-medium">
                            Status
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="project in projects"
                        :key="project.id"
                        class="border-t hover:bg-muted/30"
                    >
                        <td class="px-4 py-3 font-medium">
                            <Link
                                :href="show(project.id)"
                                class="underline-offset-4 hover:underline focus-visible:underline"
                            >
                                {{ project.name }}
                            </Link>
                        </td>
                        <td class="px-4 py-3 font-mono text-xs">
                            {{ project.repository_url }}
                            <span class="text-muted-foreground"
                                >@ {{ project.branch }}</span
                            >
                        </td>
                        <td class="px-4 py-3">{{ project.server.name }}</td>
                        <td class="px-4 py-3">
                            <Badge
                                :variant="
                                    project.initialized_at
                                        ? 'secondary'
                                        : 'outline'
                                "
                            >
                                {{
                                    project.initialized_at
                                        ? 'Initialized'
                                        : 'Not initialized'
                                }}
                            </Badge>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
