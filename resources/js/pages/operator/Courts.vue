<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Clock, Moon, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
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
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatMoney } from '@/lib/money';
import { destroy, index, store, update } from '@/routes/courts';

interface Court {
    id: number;
    name: string;
    price: number;
    deposit: number | null;
    opens_at: string;
    closes_at: string;
    slot_minutes: number;
    buffer_minutes: number;
    booking_window_days: number;
    capacity: number;
    upcoming_bookings_count: number;
}

const props = defineProps<{
    courts: Court[];
    courtLimit: number | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Courts', href: index() }],
    },
});

const dialogOpen = ref(false);
const editing = ref<Court | null>(null);

const form = useForm({
    name: '',
    price: 500,
    deposit: 0,
    opens_at: '06:00',
    closes_at: '22:00',
    slot_minutes: 60,
    buffer_minutes: 0,
    booking_window_days: 30,
    capacity: 4,
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    dialogOpen.value = true;
}

function openEdit(court: Court) {
    editing.value = court;
    form.clearErrors();
    form.name = court.name;
    form.price = court.price;
    form.deposit = court.deposit ?? 0;
    form.opens_at = court.opens_at;
    form.closes_at = court.closes_at;
    form.slot_minutes = court.slot_minutes;
    form.buffer_minutes = court.buffer_minutes;
    form.booking_window_days = court.booking_window_days;
    form.capacity = court.capacity;
    dialogOpen.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            dialogOpen.value = false;
        },
    };

    if (editing.value) {
        form.put(update(editing.value.id).url, options);
    } else {
        form.post(store().url, options);
    }
}

function archive(court: Court) {
    if (
        confirm(
            `Archive ${court.name}? It will disappear from the booking portal. Existing bookings are kept.`,
        )
    ) {
        form.delete(destroy(court.id).url, { preserveScroll: true });
    }
}

const atLimit = () =>
    props.courtLimit !== null && props.courts.length >= props.courtLimit;

// A closing time at or before the opening time means the court runs past
// midnight and closes the following day.
const isOvernight = computed(
    () =>
        !!form.opens_at &&
        !!form.closes_at &&
        form.closes_at <= form.opens_at,
);
</script>

