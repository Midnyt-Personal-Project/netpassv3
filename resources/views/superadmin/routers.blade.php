@extends('layouts.app')

@section('content')
<div class="py-6 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div class="flex items-center gap-3">
            <div class="p-2.5 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg shadow-blue-500/25">
                <i class="fas fa-network-wired text-white text-lg"></i>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-white">Routers</h1>
                <p class="text-sm text-gray-500 mt-0.5">Manage and monitor your network devices</p>
            </div>
        </div>
        {{-- <div class="flex items-center gap-3">
            <div class="flex items-center gap-2 px-4 py-2 bg-white rounded-xl border border-gray-200 shadow-sm">
                <i class="fas fa-server text-gray-400"></i>
                <span class="text-sm font-medium text-gray-700">{{ $routers->count() }} Total</span>
            </div>
            <button class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-xl transition duration-150 ease-in-out shadow-sm hover:shadow-md">
                <i class="fas fa-plus mr-2"></i>Add Router
            </button>
        </div> --}}
    </div>

    <!-- Table Card -->
    <div class=" rounded-2xl shadow-sm border overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class=" ">
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Location</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Last Heartbeat</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Router ID</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Token</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($routers as $r)
                    <tr class=" transition duration-150">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-sm font-mono font-medium text-gray-500">#{{ $r->id }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-50 to-blue-100 flex items-center justify-center">
                                    <i class="fas fa-microchip text-blue-600 text-sm"></i>
                                </div>
                                <span class="text-sm font-medium text-white">{{ $r->name }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-1.5 text-sm text-gray-600">
                                <i class="fas fa-map-pin text-gray-400 text-xs"></i>
                                <span>{{ $r->location?->name ?? '—' }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @php
                                $statusConfig = [
                                    'active' => ['color' => 'green', 'icon' => 'fa-circle'],
                                    'inactive' => ['color' => 'red', 'icon' => 'fa-circle'],
                                    'maintenance' => ['color' => 'yellow', 'icon' => 'fa-circle'],
                                ];
                                $config = $statusConfig[$r->status] ?? ['color' => 'gray', 'icon' => 'fa-circle'];
                            @endphp
                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-medium bg-{{ $config['color'] }}-50 text-{{ $config['color'] }}-700 border border-{{ $config['color'] }}-200/50">
                                <i class="fas {{ $config['icon'] }} text-{{ $config['color'] }}-500 text-[8px]"></i>
                                {{ $r->status ?? 'unknown' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-1.5 text-sm text-gray-500">
                                <i class="far fa-clock text-gray-400 text-xs"></i>
                                <span class="font-mono text-xs">{{ $r->last_heartbeat ?? 'Never' }}</span>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-1 max-w-[170px]">
                                <input type="text" 
                                       class="flex-1 px-2.5 py-1.5 text-xs font-mono bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition cursor-pointer" 
                                       value="{{ $r->router_id }}" 
                                       readonly 
                                       onclick="this.select()">
                                <button class="flex-shrink-0 p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition copy-btn" 
                                        onclick="copyToClipboard(this, '{{ $r->router_id }}')" 
                                        title="Copy Router ID">
                                    <i class="far fa-copy text-sm"></i>
                                </button>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center gap-1 max-w-[190px]">
                                <input type="text" 
                                       class="flex-1 px-2.5 py-1.5 text-xs font-mono bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition cursor-pointer" 
                                       value="{{ $r->api_token }}" 
                                       readonly 
                                       onclick="this.select()">
                                <button class="flex-shrink-0 p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg transition copy-btn" 
                                        onclick="copyToClipboard(this, '{{ $r->api_token }}')" 
                                        title="Copy Token">
                                    <i class="far fa-copy text-sm"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center">
                            <div class="flex flex-col items-center gap-3">
                                <div class="w-16 h-16  rounded-full flex items-center justify-center">
                                    <i class="fas fa-inbox text-2xl text-gray-400"></i>
                                </div>
                                <p class="text-sm text-gray-500">No routers found</p>
                            </div>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <!-- Footer -->
        <div class="px-6 py-3.5  border-t border-gray-200 flex items-center justify-between flex-wrap gap-2">
            <span class="text-xs text-gray-500">
                <i class="fas fa-mouse-pointer mr-1.5"></i>Click any field to select, use copy button
            </span>
            <span class="text-xs text-gray-400">
                <i class="far fa-clock mr-1.5"></i>Last updated: {{ now()->format('Y-m-d H:i') }} UTC
            </span>
        </div>
    </div>
</div>

@push('styles')
<style>
    /* Color utilities for status badges */
    .bg-green-50 { background-color: #f0fdf4; }
    .text-green-700 { color: #15803d; }
    .border-green-200 { border-color: #bbf7d0; }
    .text-green-500 { color: #22c55e; }
    
    .bg-red-50 { background-color: #fef2f2; }
    .text-red-700 { color: #b91c1c; }
    .border-red-200 { border-color: #fecaca; }
    .text-red-500 { color: #ef4444; }
    
    .bg-yellow-50 { background-color: #fefce8; }
    .text-yellow-700 { color: #a16207; }
    .border-yellow-200 { border-color: #fde68a; }
    .text-yellow-500 { color: #eab308; }
    
    .bg-gray-50 { background-color: #f9fafb; }
    .text-gray-700 { color: #374151; }
    .border-gray-200 { border-color: #e5e7eb; }
    .text-gray-500 { color: #6b7280; }

    /* Copy button feedback */
    .copy-btn i.fa-check {
        color: #16a34a;
    }
    
    .copy-btn:active {
        transform: scale(0.95);
    }

    /* Smooth transitions */
    .transition {
        transition: all 0.15s ease-in-out;
    }

    /* Custom scrollbar */
    .overflow-x-auto::-webkit-scrollbar {
        height: 6px;
    }
    .overflow-x-auto::-webkit-scrollbar-track {
        background: #f1f1f1;
    }
    .overflow-x-auto::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 3px;
    }
    .overflow-x-auto::-webkit-scrollbar-thumb:hover {
        background: #9ca3af;
    }
</style>
@endpush

@push('scripts')
<script>
function copyToClipboard(button, text) {
    // Modern clipboard API
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            showCopyFeedback(button);
        }).catch(() => {
            fallbackCopy(button, text);
        });
    } else {
        fallbackCopy(button, text);
    }
}

function fallbackCopy(button, text) {
    const input = document.createElement('input');
    input.value = text;
    input.style.position = 'fixed';
    input.style.opacity = '0';
    document.body.appendChild(input);
    input.select();
    document.execCommand('copy');
    document.body.removeChild(input);
    showCopyFeedback(button);
}

function showCopyFeedback(button) {
    const icon = button.querySelector('i');
    const originalClass = icon.className;
    icon.className = 'fas fa-check';
    icon.style.color = '#16a34a';
    
    setTimeout(() => {
        icon.className = originalClass;
        icon.style.color = '';
    }, 1500);
}

// Auto-select on input click
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('input[readonly]').forEach(input => {
        input.addEventListener('click', function() {
            this.select();
        });
    });
});
</script>
@endpush
@endsection
