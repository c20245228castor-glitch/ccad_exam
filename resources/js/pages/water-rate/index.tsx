import FormDialog from '@/components/form-dialog';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

type WaterRate = { rate_id: number; rate_per_cu_m: string | number; effective_from: string; effective_to: string | null };
type PageLink = { url: string | null; label: string; active: boolean };
type Paginated<T> = { data: T[]; links: PageLink[] };
type RateStats = { total: number; active: number; expired: number; upcoming: number };
const breadcrumbs: BreadcrumbItem[] = [{ title: 'Water rates', href: '/water-rates' }];

export default function WaterRateIndex({ waterRates, stats, filter }: { waterRates: Paginated<WaterRate>; stats: RateStats; filter: { search: string } }) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const form = useForm({ rate_per_cu_m: '', effective_from: '', effective_to: '' });

    const openCreate = () => { setEditingId(null); form.reset(); form.clearErrors(); setDialogOpen(true); };
    const openEdit = (rate: WaterRate) => {
        setEditingId(rate.rate_id);
        form.setData({ rate_per_cu_m: String(rate.rate_per_cu_m), effective_from: rate.effective_from, effective_to: rate.effective_to ?? '' });
        form.clearErrors();
        setDialogOpen(true);
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = { onSuccess: () => { setDialogOpen(false); form.reset(); } };
        if (editingId) form.put(`/water-rates/${editingId}`, options);
        else form.post('/water-rates', options);
    };
    const remove = (rate: WaterRate) => {
        if (window.confirm('Delete this water rate?')) router.delete(`/water-rates/${rate.rate_id}`);
    };

    const summaryCards = [
        { label: 'Total rates', value: stats.total },
        { label: 'Active rates', value: stats.active },
        { label: 'Expired rates', value: stats.expired },
        { label: 'Upcoming rates', value: stats.upcoming },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Water rates" />
            <main className="space-y-6 p-4 md:p-6">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div><h1 className="text-2xl font-semibold">Water rates</h1><p className="mt-1 text-sm text-muted-foreground">Rates and the periods they apply to</p></div>
                    <div className="flex flex-wrap gap-2">
                        <form action="/water-rates" method="get" className="flex gap-2">
                            <input aria-label="Search water rates" className="h-9 w-64 max-w-full rounded-md border bg-background px-3 text-sm" defaultValue={filter.search} name="search" placeholder="Search rate amount" type="search" />
                            <button className="h-9 rounded-md border px-3 text-sm font-medium" type="submit">Search</button>
                        </form>
                        <button className="h-9 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground" onClick={openCreate} type="button">Add rate</button>
                    </div>
                </header>
                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    {summaryCards.map((card) => <div key={card.label} className="rounded-lg border bg-card p-4 shadow-sm"><p className="text-sm text-muted-foreground">{card.label}</p><h2 className="mt-3 text-3xl font-bold tracking-tight">{card.value.toLocaleString()}</h2></div>)}
                </section>
                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full min-w-[760px] text-left text-sm">
                        <thead className="border-b bg-muted/50 text-muted-foreground"><tr><th className="px-4 py-3 font-medium">Rate per m³</th><th className="px-4 py-3 font-medium">Effective from</th><th className="px-4 py-3 font-medium">Effective to</th><th className="px-4 py-3 text-right font-medium">Actions</th></tr></thead>
                        <tbody className="divide-y">
                            {waterRates.data.map((rate) => <tr key={rate.rate_id}><td className="px-4 py-3 font-medium">{Number(rate.rate_per_cu_m).toFixed(2)}</td><td className="px-4 py-3">{rate.effective_from}</td><td className="px-4 py-3">{rate.effective_to ?? 'No end date'}</td><td className="space-x-2 px-4 py-3 text-right"><button className="rounded border px-2 py-1 hover:bg-muted" onClick={() => openEdit(rate)} type="button">Edit</button><button className="rounded border px-2 py-1 text-destructive hover:bg-muted" onClick={() => remove(rate)} type="button">Delete</button></td></tr>)}
                            {waterRates.data.length === 0 && <tr><td className="px-4 py-8 text-center text-muted-foreground" colSpan={4}>No water rates found.</td></tr>}
                        </tbody>
                    </table>
                </div>
                <nav aria-label="Water rate pages" className="flex flex-wrap gap-1">
                    {waterRates.links.map((link, index) => link.url ? <Link key={`${link.label}-${index}`} href={link.url} className={`rounded border px-3 py-1.5 text-sm ${link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'}`} dangerouslySetInnerHTML={{ __html: link.label }} /> : <span key={`${link.label}-${index}`} className="rounded border px-3 py-1.5 text-sm text-muted-foreground" dangerouslySetInnerHTML={{ __html: link.label }} />)}
                </nav>
            </main>
            <FormDialog title={editingId ? 'Edit water rate' : 'Add water rate'} open={dialogOpen} onClose={() => setDialogOpen(false)}>
                <form className="space-y-4" onSubmit={submit}>
                    <label className="block space-y-1 text-sm"><span>Rate per cubic meter</span><input className="w-full rounded-md border bg-background px-3 py-2" min="0.01" step="0.01" type="number" value={form.data.rate_per_cu_m} onChange={(e) => form.setData('rate_per_cu_m', e.target.value)} />{form.errors.rate_per_cu_m && <span className="text-destructive">{form.errors.rate_per_cu_m}</span>}</label>
                    <label className="block space-y-1 text-sm"><span>Effective from</span><input className="w-full rounded-md border bg-background px-3 py-2" type="date" value={form.data.effective_from} onChange={(e) => form.setData('effective_from', e.target.value)} />{form.errors.effective_from && <span className="text-destructive">{form.errors.effective_from}</span>}</label>
                    <label className="block space-y-1 text-sm"><span>Effective to (optional)</span><input className="w-full rounded-md border bg-background px-3 py-2" type="date" value={form.data.effective_to} onChange={(e) => form.setData('effective_to', e.target.value)} />{form.errors.effective_to && <span className="text-destructive">{form.errors.effective_to}</span>}</label>
                    {form.errors.rate_id && <p className="text-sm text-destructive">{form.errors.rate_id}</p>}
                    <div className="flex justify-end gap-2"><button className="rounded-md border px-4 py-2 text-sm" onClick={() => setDialogOpen(false)} type="button">Cancel</button><button className="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground disabled:opacity-50" disabled={form.processing} type="submit">{form.processing ? 'Saving...' : 'Save rate'}</button></div>
                    {editingId !== null && <p className="text-xs text-muted-foreground">Rates already used by bills are locked to preserve billing history.</p>}
                </form>
            </FormDialog>
        </AppLayout>
    );
}
