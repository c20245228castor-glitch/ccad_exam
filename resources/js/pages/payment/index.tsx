import FormDialog from '@/components/form-dialog';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { type FormEvent, useMemo, useState } from 'react';

type Payment = {
    payment_id: number;
    bill_id: number;
    amount_paid: string | number;
    payment_date: string;
    payment_reference: string;
    water_bill?: { total_water_bill: string | number } | null;
};
type UnpaidBill = {
    bill_id: number;
    due_date: string;
    total_water_bill: string | number;
    penalty_fee: string | number;
    water_meter?: { meter_serial: string; consumer?: { consumer_name: string } | null } | null;
};
type PageLink = { url: string | null; label: string; active: boolean };
type Paginated<T> = { data: T[]; links: PageLink[] };
type PaymentStats = { total: number; amount_paid: number; unique_bills: number; recent: number };
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Payments', href: '/payments' }];
const now = new Date();
const today = [now.getFullYear(), String(now.getMonth() + 1).padStart(2, '0'), String(now.getDate()).padStart(2, '0')].join('-');

export default function PaymentIndex({
    payments,
    stats,
    unpaidBills,
    filter,
}: {
    payments: Paginated<Payment>;
    stats: PaymentStats;
    unpaidBills: UnpaidBill[];
    filter: { search: string };
}) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const form = useForm({ bill_id: '', amount_paid: '', payment_date: today, payment_reference: '' });

    const selectedBill = useMemo(
        () => unpaidBills.find((bill) => String(bill.bill_id) === form.data.bill_id),
        [unpaidBills, form.data.bill_id],
    );

    const expectedAmount = useMemo(() => {
        if (!selectedBill) return 0;
        const baseAmount = Math.max(0, Number(selectedBill.total_water_bill) - Number(selectedBill.penalty_fee ?? 0));
        const late = form.data.payment_date > selectedBill.due_date;
        return Number((baseAmount + (late ? baseAmount * 0.05 : 0)).toFixed(2));
    }, [selectedBill, form.data.payment_date]);

    const openCreate = () => { form.reset(); form.setData('payment_date', today); form.clearErrors(); setDialogOpen(true); };
    const selectBill = (billId: string) => {
        form.setData((current) => ({ ...current, bill_id: billId }));
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post('/payments', {
            onSuccess: () => { setDialogOpen(false); form.reset(); form.setData('payment_date', today); },
        });
    };

    const summaryCards = [
        { label: 'Total payments', value: stats.total.toLocaleString() },
        { label: 'Total collected', value: Number(stats.amount_paid ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
        { label: 'Bills paid', value: stats.unique_bills.toLocaleString() },
        { label: 'Today', value: stats.recent.toLocaleString() },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payments" />
            <main className="space-y-6 p-4 md:p-6">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div><h1 className="text-2xl font-semibold">Payments</h1><p className="mt-1 text-sm text-muted-foreground">Recorded payments and their associated bills</p></div>
                    <div className="flex flex-wrap gap-2">
                        <form action="/payments" method="get" className="flex gap-2">
                            <input aria-label="Search payments" className="h-9 w-64 max-w-full rounded-md border bg-background px-3 text-sm" defaultValue={filter.search} name="search" placeholder="Search payment reference" type="search" />
                            <button className="h-9 rounded-md border px-3 text-sm font-medium" type="submit">Search</button>
                        </form>
                        <button className="h-9 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground" onClick={openCreate} type="button" disabled={unpaidBills.length === 0}>Record payment</button>
                    </div>
                </header>
                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {summaryCards.map((card) => <div key={card.label} className="rounded-lg border bg-card p-4 shadow-sm"><p className="text-sm text-muted-foreground">{card.label}</p><h2 className="mt-3 text-3xl font-bold tracking-tight">{card.value}</h2></div>)}
                </section>
                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full min-w-[760px] text-left text-sm">
                        <thead className="border-b bg-muted/50 text-muted-foreground"><tr><th className="px-4 py-3 font-medium">Reference</th><th className="px-4 py-3 font-medium">Bill</th><th className="px-4 py-3 font-medium">Payment date</th><th className="px-4 py-3 text-right font-medium">Amount paid</th><th className="px-4 py-3 text-right font-medium">Bill total</th></tr></thead>
                        <tbody className="divide-y">
                            {payments.data.map((payment) => <tr key={payment.payment_id}><td className="px-4 py-3 font-medium">{payment.payment_reference}</td><td className="px-4 py-3">#{payment.bill_id}</td><td className="px-4 py-3">{payment.payment_date.slice(0, 10)}</td><td className="px-4 py-3 text-right">{Number(payment.amount_paid).toFixed(2)}</td><td className="px-4 py-3 text-right">{payment.water_bill ? Number(payment.water_bill.total_water_bill).toFixed(2) : '—'}</td></tr>)}
                            {payments.data.length === 0 && <tr><td className="px-4 py-8 text-center text-muted-foreground" colSpan={5}>No payments found.</td></tr>}
                        </tbody>
                    </table>
                </div>
                <nav aria-label="Payment pages" className="flex flex-wrap gap-1">
                    {payments.links.map((link, index) => link.url ? <Link key={`${link.label}-${index}`} href={link.url} className={`rounded border px-3 py-1.5 text-sm ${link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'}`} dangerouslySetInnerHTML={{ __html: link.label }} /> : <span key={`${link.label}-${index}`} className="rounded border px-3 py-1.5 text-sm text-muted-foreground" dangerouslySetInnerHTML={{ __html: link.label }} />)}
                </nav>
            </main>

            <FormDialog title="Record payment" open={dialogOpen} onClose={() => setDialogOpen(false)}>
                <form className="space-y-4" onSubmit={submit}>
                    <label className="block space-y-1 text-sm"><span>Unpaid bill</span>
                        <select className="w-full rounded-md border bg-background px-3 py-2" value={form.data.bill_id} onChange={(e) => selectBill(e.target.value)}>
                            <option value="">Select a bill</option>
                            {unpaidBills.map((bill) => <option key={bill.bill_id} value={bill.bill_id}>Bill #{bill.bill_id} — {bill.water_meter?.consumer?.consumer_name ?? 'Consumer'} — Due {bill.due_date}</option>)}
                        </select>
                        {form.errors.bill_id && <span className="text-destructive">{form.errors.bill_id}</span>}
                    </label>
                    <label className="block space-y-1 text-sm"><span>Payment date</span><input className="w-full rounded-md border bg-background px-3 py-2" max={today} type="date" value={form.data.payment_date} onChange={(e) => form.setData('payment_date', e.target.value)} />{form.errors.payment_date && <span className="text-destructive">{form.errors.payment_date}</span>}</label>
                    {selectedBill && <div className="rounded-md border bg-muted/30 p-3 text-sm"><div className="flex justify-between"><span>Base bill amount</span><strong>{(Number(selectedBill.total_water_bill) - Number(selectedBill.penalty_fee ?? 0)).toFixed(2)}</strong></div><div className="mt-1 flex justify-between"><span>Late fee (5%)</span><strong>{(expectedAmount - (Number(selectedBill.total_water_bill) - Number(selectedBill.penalty_fee ?? 0))).toFixed(2)}</strong></div><div className="mt-2 flex justify-between border-t pt-2"><span>Exact amount due</span><strong>{expectedAmount.toFixed(2)}</strong></div></div>}
                    <label className="block space-y-1 text-sm"><span>Amount paid</span><input className="w-full rounded-md border bg-background px-3 py-2" min="0.01" step="0.01" type="number" value={form.data.amount_paid} onChange={(e) => form.setData('amount_paid', e.target.value)} />{form.errors.amount_paid && <span className="text-destructive">{form.errors.amount_paid}</span>}</label>
                    <label className="block space-y-1 text-sm"><span>Payment reference</span><input className="w-full rounded-md border bg-background px-3 py-2" value={form.data.payment_reference} onChange={(e) => form.setData('payment_reference', e.target.value)} />{form.errors.payment_reference && <span className="text-destructive">{form.errors.payment_reference}</span>}</label>
                    <p className="text-xs text-muted-foreground">This system accepts exact full payment only. If payment is made after the due date, a 5% late fee is added.</p>
                    <div className="flex justify-end gap-2"><button className="rounded-md border px-4 py-2 text-sm" onClick={() => setDialogOpen(false)} type="button">Cancel</button><button className="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground disabled:opacity-50" disabled={form.processing || !selectedBill} type="submit">{form.processing ? 'Recording...' : 'Record payment'}</button></div>
                </form>
            </FormDialog>
        </AppLayout>
    );
}
