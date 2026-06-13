<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ArrowRight, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatMoney } from '@/lib/money';

interface Slot {
    starts_at: string;
    label: string;
    available: boolean;
}

interface Court {
    id: number;
    name: string;
    price: number;
    deposit: number;
    capacity: number;
    slot_minutes: number;
    slots: Slot[];
}

interface PortalTenant {
    name: string;
    slug: string;
    color: string | null;
    logo: string | null;
}

interface DateOption {
    value: string;
    weekday: string;
    day: string;
}

const props = defineProps<{
    tenant: PortalTenant;
    courts: Court[];
    date: string;
    dates: DateOption[];
    waitlistToken: string | null;
}>();

const accent = computed(() => props.tenant.color ?? '#16a34a');

// --- Build a time × court matrix from each court's slots ----------------
interface Cell {
    court: Court;
    slot: Slot | null;
}
interface Row {
    startsAt: string;
    label: string;
    cells: Cell[];
}

const rows = computed<Row[]>(() => {
    const byTime = new Map<string, { label: string }>();
    for (const court of props.courts) {
        for (const slot of court.slots) {
            if (!byTime.has(slot.starts_at)) {
                byTime.set(slot.starts_at, { label: slot.label });
            }
        }
    }

    const times = [...byTime.entries()].sort(([a], [b]) => a.localeCompare(b));

    return times.map(([startsAt, { label }]) => ({
        startsAt,
        label,
        cells: props.courts.map((court) => ({
            court,
            slot: court.slots.find((s) => s.starts_at === startsAt) ?? null,
        })),
    }));
});

// --- Selection (the cart) ----------------------------------------------
interface Selected {
    courtId: number;
    courtName: string;
    startsAt: string;
    label: string;
    price: number;
    deposit: number;
}

const selected = ref<Selected[]>([]);

function cellKey(courtId: number, startsAt: string) {
    return `${courtId}@${startsAt}`;
}

function isSelected(courtId: number, startsAt: string) {
    return selected.value.some(
        (s) => s.courtId === courtId && s.startsAt === startsAt,
    );
}

function toggle(court: Court, slot: Slot) {
    if (!slot.available) {
        return;
    }
    const idx = selected.value.findIndex(
        (s) => s.courtId === court.id && s.startsAt === slot.starts_at,
    );
    if (idx >= 0) {
        selected.value.splice(idx, 1);
    } else {
        selected.value.push({
            courtId: court.id,
            courtName: court.name,
            startsAt: slot.starts_at,
            label: slot.label,
            price: court.price,
            deposit: court.deposit,
        });
    }
}

function remove(item: Selected) {
    selected.value = selected.value.filter(
        (s) => !(s.courtId === item.courtId && s.startsAt === item.startsAt),
    );
}

const totalPrice = computed(() =>
    selected.value.reduce((sum, s) => sum + s.price, 0),
);
const payNow = computed(() =>
    selected.value.reduce((sum, s) => sum + s.deposit, 0),
);
const onSite = computed(() => totalPrice.value - payNow.value);
const hasDeposit = computed(() => onSite.value > 0);

// --- Checkout -----------------------------------------------------------
const checkoutOpen = ref(false);

const form = useForm({
    guest_name: '',
    guest_email: '',
    party_size: '' as number | string,
    waitlist_token: props.waitlistToken,
    slots: [] as { resource_id: number; starts_at: string }[],
});

const suggestedCourts = computed(() => {
    const party = Number(form.party_size);
    if (!party || party < 1 || !props.courts.length) {
        return 0;
    }
    // Use the smallest court capacity as a conservative basis.
    const capacity = Math.min(...props.courts.map((c) => c.capacity));
    return Math.ceil(party / capacity);
});

function openCheckout() {
    if (!selected.value.length) {
        return;
    }
    form.clearErrors();
    form.slots = selected.value.map((s) => ({
        resource_id: s.courtId,
        starts_at: s.startsAt,
    }));
    checkoutOpen.value = true;
}

