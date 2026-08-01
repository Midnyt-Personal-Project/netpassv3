@extends('layouts.app')
@section('title', 'Super Admin Dashboard')
@section('page_title', 'Super Admin Control Center')
@section('role', 'Super Administrator — full system access')

@section('content')
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
            <h2 class="text-xl font-bold text-white">
                <i class="fa-solid fa-terminal text-indigo-400 mr-2"></i>Router Commands
            </h2>
            <span class="text-xs text-slate-400">Total: {{ $commands->total() }}</span>
        </div>

        {{-- Search form --}}
        <form method="GET" class="mb-4 flex gap-2">
            <input
                type="text"
                name="q"
                placeholder="Search by command type, status, or router ID..."
                value="{{ request('q') }}"
                class="flex-1 bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm"
            >
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 px-4 rounded-lg text-white text-sm transition">
                Search
            </button>
            @if(request('q'))
                <a href="{{ request()->url() }}" class="bg-slate-700 hover:bg-slate-600 px-4 rounded-lg text-white text-sm transition">
                    Clear
                </a>
            @endif
        </form>

        {{-- Commands table --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-800 text-slate-400 text-xs uppercase font-semibold">
                        <th class="pb-3">ID</th>
                        <th class="pb-3">Router</th>
                        <th class="pb-3">Type</th>
                        <th class="pb-3">Payload</th>
                        <th class="pb-3">Script</th>
                        <th class="pb-3">Status</th>
                        <th class="pb-3">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/50">
                    @forelse($commands as $c)
                        <tr>
                            <td class="py-3 font-mono text-xs text-slate-300">{{ $c->id }}</td>
                            <td class="py-3 text-slate-300">{{ $c->router?->router_id ?? 'N/A' }}</td>
                            <td class="py-3 text-white">
                                <span class="inline-block px-2 py-0.5 rounded text-xs bg-indigo-500/10 text-indigo-300">
                                    {{ $c->command_type }}
                                </span>
                            </td>
                            <td class="py-3">
                                <pre class="text-xs text-slate-400 bg-slate-800 p-2 rounded max-h-24 overflow-y-auto">{{ json_encode($c->payload, JSON_PRETTY_PRINT) }}</pre>
                            </td>
                            <td class="py-3">
                                <pre class="text-xs text-indigo-300 bg-slate-800 p-2 rounded max-h-24 overflow-y-auto font-mono whitespace-pre-wrap">{{ $c->script }}</pre>
                            </td>
                            <td class="py-3">
                                @php
                                    $statusColors = [
                                        'pending' => 'bg-yellow-500/10 text-yellow-400',
                                        'completed' => 'bg-emerald-500/10 text-emerald-400',
                                        'failed' => 'bg-red-500/10 text-red-400',
                                    ];
                                @endphp
                                <span class="inline-block px-2 py-0.5 rounded text-xs {{ $statusColors[$c->status] ?? 'bg-slate-500/10 text-slate-400' }}">
                                    {{ $c->status }}
                                </span>
                            </td>
                            <td class="py-3 text-slate-400 text-xs">{{ $c->created_at->format('M j, Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-slate-500">No router commands found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        <div class="mt-4">
            {{ $commands->appends(['q' => request('q')])->links() }}
        </div>
    </div>
@endsection