import FormDialog from '@/components/form-dialog';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

type Consumer = { consumer_name: string };
type MeterOption = { meter_id: number; meter_serial: string; consumer?: Consumer | null };
type RateOption = { rate_id: number; rate_per_cu_m: string | number; effective_from: string; effective_to: string | null };
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
    penalty_fee: string | number;
    status: string;
    water_meter?: MeterOption | null;
    water_rate?: RateOption | null;
};
type PageLink = { url: string | null; label: string; active: boolean };
type Paginated<T> = { data: T[]; links: PageLink[] };
type BillStats = { total: number; paid: number; unpaid: number; overdue: number };
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Water bills', href: '/water-bills' }];

export default function WaterBillIndex({
    bills,
    stats,
    meters,
    rates,
    filter,
}: {
    bills: Paginated<WaterBill>;
    stats: BillStats;
    meters: MeterOption[];
    rates: RateOption[];
    filter: { search: string };
}) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const form = useForm({
        meter_id: '',
        rate_id: '',
        previous_reading: '',
        current_reading: '',
        billing_date: '',
        due_date: '',
    });

    const openCreate = () => {
        setEditingId(null);
        form.reset();
        form.clearErrors();
        setDialogOpen(true);
    };

    const openEdit = (bill: WaterBill) => {
        setEditingId(bill.bill_id);
        form.setData({
            meter_id: String(bill.meter_id),
            rate_id: String(bill.rate_id),
            previous_reading: String(bill.previous_reading),
            current_reading: String(bill.current_reading),
            billing_date: bill.billing_date,
            due_date: bill.due_date,
        });
        form.clearErrors();
        setDialogOpen(true);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { onSuccess: () => { setDialogOpen(false); form.reset(); } };
        if (editingId) form.put(`/water-bills/${editingId}`, options);
        else form.post('/water-bills', options);
    };

    const remove = (bill: WaterBill) => {
        if (window.confirm(`Delete bill #${bill.bill_id}?`)) {
            router.delete(`/water-bills/${bill.bill_id}`);
        }
    };

    const summaryCards = [
        { label: 'Total bills', value: stats.total },
        { label: 'Paid', value: stats.paid },
        { label: 'Unpaid', value: stats.unpaid },
        { label: 'Overdue', value: stats.overdue },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Water bills" />
            <main className="space-y-6 p-4 md:p-6">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div><h1 className="text-2xl font-semibold">Water bills</h1><p className="mt-1 text-sm text-muted-foreground">Billing periods, consumption, and payment status</p></div>
                    <div className="flex flex-wrap gap-2">
                        <form action="/water-bills" method="get" className="flex gap-2">
                            <input aria-label="Search water bills" className="h-9 w-64 max-w-full rounded-md border bg-background px-3 text-sm" defaultValue={filter.search} name="search" placeholder="Search meter or status" type="search" />
                            <button className="h-9 rounded-md border px-3 text-sm font-medium" type="submit">Search</button>
                        </form>
                        <button className="h-9 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground" onClick={openCreate} type="button">Create bill</button>
                    </div>
                </header>

                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {summaryCards.map((card) => <div key={card.label} className="rounded-lg border bg-card p-4 shadow-sm"><p className="text-sm text-muted-foreground">{card.label}</p><h2 className="mt-3 text-3xl font-bold tracking-tight">{card.value.toLocaleString()}</h2></div>)}
                </section>

                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full min-w-[1050px] text-left text-sm">
                        <thead className="border-b bg-muted/50 text-muted-foreground"><tr><th className="px-4 py-3 font-medium">Consumer / meter</th><th className="px-4 py-3 font-medium">Billing date</th><th className="px-4 py-3 font-medium">Due date</th><th className="px-4 py-3 text-right font-medium">Usage</th><th className="px-4 py-3 text-right font-medium">Penalty</th><th className="px-4 py-3 text-right font-medium">Total</th><th className="px-4 py-3 font-medium">Status</th><th className="px-4 py-3 text-right font-medium">Actions</th></tr></thead>
                        <tbody className="divide-y">
                            {bills.data.map((bill) => (
                                <tr key={bill.bill_id}>
                                    <td className="px-4 py-3"><div className="font-medium">{bill.water_meter?.consumer?.consumer_name ?? 'Unknown consumer'}</div><div className="text-muted-foreground">{bill.water_meter?.meter_serial ?? 'Unknown meter'}</div></td>
                                    <td className="px-4 py-3">{bill.billing_date}</td>
                                    <td className="px-4 py-3">{bill.due_date}</td>
                                    <td className="px-4 py-3 text-right">{Number(bill.consumption_cu_m).toFixed(2)} m³</td>
                                    <td className="px-4 py-3 text-right">{Number(bill.penalty_fee).toFixed(2)}</td>
                                    <td className="px-4 py-3 text-right">{Number(bill.total_water_bill).toFixed(2)}</td>
                                    <td className="px-4 py-3 capitalize">{bill.status}</td>
                                    <td className="space-x-2 px-4 py-3 text-right">
                                        {bill.status === 'unpaid' && <><button className="rounded border px-2 py-1 hover:bg-muted" onClick={() => openEdit(bill)} type="button">Edit</button><button className="rounded border px-2 py-1 text-destructive hover:bg-muted" onClick={() => remove(bill)} type="button">Delete</button></>}
                                        {bill.status === 'paid' && <span className="text-muted-foreground">Locked</span>}
                                    </td>
                                </tr>
                            ))}
                            {bills.data.length === 0 && <tr><td className="px-4 py-8 text-center text-muted-foreground" colSpan={8}>No water bills found.</td></tr>}
                        </tbody>
                    </table>
                </div>

                <nav aria-label="Water bill pages" className="flex flex-wrap gap-1">
                    {bills.links.map((link, index) => link.url ? <Link key={`${link.label}-${index}`} href={link.url} className={`rounded border px-3 py-1.5 text-sm ${link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'}`} dangerouslySetInnerHTML={{ __html: link.label }} /> : <span key={`${link.label}-${index}`} className="rounded border px-3 py-1.5 text-sm text-muted-foreground" dangerouslySetInnerHTML={{ __html: link.label }} />)}
                </nav>
            </main>

            <FormDialog title={editingId ? 'Edit water bill' : 'Create water bill'} open={dialogOpen} onClose={() => setDialogOpen(false)}>
                <form className="space-y-4" onSubmit={submit}>
                    <label className="block space-y-1 text-sm"><span>Meter</span>
                        <select className="w-full rounded-md border bg-background px-3 py-2" value={form.data.meter_id} onChange={(e) => form.setData('meter_id', e.target.value)}>
                            <option value="">Select meter</option>
                            {meters.map((meter) => <option key={meter.meter_id} value={meter.meter_id}>{meter.meter_serial} — {meter.consumer?.consumer_name ?? 'Consumer'}</option>)}
                        </select>
                        {form.errors.meter_id && <span className="text-destructive">{form.errors.meter_id}</span>}
                    </label>
                    <label className="block space-y-1 text-sm"><span>Rate</span>
                        <select className="w-full rounded-md border bg-background px-3 py-2" value={form.data.rate_id} onChange={(e) => form.setData('rate_id', e.target.value)}>
                            <option value="">Select rate</option>
                            {rates.map((rate) => <option key={rate.rate_id} value={rate.rate_id}>{Number(rate.rate_per_cu_m).toFixed(2)} / m³ (from {rate.effective_from}{rate.effective_to ? ` to ${rate.effective_to}` : ''})</option>)}
                        </select>
                        {form.errors.rate_id && <span className="text-destructive">{form.errors.rate_id}</span>}
                    </label>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="block space-y-1 text-sm"><span>Previous reading</span><input className="w-full rounded-md border bg-background px-3 py-2" min="0" step="0.01" type="number" value={form.data.previous_reading} onChange={(e) => form.setData('previous_reading', e.target.value)} />{form.errors.previous_reading && <span className="text-destructive">{form.errors.previous_reading}</span>}</label>
                        <label className="block space-y-1 text-sm"><span>Current reading</span><input className="w-full rounded-md border bg-background px-3 py-2" min="0" step="0.01" type="number" value={form.data.current_reading} onChange={(e) => form.setData('current_reading', e.target.value)} />{form.errors.current_reading && <span className="text-destructive">{form.errors.current_reading}</span>}</label>
                        <label className="block space-y-1 text-sm"><span>Billing date</span><input className="w-full rounded-md border bg-background px-3 py-2" type="date" value={form.data.billing_date} onChange={(e) => form.setData('billing_date', e.target.value)} />{form.errors.billing_date && <span className="text-destructive">{form.errors.billing_date}</span>}</label>
                        <label className="block space-y-1 text-sm"><span>Due date</span><input className="w-full rounded-md border bg-background px-3 py-2" type="date" value={form.data.due_date} onChange={(e) => form.setData('due_date', e.target.value)} />{form.errors.due_date && <span className="text-destructive">{form.errors.due_date}</span>}</label>
                    </div>
                    <p className="text-xs text-muted-foreground">Consumption and bill total are calculated by the server. Late fees are added when payment is recorded after the due date.</p>
                    {form.errors.bill && <p className="text-sm text-destructive">{form.errors.bill}</p>}
                    <div className="flex justify-end gap-2"><button className="rounded-md border px-4 py-2 text-sm" onClick={() => setDialogOpen(false)} type="button">Cancel</button><button className="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground disabled:opacity-50" disabled={form.processing} type="submit">{form.processing ? 'Saving...' : 'Save bill'}</button></div>
                </form>
            </FormDialog>
        </AppLayout>
    );
}
