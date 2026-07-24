@extends('layouts.customer')
@section('title', 'Oyalo Hotspot - ' . $location->name)
@section('subtitle', $location->name . ' Location Portal')

@section('content')
    @if($announcements->isNotEmpty())
        <section class="mb-5 overflow-hidden rounded-xl border border-indigo-500/30 bg-indigo-500/10 py-3" aria-label="Location news">
            <div class="news-track whitespace-nowrap text-sm text-indigo-100">
                @foreach([1, 2] as $repeat)
                    @foreach($announcements as $announcement)
                        <span class="inline-flex items-center mx-7"><i class="fa-solid fa-bullhorn text-indigo-300 mr-2"></i>@if($announcement->title)<strong class="mr-2">{{ $announcement->title }}:</strong>@endif {{ $announcement->message }}</span>
                    @endforeach
                @endforeach
            </div>
        </section>
    @endif
    @if($customer && !$customer->isExpired())
        <!-- CUSTOMER DASHBOARD - LOGGED IN WITH ACTIVE PLAN -->
        <div class="space-y-6">
            <!-- Active Subscription Card -->
            <div class="bg-slate-900 border border-indigo-500/20 rounded-2xl p-6 shadow-xl relative overflow-hidden">
                <div class="absolute right-0 top-0 w-24 h-24 bg-indigo-600/10 rounded-full blur-2xl"></div>
                <p class="text-xs text-indigo-400 uppercase font-extrabold tracking-wider mb-1">Your Internet Access is Active</p>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
                    <div>
                        <h2 class="text-3xl font-black text-white flex items-center">
                            {{ $customer->activePackage->name }}
                        </h2>
                        <p class="text-xs text-slate-400 mt-1">
                            Expires on: <span class="text-slate-200 font-medium">{{ $customer->expires_at->format('d M Y H:i') }}</span>
                        </p>
                    </div>
                    <div class="bg-slate-800/80 border border-slate-700 rounded-xl p-3 flex space-x-4">
                        <div class="text-center">
                            <p class="text-[10px] text-slate-400 uppercase font-bold">Username</p>
                            <p class="font-mono text-sm text-indigo-400 font-extrabold">{{ $customer->username }}</p>
                        </div>
                        <div class="text-center">
                            <p class="text-[10px] text-slate-400 uppercase font-bold">Password</p>
                            <p class="font-mono text-sm text-slate-200 font-extrabold">{{ $customer->password }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Device MAC Address Manager (Smart TV, Android Box, Phone) -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
                <div class="flex items-center space-x-3 mb-4">
                    <div class="w-10 h-10 bg-indigo-600/10 rounded-xl flex items-center justify-center border border-indigo-500/20 text-indigo-400">
                        <i class="fa-solid fa-tv"></i>
                    </div>
                    <div>
                        <h3 class="text-lg font-extrabold text-white">Smart Device MAC Login (No Password TV Feature)</h3>
                        <p class="text-xs text-slate-400">Connect Smart TVs, Android boxes, and laptops directly without opening a portal or typing passwords.</p>
                    </div>
                </div>

                <!-- List of Devices -->
                <div class="space-y-3 mb-6">
                    @forelse($customer->devices as $dev)
                        <div class="bg-slate-800/40 border border-slate-700/50 p-4 rounded-xl flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 rounded-lg bg-indigo-500/10 flex items-center justify-center text-indigo-400">
                                    <i class="fa-solid fa-desktop text-xs"></i>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-white">{{ $dev->name }}</p>
                                    <p class="text-xs font-mono text-indigo-400 uppercase">{{ $dev->mac_address }}</p>
                                </div>
                            </div>
                            <div class="flex items-center space-x-4">
                                <span class="inline-block px-2.5 py-0.5 rounded-full text-[10px] uppercase tracking-wider font-extrabold bg-teal-500/10 text-teal-400">
                                    Connected
                                </span>
                                <form action="{{ route('customer.device.remove', ['slug' => $location->slug, 'id' => $dev->id]) }}" method="POST" onsubmit="return confirm('Remove this device?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-rose-400 hover:text-rose-500 text-xs transition p-1">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="py-6 text-center border-2 border-dashed border-slate-800 rounded-xl">
                            <p class="text-sm text-slate-500">No smart devices registered yet.</p>
                        </div>
                    @endforelse
                </div>

                @if($customer->devices->count() < 3)
                    <!-- Register Device Form -->
                    <form action="{{ route('customer.device.register', $location->slug) }}" method="POST" class="bg-slate-850 border border-slate-800 p-4 rounded-xl space-y-3">
                        @csrf
                        <input type="hidden" name="username" value="{{ $customer->username }}">
                        <p class="text-xs font-bold text-slate-300 uppercase tracking-wide">Register New Smart TV / Device</p>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] text-slate-400 uppercase font-bold mb-1">Device Name</label>
                                <input type="text" name="device_name" placeholder="e.g. Living Room TV, Samsung Box" required class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-xs focus:outline-none focus:border-indigo-500">
                            </div>
                            <div>
                                <label class="block text-[10px] text-slate-400 uppercase font-bold mb-1">Device MAC Address</label>
                                <input type="text" name="mac_address" placeholder="e.g. AA:BB:CC:11:22:33" required class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2.5 text-white text-xs font-mono focus:outline-none focus:border-indigo-500">
                            </div>
                        </div>
                        <button class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-2 px-4 rounded-lg text-xs transition duration-200 flex items-center justify-center space-x-1">
                            <i class="fa-solid fa-circle-plus"></i>
                            <span>Register & Activate Device</span>
                        </button>
                    </form>
                @else
                    <p class="text-xs text-amber-500 text-center bg-amber-500/15 border border-amber-500/20 py-2 px-3 rounded-lg">
                        You have reached the maximum limit of 3 registered smart devices on this plan.
                    </p>
                @endif
            </div>

            <!-- Buy Another Plan / Extend Access -->
            <div class="text-center pt-4">
                <a href="#packages" onclick="document.getElementById('packages-section').classList.remove('hidden'); this.remove();" class="text-indigo-400 hover:text-indigo-300 text-sm font-bold underline transition">
                    Want to buy another plan or extend? Click here.
                </a>
            </div>
        </div>
    @endif

    <!-- MAIN LANDING LANDING / PACKAGE SELECT SECTION -->
    <div id="packages-section" class="{{ $customer && !$customer->isExpired() ? 'hidden mt-10 border-t border-slate-800 pt-10' : '' }} space-y-8">
        <div class="text-center max-w-xl mx-auto space-y-3">
            <h2 class="text-3xl font-black text-white tracking-tight sm:text-4xl">Select Your Internet Plan</h2>
            <p class="text-slate-400 text-sm">Select a subscription package to instantly access high-speed internet. Quick payment processing through Paystack.</p>
        </div>

        <!-- Plans Cards Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6" id="packages">
            @forelse($packages as $pkg)
                <div class="bg-slate-900 border border-slate-800 hover:border-indigo-500/40 p-6 rounded-2xl flex flex-col justify-between shadow-xl transition relative group overflow-hidden">
                    <div class="absolute -right-4 -top-4 w-12 h-12 bg-indigo-600/10 rounded-full group-hover:scale-150 transition duration-300"></div>
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-xs bg-slate-800 text-indigo-400 font-extrabold py-1 px-3 rounded-full uppercase tracking-wider">
                                @if($pkg->duration_minutes >= 1440)
                                    {{ round($pkg->duration_minutes / 1440, 1) }} Day(s)
                                @else
                                    {{ $pkg->duration_minutes }} Mins
                                @endif
                            </span>
                            @if($pkg->speed_limit_down)
                                <span class="text-[10px] bg-emerald-500/10 text-emerald-400 font-extrabold py-1 px-2.5 rounded uppercase">
                                    {{ $pkg->speed_limit_down }} Speed
                                </span>
                            @endif
                        </div>
                        <h3 class="text-xl font-bold text-white mb-2">{{ $pkg->name }}</h3>
                        <div class="flex items-baseline space-x-1 mb-4">
                            <span class="text-3xl font-black text-white">{{ number_format($pkg->price, 1) }}</span>
                            <span class="text-slate-400 text-xs font-bold">GHS</span>
                        </div>
                        <ul class="text-xs text-slate-400 space-y-2 mb-6 border-t border-slate-800/60 pt-4">
                            <li class="flex items-center space-x-2">
                                <i class="fa-solid fa-circle-check text-indigo-500"></i>
                                <span>{{ $pkg->data_limit_mb ? number_format($pkg->data_limit_mb) . ' MB Data Limit' : 'Unlimited Data Usage' }}</span>
                            </li>
                            <li class="flex items-center space-x-2">
                                <i class="fa-solid fa-circle-check text-indigo-500"></i>
                                <span>No throttle speed guarantees</span>
                            </li>
                            <li class="flex items-center space-x-2">
                                <i class="fa-solid fa-circle-check text-indigo-500"></i>
                                <span>Support for 3 Smart TV devices</span>
                            </li>
                        </ul>
                    </div>
                    
                    <button onclick="openCheckoutModal({{ $pkg->id }}, '{{ $pkg->name }}', {{ $pkg->price }})" class="w-full bg-slate-800 hover:bg-indigo-600 text-white group-hover:bg-indigo-600 font-extrabold py-3 px-4 rounded-xl text-xs tracking-wider transition-all duration-300 flex items-center justify-center space-x-1.5 shadow-md">
                        <span>SELECT PLAN</span>
                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                    </button>
                </div>
            @empty
                <div class="col-span-full py-10 text-center bg-slate-900 border border-slate-800 rounded-2xl">
                    <p class="text-slate-400 text-sm">No internet plans defined for this location yet.</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- CHECKOUT MODAL -->
    <div id="checkout-modal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-slate-900 border border-slate-800 w-full max-w-md rounded-2xl shadow-2xl p-6 relative overflow-hidden">
            <button onclick="closeCheckoutModal()" class="absolute right-4 top-4 text-slate-400 hover:text-white transition">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>

            <div class="mb-5">
                <h3 class="text-xl font-extrabold text-white">Purchase Plan</h3>
                <p class="text-xs text-slate-400 mt-1">Provide your details to complete your secure split payment via Paystack.</p>
            </div>

            <form action="{{ route('customer.checkout', $location->slug) }}" method="POST" class="space-y-4">
                @csrf
                <input type="hidden" name="package_id" id="modal-package-id">

                <!-- Selected Plan Preview -->
                <div class="bg-slate-850 border border-slate-800 p-4 rounded-xl flex items-center justify-between">
                    <div>
                        <p class="text-[10px] text-slate-400 uppercase font-bold">Selected Plan</p>
                        <p class="font-extrabold text-white text-base" id="modal-package-name"></p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] text-slate-400 uppercase font-bold">Total Price</p>
                        <p class="font-black text-indigo-400 text-xl" id="modal-package-price"></p>
                    </div>
                </div>

                <!-- Phone input -->
                <div>
                    <label class="block text-xs text-slate-400 uppercase font-bold mb-1">Mobile Money Number</label>
                    <input type="tel" name="phone_number" placeholder="e.g. 0244123456" required class="w-full bg-slate-800 border border-slate-700 rounded-xl p-3 text-white text-sm font-semibold focus:outline-none focus:border-indigo-500">
                    <p class="text-[10px] text-slate-500 mt-1">This number will receive hotspot login credentials via SMS.</p>
                </div>

                <!-- MAC address feature checkbox -->
                <div class="bg-slate-850 border border-slate-800 p-3.5 rounded-xl">
                    <label class="flex items-start space-x-3 cursor-pointer">
                        <input type="checkbox" onchange="toggleMacInputs(this)" class="mt-1 accent-indigo-600 rounded">
                        <div>
                            <span class="text-xs font-bold text-slate-200">Register Smart TV / Laptop directly</span>
                            <p class="text-[10px] text-slate-400">If you are on your TV or laptop, activate this to grant internet access instantly without needing to enter the password.</p>
                        </div>
                    </label>
                    
                    <div id="mac-inputs" class="hidden mt-3 space-y-3 pt-3 border-t border-slate-800/80">
                        <div>
                            <label class="block text-[10px] text-slate-400 uppercase font-bold mb-1">TV / Device Name</label>
                            <input type="text" name="device_name" id="device_name" placeholder="e.g. Smart TV, LG TV" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2 text-white text-xs">
                        </div>
                        <div>
                            <label class="block text-[10px] text-slate-400 uppercase font-bold mb-1">Device MAC Address</label>
                            <input type="text" name="mac_address" id="mac_address" placeholder="e.g. 1A:2B:3C:4D:5E:6F" class="w-full bg-slate-800 border border-slate-700 rounded-lg p-2 text-white text-xs font-mono">
                            <p class="text-[9px] text-slate-500 mt-0.5">Found in your TV network or status settings.</p>
                        </div>
                    </div>
                </div>

                <button class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-extrabold py-3 px-4 rounded-xl text-xs tracking-wider transition shadow-lg flex items-center justify-center space-x-2">
                    <i class="fa-solid fa-lock text-[10px]"></i>
                    <span>PROCEED TO SECURE PAYMENT</span>
                </button>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
    <script>
        function openCheckoutModal(pkgId, name, price) {
            document.getElementById('modal-package-id').value = pkgId;
            document.getElementById('modal-package-name').innerText = name;
            document.getElementById('modal-package-price').innerText = price.toFixed(2) + " GHS";
            document.getElementById('checkout-modal').classList.remove('hidden');
        }

        function closeCheckoutModal() {
            document.getElementById('checkout-modal').classList.add('hidden');
        }

        function toggleMacInputs(cb) {
            const inputs = document.getElementById('mac-inputs');
            const macIn = document.getElementById('mac_address');
            const devIn = document.getElementById('device_name');
            if (cb.checked) {
                inputs.classList.remove('hidden');
                macIn.required = true;
                devIn.required = true;
            } else {
                inputs.classList.add('hidden');
                macIn.required = false;
                devIn.required = false;
            }
        }
    </script>
@endsection
