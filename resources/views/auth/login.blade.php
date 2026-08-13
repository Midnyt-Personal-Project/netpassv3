<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Oyalo Cloud Hotspot</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #0f172a; color: #e2e8f0; }
    </style>
</head>
<body class="min-h-screen flex flex-col justify-center py-12 sm:px-6 lg:px-8 relative overflow-hidden">
    <!-- Abstract blurred background highlights -->
    <div class="absolute left-1/3 top-1/4 w-72 h-72 bg-indigo-600/10 rounded-full blur-3xl"></div>
    <div class="absolute right-1/4 bottom-1/3 w-80 h-80 bg-purple-600/10 rounded-full blur-3xl"></div>

    <div class="sm:mx-auto sm:w-full sm:max-w-md relative z-10">
        <div class="flex flex-col items-center">
            <a href="/" class="flex items-center space-x-2.5 mb-6">
                <img src="/images/logo-192.png" alt="Oyalo Logo" class="w-12 h-12 rounded-xl shadow-lg border border-indigo-500/20">
                <span class="text-2xl font-black text-white tracking-wider">OYALO CLOUD</span>
            </a>
            <h2 class="text-center text-2xl font-black text-white tracking-tight">Sign in to your account</h2>
            <p class="mt-1.5 text-center text-xs text-slate-400">
                Manage your WiFi hot spots, packages, and smart TV bindings.
            </p>
        </div>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md relative z-10">
        <!-- Toast Alerts -->
        @if(session('success'))
            <div class="mb-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl text-xs flex items-center space-x-2">
                <i class="fa-solid fa-circle-check"></i>
                <p>{{ session('success') }}</p>
            </div>
        @endif

        @if($errors->any())
            <div class="mb-4 bg-rose-500/10 border border-rose-500/20 text-rose-400 p-4 rounded-xl text-xs space-y-1">
                @foreach($errors->all() as $error)
                    <p><i class="fa-solid fa-circle-exclamation mr-1.5"></i> {{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="bg-slate-900 border border-slate-800 py-8 px-4 shadow-2xl sm:rounded-2xl sm:px-10">
            <form action="/login" method="POST" class="space-y-6" id="login-form">
                @csrf
                <div>
                    <label for="email" class="block text-xs font-bold text-slate-400 uppercase tracking-wide">Email Address</label>
                    <div class="mt-1">
                        <input id="email" name="email" type="email" required autocomplete="email" class="appearance-none block w-full px-3 py-3 bg-slate-800 border border-slate-700 rounded-xl placeholder-slate-500 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-xs font-bold text-slate-400 uppercase tracking-wide">Password</label>
                    <div class="mt-1">
                        <input id="password" name="password" type="password" required autocomplete="current-password" class="appearance-none block w-full px-3 py-3 bg-slate-800 border border-slate-700 rounded-xl placeholder-slate-500 text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="remember_me" name="remember" type="checkbox" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-slate-700 rounded bg-slate-800">
                        <label for="remember_me" class="ml-2 block text-xs text-slate-400">Remember me</label>
                    </div>
                    <div class="text-xs">
                        <a href="#" class="font-semibold text-indigo-400 hover:text-indigo-300">Forgot your password?</a>
                    </div>
                </div>

                <button type="submit" id="login-button" class="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-xl shadow-lg text-xs font-bold tracking-wider uppercase text-white bg-indigo-600 hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition disabled:opacity-60 disabled:cursor-not-allowed">
                    <i id="login-spinner" class="fa-solid fa-circle-notch fa-spin mr-2 hidden"></i>
                    <span id="login-button-text">Sign In</span>
                </button>
            </form>

            <!-- DEMO MOCK LOGIN BUTTONS -->
            
        </div>
    </div>

    <script>
        function quickLogin(email, pass) {
            document.getElementById('email').value = email;
            document.getElementById('password').value = pass;
            document.getElementById('login-form').submit();
        }

        // Show a loading state on the Sign In button so users don't tap twice
        // while the credentials are being checked.
        (function () {
            var form = document.getElementById('login-form');
            if (!form) return;

            form.addEventListener('submit', function () {
                var button = document.getElementById('login-button');
                var spinner = document.getElementById('login-spinner');
                var text = document.getElementById('login-button-text');

                if (!button || button.disabled) return;

                button.disabled = true;
                if (spinner) spinner.classList.remove('hidden');
                if (text) text.textContent = 'Signing in...';
            });
        })();
    </script>
</body>
</html>
