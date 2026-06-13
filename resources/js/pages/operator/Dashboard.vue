<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CalendarX2, RotateCcw, TrendingUp } from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatMoney } from '@/lib/money';
import { dashboard } from '@/routes';

interface PeakHour {
    hour: string;
    bookings: number;
}

interface Metrics {
    revenue: { today: number; week: number; month: number };
    utilization: {
        rate: number;
        booked_hours: number;
        available_hours: number;
        peak_hours: PeakHour[];
    };
    insights: {
        total_bookings: number;
        cancellation_rate: number;
        recovery_rate: number;
    };
}

const props = defineProps<{
    metrics: Metrics;
    currency: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

const money = (centavos: number) => formatMoney(centavos / 100, props.currency);
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <section aria-labelledby="revenue-heading">
            <h2
                id="revenue-heading"
                class="mb-3 text-sm font-medium tracking-wide text-muted-foreground uppercase"
            >
                Revenue
            </h2>
            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardDescription>Today</CardDescription>
                        <CardTitle class="text-3xl tabular-nums">
                            {{ money(metrics.revenue.today) }}
                        </CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader>
                        <CardDescription>This week</CardDescription>
                        <CardTitle class="text-3xl tabular-nums">
                            {{ money(metrics.revenue.week) }}
                        </CardTitle>
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader>
                        <CardDescription>This month</CardDescription>
                        <CardTitle class="text-3xl tabular-nums">
                            {{ money(metrics.revenue.month) }}
                        </CardTitle>
                    </CardHeader>
                </Card>
            </div>
        </section>

        <section aria-labelledby="utilization-heading">
            <h2
                id="utilization-heading"
                class="mb-3 text-sm font-medium tracking-wide text-muted-foreground uppercase"
            >
                Utilization · last 30 days
            </h2>
            <div class="grid gap-4 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardDescription>Court hours sold</CardDescription>
                        <CardTitle class="text-4xl tabular-nums">
                            {{ metrics.utilization.rate }}%
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div
                            class="h-2 w-full overflow-hidden rounded-full bg-muted"
                            role="progressbar"
                            :aria-valuenow="metrics.utilization.rate"
                            aria-valuemin="0"
                            aria-valuemax="100"
                        >
                            <div
                                class="h-full rounded-full bg-primary transition-all"
                                :style="{ width: `${Math.min(metrics.utilization.rate, 100)}%` }"
                            />
                        </div>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{ metrics.utilization.booked_hours }} of
                            {{ metrics.utilization.available_hours }} available
                            hours booked
                        </p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader>
                        <CardDescription>Peak hours</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div
                            v-if="metrics.utilization.peak_hours.length"
                            class="flex flex-wrap gap-2"
                        >
                            <Badge
                                v-for="peak in metrics.utilization.peak_hours"
                                :key="peak.hour"
                                variant="secondary"
                                class="px-3 py-1 text-sm"
                            >
                                {{ peak.hour }} · {{ peak.bookings }} bookings
                            </Badge>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">
                            No bookings yet — peak hours will appear once courts
                            start selling.
                        </p>
                    </CardContent>
                </Card>
            </div>
        </section>

        <section aria-labelledby="insights-heading">
            <h2
                id="insights-heading"
                class="mb-3 text-sm font-medium tracking-wide text-muted-foreground uppercase"
            >
                Booking insights · last 30 days
            </h2>
            <div class="grid gap-4 md:grid-cols-3">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between">
                        <div>
                            <CardDescription>Total bookings</CardDescription>
                            <CardTitle class="text-3xl tabular-nums">
                                {{ metrics.insights.total_bookings }}
                            </CardTitle>
                        </div>
                        <TrendingUp class="size-5 text-muted-foreground" />
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between">
                        <div>
                            <CardDescription>Cancellation rate</CardDescription>
                            <CardTitle class="text-3xl tabular-nums">
                                {{ metrics.insights.cancellation_rate }}%
                            </CardTitle>
                        </div>
                        <CalendarX2 class="size-5 text-muted-foreground" />
                    </CardHeader>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between">
                        <div>
                            <CardDescription>Waitlist recovery</CardDescription>
                            <CardTitle class="text-3xl tabular-nums">
                                {{ metrics.insights.recovery_rate }}%
                            </CardTitle>
                        </div>
                        <RotateCcw class="size-5 text-muted-foreground" />
                    </CardHeader>
                </Card>
            </div>
        </section>
    </div>
</template>