function pay() {
    form.transform((data) => ({
        ...data,
        slots: selected.value.map((s) => ({
            resource_id: s.courtId,
            starts_at: s.startsAt,
        })),
    })).post(`/${props.tenant.slug}/book`);
}

function pickDate(value: string) {
    router.get(
        `/${props.tenant.slug}`,
        { date: value },
        { preserveState: false, preserveScroll: true },
    );
}

// --- Waitlist for taken slots ------------------------------------------
const waitlistTarget = ref<{ court: Court; startsAt: string; label: string } | null>(null);
const waitlistJoined = ref(false);

const waitlistForm = useForm({
    resource_id: 0,
    starts_at: '',
    guest_name: '',
    guest_email: '',
});

function openWaitlist(court: Court, startsAt: string, label: string) {
    waitlistTarget.value = { court, startsAt, label };
    waitlistJoined.value = false;
    waitlistForm.clearErrors();
    waitlistForm.resource_id = court.id;
    waitlistForm.starts_at = startsAt;
}

function joinWaitlist() {
    waitlistForm.post(`/${props.tenant.slug}/waitlist`, {
        preserveScroll: true,
        onSuccess: () => {
            waitlistJoined.value = true;
        },
    });
}
</script>

<template>
    <Head :title="`Book a court — ${tenant.name}`" />

    <div class="min-h-screen bg-neutral-50 pb-28 dark:bg-neutral-950">
        <header
            class="border-b-4 bg-white dark:bg-neutral-900"
            :style="{ borderBottomColor: accent }"
        >
            <div class="mx-auto max-w-5xl px-4 pt-4">
                <Link
                    href="/"
                    class="inline-flex items-center gap-1 text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    <ArrowLeft class="size-4" />
                    Back
                </Link>
            </div>
            <div class="mx-auto flex max-w-5xl items-center gap-3 px-4 pb-5 pt-3">
                <img
                    v-if="tenant.logo"
                    :src="tenant.logo"
                    :alt="`${tenant.name} logo`"
                    class="size-10 rounded-lg object-cover"
                />
                <div
                    v-else
                    class="flex size-10 items-center justify-center rounded-lg text-lg font-bold text-white"
                    :style="{ backgroundColor: accent }"
                    aria-hidden="true"
                >
                    {{ tenant.name.charAt(0) }}
                </div>
                <div>
                    <h1 class="text-lg font-semibold">{{ tenant.name }}</h1>
                    <p class="text-sm text-muted-foreground">
                        Tap open slots to build your booking — one or many courts.
                    </p>
                </div>
                <Link
                    :href="`/${tenant.slug}/cancel`"
                    class="ml-auto text-sm text-muted-foreground transition-colors hover:text-foreground"
                >
                    Cancel a booking
                </Link>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-6">
            <nav aria-label="Choose a date" class="mb-6">
                <div class="flex gap-2 overflow-x-auto pb-2">
                    <button
                        v-for="option in dates"
                        :key="option.value"
                        type="button"
                        class="flex min-w-14 flex-col items-center rounded-xl border px-3 py-2 text-sm transition-colors"
                        :class="
                            option.value === date
                                ? 'text-white'
                                : 'bg-white hover:border-neutral-400 dark:bg-neutral-900'
                        "
                        :style="option.value === date ? { backgroundColor: accent, borderColor: accent } : {}"
                        :aria-pressed="option.value === date"
                        @click="pickDate(option.value)"
                    >
                        <span class="text-xs opacity-80">{{ option.weekday }}</span>
                        <span class="text-base font-semibold">{{ option.day }}</span>
                    </button>
                </div>
            </nav>

            <p
                v-if="!courts.length"
                class="rounded-xl border border-dashed p-10 text-center text-muted-foreground"
            >
                No courts are open for booking yet — check back soon.
            </p>

            <!-- Time × court grid -->
            <div v-else class="overflow-x-auto rounded-xl border bg-white dark:bg-neutral-900">
                <table class="w-full border-collapse text-center text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="sticky left-0 z-10 bg-white px-3 py-3 text-left font-medium dark:bg-neutral-900">
                                Time
                            </th>
                            <th
                                v-for="court in courts"
                                :key="court.id"
                                class="px-2 py-3 font-medium whitespace-nowrap"
                            >
                                {{ court.name }}
                                <span class="block text-xs font-normal text-muted-foreground">
                                    {{ formatMoney(court.price) }}
                                </span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in rows"
                            :key="row.startsAt"
                            class="border-b last:border-0"
                        >
                            <th
                                scope="row"
                                class="sticky left-0 z-10 bg-white px-3 py-2 text-left font-normal whitespace-nowrap dark:bg-neutral-900"
                            >
                                {{ row.label }}
                            </th>
                            <td v-for="cell in row.cells" :key="cellKey(cell.court.id, row.startsAt)" class="p-1.5">
                                <button
                                    v-if="cell.slot && cell.slot.available"
                                    type="button"
                                    class="w-full rounded-lg border px-2 py-2 text-xs font-medium transition-all"
                                    :class="
                                        isSelected(cell.court.id, row.startsAt)
                                            ? 'text-white'
                                            : 'hover:-translate-y-0.5 hover:shadow-sm'
                                    "
                                    :style="
                                        isSelected(cell.court.id, row.startsAt)
                                            ? { backgroundColor: accent, borderColor: accent }
                                            : { borderColor: accent, color: accent }
                                    "
                                    :aria-pressed="isSelected(cell.court.id, row.startsAt)"
                                    @click="toggle(cell.court, cell.slot)"
                                >
                                    {{ isSelected(cell.court.id, row.startsAt) ? 'Selected' : 'Open' }}
                                </button>
                                <button
                                    v-else-if="cell.slot"
                                    type="button"
                                    class="block w-full rounded-lg bg-neutral-100 px-2 py-2 text-xs text-neutral-400 transition-colors hover:text-neutral-600 dark:bg-neutral-800 dark:text-neutral-600 dark:hover:text-neutral-300"
                                    @click="openWaitlist(cell.court, row.startsAt, row.label)"
                                >
                                    Taken
                                </button>
                                <span v-else class="block px-2 py-2 text-xs text-neutral-300 dark:text-neutral-700">
                                    —
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </main>

        <!-- Sticky cart bar -->
        <Transition
            enter-active-class="transition duration-200"
            enter-from-class="translate-y-full"
            leave-active-class="transition duration-200"
            leave-to-class="translate-y-full"
        >
            <div
                v-if="selected.length"
                class="fixed inset-x-0 bottom-0 z-20 border-t bg-white/95 backdrop-blur dark:bg-neutral-900/95"
            >
                <div class="mx-auto flex max-w-5xl items-center justify-between gap-4 px-4 py-3">
                    <div>
                        <p class="font-semibold">
                            {{ selected.length }}
                            {{ selected.length === 1 ? 'slot' : 'slots' }} ·
                            {{ formatMoney(payNow) }}
                            <span v-if="hasDeposit" class="text-sm font-normal text-muted-foreground">
                                now
                            </span>
                        </p>
                        <p v-if="hasDeposit" class="text-xs text-muted-foreground">
                            {{ formatMoney(onSite) }} on-site · {{ formatMoney(totalPrice) }} total
                        </p>
                    </div>
                    <Button class="text-white" :style="{ backgroundColor: accent }" @click="openCheckout">
                        Review &amp; pay
                        <ArrowRight class="size-4" />
                    </Button>
                </div>
            </div>
        </Transition>

        <!-- Checkout dialog -->
        <Dialog v-model:open="checkoutOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Review &amp; pay</DialogTitle>
                    <DialogDescription>
                        {{ selected.length }}
                        {{ selected.length === 1 ? 'court' : 'courts' }} selected.
                    </DialogDescription>
                </DialogHeader>

                <ul class="max-h-40 space-y-1 overflow-y-auto text-sm">
                    <li
                        v-for="item in selected"
                        :key="cellKey(item.courtId, item.startsAt)"
                        class="flex items-center justify-between gap-2 rounded-lg bg-neutral-50 px-3 py-2 dark:bg-neutral-950"
                    >
                        <span>{{ item.courtName }} · {{ item.label }}</span>
                        <span class="flex items-center gap-2">
                            <span class="tabular-nums">{{ formatMoney(item.price) }}</span>
                            <button
                                type="button"
                                class="text-muted-foreground hover:text-destructive"
                                :aria-label="`Remove ${item.courtName} ${item.label}`"
                                @click="remove(item)"
                            >
                                <X class="size-4" />
                            </button>
                        </span>
                    </li>
                </ul>
                <InputError :message="form.errors.slots" />

                <form class="grid gap-4" @submit.prevent="pay">
                    <div class="grid gap-2">
                        <Label for="guest-name">Your name</Label>
                        <Input id="guest-name" v-model="form.guest_name" autocomplete="name" required />
                        <InputError :message="form.errors.guest_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="guest-email">Email</Label>
                        <Input id="guest-email" v-model="form.guest_email" type="email" autocomplete="email" required />
                        <InputError :message="form.errors.guest_email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="party-size">
                            How many players? <span class="text-muted-foreground">(optional)</span>
                        </Label>
                        <Input
                            id="party-size"
                            v-model.number="form.party_size"
                            type="number"
                            min="1"
                            inputmode="numeric"
                            placeholder="e.g. 4"
                        />
                        <p
                            v-if="suggestedCourts > selected.length"
                            class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:bg-amber-950 dark:text-amber-200"
                        >
                            For {{ form.party_size }} players we'd suggest about
                            <strong>{{ suggestedCourts }} courts</strong>. You've
                            picked {{ selected.length }} — add more from the grid
                            if you'd like.
                        </p>
                        <InputError :message="form.errors.party_size" />
                    </div>

                    <div class="rounded-lg border bg-neutral-50 p-3 text-sm dark:bg-neutral-950">
                        <div class="flex justify-between">
                            <span class="text-muted-foreground">Pay now</span>
                            <span class="font-semibold tabular-nums">{{ formatMoney(payNow) }}</span>
                        </div>
                        <div v-if="hasDeposit" class="flex justify-between">
                            <span class="text-muted-foreground">Pay on-site</span>
                            <span class="font-medium tabular-nums">{{ formatMoney(onSite) }}</span>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="submit"
                            class="w-full text-white"
                            :style="{ backgroundColor: accent }"
                            :disabled="form.processing"
                        >
                            {{ form.processing ? 'One moment…' : `Pay ${formatMoney(payNow)}` }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Waitlist dialog for taken slots -->
        <Dialog
            :open="waitlistTarget !== null"
            @update:open="(open: boolean) => !open && (waitlistTarget = null)"
        >
            <DialogContent class="sm:max-w-sm">
                <template v-if="waitlistTarget">
                    <DialogHeader>
                        <DialogTitle>Join the waitlist</DialogTitle>
                        <DialogDescription>
                            {{ waitlistTarget.court.name }} · {{ waitlistTarget.label }}
                            is taken. We'll email you the moment it opens up.
                        </DialogDescription>
                    </DialogHeader>

                    <div
                        v-if="waitlistJoined"
                        class="rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200"
                        role="status"
                    >
                        You're on the waitlist! Keep an eye on your inbox.
                    </div>

                    <form v-else class="grid gap-4" @submit.prevent="joinWaitlist">
                        <div class="grid gap-2">
                            <Label for="wl-name">Your name</Label>
                            <Input id="wl-name" v-model="waitlistForm.guest_name" autocomplete="name" required />
                            <InputError :message="waitlistForm.errors.guest_name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="wl-email">Email</Label>
                            <Input id="wl-email" v-model="waitlistForm.guest_email" type="email" autocomplete="email" required />
                            <InputError :message="waitlistForm.errors.guest_email" />
                        </div>
                        <DialogFooter>
                            <Button
                                type="submit"
                                class="w-full text-white"
                                :style="{ backgroundColor: accent }"
                                :disabled="waitlistForm.processing"
                            >
                                Join waitlist
                            </Button>
                        </DialogFooter>
                    </form>
                </template>
            </DialogContent>
        </Dialog>
    </div>
</template>
