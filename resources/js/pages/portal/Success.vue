<script setup lang="ts">
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { CalendarCheck, Check, CircleAlert, Clock, Copy } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/money';

interface PortalTenant {
    name: string;
    slug: string;
    color: string | null;
    logo: string | null;
}

interface BookingLine {
    reference: string;
    court: string;
    starts_at: string;
    amount: number;
}

interface Totals {
    amount: number;
    deposit_paid: number;
    balance_due: number;
}

const props = defineProps<{
    tenant: PortalTenant;
    reference: string;
    status: string;
    partySize: number | null;
    bookings: BookingLine[];
    totals: Totals;
    dropped: string[];
}>();

const accent = computed(() => props.tenant.color ?? '#16a34a');
const isPending = computed(() => props.status === 'pending_payment');
const isConfirmed = computed(
    () => props.status === 'confirmed' || props.status === 'completed',
);
const failed = computed(() => props.status === 'failed' || props.status === 'expired');
const multiple = computed(() => props.bookings.length > 1);

usePoll(3000, {}, { autoStart: isPending.value });

const exitUrl = '/';

function trapBack() {
    window.location.replace(exitUrl);
}

onMounted(() => {
    window.history.pushState(null, '', window.location.href);
    window.addEventListener('popstate', trapBack);
});

onBeforeUnmount(() => window.removeEventListener('popstate', trapBack));

const copied = ref(false);

async function copyReference() {
    try {
        await navigator.clipboard.writeText(props.reference);
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    } catch {
        // Clipboard unavailable — the reference is shown in full below.
    }
}

function done() {
    router.visit(exitUrl, { replace: true });
}
</script>

<template>
    <Head :title="`Booking confirmed — ${tenant.name}`" />

    <div class="flex min-h-screen items-center justify-center bg-neutral-50 px-4 py-10 dark:bg-neutral-950">
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
                <template v-else-if="isPending">Finalizing your payment…</template>
                <template v-else>Payment didn't go through</template>
            </h1>

            <p class="mt-1 text-sm text-muted-foreground" role="status">
                <template v-if="isConfirmed">
                    A confirmation email is on its way. Save your reference below
                    — you'll need it to cancel later.
                </template>
                <template v-else-if="isPending">
                    This updates automatically once your payment is verified.
                </template>
                <template v-else>
                    Your payment wasn't completed and the slots were released.
                </template>
            </p>

            <!-- Slots that couldn't be secured at checkout (best-effort) -->
            <div
                v-if="dropped.length"
                class="mt-5 rounded-xl border border-amber-300 bg-amber-50 p-3 text-left text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200"
            >
                <p class="font-medium">Couldn't book (taken during checkout):</p>
                <ul class="mt-1 list-inside list-disc">
                    <li v-for="slot in dropped" :key="slot">{{ slot }}</li>
                </ul>
                <p class="mt-1 text-xs">You were not charged for these.</p>
            </div>

            <!-- Booking reference (the representative one for a group) -->
            <div class="mt-6 rounded-xl border bg-neutral-50 p-4 text-left dark:bg-neutral-950">
                <p class="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                    Booking reference
                </p>
                <div class="mt-1 flex items-center justify-between gap-2">
                    <code class="font-mono text-sm break-all">{{ reference }}</code>
                    <button
                        type="button"
                        class="inline-flex shrink-0 items-center gap-1 rounded-md border px-2 py-1 text-xs transition-colors hover:bg-muted"
                        @click="copyReference"
                    >
                        <component :is="copied ? Check : Copy" class="size-3.5" />
                        {{ copied ? 'Copied' : 'Copy' }}
                    </button>
                </div>
                <p v-if="multiple" class="mt-2 text-xs text-muted-foreground">
                    Each court below has its own reference for cancelling individually.
                </p>
            </div>

            <!-- The booked slots -->
            <ul class="mt-4 space-y-2 text-left text-sm">
                <li
                    v-for="line in bookings"
                    :key="line.reference"
                    class="flex justify-between gap-2 rounded-lg bg-neutral-50 px-4 py-2 dark:bg-neutral-950"
                >
                    <span>
                        <span class="font-medium">{{ line.court }}</span>
                        <span class="block text-xs text-muted-foreground">{{ line.starts_at }}</span>
                    </span>
                    <span class="tabular-nums">{{ formatMoney(line.amount) }}</span>
                </li>
            </ul>

            <dl class="mt-4 space-y-2 rounded-xl bg-neutral-50 p-4 text-left text-sm dark:bg-neutral-950">
                <div v-if="partySize" class="flex justify-between">
                    <dt class="text-muted-foreground">Players</dt>
                    <dd class="font-medium">{{ partySize }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-muted-foreground">Total</dt>
                    <dd class="font-medium tabular-nums">{{ formatMoney(totals.amount) }}</dd>
                </div>
                <template v-if="totals.balance_due > 0 && !failed">
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">{{ isConfirmed ? 'Deposit paid' : 'Deposit' }}</dt>
                        <dd class="font-medium tabular-nums">{{ formatMoney(totals.deposit_paid) }}</dd>
                    </div>
                    <div class="flex justify-between text-emerald-700 dark:text-emerald-400">
                        <dt>Pay on-site</dt>
                        <dd class="font-semibold tabular-nums">{{ formatMoney(totals.balance_due) }}</dd>
                    </div>
                </template>
            </dl>

            <div class="mt-6 flex flex-col gap-2">
                <Button class="w-full text-white" :style="{ backgroundColor: accent }" @click="done">
                    Done
                </Button>
                <Button v-if="failed" variant="outline" class="w-full" as-child>
                    <Link :href="`/${tenant.slug}`">Start a new booking</Link>
                </Button>
            </div>
        </div>
    </div>
</template>
