@extends('layouts.app')
@section('title', 'My Account')
@section('page_title', 'My Account Settings')
@section('role', auth()->user()->isSuperAdmin() ? 'Super Administrator' : 'Hotspot Business Owner')

@section('content')
<div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
    <!-- Profile -->
    <section class="bg-slate-900 border border-slate-800 rounded-2xl p-5 sm:p-6 shadow-xl">
        <h2 class="text-lg font-bold text-white"><i class="fa-solid fa-user-gear text-indigo-400 mr-2"></i>My profile</h2>
        <p class="mt-1 text-xs text-slate-400">Update your own name, email, phone number, and password.</p>
        <form action="{{ route('admin.settings.update') }}" method="POST" class="mt-6 space-y-4">
            @csrf
            <div>
                <label class="block text-xs text-slate-400 mb-1">Full name</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm focus:outline-none focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">Email address</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" required class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm focus:outline-none focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-xs text-slate-400 mb-1">Phone number <span class="text-slate-600">(optional)</span></label>
                <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="e.g. 0244123456" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm focus:outline-none focus:border-indigo-500">
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">New password <span class="text-slate-600">(leave blank to keep)</span></label>
                    <input type="password" name="password" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Confirm new password</label>
                    <input type="password" name="password_confirmation" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm focus:outline-none focus:border-indigo-500">
                </div>
            </div>
            <button class="w-full bg-indigo-600 hover:bg-indigo-500 py-2.5 rounded-lg text-white font-bold text-sm shadow-lg transition">Save profile</button>
        </form>
    </section>

    <!-- Paystack accounts (super admin only) -->
    <section class="xl:col-span-2 bg-slate-900 border border-slate-800 rounded-2xl p-5 sm:p-6 shadow-xl">
        <h2 class="text-lg font-bold text-white"><i class="fa-solid fa-money-bill-transfer text-indigo-400 mr-2"></i>Paystack payout accounts</h2>

        @if(auth()->user()->isSuperAdmin())
            <p class="mt-1 text-xs text-slate-400">The Paystack subaccount where each location's customer payments are settled. Update them whenever the Paystack details change.</p>

            <div class="mt-6 space-y-4">
                @forelse($locations as $location)
                    <div class="rounded-xl border border-slate-800 bg-slate-800/40 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="font-bold text-white">{{ $location->name }}</p>
                                <p class="text-xs text-slate-500">Owner: {{ $location->admin?->name }} ({{ $location->admin?->email }})</p>
                            </div>
                            <span class="rounded-full bg-slate-700/40 px-2 py-1 text-[10px] text-slate-400">Commission {{ $location->commission_percentage }}%</span>
                        </div>
                        <form action="{{ route('admin.settings.paystack', $location) }}" method="POST" class="mt-3 flex flex-wrap items-center gap-2">
                            @csrf
                            <input type="text" name="paystack_subaccount" value="{{ old('paystack_subaccount', $location->paystack_subaccount) }}" placeholder="ACCT_xxxxxxxxxx" class="flex-1 min-w-52 bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm font-mono focus:outline-none focus:border-indigo-500">
                            <button class="rounded-lg bg-indigo-600 hover:bg-indigo-500 px-4 py-2.5 text-xs text-white font-bold">Save account</button>
                        </form>
                    </div>
                @empty
                    <div class="py-12 text-center text-slate-500 text-sm">No locations have been created yet.</div>
                @endforelse
            </div>
        @else
            <div class="mt-4 rounded-xl border border-slate-800 bg-slate-800/40 p-5 text-sm text-slate-400">
                <i class="fa-solid fa-lock text-amber-400 mr-2"></i>
                Paystack payout accounts are managed by the super admin only.
                Contact support if your payout account details need to change.
            </div>
        @endif
    </section>
</div>
@endsection
