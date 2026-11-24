import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Payment = {
    payment_id: number;
    bill_id: number;
    amount_paid: string | number;
    payment_date: string;
    payment_date_input: string;
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

type BillOption = {
    bill_id: number;
    billing_date: string;
    total_water_bill: string | number;
    status: string;
    water_meter?: {
        meter_serial: string;
        consumer?: {
            consumer_name: string;
            account_number: string;
        } | null;
    } | null;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Payments', href: '/payments' }];

export default function PaymentIndex({
    payments,
    stats,
    filter,
    bills,
    paymentDateNow,
}: {
    payments: Paginated<Payment>;
    stats: PaymentStats;
    filter: { search: string };
    bills: BillOption[];
    paymentDateNow: string;
}) {
    const [editingId, setEditingId] = useState<number | null>(null);
    const [recordError, setRecordError] = useState<string>();
    const availableBills = editingId ? bills : bills.filter((bill) => bill.status === 'unpaid');
    const form = useForm({
        bill_id: '',
        amount_paid: '',
        payment_date: paymentDateNow,
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            onSuccess: () => {
                setEditingId(null);
                setRecordError(undefined);
                form.reset();
            },
            onError: (errors: Record<string, string>) => setRecordError(errors.record),
        };

        if (editingId) {
            form.put(`/payments/${editingId}`, options);
        } else {
            form.post('/payments', options);
        }
    };

    const edit = (payment: Payment) => {
        form.clearErrors();
        setRecordError(undefined);
        form.setData({
            bill_id: String(payment.bill_id),
            amount_paid: String(payment.amount_paid),
            payment_date: payment.payment_date_input,
        });
        setEditingId(payment.payment_id);
    };

    const cancelEdit = () => {
        setEditingId(null);
        form.reset();
        form.clearErrors();
        setRecordError(undefined);
    };

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
                        <p className="text-muted-foreground mt-1 text-sm">Recorded payments and their associated bills</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <button
                            className="bg-primary text-primary-foreground h-9 rounded-md px-3 text-sm font-medium"
                            onClick={cancelEdit}
                            type="button"
                        >
                            Record payment
                        </button>
                        <form action="/payments" method="get" className="flex gap-2">
                            <input
                                aria-label="Search payments"
                                className="bg-background h-9 w-64 max-w-full rounded-md border px-3 text-sm"
                                defaultValue={filter.search}
                                name="search"
                                placeholder="Search payment reference"
                                type="search"
                            />
                            <button className="h-9 rounded-md border px-3 text-sm font-medium" type="submit">
                                Search
                            </button>
                        </form>
                    </div>
                </header>

                <form onSubmit={submit} className="grid gap-3 rounded-md border p-4 md:grid-cols-4">
                    <h2 className="font-medium md:col-span-4">{editingId ? 'Edit payment' : 'Record payment'}</h2>
                    <p className="text-muted-foreground text-sm md:col-span-4">
                        A payment reference is generated automatically after successful payment.
                    </p>
                    <div>
                        <label className="mb-1 block text-sm" htmlFor="payment-bill">
                            Bill
                        </label>
                        <select
                            id="payment-bill"
                            className="bg-background h-9 w-full rounded-md border px-3 text-sm"
                            value={form.data.bill_id}
                            onChange={(event) => form.setData('bill_id', event.target.value)}
                            required
                        >
                            <option value="">Select a bill</option>
                            {availableBills.map((bill) => (
                                <option key={bill.bill_id} value={bill.bill_id}>
                                    {bill.water_meter?.consumer?.consumer_name ?? 'Unknown consumer'}
                                    {' - Account '}
                                    {bill.water_meter?.consumer?.account_number ?? 'N/A'}
                                    {' - Meter '}
                                    {bill.water_meter?.meter_serial ?? 'N/A'}
                                    {' - Bill #'}
                                    {bill.bill_id}
                                    {' ('}
                                    {bill.billing_date.slice(0, 10)}
                                    {') - '}
                                    {Number(bill.total_water_bill).toFixed(2)}
                                    {' ('}
                                    {bill.status}
                                    {')'}
                                </option>
                            ))}
                        </select>
                        {form.errors.bill_id && <p className="mt-1 text-sm text-red-600">{form.errors.bill_id}</p>}
                    </div>
                    <div>
                        <label className="mb-1 block text-sm" htmlFor="payment-amount">
                            Amount paid
                        </label>
                        <input
                            id="payment-amount"
                            className="bg-background h-9 w-full rounded-md border px-3 text-sm"
                            min="0"
                            step="0.01"
                            type="number"
                            value={form.data.amount_paid}
                            onChange={(event) => form.setData('amount_paid', event.target.value)}
                            required
                        />
                        {form.errors.amount_paid && <p className="mt-1 text-sm text-red-600">{form.errors.amount_paid}</p>}
                    </div>
                    <div>
                        <label className="mb-1 block text-sm" htmlFor="payment-date">
                            Payment date (Philippine time)
                        </label>
                        <input
                            id="payment-date"
                            className="bg-background h-9 w-full rounded-md border px-3 text-sm"
                            type="datetime-local"
                            value={form.data.payment_date}
                            onChange={(event) => form.setData('payment_date', event.target.value)}
                            required
                        />
                        <p className="text-muted-foreground mt-1 text-xs">Time zone: Asia/Manila (UTC+08:00)</p>
                        {form.errors.payment_date && <p className="mt-1 text-sm text-red-600">{form.errors.payment_date}</p>}
                    </div>
                    <div className="flex items-end gap-2">
                        <button
                            className="bg-primary text-primary-foreground h-9 rounded-md px-3 text-sm font-medium"
                            disabled={form.processing || availableBills.length === 0}
                            type="submit"
                        >
                            {editingId ? 'Save changes' : 'Record payment'}
                        </button>
                        {editingId && (
                            <button className="h-9 rounded-md border px-3 text-sm" onClick={cancelEdit} type="button">
                                Cancel
                            </button>
                        )}
                    </div>
                    {recordError && <p className="text-sm text-red-600 md:col-span-4">{recordError}</p>}
                </form>

                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {summaryCards.map((card) => (
                        <div key={card.label} className="bg-card rounded-lg border p-4 shadow-sm">
                            <p className="text-muted-foreground text-sm">{card.label}</p>
                            <h2 className="mt-3 text-3xl font-bold tracking-tight">{card.value}</h2>
                        </div>
                    ))}
                </section>

                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full min-w-[760px] text-left text-sm">
                        <thead className="bg-muted/50 text-muted-foreground border-b">
                            <tr>
                                <th className="px-4 py-3 font-medium">Reference</th>
                                <th className="px-4 py-3 font-medium">Bill</th>
                                <th className="px-4 py-3 font-medium">Payment date</th>
                                <th className="px-4 py-3 text-right font-medium">Amount paid</th>
                                <th className="px-4 py-3 text-right font-medium">Bill total</th>
                                <th className="px-4 py-3 font-medium">Actions</th>
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
                                    <td className="px-4 py-3">
                                        <div className="flex gap-2">
                                            <button className="rounded border px-2 py-1" onClick={() => edit(payment)} type="button">
                                                Edit
                                            </button>
                                            <button
                                                className="rounded border px-2 py-1 text-red-600"
                                                onClick={() =>
                                                    window.confirm(
                                                        `Delete payment ${payment.payment_reference}? This will mark bill #${payment.bill_id} unpaid.`,
                                                    ) &&
                                                    form.delete(`/payments/${payment.payment_id}`, {
                                                        onSuccess: () => setRecordError(undefined),
                                                        onError: (errors) => setRecordError(errors.record),
                                                    })
                                                }
                                                type="button"
                                            >
                                                Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {payments.data.length === 0 && (
                                <tr>
                                    <td className="text-muted-foreground px-4 py-8 text-center" colSpan={6}>
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
                                className="text-muted-foreground rounded border px-3 py-1.5 text-sm"
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ),
                    )}
                </nav>
            </main>
        </AppLayout>
    );
}
