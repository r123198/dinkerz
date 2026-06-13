<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface PortalTenant {
    name: string;
    slug: string;
    color: string | null;
    logo: string | null;
}

const props = defineProps<{
    tenant: PortalTenant;
    reference: string | null;
}>();

const accent = computed(() => props.tenant.color ?? '#16a34a');

const form = useForm({
    reference: props.reference ?? '',
    email: '',
});

function submit() {
    form.post(`/${props.tenant.slug}/cancel`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Cancel a booking — ${tenant.name}`" />

    <div class="flex min-h-screen items-center justify-center bg-neutral-50 px-4 dark:bg-neutral-950">
        <div class="w-full max-w-sm rounded-2xl border bg-white p-8 dark:bg-neutral-900">
            <h1 class="text-lg font-semibold">Cancel a booking</h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Enter your booking reference and the email you booked with.
                Your reference is on your confirmation email and screen.
            </p>

            <form class="mt-6 grid gap-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="reference">Booking reference</Label>
                    <Input
                        id="reference"
                        v-model="form.reference"
                        class="font-mono"
                        placeholder="e.g. 3f9c1a2b-…"
                        required
                    />
                    <InputError :message="form.errors.reference" />
                </div>
                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        placeholder="you@example.com"
                        required
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <Button
                    type="submit"
                    class="w-full text-white"
                    :style="{ backgroundColor: accent }"
                    :disabled="form.processing"
                >
                    {{ form.processing ? 'Cancelling…' : 'Cancel my booking' }}
                </Button>
            </form>

            <p class="mt-4 text-xs text-muted-foreground">
                Cancelling frees the court for other players. Refunds for paid
                bookings are handled by {{ tenant.name }}.
            </p>

            <Link
                :href="`/${tenant.slug}`"
                class="mt-6 inline-flex items-center gap-1.5 text-sm text-muted-foreground transition-colors hover:text-foreground"
            >
                <ArrowLeft class="size-4" />
                Back to {{ tenant.name }}
            </Link>
        </div>
    </div>
</template>
