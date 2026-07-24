<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Oyalo Cloud Admin')</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #0f172a; color: #e2e8f0; }
    </style>
</head>
<body class="flex min-h-screen">
    <!-- Sidebar -->
    <aside class="w-64 bg-slate-900 border-r border-slate-800 flex flex-col shadow-xl">
        <div class="p-6 border-b border-slate-800 flex items-center space-x-3">
            <img src="/images/logo-192.png" alt="Oyalo" class="w-8 h-8 rounded-lg">
            <span class="font-extrabold text-white tracking-wider text-lg">OYALO CLOUD</span>
        </div>
        <nav class="flex-grow p-4 space-y-1">
            @if(request()->is('superadmin*'))
                <a href="/superadmin" class="flex items-center space-x-3 py-2.5 px-4 rounded-lg bg-indigo-600/10 text-indigo-400 font-medium">
                    <i class="fa-solid fa-gauge"></i> <span>Super Dashboard</span>
                </a>
            @else
                <a href="/admin" class="flex items-center space-x-3 py-2.5 px-4 rounded-lg bg-indigo-600/10 text-indigo-400 font-medium mb-1">
                    <i class="fa-solid fa-gauge"></i> <span>Admin Dashboard</span>
                </a>
                <a href="/admin/packages" class="flex items-center space-x-3 py-2.5 px-4 rounded-lg text-slate-400 hover:bg-slate-800 hover:text-white transition">
                    <i class="fa-solid fa-cubes"></i> <span>Packages</span>
                </a>
                <a href="/admin/devices" class="flex items-center space-x-3 py-2.5 px-4 rounded-lg text-slate-400 hover:bg-slate-800 hover:text-white transition">
                    <i class="fa-solid fa-tv"></i> <span>TV & Devices</span>
                </a>
            @endif
        </nav>
        <div class="p-4 border-t border-slate-800 text-xs text-slate-500 text-center">
            Oyalo Cloud Admin Panel v1.0
        </div>
    </aside>

    <!-- Main Container -->
    <div class="flex-grow flex flex-col">
        <!-- Top Nav -->
        <header class="bg-slate-900/60 border-b border-slate-800 py-4 px-8 flex justify-between items-center">
            <h2 class="text-xl font-bold text-white">@yield('page_title', 'Dashboard')</h2>
            <div class="flex items-center space-x-4">
                <div class="text-right">
                    <p class="text-sm font-semibold text-white">Oyalo User</p>
                    <div class="flex items-center justify-end space-x-2">
                        <span class="text-[10px] text-slate-400 capitalize">@yield('role', 'Administrator')</span>
                        <span class="text-slate-700 text-xs">•</span>
                        <a href="/logout" class="text-[10px] text-rose-400 hover:text-rose-300 font-semibold hover:underline">Logout</a>
                    </div>
                </div>
                <div class="w-10 h-10 rounded-full bg-indigo-600 flex items-center justify-center font-bold text-white shadow-md border border-indigo-500/20">
                    OY
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="p-8 flex-grow">
            @if(session('success'))
                <div class="mb-6 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl flex items-center space-x-3">
                    <i class="fa-solid fa-circle-check"></i>
                    <p class="text-sm">{{ session('success') }}</p>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</body>
</html>
