@extends('layouts.app')
@section('title', 'Announcements')
@section('page_title', 'Announcements & SMS Broadcasts')
@section('role', auth()->user()->isSuperAdmin() ? 'Super Administrator' : 'Hotspot Business Owner')

@section('content')
<div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
    <section class="bg-slate-900 border border-slate-800 rounded-2xl p-5 sm:p-6 shadow-xl">
        <h2 class="text-lg font-bold text-white"><i class="fa-solid fa-bullhorn text-indigo-400 mr-2"></i>Send an announcement</h2>
        <p class="mt-1 text-xs leading-5 text-slate-400">Publish a portal news ticker, send it as an SMS to one customer or everyone, or do both. SMS can go out now or be scheduled for a future date.</p>
        <form action="{{ route('admin.announcements.create') }}" method="POST" class="mt-6 space-y-4">
            @csrf

            <div>
                <label class="block text-xs text-slate-400 mb-1">Show on / send to</label>
                <select name="location_id" id="announcement-location" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm">
                    @if(auth()->user()->isSuperAdmin())
                        <option value="" data-count="{{ $scopeCounts['global'] ?? 0 }}">All locations — global</option>
                    @endif
                    @foreach($locations as $location)
                        <option value="{{ $location->id }}" data-count="{{ $scopeCounts[$location->id] ?? 0 }}" @selected(old('location_id') == $location->id)>{{ $location->name }} only</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs text-slate-400 mb-1">Headline <span class="text-slate-600">(optional)</span></label>
                <input name="title" maxlength="80" value="{{ old('title') }}" placeholder="e.g. Weekend offer" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm">
            </div>

            <div>
                <label class="block text-xs text-slate-400 mb-1">Message</label>
                <textarea name="message" required maxlength="240" rows="4" placeholder="Share news, WiFi information, events, or promotions..." class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm">{{ old('message') }}</textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Priority</label>
                    <input type="number" min="0" max="100" name="priority" value="{{ old('priority', 0) }}" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm">
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Stop ticker <span class="text-slate-600">(optional)</span></label>
                    <input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm">
                </div>
            </div>

            <label class="flex gap-3 items-start rounded-xl bg-slate-800/60 border border-slate-700 p-3 cursor-pointer">
                <input type="checkbox" name="show_ticker" value="1" id="announcement-show-ticker" class="mt-0.5 accent-indigo-500" @checked(old('show_ticker', '1'))>
                <span>
                    <span class="block text-sm text-white font-semibold"><i class="fa-solid fa-tv text-indigo-400 mr-1"></i> Show on the customer portal ticker</span>
                    <span class="block text-xs text-slate-400 mt-0.5">The message scrolls across the portal and TV pages.</span>
                </span>
            </label>

            <label class="flex gap-3 items-start rounded-xl bg-slate-800/60 border border-slate-700 p-3 cursor-pointer">
                <input type="checkbox" name="send_sms" value="1" id="announcement-send-sms" class="mt-0.5 accent-indigo-500" @checked(old('send_sms'))>
                <span>
                    <span class="block text-sm text-white font-semibold"><i class="fa-solid fa-comment-sms text-indigo-400 mr-1"></i> Send as SMS</span>
                    <span class="block text-xs text-slate-400 mt-0.5">Deliver this message by SMS to one customer or to everyone.</span>
                </span>
            </label>

            <div id="announcement-sms-options" class="hidden space-y-4 rounded-xl border border-indigo-500/30 bg-indigo-950/20 p-3">
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Recipient</label>
                    <label class="flex items-center gap-2 text-sm text-slate-200 py-1">
                        <input type="radio" name="sms_recipient" value="all" class="accent-indigo-500" @checked(old('sms_recipient', 'all') === 'all')>
                        Everyone at the selected location — <span id="announcement-recipient-count" class="text-indigo-300 font-bold">0</span> customer(s)
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-200 py-1">
                        <input type="radio" name="sms_recipient" value="one" class="accent-indigo-500" @checked(old('sms_recipient') === 'one')>
                        One customer
                    </label>
                    <input type="text" name="recipient_phone" id="announcement-recipient-phone" value="{{ old('recipient_phone') }}" placeholder="Customer phone (024xxxxxxx) or voucher code" class="mt-2 hidden w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm">
                    @error('recipient_phone')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1">When to send</label>
                    <div class="flex flex-wrap items-center gap-4">
                        <label class="flex items-center gap-2 text-sm text-slate-200">
                            <input type="radio" name="sms_schedule" value="now" class="accent-indigo-500" @checked(old('sms_schedule', 'now') === 'now')>
                            Send now
                        </label>
                        <label class="flex items-center gap-2 text-sm text-slate-200">
                            <input type="radio" name="sms_schedule" value="later" class="accent-indigo-500" @checked(old('sms_schedule') === 'later')>
                            Schedule for later
                        </label>
                    </div>
                    <input type="datetime-local" name="scheduled_at" id="announcement-scheduled-at" value="{{ old('scheduled_at') }}" class="mt-2 hidden w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm">
                    @error('scheduled_at')<p class="mt-1 text-xs text-rose-400">{{ $message }}</p>@enderror
                </div>
                <p class="text-[10px] text-slate-500"><i class="fa-solid fa-circle-info mr-1"></i>SMS messages are sent with the numbers formatted as local Ghana numbers (024xxxxxxx) for faster, more reliable delivery.</p>
            </div>

            <button class="w-full bg-indigo-600 hover:bg-indigo-500 py-3 rounded-lg text-white font-bold text-sm"><i class="fa-solid fa-paper-plane mr-1"></i> Publish</button>
        </form>
    </section>

    <section class="xl:col-span-2 bg-slate-900 border border-slate-800 rounded-2xl p-5 sm:p-6 shadow-xl">
        <h2 class="text-lg font-bold text-white">Published announcements</h2>
        <p class="text-xs text-slate-400 mt-1 mb-5">Pause, reschedule, or delete any item. Global items have no location label.</p>

        <div class="space-y-3">
            @forelse($announcements as $announcement)
                @php
                    $canManage = auth()->user()->isSuperAdmin()
                        || ($announcement->location_id && $locationIds->contains($announcement->location_id));
                    $isLive = $announcement->is_active
                        && $announcement->show_ticker
                        && (!$announcement->starts_at || $announcement->starts_at->isPast())
                        && (!$announcement->ends_at || $announcement->ends_at->isFuture());
                @endphp
                <article class="rounded-xl border border-slate-800 bg-slate-800/40 p-4">
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="rounded-full bg-indigo-500/15 px-2 py-1 text-indigo-300">{{ $announcement->location?->name ?? 'Global — all locations' }}</span>
                        @if($announcement->show_ticker)
                            <span class="rounded-full bg-cyan-500/15 px-2 py-1 text-cyan-300">Ticker</span>
                        @endif
                        @if($announcement->send_sms)
                            <span class="rounded-full bg-emerald-500/15 px-2 py-1 text-emerald-300">SMS</span>
                        @endif
                        @if($announcement->customer)
                            <span class="rounded-full bg-amber-500/15 px-2 py-1 text-amber-300">To: {{ $announcement->customer->phone_number }}</span>
                        @elseif($announcement->send_sms)
                            <span class="rounded-full bg-amber-500/15 px-2 py-1 text-amber-300">To: everyone</span>
                        @endif
                        <span class="text-slate-500">Priority {{ $announcement->priority }}</span>
                        @if($announcement->ends_at)<span class="text-slate-500">Ticker ends {{ $announcement->ends_at->format('M j, Y H:i') }}</span>@endif
                    </div>

                    <div class="flex flex-wrap items-center gap-2 mt-2 text-xs">
                        @if($announcement->isSmsSent())
                            <span class="rounded-full bg-emerald-500/10 px-2 py-1 text-emerald-400"><i class="fa-solid fa-circle-check mr-1"></i>SMS sent {{ $announcement->sent_at->format('M j, Y g:i A') }}</span>
                        @elseif($announcement->isSmsScheduled())
                            <span class="rounded-full bg-amber-500/10 px-2 py-1 text-amber-300"><i class="fa-solid fa-clock mr-1"></i>SMS scheduled {{ $announcement->scheduled_at->format('M j, Y g:i A') }}</span>
                        @elseif($announcement->send_sms && $announcement->is_active)
                            <span class="rounded-full bg-indigo-500/10 px-2 py-1 text-indigo-300"><i class="fa-solid fa-paper-plane mr-1"></i>Sending SMS...</span>
                        @elseif($announcement->isPaused())
                            <span class="rounded-full bg-rose-500/10 px-2 py-1 text-rose-300"><i class="fa-solid fa-pause mr-1"></i>Paused</span>
                        @elseif($isLive)
                            <span class="rounded-full bg-emerald-500/10 px-2 py-1 text-emerald-400"><i class="fa-solid fa-signal mr-1"></i>Live on portal</span>
                        @else
                            <span class="rounded-full bg-slate-700/40 px-2 py-1 text-slate-400">Not showing</span>
                        @endif
                    </div>

                    @if($announcement->title)
                        <h3 class="mt-3 font-bold text-white">{{ $announcement->title }}</h3>
                    @endif
                    <p class="mt-1 text-sm text-slate-300 break-words">{{ $announcement->message }}</p>

                    @if($canManage)
                        <div class="flex flex-wrap items-center gap-2 mt-3 pt-3 border-t border-slate-800">
                            <form method="POST" action="{{ route('admin.announcements.toggle', $announcement) }}">
                                @csrf
                                <button class="rounded-lg border border-slate-700 px-3 py-1.5 text-xs text-slate-300 hover:bg-slate-700 font-semibold">
                                    @if($announcement->isPaused())<i class="fa-solid fa-play mr-1 text-emerald-400"></i>Resume
                                    @else<i class="fa-solid fa-pause mr-1 text-amber-400"></i>Pause
                                    @endif
                                </button>
                            </form>

                            @if($announcement->send_sms)
                                <button onclick="document.getElementById('reschedule-{{ $announcement->id }}').classList.toggle('hidden')" class="rounded-lg border border-slate-700 px-3 py-1.5 text-xs text-slate-300 hover:bg-slate-700 font-semibold">
                                    <i class="fa-solid fa-calendar-plus mr-1 text-indigo-400"></i>Reschedule / new date
                                </button>
                            @endif

                            <form method="POST" action="{{ route('admin.announcements.delete', $announcement) }}" onsubmit="return confirm('Delete this announcement? This cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <button class="rounded-lg border border-rose-500/30 px-3 py-1.5 text-xs text-rose-300 hover:bg-rose-500/10 font-semibold"><i class="fa-solid fa-trash mr-1"></i>Delete</button>
                            </form>
                        </div>

                        @if($announcement->send_sms)
                            <form id="reschedule-{{ $announcement->id }}" method="POST" action="{{ route('admin.announcements.reschedule', $announcement) }}" class="hidden mt-3 flex flex-wrap items-center gap-2 bg-slate-900 border border-slate-800 rounded-xl p-3">
                                @csrf
                                <label class="text-xs text-slate-400">New SMS date:</label>
                                <input type="datetime-local" name="scheduled_at" required class="bg-slate-800 border border-slate-700 rounded-lg p-2 text-white text-xs">
                                <button class="rounded-lg bg-indigo-600 hover:bg-indigo-500 px-3 py-2 text-xs text-white font-bold">Save new date</button>
                                <p class="w-full text-[10px] text-slate-500">Saved announcements that were already sent will be sent again at the new date. Pausing an announcement cancels its scheduled SMS.</p>
                            </form>
                        @endif
                    @endif
                </article>
            @empty
                <div class="py-12 text-center text-slate-500">No announcements yet. Publish your first one on the left!</div>
            @endforelse

            <div class="pt-2">{{ $announcements->links() }}</div>
        </div>
    </section>
