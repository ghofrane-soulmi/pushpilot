<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import {
    CircleCheck,
    CircleX,
    Pencil,
    PlugZap,
    ScanSearch,
    Trash2,
} from '@lucide/vue';
import { computed } from 'vue';
import ServerConnectionController from '@/actions/App/Http/Controllers/ServerConnectionController';
import ServerController from '@/actions/App/Http/Controllers/ServerController';
import CopyableCode from '@/components/CopyableCode.vue';
import Heading from '@/components/Heading.vue';
import ServerStatusBadge from '@/components/servers/ServerStatusBadge.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import { Spinner } from '@/components/ui/spinner';
import { edit, index, show } from '@/routes/servers';
import type { ConnectionTestFlash, ServerDetails } from '@/types';

const props = defineProps<{
    server: ServerDetails;
}>();

defineOptions({
    layout: (props: { server: ServerDetails }) => ({
        breadcrumbs: [
            { title: 'Servers', href: index() },
            { title: props.server.name, href: show(props.server.id) },
        ],
    }),
});

const page = usePage();

const connectionTest = computed(
    () =>
        (page.flash as { connectionTest?: ConnectionTestFlash }).connectionTest,
);

const lastChecked = computed(() =>
    props.server.last_checked_at
        ? new Date(props.server.last_checked_at).toLocaleString()
        : null,
);
</script>

