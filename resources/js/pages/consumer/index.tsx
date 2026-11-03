import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

type Consumer = {
    consumer_id: number;
    account_number: string;
    consumer_name: string;
    consumer_address: string;
};

type PageLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Paginated<T> = {
    data: T[];
    links: PageLink[];
};

type ConsumerStats = {
    total: number;
    with_meters: number;
    without_meters: number;
    active_meters: number;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Consumers', href: '/consumers' },
];

export default function ConsumerIndex({
    consumers,
    stats,
    filter,
}: {
    consumers: Paginated<Consumer>;
    stats: ConsumerStats;
    filter: { search: string };
}) {
    const summaryCards = [
        { label: 'Total consumers', value: stats?.total ?? 0 },
        { label: 'With meters', value: stats?.with_meters ?? 0 },
        { label: 'Without meters', value: stats?.without_meters ?? 0 },
        { label: 'Active meters', value: stats?.active_meters ?? 0 },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Consumers" />
            <main className="space-y-6 p-4 md:p-6">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Consumers</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Consumer accounts and service addresses
                        </p>
                    </div>
                    <form action="/consumers" method="get" className="flex gap-2">
                        <input
                            aria-label="Search consumers"
                            className="h-9 w-64 max-w-full rounded-md border bg-background px-3 text-sm"
                            defaultValue={filter.search}
                            name="search"
                            placeholder="Search name or account"
                            type="search"
                        />
                        <button className="h-9 rounded-md border px-3 text-sm font-medium" type="submit">
                            Search
                        </button>
                    </form>
                </header>

                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {summaryCards.map((card) => (
                        <div key={card.label} className="rounded-lg border bg-card p-4 shadow-sm">
                            <p className="text-sm text-muted-foreground">{card.label}</p>
                            <h2 className="mt-3 text-3xl font-bold tracking-tight">{card.value.toLocaleString()}</h2>
                        </div>
                    ))}
                </section>

                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full min-w-[640px] text-left text-sm">
                        <thead className="border-b bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Account</th>
                                <th className="px-4 py-3 font-medium">Consumer</th>
                                <th className="px-4 py-3 font-medium">Address</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {consumers.data.map((consumer) => (
                                <tr key={consumer.consumer_id}>
                                    <td className="px-4 py-3 font-medium">{consumer.account_number}</td>
                                    <td className="px-4 py-3">{consumer.consumer_name}</td>
                                    <td className="px-4 py-3">{consumer.consumer_address}</td>
                                </tr>
                            ))}
                            {consumers.data.length === 0 && (
                                <tr>
                                    <td className="px-4 py-8 text-center text-muted-foreground" colSpan={3}>
                                        No consumers found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <nav aria-label="Consumer pages" className="flex flex-wrap gap-1">
                    {consumers.links.map((link, index) =>
                        link.url ? (
                            <Link
                                key={`${link.label}-${index}`}
                                href={link.url}
                                className={`rounded border px-3 py-1.5 text-sm ${link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'}`}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ) : (
                            <span
                                key={`${link.label}-${index}`}
                                className="rounded border px-3 py-1.5 text-sm text-muted-foreground"
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ),
                    )}
                </nav>
            </main>
        </AppLayout>
    );
}