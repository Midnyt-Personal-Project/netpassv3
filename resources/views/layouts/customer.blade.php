<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Oyalo Hotspot Portal')</title>
    
    <!-- PWA Settings -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#4f46e5">
    
    <!-- iOS support -->
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Oyalo">
    <link rel="apple-touch-icon" href="/images/logo-192.png">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            background-color: #0f172a;
            color: #f8fafc;
            -webkit-tap-highlight-color: transparent;
        }
        /* Large, clearly focused controls work well with remotes and keyboards. */
        button:focus-visible, a:focus-visible, input:focus-visible, select:focus-visible { outline: 3px solid #67e8f9; outline-offset: 3px; }
        @media (min-width: 1100px) { body { font-size: 18px; } button, input, select { min-height: 48px; } }
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #1e293b;
        }
        ::-webkit-scrollbar-thumb {
            background: #4f46e5;
            border-radius: 4px;
        }
    </style>
</head>
<body class="flex flex-col min-h-screen">
    <!-- Header -->
    <header class="bg-slate-900 border-b border-slate-800 py-4 px-6 sticky top-0 z-50 shadow-md">
        <div class="max-w-4xl mx-auto flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <img src="/images/logo-192.png" alt="Oyalo Logo" class="w-10 h-10 rounded-xl shadow-lg border border-indigo-500/30">
                <div>
                    <h1 class="font-extrabold text-lg text-white tracking-tight flex items-center">
                        OYALO <span class="ml-1.5 text-xs bg-indigo-600 text-white font-semibold py-0.5 px-2 rounded-full uppercase tracking-widest">WIFI</span>
                    </h1>
                    <p class="text-xs text-slate-400">@yield('subtitle', 'Fast Cloud Internet')</p>
                </div>
            </div>
            
            <!-- PWA Back / Menu Button inside Standalone Mode -->
            <div class="flex items-center space-x-2">
                <button id="btn-install-pwa" type="button" class="bg-indigo-600 hover:bg-indigo-500 text-white py-2 px-3 rounded-lg text-xs font-bold shadow-lg transition" aria-label="Install Oyalo app">
                    <i class="fa-solid fa-download sm:mr-1"></i><span class="hidden sm:inline">Install app</span>
                </button>
                <button id="pwa-back-button" onclick="history.back()" class="hidden bg-slate-800 hover:bg-slate-700 text-white py-1.5 px-3 rounded-lg text-xs font-medium border border-slate-700 transition">
                    <i class="fa-solid fa-arrow-left mr-1"></i> Back
                </button>
                @yield('header_action')
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow max-w-4xl mx-auto w-full p-4 sm:p-6">
        <!-- Toast Alerts -->
        @if(session('success'))
            <div class="mb-5 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl flex items-start space-x-3 shadow-lg">
                <i class="fa-solid fa-circle-check mt-1 text-lg"></i>
                <div class="text-sm">
                    <p class="font-semibold">Success</p>
                    <p class="text-xs text-slate-300">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-5 bg-rose-500/10 border border-rose-500/20 text-rose-400 p-4 rounded-xl flex items-start space-x-3 shadow-lg">
                <i class="fa-solid fa-circle-exclamation mt-1 text-lg"></i>
                <div class="text-sm">
                    <p class="font-semibold">Error</p>
                    <p class="text-xs text-slate-300">{{ session('error') }}</p>
                </div>
            </div>
        @endif

        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-slate-900/60 border-t border-slate-800 py-6 text-center text-xs text-slate-500">
        <p>© 2026 Oyalo Cloud WiFi. All rights reserved.</p>
        <p class="mt-1 text-slate-600">Built for seamless MikroTik Hotspot Automation</p>
    </footer>

    <!-- PWA Loader -->
    <script src="/js/pwa.js"></script>
    @yield('scripts')
</body>
</html>
