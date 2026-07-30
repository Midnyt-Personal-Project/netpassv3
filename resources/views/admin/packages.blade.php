@extends('layouts.app')
@section('title', 'Manage Packages')
@section('page_title', 'Internet Plan Packages')
@section('role', 'Hotspot Business Owner')

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Package Form -->
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl h-fit">
            <h4 class="text-lg font-bold text-white mb-4"><i class="fa-solid fa-plus mr-2 text-indigo-500"></i> Add New Internet Plan</h4>
            <form action="{{ route('admin.packages.create') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Target Location</label>
                    <select name="location_id" required class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm focus:outline-none focus:border-indigo-500">
                        @foreach($locations as $loc)
                            <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Package Name</label>
                    <input type="text" name="name" placeholder="e.g. 1 Hour High Speed, 1 Day Plan" required class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Price (GHS)</label>
                    <input type="number" step="0.01" name="price" placeholder="e.g. 5.00" required class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Access duration</label>
                    <div class="grid grid-cols-2 gap-3">
                        <input type="number" name="duration_value" min="1" max="999" value="{{ old('duration_value', 1) }}" required aria-label="Duration number" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm focus:outline-none focus:border-indigo-500">
                        <select name="duration_unit" required aria-label="Duration unit" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm focus:outline-none focus:border-indigo-500">
                            <option value="minutes" @selected(old('duration_unit') === 'minutes')>Minutes</option>
                            <option value="hours" @selected(old('duration_unit', 'hours') === 'hours')>Hours</option>
                            <option value="days" @selected(old('duration_unit') === 'days')>Days</option>
                            <option value="months" @selected(old('duration_unit') === 'months')>Months</option>
                        </select>
                    </div>
                    <p class="mt-1 text-[10px] text-slate-500">For example: enter 1 and select Hours, Days, or Months. One month is calculated as 30 days.</p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Upload Speed <span class="text-slate-600">(optional)</span></label>
                        <input type="text" name="speed_limit_up" placeholder="e.g. 2M" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm focus:outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Download Speed <span class="text-slate-600">(optional)</span></label>
                        <input type="text" name="speed_limit_down" placeholder="e.g. 5M" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm focus:outline-none focus:border-indigo-500">
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Data Cap (MB, Optional)</label>
                    <input type="number" name="data_limit_mb" placeholder="e.g. 1024 for 1GB (Empty for Unlimited)" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Shared Users (max users)</label>
                    <input type="number" name="share_users" min="1" max="100" value="{{ old('share_users', 1) }}" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm focus:outline-none focus:border-indigo-500">
                </div>
                <button class="w-full bg-indigo-600 hover:bg-indigo-500 py-2.5 rounded-lg text-white font-bold text-sm shadow-lg transition">Create Plan</button>
            </form>
        </div>

        <!-- Package List -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl lg:col-span-2">
            <h4 class="text-lg font-bold text-white mb-4"><i class="fa-solid fa-cubes mr-2 text-indigo-500"></i> Active Internet Packages</h4>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-800 text-slate-400 text-xs uppercase font-semibold">
                            <th class="pb-3">Plan Name</th>
                            <th class="pb-3">Location</th>
                            <th class="pb-3">Price</th>
                            <th class="pb-3">Duration</th>
                            <th class="pb-3">Speed limits</th>
                            <th class="pb-3">Data Cap</th>
                            <th class="pb-3">Shared Users</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50 text-sm">
                        @forelse($packages as $pkg)
                            <tr>
                                <td class="py-3 text-white font-bold">{{ $pkg->name }}</td>
                                <td class="py-3 text-slate-300">{{ $pkg->location->name }}</td>
                                <td class="py-3 text-emerald-400 font-semibold">{{ number_format($pkg->price, 2) }} GHS</td>
                                <td class="py-3 text-slate-300">
                                    @if($pkg->duration_minutes % 1440 === 0)
                                        {{ $pkg->duration_minutes / 1440 }} {{ $pkg->duration_minutes === 1440 ? 'day' : 'days' }}
                                    @elseif($pkg->duration_minutes % 60 === 0)
                                        {{ $pkg->duration_minutes / 60 }} {{ $pkg->duration_minutes === 60 ? 'hour' : 'hours' }}
                                    @else
                                        {{ $pkg->duration_minutes }} minutes
                                    @endif
                                </td>
                                <td class="py-3 font-mono text-xs text-indigo-400">
                                    {{ $pkg->speed_limit_up ?: 'Unlimited' }} / {{ $pkg->speed_limit_down ?: 'Unlimited' }} (U/D)
                                </td>
                                <td class="py-3 text-slate-400">
                                    {{ $pkg->data_limit_mb ? number_format($pkg->data_limit_mb) . ' MB' : 'Unlimited Data' }}
                                </td>
                                <td class="py-3 text-slate-300">{{ $pkg->share_users ?? 1 }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-6 text-center text-slate-500 text-sm">No internet plans defined. Add your first plan on the left!</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
<form method="GET" class="mb-2"><input name="q" placeholder="Search..." value="{{ request("q") }}" class="bg-slate-800 border border-slate-700 rounded p-2 text-white"></form>
