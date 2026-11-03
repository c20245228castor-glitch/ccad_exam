import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

type WaterBill = {
    bill_id: number;
    billing_date: string;
    due_date: string;
    consumption_cu_m: string | number;
    total_water_bill: string | number;
    status: string;
    water_meter?: {
        meter_serial: string;
        consumer?: { consumer_name: string } | null;
    } | null;
    water_rate?: { rate_per_cu_m: string | number } | null;
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

type BillStats = {
    total: number;
    paid: number;
    unpaid: number;
    overdue: number;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Water bills', href: '/water-bills' },
];

export default function WaterBillIndex({
    bills,
    stats,
    filter,
}: {
    bills: Paginated<WaterBill>;
    stats: BillStats;
    filter: { search: string };
}) {
    const summaryCards = [
        { label: 'Total bills', value: stats?.total ?? 0 },
        { label: 'Paid', value: stats?.paid ?? 0 },
        { label: 'Unpaid', value: stats?.unpaid ?? 0 },
        { label: 'Overdue', value: stats?.overdue ?? 0 },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Water bills" />
            <main className="space-y-6 p-4 md:p-6">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Water bills</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Billing periods, consumption, and payment status
                        </p>
                    </div>
                    <form action="/water-bills" method="get" className="flex gap-2">
                        <input
                            aria-label="Search water bills"
                            className="h-9 w-64 max-w-full rounded-md border bg-background px-3 text-sm"
                            defaultValue={filter.search}
                            name="search"
                            placeholder="Search meter or status"
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
                    <table className="w-full min-w-[900px] text-left text-sm">
                        <thead className="border-b bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Consumer / meter</th>
                                <th className="px-4 py-3 font-medium">Billing date</th>
                                <th className="px-4 py-3 font-medium">Due date</th>
                                <th className="px-4 py-3 text-right font-medium">Usage</th>
                                <th className="px-4 py-3 text-right font-medium">Total</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {bills.data.map((bill) => (
                                <tr key={bill.bill_id}>
                                    <td className="px-4 py-3">
                                        <div className="font-medium">{bill.water_meter?.consumer?.consumer_name ?? 'Unknown consumer'}</div>
                                        <div className="text-muted-foreground">{bill.water_meter?.meter_serial ?? 'Unknown meter'}</div>
                                    </td>
                                    <td className="px-4 py-3">{bill.billing_date}</td>
                                    <td className="px-4 py-3">{bill.due_date}</td>
                                    <td className="px-4 py-3 text-right">{Number(bill.consumption_cu_m).toFixed(2)} m³</td>
                                    <td className="px-4 py-3 text-right">{Number(bill.total_water_bill).toFixed(2)}</td>
                                    <td className="px-4 py-3 capitalize">{bill.status}</td>
                                </tr>
                            ))}
                            {bills.data.length === 0 && (
                                <tr>
                                    <td className="px-4 py-8 text-center text-muted-foreground" colSpan={6}>
                                        No water bills found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <nav aria-label="Water bill pages" className="flex flex-wrap gap-1">
                    {bills.links.map((link, index) =>
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