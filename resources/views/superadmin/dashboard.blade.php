@extends('layouts.app')
@section('title', 'Super Admin Dashboard')
@section('page_title', 'Super Admin Control Center')
@section('role', 'Super Admin')

@section('content')
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
        <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-lg"><p class="text-xs text-slate-400 uppercase font-bold tracking-wider mb-1">Gross sales (GHS)</p><h3 class="text-2xl font-extrabold text-white">{{ number_format($stats['total_sales'], 2) }}</h3></div>
        <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-lg"><p class="text-xs text-slate-400 uppercase font-bold tracking-wider mb-1">Your commission (GHS)</p><h3 class="text-2xl font-extrabold text-emerald-400">{{ number_format($stats['total_commission'], 2) }}</h3></div>
        <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-lg"><p class="text-xs text-slate-400 uppercase font-bold tracking-wider mb-1">Paystack fees (GHS)</p><h3 class="text-2xl font-extrabold text-amber-400">{{ number_format($stats['total_paystack_fees'], 2) }}</h3></div>
        <div class="bg-indigo-950/40 border border-indigo-500/30 p-5 rounded-2xl shadow-lg"><p class="text-xs text-indigo-200 uppercase font-bold tracking-wider mb-1">Total owner payout (GHS)</p><h3 class="text-2xl font-extrabold text-cyan-300">{{ number_format($stats['owner_payout_total'], 2) }}</h3><p class="mt-1 text-[10px] text-indigo-200/70">Gross − your commission − Paystack fees</p></div>
        <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-lg"><p class="text-xs text-slate-400 uppercase font-bold tracking-wider mb-1">Admins</p><h3 class="text-2xl font-extrabold text-white">{{ $stats['total_admins'] }}</h3></div>
        <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-lg"><p class="text-xs text-slate-400 uppercase font-bold tracking-wider mb-1">Locations</p><h3 class="text-2xl font-extrabold text-white">{{ $stats['total_locations'] }}</h3></div>
        <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-lg"><p class="text-xs text-slate-400 uppercase font-bold tracking-wider mb-1">Routers</p><h3 class="text-2xl font-extrabold text-indigo-400">{{ $stats['total_routers'] }}</h3></div>
    </div>

    <!-- Management Forms Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
        <!-- Create Admin -->
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl">
            <h4 class="text-lg font-bold text-white mb-4"><i class="fa-solid fa-user-plus mr-2 text-indigo-500"></i> Create Admin Account</h4>
            <form action="{{ route('superadmin.admin.create') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Full Name</label>
                    <input type="text" name="name" required class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Email Address</label>
                    <input type="email" name="email" required class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Password</label>
                    <input type="password" name="password" required class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm focus:outline-none focus:border-indigo-500">
                </div>
                <button class="w-full bg-indigo-600 hover:bg-indigo-500 py-2.5 rounded-lg text-white font-bold text-sm shadow-lg transition">Create Admin</button>
            </form>
        </div>

        <!-- Create Location -->
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl">
            <h4 class="text-lg font-bold text-white mb-4"><i class="fa-solid fa-location-dot mr-2 text-indigo-500"></i> Register Location</h4>
            <form action="{{ route('superadmin.location.create') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Location Name</label>
                    <input type="text" name="name" placeholder="e.g. East Legon" required class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Assign Admin Owner</label>
                    <select name="admin_id" required class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm focus:outline-none focus:border-indigo-500">
                        <option value="">Select Admin...</option>
                        @foreach(\App\Models\User::where('role', 'admin')->get() as $adm)
                            <option value="{{ $adm->id }}">{{ $adm->name }} ({{ $adm->email }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Platform Commission (%)</label>
                    <input type="number" step="0.1" name="commission_percentage" value="10" required class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Paystack Subaccount (Optional)</label>
                    <input type="text" name="paystack_subaccount" placeholder="ACCT_xxxxxxxx" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm focus:outline-none focus:border-indigo-500">
                </div>
                <label class="flex gap-3 items-start rounded-lg bg-slate-800/60 border border-slate-700 p-3 cursor-pointer">
                    <input type="checkbox" name="subscription_email_notifications" value="1" class="mt-0.5 accent-indigo-500">
                    <span><span class="block text-sm text-white font-semibold">Email the owner for each subscription</span><span class="block text-xs text-slate-400 mt-0.5">Sends a subscription receipt to the assigned admin owner.</span></span>
                </label>
                <button class="w-full bg-indigo-600 hover:bg-indigo-500 py-2.5 rounded-lg text-white font-bold text-sm shadow-lg transition">Create Location</button>
            </form>
        </div>

        <!-- Create Router -->
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl">
            <h4 class="text-lg font-bold text-white mb-4"><i class="fa-solid fa-network-wired mr-2 text-indigo-500"></i> Generate Router Token</h4>
            <form action="{{ route('superadmin.router.create') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Router Name</label>
                    <input type="text" name="name" placeholder="e.g. RB750 East Legon" required class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Location</label>
                    <select name="location_id" required class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm focus:outline-none focus:border-indigo-500">
                        <option value="">Select Location...</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button class="w-full bg-indigo-600 hover:bg-indigo-500 py-2.5 rounded-lg text-white font-bold text-sm shadow-lg transition">Generate Router & Token</button>
            </form>
        </div>
    </div>

    <!-- Locations & Routers Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl mb-8">
        <h4 class="text-lg font-bold text-white mb-4">Locations & Associated Routers</h4>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-800 text-slate-400 text-xs uppercase font-semibold">
                        <th class="pb-3">Location</th>
                        <th class="pb-3">Slug (Portal URL)</th>
                        <th class="pb-3">Admin Owner</th>
                        <th class="pb-3">Commission</th>
                        <th class="pb-3">Router ID</th>
                        <th class="pb-3">Router Token</th>
                        <th class="pb-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/50 text-sm">
                    @foreach($locations as $loc)
                        @php $rtr = $loc->routers->first(); @endphp
                        <tr>
                            <td class="py-3 text-white font-semibold">{{ $loc->name }}</td>
                            <td class="py-3 text-indigo-400"><a href="/h/{{ $loc->slug }}" target="_blank">/h/{{ $loc->slug }}</a></td>
                            <td class="py-3 text-slate-300">{{ $loc->admin->name }}</td>
                            <td class="py-3 text-slate-300">{{ $loc->commission_percentage }}%</td>
                            <td class="py-3 font-mono text-slate-400">{{ $rtr ? $rtr->router_id : 'No Router' }}</td>
                            <td class="py-3 font-mono text-xs text-slate-500">{{ $rtr ? substr($rtr->api_token, 0, 15) . '...' : 'N/A' }}</td>
                            <td class="py-3">
                                @if($rtr && $rtr->status === 'online')
                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-xs bg-emerald-500/10 text-emerald-400">Online</span>
                                @else
                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-xs bg-slate-800 text-slate-400">Offline</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
