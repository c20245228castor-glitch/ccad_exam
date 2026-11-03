import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

type WaterRate = {
    rate_id: number;
    rate_per_cu_m: string | number;
    effective_from: string;
    effective_to: string | null;
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

type RateStats = {
    total: number;
    active: number;
    expired: number;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Water rates', href: '/water-rates' },
];

export default function WaterRateIndex({
    waterRates,
    stats,
    filter,
}: {
    waterRates: Paginated<WaterRate>;
    stats: RateStats;
    filter: { search: string };
}) {
    const summaryCards = [
        { label: 'Total rates', value: stats?.total ?? 0 },
        { label: 'Active rates', value: stats?.active ?? 0 },
        { label: 'Expired rates', value: stats?.expired ?? 0 },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Water rates" />
            <main className="space-y-6 p-4 md:p-6">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Water rates</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Rates and the periods they apply to
                        </p>
                    </div>
                    <form action="/water-rates" method="get" className="flex gap-2">
                        <input
                            aria-label="Search water rates"
                            className="h-9 w-64 max-w-full rounded-md border bg-background px-3 text-sm"
                            defaultValue={filter.search}
                            name="search"
                            placeholder="Search rate amount"
                            type="search"
                        />
                        <button className="h-9 rounded-md border px-3 text-sm font-medium" type="submit">
                            Search
                        </button>
                    </form>
                </header>

                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
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
                                <th className="px-4 py-3 font-medium">Rate per m³</th>
                                <th className="px-4 py-3 font-medium">Effective from</th>
                                <th className="px-4 py-3 font-medium">Effective to</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {waterRates.data.map((rate) => (
                                <tr key={rate.rate_id}>
                                    <td className="px-4 py-3 font-medium">{Number(rate.rate_per_cu_m).toFixed(2)}</td>
                                    <td className="px-4 py-3">{rate.effective_from}</td>
                                    <td className="px-4 py-3">{rate.effective_to ?? 'No end date'}</td>
                                </tr>
                            ))}
                            {waterRates.data.length === 0 && (
                                <tr>
                                    <td className="px-4 py-8 text-center text-muted-foreground" colSpan={3}>
                                        No water rates found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <nav aria-label="Water rate pages" className="flex flex-wrap gap-1">
                    {waterRates.links.map((link, index) =>
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