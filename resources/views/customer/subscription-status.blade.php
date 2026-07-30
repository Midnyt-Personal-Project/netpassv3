@extends('layouts.customer')
@section('title', 'Check Subscription - '.$location->name)
@section('subtitle', $location->name.' Subscription Status')

@section('content')
<div class="max-w-lg mx-auto space-y-6">
    <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5 sm:p-7 shadow-xl">
        <div class="text-center"><div class="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-300"><i class="fa-solid fa-ticket text-xl"></i></div><h2 class="mt-4 text-xl font-black text-white">Check your subscription</h2><p class="mt-2 text-sm text-slate-400">Enter the voucher you received to see your remaining access time.</p></div>
        <form method="GET" action="{{ route('customer.subscription-status', $location->slug) }}" class="mt-6 space-y-3"><label class="block text-xs font-bold uppercase tracking-wide text-slate-400">Voucher code</label><input name="voucher" required value="{{ request('voucher') }}" placeholder="e.g. OY-AB12CD34" autocomplete="off" class="w-full rounded-xl border border-slate-700 bg-slate-800 p-3 text-center font-mono text-base font-bold uppercase text-white focus:border-indigo-500 focus:outline-none"><button class="w-full rounded-xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white hover:bg-indigo-500">Check subscription</button></form>
    </section>
    @if($customer)
        @php
            $active = $customer->hasActiveAccess();
            $displayStatus = $active ? 'Active' : ($customer->status === 'suspended' ? 'Suspended' : 'Expired');
        @endphp
        <section @class(['rounded-2xl border p-5 sm:p-6 shadow-xl text-center','border-emerald-500/30 bg-emerald-500/10'=>$active,'border-rose-500/30 bg-rose-500/10'=>!$active])>
            <i @class(['fa-solid text-3xl','fa-circle-check text-emerald-300'=>$active,'fa-circle-xmark text-rose-300'=>!$active])></i>
            <h3 class="mt-3 text-xl font-black text-white">
                {{ $active ? 'Your access is active' : ($displayStatus === 'Suspended' ? 'Your access is suspended' : 'Your access has expired') }}
            </h3>
            <p class="mt-2 text-xs font-bold uppercase tracking-wider {{ $active ? 'text-emerald-300' : 'text-rose-300' }}">Status: {{ $displayStatus }}</p>
            <p class="mt-3 text-sm text-slate-200">Voucher: <span class="font-mono font-bold">{{ $customer->voucher_code }}</span></p>
            @if($customer->activePackage)<p class="mt-1 text-sm text-slate-300">Plan: {{ $customer->activePackage->name }}</p>@endif
            <p class="mt-4 text-sm text-slate-300">@if($active)Time remaining: <strong class="text-white">{{ $customer->expires_at->diffForHumans(now(), true) }}</strong><br>@endif Expires: <strong class="text-white">{{ $customer->expires_at?->format('d M Y, H:i') ?? 'Not available' }}</strong></p>
            @unless($active)<a href="{{ route('customer.portal', $location->slug) }}" class="mt-5 inline-block rounded-lg bg-indigo-600 px-4 py-2 text-sm font-bold text-white">Buy a new voucher</a>@endunless
        </section>
    @endif
</div>
@endsection
