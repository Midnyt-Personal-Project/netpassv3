
@php
    $hotspotLoginUrl = $location->hotspot_login_url
        ?? 'http://192.168.88.1/login';

    $message = $location->maintenance_message
        ?: 'We are making a few improvements to bring you a smoother, faster and more reliable WiFi experience. Thank you for your patience. We will be back soon!';
@endphp

@extends('layouts.customer')

@section('title', 'Back Soon | ' . $location->name)
@section('subtitle', $location->name . ' WiFi Portal')

@section('content')
<div class="oyalo-maintenance min-h-[85vh] flex items-center justify-center px-4 py-10 sm:py-14">

    <section class="w-full max-w-md mx-auto">

        {{-- Logo and brand --}}
        {{-- <header class="text-center mb-8">
            <div class="oyalo-logo mx-auto mb-4">
                <img
                    src="/images/logo-192.png"
                    alt="Oyalo WiFi"
                    class="w-full h-full object-contain rounded-2xl"
                >
            </div>

            <h2 class="text-sm font-extrabold tracking-[0.3em] text-white uppercase">
                OYALO <span class="text-emerald-400">WIFI</span>
            </h2>

            <p class="text-xs text-slate-500 mt-2">
                {{ $location->name }}
            </p>
        </header> --}}

        {{-- Maintenance card --}}
        <div class="oyalo-card rounded-[28px] overflow-hidden">

            {{-- Ghana flag accent --}}
            <div class="flex h-1">
                <div class="flex-1 bg-red-500"></div>
                <div class="flex-1 bg-yellow-400"></div>
                <div class="flex-1 bg-green-500"></div>
            </div>

            <div class="px-6 pt-9 pb-8 sm:px-8 sm:pt-10 text-center">

                {{-- Animated illustration --}}
                <div class="maintenance-visual mx-auto mb-7">

                    <div class="orbit orbit-one"></div>
                    <div class="orbit orbit-two"></div>

                    <div class="visual-circle">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.5"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="w-12 h-12"
                            aria-hidden="true"
                        >
                            <path d="M12 3v2"/>
                            <path d="M12 19v2"/>
                            <path d="m4.93 4.93 1.42 1.42"/>
                            <path d="m17.65 17.65 1.42 1.42"/>
                            <path d="M3 12h2"/>
                            <path d="M19 12h2"/>
                            <path d="m4.93 19.07 1.42-1.42"/>
                            <path d="m17.65 6.35 1.42-1.42"/>
                            <circle cx="12" cy="12" r="5"/>
                            <path d="m10 12 1.5 1.5L14.5 10"/>
                        </svg>
                    </div>

                    <span class="visual-status"></span>
                </div>

                {{-- Status label --}}
               

                <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-white leading-tight">
                    We'll be back
                    <span class="block gradient-text">before you know it.</span>
                </h1>

                <p class="text-sm text-slate-400 leading-7 mt-5 max-w-sm mx-auto">
                    {{ $message }}
                </p>

                {{-- Animated activity indicator --}}
                <div class="mt-7 max-w-[190px] mx-auto">
                    <div class="activity-track">
                        <div class="activity-bar"></div>
                    </div>
                    <p class="text-[10px] text-slate-500 mt-3">
                        Making your connection better
                    </p>
                </div>
            </div>

            {{-- Existing voucher login --}}
            <div class="voucher-panel px-6 py-6 sm:px-8">

                <div class="flex items-center gap-3 mb-5">
                    <div class="voucher-icon flex-shrink-0">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.7"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="w-5 h-5"
                            aria-hidden="true"
                        >
                            <rect x="3" y="5" width="18" height="14" rx="2"/>
                            <path d="M7 9h.01M7 15h.01M11 9h6M11 13h6"/>
                        </svg>
                    </div>

                    <div class="text-left">
                        <h3 class="text-sm font-bold text-white">
                            Already have a voucher?
                        </h3>
                        <p class="text-xs text-slate-400 mt-1 leading-5">
                            Try connecting with your existing voucher.
                        </p>
                    </div>
                </div>

                <a
                    href="{{ $hotspotLoginUrl }}"
                    class="login-button group flex items-center justify-center gap-3 w-full rounded-2xl px-5 py-4 text-sm font-extrabold text-white"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="w-5 h-5"
                        aria-hidden="true"
                    >
                        <path d="M5 12.55a11 11 0 0 1 14.08 0"/>
                        <path d="M8.5 16.05a5.5 5.5 0 0 1 7 0"/>
                        <path d="M12 20h.01"/>
                    </svg>

                    <span>Connect to WiFi</span>

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="w-4 h-4 transition-transform duration-200 group-hover:translate-x-1"
                        aria-hidden="true"
                    >
                        <path d="M5 12h14m-7-7 7 7-7 7"/>
                    </svg>
                </a>

                <p class="text-center text-[11px] text-slate-500 leading-5 mt-4">
                    New voucher purchases are temporarily unavailable.
                </p>
            </div>
        </div>

        {{-- Footer --}}
        <footer class="text-center mt-7">
            <div class="flex items-center justify-center gap-2 text-[10px] uppercase tracking-[0.16em] text-slate-500">
                <span class="footer-indicator"></span>
                <span>{{ $location->name }}</span>
                <span class="text-slate-700">·</span>
                <span>Powered by Oyalo</span>
            </div>

            <p class="text-[11px] text-slate-600 mt-3">
                Thank you for being part of our network.
            </p>
        </footer>

    </section>
