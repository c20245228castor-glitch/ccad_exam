import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Consumer = {
    consumer_id: number;
    account_number: string;
    consumer_name: string;
    consumer_address: string;
    account_credit: string | number;
    user?: { email: string } | null;
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

type ConsumerStats = {
    total: number;
    with_meters: number;
    without_meters: number;
    active_meters: number;
};

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
    const [editingId, setEditingId] = useState<number | null>(null);
    const [editingAccountNumber, setEditingAccountNumber] = useState<string | null>(null);
    const [recordError, setRecordError] = useState<string>();
    const form = useForm({
        consumer_name: '',
        consumer_address: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const options = {
            onSuccess: () => {
                setEditingId(null);
                setEditingAccountNumber(null);
                setRecordError(undefined);
                form.reset();
            },
            onError: (errors: Record<string, string>) => setRecordError(errors.record),
            onFinish: () => form.reset('password', 'password_confirmation'),
        };

        if (editingId) {
            form.put(`/consumers/${editingId}`, options);
        } else {
            form.post('/consumers', options);
        }
    };

    const edit = (consumer: Consumer) => {
        form.clearErrors();
        setRecordError(undefined);
        setEditingAccountNumber(consumer.account_number);
        form.setData({
            consumer_name: consumer.consumer_name,
            consumer_address: consumer.consumer_address,
            email: '',
            password: '',
            password_confirmation: '',
        });
        setEditingId(consumer.consumer_id);
    };

    const cancelEdit = () => {
        setEditingId(null);
        setEditingAccountNumber(null);
        form.reset();
        form.clearErrors();
        setRecordError(undefined);
    };

    const summaryCards = [
        { label: 'Total consumers', value: stats?.total ?? 0 },
        { label: 'With meters', value: stats?.with_meters ?? 0 },
        { label: 'Without meters', value: stats?.without_meters ?? 0 },
        { label: 'Active meters', value: stats?.active_meters ?? 0 },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Consumers" />
            <main className="space-y-6 p-4 md:p-6">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Consumers</h1>
                        <p className="text-muted-foreground mt-1 text-sm">Consumer accounts and service addresses</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <button
                            className="bg-primary text-primary-foreground h-9 rounded-md px-3 text-sm font-medium"
                            onClick={cancelEdit}
                            type="button"
                        >
                            Add consumer
                        </button>
                        <form action="/consumers" method="get" className="flex gap-2">
                            <input
                                aria-label="Search consumers"
                                className="bg-background h-9 w-64 max-w-full rounded-md border px-3 text-sm"
                                defaultValue={filter.search}
                                name="search"
                                placeholder="Search name or account"
                                type="search"
                            />
                            <button className="h-9 rounded-md border px-3 text-sm font-medium" type="submit">
                                Search
                            </button>
                        </form>
                    </div>
                </header>

                <form onSubmit={submit} className="grid gap-3 rounded-md border p-4 md:grid-cols-3">
                    <h2 className="font-medium md:col-span-3">{editingId ? 'Edit consumer' : 'Add consumer'}</h2>
                    <p className="text-muted-foreground text-sm md:col-span-3">
                        {editingAccountNumber
                            ? `Account number: ${editingAccountNumber} (automatically assigned)`
                            : 'An account number will be assigned automatically after saving.'}
                    </p>
                    <div>
                        <label className="mb-1 block text-sm" htmlFor="consumer-name">
                            Name
                        </label>
                        <input
                            id="consumer-name"
                            className="bg-background h-9 w-full rounded-md border px-3 text-sm"
                            value={form.data.consumer_name}
                            onChange={(event) => form.setData('consumer_name', event.target.value)}
                            required
                        />
                        {form.errors.consumer_name && <p className="mt-1 text-sm text-red-600">{form.errors.consumer_name}</p>}
                    </div>
                    <div>
                        <label className="mb-1 block text-sm" htmlFor="consumer-address">
                            Address
                        </label>
                        <input
                            id="consumer-address"
                            className="bg-background h-9 w-full rounded-md border px-3 text-sm"
                            value={form.data.consumer_address}
                            onChange={(event) => form.setData('consumer_address', event.target.value)}
                            required
                        />
                        {form.errors.consumer_address && <p className="mt-1 text-sm text-red-600">{form.errors.consumer_address}</p>}
                    </div>
                    {!editingId && (
                        <>
                            <div>
                                <label className="mb-1 block text-sm" htmlFor="consumer-email">
                                    Gmail / email address
                                </label>
                                <input
                                    id="consumer-email"
                                    autoComplete="email"
                                    className="bg-background h-9 w-full rounded-md border px-3 text-sm"
                                    type="email"
                                    value={form.data.email}
                                    onChange={(event) => form.setData('email', event.target.value)}
                                    required
                                />
                                {form.errors.email && <p className="mt-1 text-sm text-red-600">{form.errors.email}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm" htmlFor="consumer-password">
                                    Login password
                                </label>
                                <input
                                    id="consumer-password"
                                    autoComplete="new-password"
                                    className="bg-background h-9 w-full rounded-md border px-3 text-sm"
                                    type="password"
                                    value={form.data.password}
                                    onChange={(event) => form.setData('password', event.target.value)}
                                    required
                                />
                                {form.errors.password && <p className="mt-1 text-sm text-red-600">{form.errors.password}</p>}
                            </div>
                            <div>
                                <label className="mb-1 block text-sm" htmlFor="consumer-password-confirmation">
                                    Confirm password
                                </label>
                                <input
                                    id="consumer-password-confirmation"
                                    autoComplete="new-password"
                                    className="bg-background h-9 w-full rounded-md border px-3 text-sm"
                                    type="password"
                                    value={form.data.password_confirmation}
                                    onChange={(event) => form.setData('password_confirmation', event.target.value)}
                                    required
                                />
                                {form.errors.password_confirmation && (
                                    <p className="mt-1 text-sm text-red-600">{form.errors.password_confirmation}</p>
                                )}
                            </div>
                        </>
                    )}
                    <div className="flex items-end gap-2">
                        <button
                            className="bg-primary text-primary-foreground h-9 rounded-md px-3 text-sm font-medium"
                            disabled={form.processing}
                            type="submit"
                        >
                            {editingId ? 'Save changes' : 'Create consumer'}
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
                    <table className="w-full min-w-[640px] text-left text-sm">
                        <thead className="bg-muted/50 text-muted-foreground border-b">
                            <tr>
                                <th className="px-4 py-3 font-medium">Account</th>
                                <th className="px-4 py-3 font-medium">Consumer</th>
                                <th className="px-4 py-3 font-medium">Login email</th>
                                <th className="px-4 py-3 text-right font-medium">Credit</th>
                                <th className="px-4 py-3 font-medium">Address</th>
                                <th className="px-4 py-3 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {consumers.data.map((consumer) => (
                                <tr key={consumer.consumer_id}>
                                    <td className="px-4 py-3 font-medium">{consumer.account_number}</td>
                                    <td className="px-4 py-3">{consumer.consumer_name}</td>
                                    <td className="px-4 py-3">{consumer.user?.email ?? 'No login account'}</td>
                                    <td className="px-4 py-3 text-right">{Number(consumer.account_credit).toFixed(2)}</td>
                                    <td className="px-4 py-3">{consumer.consumer_address}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex gap-2">
                                            <button className="rounded border px-2 py-1" onClick={() => edit(consumer)} type="button">
                                                Edit
                                            </button>
                                            <button
                                                className="rounded border px-2 py-1 text-red-600"
                                                onClick={() =>
                                                    window.confirm(`Delete ${consumer.consumer_name}?`) &&
                                                    form.delete(`/consumers/${consumer.consumer_id}`, {
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
                            {consumers.data.length === 0 && (
                                <tr>
                                    <td className="text-muted-foreground px-4 py-8 text-center" colSpan={6}>
                                        No consumers found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <nav aria-label="Consumer pages" className="flex flex-wrap gap-1">
                    {consumers.links.map((link, index) =>
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
