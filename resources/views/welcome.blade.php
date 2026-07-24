<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Oyalo Cloud — MikroTik Hotspot Management</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #0f172a; color: #f8fafc; }
    </style>
</head>
<body class="flex flex-col min-h-screen">
    <!-- Header -->
    <header class="bg-slate-900/80 border-b border-slate-850 py-4 px-8 sticky top-0 z-50 backdrop-blur-md">
        <div class="max-w-6xl mx-auto flex justify-between items-center">
            <div class="flex items-center space-x-3">
                <img src="/images/logo-192.png" alt="Oyalo Logo" class="w-10 h-10 rounded-xl">
                <span class="font-extrabold text-white tracking-wider text-xl">OYALO CLOUD</span>
            </div>
            <div class="flex items-center space-x-4">
                <a href="#demo" class="bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold py-2 px-5 rounded-xl text-xs transition shadow-lg">
                    EXPLORE DEMO
                </a>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="flex-grow max-w-6xl mx-auto w-full px-6 py-20 flex flex-col items-center text-center space-y-8">
        <div class="inline-flex items-center space-x-2 bg-indigo-500/10 border border-indigo-500/25 py-1.5 px-4 rounded-full text-indigo-400 font-extrabold text-xs tracking-wider uppercase">
            <i class="fa-solid fa-cloud-bolt text-[10px]"></i>
            <span>No Public Ports Exposed • Split Payments Enabled</span>
        </div>
        <h1 class="text-4xl sm:text-6xl font-black text-white leading-tight max-w-4xl tracking-tight">
            Automate Your <span class="bg-clip-text text-transparent bg-gradient-to-r from-indigo-400 via-purple-400 to-pink-400">MikroTik Hotspot</span> Business
        </h1>
        <p class="text-slate-400 text-sm sm:text-lg max-w-2xl leading-relaxed">
            Oyalo is a complete cloud hotspot solution that connects to any MikroTik router via standard HTTP fetch polling. Charge clients via split Paystack subaccounts and auto-authenticate Smart TVs using physical MAC addresses.
        </p>

        <!-- Access Dashboards Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 w-full pt-12" id="demo">
            <!-- Super Admin -->
            <div class="bg-slate-900 border border-slate-800 hover:border-slate-700 p-8 rounded-3xl text-left flex flex-col justify-between shadow-2xl transition duration-300">
                <div>
                    <div class="w-12 h-12 bg-indigo-600/10 text-indigo-400 rounded-2xl flex items-center justify-center border border-indigo-500/20 mb-6">
                        <i class="fa-solid fa-shield-halved text-xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2">Super Admin Platform</h3>
                    <p class="text-slate-400 text-xs leading-relaxed mb-6">
                        Manage global commissions, create admin business owners, register locations, map router tokens, and view consolidated sales.
                    </p>
                </div>
                <a href="/superadmin" class="bg-slate-800 hover:bg-indigo-600 hover:text-white text-slate-300 font-bold py-3 text-center rounded-xl text-xs transition duration-200">
                    Open Super Admin Panel
                </a>
            </div>

            <!-- Hotspot Owner Admin -->
            <div class="bg-slate-900 border border-slate-800 hover:border-slate-700 p-8 rounded-3xl text-left flex flex-col justify-between shadow-2xl transition duration-300">
                <div>
                    <div class="w-12 h-12 bg-purple-600/10 text-purple-400 rounded-2xl flex items-center justify-center border border-purple-500/20 mb-6">
                        <i class="fa-solid fa-briefcase text-xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2">Location Business Admin</h3>
                    <p class="text-slate-400 text-xs leading-relaxed mb-6">
                        Define internet pricing plans, view registered customer accounts, manage Smart TV MAC addresses, and review sales reports.
                    </p>
                </div>
                <a href="/admin" class="bg-slate-800 hover:bg-purple-600 hover:text-white text-slate-300 font-bold py-3 text-center rounded-xl text-xs transition duration-200">
                    Open Admin Dashboard
                </a>
            </div>

            <!-- Customer PWA Portal -->
            <div class="bg-slate-900 border border-slate-800 hover:border-slate-700 p-8 rounded-3xl text-left flex flex-col justify-between shadow-2xl transition duration-300 relative overflow-hidden group">
                <span class="absolute top-3 right-3 text-[9px] bg-emerald-500/10 text-emerald-400 font-extrabold px-2 py-0.5 rounded-full uppercase tracking-wider">
                    PWA Installed
                </span>
                <div>
                    <div class="w-12 h-12 bg-emerald-600/10 text-emerald-400 rounded-2xl flex items-center justify-center border border-emerald-500/20 mb-6">
                        <i class="fa-solid fa-mobile-screen-button text-xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2">Customer Hotspot Portal</h3>
                    <p class="text-slate-400 text-xs leading-relaxed mb-6">
                        A mobile-first splash experience where customers purchase internet packages, register device MAC addresses, and install the web app.
                    </p>
                </div>
                <a href="/h/demo" class="bg-slate-800 hover:bg-emerald-600 hover:text-white text-slate-300 font-bold py-3 text-center rounded-xl text-xs transition duration-200">
                    Open Demo Portal URL
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-slate-950 py-8 border-t border-slate-900 text-center text-xs text-slate-600">
        <div class="max-w-6xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-between space-y-4 sm:space-y-0">
            <p>© 2026 Oyalo Cloud WiFi — The Modern Hotspot Standard.</p>
            <div class="flex space-x-4">
                <span class="text-slate-500">PWA Ready</span>
                <span class="text-slate-700">•</span>
                <span class="text-slate-500">Ghana (Accra)</span>
            </div>
        </div>
    </footer>
</body>
</html>
