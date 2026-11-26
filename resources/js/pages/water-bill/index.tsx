import AppLayout from '@/layouts/app-layout';
import { formatAppDate, toAppDateInput } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

function addOneMonth(date: string): string {
    const [year, month, day] = date.split('-').map(Number);
    const lastDayOfNextMonth = new Date(Date.UTC(year, month + 1, 0)).getUTCDate();
    const nextMonthDate = new Date(Date.UTC(year, month, Math.min(day, lastDayOfNextMonth)));

    return [
        nextMonthDate.getUTCFullYear(),
        String(nextMonthDate.getUTCMonth() + 1).padStart(2, '0'),
        String(nextMonthDate.getUTCDate()).padStart(2, '0'),
    ].join('-');
}

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
    current_penalty_fee: string | number;
    credit_applied: string | number;
    amount_due: string | number;
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
    today,
}: {
    bills: Paginated<WaterBill>;
    stats: BillStats;
    filter: { search: string };
    meters: MeterOption[];
    rates: RateOption[];
    today: string;
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
            billing_date: toAppDateInput(bill.billing_date),
            due_date: toAppDateInput(bill.due_date),
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
    const selectedRate = rates.find((rate) => String(rate.rate_id) === form.data.rate_id);
    const hasReadings = form.data.previous_reading !== '' && form.data.current_reading !== '';
    const estimatedConsumption = hasReadings
        ? Number(form.data.current_reading) - Number(form.data.previous_reading)
        : null;
    const readingsInvalid = estimatedConsumption !== null && estimatedConsumption <= 0;
    const estimatedCharge =
        estimatedConsumption !== null && selectedRate
            ? estimatedConsumption * Number(selectedRate.rate_per_cu_m)
            : null;
    const maxDueDate = form.data.billing_date ? addOneMonth(form.data.billing_date) : undefined;

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
                            disabled={Boolean(editingId)}
                            onChange={(event) => {
                                const meterId = event.target.value;

                                if (meterId && !editingId) {
                                    form.setData({
                                        ...form.data,
                                        meter_id: meterId,
                                        billing_date: today,
                                        due_date: addOneMonth(today),
                                    });
                                } else {
                                    form.setData('meter_id', meterId);
                                }
                            }}
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
                            disabled={Boolean(editingId)}
                            onChange={(event) => form.setData('rate_id', event.target.value)}
                            required
                        >
                            <option value="">Select a rate</option>
                            {rates.map((rate) => (
                                <option key={rate.rate_id} value={rate.rate_id}>
                                    {Number(rate.rate_per_cu_m).toFixed(2)} per m³ (from {formatAppDate(rate.effective_from)})
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
                            readOnly={Boolean(editingId)}
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
                            min={
                                form.data.previous_reading
                                    ? (Number(form.data.previous_reading) + 0.01).toFixed(2)
                                    : '0'
                            }
                            step="0.01"
                            type="number"
                            value={form.data.current_reading}
                            onChange={(event) => form.setData('current_reading', event.target.value)}
                            required
                        />
                        {form.errors.current_reading && <p className="mt-1 text-sm text-red-600">{form.errors.current_reading}</p>}
                        {readingsInvalid && (
                            <p className="mt-1 text-sm text-red-600">
                                Current reading must be greater than previous reading.
                            </p>
                        )}
                    </div>
                    <div>
                        <label className="mb-1 block text-sm" htmlFor="bill-date">
                            Billing date
                        </label>
                        <input
                            id="bill-date"
                            className="bg-background h-9 w-full rounded-md border px-3 text-sm"
                            type="date"
                            disabled={Boolean(editingId)}
                            value={form.data.billing_date}
                            onChange={(event) => {
                                const billingDate = event.target.value;
                                form.setData({
                                    ...form.data,
                                    billing_date: billingDate,
                                    due_date: billingDate ? addOneMonth(billingDate) : '',
                                });
                            }}
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
                            min={maxDueDate}
                            max={maxDueDate}
                            type="date"
                            disabled={Boolean(editingId)}
                            value={form.data.due_date}
                            onChange={(event) => form.setData('due_date', event.target.value)}
                            required
                        />
                        {form.errors.due_date && <p className="mt-1 text-sm text-red-600">{form.errors.due_date}</p>}
                    </div>
                    <div className="text-muted-foreground space-y-1 text-sm md:col-span-3">
                        <p>
                            The bill uses (current reading − previous reading) × water rate. Selecting a meter only links the bill to
                            that meter; enter its readings to calculate usage.
                        </p>
                        {estimatedConsumption !== null && selectedRate && estimatedConsumption > 0 && (
                            <p>
                                Estimated usage: {estimatedConsumption.toFixed(2)} m³ · Base water charge:{' '}
                                {estimatedCharge?.toFixed(2)}
                                . Any overdue penalty is calculated separately.
                            </p>
                        )}
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
                                <th className="px-4 py-3 text-right font-medium">Amount due</th>
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
                                    <td className="px-4 py-3">{formatAppDate(bill.billing_date)}</td>
                                    <td className="px-4 py-3">{formatAppDate(bill.due_date)}</td>
                                    <td className="px-4 py-3 text-right">{Number(bill.consumption_cu_m).toFixed(2)} m³</td>
                                    <td className="px-4 py-3 text-right">
                                        <div>{Number(bill.amount_due).toFixed(2)}</div>
                                        {Number(bill.current_penalty_fee) > 0 && (
                                            <div className="text-red-600 text-xs">
                                                Late penalty: {Number(bill.current_penalty_fee).toFixed(2)}
                                            </div>
                                        )}
                                        {Number(bill.credit_applied) > 0 && (
                                            <div className="text-muted-foreground text-xs">
                                                Credit applied: {Number(bill.credit_applied).toFixed(2)}
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 capitalize">{bill.status}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex gap-2">
                                            {bill.status === 'unpaid' && !bill.payments_exists && (
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
                                            {(bill.payments_exists || bill.status === 'paid') && (
                                                <span className="text-muted-foreground">
                                                    {bill.payments_exists ? 'Payment recorded' : 'Paid by account credit'}
                                                </span>
                                            )}
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