</div>
@endsection

@section('scripts')
<script>
(function () {
    var smsCheckbox = document.getElementById('announcement-send-sms');
    var smsOptions = document.getElementById('announcement-sms-options');
    var recipientPhone = document.getElementById('announcement-recipient-phone');
    var scheduledInput = document.getElementById('announcement-scheduled-at');
    var locationSelect = document.getElementById('announcement-location');
    var countLabel = document.getElementById('announcement-recipient-count');

    function refreshSmsPanel() {
        if (!smsCheckbox) return;
        smsOptions.classList.toggle('hidden', !smsCheckbox.checked);
        refreshRecipientInput();
        refreshScheduleInput();
    }

    function refreshRecipientInput() {
        if (!recipientPhone) return;
        var one = document.querySelector('input[name="sms_recipient"]:checked');
        recipientPhone.classList.toggle('hidden', !(one && one.value === 'one'));
    }

    function refreshScheduleInput() {
        if (!scheduledInput) return;
        var later = document.querySelector('input[name="sms_schedule"]:checked');
        scheduledInput.classList.toggle('hidden', !(later && later.value === 'later'));
    }

    function refreshCount() {
        if (!locationSelect || !countLabel) return;
        var option = locationSelect.options[locationSelect.selectedIndex];
        countLabel.textContent = option ? option.getAttribute('data-count') || '0' : '0';
    }

    if (smsCheckbox) smsCheckbox.addEventListener('change', refreshSmsPanel);
    document.querySelectorAll('input[name="sms_recipient"]').forEach(function (el) {
        el.addEventListener('change', refreshRecipientInput);
    });
    document.querySelectorAll('input[name="sms_schedule"]').forEach(function (el) {
        el.addEventListener('change', refreshScheduleInput);
    });
    if (locationSelect) locationSelect.addEventListener('change', refreshCount);

    refreshSmsPanel();
    refreshCount();
})();
</script>
@endsection
