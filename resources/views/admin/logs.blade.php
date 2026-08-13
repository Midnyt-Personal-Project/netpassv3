@extends('layouts.app')
@section('title', 'System Logs')
@section('page_title', auth()->user()->isSuperAdmin() ? 'Platform Logs & Delivery Status' : 'Your Delivery & Activity Logs')
@section('role', auth()->user()->isSuperAdmin() ? 'Super Administrator — all activity' : 'Hotspot Business Owner')

@section('content')
@if(config('mail.default') === 'log')
<div class="mb-6 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-sm text-amber-100"><i class="fa-solid fa-triangle-exclamation mr-2"></i><strong>Email is in log mode.</strong> Emails are recorded locally and are not delivered. Set <code class="rounded bg-slate-950/40 px-1">MAIL_MAILER=smtp</code> with valid SMTP credentials in the deployed <code class="rounded bg-slate-950/40 px-1">.env</code>, then run <code class="rounded bg-slate-950/40 px-1">php artisan config:clear</code>.</div>
@endif

<div class="space-y-8">
    {{-- ==================== SMS DELIVERY LOG ==================== --}}
    <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5 sm:p-6 shadow-xl">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-lg font-bold text-white"><i class="fa-solid fa-comment-sms mr-2 text-indigo-400"></i>SMS delivery log</h2>
                <p class="mt-1 text-xs text-slate-400">Every SMS attempt with the gateway's exact reply. Failed entries show the reason (no balance, wrong key, bad number...).</p>
            </div>
            <span class="text-xs text-slate-500">Latest {{ $smsLogs->perPage() }} of {{ $smsLogs->total() }}</span>
        </div>

        {{-- Summary chips --}}
        <div class="mb-4 grid grid-cols-2 lg:grid-cols-4 gap-3">
            <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-3">
                <p class="text-[10px] uppercase font-bold tracking-wider text-emerald-400/80">Sent today</p>
                <p class="text-xl font-extrabold text-emerald-300">{{ $smsStats['today_sent'] }}</p>
            </div>
            <div class="rounded-xl border border-rose-500/20 bg-rose-500/5 p-3">
                <p class="text-[10px] uppercase font-bold tracking-wider text-rose-400/80">Failed today</p>
                <p class="text-xl font-extrabold text-rose-300">{{ $smsStats['today_failed'] }}</p>
            </div>
            <div class="rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-3">
                <p class="text-[10px] uppercase font-bold tracking-wider text-emerald-400/80">Sent last 7 days</p>
                <p class="text-xl font-extrabold text-emerald-300">{{ $smsStats['week_sent'] }}</p>
            </div>
            <div class="rounded-xl border border-rose-500/20 bg-rose-500/5 p-3">
                <p class="text-[10px] uppercase font-bold tracking-wider text-rose-400/80">Failed last 7 days</p>
                <p class="text-xl font-extrabold text-rose-300">{{ $smsStats['week_failed'] }}</p>
            </div>
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.logs') }}" class="mb-4 flex flex-wrap items-center gap-2">
            <select name="sms_status" class="bg-slate-800 border border-slate-700 rounded-lg p-2 text-white text-xs">
                <option value="">All statuses</option>
                <option value="sent" @selected(request('sms_status') === 'sent')>Sent only</option>
                <option value="failed" @selected(request('sms_status') === 'failed')>Failed only</option>
            </select>
            <input type="text" name="sms_search" value="{{ request('sms_search') }}" placeholder="Search phone, message, or error..." class="flex-1 min-w-48 bg-slate-800 border border-slate-700 rounded-lg p-2 text-white text-xs">
            <button class="rounded-lg bg-indigo-600 hover:bg-indigo-500 px-3 py-2 text-xs text-white font-bold">Filter</button>
            @if(request()->filled('sms_status') || request()->filled('sms_search'))
                <a href="{{ route('admin.logs') }}" class="rounded-lg border border-slate-700 px-3 py-2 text-xs text-slate-300 hover:bg-slate-700 font-semibold">Clear</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[900px] text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-800 text-xs uppercase text-slate-400">
                        <th class="pb-3">Time</th>
                        <th class="pb-3">Type</th>
                        <th class="pb-3">Phone (format)</th>
                        <th class="pb-3">Customer</th>
                        <th class="pb-3">Message</th>
                        <th class="pb-3">Attempts</th>
                        <th class="pb-3">Gateway reply</th>
                        <th class="pb-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    @forelse($smsLogs as $log)
                        <tr>
                            <td class="py-3 text-xs text-slate-400 whitespace-nowrap">{{ $log->created_at->format('d M H:i:s') }}</td>
                            <td class="py-3 whitespace-nowrap">
                                @php
                                    $typeBadge = match($log->type) {
                                        'voucher' => 'bg-indigo-500/15 text-indigo-300',
                                        'expiry' => 'bg-amber-500/15 text-amber-300',
                                        'announcement' => 'bg-emerald-500/15 text-emerald-300',
                                        'test' => 'bg-cyan-500/15 text-cyan-300',
                                        default => 'bg-slate-700/40 text-slate-400',
                                    };
                                @endphp
                                <span class="rounded-full px-2 py-1 text-xs {{ $typeBadge }}">{{ ucfirst($log->type) }}</span>
                            </td>
                            <td class="py-3 text-slate-300 whitespace-nowrap">
                                {{ $log->phone_number }}
                                <span class="text-[10px] text-slate-500">({{ str_starts_with($log->phone_number, '0') ? '0244 format' : '233 format' }})</span>
                            </td>
                            <td class="py-3 text-white whitespace-nowrap">{{ $log->customer?->voucher_code ?? $log->customer?->username ?? '—' }}</td>
                            <td class="max-w-xs py-3 text-xs text-slate-400 break-words">
                                {{ $log->message }}
                                @if($log->error_message)
                                    <br><span class="text-rose-300"><i class="fa-solid fa-circle-exclamation mr-1"></i>{{ $log->error_message }}</span>
                                @endif
                            </td>
                            <td class="py-3 text-slate-400 whitespace-nowrap">{{ $log->attempts }} {{ $log->attempts === 1 ? 'try' : 'tries' }}</td>
                            <td class="max-w-xs py-3 text-xs text-slate-400 whitespace-nowrap">
                                @if($log->gateway_response)
                                    <details class="font-mono text-[10px] text-slate-500">
                                        <summary class="cursor-pointer text-indigo-300 hover:text-indigo-200">View reply</summary>
                                        <pre class="mt-1 whitespace-pre-wrap break-all">{{ $log->gateway_response }}</pre>
                                    </details>
                                @else
                                    <span class="text-slate-600">—</span>
                                @endif
                            </td>
                            <td class="py-3 whitespace-nowrap">
                                <span @class(['rounded-full px-2 py-1 text-xs','bg-emerald-500/10 text-emerald-300'=>$log->status==='sent','bg-rose-500/10 text-rose-300'=>$log->status==='failed'])>{{ ucfirst($log->status) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="py-10 text-center text-slate-500">No SMS logs yet. They appear here as soon as a voucher, expiry, announcement, or test SMS is sent.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $smsLogs->withQueryString()->links() }}</div>
    </section>

    {{-- ==================== OWNER EMAIL LOG ==================== --}}
    <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5 sm:p-6 shadow-xl">
        <div class="mb-5 flex items-center justify-between">
            <div>
                <h2 class="text-lg font-bold text-white"><i class="fa-solid fa-envelope-circle-check mr-2 text-cyan-400"></i>Owner email log</h2>
                <p class="mt-1 text-xs text-slate-400">Email attempts for subscription notifications. Failed entries include the reason when available.</p>
            </div>
            <span class="text-xs text-slate-500">Latest 15</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[700px] text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-800 text-xs uppercase text-slate-400">
                        <th class="pb-3">Time</th>
                        <th class="pb-3">Location</th>
                        <th class="pb-3">To</th>
                        <th class="pb-3">Subject / error</th>
                        <th class="pb-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    @forelse($emailLogs as $log)
                        <tr>
                            <td class="py-3 text-xs text-slate-400">{{ $log->created_at->format('d M H:i') }}</td>
                            <td class="py-3 text-white">{{ $log->location?->name ?? '—' }}</td>
                            <td class="py-3 text-slate-300">{{ $log->to }}</td>
                            <td class="max-w-sm py-3 text-xs text-slate-400 break-words">{{ $log->subject }}@if($log->error_message)<br><span class="text-rose-300">{{ $log->error_message }}</span>@endif</td>
                            <td class="py-3">
                                <span @class(['rounded-full px-2 py-1 text-xs','bg-emerald-500/10 text-emerald-300'=>$log->status==='sent','bg-rose-500/10 text-rose-300'=>$log->status==='failed'])>{{ ucfirst($log->status) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-10 text-center text-slate-500">No owner email attempts yet. Enable subscription email alerts on the dashboard.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $emailLogs->links() }}</div>
    </section>

    {{-- ==================== ACTIVITY AUDIT TRAIL ==================== --}}
    <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5 sm:p-6 shadow-xl">
        <div class="mb-5">
            <h2 class="text-lg font-bold text-white"><i class="fa-solid fa-clock-rotate-left mr-2 text-violet-400"></i>Activity audit trail</h2>
            <p class="mt-1 text-xs text-slate-400">@if(auth()->user()->isSuperAdmin()) Platform-wide administrative actions. @else Your administrative actions. @endif</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[650px] text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-800 text-xs uppercase text-slate-400">
                        <th class="pb-3">Time</th>
                        <th class="pb-3">Actor</th>
                        <th class="pb-3">Action</th>
                        <th class="pb-3">Description</th>
                        <th class="pb-3">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60">
                    @forelse($activityLogs as $log)
                        <tr>
                            <td class="py-3 text-xs text-slate-400">{{ $log->created_at->format('d M H:i') }}</td>
                            <td class="py-3 text-white">{{ $log->user?->name ?? 'System / Router' }}</td>
                            <td class="py-3 font-mono text-xs text-indigo-300">{{ $log->action }}</td>
                            <td class="max-w-sm py-3 text-slate-300 break-words">{{ $log->description }}</td>
                            <td class="py-3 font-mono text-xs text-slate-500">{{ $log->ip_address ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-10 text-center text-slate-500">No activity has been recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $activityLogs->links() }}</div>
    </section>
</div>
@endsection
