import AppLayout from '@/layouts/app-layout';
import { formatAppDate } from '@/lib/utils';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';

type Consumer = {
    name: string;
    account_number: string;
    address: string;
    account_credit: number;
};

type Bill = {
    bill_id: number;
    meter_serial: string | null;
    billing_date: string | null;
    due_date: string | null;
    consumption_cu_m: number;
    total_water_bill: number;
    current_penalty_fee: number;
    credit_applied: number;
    amount_due: number;
    status: 'paid' | 'unpaid';
    is_overdue: boolean;
    payments_exists: boolean;
};

type Props = {
    consumer: Consumer | null;
    bills: Bill[];
    stats: { total: number; unpaid: number; paid: number };
    paymentMethods: Record<string, string>;
    flash?: { success?: string | null };
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'My dashboard', href: '/dashboard' }];

export default function ConsumerDashboard({ consumer, bills, stats, paymentMethods }: Props) {
    const [payingBillId, setPayingBillId] = useState<number | null>(null);
    const paymentForm = useForm({
        amount_paid: '',
        payment_method: 'cash',
    });
    const { flash } = usePage<Props>().props;

    const startPayment = (bill: Bill) => {
        paymentForm.clearErrors();
        paymentForm.setData({
            amount_paid: bill.amount_due.toFixed(2),
            payment_method: 'cash',
        });
        setPayingBillId(bill.bill_id);
    };

    const cancelPayment = () => {
        setPayingBillId(null);
        paymentForm.reset();
        paymentForm.clearErrors();
    };

    const submitPayment = (bill: Bill) => {
        paymentForm.post(route('consumer.bills.pay', bill.bill_id), {
            preserveScroll: true,
            onSuccess: () => {
                setPayingBillId(null);
                paymentForm.reset();
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My water account" />
            <main className="space-y-6 p-4 md:p-6">
                <header>
                    <h1 className="text-2xl font-semibold">My water account</h1>
                    <p className="text-muted-foreground mt-1 text-sm">
                        Choose a payment amount and method. Payments are recorded for demonstration; no money is processed.
                    </p>
                </header>

                {flash?.success && <p className="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-800">{flash.success}</p>}

                {!consumer ? (
                    <section className="rounded-md border p-5">
                        <h2 className="font-medium">No consumer account is linked</h2>
                        <p className="text-muted-foreground mt-1 text-sm">
                            Contact the administrator to link your login to a water account.
                        </p>
                    </section>
                ) : (
                    <>
                        <section className="grid gap-4 md:grid-cols-4">
                            <div className="rounded-lg border p-4">
                                <p className="text-muted-foreground text-sm">Account</p>
                                <p className="mt-2 text-xl font-semibold">{consumer.account_number}</p>
                                <p className="mt-1 text-sm">{consumer.name}</p>
                            </div>
                            <div className="rounded-lg border p-4">
                                <p className="text-muted-foreground text-sm">Unpaid bills</p>
                                <p className="mt-2 text-2xl font-semibold">{stats.unpaid}</p>
                            </div>
                            <div className="rounded-lg border p-4">
                                <p className="text-muted-foreground text-sm">Paid bills</p>
                                <p className="mt-2 text-2xl font-semibold">{stats.paid}</p>
                            </div>
                            <div className="rounded-lg border p-4">
                                <p className="text-muted-foreground text-sm">Account credit</p>
                                <p className="mt-2 text-2xl font-semibold">₱{consumer.account_credit.toFixed(2)}</p>
                            </div>
                        </section>

                        <section className="rounded-md border">
                            <div className="border-b p-4">
                                <h2 className="font-semibold">My bills</h2>
                                <p className="text-muted-foreground mt-1 text-sm">{consumer.address}</p>
                            </div>
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[760px] text-left text-sm">
                                    <thead className="bg-muted/50 text-muted-foreground border-b">
                                        <tr>
                                            <th className="px-4 py-3 font-medium">Bill</th>
                                            <th className="px-4 py-3 font-medium">Meter</th>
                                            <th className="px-4 py-3 font-medium">Billing date</th>
                                            <th className="px-4 py-3 font-medium">Due date</th>
                                            <th className="px-4 py-3 text-right font-medium">Amount (₱)</th>
                                            <th className="px-4 py-3 font-medium">Status</th>
                                            <th className="px-4 py-3 font-medium">Payment</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {bills.map((bill) => (
                                            <tr key={bill.bill_id}>
                                                <td className="px-4 py-3">#{bill.bill_id}</td>
                                                <td className="px-4 py-3">{bill.meter_serial ?? '—'}</td>
                                                <td className="px-4 py-3">{formatAppDate(bill.billing_date)}</td>
                                                <td className="px-4 py-3">{formatAppDate(bill.due_date)}</td>
                                                <td className="px-4 py-3 text-right">
                                                    <div>{bill.amount_due.toFixed(2)}</div>
                                                    {bill.current_penalty_fee > 0 && (
                                                        <div className="text-red-600 text-xs">
                                                            Late penalty: ₱{bill.current_penalty_fee.toFixed(2)}
                                                        </div>
                                                    )}
                                                    {bill.credit_applied > 0 && (
                                                        <div className="text-muted-foreground text-xs">
                                                            Credit applied: {bill.credit_applied.toFixed(2)}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {bill.is_overdue ? 'Overdue' : bill.status === 'paid' ? 'Paid' : 'Unpaid'}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {bill.status === 'unpaid' ? (
                                                        payingBillId === bill.bill_id ? (
                                                            <div className="min-w-52 space-y-2">
                                                                <div>
                                                                    <label className="mb-1 block text-xs" htmlFor={`payment-amount-${bill.bill_id}`}>
                                                                        Amount to pay (₱)
                                                                    </label>
                                                                    <input
                                                                        id={`payment-amount-${bill.bill_id}`}
                                                                        className="bg-background h-9 w-full rounded-md border px-3 text-sm"
                                                                        min="0.01"
                                                                        step="0.01"
                                                                        type="number"
                                                                        value={paymentForm.data.amount_paid}
                                                                        onChange={(event) => paymentForm.setData('amount_paid', event.target.value)}
                                                                    />
                                                                    {paymentForm.errors.amount_paid && (
                                                                        <p className="mt-1 text-xs text-red-600">{paymentForm.errors.amount_paid}</p>
                                                                    )}
                                                                    {Number(paymentForm.data.amount_paid) > 0
                                                                        && Number(paymentForm.data.amount_paid) < bill.amount_due && (
                                                                            <p className="text-muted-foreground mt-1 text-xs">
                                                                                Remaining after payment: ₱
                                                                                {(bill.amount_due - Number(paymentForm.data.amount_paid)).toFixed(2)}
                                                                            </p>
                                                                        )}
                                                                    {Number(paymentForm.data.amount_paid) > bill.amount_due && (
                                                                        <p className="text-muted-foreground mt-1 text-xs">
                                                                            Extra amount will be added to your account credit.
                                                                        </p>
                                                                    )}
                                                                </div>
                                                                <div>
                                                                    <label className="mb-1 block text-xs" htmlFor={`payment-method-${bill.bill_id}`}>
                                                                        Payment method
                                                                    </label>
                                                                    <select
                                                                        id={`payment-method-${bill.bill_id}`}
                                                                        className="bg-background h-9 w-full rounded-md border px-3 text-sm"
                                                                        value={paymentForm.data.payment_method}
                                                                        onChange={(event) => paymentForm.setData('payment_method', event.target.value)}
                                                                    >
                                                                        {Object.entries(paymentMethods).map(([value, label]) => (
                                                                            <option key={value} value={value}>{label}</option>
                                                                        ))}
                                                                    </select>
                                                                    {paymentForm.errors.payment_method && (
                                                                        <p className="mt-1 text-xs text-red-600">{paymentForm.errors.payment_method}</p>
                                                                    )}
                                                                </div>
                                                                {paymentForm.errors.bill_id && (
                                                                    <p className="text-xs text-red-600">{paymentForm.errors.bill_id}</p>
                                                                )}
                                                                <div className="flex gap-2">
                                                                    <button
                                                                        className="rounded border px-3 py-1.5 font-medium hover:bg-muted"
                                                                        disabled={paymentForm.processing}
                                                                        onClick={() => submitPayment(bill)}
                                                                        type="button"
                                                                    >
                                                                        Submit
                                                                    </button>
                                                                    <button
                                                                        className="rounded border px-3 py-1.5"
                                                                        onClick={cancelPayment}
                                                                        type="button"
                                                                    >
                                                                        Cancel
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        ) : (
                                                        <button
                                                            className="rounded border px-3 py-1.5 font-medium hover:bg-muted"
                                                            onClick={() => startPayment(bill)}
                                                            type="button"
                                                        >
                                                            Pay bill
                                                        </button>
                                                        )
                                                    ) : (
                                                        <span className="text-muted-foreground">
                                                            {bill.status === 'paid' && !bill.payments_exists
                                                                ? 'Covered by account credit'
                                                                : 'Recorded'}
                                                        </span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                        {bills.length === 0 && (
                                            <tr>
                                                <td className="text-muted-foreground px-4 py-8 text-center" colSpan={7}>
                                                    No bills are linked to your account yet.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </>
                )}
            </main>
        </AppLayout>
    );
}
