<script setup lang="ts">
import { Head, Link, usePoll } from '@inertiajs/vue3';
import { CalendarCheck, CircleAlert, Clock } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/money';

interface PortalTenant {
    name: string;
    slug: string;
    color: string | null;
    logo: string | null;
}

interface BookingSummary {
    reference: string;
    court: string;
    starts_at: string;
    status: string;
    amount: number;
    deposit_paid: number;
    balance_due: number;
    party_size: number | null;
    player_name: string;
    checkout_url: string | null;
}

const props = defineProps<{
    tenant: PortalTenant;
    booking: BookingSummary;
}>();

const accent = computed(() => props.tenant.color ?? '#16a34a');
const isPending = computed(() => props.booking.status === 'pending_payment');
const isConfirmed = computed(
    () => props.booking.status === 'confirmed' || props.booking.status === 'completed',
);

// While payment is in flight, watch for the webhook to confirm the booking.
usePoll(3000, {}, { autoStart: isPending.value });
</script>

<template>
    <Head :title="`Booking ${booking.reference.slice(0, 8)} — ${tenant.name}`" />

    <div class="flex min-h-screen items-center justify-center bg-neutral-50 px-4 dark:bg-neutral-950">
        <div class="w-full max-w-md rounded-2xl border bg-white p-8 text-center shadow-sm dark:bg-neutral-900">
            <div
                class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full"
                :class="
                    isConfirmed
                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'
                        : isPending
                          ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300'
                          : 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300'
                "
            >
                <CalendarCheck v-if="isConfirmed" class="size-7" />
                <Clock v-else-if="isPending" class="size-7 animate-pulse" />
                <CircleAlert v-else class="size-7" />
            </div>

            <h1 class="text-xl font-semibold">
                <template v-if="isConfirmed">You're booked!</template>
                <template v-else-if="isPending">Waiting for payment…</template>
                <template v-else>This booking is {{ booking.status.replace('_', ' ') }}</template>
            </h1>

            <p class="mt-1 text-sm text-muted-foreground" role="status">
                <template v-if="isPending">
                    Finish checkout to lock in your slot. This page updates
                    automatically.
                </template>
                <template v-else-if="isConfirmed">
                    A confirmation email is on its way.
                </template>
                <template v-else>
                    The slot has been released — you can pick another time.
                </template>
            </p>

            <dl class="mt-6 space-y-2 rounded-xl bg-neutral-50 p-4 text-left text-sm dark:bg-neutral-950">
                <div class="flex justify-between">
                    <dt class="text-muted-foreground">Court</dt>
                    <dd class="font-medium">{{ booking.court }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-muted-foreground">When</dt>
                    <dd class="font-medium">{{ booking.starts_at }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-muted-foreground">Player</dt>
                    <dd class="font-medium">{{ booking.player_name }}</dd>
                </div>
                <div v-if="booking.party_size" class="flex justify-between">
                    <dt class="text-muted-foreground">Players</dt>
                    <dd class="font-medium">{{ booking.party_size }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-muted-foreground">Total</dt>
                    <dd class="font-medium tabular-nums">{{ formatMoney(booking.amount) }}</dd>
                </div>
                <template v-if="booking.balance_due > 0">
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">{{ isConfirmed ? 'Deposit paid' : 'Deposit' }}</dt>
                        <dd class="font-medium tabular-nums">{{ formatMoney(booking.deposit_paid) }}</dd>
                    </div>
                    <div class="flex justify-between text-emerald-700 dark:text-emerald-400">
                        <dt>Pay on-site</dt>
                        <dd class="font-semibold tabular-nums">{{ formatMoney(booking.balance_due) }}</dd>
                    </div>
                </template>
                <div class="flex justify-between">
                    <dt class="text-muted-foreground">Reference</dt>
                    <dd class="font-mono text-xs">{{ booking.reference.slice(0, 13) }}</dd>
                </div>
            </dl>

            <div class="mt-6 flex flex-col gap-2">
                <Button
                    v-if="isPending && booking.checkout_url"
                    class="w-full text-white"
                    :style="{ backgroundColor: accent }"
                    as-child
                >
                    <a :href="booking.checkout_url">Finish payment</a>
                </Button>
                <Button variant="outline" class="w-full" as-child>
                    <Link :href="`/${tenant.slug}`">Back to {{ tenant.name }}</Link>
                </Button>
            </div>

            <p v-if="isConfirmed" class="mt-4 text-center text-sm text-muted-foreground">
                Can't make it?
                <Link
                    :href="`/${tenant.slug}/cancel?reference=${booking.reference}`"
                    class="font-medium text-foreground underline underline-offset-2"
                >
                    Cancel this booking
                </Link>
            </p>
        </div>
    </div>
</template>
