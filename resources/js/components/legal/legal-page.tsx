import { Head, Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import { LegalLinks } from '@/components/legal/legal-links';
import { home } from '@/routes';

export type LegalFacts = {
    entity: string | null;
    contact_email: string | null;
    effective_date: string | null;
    backup_retention: string | null;
    log_retention: string | null;
};

/** Shows a configured fact, or a visible placeholder when it is still unset. */
export function Fact({
    value,
    placeholder,
}: {
    value: string | null;
    placeholder: string;
}) {
    return value ? (
        <>{value}</>
    ) : (
        <mark className="rounded bg-secondary px-1 text-foreground">
            [{placeholder}]
        </mark>
    );
}

export function Contact({ legal }: { legal: LegalFacts }) {
    return legal.contact_email ? (
        <a
            href={`mailto:${legal.contact_email}`}
            className="text-primary underline underline-offset-4"
        >
            {legal.contact_email}
        </a>
    ) : (
        <Fact value={null} placeholder="contact email" />
    );
}

export function LegalPage({
    title,
    legal,
    children,
}: {
    title: string;
    legal: LegalFacts;
    children: ReactNode;
}) {
    const { name } = usePage().props;
    return (
        <>
            <Head title={title} />
            <div className="min-h-screen bg-background text-foreground">
                <header className="mx-auto flex w-full max-w-3xl items-center p-4">
                    <Link
                        href={home()}
                        className="flex items-center gap-2 font-semibold"
                    >
                        <AppLogoIcon className="size-6 fill-current text-primary" />
                        {name}
                    </Link>
                </header>
                <main className="mx-auto w-full max-w-3xl p-4">
                    <h1 className="text-3xl font-semibold tracking-tight">
                        {title}
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Effective{' '}
                        <Fact
                            value={legal.effective_date}
                            placeholder="effective date"
                        />
                    </p>
                    <div className="mt-6 space-y-4 [&_h2]:mt-8 [&_h2]:text-xl [&_h2]:font-semibold [&_li]:ml-5 [&_li]:list-disc [&_ul]:space-y-1">
                        {children}
                    </div>
                </main>
                <footer className="mx-auto w-full max-w-3xl p-4">
                    <LegalLinks />
                </footer>
            </div>
        </>
    );
}