<template>
    <Head :title="server.name" />

    <div class="flex max-w-4xl flex-col gap-6 p-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <Heading
                    :title="server.name"
                    :description="`${server.username}@${server.host}:${server.port}`"
                />
                <div
                    class="-mt-6 flex items-center gap-2 text-sm text-muted-foreground"
                >
                    <ServerStatusBadge
                        :status="server.status"
                        :last-checked-at="server.last_checked_at"
                    />
                    <span v-if="lastChecked"
                        >Last checked {{ lastChecked }}</span
                    >
                </div>
            </div>
            <Button variant="outline" as-child>
                <Link :href="edit(server.id)">
                    <Pencil class="size-4" /> Edit
                </Link>
            </Button>
        </div>

        <!-- SRV-2: authorize the generated key -->
        <Card>
            <CardHeader>
                <CardTitle>1. Authorize Pushpilot's key</CardTitle>
                <CardDescription>
                    Pushpilot generated an SSH key for this server. Log in to
                    the server as <strong>{{ server.username }}</strong> and run
                    the command below once. The private key never leaves
                    Pushpilot.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-1.5">
                    <p class="text-sm font-medium">
                        Command to run on the server
                    </p>
                    <CopyableCode
                        :value="server.authorize_command"
                        label="authorize command"
                    />
                </div>
                <div class="space-y-1.5">
                    <p class="text-sm font-medium">Public key</p>
                    <CopyableCode
                        :value="server.public_key"
                        label="public key"
                    />
                </div>
            </CardContent>
        </Card>

        <!-- SRV-3: test connection -->
        <Card>
            <CardHeader>
                <CardTitle>2. Test the connection</CardTitle>
                <CardDescription>
                    Connects over SSH and runs <code>whoami</code>.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <Form
                    v-bind="ServerConnectionController.test.form(server.id)"
                    v-slot="{ processing }"
                    :options="{ preserveScroll: true }"
                >
                    <Button
                        type="submit"
                        :disabled="processing"
                        data-test="test-connection"
                    >
                        <Spinner v-if="processing" />
                        <PlugZap v-else class="size-4" />
                        Test connection
                    </Button>
                </Form>

                <Alert
                    v-if="connectionTest"
                    :variant="
                        connectionTest.successful ? 'default' : 'destructive'
                    "
                    role="status"
                    data-test="connection-result"
                >
                    <CircleCheck
                        v-if="connectionTest.successful"
                        class="size-4"
                    />
                    <CircleX v-else class="size-4" />
                    <AlertTitle>
                        {{
                            connectionTest.successful
                                ? 'Connected'
                                : 'Connection failed'
                        }}
                    </AlertTitle>
                    <AlertDescription>{{
                        connectionTest.message
                    }}</AlertDescription>
                </Alert>
            </CardContent>
        </Card>

        <!-- SRV-4: requirements -->
        <Card>
            <CardHeader>
                <CardTitle>3. Check requirements</CardTitle>
                <CardDescription>
                    Pushpilot checks what is installed; it never installs
                    anything on your server.
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <Form
                    v-bind="ServerConnectionController.check.form(server.id)"
                    v-slot="{ processing }"
                    :options="{ preserveScroll: true }"
                >
                    <Button
                        type="submit"
                        variant="secondary"
                        :disabled="processing"
                        data-test="check-requirements"
                    >
                        <Spinner v-if="processing" />
                        <ScanSearch v-else class="size-4" />
                        {{
                            server.requirements
                                ? 'Check again'
                                : 'Check requirements'
                        }}
                    </Button>
                </Form>

                <p
                    v-if="!server.requirements"
                    class="text-sm text-muted-foreground"
                >
                    Not checked yet.
                </p>

                <ul
                    v-else
                    class="divide-y rounded-md border"
                    data-test="requirements"
                >
                    <li
                        v-for="check in server.requirements.checks"
                        :key="check.key"
                        class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-start sm:gap-4"
                    >
                        <div
                            class="flex min-w-40 items-center gap-2 font-medium"
                        >
                            <CircleCheck
                                v-if="check.ok"
                                class="size-4 text-emerald-600"
                                aria-label="Installed"
                            />
                            <CircleX
                                v-else
                                :class="[
                                    'size-4',
                                    check.required
                                        ? 'text-red-600'
                                        : 'text-amber-500',
                                ]"
                                :aria-label="
                                    check.required
                                        ? 'Missing'
                                        : 'Missing (optional)'
                                "
                            />
                            {{ check.label }}
                            <span
                                v-if="!check.required"
                                class="text-xs font-normal text-muted-foreground"
                                >optional</span
                            >
                        </div>
                        <div class="text-sm">
                            <span v-if="check.version" class="font-mono">{{
                                check.version
                            }}</span>
                            <span v-else class="text-muted-foreground"
                                >Not found</span
                            >
                            <p
                                v-if="check.hint"
                                class="mt-1 text-muted-foreground"
                            >
                                Fix:
                                <code class="font-mono text-xs">{{
                                    check.hint
                                }}</code>
                            </p>
                        </div>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <!-- SRV-5: delete -->
        <Card class="border-destructive/40">
            <CardHeader>
                <CardTitle>Delete server</CardTitle>
                <CardDescription>
                    Removes the server and its SSH key from Pushpilot. Nothing
                    is changed on the server itself.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Dialog>
                    <DialogTrigger as-child>
                        <Button variant="destructive" data-test="delete-server">
                            <Trash2 class="size-4" /> Delete server
                        </Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Delete {{ server.name }}?</DialogTitle>
                            <DialogDescription>
                                Pushpilot's key stays in the server's
                                authorized_keys until you remove it there.
                            </DialogDescription>
                        </DialogHeader>
                        <Form
                            v-bind="ServerController.destroy.form(server.id)"
                            v-slot="{ processing }"
                        >
                            <DialogFooter class="gap-2">
                                <DialogClose as-child>
                                    <Button type="button" variant="secondary"
                                        >Cancel</Button
                                    >
                                </DialogClose>
                                <Button
                                    type="submit"
                                    variant="destructive"
                                    :disabled="processing"
                                    data-test="confirm-delete-server"
                                >
                                    <Spinner v-if="processing" />
                                    Delete
                                </Button>
                            </DialogFooter>
                        </Form>
                    </DialogContent>
                </Dialog>
            </CardContent>
        </Card>
    </div>
</template>
