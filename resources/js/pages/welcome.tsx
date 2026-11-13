import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Bell,
    ChartColumn,
    Trash2,
    EyeOff,
    Lock,
    Radio,
    Receipt,
    Repeat,
    ShieldCheck,
    Sparkles,
    Store,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { LegalLinks } from '@/components/legal/legal-links';
import { AppPreview } from '@/components/marketing/app-preview';
import {
    Apple,
    Avocado,
    Bread,
    Carrot,
    Lemon,
    MilkCarton,
} from '@/components/marketing/produce';
import { cn } from '@/lib/utils';
import { dashboard, login, register } from '@/routes';
import legal from '@/routes/legal';

const primaryButton =
    'inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-m-green px-6 font-medium text-m-on-green transition-opacity hover:opacity-90 focus-visible:ring-2 focus-visible:ring-m-green focus-visible:ring-offset-2 focus-visible:ring-offset-m-bg focus-visible:outline-none';

const secondaryButton =
    'inline-flex min-h-12 items-center justify-center gap-2 rounded-full border border-m-ink/25 px-6 font-medium transition-colors hover:bg-m-ink/5 focus-visible:ring-2 focus-visible:ring-m-green focus-visible:outline-none';

const eyebrow = 'text-sm font-semibold tracking-wide text-m-accent uppercase';

const promises: { icon: LucideIcon; label: string }[] = [
    { icon: Radio, label: 'Live for everyone' },
    { icon: Lock, label: 'Personal lists stay private' },
    { icon: EyeOff, label: 'No ads or trackers' },
    { icon: Trash2, label: 'Delete your account anytime' },
];

const steps = [
    {
        title: 'Add it in seconds',
        body: 'Type “2 lb apples” and press Enter. :app reads the amount, picks a category, and remembers it for next time.',
    },
    {
        title: 'Shop together',
        body: 'Start a trip and check things off with big, easy tap targets. Everyone on the trip sees the cart fill up as it happens.',
    },
    {
        title: 'Keep what really happened',
        body: 'Finish the trip and what you bought goes into history. Whatever you missed stays on the list for next time.',
    },
];

const households = [
    {
        id: 'families',
        label: 'Families',
        title: 'One list on every phone',
        body: 'Anyone can add to the shared list, so the milk request reaches whoever is at the store. Purchase history stays visible to the whole household.',
    },
    {
        id: 'roommates',
        label: 'Roommates',
        title: 'Shared basics, separate lists',
        body: 'Keep toilet paper and dish soap on a household list, and your own items on a personal list nobody else can see.',
    },
    {
        id: 'caregivers',
        label: 'Helping a relative',
        title: 'Shop for more than one home',
        body: 'Belong to several households and switch between them. Or share a single list with someone, without giving them access to anything else.',
    },
    {
        id: 'solo',
        label: 'Just you',
        title: 'A list that learns your routine',
        body: 'Personal lists are private by default. As you complete trips, :app starts to notice what you buy and when you tend to run low.',
    },
];

const features: { icon: LucideIcon; title: string; body: string }[] = [
    {
        icon: Sparkles,
        title: 'Suggestions that explain themselves',
        body: 'Every suggestion says why it’s there, like “purchased on 5 of your last 7 trips”. Snooze or mute anything.',
    },
    {
        icon: Repeat,
        title: 'Staples and gentle reminders',
        body: 'Set a schedule for the things you never want to run out of, with quiet hours when you’d rather not hear about it.',
    },
    {
        icon: Store,
        title: 'Sorted the way your store is',
        body: 'Set a store’s aisle order once, and your list follows it, so you stop doubling back for the eggs.',
    },
    {
        icon: Bell,
        title: 'Updates, without the noise',
        body: 'Changes from others arrive as one tidy summary. Choose what reaches you in the app or by email.',
    },
    {
        icon: ChartColumn,
        title: 'See where the money goes',
        body: 'Spending by month, store, and category, with what you paid for each product over time. Add prices on a trip or after.',
    },
    {
        icon: Receipt,
        title: 'A record you can trust',
        body: 'History comes from finished trips, not guesses from an old list. Later list edits never rewrite it.',
    },
];

