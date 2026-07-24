@extends('layouts.app')
@section('title', 'Subscriptions')
@section('page_title', 'Create & Renew Subscriptions')
@section('role', auth()->user()->isSuperAdmin() ? 'Super Administrator — all locations' : 'Hotspot Business Owner')

@section('content')
<div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
    <section class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
        <div class="mb-6">
            <h3 class="text-lg font-bold text-white"><i class="fa-solid fa-user-plus text-indigo-400 mr-2"></i>Grant access</h3>
            <p class="text-xs text-slate-400 mt-1">Create a customer or renew an existing customer by phone number. Router access and credentials are synced automatically.</p>
        </div>
        @if($locations->isEmpty() || $packages->isEmpty())
            <p class="text-sm text-amber-300">Create a location and at least one package before granting a subscription.</p>
        @else
        <form method="POST" action="{{ route('admin.subscriptions.create') }}" class="space-y-4">
            @csrf
            <div><label class="block text-xs text-slate-400 mb-1">Location</label><select name="location_id" required class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm">@foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></div>
            <div><label class="block text-xs text-slate-400 mb-1">Internet package</label><select name="package_id" required class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm">@foreach($packages as $package)<option value="{{ $package->id }}" data-location="{{ $package->location_id }}">{{ $package->location->name }} — {{ $package->name }} ({{ number_format($package->price, 2) }} GHS)</option>@endforeach</select></div>
            <div><label class="block text-xs text-slate-400 mb-1">Customer phone number</label><input name="phone_number" required value="{{ old('phone_number') }}" placeholder="e.g. 0244123456" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm"></div>
            <div><label class="block text-xs text-slate-400 mb-1">Username <span class="text-slate-600">(new customer only, optional)</span></label><input name="username" value="{{ old('username') }}" placeholder="Auto-generated when empty" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm"></div>
            <details class="rounded-xl border border-slate-700 bg-slate-800/50 p-3"><summary class="cursor-pointer text-sm font-semibold text-indigo-300"><i class="fa-solid fa-tv mr-1"></i>Add a smart device now <span class="text-slate-500 font-normal">(optional)</span></summary><p class="mt-2 text-xs text-slate-400">The device will be registered to the customer and granted access when the router syncs.</p><div class="mt-3 space-y-3"><div><label class="block text-xs text-slate-400 mb-1">Device name</label><input name="device_name" value="{{ old('device_name') }}" maxlength="50" placeholder="e.g. Living Room TV" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm"></div><div><label class="block text-xs text-slate-400 mb-1">MAC address</label><input name="mac_address" value="{{ old('mac_address') }}" placeholder="AA:BB:CC:DD:EE:FF" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm font-mono"></div></div></details>
            <button class="w-full bg-indigo-600 hover:bg-indigo-500 py-3 rounded-lg text-white font-bold text-sm transition">Create subscription</button>
        </form>
        @endif
    </section>
    <section class="xl:col-span-2 bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl overflow-hidden">
        <h3 class="text-lg font-bold text-white mb-1"><i class="fa-solid fa-clock-rotate-left text-indigo-400 mr-2"></i>Recent subscriptions</h3>
        <p class="text-xs text-slate-400 mb-5">Includes online payments and manually issued subscriptions.</p>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b border-slate-800 text-slate-400 text-xs uppercase"><th class="pb-3">Customer</th><th class="pb-3">Package</th><th class="pb-3">Location</th><th class="pb-3">Access expires</th><th class="pb-3">Amount</th></tr></thead><tbody class="divide-y divide-slate-800/50">@forelse($subscriptions as $subscription)<tr><td class="py-3"><p class="font-semibold text-white">{{ $subscription->customer?->username ?? 'Pending customer' }}</p><p class="text-xs text-slate-500">{{ $subscription->customer?->phone_number }}</p></td><td class="py-3 text-slate-300">{{ $subscription->package->name }}</td><td class="py-3 text-slate-300">{{ $subscription->location->name }}</td><td class="py-3 text-slate-300">{{ $subscription->customer?->expires_at?->format('M j, Y g:i A') ?? '—' }}</td><td class="py-3 text-emerald-400">{{ number_format($subscription->amount, 2) }} GHS</td></tr>@empty<tr><td colspan="5" class="py-10 text-center text-slate-500">No completed subscriptions yet.</td></tr>@endforelse</tbody></table></div>
    </section>
</div>
@endsection

@section('scripts')
<script>
const locationSelect = document.querySelector('[name=location_id]');
const packageSelect = document.querySelector('[name=package_id]');
function filterPackages() { [...packageSelect.options].forEach(option => option.hidden = option.dataset.location !== locationSelect.value); const chosen = packageSelect.selectedOptions[0]; if (!chosen || chosen.hidden) packageSelect.value = [...packageSelect.options].find(option => !option.hidden)?.value || ''; }
locationSelect?.addEventListener('change', filterPackages); filterPackages();
</script>
@endsection
