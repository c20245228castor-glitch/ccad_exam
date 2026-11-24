import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type WaterMeter = {
    meter_id: number;
    consumer_id: number;
    meter_serial: string;
    is_active: boolean;
    consumer?: {
        consumer_name: string;
        account_number: string;
    } | null;
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

type MeterStats = {
    total: number;
    active: number;
    inactive: number;
    assigned: number;
};

type ConsumerOption = {
    consumer_id: number;
    consumer_name: string;
    account_number: string;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Water meters', href: '/water-meters' }];

export default function WaterMeterIndex({
    waterMeters,
    stats,
    filter,
    consumers,
}: {
    waterMeters: Paginated<WaterMeter>;
    stats: MeterStats;
    filter: { search: string };
    consumers: ConsumerOption[];
}) {
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editingMeterSerial, setEditingMeterSerial] = useState<string | null>(null);
    const [recordError, setRecordError] = useState<string>();
    const form = useForm({
        consumer_id: '',
        is_active: Boolean(true),
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            onSuccess: () => {
                setEditingId(null);
                setEditingMeterSerial(null);
                setRecordError(undefined);
                form.reset();
            },
            onError: (errors: Record<string, string>) => setRecordError(errors.record),
        };

        if (editingId) {
            form.put(`/water-meters/${editingId}`, options);
        } else {
            form.post('/water-meters', options);
        }
    };

    const edit = (meter: WaterMeter) => {
        form.clearErrors();
        setRecordError(undefined);
        setEditingMeterSerial(meter.meter_serial);
        form.setData({
            consumer_id: String(meter.consumer_id),
            is_active: meter.is_active,
        });
        setEditingId(meter.meter_id);
    };

    const cancelEdit = () => {
        setEditingId(null);
        setEditingMeterSerial(null);
        form.reset();
        form.clearErrors();
        setRecordError(undefined);
    };

    const summaryCards = [
        { label: 'Total meters', value: stats?.total ?? 0 },
        { label: 'Active', value: stats?.active ?? 0 },
        { label: 'Inactive', value: stats?.inactive ?? 0 },
        { label: 'Assigned', value: stats?.assigned ?? 0 },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Water meters" />
            <main className="space-y-6 p-4 md:p-6">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Water meters</h1>
                        <p className="text-muted-foreground mt-1 text-sm">Meter assignments and service status</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <button
                            className="bg-primary text-primary-foreground h-9 rounded-md px-3 text-sm font-medium"
                            onClick={cancelEdit}
                            type="button"
                        >
                            Add meter
                        </button>
                        <form action="/water-meters" method="get" className="flex gap-2">
                            <input
                                aria-label="Search water meters"
                                className="bg-background h-9 w-64 max-w-full rounded-md border px-3 text-sm"
                                defaultValue={filter.search}
                                name="search"
                                placeholder="Search serial or consumer"
                                type="search"
                            />
                            <button className="h-9 rounded-md border px-3 text-sm font-medium" type="submit">
                                Search
                            </button>
                        </form>
                    </div>
                </header>

                <form onSubmit={submit} className="grid gap-3 rounded-md border p-4 md:grid-cols-4">
                    <h2 className="font-medium md:col-span-4">{editingId ? 'Edit water meter' : 'Add water meter'}</h2>
                    <p className="text-muted-foreground text-sm md:col-span-4">
                        {editingMeterSerial
                            ? `Serial number: ${editingMeterSerial} (automatically assigned)`
                            : 'A serial number will be assigned automatically after saving.'}
                    </p>
                    <div>
                        <label className="mb-1 block text-sm" htmlFor="meter-consumer">
                            Consumer
                        </label>
                        <select
                            id="meter-consumer"
                            className="bg-background h-9 w-full rounded-md border px-3 text-sm"
                            value={form.data.consumer_id}
                            onChange={(event) => form.setData('consumer_id', event.target.value)}
                            required
                        >
                            <option value="">Select a consumer</option>
                            {consumers.map((consumer) => (
                                <option key={consumer.consumer_id} value={consumer.consumer_id}>
                                    {consumer.consumer_name} ({consumer.account_number})
                                </option>
                            ))}
                        </select>
                        {form.errors.consumer_id && <p className="mt-1 text-sm text-red-600">{form.errors.consumer_id}</p>}
                    </div>
                    <label className="flex items-center gap-2 self-end pb-2 text-sm">
                        <input checked={form.data.is_active} onChange={(event) => form.setData('is_active', event.target.checked)} type="checkbox" />
                        Active
                    </label>
                    <div className="flex items-end gap-2">
                        <button
                            className="bg-primary text-primary-foreground h-9 rounded-md px-3 text-sm font-medium"
                            disabled={form.processing}
                            type="submit"
                        >
                            {editingId ? 'Save changes' : 'Create meter'}
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
                            <h2 className="mt-3 text-3xl font-bold tracking-tight">{card.value.toLocaleString()}</h2>
                        </div>
                    ))}
                </section>

                <div className="overflow-x-auto rounded-md border">
                    <table className="w-full min-w-[640px] text-left text-sm">
                        <thead className="bg-muted/50 text-muted-foreground border-b">
                            <tr>
                                <th className="px-4 py-3 font-medium">Serial</th>
                                <th className="px-4 py-3 font-medium">Consumer</th>
                                <th className="px-4 py-3 font-medium">Account</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {waterMeters.data.map((meter) => (
                                <tr key={meter.meter_id}>
                                    <td className="px-4 py-3 font-medium">{meter.meter_serial}</td>
                                    <td className="px-4 py-3">{meter.consumer?.consumer_name ?? 'Unknown consumer'}</td>
                                    <td className="px-4 py-3">{meter.consumer?.account_number ?? '—'}</td>
                                    <td className="px-4 py-3">{meter.is_active ? 'Active' : 'Inactive'}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex gap-2">
                                            <button className="rounded border px-2 py-1" onClick={() => edit(meter)} type="button">
                                                Edit
                                            </button>
                                            <button
                                                className="rounded border px-2 py-1 text-red-600"
                                                onClick={() =>
                                                    window.confirm(`Delete meter ${meter.meter_serial}?`) &&
                                                    form.delete(`/water-meters/${meter.meter_id}`, {
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
                            {waterMeters.data.length === 0 && (
                                <tr>
                                    <td className="text-muted-foreground px-4 py-8 text-center" colSpan={5}>
                                        No water meters found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <nav aria-label="Water meter pages" className="flex flex-wrap gap-1">
                    {waterMeters.links.map((link, index) =>
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
