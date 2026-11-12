import FormDialog from '@/components/form-dialog';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { type FormEvent, useState } from 'react';

type Consumer = {
    consumer_id: number;
    account_number: string;
    consumer_name: string;
    consumer_address: string;
};

type PageLink = { url: string | null; label: string; active: boolean };
type Paginated<T> = { data: T[]; links: PageLink[] };
type ConsumerStats = { total: number; with_meters: number; without_meters: number };

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Consumers', href: '/consumers' }];

export default function ConsumerIndex({
    consumers,
    stats,
    filter,
}: {
    consumers: Paginated<Consumer>;
    stats: ConsumerStats;
    filter: { search: string };
}) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const form = useForm({
        account_number: '',
        consumer_name: '',
        consumer_address: '',
    });

    const openCreate = () => {
        setEditingId(null);
        form.reset();
        form.clearErrors();
        setDialogOpen(true);
    };

    const openEdit = (consumer: Consumer) => {
        setEditingId(consumer.consumer_id);
        form.setData({
            account_number: consumer.account_number,
            consumer_name: consumer.consumer_name,
            consumer_address: consumer.consumer_address,
        });
        form.clearErrors();
        setDialogOpen(true);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        const options = {
            onSuccess: () => {
                setDialogOpen(false);
                form.reset();
            },
        };

        if (editingId) {
            form.put(`/consumers/${editingId}`, options);
        } else {
            form.post('/consumers', options);
        }
    };

    const remove = (consumer: Consumer) => {
        if (window.confirm(`Delete ${consumer.consumer_name}?`)) {
            router.delete(`/consumers/${consumer.consumer_id}`);
        }
    };

    const summaryCards = [
        { label: 'Total consumers', value: stats.total },
        { label: 'With meters', value: stats.with_meters },
        { label: 'Without meters', value: stats.without_meters },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Consumers" />
            <main className="space-y-6 p-4 md:p-6">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Consumers</h1>
                        <p className="mt-1 text-sm text-muted-foreground">Consumer accounts and service addresses</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <form action="/consumers" method="get" className="flex gap-2">
                            <input aria-label="Search consumers" className="h-9 w-64 max-w-full rounded-md border bg-background px-3 text-sm" defaultValue={filter.search} name="search" placeholder="Search name or account" type="search" />
                            <button className="h-9 rounded-md border px-3 text-sm font-medium" type="submit">Search</button>
                        </form>
                        <button className="h-9 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground" onClick={openCreate} type="button">Add consumer</button>
                    </div>
                </header>

                <section className="grid gap-4 md:grid-cols-3">
                    {summaryCards.map((card) => (
                        <div key={card.label} className="rounded-lg border bg-card p-4 shadow-sm">
                            <p className="text-sm text-muted-foreground">{card.label}</p>
                            <h2 className="mt-3 text-3xl font-bold tracking-tight">{card.value.toLocaleString()}</h2>
                        </div>
                    ))}
                </section>

                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full min-w-[760px] text-left text-sm">
                        <thead className="border-b bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 font-medium">Account</th>
                                <th className="px-4 py-3 font-medium">Consumer</th>
                                <th className="px-4 py-3 font-medium">Address</th>
                                <th className="px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {consumers.data.map((consumer) => (
                                <tr key={consumer.consumer_id}>
                                    <td className="px-4 py-3 font-medium">{consumer.account_number}</td>
                                    <td className="px-4 py-3">{consumer.consumer_name}</td>
                                    <td className="px-4 py-3">{consumer.consumer_address}</td>
                                    <td className="space-x-2 px-4 py-3 text-right">
                                        <button className="rounded border px-2 py-1 hover:bg-muted" onClick={() => openEdit(consumer)} type="button">Edit</button>
                                        <button className="rounded border px-2 py-1 text-destructive hover:bg-muted" onClick={() => remove(consumer)} type="button">Delete</button>
                                    </td>
                                </tr>
                            ))}
                            {consumers.data.length === 0 && <tr><td className="px-4 py-8 text-center text-muted-foreground" colSpan={4}>No consumers found.</td></tr>}
                        </tbody>
                    </table>
                </div>

                <nav aria-label="Consumer pages" className="flex flex-wrap gap-1">
                    {consumers.links.map((link, index) => link.url ? (
                        <Link key={`${link.label}-${index}`} href={link.url} className={`rounded border px-3 py-1.5 text-sm ${link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'}`} dangerouslySetInnerHTML={{ __html: link.label }} />
                    ) : (
                        <span key={`${link.label}-${index}`} className="rounded border px-3 py-1.5 text-sm text-muted-foreground" dangerouslySetInnerHTML={{ __html: link.label }} />
                    ))}
                </nav>
            </main>

            <FormDialog title={editingId ? 'Edit consumer' : 'Add consumer'} open={dialogOpen} onClose={() => setDialogOpen(false)}>
                <form className="space-y-4" onSubmit={submit}>
                    <label className="block space-y-1 text-sm">
                        <span>Account number</span>
                        <input className="w-full rounded-md border bg-background px-3 py-2" value={form.data.account_number} onChange={(e) => form.setData('account_number', e.target.value)} />
                        {form.errors.account_number && <span className="text-sm text-destructive">{form.errors.account_number}</span>}
                    </label>
                    <label className="block space-y-1 text-sm">
                        <span>Consumer name</span>
                        <input className="w-full rounded-md border bg-background px-3 py-2" value={form.data.consumer_name} onChange={(e) => form.setData('consumer_name', e.target.value)} />
                        {form.errors.consumer_name && <span className="text-sm text-destructive">{form.errors.consumer_name}</span>}
                    </label>
                    <label className="block space-y-1 text-sm">
                        <span>Address</span>
                        <textarea className="w-full rounded-md border bg-background px-3 py-2" rows={3} value={form.data.consumer_address} onChange={(e) => form.setData('consumer_address', e.target.value)} />
                        {form.errors.consumer_address && <span className="text-sm text-destructive">{form.errors.consumer_address}</span>}
                    </label>
                    <div className="flex justify-end gap-2">
                        <button className="rounded-md border px-4 py-2 text-sm" onClick={() => setDialogOpen(false)} type="button">Cancel</button>
                        <button className="rounded-md bg-primary px-4 py-2 text-sm text-primary-foreground disabled:opacity-50" disabled={form.processing} type="submit">{form.processing ? 'Saving...' : 'Save consumer'}</button>
                    </div>
                </form>
            </FormDialog>
        </AppLayout>
    );
}
