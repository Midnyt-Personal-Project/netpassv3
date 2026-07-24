@extends('layouts.app')
@section('title', 'News Ticker')
@section('page_title', 'TV & Portal News Ticker')
@section('role', auth()->user()->isSuperAdmin() ? 'Super Administrator' : 'Hotspot Business Owner')

@section('content')
<div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
    <section class="bg-slate-900 border border-slate-800 rounded-2xl p-5 sm:p-6 shadow-xl">
        <h2 class="text-lg font-bold text-white"><i class="fa-solid fa-bullhorn text-indigo-400 mr-2"></i>Publish moving news</h2>
        <p class="mt-1 text-xs leading-5 text-slate-400">This message scrolls across the customer portal and is easy to read on TVs. Global news appears at every location; location news appears only where it is selected.</p>
        <form action="{{ route('admin.announcements.create') }}" method="POST" class="mt-6 space-y-4">
            @csrf
            <div><label class="block text-xs text-slate-400 mb-1">Show on</label><select name="location_id" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm">@if(auth()->user()->isSuperAdmin())<option value="">All locations — global news</option>@endif @foreach($locations as $location)<option value="{{ $location->id }}">{{ $location->name }} only</option>@endforeach</select></div>
            <div><label class="block text-xs text-slate-400 mb-1">Headline <span class="text-slate-600">(optional)</span></label><input name="title" maxlength="80" value="{{ old('title') }}" placeholder="e.g. Weekend offer" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm"></div>
            <div><label class="block text-xs text-slate-400 mb-1">Ticker message</label><textarea name="message" required maxlength="240" rows="4" placeholder="Share news, WiFi information, events, or promotions..." class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm">{{ old('message') }}</textarea></div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4"><div><label class="block text-xs text-slate-400 mb-1">Priority</label><input type="number" min="0" max="100" name="priority" value="{{ old('priority', 0) }}" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm"></div><div><label class="block text-xs text-slate-400 mb-1">Stop showing <span class="text-slate-600">(optional)</span></label><input type="datetime-local" name="ends_at" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-sm"></div></div>
            <button class="w-full bg-indigo-600 hover:bg-indigo-500 py-3 rounded-lg text-white font-bold text-sm">Publish ticker</button>
        </form>
    </section>
    <section class="xl:col-span-2 bg-slate-900 border border-slate-800 rounded-2xl p-5 sm:p-6 shadow-xl">
        <h2 class="text-lg font-bold text-white">Published news</h2><p class="text-xs text-slate-400 mt-1 mb-5">Global items have no location label. Newer items appear first.</p>
        <div class="space-y-3">@forelse($announcements as $announcement)<article class="rounded-xl border border-slate-800 bg-slate-800/40 p-4"><div class="flex flex-wrap items-center gap-2 text-xs"><span class="rounded-full bg-indigo-500/15 px-2 py-1 text-indigo-300">{{ $announcement->location?->name ?? 'Global — all locations' }}</span><span class="text-slate-500">Priority {{ $announcement->priority }}</span>@if($announcement->ends_at)<span class="text-slate-500">Ends {{ $announcement->ends_at->format('M j, Y H:i') }}</span>@endif</div>@if($announcement->title)<h3 class="mt-3 font-bold text-white">{{ $announcement->title }}</h3>@endif<p class="mt-1 text-sm text-slate-300 break-words">{{ $announcement->message }}</p></article>@empty<div class="py-12 text-center text-slate-500">No news has been published yet.</div>@endforelse</div>
    </section>
</div>
@endsection
