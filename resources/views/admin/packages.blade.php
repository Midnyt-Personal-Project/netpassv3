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
                            <th class="pb-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/50 text-sm">
                        @forelse($packages as $pkg)
                            @php
                                if ($pkg->duration_minutes % 43200 === 0) {
                                    $editValue = $pkg->duration_minutes / 43200;
                                    $editUnit = 'months';
                                } elseif ($pkg->duration_minutes % 1440 === 0) {
                                    $editValue = $pkg->duration_minutes / 1440;
                                    $editUnit = 'days';
                                } elseif ($pkg->duration_minutes % 60 === 0) {
                                    $editValue = $pkg->duration_minutes / 60;
                                    $editUnit = 'hours';
                                } else {
                                    $editValue = $pkg->duration_minutes;
                                    $editUnit = 'minutes';
                                }
                            @endphp
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
                                <td class="py-3">
                                    <button onclick="toggleEditForm({{ $pkg->id }})" class="rounded-lg border border-indigo-500/40 px-3 py-1.5 text-xs text-indigo-300 hover:bg-indigo-500/10 font-semibold">
                                        <i class="fa-solid fa-pen mr-1"></i>Edit
                                    </button>
                                </td>
                            </tr>
                            <tr id="edit-row-{{ $pkg->id }}" class="hidden">
                                <td colspan="8" class="py-3">
                                    <form action="{{ route('admin.packages.update', $pkg) }}" method="POST" class="bg-slate-950/60 border border-indigo-500/30 rounded-xl p-4 space-y-3">
                                        @csrf
                                        @method('PUT')
                                        <p class="text-xs font-bold text-indigo-300 uppercase tracking-wide"><i class="fa-solid fa-pen-to-square mr-1"></i>Edit "{{ $pkg->name }}" ({{ $pkg->location->name }})</p>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                            <div>
                                                <label class="block text-xs text-slate-400 mb-1">Package Name</label>
                                                <input type="text" name="name" value="{{ old('name', $pkg->name) }}" required maxlength="100" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm">
                                            </div>
                                            <div>
                                                <label class="block text-xs text-slate-400 mb-1">Price (GHS)</label>
                                                <input type="number" step="0.01" name="price" value="{{ old('price', $pkg->price) }}" required min="0" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm">
                                            </div>
                                            <div>
                                                <label class="block text-xs text-slate-400 mb-1">Access duration</label>
                                                <div class="grid grid-cols-2 gap-2">
                                                    <input type="number" name="duration_value" value="{{ old('duration_value', $editValue) }}" min="1" max="999" required class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm">
                                                    <select name="duration_unit" required class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm">
                                                        <option value="minutes" @selected($editUnit === 'minutes')>Minutes</option>
                                                        <option value="hours" @selected($editUnit === 'hours')>Hours</option>
                                                        <option value="days" @selected($editUnit === 'days')>Days</option>
                                                        <option value="months" @selected($editUnit === 'months')>Months</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div>
                                                <label class="block text-xs text-slate-400 mb-1">Upload Speed <span class="text-slate-600">(optional)</span></label>
                                                <input type="text" name="speed_limit_up" value="{{ old('speed_limit_up', $pkg->speed_limit_up) }}" maxlength="30" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm">
                                            </div>
                                            <div>
                                                <label class="block text-xs text-slate-400 mb-1">Download Speed <span class="text-slate-600">(optional)</span></label>
                                                <input type="text" name="speed_limit_down" value="{{ old('speed_limit_down', $pkg->speed_limit_down) }}" maxlength="30" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm">
                                            </div>
                                            <div>
                                                <label class="block text-xs text-slate-400 mb-1">Data Cap (MB, optional)</label>
                                                <input type="number" name="data_limit_mb" value="{{ old('data_limit_mb', $pkg->data_limit_mb) }}" min="1" placeholder="Empty = Unlimited" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm">
                                            </div>
                                            <div>
                                                <label class="block text-xs text-slate-400 mb-1">Shared Users</label>
                                                <input type="number" name="share_users" value="{{ old('share_users', $pkg->share_users ?? 1) }}" min="1" max="100" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm">
                                            </div>
                                        </div>
                                        <div class="flex gap-2">
                                            <button class="rounded-lg bg-indigo-600 hover:bg-indigo-500 px-4 py-2 text-xs text-white font-bold">Save Changes</button>
                                            <button type="button" onclick="toggleEditForm({{ $pkg->id }})" class="rounded-lg border border-slate-700 px-4 py-2 text-xs text-slate-300 hover:bg-slate-700 font-semibold">Cancel</button>
                                        </div>
                                        <p class="text-[10px] text-slate-500"><i class="fa-solid fa-circle-info mr-1"></i>Saving re-syncs the plan profile to every router at this location. The location cannot be changed from here.</p>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-6 text-center text-slate-500 text-sm">No internet plans defined. Add your first plan on the left!</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $packages->links() }}</div>
        </div>
    </div>
@endsection

@section('scripts')
<script>
    function toggleEditForm(id) {
        var row = document.getElementById('edit-row-' + id);
        if (row) row.classList.toggle('hidden');
    }
</script>
@endsection
