<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0f172a"><title>@yield('title', 'Oyalo Cloud Admin')</title>
    <script src="https://cdn.tailwindcss.com"></script><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>body{background:#0f172a;color:#e2e8f0}button:focus-visible,a:focus-visible,input:focus-visible,select:focus-visible{outline:3px solid #67e8f9;outline-offset:3px}</style>
</head>
<body class="min-h-screen">
@php
    $super = auth()->user()->isSuperAdmin();
    $nav = $super ? [
        ['superadmin.dashboard','/superadmin','fa-gauge','Super dashboard'], ['admin.dashboard','/admin','fa-chart-line','All locations'], ['admin.packages','/admin/packages','fa-cubes','Packages'], ['admin.devices','/admin/devices','fa-tv','TV & devices'], ['admin.announcements','/admin/announcements','fa-bullhorn','Announcements'], ['admin.subscriptions','/admin/subscriptions','fa-user-plus','Subscriptions'], ['superadmin.routers','/superadmin/routers','fa-server','Routers'], ['admin.logs','/admin/logs','fa-clipboard-list','Logs'],['superadmin.router-commands','/superadmin/router-commands','fa-terminal','Router commands'], ['admin.settings','/admin/settings','fa-user-gear','My account'],
    ] : [
        ['admin.dashboard','/admin','fa-gauge','Dashboard'], ['admin.packages','/admin/packages','fa-cubes','Packages'], ['admin.devices','/admin/devices','fa-tv','TV & devices'], ['admin.announcements','/admin/announcements','fa-bullhorn','Announcements'], ['admin.subscriptions','/admin/subscriptions','fa-user-plus','Subscriptions'], ['admin.logs','/admin/logs','fa-clipboard-list','Logs'], ['admin.settings','/admin/settings','fa-user-gear','My account'],
    ];
@endphp
<div id="nav-backdrop" class="fixed inset-0 bg-slate-950/70 z-30 hidden md:hidden" onclick="toggleSidebar(false)"></div>
<aside id="sidebar" class="fixed inset-y-0 left-0 z-40 w-72 -translate-x-full md:translate-x-0 md:w-64 bg-slate-900 border-r border-slate-800 flex flex-col shadow-xl transition-transform duration-200">
    <div class="p-5 border-b border-slate-800 flex items-center justify-between"><a href="{{ $super ? route('superadmin.dashboard') : route('admin.dashboard') }}" class="flex items-center gap-3"><img src="/images/logo-192.png" alt="Oyalo" class="w-9 h-9 rounded-lg"><span class="font-extrabold text-white tracking-wider">OYALO CLOUD</span></a><button class="md:hidden text-slate-400 p-2" onclick="toggleSidebar(false)" aria-label="Close menu"><i class="fa-solid fa-xmark"></i></button></div>
    <nav class="flex-grow p-3 space-y-1" aria-label="Dashboard navigation">
        @foreach($nav as [$route, $url, $icon, $label])
            <a href="{{ $url }}" @class(['flex items-center gap-3 py-3 px-4 rounded-xl font-medium transition','bg-indigo-600 text-white shadow-lg shadow-indigo-950/40'=>request()->routeIs($route),'text-slate-400 hover:bg-slate-800 hover:text-white'=>!request()->routeIs($route)]) aria-current="{{ request()->routeIs($route) ? 'page' : 'false' }}"><i class="fa-solid {{ $icon }} w-5 text-center"></i><span>{{ $label }}</span></a>
        @endforeach
    </nav>
    <div class="p-4 border-t border-slate-800 text-xs text-slate-500">Oyalo Cloud Admin Panel</div>
</aside>
<div class="md:pl-64 min-h-screen flex flex-col">
    <header class="sticky top-0 z-20 bg-slate-900/95 backdrop-blur border-b border-slate-800 px-4 sm:px-6 py-3 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0"><button class="md:hidden p-2 -ml-2 text-slate-300" onclick="toggleSidebar(true)" aria-label="Open menu"><i class="fa-solid fa-bars text-lg"></i></button><h1 class="text-base sm:text-xl font-bold text-white truncate">@yield('page_title', 'Dashboard')</h1></div>
        <div class="flex items-center gap-2 sm:gap-4 shrink-0"><div class="text-right hidden sm:block"><p class="text-sm font-semibold text-white truncate max-w-32">{{ auth()->user()->name }}</p><p class="text-[10px] text-slate-400">@yield('role', 'Administrator')</p></div><form action="{{ route('logout') }}" method="POST">@csrf<button type="submit" class="rounded-lg border border-rose-500/30 px-2.5 sm:px-3 py-2 text-xs text-rose-300 hover:bg-rose-500/10 font-semibold">Sign out</button></form></div>
    </header>
    <main class="p-4 sm:p-6 lg:p-8 flex-grow max-w-[1700px] w-full mx-auto">
        @if(session('success'))<div class="mb-5 bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 p-4 rounded-xl text-sm"><i class="fa-solid fa-circle-check mr-2"></i>{{ session('success') }}</div>@endif
        @if($errors->any())<div class="mb-5 bg-rose-500/10 border border-rose-500/20 text-rose-200 p-4 rounded-xl text-sm">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
        @yield('content')
    </main>
</div>
<script>function toggleSidebar(open){document.getElementById('sidebar').classList.toggle('-translate-x-full',!open);document.getElementById('nav-backdrop').classList.toggle('hidden',!open);}</script>
@yield('scripts')
</body></html>
