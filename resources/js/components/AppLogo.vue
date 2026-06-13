<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const page = usePage();
const appName = page.props.name as string;
</script>

<template>
    <AppLogoIcon class="size-8 shrink-0" />
    <div class="ml-2 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">
            {{ appName }}
        </span>
    </div>
</template>
