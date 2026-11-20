<script setup lang="ts">
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';

defineProps<{
    id: string;
    label: string;
    description?: string;
}>();

const model = defineModel<boolean>({ required: true });
</script>

<template>
    <div class="flex items-start gap-3">
        <Checkbox
            :id="id"
            :model-value="model"
            class="mt-0.5"
            :aria-describedby="description ? `${id}-description` : undefined"
            @update:model-value="model = $event === true"
        />
        <div class="grid gap-1">
            <Label :for="id">{{ label }}</Label>
            <p
                v-if="description"
                :id="`${id}-description`"
                class="text-sm text-muted-foreground"
            >
                {{ description }}
            </p>
        </div>
    </div>
</template>
