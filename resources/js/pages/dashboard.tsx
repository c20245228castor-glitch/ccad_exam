import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';

type DashboardStats = {
    consumers: number;
    totalMeters: number;
    activeMeters: number;
    totalBills: number;
    unpaidBills: number;
    overdueBills: number;
    paidRevenue: number;
};

type RecentBill = {
    bill_id: number;
    consumer_name: string;
    meter_serial: string;
    consumption_cu_m: number;
    total_water_bill: number;
    status: string;
    is_overdue: boolean;
    due_date?: string | null;
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

export default function Dashboard({ stats, bills }: { stats: DashboardStats; bills: RecentBill[] }) {
    const statCards = [
        { title: 'Total Consumers', value: Number(stats?.consumers ?? 0).toLocaleString() },
        { title: 'Water Meters', value: Number(stats?.totalMeters ?? 0).toLocaleString() },
        { title: 'Total Bills', value: Number(stats?.totalBills ?? 0).toLocaleString() },
        { title: 'Unpaid Bills', value: Number(stats?.unpaidBills ?? 0).toLocaleString() },
    ];

    const recentBills = Array.isArray(bills) ? bills : [];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <main className="flex-1 p-6 text-black md:p-8">
                {/* Header */}
                <div className="mb-8 flex items-center justify-between">
                    <div>
                        <h2 className="text-2xl font-bold">Dashboard</h2>
                        <p className="mt-1 text-sm text-gray-500">Municipal Water Billing & Meter Reading System</p>
                    </div>

                    <div className="flex items-center gap-2 text-sm font-medium text-gray-600">
                        <ShieldCheck size={16} className="text-cyan-600" />
                        <span>Administrator</span>
                    </div>
                </div>

                {/* Statistics */}
                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    {statCards.map((stat) => (
                        <div key={stat.title} className="rounded-lg bg-white p-5 shadow-sm">
                            <p className="text-sm text-gray-500">{stat.title}</p>
                            <h3 className="mt-3 text-2xl font-bold">{stat.value}</h3>
                        </div>
                    ))}
                </div>

                {/* Billing Table */}
                <div className="mt-8 rounded-lg bg-white p-5 shadow-sm">
                    <div className="mb-5 flex items-center justify-between">
                        <h3 className="text-lg font-bold">Recent Water Bills</h3>
                        <Link href={route('water-bills.index')} className="text-sm text-cyan-600 hover:underline">
                            View All
                        </Link>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b bg-gray-50 text-gray-500">
                                <tr>
                                    <th className="p-3">Bill ID</th>
                                    <th className="p-3">Consumer Name</th>
                                    <th className="p-3">Consumption</th>
                                    <th className="p-3">Amount</th>
                                    <th className="p-3">Status</th>
                                </tr>
                            </thead>

                            <tbody>
                                {recentBills.map((bill) => {
                                    const statusLabel = bill.is_overdue ? 'Overdue' : bill.status === 'paid' ? 'Paid' : 'Unpaid';
                                    const statusClass =
                                        bill.is_overdue || bill.status === 'unpaid' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700';

                                    return (
                                        <tr key={bill.bill_id} className="border-b hover:bg-gray-50">
                                            <td className="p-3">WB-{String(bill.bill_id).padStart(3, '0')}</td>
                                            <td className="p-3">{bill.consumer_name}</td>
                                            <td className="p-3">{Number(bill.consumption_cu_m).toFixed(2)} m³</td>
                                            <td className="p-3 font-medium">₱{Number(bill.total_water_bill).toLocaleString()}</td>
                                            <td className="p-3">
                                                <span className={`rounded-full px-3 py-1 text-xs ${statusClass}`}>{statusLabel}</span>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Footer */}
                <footer className="mt-8 text-center text-xs text-gray-400">© 2026 WET wiw | Municipal Water Billing System</footer>
            </main>
        </AppLayout>
    );
}
