<script setup lang="ts">
import { computed, useAttrs } from 'vue';
import { cn } from '@/lib/utils';
import {
    LOGO_DARK_GREEN,
    LOGO_NO_BG,
    LOGO_WHITE_BG,
} from '@/lib/branding';

defineOptions({
    inheritAttrs: false,
});

type Variant = 'default' | 'on-dark' | 'on-light';

const props = withDefaults(
    defineProps<{
        variant?: Variant;
    }>(),
    {
        variant: 'default',
    },
);

const attrs = useAttrs();

const src = computed(() => {
    switch (props.variant) {
        case 'on-dark':
            return LOGO_DARK_GREEN;
        case 'on-light':
            return LOGO_WHITE_BG;
        default:
            return LOGO_NO_BG;
    }
});

const invertOnDark = computed(() => props.variant === 'default');
</script>

<template>
    <img
        :src="src"
        alt="CourtOS"
        :class="
            cn(
                invertOnDark && 'dark:brightness-0 dark:invert',
                attrs.class as string,
            )
        "
        v-bind="attrs"
    />
</template>
