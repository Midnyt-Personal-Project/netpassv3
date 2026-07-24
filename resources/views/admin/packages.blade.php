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
                    <label class="block text-xs text-slate-400 mb-1">Duration (Minutes)</label>
                    <input type="number" name="duration_minutes" placeholder="e.g. 60 (for 1hr), 1440 (for 1 day)" required class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm focus:outline-none focus:border-indigo-500">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Upload Speed</label>
                        <input type="text" name="speed_limit_up" placeholder="e.g. 2M" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm focus:outline-none focus:border-indigo-500">
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Download Speed</label>
                        <input type="text" name="speed_limit_down" placeholder="e.g. 5M" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm focus:outline-none focus:border-indigo-500">
                    </div>
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Data Cap (MB, Optional)</label>
                    <input type="number" name="data_limit_mb" placeholder="e.g. 1024 for 1GB (Empty for Unlimited)" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm focus:outline-none focus:border-indigo-500">
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
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50 text-sm">
                        @forelse($packages as $pkg)
                            <tr>
                                <td class="py-3 text-white font-bold">{{ $pkg->name }}</td>
                                <td class="py-3 text-slate-300">{{ $pkg->location->name }}</td>
                                <td class="py-3 text-emerald-400 font-semibold">{{ number_format($pkg->price, 2) }} GHS</td>
                                <td class="py-3 text-slate-300">
                                    @if($pkg->duration_minutes >= 1440)
                                        {{ round($pkg->duration_minutes / 1440, 1) }} Day(s)
                                    @else
                                        {{ $pkg->duration_minutes }} Min(s)
                                    @endif
                                </td>
                                <td class="py-3 font-mono text-xs text-indigo-400">
                                    {{ $pkg->speed_limit_up ?: 'Unlimited' }} / {{ $pkg->speed_limit_down ?: 'Unlimited' }} (U/D)
                                </td>
                                <td class="py-3 text-slate-400">
                                    {{ $pkg->data_limit_mb ? number_format($pkg->data_limit_mb) . ' MB' : 'Unlimited Data' }}
                                </td>
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
