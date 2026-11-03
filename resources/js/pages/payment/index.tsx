import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';

type Payment = {
    payment_id: number;
    bill_id: number;
    amount_paid: string | number;
    payment_date: string;
    payment_reference: string;
    water_bill?: { total_water_bill: string | number } | null;
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

type PaymentStats = {
    total: number;
    amount_paid: number;
    unique_bills: number;
    recent: number;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Payments', href: '/payments' },
];

export default function PaymentIndex({
    payments,
    stats,
    filter,
}: {
    payments: Paginated<Payment>;
    stats: PaymentStats;
    filter: { search: string };
}) {
    const summaryCards = [
        { label: 'Total payments', value: stats?.total ?? 0 },
        { label: 'Total collected', value: Number(stats?.amount_paid ?? 0).toLocaleString() },
        { label: 'Bills paid', value: stats?.unique_bills ?? 0 },
        { label: 'Today', value: stats?.recent ?? 0 },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payments" />
            <main className="space-y-6 p-4 md:p-6">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Payments</h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Recorded payments and their associated bills
                        </p>
                    </div>
                    <form action="/payments" method="get" className="flex gap-2">
                        <input
                            aria-label="Search payments"
                            className="h-9 w-64 max-w-full rounded-md border bg-background px-3 text-sm"
                            defaultValue={filter.search}
                            name="search"
                            placeholder="Search payment reference"
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
                            <h2 className="mt-3 text-3xl font-bold tracking-tight">{card.value}</h2>
                        </div>
                    ))}
                </section>

                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full min-w-[760px] text-left text-sm">
                        <thead className="border-b bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Reference</th>
                                <th className="px-4 py-3 font-medium">Bill</th>
                                <th className="px-4 py-3 font-medium">Payment date</th>
                                <th className="px-4 py-3 text-right font-medium">Amount paid</th>
                                <th className="px-4 py-3 text-right font-medium">Bill total</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {payments.data.map((payment) => (
                                <tr key={payment.payment_id}>
                                    <td className="px-4 py-3 font-medium">{payment.payment_reference}</td>
                                    <td className="px-4 py-3">#{payment.bill_id}</td>
                                    <td className="px-4 py-3">{payment.payment_date}</td>
                                    <td className="px-4 py-3 text-right">{Number(payment.amount_paid).toFixed(2)}</td>
                                    <td className="px-4 py-3 text-right">
                                        {payment.water_bill ? Number(payment.water_bill.total_water_bill).toFixed(2) : '—'}
                                    </td>
                                </tr>
                            ))}
                            {payments.data.length === 0 && (
                                <tr>
                                    <td className="px-4 py-8 text-center text-muted-foreground" colSpan={5}>
                                        No payments found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <nav aria-label="Payment pages" className="flex flex-wrap gap-1">
                    {payments.links.map((link, index) =>
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