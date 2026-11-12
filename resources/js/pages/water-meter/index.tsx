import FormDialog from '@/components/form-dialog';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

type ConsumerOption = { consumer_id: number; account_number: string; consumer_name: string };
type WaterMeter = {
    meter_id: number;
    meter_serial: string;
    is_active: boolean;
    consumer?: ConsumerOption | null;
};
type PageLink = { url: string | null; label: string; active: boolean };
type Paginated<T> = { data: T[]; links: PageLink[] };
type MeterStats = { total: number; active: number; inactive: number; with_bills: number };

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Water meters', href: '/water-meters' }];

export default function WaterMeterIndex({
    waterMeters,
    stats,
    consumers,
    filter,
}: {
    waterMeters: Paginated<WaterMeter>;
    stats: MeterStats;
    consumers: ConsumerOption[];
    filter: { search: string };
}) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const form = useForm({ consumer_id: '', meter_serial: '', is_active: true });

    const openCreate = () => {
        setEditingId(null);
        form.reset();
        form.setData('is_active', true);
        form.clearErrors();
        setDialogOpen(true);
    };

    const openEdit = (meter: WaterMeter) => {
        setEditingId(meter.meter_id);
        form.setData({
            consumer_id: String(meter.consumer?.consumer_id ?? ''),
            meter_serial: meter.meter_serial,
            is_active: meter.is_active,
        });
        form.clearErrors();
        setDialogOpen(true);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { onSuccess: () => { setDialogOpen(false); form.reset(); } };
        if (editingId) form.put(`/water-meters/${editingId}`, options);
        else form.post('/water-meters', options);
    };

    const remove = (meter: WaterMeter) => {
        if (window.confirm(`Delete meter ${meter.meter_serial}?`)) {
            router.delete(`/water-meters/${meter.meter_id}`);
        }
    };

    const summaryCards = [
        { label: 'Total meters', value: stats.total },
        { label: 'Active', value: stats.active },
        { label: 'Inactive', value: stats.inactive },
        { label: 'With bills', value: stats.with_bills },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Water meters" />
            <main className="space-y-6 p-4 md:p-6">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Water meters</h1>
                        <p className="mt-1 text-sm text-muted-foreground">Meter assignments and service status</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <form action="/water-meters" method="get" className="flex gap-2">
                            <input aria-label="Search water meters" className="h-9 w-64 max-w-full rounded-md border bg-background px-3 text-sm" defaultValue={filter.search} name="search" placeholder="Search serial or consumer" type="search" />
                            <button className="h-9 rounded-md border px-3 text-sm font-medium" type="submit">Search</button>
                        </form>
                        <button className="h-9 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground" onClick={openCreate} type="button">Add meter</button>
                    </div>
                </header>

                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {summaryCards.map((card) => <div key={card.label} className="rounded-lg border bg-card p-4 shadow-sm"><p className="text-sm text-muted-foreground">{card.label}</p><h2 className="mt-3 text-3xl font-bold tracking-tight">{card.value.toLocaleString()}</h2></div>)}
                </section>

                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full min-w-[760px] text-left text-sm">
                        <thead className="border-b bg-muted/50 text-muted-foreground">
                            <tr><th className="px-4 py-3 font-medium">Serial</th><th className="px-4 py-3 font-medium">Consumer</th><th className="px-4 py-3 font-medium">Account</th><th className="px-4 py-3 font-medium">Status</th><th className="px-4 py-3 text-right font-medium">Actions</th></tr>
                        </thead>
                        <tbody className="divide-y">
                            {waterMeters.data.map((meter) => (
                                <tr key={meter.meter_id}>
                                    <td className="px-4 py-3 font-medium">{meter.meter_serial}</td>
                                    <td className="px-4 py-3">{meter.consumer?.consumer_name ?? 'Unknown consumer'}</td>
                                    <td className="px-4 py-3">{meter.consumer?.account_number ?? '—'}</td>
                                    <td className="px-4 py-3">{meter.is_active ? 'Active' : 'Inactive'}</td>
                                    <td className="space-x-2 px-4 py-3 text-right">
                                        <button className="rounded border px-2 py-1 hover:bg-muted" onClick={() => openEdit(meter)} type="button">Edit</button>
                                        <button className="rounded border px-2 py-1 text-destructive hover:bg-muted" onClick={() => remove(meter)} type="button">Delete</button>
                                    </td>
                                </tr>
                            ))}
                            {waterMeters.data.length === 0 && <tr><td className="px-4 py-8 text-center text-muted-foreground" colSpan={5}>No water meters found.</td></tr>}
                        </tbody>
                    </table>
                </div>

                <nav aria-label="Water meter pages" className="flex flex-wrap gap-1">
                    {waterMeters.links.map((link, index) => link.url ? <Link key={`${link.label}-${index}`} href={link.url} className={`rounded border px-3 py-1.5 text-sm ${link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'}`} dangerouslySetInnerHTML={{ __html: link.label }} /> : <span key={`${link.label}-${index}`} className="rounded border px-3 py-1.5 text-sm text-muted-foreground" dangerouslySetInnerHTML={{ __html: link.label }} />)}
                </nav>
            </main>

            <FormDialog title={editingId ? 'Edit water meter' : 'Add water meter'} open={dialogOpen} onClose={() => setDialogOpen(false)}>
                <form className="space-y-4" onSubmit={submit}>
                    <label className="block space-y-1 text-sm"><span>Consumer</span>
                        <select className="w-full rounded-md border bg-background px-3 py-2" value={form.data.consumer_id} onChange={(e) => form.setData('consumer_id', e.target.value)}>
                            <option value="">Select a consumer</option>
                            {consumers.map((consumer) => <option key={consumer.consumer_id} value={consumer.consumer_id}>{consumer.account_number} — {consumer.consumer_name}</option>)}
                        </select>
                        {form.errors.consumer_id && <span className="text-destructive">{form.errors.consumer_id}</span>}
                    </label>
                    <label className="block space-y-1 text-sm"><span>Meter serial</span>
                        <input className="w-full rounded-md border bg-background px-3 py-2" value={form.data.meter_serial} onChange={(e) => form.setData('meter_serial', e.target.value.toUpperCase())} />
                        {form.errors.meter_serial && <span className="text-destructive">{form.errors.meter_serial}</span>}
                    </label>
                    <label className="flex items-center gap-2 text-sm"><input checked={form.data.is_active} onChange={(e) => form.setData('is_active', e.target.checked)} type="checkbox" /> Active meter</label>
                    {form.errors.is_active && <p className="text-sm text-destructive">{form.errors.is_active}</p>}
                    <div className="flex justify-end gap-2">
                        <button className="rounded-md border px-4 py-2 text-sm" onClick={() => setDialogOpen(false)} type="button">Cancel</button>
                        <button className="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground disabled:opacity-50" disabled={form.processing} type="submit">{form.processing ? 'Saving...' : 'Save meter'}</button>
                    </div>
                </form>
            </FormDialog>
        </AppLayout>
    );
}
