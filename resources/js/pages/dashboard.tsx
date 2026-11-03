
import React from "react";
import { Link } from '@inertiajs/react';
import {
    LayoutDashboard,
    Users,
    Gauge,
    Receipt,
    FileText,
    Settings,
    Droplets,
    ShieldCheck,
} from "lucide-react";

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

export default function Dashboard({
    stats,
    bills,
}: {
    stats: DashboardStats;
    bills: RecentBill[];
}) {
    const statCards = [
        { title: 'Total Consumers', value: Number(stats?.consumers ?? 0).toLocaleString() },
        { title: 'Water Meters', value: Number(stats?.totalMeters ?? 0).toLocaleString() },
        { title: 'Total Bills', value: Number(stats?.totalBills ?? 0).toLocaleString() },
        { title: 'Unpaid Bills', value: Number(stats?.unpaidBills ?? 0).toLocaleString() },
    ];

    const recentBills = Array.isArray(bills) ? bills : [];

    return (
        <div className="flex min-h-screen bg-gray-100 text-gray-800">

            {/* Sidebar */}
            <aside className="hidden w-60 bg-slate-900 p-5 text-white md:block">
                <div className="mb-10 flex items-center gap-3">
                    <Droplets className="text-red-700" size={28} />
                    <h1 className="text-xl font-bold">WET wiw</h1>
                </div>

                <nav className="space-y-3">
                    <Link href={route('dashboard')} className="flex items-center gap-3 rounded-lg bg-cyan-600 p-3">
                        <LayoutDashboard size={19} /> Dashboard
                    </Link>
                    <Link href={route('consumers.index')} className="flex items-center gap-3 p-3 text-gray-300 hover:text-white">
                        <Users size={19} /> Consumers
                    </Link>
                    <Link href={route('water-meters.index')} className="flex items-center gap-3 p-3 text-gray-300 hover:text-white">
                        <Gauge size={19} /> Water Meters
                    </Link>
                    <Link href={route('water-bills.index')} className="flex items-center gap-3 p-3 text-gray-300 hover:text-white">
                        <Receipt size={19} /> Water Bills
                    </Link>
                    <Link href={route('water-rates.index')} className="flex items-center gap-3 p-3 text-gray-300 hover:text-white">
                        <FileText size={19} /> Water Rates
                    </Link>
                    <Link href={route('payments.index')} className="flex items-center gap-3 p-3 text-gray-300 hover:text-white">
                        <Settings size={19} /> Payments
                    </Link>
                </nav>
            </aside>

            {/* Main Content */}
            <main className="flex-1 p-6 md:p-8">

                {/* Header */}
                <div className="mb-8 flex items-center justify-between">
                    <div>
                        <h2 className="text-2xl font-bold">Dashboard</h2>
                        <p className="mt-1 text-sm text-gray-500">
                            Municipal Water Billing & Meter Reading System
                        </p>
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
                                    const statusClass = bill.is_overdue || bill.status === 'unpaid'
                                        ? 'bg-red-100 text-red-700'
                                        : 'bg-green-100 text-green-700';

                                    return (
                                        <tr key={bill.bill_id} className="border-b hover:bg-gray-50">
                                            <td className="p-3">WB-{String(bill.bill_id).padStart(3, '0')}</td>
                                            <td className="p-3">{bill.consumer_name}</td>
                                            <td className="p-3">{Number(bill.consumption_cu_m).toFixed(2)} m³</td>
                                            <td className="p-3 font-medium">₱{Number(bill.total_water_bill).toLocaleString()}</td>
                                            <td className="p-3">
                                                <span className={`rounded-full px-3 py-1 text-xs ${statusClass}`}>
                                                    {statusLabel}
                                                </span>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Footer */}
                <footer className="mt-8 text-center text-xs text-gray-400">
                    © 2026 AquaFlow | Municipal Water Billing System
                </footer>

            </main>
        </div>
    );
}
