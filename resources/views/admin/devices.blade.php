@extends('layouts.app')
@section('title', 'Registered Devices')
@section('page_title', 'Smart Device MAC Address Management')
@section('role', 'Hotspot Business Owner')

@section('content')
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl mb-8">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h4 class="text-lg font-bold text-white"><i class="fa-solid fa-tv mr-2 text-indigo-500"></i> Registered Smart TVs, Phone, & Laptop Devices</h4>
                <p class="text-xs text-slate-400 mt-1">These devices log in automatically on the MikroTik router based on their hardware MAC address.</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-800 text-slate-400 text-xs uppercase font-semibold">
                        <th class="pb-3">Device Name</th>
                        <th class="pb-3">Hardware MAC Address</th>
                        <th class="pb-3">Customer Voucher</th>
                        <th class="pb-3">Location</th>
                        <th class="pb-3">Customer Phone</th>
                        <th class="pb-3">Status</th>
                        <th class="pb-3">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/50 text-sm">
                    @forelse($devices as $dev)
                        <tr>
                            <td class="py-3 text-white font-semibold">
                                <i class="fa-solid {{ $dev->status === 'active' ? 'fa-tv text-teal-400' : 'fa-ban text-rose-400' }} mr-2"></i>
                                {{ $dev->name }}
                            </td>
                            <td class="py-3 font-mono text-indigo-400 text-xs">{{ $dev->mac_address }}</td>
                            <td class="py-3 font-mono text-slate-400 text-xs font-semibold">{{ $dev->customer->voucher_code ?? $dev->customer->username }}</td>
                            <td class="py-3 text-slate-300">{{ $dev->customer->location->name }}</td>
                            <td class="py-3 text-slate-300">{{ $dev->customer->phone_number }}</td>
                            <td class="py-3">
                                @if($dev->status === 'active')
                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-xs bg-teal-500/10 text-teal-400">Active Access</span>
                                @else
                                    <span class="inline-block px-2.5 py-0.5 rounded-full text-xs bg-rose-500/10 text-rose-400">Blocked</span>
                                @endif
                            </td>
                            <td class="py-3">
                                <form action="{{ route('admin.devices.toggle', $dev->id) }}" method="POST" class="inline">
                                    @csrf
                                    <button class="bg-slate-800 hover:bg-slate-700 text-white font-semibold py-1 px-3 rounded text-xs border border-slate-700 transition">
                                        @if($dev->status === 'active')
                                            Block Device
                                        @else
                                            Unblock Device
                                        @endif
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-500 text-sm">No smart devices registered yet by customers.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
<form method="GET" class="mb-2"><input name="q" placeholder="Search..." value="{{ request("q") }}" class="bg-slate-800 border border-slate-700 rounded p-2 text-white"></form>
