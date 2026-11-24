import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type WaterRate = {
    rate_id: number;
    rate_per_cu_m: string | number;
    effective_from: string;
    effective_to: string | null;
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

type RateStats = {
    total: number;
    active: number;
    expired: number;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Water rates', href: '/water-rates' }];

export default function WaterRateIndex({
    waterRates,
    stats,
    filter,
}: {
    waterRates: Paginated<WaterRate>;
    stats: RateStats;
    filter: { search: string };
}) {
    const [editingId, setEditingId] = useState<number | null>(null);
    const [recordError, setRecordError] = useState<string>();
    const form = useForm({
        rate_per_cu_m: '',
        effective_from: '',
        effective_to: '',
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
            form.put(`/water-rates/${editingId}`, options);
        } else {
            form.post('/water-rates', options);
        }
    };

    const edit = (rate: WaterRate) => {
        form.clearErrors();
        setRecordError(undefined);
        form.setData({
            rate_per_cu_m: String(rate.rate_per_cu_m),
            effective_from: rate.effective_from,
            effective_to: rate.effective_to ?? '',
        });
        setEditingId(rate.rate_id);
    };

    const cancelEdit = () => {
        setEditingId(null);
        form.reset();
        form.clearErrors();
        setRecordError(undefined);
    };

    const summaryCards = [
        { label: 'Total rates', value: stats?.total ?? 0 },
        { label: 'Active rates', value: stats?.active ?? 0 },
        { label: 'Expired rates', value: stats?.expired ?? 0 },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Water rates" />
            <main className="space-y-6 p-4 md:p-6">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Water rates</h1>
                        <p className="text-muted-foreground mt-1 text-sm">Rates and the periods they apply to</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <button
                            className="bg-primary text-primary-foreground h-9 rounded-md px-3 text-sm font-medium"
                            onClick={cancelEdit}
                            type="button"
                        >
                            Add rate
                        </button>
                        <form action="/water-rates" method="get" className="flex gap-2">
                            <input
                                aria-label="Search water rates"
                                className="bg-background h-9 w-64 max-w-full rounded-md border px-3 text-sm"
                                defaultValue={filter.search}
                                name="search"
                                placeholder="Search rate amount"
                                type="search"
                            />
                            <button className="h-9 rounded-md border px-3 text-sm font-medium" type="submit">
                                Search
                            </button>
                        </form>
                    </div>
                </header>

                <form onSubmit={submit} className="grid gap-3 rounded-md border p-4 md:grid-cols-4">
                    <h2 className="font-medium md:col-span-4">{editingId ? 'Edit water rate' : 'Add water rate'}</h2>
                    <div>
                        <label className="mb-1 block text-sm" htmlFor="rate-amount">
                            Rate per m³
                        </label>
                        <input
                            id="rate-amount"
                            className="bg-background h-9 w-full rounded-md border px-3 text-sm"
                            min="0"
                            step="0.01"
                            type="number"
                            value={form.data.rate_per_cu_m}
                            onChange={(event) => form.setData('rate_per_cu_m', event.target.value)}
                            required
                        />
                        {form.errors.rate_per_cu_m && <p className="mt-1 text-sm text-red-600">{form.errors.rate_per_cu_m}</p>}
                    </div>
                    <div>
                        <label className="mb-1 block text-sm" htmlFor="rate-from">
                            Effective from
                        </label>
                        <input
                            id="rate-from"
                            className="bg-background h-9 w-full rounded-md border px-3 text-sm"
                            type="date"
                            value={form.data.effective_from}
                            onChange={(event) => form.setData('effective_from', event.target.value)}
                            required
                        />
                        {form.errors.effective_from && <p className="mt-1 text-sm text-red-600">{form.errors.effective_from}</p>}
                    </div>
                    <div>
                        <label className="mb-1 block text-sm" htmlFor="rate-to">
                            Effective to
                        </label>
                        <input
                            id="rate-to"
                            className="bg-background h-9 w-full rounded-md border px-3 text-sm"
                            type="date"
                            value={form.data.effective_to}
                            onChange={(event) => form.setData('effective_to', event.target.value)}
                        />
                        {form.errors.effective_to && <p className="mt-1 text-sm text-red-600">{form.errors.effective_to}</p>}
                    </div>
                    <div className="flex items-end gap-2">
                        <button
                            className="bg-primary text-primary-foreground h-9 rounded-md px-3 text-sm font-medium"
                            disabled={form.processing}
                            type="submit"
                        >
                            {editingId ? 'Save changes' : 'Create rate'}
                        </button>
                        {editingId && (
                            <button className="h-9 rounded-md border px-3 text-sm" onClick={cancelEdit} type="button">
                                Cancel
                            </button>
                        )}
                    </div>
                    {recordError && <p className="text-sm text-red-600 md:col-span-4">{recordError}</p>}
                </form>

                <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    {summaryCards.map((card) => (
                        <div key={card.label} className="bg-card rounded-lg border p-4 shadow-sm">
                            <p className="text-muted-foreground text-sm">{card.label}</p>
                            <h2 className="mt-3 text-3xl font-bold tracking-tight">{card.value.toLocaleString()}</h2>
                        </div>
                    ))}
                </section>

                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full min-w-[640px] text-left text-sm">
                        <thead className="bg-muted/50 text-muted-foreground border-b">
                            <tr>
                                <th className="px-4 py-3 font-medium">Rate per m³</th>
                                <th className="px-4 py-3 font-medium">Effective from</th>
                                <th className="px-4 py-3 font-medium">Effective to</th>
                                <th className="px-4 py-3 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {waterRates.data.map((rate) => (
                                <tr key={rate.rate_id}>
                                    <td className="px-4 py-3 font-medium">{Number(rate.rate_per_cu_m).toFixed(2)}</td>
                                    <td className="px-4 py-3">{rate.effective_from}</td>
                                    <td className="px-4 py-3">{rate.effective_to ?? 'No end date'}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex gap-2">
                                            <button className="rounded border px-2 py-1" onClick={() => edit(rate)} type="button">
                                                Edit
                                            </button>
                                            <button
                                                className="rounded border px-2 py-1 text-red-600"
                                                onClick={() =>
                                                    window.confirm('Delete this water rate?') &&
                                                    form.delete(`/water-rates/${rate.rate_id}`, {
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
                            {waterRates.data.length === 0 && (
                                <tr>
                                    <td className="text-muted-foreground px-4 py-8 text-center" colSpan={4}>
                                        No water rates found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <nav aria-label="Water rate pages" className="flex flex-wrap gap-1">
                    {waterRates.links.map((link, index) =>
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
