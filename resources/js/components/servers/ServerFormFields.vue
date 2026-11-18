<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Server } from '@/types';

defineProps<{
    errors: Partial<Record<string, string>>;
    server?: Server;
}>();
</script>

<template>
    <div class="grid gap-2">
        <Label for="name">Name</Label>
        <Input
            id="name"
            name="name"
            required
            v-focus
            maxlength="100"
            :default-value="server?.name"
            placeholder="production-1"
        />
        <InputError :message="errors.name" />
    </div>

    <div class="grid gap-4 sm:grid-cols-[1fr_8rem]">
        <div class="grid gap-2">
            <Label for="host">Host or IP address</Label>
            <Input
                id="host"
                name="host"
                required
                maxlength="253"
                autocomplete="off"
                spellcheck="false"
                :default-value="server?.host"
                placeholder="203.0.113.10"
            />
            <InputError :message="errors.host" />
        </div>

        <div class="grid gap-2">
            <Label for="port">SSH port</Label>
            <Input
                id="port"
                name="port"
                type="number"
                min="1"
                max="65535"
                required
                :default-value="server?.port ?? 22"
            />
            <InputError :message="errors.port" />
        </div>
    </div>

    <div class="grid gap-2">
        <Label for="username">SSH user</Label>
        <Input
            id="username"
            name="username"
            required
            maxlength="32"
            autocomplete="off"
            spellcheck="false"
            :default-value="server?.username ?? 'deployer'"
            aria-describedby="username-help"
        />
        <p id="username-help" class="text-sm text-muted-foreground">
            A non-root user that owns your deploy path. Pushpilot only needs
            sudo to reload PHP-FPM.
        </p>
        <InputError :message="errors.username" />
    </div>
</template>
