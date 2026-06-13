<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatMoney } from '@/lib/money';
import { cancel, index } from '@/routes/bookings';

interface BookingRow {
    id: number;
    reference: string;
    court: string;
    player_name: string;
    player_email: string | null;
    party_size: number | null;
    starts_at: string;
    status: string;
    amount: number;
    balance_due: number;
    cancellable: boolean;
}

interface Paginated<T> {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
}

defineProps<{
    bookings: Paginated<BookingRow>;
    status: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Bookings', href: index() }],
    },
});

const statusVariants: Record<string, string> = {
    confirmed: 'bg-emerald-100 text-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
    pending_payment: 'bg-amber-100 text-amber-900 dark:bg-amber-950 dark:text-amber-200',
    completed: 'bg-sky-100 text-sky-900 dark:bg-sky-950 dark:text-sky-200',
    cancelled: 'bg-rose-100 text-rose-900 dark:bg-rose-950 dark:text-rose-200',
};

function filterStatus(value: unknown) {
    const status = typeof value === 'string' ? value : '';

    router.get(
        index().url,
        status && status !== 'all' ? { status } : {},
        { preserveState: true, preserveScroll: true },
    );
}

function cancelBooking(booking: BookingRow) {
    if (
        confirm(
            `Cancel ${booking.court} for ${booking.player_name} (${booking.starts_at})? The waitlist will be offered this slot.`,
        )
    ) {
        router.post(cancel(booking.id).url, {}, { preserveScroll: true });
    }
}
</script>

<template>
    <Head title="Bookings" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <header class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">Bookings</h1>
                <p class="text-sm text-muted-foreground">
                    {{ bookings.total }} total
                </p>
            </div>
            <Select :model-value="status || 'all'" @update:model-value="filterStatus">
                <SelectTrigger class="w-44" aria-label="Filter by status">
                    <SelectValue placeholder="All statuses" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All statuses</SelectItem>
                    <SelectItem value="confirmed">Confirmed</SelectItem>
                    <SelectItem value="pending_payment">Pending payment</SelectItem>
                    <SelectItem value="completed">Completed</SelectItem>
                    <SelectItem value="cancelled">Cancelled</SelectItem>
                    <SelectItem value="expired">Expired</SelectItem>
                </SelectContent>
            </Select>
        </header>

        <div class="overflow-hidden rounded-xl border">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b bg-muted/50 text-left">
                        <th class="px-4 py-3 font-medium">When</th>
                        <th class="px-4 py-3 font-medium">Court</th>
                        <th class="px-4 py-3 font-medium">Player</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 text-right font-medium">Amount</th>
                        <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="booking in bookings.data"
                        :key="booking.id"
                        class="border-b transition-colors last:border-0 hover:bg-muted/30"
                    >
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ booking.starts_at }}
                        </td>
                        <td class="px-4 py-3">{{ booking.court }}</td>
                        <td class="px-4 py-3">
                            <div>
                                {{ booking.player_name }}
                                <span v-if="booking.party_size" class="text-muted-foreground">
                                    · {{ booking.party_size }} players
                                </span>
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ booking.player_email }}
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                variant="outline"
                                :class="statusVariants[booking.status] ?? ''"
                            >
                                {{ booking.status.replace('_', ' ') }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ formatMoney(booking.amount) }}
                            <div
                                v-if="booking.balance_due > 0"
                                class="text-xs font-normal text-amber-700 dark:text-amber-400"
                            >
                                {{ formatMoney(booking.balance_due) }} on-site
                            </div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Button
                                v-if="booking.cancellable"
                                variant="ghost"
                                size="sm"
                                class="text-destructive hover:text-destructive"
                                @click="cancelBooking(booking)"
                            >
                                Cancel
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="!bookings.data.length">
                        <td
                            colspan="6"
                            class="px-4 py-12 text-center text-muted-foreground"
                        >
                            No bookings match this filter yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav
            v-if="bookings.prev_page_url || bookings.next_page_url"
            class="flex justify-end gap-2"
            aria-label="Pagination"
        >
            <Button
                variant="outline"
                size="sm"
                :disabled="!bookings.prev_page_url"
                as-child
            >
                <Link v-if="bookings.prev_page_url" :href="bookings.prev_page_url" preserve-scroll>
                    Previous
                </Link>
                <span v-else>Previous</span>
            </Button>
            <Button
                variant="outline"
                size="sm"
                :disabled="!bookings.next_page_url"
                as-child
            >
                <Link v-if="bookings.next_page_url" :href="bookings.next_page_url" preserve-scroll>
                    Next
                </Link>
                <span v-else>Next</span>
            </Button>
        </nav>
    </div>
</template>