<template>
    <Head title="Courts" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <header class="flex items-center justify-between">
            <div>
                <h1 class="text-xl font-semibold">Courts</h1>
                <p class="text-sm text-muted-foreground">
                    {{ courts.length }}
                    <template v-if="courtLimit !== null">
                        of {{ courtLimit }}</template
                    >
                    courts on your plan
                </p>
            </div>
            <Button :disabled="atLimit()" @click="openCreate">
                <Plus class="size-4" />
                Add court
            </Button>
        </header>

        <p
            v-if="atLimit()"
            class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-200"
        >
            You've reached your plan's court limit. Upgrade to add more courts.
        </p>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Card v-for="court in courts" :key="court.id">
                <CardHeader>
                    <div class="flex items-start justify-between">
                        <div>
                            <CardTitle>{{ court.name }}</CardTitle>
                            <CardDescription class="mt-1 flex items-center gap-1">
                                <Clock class="size-3.5" />
                                {{ court.opens_at }}–{{ court.closes_at }} ·
                                {{ court.slot_minutes }} min slots
                                <template v-if="court.buffer_minutes > 0">
                                    + {{ court.buffer_minutes }} min buffer
                                </template>
                            </CardDescription>
                        </div>
                        <div class="flex flex-col items-end gap-1">
                            <Badge variant="secondary" class="tabular-nums">
                                {{ formatMoney(court.price) }}/slot
                            </Badge>
                            <span
                                v-if="court.deposit"
                                class="text-xs text-muted-foreground tabular-nums"
                            >
                                {{ formatMoney(court.deposit) }} deposit
                            </span>
                        </div>
                    </div>
                </CardHeader>
                <CardContent class="flex items-center justify-between">
                    <p class="text-sm text-muted-foreground">
                        {{ court.upcoming_bookings_count }} upcoming
                        {{ court.upcoming_bookings_count === 1 ? 'booking' : 'bookings' }}
                    </p>
                    <div class="flex gap-1">
                        <Button
                            variant="ghost"
                            size="icon"
                            :aria-label="`Edit ${court.name}`"
                            @click="openEdit(court)"
                        >
                            <Pencil class="size-4" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            :aria-label="`Archive ${court.name}`"
                            @click="archive(court)"
                        >
                            <Trash2 class="size-4 text-destructive" />
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <button
                v-if="!courts.length"
                type="button"
                class="flex min-h-40 flex-col items-center justify-center gap-2 rounded-xl border border-dashed text-muted-foreground transition-colors hover:border-primary hover:text-primary"
                @click="openCreate"
            >
                <Plus class="size-6" />
                Create your first court
            </button>
        </div>

        <Dialog v-model:open="dialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {{ editing ? `Edit ${editing.name}` : 'Add court' }}
                    </DialogTitle>
                    <DialogDescription>
                        Operating hours and slot settings drive the public
                        booking grid.
                    </DialogDescription>
                </DialogHeader>

                <form class="grid gap-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="court-name">Name</Label>
                        <Input
                            id="court-name"
                            v-model="form.name"
                            placeholder="Court 1"
                            required
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="court-price">Price per slot (₱)</Label>
                            <Input
                                id="court-price"
                                v-model.number="form.price"
                                type="number"
                                min="0"
                                step="50"
                                required
                            />
                            <InputError :message="form.errors.price" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="court-slot">Slot duration</Label>
                            <Select
                                :model-value="String(form.slot_minutes)"
                                @update:model-value="form.slot_minutes = Number($event)"
                            >
                                <SelectTrigger id="court-slot">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="30">30 minutes</SelectItem>
                                    <SelectItem value="60">60 minutes</SelectItem>
                                    <SelectItem value="90">90 minutes</SelectItem>
                                    <SelectItem value="120">120 minutes</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.slot_minutes" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="court-deposit">Online deposit (₱)</Label>
                        <Input
                            id="court-deposit"
                            v-model.number="form.deposit"
                            type="number"
                            min="0"
                            step="50"
                        />
                        <p class="text-xs text-muted-foreground">
                            Leave at 0 to require full payment online. A deposit
                            below the price lets players pay
                            {{ form.deposit > 0 && form.deposit < form.price
                                ? formatMoney(form.price - form.deposit)
                                : 'the balance' }}
                            on-site.
                        </p>
                        <InputError :message="form.errors.deposit" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="court-opens">Opens</Label>
                            <Input
                                id="court-opens"
                                v-model="form.opens_at"
                                type="time"
                                required
                            />
                            <InputError :message="form.errors.opens_at" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="court-closes">Closes</Label>
                            <Input
                                id="court-closes"
                                v-model="form.closes_at"
                                type="time"
                                required
                            />
                            <InputError :message="form.errors.closes_at" />
                        </div>
                    </div>

                    <p
                        v-if="isOvernight"
                        class="flex items-center gap-1.5 -mt-1 text-xs text-muted-foreground"
                    >
                        <Moon class="size-3.5 shrink-0" />
                        Runs past midnight — this court closes at
                        {{ form.closes_at }} the next day.
                    </p>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="court-buffer">Buffer (minutes)</Label>
                            <Input
                                id="court-buffer"
                                v-model.number="form.buffer_minutes"
                                type="number"
                                min="0"
                                max="120"
                                step="5"
                            />
                            <InputError :message="form.errors.buffer_minutes" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="court-window">Booking window (days)</Label>
                            <Input
                                id="court-window"
                                v-model.number="form.booking_window_days"
                                type="number"
                                min="1"
                                max="365"
                            />
                            <InputError :message="form.errors.booking_window_days" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="court-capacity">Players per court</Label>
                        <Input
                            id="court-capacity"
                            v-model.number="form.capacity"
                            type="number"
                            min="1"
                            max="50"
                        />
                        <p class="text-xs text-muted-foreground">
                            Used to suggest how many courts a large group should
                            book. Pickleball doubles is 4.
                        </p>
                        <InputError :message="form.errors.capacity" />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="dialogOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ editing ? 'Save changes' : 'Create court' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
