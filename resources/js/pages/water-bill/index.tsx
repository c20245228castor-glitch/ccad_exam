import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type WaterBill = {
    bill_id: number;
    meter_id: number;
    rate_id: number;
    previous_reading: string | number;
    current_reading: string | number;
    billing_date: string;
    due_date: string;
    consumption_cu_m: string | number;
    total_water_bill: string | number;
    status: string;
    payments_exists: boolean;
    water_meter?: {
        meter_serial: string;
        consumer?: { consumer_name: string } | null;
    } | null;
    water_rate?: { rate_per_cu_m: string | number } | null;
};

type MeterOption = {
    meter_id: number;
    meter_serial: string;
    consumer?: { consumer_name: string } | null;
};

type RateOption = {
    rate_id: number;
    rate_per_cu_m: string | number;
    effective_from: string;
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

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Water bills', href: '/water-bills' }];

export default function WaterBillIndex({
    bills,
    stats,
    filter,
    meters,
    rates,
}: {
    bills: Paginated<WaterBill>;
    stats: BillStats;
    filter: { search: string };
    meters: MeterOption[];
    rates: RateOption[];
}) {
    const [editingId, setEditingId] = useState<number | null>(null);
    const [recordError, setRecordError] = useState<string>();
    const form = useForm({
        meter_id: '',
        rate_id: '',
        previous_reading: '',
        current_reading: '',
        billing_date: '',
        due_date: '',
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
            form.put(`/water-bills/${editingId}`, options);
        } else {
            form.post('/water-bills', options);
        }
    };

    const edit = (bill: WaterBill) => {
        form.clearErrors();
        setRecordError(undefined);
        form.setData({
            meter_id: String(bill.meter_id),
            rate_id: String(bill.rate_id),
            previous_reading: String(bill.previous_reading),
            current_reading: String(bill.current_reading),
            billing_date: bill.billing_date.slice(0, 10),
            due_date: bill.due_date.slice(0, 10),
        });
        setEditingId(bill.bill_id);
    };

    const cancelEdit = () => {
        setEditingId(null);
        form.reset();
        form.clearErrors();
        setRecordError(undefined);
    };

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
                        <p className="text-muted-foreground mt-1 text-sm">Billing periods, consumption, and payment status</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <button
                            className="bg-primary text-primary-foreground h-9 rounded-md px-3 text-sm font-medium"
                            onClick={cancelEdit}
                            type="button"
                        >
                            Create bill
                        </button>
                        <form action="/water-bills" method="get" className="flex gap-2">
                            <input
                                aria-label="Search water bills"
                                className="bg-background h-9 w-64 max-w-full rounded-md border px-3 text-sm"
                                defaultValue={filter.search}
                                name="search"
                                placeholder="Search meter or status"
                                type="search"
                            />
                            <button className="h-9 rounded-md border px-3 text-sm font-medium" type="submit">
                                Search
                            </button>
                        </form>
                    </div>
                </header>

                <form onSubmit={submit} className="grid gap-3 rounded-md border p-4 md:grid-cols-3">
                    <h2 className="font-medium md:col-span-3">{editingId ? 'Edit water bill' : 'Create water bill'}</h2>
                    <div>
                        <label className="mb-1 block text-sm" htmlFor="bill-meter">
                            Meter
                        </label>
                        <select
                            id="bill-meter"
                            className="bg-background h-9 w-full rounded-md border px-3 text-sm"
                            value={form.data.meter_id}
                            onChange={(event) => form.setData('meter_id', event.target.value)}
                            required
                        >
                            <option value="">Select a meter</option>
                            {meters.map((meter) => (
                                <option key={meter.meter_id} value={meter.meter_id}>
                                    {meter.meter_serial} - {meter.consumer?.consumer_name ?? 'Unknown consumer'}
                                </option>
                            ))}
                        </select>
                        {form.errors.meter_id && <p className="mt-1 text-sm text-red-600">{form.errors.meter_id}</p>}
                    </div>
                    <div>
                        <label className="mb-1 block text-sm" htmlFor="bill-rate">
                            Water rate
                        </label>
                        <select
                            id="bill-rate"
                            className="bg-background h-9 w-full rounded-md border px-3 text-sm"
                            value={form.data.rate_id}
                            onChange={(event) => form.setData('rate_id', event.target.value)}
                            required
                        >
                            <option value="">Select a rate</option>
                            {rates.map((rate) => (
                                <option key={rate.rate_id} value={rate.rate_id}>
                                    {Number(rate.rate_per_cu_m).toFixed(2)} per m³ (from {rate.effective_from.slice(0, 10)})
                                </option>
                            ))}
                        </select>
                        {form.errors.rate_id && <p className="mt-1 text-sm text-red-600">{form.errors.rate_id}</p>}
                    </div>
                    <div>
                        <label className="mb-1 block text-sm" htmlFor="bill-from">
                            Previous reading
                        </label>
                        <input
                            id="bill-from"
                            className="bg-background h-9 w-full rounded-md border px-3 text-sm"
                            min="0"
                            step="0.01"
                            type="number"
                            value={form.data.previous_reading}
                            onChange={(event) => form.setData('previous_reading', event.target.value)}
                            required
                        />
                        {form.errors.previous_reading && <p className="mt-1 text-sm text-red-600">{form.errors.previous_reading}</p>}
                    </div>
                    <div>
                        <label className="mb-1 block text-sm" htmlFor="bill-current">
                            Current reading
                        </label>
                        <input
                            id="bill-current"
                            className="bg-background h-9 w-full rounded-md border px-3 text-sm"
                            min={form.data.previous_reading || '0'}
                            step="0.01"
                            type="number"
                            value={form.data.current_reading}
                            onChange={(event) => form.setData('current_reading', event.target.value)}
                            required
                        />
                        {form.errors.current_reading && <p className="mt-1 text-sm text-red-600">{form.errors.current_reading}</p>}
                    </div>
                    <div>
                        <label className="mb-1 block text-sm" htmlFor="bill-date">
                            Billing date
                        </label>
                        <input
                            id="bill-date"
                            className="bg-background h-9 w-full rounded-md border px-3 text-sm"
                            type="date"
                            value={form.data.billing_date}
                            onChange={(event) => form.setData('billing_date', event.target.value)}
                            required
                        />
                        {form.errors.billing_date && <p className="mt-1 text-sm text-red-600">{form.errors.billing_date}</p>}
                    </div>
                    <div>
                        <label className="mb-1 block text-sm" htmlFor="bill-due">
                            Due date
                        </label>
                        <input
                            id="bill-due"
                            className="bg-background h-9 w-full rounded-md border px-3 text-sm"
                            min={form.data.billing_date || undefined}
                            type="date"
                            value={form.data.due_date}
                            onChange={(event) => form.setData('due_date', event.target.value)}
                            required
                        />
                        {form.errors.due_date && <p className="mt-1 text-sm text-red-600">{form.errors.due_date}</p>}
                    </div>
                    <div className="flex items-end gap-2">
                        <button
                            className="bg-primary text-primary-foreground h-9 rounded-md px-3 text-sm font-medium"
                            disabled={form.processing || meters.length === 0 || rates.length === 0}
                            type="submit"
                        >
                            {editingId ? 'Save changes' : 'Create bill'}
                        </button>
                        {editingId && (
                            <button className="h-9 rounded-md border px-3 text-sm" onClick={cancelEdit} type="button">
                                Cancel
                            </button>
                        )}
                    </div>
                    {recordError && <p className="text-sm text-red-600 md:col-span-3">{recordError}</p>}
                </form>

                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {summaryCards.map((card) => (
                        <div key={card.label} className="bg-card rounded-lg border p-4 shadow-sm">
                            <p className="text-muted-foreground text-sm">{card.label}</p>
                            <h2 className="mt-3 text-3xl font-bold tracking-tight">{card.value.toLocaleString()}</h2>
                        </div>
                    ))}
                </section>

                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full min-w-[900px] text-left text-sm">
                        <thead className="bg-muted/50 text-muted-foreground border-b">
                            <tr>
                                <th className="px-4 py-3 font-medium">Consumer / meter</th>
                                <th className="px-4 py-3 font-medium">Billing date</th>
                                <th className="px-4 py-3 font-medium">Due date</th>
                                <th className="px-4 py-3 text-right font-medium">Usage</th>
                                <th className="px-4 py-3 text-right font-medium">Total</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 font-medium">Actions</th>
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
                                    <td className="px-4 py-3">
                                        <div className="flex gap-2">
                                            {!bill.payments_exists && (
                                                <>
                                                    <button className="rounded border px-2 py-1" onClick={() => edit(bill)} type="button">
                                                        Edit
                                                    </button>
                                                    <button
                                                        className="rounded border px-2 py-1 text-red-600"
                                                        onClick={() =>
                                                            window.confirm(`Delete bill #${bill.bill_id}?`) &&
                                                            form.delete(`/water-bills/${bill.bill_id}`, {
                                                                onSuccess: () => setRecordError(undefined),
                                                                onError: (errors) => setRecordError(errors.record),
                                                            })
                                                        }
                                                        type="button"
                                                    >
                                                        Delete
                                                    </button>
                                                </>
                                            )}
                                            {bill.payments_exists && <span className="text-muted-foreground">Payment recorded</span>}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {bills.data.length === 0 && (
                                <tr>
                                    <td className="text-muted-foreground px-4 py-8 text-center" colSpan={7}>
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
