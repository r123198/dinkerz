<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarCheck,
    ChevronDown,
    CreditCard,
    LandPlot,
    MapPin,
    RotateCcw,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';

interface Club {
    name: string;
    slug: string;
    color: string | null;
}

const props = defineProps<{
    clubs: Club[];
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const isOperator = computed(
    () => user.value?.role === 'operator' || user.value?.role === 'staff',
);

const gymCount = computed(() => props.clubs.length);

function scrollToBook() {
    document
        .getElementById('book')
        ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

const highlights = [
    {
        icon: CalendarCheck,
        title: 'Real-time availability',
        text: 'Open slots update live as players book. What you see is what you get — no double bookings, no waiting for a reply.',
    },
    {
        icon: CreditCard,
        title: 'Pay your way',
        text: 'Check out with GCash, Maya, or any credit or debit card. Your court is locked the moment payment clears.',
    },
    {
        icon: RotateCcw,
        title: 'Never miss out',
        text: 'Slot already taken? Join the waitlist. If someone cancels, everyone waiting is notified instantly — first to pay gets the court.',
    },
];

const faqs = [
    {
        q: 'How do I book a court?',
        a: 'Pick a gym, choose your date and an open time slot, enter your name and email, then pay. Your confirmation arrives right after checkout.',
    },
    {
        q: 'Do I need an account to book?',
        a: 'No. You can book as a guest with just your name and email. Everything you need to play arrives by email.',
    },
    {
        q: 'How do I pay for my session?',
        a: 'Checkout supports GCash, Maya, and credit or debit cards. Your booking is only confirmed once the payment is verified — so a court is never sold twice.',
    },
    {
        q: 'What if my preferred time is already booked?',
        a: 'Join the waitlist for that slot. The moment someone cancels, everyone on the waitlist is emailed a booking link — the first to pay claims the court.',
    },
    {
        q: 'Will I get a reminder before my session?',
        a: 'Yes. We email a confirmation when you book and send a reminder before your session starts, so you never miss your court time.',
    },
    {
        q: 'How long is each session?',
        a: 'Session length is set by each gym — usually between 30 and 120 minutes — and is shown on every time slot before you book.',
    },
];
</script>

<template>
    <Head title="CourtOS — book your pickleball court" />

    <div
        class="min-h-screen scroll-smooth bg-neutral-50 text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100"
    >
        <header
            class="sticky top-0 z-30 border-b border-neutral-200/70 bg-neutral-50/80 backdrop-blur dark:border-neutral-800/70 dark:bg-neutral-950/80"
        >
            <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
                <div class="flex items-center gap-2">
                    <AppLogoIcon class="size-8" />
                    <span class="text-lg font-semibold tracking-tight">CourtOS</span>
                </div>
                <nav class="flex items-center gap-2">
                    <template v-if="user">
                        <Button v-if="isOperator" as-child>
                            <Link :href="dashboard()">
                                Open dashboard
                                <ArrowRight class="size-4" />
                            </Link>
                        </Button>
                        <Button v-else @click="scrollToBook">
                            Book a session
                        </Button>
                    </template>
                    <template v-else>
                        <Button variant="ghost" as-child class="hidden sm:inline-flex">
                            <Link :href="login()">Operator login</Link>
                        </Button>
                        <Button @click="scrollToBook">Book a session</Button>
                    </template>
                </nav>
            </div>
        </header>

        <main>
            <!-- 1. HERO -->
            <section class="relative overflow-hidden">
                <div
                    class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top,theme(colors.emerald.500/0.12),transparent_60%)]"
                    aria-hidden="true"
                />
                <div class="mx-auto max-w-3xl px-6 py-24 text-center lg:py-32">
                    <span
                        class="inline-flex items-center gap-2 rounded-full border border-emerald-600/30 bg-emerald-50 px-4 py-1.5 text-sm font-medium text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300"
                    >
                        <span class="relative flex size-2">
                            <span
                                class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-500 opacity-75"
                            />
                            <span class="relative inline-flex size-2 rounded-full bg-emerald-500" />
                        </span>
                        {{ gymCount }}
                        {{ gymCount === 1 ? 'gym is' : 'gyms are' }} taking bookings now
                    </span>

                    <h1
                        class="mt-6 text-4xl font-bold tracking-tight text-balance sm:text-5xl lg:text-7xl"
                    >
                        Find a court.
                        <span class="text-emerald-600 dark:text-emerald-400">Book your game.</span>
                        Play today.
                    </h1>

                    <p class="mx-auto mt-6 max-w-xl text-lg text-muted-foreground">
                        Reserve pickleball court time at partner gyms in
                        seconds. Browse live availability, lock your slot, and
                        pay with GCash, Maya, or card — no group chats, no
                        waiting.
                    </p>

                    <div class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
                        <Button size="lg" class="w-full sm:w-auto" @click="scrollToBook">
                            Book a session
                            <ArrowRight class="size-4" />
                        </Button>
                        <Button
                            size="lg"
                            variant="outline"
                            class="w-full sm:w-auto"
                            as-child
                        >
                            <a href="#faq">How it works</a>
                        </Button>
                    </div>
                </div>
            </section>

            <!-- 2. PICKLE GYMS FOR BOOKING -->
            <section
                id="book"
                aria-labelledby="book-heading"
                class="mx-auto max-w-5xl scroll-mt-20 px-6 py-16"
            >
                <div class="mb-8 text-center">
                    <h2 id="book-heading" class="text-3xl font-bold tracking-tight">
                        Pickleball gyms
                    </h2>
                    <p class="mt-2 text-muted-foreground">
                        Choose a gym to see today's open courts and book your slot.
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <Link
                        v-for="club in clubs"
                        :key="club.slug"
                        :href="`/${club.slug}`"
                        class="group flex items-center justify-between rounded-2xl border bg-white p-5 transition-all hover:-translate-y-0.5 hover:border-emerald-500/50 hover:shadow-md dark:bg-neutral-900"
                    >
                        <div class="flex items-center gap-4">
                            <div
                                class="flex size-12 items-center justify-center rounded-xl text-lg font-bold text-white"
                                :style="{ backgroundColor: club.color ?? '#16a34a' }"
                                aria-hidden="true"
                            >
                                {{ club.name.charAt(0) }}
                            </div>
                            <div>
                                <p class="font-semibold">{{ club.name }}</p>
                                <p class="flex items-center gap-1 text-sm text-muted-foreground">
                                    <MapPin class="size-3.5" />
                                    View courts &amp; times
                                </p>
                            </div>
                        </div>
                        <ArrowRight
                            class="size-5 text-muted-foreground transition-transform group-hover:translate-x-1 group-hover:text-emerald-600"
                        />
                    </Link>

                    <p
                        v-if="!clubs.length"
                        class="col-span-full rounded-2xl border border-dashed p-10 text-center text-muted-foreground"
                    >
                        No gyms are live yet. Run
                        <code class="rounded bg-muted px-1.5 py-0.5">php artisan db:seed</code>
                        to create the demo gym.
                    </p>
                </div>
            </section>

            <!-- 3. ABOUT -->
            <section
                id="about"
                aria-labelledby="about-heading"
                class="border-y border-neutral-200/70 bg-white py-20 dark:border-neutral-800/70 dark:bg-neutral-900/40"
            >
                <div class="mx-auto max-w-5xl px-6">
                    <div class="mx-auto max-w-2xl text-center">
                        <h2 id="about-heading" class="text-3xl font-bold tracking-tight">
                            Court time, without the back-and-forth
                        </h2>
                        <p class="mt-4 text-lg text-muted-foreground">
                            CourtOS is how pickleball players find and book court
                            time. Partner gyms put their real availability online,
                            so you reserve and pay in under a minute instead of
                            chasing replies in a group chat.
                        </p>
                    </div>

                    <div class="mt-12 grid gap-8 md:grid-cols-3">
                        <article v-for="item in highlights" :key="item.title">
                            <div
                                class="mb-4 flex size-11 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                            >
                                <component :is="item.icon" class="size-5" aria-hidden="true" />
                            </div>
                            <h3 class="font-semibold">{{ item.title }}</h3>
                            <p class="mt-1.5 text-sm leading-relaxed text-muted-foreground">
                                {{ item.text }}
                            </p>
                        </article>
                    </div>
                </div>
            </section>

            <!-- 4. FAQ -->
            <section
                id="faq"
                aria-labelledby="faq-heading"
                class="mx-auto max-w-3xl scroll-mt-20 px-6 py-20"
            >
                <div class="mb-10 text-center">
                    <h2 id="faq-heading" class="text-3xl font-bold tracking-tight">
                        Questions players ask
                    </h2>
                    <p class="mt-2 text-muted-foreground">
                        Everything you need to know before your first booking.
                    </p>
                </div>

                <div class="divide-y divide-neutral-200 rounded-2xl border bg-white dark:divide-neutral-800 dark:bg-neutral-900">
                    <details
                        v-for="faq in faqs"
                        :key="faq.q"
                        class="group px-5"
                    >
                        <summary
                            class="flex cursor-pointer list-none items-center justify-between py-4 font-medium [&::-webkit-details-marker]:hidden"
                        >
                            {{ faq.q }}
                            <ChevronDown
                                class="size-5 shrink-0 text-muted-foreground transition-transform group-open:rotate-180"
                                aria-hidden="true"
                            />
                        </summary>
                        <p class="pb-4 text-sm leading-relaxed text-muted-foreground">
                            {{ faq.a }}
                        </p>
                    </details>
                </div>
            </section>

            <!-- 5. BIG CTA -->
            <section class="px-6 pb-20">
                <div
                    class="mx-auto max-w-5xl overflow-hidden rounded-3xl bg-emerald-600 px-8 py-16 text-center text-white sm:py-20"
                >
                    <h2 class="text-3xl font-bold tracking-tight text-balance sm:text-4xl">
                        Ready to play?
                    </h2>
                    <p class="mx-auto mt-3 max-w-md text-emerald-50">
                        Pick a gym, grab an open slot, and lock in your court
                        time now.
                    </p>
                    <Button
                        size="lg"
                        variant="secondary"
                        class="mt-8 bg-white text-emerald-700 hover:bg-emerald-50"
                        @click="scrollToBook"
                    >
                        Book a session
                        <ArrowRight class="size-4" />
                    </Button>
                </div>
            </section>
        </main>

        <!-- 6. FOOTER -->
        <footer class="border-t border-neutral-200/70 dark:border-neutral-800/70">
            <div class="mx-auto grid max-w-5xl gap-8 px-6 py-12 sm:grid-cols-2 lg:grid-cols-4">
                <div class="sm:col-span-2 lg:col-span-2">
                    <div class="flex items-center gap-2">
                        <AppLogoIcon class="size-7" />
                        <span class="font-semibold tracking-tight">CourtOS</span>
                    </div>
                    <p class="mt-3 max-w-xs text-sm text-muted-foreground">
                        Book pickleball court time at partner gyms — real
                        availability, instant confirmation, secure payment.
                    </p>
                </div>

                <div>
                    <h3 class="text-sm font-semibold">For players</h3>
                    <ul class="mt-3 space-y-2 text-sm text-muted-foreground">
                        <li>
                            <button class="transition-colors hover:text-foreground" @click="scrollToBook">
                                Book a session
                            </button>
                        </li>
                        <li><a href="#faq" class="transition-colors hover:text-foreground">FAQs</a></li>
                        <li><a href="#about" class="transition-colors hover:text-foreground">About</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="text-sm font-semibold">For operators</h3>
                    <ul class="mt-3 space-y-2 text-sm text-muted-foreground">
                        <li>
                            <Link :href="login()" class="transition-colors hover:text-foreground">
                                Operator login
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="border-t border-neutral-200/70 py-6 dark:border-neutral-800/70">
                <p class="mx-auto flex max-w-5xl items-center gap-1.5 px-6 text-sm text-muted-foreground">
                    <LandPlot class="size-4" />
                    CourtOS — white-label pickleball court booking
                </p>
            </div>
        </footer>
    </div>
</template>
