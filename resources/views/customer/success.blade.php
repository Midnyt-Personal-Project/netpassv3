@extends('layouts.customer')
@section('title', 'Payment Success - Oyalo')
@section('subtitle', 'Internet Access Activated!')

@section('content')
    <div class="max-w-md mx-auto space-y-6">
        <!-- Success Card -->
        <div class="bg-slate-900 border border-emerald-500/20 rounded-2xl p-6 text-center shadow-2xl relative overflow-hidden">
            <div class="absolute left-1/2 -top-12 -translate-x-1/2 w-24 h-24 bg-emerald-500/10 rounded-full blur-2xl"></div>
            
            <div class="w-16 h-16 bg-emerald-500/10 text-emerald-400 rounded-full flex items-center justify-center mx-auto border border-emerald-500/20 mb-4 animate-bounce">
                <i class="fa-solid fa-circle-check text-3xl"></i>
            </div>
            
            <h2 class="text-2xl font-black text-white">Payment Successful</h2>
            <p class="text-slate-400 text-xs mt-1">Your hotspot account is now active and synced with the router.</p>

            <!-- One-code voucher details -->
            <div class="bg-slate-850 border border-slate-800 p-5 rounded-2xl mt-6 space-y-4 text-center">
                <p class="text-[10px] text-indigo-400 uppercase font-extrabold tracking-wider">Your WiFi voucher</p>
                <p class="font-mono text-xl text-white font-extrabold select-all tracking-wider">{{ $customer->voucher_code ?? $customer->username }}</p>
                <p class="text-[10px] leading-5 text-slate-400">Use this same voucher code for both the MikroTik username and password fields. Keep it private.</p>
                <div class="border-t border-slate-800/80 pt-3 text-center">
                    <p class="text-[10px] text-slate-400">
                        Expires: <span class="text-slate-200 font-bold">{{ $customer->expires_at->format('d M Y H:i') }}</span>
                    </p>
                </div>
            </div>

            <!-- Copy Button -->
            <button onclick="copyVoucher('{{ $customer->voucher_code ?? $customer->username }}')" class="mt-4 bg-slate-800 hover:bg-slate-750 text-white font-bold py-2.5 px-4 rounded-xl text-xs transition duration-200 flex items-center justify-center space-x-1.5 w-full border border-slate-700">
                <i class="fa-solid fa-copy"></i>
                <span>Copy Voucher</span>
            </button>
        </div>

        <!-- How to connect section -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-4">
            <h3 class="font-bold text-white text-sm flex items-center">
                <i class="fa-solid fa-wifi text-indigo-400 mr-2"></i> How to Connect Now
            </h3>
            <ol class="space-y-3.5 text-xs text-slate-300">
                <li class="flex items-start">
                    <span class="w-5 h-5 bg-indigo-600/10 text-indigo-400 rounded-full flex items-center justify-center font-bold mr-2.5 border border-indigo-500/20 shrink-0">1</span>
                    <p>Connect your phone, TV, or laptop to the **Oyalo WiFi** network.</p>
                </li>
                <li class="flex items-start">
                    <span class="w-5 h-5 bg-indigo-600/10 text-indigo-400 rounded-full flex items-center justify-center font-bold mr-2.5 border border-indigo-500/20 shrink-0">2</span>
                    <p>Wait for the login screen to pop up automatically (or open browser to **10.0.0.1**).</p>
                </li>
                <li class="flex items-start">
                    <span class="w-5 h-5 bg-indigo-600/10 text-indigo-400 rounded-full flex items-center justify-center font-bold mr-2.5 border border-indigo-500/20 shrink-0">3</span>
                    <p>Enter the Username and Password shown above.</p>
                </li>
            </ol>
            <div class="bg-slate-850 p-3 rounded-lg border border-slate-800 text-center">
                <p class="text-[10px] text-slate-400">An SMS with these credentials was also sent to <span class="text-slate-200 font-bold">{{ $customer->phone_number }}</span></p>
            </div>
        </div>

        <!-- TV MAC Device Info -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl text-center space-y-4">
            <div class="w-10 h-10 bg-indigo-600/10 text-indigo-400 rounded-xl flex items-center justify-center mx-auto border border-indigo-500/20">
                <i class="fa-solid fa-tv"></i>
            </div>
            <div>
                <h3 class="font-bold text-white text-sm">Managing Smart TVs</h3>
                <p class="text-xs text-slate-400 mt-1">If you registered your TV's MAC address, the internet is already active for it! Connect the TV to the WiFi and enjoy streaming immediately.</p>
            </div>
            <div class="flex flex-col items-center gap-3"><a href="{{ route('customer.subscription-status', $location->slug) }}" class="inline-block text-indigo-300 hover:text-indigo-200 text-xs font-bold underline">Check remaining subscription time</a><a href="{{ route('customer.portal', $location->slug) }}" class="inline-block text-indigo-400 hover:text-indigo-300 text-xs font-bold underline">Go to Portal Dashboard to Add more TVs</a></div>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        function copyVoucher(voucher) {
            navigator.clipboard.writeText(voucher).then(() => {
                alert("Voucher copied to clipboard!");
            }).catch(err => {
                console.error("Could not copy:", err);
            });
        }
    </script>
@endsection
