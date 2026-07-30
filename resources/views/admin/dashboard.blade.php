@extends('layouts.app')
@section('title', 'Admin Dashboard')
@section('page_title', 'Business Dashboard')
@section('role', 'Hotspot Business Owner')

@section('content')
    <section class="mb-6 rounded-2xl border border-slate-800 bg-slate-900 p-4 sm:p-5">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><div><h3 class="font-bold text-white"><i class="fa-solid fa-envelope text-indigo-400 mr-2"></i>Subscription email alerts</h3><p class="text-xs text-slate-400 mt-1">Choose whether the location owner receives an email whenever a subscription is created or renewed.</p></div><div class="space-y-2 w-full sm:w-auto">@foreach($locations as $location)<form method="POST" action="{{ route('admin.locations.subscription-notifications', $location) }}" class="flex items-center justify-between gap-3 rounded-lg bg-slate-800 px-3 py-2 text-sm">@csrf<span class="text-slate-200 truncate">{{ $location->name }}</span><label class="flex items-center gap-2 text-xs text-slate-300 cursor-pointer"><input type="hidden" name="subscription_email_notifications" value="0"><input type="checkbox" name="subscription_email_notifications" value="1" onchange="this.form.submit()" @checked($location->subscription_email_notifications) class="accent-indigo-500"> Email owner</label></form>@endforeach</div></div>
    </section>
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-6 mb-8">
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-lg">
            <p class="text-xs text-slate-400 uppercase font-bold tracking-wider mb-1">Total Packages</p>
            <h3 class="text-3xl font-extrabold text-white">{{ $stats['total_packages'] }}</h3>
        </div>
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-lg">
            <p class="text-xs text-slate-400 uppercase font-bold tracking-wider mb-1">Total Customers</p>
            <h3 class="text-3xl font-extrabold text-white">{{ $stats['total_customers'] }}</h3>
        </div>
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-lg">
            <p class="text-xs text-slate-400 uppercase font-bold tracking-wider mb-1">Active Now</p>
            <h3 class="text-3xl font-extrabold text-indigo-400">{{ $stats['active_customers'] }}</h3>
        </div>
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-lg">
            <p class="text-xs text-slate-400 uppercase font-bold tracking-wider mb-1">Registered Devices (TVs)</p>
            <h3 class="text-3xl font-extrabold text-teal-400">{{ $stats['total_devices'] }}</h3>
        </div>
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-lg">
            <p class="text-xs text-slate-400 uppercase font-bold tracking-wider mb-1">Total Sales (GHS)</p>
            <h3 class="text-3xl font-extrabold text-emerald-400">{{ number_format($stats['total_revenue'], 2) }}</h3>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Recent Payments -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <h4 class="text-lg font-bold text-white mb-4"><i class="fa-solid fa-receipt mr-2 text-indigo-500"></i> Recent Sales</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-800 text-slate-400 text-xs uppercase font-semibold">
                            <th class="pb-3">Reference</th>
                            <th class="pb-3">Package</th>
                            <th class="pb-3">Amount</th>
                            <th class="pb-3">Status</th>
                            <th class="pb-3">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50 text-sm">
                        @forelse($payments as $pay)
                            <tr>
                                <td class="py-3 font-mono text-slate-300 text-xs">{{ $pay->paystack_reference }}</td>
                                <td class="py-3 text-white">{{ $pay->package->name }}</td>
                                <td class="py-3 text-emerald-400 font-semibold">{{ number_format($pay->amount, 2) }} GHS</td>
                                <td class="py-3">
                                    <span class="inline-block px-2 py-0.5 rounded text-xs bg-emerald-500/10 text-emerald-400">Success</span>
                                </td>
                                <td class="py-3 text-slate-400 text-xs">{{ $pay->created_at->format('d M H:i') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-slate-500 text-sm">No sales processed yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Customers -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <h4 class="text-lg font-bold text-white mb-4"><i class="fa-solid fa-users mr-2 text-indigo-500"></i> Recent Active Customers</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-800 text-slate-400 text-xs uppercase font-semibold">
                            <th class="pb-3">Voucher</th>
                            <th class="pb-3">Phone</th>
                            <th class="pb-3">Package</th>
                            <th class="pb-3">Expires At</th>
                            <th class="pb-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50 text-sm">
                        @forelse($customers as $cust)
                            <tr>
                                <td class="py-3 font-mono text-indigo-400 font-semibold text-xs">{{ $cust->voucher_code ?? $cust->username }}</td>
                                <td class="py-3 text-slate-300">{{ $cust->phone_number }}</td>
                                <td class="py-3 text-white">{{ $cust->activePackage ? $cust->activePackage->name : 'N/A' }}</td>
                                <td class="py-3 text-slate-400 text-xs">{{ $cust->expires_at ? $cust->expires_at->format('d M H:i') : 'Unlimited' }}</td>
                                <td class="py-3">
                                    @if($cust->hasActiveAccess())
                                        <span class="inline-block px-2 py-0.5 rounded text-xs bg-emerald-500/10 text-emerald-400">Active</span>
                                    @else
                                        <span class="inline-block px-2 py-0.5 rounded text-xs bg-rose-500/10 text-rose-400">{{ ucfirst($cust->status === 'active' ? 'expired' : $cust->status) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-slate-500 text-sm">No customers online yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