</div>

<style>
    .oyalo-maintenance {
        position: relative;
        isolation: isolate;
        overflow: hidden;
        background:
            radial-gradient(ellipse at 50% 0%, rgba(16, 185, 129, .12), transparent 48%),
            radial-gradient(ellipse at 0% 100%, rgba(14, 165, 233, .05), transparent 40%);
    }

    .oyalo-logo {
        width: 68px;
        height: 68px;
        padding: 5px;
        border-radius: 21px;
        background: linear-gradient(145deg, rgba(16, 185, 129, .18), rgba(15, 23, 42, .9));
        border: 1px solid rgba(110, 231, 183, .18);
        box-shadow: 0 8px 35px rgba(0, 0, 0, .2);
    }

    .oyalo-card {
        background: rgba(15, 23, 42, .91);
        border: 1px solid rgba(148, 163, 184, .13);
        box-shadow: 0 25px 80px rgba(0, 0, 0, .3);
        backdrop-filter: blur(18px);
        -webkit-backdrop-filter: blur(18px);
    }

    .maintenance-visual {
        position: relative;
        width: 128px;
        height: 128px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .visual-circle {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 82px;
        height: 82px;
        border-radius: 28px;
        color: #6ee7b7;
        border: 1px solid rgba(52, 211, 153, .25);
        background: linear-gradient(145deg, rgba(16, 185, 129, .2), rgba(15, 23, 42, .95));
        box-shadow: 0 0 45px rgba(16, 185, 129, .09);
        animation: float-icon 4s ease-in-out infinite;
    }

    .orbit {
        position: absolute;
        border: 1px dashed rgba(52, 211, 153, .22);
        border-radius: 50%;
    }

    .orbit-one {
        inset: 0;
        animation: spin-orbit 24s linear infinite;
    }

    .orbit-two {
        inset: 12px;
        border-style: solid;
        border-color: rgba(52, 211, 153, .09);
    }

    .visual-status {
        position: absolute;
        z-index: 2;
        top: 15px;
        right: 9px;
        width: 13px;
        height: 13px;
        border: 3px solid #0f172a;
        border-radius: 50%;
        background: #fbbf24;
    }

    .status-label {
        color: #fcd34d;
        background: rgba(251, 191, 36, .07);
        border: 1px solid rgba(251, 191, 36, .18);
    }

    .status-dot {
        width: 7px;
        height: 7px;
        flex-shrink: 0;
        border-radius: 50%;
        background: #fbbf24;
        box-shadow: 0 0 9px rgba(251, 191, 36, .55);
        animation: blink-status 2s ease-in-out infinite;
    }

    .gradient-text {
        background: linear-gradient(90deg, #6ee7b7, #34d399, #a7f3d0);
        -webkit-background-clip: text;
        background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .activity-track {
        height: 4px;
        border-radius: 999px;
        background: rgba(148, 163, 184, .12);
        overflow: hidden;
    }

    .activity-bar {
        width: 38%;
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #059669, #6ee7b7);
        box-shadow: 0 0 12px rgba(52, 211, 153, .3);
        animation: progress-slide 2.4s ease-in-out infinite alternate;
    }

    .voucher-panel {
        border-top: 1px solid rgba(148, 163, 184, .12);
        background: rgba(2, 6, 23, .25);
    }

    .voucher-icon {
        width: 43px;
        height: 43px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 14px;
        color: #6ee7b7;
        background: rgba(16, 185, 129, .09);
        border: 1px solid rgba(52, 211, 153, .15);
    }

    .login-button {
        background: linear-gradient(110deg, #047857, #059669, #10b981);
        border: 1px solid rgba(110, 231, 183, .2);
        box-shadow: 0 8px 25px rgba(5, 150, 105, .18);
        transition: transform .2s ease, box-shadow .2s ease, filter .2s ease;
    }

    .login-button:hover {
        filter: brightness(1.1);
        transform: translateY(-2px);
        box-shadow: 0 12px 30px rgba(5, 150, 105, .25);
    }

    .login-button:active {
        transform: translateY(0);
    }

    .login-button:focus-visible {
        outline: 2px solid #6ee7b7;
        outline-offset: 4px;
    }

    .footer-indicator {
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: #10b981;
        box-shadow: 0 0 8px rgba(16, 185, 129, .55);
    }

    @keyframes float-icon {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-5px); }
    }

    @keyframes spin-orbit {
        to { transform: rotate(360deg); }
    }

    @keyframes progress-slide {
        from { transform: translateX(0); }
        to { transform: translateX(165%); }
    }

    @keyframes blink-status {
        0%, 100% { opacity: 1; }
        50% { opacity: .45; }
    }

    @media (prefers-reduced-motion: reduce) {
        .visual-circle,
        .orbit-one,
        .activity-bar,
        .status-dot {
            animation: none !important;
        }

        .login-button {
            transition: none;
        }
    }

    @media (max-width: 380px) {
        .maintenance-visual {
            transform: scale(.9);
            margin-bottom: 1rem;
        }
    }
</style>
@endsection
