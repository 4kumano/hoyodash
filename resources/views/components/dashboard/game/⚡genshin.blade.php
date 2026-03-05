<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Services\GenshinService;

new #[Layout('layouts.dashboard')] class extends Component {
    public $uid;
    public $stats = [];
    public $characters = [];
    public $errorMessage = '';

    public function mount($uid, GenshinService $genshinService)
    {
        $this->uid = $uid;

        try {
            $data = $genshinService->getUserData($uid);
            dd($data);

            // Periksa jika ada retcode error dari API
            if (isset($data['stats']['retcode']) && $data['stats']['retcode'] !== 0) {
                $this->errorMessage = $data['stats']['message'] ?? 'Gagal mengambil data dari server HoYoLAB.';

                // Handle kasus data private
                if ($data['stats']['retcode'] === 10102) {
                    $this->errorMessage = 'Data akun Anda disembunyikan (Private). Buka pengaturan Privasi HoYoLAB untuk menampilkannya.';
                }
            } else {
                $this->stats = $data['stats'] ?? [];
                $this->characters = $data['characters'] ?? [];
            }
        } catch (\Exception $e) {
            $this->errorMessage = 'Terjadi kesalahan koneksi saat mengambil data: ' . $e->getMessage();
        }
    }
};
?>

<div class="h-full flex flex-col" x-data="{ tab: 'mystat' }">
    <!-- Header Page -->
    <div class="p-8 lg:p-10 shrink-0 border-b border-slate-800/80 bg-[#111827]/40">
        <div class="flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-5">
                <div
                    class="w-16 h-16 rounded-2xl bg-gradient-to-br from-teal-500 to-emerald-700 p-0.5 shadow-lg shadow-teal-900/20">
                    <img src="https://img-os-static.hoyolab.com/communityWeb/upload/1d7dd8f33c5ccdfdeac86e1e86ddd652.png"
                        class="w-full h-full object-cover rounded-[14px]" alt="Genshin">
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-white tracking-wide">Genshin Impact</h1>
                    <p class="text-teal-400 font-medium tracking-wider mt-1 flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2">
                            </path>
                        </svg>
                        UID: {{ $uid }}
                    </p>
                </div>
            </div>
            <a livewire:navigate href="{{ route('dashboard.home') }}"
                class="px-5 py-2.5 bg-slate-800/80 hover:bg-slate-700 text-slate-300 hover:text-white rounded-xl transition-all border border-slate-700/50 shadow-sm flex items-center gap-2 font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Dashboard
            </a>
        </div>

        @if (!$errorMessage)
            <!-- Tabs Navigation -->
            <div class="flex space-x-2 mt-8 -mb-10 lg:-mb-12">
                <button @click="tab = 'mystat'"
                    :class="{ 'bg-teal-500/10 text-teal-400 border-teal-500/50': tab === 'mystat', 'bg-transparent text-slate-400 hover:text-white border-transparent hover:border-slate-600': tab !== 'mystat' }"
                    class="px-6 py-3 border-b-2 font-semibold transition-all focus:outline-none rounded-t-xl text-sm lg:text-base">
                    <span class="flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                            </path>
                        </svg>
                        My Stat
                    </span>
                </button>
                <button @click="tab = 'characters'"
                    :class="{ 'bg-teal-500/10 text-teal-400 border-teal-500/50': tab === 'characters', 'bg-transparent text-slate-400 hover:text-white border-transparent hover:border-slate-600': tab !== 'characters' }"
                    class="px-6 py-3 border-b-2 font-semibold transition-all focus:outline-none rounded-t-xl text-sm lg:text-base">
                    <span class="flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                            </path>
                        </svg>
                        Characters
                    </span>
                </button>
            </div>
        @endif
    </div>

    <!-- Main Content Area -->
    <div class="flex-1 overflow-y-auto p-8 lg:p-10 hide-scrollbar">
        @if ($errorMessage)
            <div class="flex flex-col items-center justify-center py-20">
                <div
                    class="bg-red-500/10 border border-red-500/30 rounded-2xl p-8 max-w-xl text-center shadow-lg relative overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-br from-red-500/5 to-transparent"></div>
                    <svg class="w-16 h-16 text-red-400 mx-auto mb-4 relative z-10" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                        </path>
                    </svg>
                    <h3 class="text-xl font-bold text-red-400 mb-2 relative z-10">Gagal Memuat Data</h3>
                    <p class="text-slate-300 relative z-10">{{ $errorMessage }}</p>
                </div>
            </div>
        @else
            <!-- Tab Content: My Stat -->
            <div x-show="tab === 'mystat'" x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
                style="display: none;" class="space-y-8">

                @php
                    $roleId = $stats['role']['Nickname'] ?? 'Traveler';
                    $roleLevel = $stats['role']['level'] ?? '-';
                    $statsData = $stats['stats'] ?? [];
                    // stats typically contains: active_day_number, achievement_number, spiral_abyss, anemoculus_number, etc
                @endphp

                <!-- Stat Cards -->
                <div
                    class="bg-gradient-to-r from-teal-900/30 to-[#111827] border border-teal-900/50 rounded-2xl p-8 shadow-xl relative overflow-hidden">
                    <!-- Decorative Element -->
                    <div class="absolute -right-20 -top-20 w-80 h-80 bg-teal-500/10 rounded-full blur-3xl"></div>

                    <h2 class="text-xl font-bold text-white mb-6 relative z-10 flex items-center gap-2">
                        <svg class="w-5 h-5 text-teal-400" fill="currentColor" viewBox="0 0 20 20">
                            <path
                                d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z">
                            </path>
                        </svg>
                        Exploration & Battle Stats
                    </h2>

                    <!-- Main Stats -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 relative z-10">
                        <div
                            class="bg-[#1e293b]/60 backdrop-blur-sm border border-slate-700/50 p-5 rounded-xl text-center hover:border-teal-500/30 transition-colors">
                            <p class="text-sm text-slate-400 mb-2">Days Active</p>
                            <p class="text-3xl font-bold text-emerald-400">{{ $statsData['active_day_number'] ?? 0 }}
                            </p>
                        </div>
                        <div
                            class="bg-[#1e293b]/60 backdrop-blur-sm border border-slate-700/50 p-5 rounded-xl text-center hover:border-teal-500/30 transition-colors">
                            <p class="text-sm text-slate-400 mb-2">Achievements</p>
                            <p class="text-3xl font-bold text-yellow-500">{{ $statsData['achievement_number'] ?? 0 }}
                            </p>
                        </div>
                        <div
                            class="bg-[#1e293b]/60 backdrop-blur-sm border border-slate-700/50 p-5 rounded-xl text-center hover:border-teal-500/30 transition-colors">
                            <p class="text-sm text-slate-400 mb-2">Characters</p>
                            <p class="text-3xl font-bold text-blue-400">{{ $statsData['avatar_number'] ?? 0 }}</p>
                        </div>
                        <div
                            class="bg-[#1e293b]/60 backdrop-blur-sm border border-slate-700/50 p-5 rounded-xl text-center hover:border-teal-500/30 transition-colors">
                            <p class="text-sm text-slate-400 mb-2">Spiral Abyss</p>
                            <p class="text-3xl font-bold text-purple-400">{{ $statsData['spiral_abyss'] ?? '-' }}</p>
                        </div>
                        <div
                            class="bg-[#1e293b]/60 backdrop-blur-sm border border-slate-700/50 p-5 rounded-xl text-center hover:border-teal-500/30 transition-colors">
                            <p class="text-sm text-slate-400 mb-2">Waypoints</p>
                            <p class="text-3xl font-bold text-white">{{ $statsData['way_point_number'] ?? 0 }}</p>
                        </div>
                        <div
                            class="bg-[#1e293b]/60 backdrop-blur-sm border border-slate-700/50 p-5 rounded-xl text-center hover:border-teal-500/30 transition-colors">
                            <p class="text-sm text-slate-400 mb-2">Domains</p>
                            <p class="text-3xl font-bold text-white">{{ $statsData['domain_number'] ?? 0 }}</p>
                        </div>
                        <div
                            class="bg-[#1e293b]/60 backdrop-blur-sm border border-slate-700/50 p-5 rounded-xl text-center hover:border-teal-500/30 transition-colors">
                            <p class="text-sm text-slate-400 mb-2">Luxurious Chests</p>
                            <p class="text-3xl font-bold text-white">{{ $statsData['luxurious_chest_number'] ?? 0 }}</p>
                        </div>
                        <div
                            class="bg-[#1e293b]/60 backdrop-blur-sm border border-slate-700/50 p-5 rounded-xl text-center hover:border-teal-500/30 transition-colors">
                            <p class="text-sm text-slate-400 mb-2">Precious Chests</p>
                            <p class="text-3xl font-bold text-white">{{ $statsData['precious_chest_number'] ?? 0 }}
                            </p>
                        </div>
                    </div>

                    <!-- Oculi Section -->
                    <div class="mt-8 border-t border-slate-700/50 pt-6 relative z-10">
                        <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-widest mb-4">Oculi
                            Collections</h3>
                        <div class="flex flex-wrap gap-3">
                            <div
                                class="bg-slate-800/80 border border-slate-700 px-4 py-2 rounded-lg flex items-center justify-between min-w-[140px]">
                                <span class="text-xs text-slate-400 font-medium tracking-wide">Anemo</span>
                                <span
                                    class="font-bold text-teal-300 ml-4">{{ $statsData['anemoculus_number'] ?? 0 }}</span>
                            </div>
                            <div
                                class="bg-slate-800/80 border border-slate-700 px-4 py-2 rounded-lg flex items-center justify-between min-w-[140px]">
                                <span class="text-xs text-slate-400 font-medium tracking-wide">Geo</span>
                                <span
                                    class="font-bold text-yellow-500 ml-4">{{ $statsData['geoculus_number'] ?? 0 }}</span>
                            </div>
                            <div
                                class="bg-slate-800/80 border border-slate-700 px-4 py-2 rounded-lg flex items-center justify-between min-w-[140px]">
                                <span class="text-xs text-slate-400 font-medium tracking-wide">Electro</span>
                                <span
                                    class="font-bold text-purple-400 ml-4">{{ $statsData['electroculus_number'] ?? 0 }}</span>
                            </div>
                            <div
                                class="bg-slate-800/80 border border-slate-700 px-4 py-2 rounded-lg flex items-center justify-between min-w-[140px]">
                                <span class="text-xs text-slate-400 font-medium tracking-wide">Dendro</span>
                                <span
                                    class="font-bold text-green-400 ml-4">{{ $statsData['dendroculus_number'] ?? 0 }}</span>
                            </div>
                            <div
                                class="bg-slate-800/80 border border-slate-700 px-4 py-2 rounded-lg flex items-center justify-between min-w-[140px]">
                                <span class="text-xs text-slate-400 font-medium tracking-wide">Hydro</span>
                                <span
                                    class="font-bold text-blue-400 ml-4">{{ $statsData['hydroculus_number'] ?? 0 }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- World Explorations List -->
                @if (isset($stats['world_explorations']) && !empty($stats['world_explorations']))
                    <div>
                        <h2 class="text-xl font-bold text-white mb-6 flex items-center gap-2">
                            <svg class="w-5 h-5 text-teal-400" fill="none" stroke="currentColor"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                                </path>
                            </svg>
                            World Explorations
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            @foreach ($stats['world_explorations'] as $exp)
                                <div
                                    class="bg-[#1e293b]/50 border border-slate-700/50 rounded-2xl p-5 flex items-center shadow-lg hover:border-teal-500/50 transition-colors group">
                                    @if (!empty($exp['icon']))
                                        <div
                                            class="w-16 h-16 rounded-full overflow-hidden shrink-0 border-2 border-slate-600 bg-slate-800 group-hover:border-teal-400 transition-colors mr-5">
                                            <img src="{{ $exp['icon'] }}" class="w-full h-full object-cover">
                                        </div>
                                    @endif
                                    <div class="flex-1">
                                        <h3 class="text-white font-bold leading-tight">{{ $exp['name'] ?? 'Region' }}
                                        </h3>
                                        <div class="flex justify-between items-end mt-2 mb-1.5">
                                            <span
                                                class="text-[11px] font-semibold text-slate-500 uppercase tracking-widest">Exploration</span>
                                            <span
                                                class="font-bold text-teal-400">{{ ($exp['exploration_percentage'] ?? 0) / 10 }}%</span>
                                        </div>
                                        <div class="w-full bg-slate-800 rounded-full h-2 overflow-hidden">
                                            <div class="bg-gradient-to-r from-teal-500 to-emerald-400 h-2 rounded-full"
                                                style="width: {{ ($exp['exploration_percentage'] ?? 0) / 10 }}%">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <!-- Tab Content: Characters -->
            <div x-show="tab === 'characters'" x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
                style="display: none;">

                @if (empty($characters) || (!isset($characters['avatars']) && empty($characters)))
                    <div class="text-center py-20">
                        <svg class="w-12 h-12 text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z">
                            </path>
                        </svg>
                        <p class="text-slate-500 font-medium">Data karakter kosong atau gagal diambil.</p>
                    </div>
                @else
                    @php
                        // Biasanya API mengembalikan karakter di dalam 'avatars' atau list terstruktur lain
                        $avatars = $characters['avatars'] ?? (isset($characters[0]) ? $characters : []);
                    @endphp

                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-white flex items-center gap-2">
                            <span
                                class="bg-teal-500/20 text-teal-400 px-3 py-1 rounded-lg text-sm">{{ count($avatars) }}</span>
                            Unlocked Characters
                        </h2>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-5">
                        @foreach ($avatars as $char)
                            @php
                                $isFiveStar = ($char['rarity'] ?? 4) == 5;
                                $borderClass = $isFiveStar
                                    ? 'border-orange-500/30 group-hover:border-orange-500'
                                    : 'border-purple-500/30 group-hover:border-purple-500';
                                $bgClass = $isFiveStar
                                    ? 'bg-gradient-to-t from-orange-500/20 to-[#1e293b]'
                                    : 'bg-gradient-to-t from-purple-500/20 to-[#1e293b]';
                                $textRarity = $isFiveStar ? 'text-orange-400' : 'text-purple-400';
                                $constellation = $char['actived_constellation_num'] ?? 0;
                            @endphp

                            <div
                                class="relative rounded-2xl border {{ $borderClass }} {{ $bgClass }} p-1.5 hover:-translate-y-1 hover:shadow-xl hover:shadow-black/50 transition-all cursor-pointer overflow-hidden group">
                                <!-- Level Badge -->
                                <div
                                    class="absolute top-2.5 left-2.5 bg-black/70 backdrop-blur-md rounded border border-white/10 px-1.5 py-0.5 z-10">
                                    <span
                                        class="text-[10px] font-bold text-white tracking-wider">Lv.{{ $char['level'] ?? 1 }}</span>
                                </div>

                                <!-- Friendship / Fetter -->
                                <div
                                    class="absolute top-2.5 right-2.5 bg-black/70 backdrop-blur-md rounded border border-white/10 px-1.5 py-0.5 z-10 flex items-center gap-1">
                                    <svg class="w-3 h-3 text-pink-400 shrink-0" fill="currentColor"
                                        viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z"
                                            clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="text-[10px] font-bold text-white">{{ $char['fetter'] ?? 1 }}</span>
                                </div>

                                <div
                                    class="aspect-[4/5] rounded-xl overflow-hidden relative border border-slate-700/50 bg-[#0b0f19]">
                                    @if (isset($char['image']))
                                        <img src="{{ $char['image'] }}" alt="{{ $char['name'] ?? '' }}"
                                            class="w-full h-full object-cover">
                                    @else
                                        <!-- Placeholder if no image -->
                                        <div class="w-full h-full flex items-center justify-center bg-slate-800">
                                            <svg class="w-8 h-8 text-slate-600" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z">
                                                </path>
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                                <div class="mt-2 text-center pb-1">
                                    <h4 class="text-sm font-bold text-white truncate px-1">
                                        {{ $char['name'] ?? 'Unknown' }}</h4>
                                    <span
                                        class="text-[11px] {{ $textRarity }} font-bold uppercase tracking-wider">C{{ $constellation }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