const privacyPoints = [
    'We never sell your personal information.',
    'No advertising cookies, analytics, or third-party trackers.',
    'Optional two-factor sign-in to keep your account yours.',
    'Delete your account yourself, without asking anyone.',
];

export default function Welcome() {
    const { auth, name, tagline, taglineLong, canRegister } = usePage().props;
    const [household, setHousehold] = useState(households[0]);
    const signedIn = Boolean(auth.user);
    // With sign-up closed for the demo site, the way in is the demo login on the login page.
    const primaryHref = signedIn
        ? dashboard()
        : canRegister
          ? register()
          : login();
    const primaryLabel = signedIn
        ? 'Go to your lists'
        : canRegister
          ? 'Create your first list'
          : 'Try the demo';

    return (
        <>
            <Head title="Shared shopping lists for your household" />
            <div className="marketing min-h-screen bg-m-bg font-sans text-m-ink">
                <a
                    href="#main"
                    className="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50 focus:rounded-md focus:bg-m-surface focus:px-3 focus:py-2"
                >
                    Skip to content
                </a>

                <header className="sticky top-0 z-40 border-b border-m-line/70 bg-m-bg/90 backdrop-blur">
                    <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-3 sm:px-6">
                        <Link
                            href="/"
                            className="flex items-center gap-2 font-display text-xl font-semibold"
                        >
                            <AppLogoIcon className="size-6 fill-current text-m-green" />
                            {name}
                        </Link>
                        <nav
                            aria-label="Page sections"
                            className="hidden items-center gap-6 text-sm md:flex"
                        >
                            <a href="#how" className="hover:underline">
                                How it works
                            </a>
                            <a href="#homes" className="hover:underline">
                                For your home
                            </a>
                            <a href="#features" className="hover:underline">
                                Features
                            </a>
                            <a href="#privacy" className="hover:underline">
                                Privacy
                            </a>
                        </nav>
                        <nav
                            aria-label="Account"
                            className="flex items-center gap-1"
                        >
                            {signedIn ? (
                                <Link
                                    href={dashboard()}
                                    className="inline-flex min-h-10 items-center rounded-full bg-m-green px-4 text-sm font-medium text-m-on-green hover:opacity-90"
                                >
                                    Dashboard
                                </Link>
                            ) : (
                                <>
                                    <Link
                                        href={login()}
                                        className="inline-flex min-h-10 items-center rounded-full px-3 text-sm font-medium hover:underline"
                                    >
                                        Log in
                                    </Link>
                                    {canRegister && (
                                        <Link
                                            href={register()}
                                            className="inline-flex min-h-10 items-center rounded-full bg-m-green px-4 text-sm font-medium text-m-on-green hover:opacity-90"
                                        >
                                            Sign up
                                        </Link>
                                    )}
                                </>
                            )}
                        </nav>
                    </div>
                </header>

                <main id="main">
                    <section className="relative overflow-hidden">
                        <div className="mx-auto grid max-w-6xl items-center gap-14 px-4 pt-14 pb-20 sm:px-6 lg:grid-cols-2 lg:pt-20 lg:pb-28">
                            <div>
                                <p className="inline-flex items-center gap-2 rounded-full bg-m-band px-3 py-1.5 text-sm font-medium">
                                    <Users className="size-4 text-m-green" />
                                    Made for households that share the shopping
                                </p>
                                <h1 className="mt-5 font-display text-5xl leading-[1.05] font-semibold tracking-tight sm:text-6xl">
                                    {tagline}
                                </h1>
                                <p className="mt-5 max-w-lg font-display text-2xl font-semibold">
                                    {taglineLong}
                                </p>
                                <p className="mt-3 max-w-lg text-lg text-m-muted">
                                    One shopping list the whole home can add to,
                                    that updates live in the store and learns
                                    what you buy, without ever selling your
                                    data.
                                </p>
                                <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                                    <Link
                                        href={primaryHref}
                                        className={primaryButton}
                                    >
                                        {primaryLabel}
                                        <ArrowRight className="size-4" />
                                    </Link>
                                    <a href="#how" className={secondaryButton}>
                                        See how it works
                                    </a>
                                </div>
                            </div>

                            <div className="relative mx-auto w-full max-w-md lg:max-w-none">
                                <div
                                    aria-hidden="true"
                                    className="absolute -inset-4 -z-0 rounded-[2.5rem] bg-m-band sm:-inset-8"
                                />
                                <Apple className="absolute -top-10 right-2 size-16 rotate-12 sm:-right-6 sm:size-20" />
                                <Avocado className="absolute -bottom-12 left-2 size-16 -rotate-12 sm:-left-8 sm:size-20" />
                                <Lemon className="absolute top-1/2 -right-6 hidden size-14 sm:block lg:-right-12" />
                                <div className="relative px-2 sm:px-6">
                                    <AppPreview />
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        aria-label="Our promises"
                        className="border-y border-m-line bg-m-surface"
                    >
                        <ul className="mx-auto grid max-w-6xl grid-cols-2 gap-x-4 gap-y-5 px-4 py-6 sm:px-6 lg:grid-cols-4">
                            {promises.map(({ icon: Icon, label }) => (
                                <li
                                    key={label}
                                    className="flex items-center gap-2.5 text-sm font-medium"
                                >
                                    <Icon className="size-5 shrink-0 text-m-green" />
                                    {label}
                                </li>
                            ))}
                        </ul>
                    </section>

                    <section
                        id="how"
                        className="mx-auto max-w-6xl scroll-mt-20 px-4 py-20 sm:px-6"
                    >
                        <p className={cn(eyebrow, 'text-center')}>
                            How it works
                        </p>
                        <h2 className="mt-2 text-center font-display text-4xl font-semibold tracking-tight">
                            From “we’re out of milk” to home again
                        </h2>
                        <ol className="mt-12 grid gap-6 md:grid-cols-3">
                            {steps.map((step, index) => (
                                <li
                                    key={step.title}
                                    className="rounded-3xl border border-m-line bg-m-surface p-7"
                                >
                                    <span className="flex size-10 items-center justify-center rounded-full bg-m-green font-display text-lg font-semibold text-m-on-green">
                                        {index + 1}
                                    </span>
                                    <h3 className="mt-5 font-display text-xl font-semibold">
                                        {step.title}
                                    </h3>
                                    <p className="mt-2 text-m-muted">
                                        {step.body.replace(':app', name)}
                                    </p>
                                </li>
                            ))}
                        </ol>
                    </section>

                    <section
                        id="homes"
                        className="scroll-mt-20 bg-m-band py-20"
                    >
                        <div className="mx-auto max-w-6xl px-4 sm:px-6">
                            <p className={cn(eyebrow, 'text-center')}>
                                For your home
                            </p>
                            <h2 className="mt-2 text-center font-display text-4xl font-semibold tracking-tight">
                                However your household works
                            </h2>
                            <div
                                role="group"
                                aria-label="Choose a household type"
                                className="mt-8 flex flex-wrap justify-center gap-2"
                            >
                                {households.map((option) => (
                                    <button
                                        key={option.id}
                                        type="button"
                                        aria-pressed={
                                            option.id === household.id
                                        }
                                        onClick={() => setHousehold(option)}
                                        className={cn(
                                            'min-h-11 rounded-full border px-4 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-m-green focus-visible:ring-offset-2 focus-visible:ring-offset-m-band focus-visible:outline-none',
                                            option.id === household.id
                                                ? 'border-m-green bg-m-green text-m-on-green'
                                                : 'border-m-line bg-m-surface hover:border-m-green',
                                        )}
                                    >
                                        {option.label}
                                    </button>
                                ))}
                            </div>
                            <div
                                aria-live="polite"
                                className="relative mx-auto mt-8 max-w-3xl overflow-hidden rounded-3xl border border-m-line bg-m-surface p-8 sm:p-10 sm:pr-44"
                            >
                                <h3 className="font-display text-2xl font-semibold">
                                    {household.title}
                                </h3>
                                <p className="mt-3 text-m-muted">
                                    {household.body.replace(':app', name)}
                                </p>
                                <div
                                    aria-hidden="true"
                                    className="absolute top-1/2 right-8 hidden size-24 -translate-y-1/2 items-center justify-center rounded-full bg-m-band sm:flex"
                                >
                                    <MilkCarton className="size-16 rotate-6" />
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        id="features"
                        className="mx-auto max-w-6xl scroll-mt-20 px-4 py-20 sm:px-6"
                    >
                        <p className={cn(eyebrow, 'text-center')}>Features</p>
                        <h2 className="mx-auto mt-2 max-w-2xl text-center font-display text-4xl font-semibold tracking-tight">
                            Smart enough to help, honest about what it knows
                        </h2>
                        <ul className="mt-12 grid gap-x-8 gap-y-10 sm:grid-cols-2 lg:grid-cols-3">
                            {features.map(({ icon: Icon, title, body }) => (
                                <li key={title}>
                                    <span className="flex size-11 items-center justify-center rounded-2xl bg-m-band text-m-green">
                                        <Icon className="size-5" />
                                    </span>
                                    <h3 className="mt-4 font-display text-lg font-semibold">
                                        {title}
                                    </h3>
                                    <p className="mt-1.5 text-m-muted">
                                        {body}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    </section>

                    <section
                        id="privacy"
                        className="scroll-mt-20 bg-m-deep text-m-on-deep"
                    >
                        <div className="mx-auto grid max-w-6xl items-center gap-10 px-4 py-20 sm:px-6 lg:grid-cols-2">
                            <div>
                                <ShieldCheck className="size-10" />
                                <h2 className="mt-4 font-display text-4xl font-semibold tracking-tight">
                                    What you buy is your business
                                </h2>
                                <p className="mt-4 max-w-md text-m-on-deep-muted">
                                    Shopping lists say a lot about a home. Ours
                                    stay between you and the people you choose.
                                </p>
                                <div className="mt-6 flex flex-wrap gap-x-5 gap-y-2 text-sm">
                                    <Link
                                        href={legal.privacy()}
                                        className="underline underline-offset-4"
                                    >
                                        Privacy Policy
                                    </Link>
                                </div>
                            </div>
                            <ul className="grid gap-3">
                                {privacyPoints.map((point) => (
                                    <li
                                        key={point}
                                        className="rounded-2xl border border-white/15 bg-white/5 px-5 py-4"
                                    >
                                        {point}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </section>

                    <section className="relative overflow-hidden px-4 py-24 text-center sm:px-6">
                        <Carrot className="absolute top-10 left-[6%] hidden size-20 -rotate-12 md:block" />
                        <Bread className="absolute right-[7%] bottom-10 hidden size-20 rotate-6 md:block" />
                        <h2 className="mx-auto max-w-xl font-display text-4xl font-semibold tracking-tight sm:text-5xl">
                            Your next shop starts with one item
                        </h2>
                        <p className="mx-auto mt-4 max-w-md text-lg text-m-muted">
                            Create a list now, and invite your household when
                            you’re ready.
                        </p>
                        <Link
                            href={primaryHref}
                            className={cn(primaryButton, 'mt-8')}
                        >
                            {primaryLabel}
                            <ArrowRight className="size-4" />
                        </Link>
                    </section>
                </main>

                <footer className="border-t border-m-line">
                    <div className="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-8 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <span className="flex items-center gap-2 font-display font-semibold">
                            <AppLogoIcon className="size-5 fill-current text-m-green" />
                            {name}
                        </span>
                        <LegalLinks className="text-sm text-m-muted" />
                    </div>
                </footer>
            </div>
        </>
    );
}
