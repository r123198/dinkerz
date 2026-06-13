<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/money';

interface PortalTenant {
    name: string;
    slug: string;
    color: string | null;
}

interface PaymentSummary {
    id: number;
    amount: number;
    balance_due: number;
    court: string;
    starts_at: string | null;
    reference: string;
}

const props = defineProps<{
    tenant: PortalTenant;
    payment: PaymentSummary;
}>();

const accent = computed(() => props.tenant.color ?? '#16a34a');
const processing = ref(false);

function complete(outcome: 'paid' | 'failed') {
    processing.value = true;
    router.post(
        `/${props.tenant.slug}/payments/${props.payment.id}/simulate`,
        { outcome },
        { onFinish: () => (processing.value = false) },
    );
}
</script>

<template>
    <Head :title="`Checkout — ${tenant.name}`" />

    <div class="flex min-h-screen items-center justify-center bg-neutral-50 px-4 dark:bg-neutral-950">
        <div class="w-full max-w-sm rounded-2xl border bg-white p-8 dark:bg-neutral-900">
            <p class="mb-1 text-xs font-medium tracking-wide text-muted-foreground uppercase">
                Sandbox checkout
            </p>
            <h1 class="text-lg font-semibold">{{ tenant.name }}</h1>

            <dl class="mt-4 space-y-2 rounded-xl bg-neutral-50 p-4 text-sm dark:bg-neutral-950">
                <div class="flex justify-between">
                    <dt class="text-muted-foreground">Court</dt>
                    <dd class="font-medium">{{ payment.court }}</dd>
                </div>
                <div v-if="payment.starts_at" class="flex justify-between">
                    <dt class="text-muted-foreground">When</dt>
                    <dd class="font-medium">{{ payment.starts_at }}</dd>
                </div>
                <div class="flex justify-between border-t pt-2">
                    <dt class="text-muted-foreground">
                        {{ payment.balance_due > 0 ? 'Deposit due now' : 'Total' }}
                    </dt>
                    <dd class="text-lg font-semibold tabular-nums">
                        {{ formatMoney(payment.amount) }}
                    </dd>
                </div>
                <div
                    v-if="payment.balance_due > 0"
                    class="flex justify-between text-xs text-muted-foreground"
                >
                    <dt>Balance on-site</dt>
                    <dd class="tabular-nums">{{ formatMoney(payment.balance_due) }}</dd>
                </div>
            </dl>

            <div class="mt-6 grid gap-2">
                <Button
                    class="w-full text-white"
                    :style="{ backgroundColor: accent }"
                    :disabled="processing"
                    @click="complete('paid')"
                >
                    Pay {{ formatMoney(payment.amount) }}
                </Button>
                <Button
                    variant="outline"
                    class="w-full"
                    :disabled="processing"
                    @click="complete('failed')"
                >
                    Simulate failed payment
                </Button>
            </div>

            <p class="mt-4 text-center text-xs text-muted-foreground">
                This simulator stands in for GCash / Maya / card checkout in
                local development.
            </p>
        </div>
    </div>
</template>
