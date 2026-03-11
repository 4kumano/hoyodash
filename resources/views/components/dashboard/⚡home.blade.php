<?php

use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

new #[Layout('layouts.dashboard')] #[Title('Dashboard')] class extends Component {
    public $user = [
        // Using generic data for the overall profile for now.
        // We'll update the user name if we find a nickname in the accounts later.
        'name' => 'Commander',
        'avatar' => 'https://ui-avatars.com/api/?name=Commander&background=2563eb&color=fff',
        'hoyolab_id' => 'Active',
    ];
    public $games = [];
    public $news = [];
    public $redeemCodes = [];

    // Game name → Game ID mapping for redeem code APIs
    const GAME_IDS = [
        'Genshin Impact' => 2,
        'Honkai: Star Rail' => 6,
        'Zenless Zone Zero' => 8,
    ];

    public $builds = [['name' => 'Acheron', 'game' => 'Honkai: Star Rail', 'tier' => 'T0', 'role' => 'Main DPS', 'path' => 'Nihility', 'bg' => 'bg-purple-950/50'], ['name' => 'Arlecchino', 'game' => 'Genshin Impact', 'tier' => 'T0', 'role' => 'Main DPS', 'path' => 'Pyro', 'bg' => 'bg-red-950/50'], ['name' => 'Ellen Joe', 'game' => 'Zenless Zone Zero', 'tier' => 'S', 'role' => 'Attack', 'path' => 'Ice', 'bg' => 'bg-blue-950/50']];

    public function mount()
    {
        $savedAccounts = session('hoyolab_accounts', []);
        $hoyolabService = app(\App\Services\HoyolabService::class);

        $mappedGames = [];
        $mappedNews = [];

        foreach ($savedAccounts as $acc) {
            $biz = $acc['game_biz'] ?? '';
            $gameName = 'Unknown Game';
            $color = 'from-slate-700 to-slate-900';
            $iconColor = 'text-slate-400';

            // Map Styling & Nama berdasar game_biz
            if (str_contains($biz, 'hk4e')) {
                $gameName = 'Genshin Impact';
                $color = 'from-emerald-900 to-teal-900';
                $iconColor = 'text-teal-400';
            } elseif (str_contains($biz, 'hkrpg')) {
                $gameName = 'Honkai: Star Rail';
                $color = 'from-indigo-900 to-purple-900';
                $iconColor = 'text-indigo-400';
            } elseif (str_contains($biz, 'bh3')) {
                $gameName = 'Honkai Impact 3rd';
                $color = 'from-blue-900 to-sky-900';
                $iconColor = 'text-blue-400';
            } elseif (str_contains($biz, 'nap')) {
                $gameName = 'Zenless Zone Zero';
                $color = 'from-green-900 to-lime-900';
                $iconColor = 'text-lime-400';
            }

            $mappedGames[] = [
                'name' => $gameName,
                'uid' => $acc['game_uid'],
                'server' => $acc['region_name'],
                'nickname' => $acc['nickname'],
                'level' => $acc['level'],
                'color' => $color,
                'icon_color' => $iconColor,
                'icon_url' => $hoyolabService->getIcon($biz),
                // Setup default daily task API
                'resin' => '?',
                'max_resin' => '?',
                'resin_recovery_time' => '?',
                'recovery_formatted' => '?',
                'daily_task_finished' => '?',
                'daily_task_total' => '?',
                'daily_checkin' => [],
            ];

            // Setup Dummy News Structure per Game
            // Nantinya di HoyolabService diisikan array berita asli
            $rawRegion = $acc['region'] ?? '';
            $rawUid = $acc['game_uid'] ?? 0;
            $rawLevel = $acc['level'] ?? 1;

            $gameNewsList = $hoyolabService->getNews($biz, $rawUid, $rawRegion, $rawLevel);

            if (!empty($gameNewsList)) {
                // Batasi hanya 3 berita per game
                $mappedNews[$gameName] = array_slice($gameNewsList, 0, 3);
            }
        }

        // Kalau ada session, gunakan itu. Kalau tidak ada, kosongkan.
        $this->games = $mappedGames;
        $this->news = $mappedNews;
    }

    public function loadData($cookie)
    {
        set_time_limit(120);

        $genshinService = app(\App\Services\GenshinService::class);
        $mappedGames = $this->games;

        foreach ($mappedGames as &$game) {
            if ($game['name'] === 'Genshin Impact') {
                $dailyNote = $genshinService->getDailyNote($cookie, $game['uid']);

                if (isset($dailyNote['retcode']) && $dailyNote['retcode'] === 0) {
                    $noteData = $dailyNote['data'] ?? [];
                    $game['resin'] = $noteData['current_resin'] ?? '?';
                    $game['max_resin'] = $noteData['max_resin'] ?? '?';

                    $recoveryTime = $noteData['resin_recovery_time'] ?? 0;
                    $game['resin_recovery_time'] = $recoveryTime;
                    if (is_numeric($recoveryTime) && $recoveryTime > 0) {
                        $hours = floor($recoveryTime / 3600);
                        $minutes = floor(($recoveryTime % 3600) / 60);
                        $game['recovery_formatted'] = "{$hours}h {$minutes}m";
                    } elseif ($recoveryTime == 0) {
                        $game['recovery_formatted'] = 'Fully capped';
                    }

                    if (isset($noteData['daily_task'])) {
                        $game['daily_task_finished'] = $noteData['daily_task']['finished_num'] ?? 0;
                        $game['daily_task_total'] = $noteData['daily_task']['total_num'] ?? 4;
                    }
                }

                // Fetch Daily Check-In data
                $checkIn = $genshinService->getDailyCheckIn($cookie);
                if (isset($checkIn['retcode']) && $checkIn['retcode'] === 0) {
                    $game['daily_checkin'] = $checkIn;
                }
            }
        }
        $this->games = $mappedGames;
    }

    /**
     * Load redeem codes from Hoyolab + GamesRadar, filtering out already-redeemed codes.
     *
     * @param string $redeemedJson  JSON from localStorage, e.g. {"genshin":"AAA,BBB","starrail":"CCC"}
     */
    public function loadRedeemCodes(string $redeemedJson = '{}')
    {
        set_time_limit(120);

        $hoyolabService = app(\App\Services\HoyolabService::class);
        $gameRadarService = app(\App\Services\GameRadarService::class);

        // Parse already-redeemed from localStorage
        $redeemed = json_decode($redeemedJson, true) ?: [];

        $allCodes = [];

        foreach ($this->games as $game) {
            $gameName = $game['name'];
            $gameId = self::GAME_IDS[$gameName] ?? null;

            if (!$gameId) {
                continue;
            }

            // Storage key: lowercase, no spaces/colons
            $storageKey = strtolower(str_replace([' ', ':', '-'], '', $gameName));
            $redeemedCodes = [];
            if (!empty($redeemed[$storageKey])) {
                $redeemedCodes = array_map('trim', explode(',', $redeemed[$storageKey]));
            }

            $gameCodes = [];
            $seenCodes = [];

            // 1. Fetch from Hoyolab API
            $hoyolabResult = $hoyolabService->parseByHoyolab($gameId);
            if (isset($hoyolabResult['retcode']) && $hoyolabResult['retcode'] === 0) {
                foreach ($hoyolabResult['codes'] as $code) {
                    $upper = strtoupper(trim($code));
                    if (!empty($upper) && !in_array($upper, $seenCodes) && !in_array($upper, $redeemedCodes)) {
                        $gameCodes[] = ['code' => $upper, 'rewards' => ''];
                        $seenCodes[] = $upper;
                    }
                }
            }

            // 2. Fetch from GamesRadar
            $pocketResult = $gameRadarService->parseByGameRadar($gameId);
            if (isset($pocketResult['retcode']) && $pocketResult['retcode'] === 0) {
                foreach ($pocketResult['codes'] as $entry) {
                    $upper = strtoupper(trim($entry['code']));
                    if (empty($upper) || in_array($upper, $redeemedCodes)) {
                        continue;
                    }
                    if (in_array($upper, $seenCodes)) {
                        // Code already exists from Hoyolab, update rewards if available
                        if (!empty($entry['rewards'])) {
                            foreach ($gameCodes as &$existing) {
                                if ($existing['code'] === $upper && empty($existing['rewards'])) {
                                    $existing['rewards'] = $entry['rewards'];
                                }
                            }
                        }
                        continue;
                    }
                    $gameCodes[] = ['code' => $upper, 'rewards' => $entry['rewards'] ?? ''];
                    $seenCodes[] = $upper;
                }
            }

            if (!empty($gameCodes)) {
                $allCodes[$gameName] = [
                    'codes' => $gameCodes,
                    'color' => $game['color'],
                    'icon_color' => $game['icon_color'],
                    'icon_url' => $game['icon_url'],
                    'storage_key' => $storageKey,
                ];
            }
        }

        $this->redeemCodes = $allCodes;
    }

    /**
     * Redeem a single code via the Hoyoverse cdkey API.
     *
     * @param string $code The redeem code (cdkey)
     * @return array{retcode: int, message: string, status: string, description: string}
     */
    public function redeemSingleCode(string $code, string $storageKey): array
    {
        $cookie = session('hoyolab_cookie');
        if (empty($cookie)) {
            return [
                'retcode' => -1,
                'message' => 'Cookie tidak ditemukan.',
                'status' => 'credentials_error',
                'description' => 'Silakan login ulang.',
            ];
        }

        // Map storage key to game configuration
        $gameConfig = [
            'genshinimpact' => ['biz_prefix' => 'hk4e', 'game_code' => 'hk4e', 'game_biz' => 'hk4e_global'],
            'honkaistarrail' => ['biz_prefix' => 'hkrpg', 'game_code' => 'hkrpg', 'game_biz' => 'hkrpg_global'],
            'zenlesszonezero' => ['biz_prefix' => 'nap', 'game_code' => 'nap', 'game_biz' => 'nap_global'],
        ];

        $config = $gameConfig[$storageKey] ?? null;
        if (!$config) {
            return [
                'retcode' => -1,
                'message' => 'Game tidak dikenali.',
                'status' => 'invalid',
                'description' => 'Redeem code belum didukung untuk game ini.',
            ];
        }

        // Find matching game account from session
        $accounts = session('hoyolab_accounts', []);
        $gameUid = null;
        $gameRegion = null;
        foreach ($accounts as $acc) {
            if (str_contains($acc['game_biz'] ?? '', $config['biz_prefix'])) {
                $gameUid = $acc['game_uid'] ?? null;
                $gameRegion = $acc['region'] ?? null;
                break;
            }
        }

        if (empty($gameUid) || empty($gameRegion)) {
            return [
                'retcode' => -1,
                'message' => 'Akun game tidak ditemukan.',
                'status' => 'credentials_error',
                'description' => 'Tidak ada akun yang terhubung untuk game ini.',
            ];
        }

        $hoyolabService = app(\App\Services\HoyolabService::class);
        return $hoyolabService->redeemCode($cookie, $gameUid, $gameRegion, $code, $config['game_code'], $config['game_biz']);
    }
};
?>


<!-- Main Body -->
<div class="flex-1 overflow-y-auto p-5 md:p-8 lg:p-10 space-y-10" x-data="{
    gamesLoaded: false,
    codesLoaded: false,
    init() {
        let cookie = localStorage.getItem('hoyolab_cookie');
        let isLogin = localStorage.getItem('isLogin');
        if (!cookie || !isLogin) {
            window.location.href = '{{ route('login') }}';
            return;
        }

        // Notify: fetching game data
        $dispatch('notify', { type: 'info', message: 'Sedang Mengambil data Live Game Status...' });

        $wire.loadData(cookie).then(() => {
            this.gamesLoaded = true;
            $dispatch('notify', { type: 'success', message: 'Sukses Mendapatkan data \'Live Game Status\'' });
        });

        // Load redeem codes, passing already-redeemed from localStorage
        let redeemed = localStorage.getItem('Redeem') || '{}';

        $dispatch('notify', { type: 'info', message: 'Sedang Mengambil data Redeem Codes...' });

        $wire.loadRedeemCodes(redeemed).then(() => {
            this.codesLoaded = true;
            $dispatch('notify', { type: 'success', message: 'Sukses Mendapatkan data \'Redeem Codes\'' });
        });
    }
}">

    <!-- Hero / Daily Summary Banner -->
    <section name="Hero"
        class="relative rounded-2xl overflow-hidden shadow-2xl shadow-blue-900/10 transform-gpu isolate">
        <div class="absolute inset-0 bg-gradient-to-r from-blue-900/80 to-[#111827] z-0"></div>
        <!-- Decorative background elements -->
        <div class="absolute -top-24 -right-24 w-96 h-96 bg-blue-500/20 rounded-full blur-3xl z-0 transform-gpu"></div>
        <div class="absolute bottom-0 right-10 w-64 h-64 bg-purple-500/10 rounded-full blur-3xl z-0 transform-gpu">
        </div>

        <div
            class="relative z-10 p-8 lg:p-12 flex flex-col md:flex-row items-start md:items-center justify-between gap-6 px-10">
            <div x-data="{
                userName: '{{ $user['name'] }}',
                init() {
                    // Listener if Livewire updates the data
                    Livewire.on('update-user-info', (data) => {
                        let payload = Array.isArray(data) ? data[0] : data;
                        if (payload && payload.userInfo) {
                            localStorage.setItem('hoyolab_user_info', JSON.stringify(payload.userInfo));
                            if (payload.userInfo.nickname) {
                                this.userName = payload.userInfo.nickname;
                            }
                        }
                    });
            
                    // Format fallback dr LocalStorage jika halaman baru diload cepat
                    let savedInfo = localStorage.getItem('hoyolab_user_info');
                    if (savedInfo) {
                        try {
                            let parsed = JSON.parse(savedInfo);
                            if (parsed.nickname) this.userName = parsed.nickname;
                        } catch (e) {}
                    }
                }
            }">
                <h1 class="text-2xl md:text-3xl lg:text-4xl font-bold text-white mb-2">Welcome Back, <span
                        class="text-transparent bg-clip-text bg-gradient-to-r from-blue-400 to-teal-300"
                        x-text="userName"></span>
                </h1>
                <p class="text-slate-300 text-lg max-w-xl">Your daily commissions and expeditions await. Check
                    your real-time resins and trailblaze power below.</p>
            </div>
            <div class="flex gap-4">
                <button
                    class="px-6 py-3 bg-white text-blue-900 font-semibold rounded-xl hover:bg-slate-100 transition-all shadow-lg hover:shadow-xl hover:-translate-y-0.5 transform">
                    Daily Check-In
                </button>
                <button
                    class="px-6 py-3 bg-white/10 text-white font-semibold rounded-xl backdrop-blur-md border border-white/20 hover:bg-white/20 transition-all">
                    View Battle Records
                </button>
            </div>
        </div>
    </section>

    <!-- Real-time Game Data Grid -->
    <section name="games">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-white">Live Game Status</h2>
            <div class="flex items-center gap-3">
                <span x-show="!gamesLoaded" x-transition
                    class="flex items-center gap-2 text-xs text-blue-400 font-medium">
                    <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                    Memuat data...
                </span>
                <a href="#" class="text-blue-400 hover:text-blue-300 text-sm font-medium transition-colors">Manage
                    Games
                    &rarr;</a>
            </div>
        </div>

        {{-- Skeleton Loading --}}
        <div x-show="!gamesLoaded" x-transition:leave="transition ease-in duration-300"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @for ($i = 0; $i < min(count($games), 3); $i++)
                <div class="bg-[#1e293b]/50 backdrop-blur-sm border border-slate-700/50 rounded-2xl p-6 animate-pulse">
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="w-10 h-10 rounded-xl bg-slate-700"></div>
                        <div class="flex-1">
                            <div class="h-4 bg-slate-700 rounded w-3/4 mb-2"></div>
                            <div class="h-3 bg-slate-700/60 rounded w-1/2"></div>
                        </div>
                        <div class="w-3 h-3 rounded-full bg-slate-700"></div>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between mb-1.5">
                                <div class="h-3 bg-slate-700 rounded w-24"></div>
                                <div class="h-3 bg-slate-700 rounded w-16"></div>
                            </div>
                            <div class="w-full bg-slate-800 rounded-full h-2.5">
                                <div class="bg-slate-700 h-2.5 rounded-full w-2/3"></div>
                            </div>
                            <div class="h-3 bg-slate-700/40 rounded w-36 mt-2"></div>
                        </div>
                        <div class="bg-slate-800/50 rounded-lg p-3 flex justify-between items-center">
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 bg-slate-700 rounded"></div>
                                <div class="h-3 bg-slate-700 rounded w-20"></div>
                            </div>
                            <div class="h-3 bg-slate-700 rounded w-10"></div>
                        </div>
                    </div>
                </div>
            @endfor
        </div>

        {{-- Actual Game Cards --}}
        <div x-show="gamesLoaded" x-transition:enter="transition ease-out duration-500"
            x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
            class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" style="display: none;">
            @foreach ($games as $game)
                <div
                    class="bg-[#1e293b]/50 backdrop-blur-sm border border-slate-700/50 rounded-2xl p-6 hover:border-slate-500/50 transition-all shadow-lg hover:shadow-xl group relative overflow-hidden">
                    <!-- BG Gradient decorative -->
                    <div
                        class="absolute inset-0 bg-gradient-to-br {{ $game['color'] }} opacity-0 group-hover:opacity-10 transition-opacity duration-500">
                    </div>

                    <div class="flex items-center justify-between mb-4 relative z-10">
                        <div class="flex items-center space-x-3">
                            <div
                                class="w-10 h-10 rounded-xl bg-gradient-to-br {{ $game['color'] }} flex items-center justify-center p-0.5 shadow-md overflow-hidden">
                                <img src="{{ $game['icon_url'] }}" alt="{{ $game['name'] }}"
                                    class="w-full h-full object-cover rounded-[10px]" loading="lazy">
                            </div>
                            <div>
                                <h3 class="font-bold text-white leading-tight">{{ $game['name'] }}</h3>
                                <p class="text-xs text-slate-400">UID: {{ $game['uid'] }} •
                                    {{ $game['server'] }}
                                </p>
                            </div>
                        </div>
                        <span class="flex h-3 w-3 relative">
                            <span
                                class="animate-ping absolute inline-flex h-full w-full rounded-full {{ $game['resin'] >= $game['max_resin'] ? 'bg-red-400' : 'bg-green-400' }} opacity-75"></span>
                            <span
                                class="relative inline-flex rounded-full h-3 w-3 {{ $game['resin'] >= $game['max_resin'] ? 'bg-red-500' : 'bg-green-500' }}"></span>
                        </span>
                    </div>

                    <div class="space-y-4 relative z-10">
                        <div>
                            <div class="flex justify-between text-sm mb-1.5">
                                <span class="text-slate-300 font-medium">Energy / Resin</span>
                                <span
                                    class="font-bold {{ $game['resin'] !== '?' && $game['max_resin'] !== '?' && $game['resin'] >= $game['max_resin'] ? 'text-red-400' : 'text-white' }}">{{ $game['resin'] }}
                                    <span class="text-slate-500 font-normal">/
                                        {{ $game['max_resin'] }}</span></span>
                            </div>
                            <div class="w-full bg-slate-800 rounded-full h-2.5 overflow-hidden">
                                @if ($game['resin'] !== '?' && $game['max_resin'] !== '?')
                                    <div class="{{ $game['resin'] >= $game['max_resin'] ? 'bg-red-500' : 'bg-gradient-to-r from-blue-500 to-teal-400' }} h-2.5 rounded-full"
                                        style="width: {{ ($game['resin'] / $game['max_resin']) * 100 }}%">
                                    </div>
                                @else
                                    <div class="bg-slate-700 h-2.5 rounded-full w-full animate-pulse"></div>
                                @endif
                            </div>
                            @if ($game['resin'] !== '?' && $game['max_resin'] !== '?' && $game['resin'] >= $game['max_resin'])
                                <p class="text-xs text-red-400 mt-2 font-medium">Energy is fully capped!</p>
                            @elseif($game['resin'] === '?')
                                <p class="text-xs text-slate-500 mt-2">Connecting to game server...</p>
                            @else
                                <p class="text-xs text-slate-400 mt-2">Recovers fully in
                                    ~{{ $game['recovery_formatted'] ?? '4h 20m' }}</p>
                            @endif
                        </div>

                        <div class="flex justify-between items-center bg-slate-800/50 rounded-lg p-3">
                            <div class="flex items-center space-x-2">
                                <svg class="w-4 h-4 text-orange-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M10 2a4 4 0 00-4 4v1H5a1 1 0 00-.994.89l-1 9A1 1 0 004 18h12a1 1 0 00.994-1.11l-1-9A1 1 0 0015 7h-1V6a4 4 0 00-4-4zm2 5V6a2 2 0 10-4 0v1h4zm-6 3a1 1 0 112 0 1 1 0 01-2 0zm7-1a1 1 0 100 2 1 1 0 000-2z"
                                        clip-rule="evenodd"></path>
                                </svg>
                                <span class="text-sm text-slate-300">Daily Tasks</span>
                            </div>
                            <span
                                class="text-sm font-bold text-white">{{ $game['daily_task_finished'] !== '?' ? $game['daily_task_finished'] : '-' }}
                                <span class="text-slate-500">/
                                    {{ $game['daily_task_total'] !== '?' ? $game['daily_task_total'] : '-' }}</span>
                                @if (($game['daily_task_finished'] ?? 0) === ($game['daily_task_total'] ?? 4) && $game['daily_task_finished'] !== '?')
                                    <span class="text-green-400 ml-1">✓</span>
                                @endif
                            </span>
                        </div>

                        {{-- Daily Check-In (inline) --}}
                        @if (!empty($game['daily_checkin']) && !empty($game['daily_checkin']['today_reward']))
                            @php $checkin = $game['daily_checkin']; @endphp
                            <div class="mt-1 pt-4 border-t border-slate-700/40">
                                <div class="flex items-center justify-between mb-3">
                                    <span
                                        class="text-xs font-semibold text-amber-400 uppercase tracking-wider flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd"
                                                d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z"
                                                clip-rule="evenodd"></path>
                                        </svg>
                                        Daily Check-In
                                    </span>
                                    <span class="text-[10px] text-slate-500 font-medium">Day
                                        {{ $checkin['today_day'] }}/{{ count($checkin['awards']) }}</span>
                                </div>

                                <div class="flex items-center gap-3 bg-slate-800/50 rounded-xl p-3">
                                    {{-- Reward icon --}}
                                    <div class="relative shrink-0">
                                        <div
                                            class="w-12 h-12 rounded-xl bg-gradient-to-br from-amber-500/20 to-orange-500/10 border border-amber-500/30 flex items-center justify-center p-1.5">
                                            <img src="{{ $checkin['today_reward']['icon'] }}"
                                                alt="{{ $checkin['today_reward']['name'] }}"
                                                class="w-full h-full object-contain" loading="lazy">
                                        </div>
                                        <span
                                            class="absolute -top-1.5 -right-1.5 px-1.5 py-0.5 bg-amber-500 text-[8px] font-bold text-black rounded-full leading-none">
                                            x{{ $checkin['today_reward']['cnt'] }}
                                        </span>
                                    </div>

                                    {{-- Info --}}
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-semibold text-white truncate">
                                            {{ $checkin['today_reward']['name'] }}</p>
                                        <p class="text-[10px] text-slate-400 mt-0.5">Today's Reward</p>
                                    </div>

                                    {{-- Action / Status --}}
                                    @if ($checkin['is_checked_in'])
                                        <span
                                            class="shrink-0 flex items-center gap-1 px-3 py-1.5 bg-emerald-500/15 text-emerald-400 text-xs font-bold rounded-lg border border-emerald-500/25">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            Claimed
                                        </span>
                                    @else
                                        <button type="button"
                                            class="shrink-0 px-3 py-1.5 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-400 hover:to-orange-400 text-black text-xs font-bold rounded-lg shadow-md shadow-amber-900/20 hover:shadow-amber-500/30 transition-all active:scale-95 flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            Check In
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>


    <!-- Redeem Codes Section -->

    {{-- Skeleton Loading for Redeem Codes --}}
    <section x-show="!codesLoaded" x-transition:leave="transition ease-in duration-300"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-2xl font-bold text-white flex items-center">
                <svg class="w-6 h-6 mr-2 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z">
                    </path>
                </svg>
                Redeem Codes
            </h2>
            <span class="flex items-center gap-2 text-xs text-blue-400 font-medium">
                <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                        stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor"
                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                    </path>
                </svg>
                Memuat data...
            </span>
        </div>
        <div class="space-y-6">
            @for ($i = 0; $i < 2; $i++)
                <div
                    class="bg-[#1e293b]/40 backdrop-blur-sm border border-slate-700/50 rounded-2xl overflow-hidden animate-pulse">
                    {{-- Skeleton Header --}}
                    <div class="flex items-center gap-3 px-5 py-3.5 border-b border-slate-700/40">
                        <div class="w-8 h-8 rounded-lg bg-slate-700"></div>
                        <div class="h-4 bg-slate-700 rounded w-32"></div>
                        <div class="ml-auto h-5 bg-slate-700/60 rounded-full w-16"></div>
                    </div>
                    {{-- Skeleton Cards --}}
                    <div class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                        @for ($j = 0; $j < 3; $j++)
                            <div class="bg-[#0b0f19]/60 border border-slate-700/40 rounded-xl p-4 flex flex-col gap-3">
                                <div class="flex items-center justify-between">
                                    <div class="h-5 bg-slate-700 rounded w-28"></div>
                                    <div class="w-7 h-7 bg-slate-700/50 rounded-lg"></div>
                                </div>
                                <div class="h-3 bg-slate-700/40 rounded w-full"></div>
                                <div class="h-8 bg-slate-700/50 rounded-lg w-full mt-auto"></div>
                            </div>
                        @endfor
                    </div>
                </div>
            @endfor
        </div>
    </section>

    {{-- Actual Redeem Codes --}}
    @if (!empty($redeemCodes))
        <section name="redeem-codes" x-show="codesLoaded" x-transition:enter="transition ease-out duration-500"
            x-transition:enter-start="opacity-0 translate-y-4" x-transition:enter-end="opacity-100 translate-y-0"
            style="display: none;" x-data="{
                redeemingCode: null,
                async redeemCode(code, storageKey) {
                    if (this.redeemingCode) return;
                    this.redeemingCode = code;
            
                    try {
                        const result = await $wire.redeemSingleCode(code, storageKey);
            
                        if (result.status === 'valid') {
                            $dispatch('notify', { type: 'success', message: result.description });
            
                            // Save to localStorage
                            let data = {};
                            try { data = JSON.parse(localStorage.getItem('Redeem') || '{}'); } catch (e) { data = {}; }
                            let existing = data[storageKey] ? data[storageKey].split(',').map(c => c.trim()) : [];
                            if (!existing.includes(code)) existing.push(code);
                            data[storageKey] = existing.join(',');
                            localStorage.setItem('Redeem', JSON.stringify(data));
            
                            // Hide the card
                            this.$refs['code_' + code]?.remove();
                            this.$nextTick(() => {
                                document.querySelectorAll('[data-game-group]').forEach(group => {
                                    if (group.querySelectorAll('[data-code-card]').length === 0) {
                                        group.remove();
                                    }
                                });
                            });
                        } else if (result.status === 'cooldown') {
                            $dispatch('notify', { type: 'warning', message: result.description });
                        } else {
                            $dispatch('notify', { type: 'error', message: result.description || result.message });
                        }
                    } catch (e) {
                        $dispatch('notify', { type: 'error', message: 'Gagal menghubungi server.' });
                    } finally {
                        this.redeemingCode = null;
                    }
                }
            }">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-2xl font-bold text-white flex items-center">
                    <svg class="w-6 h-6 mr-2 text-amber-400" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z">
                        </path>
                    </svg>
                    Redeem Codes
                </h2>
                <span class="text-sm text-slate-400 font-medium">
                    {{ array_sum(array_map(fn($g) => count($g['codes']), $redeemCodes)) }} codes available
                </span>
            </div>

            <div class="space-y-6">
                @foreach ($redeemCodes as $gameName => $gameData)
                    <div data-game-group
                        class="bg-[#1e293b]/40 backdrop-blur-sm border border-slate-700/50 rounded-2xl overflow-hidden">
                        {{-- Game Header --}}
                        <div
                            class="flex items-center gap-3 px-5 py-3.5 bg-gradient-to-r {{ $gameData['color'] }}/30 border-b border-slate-700/40">
                            <div
                                class="w-8 h-8 rounded-lg bg-gradient-to-br {{ $gameData['color'] }} flex items-center justify-center p-0.5 shadow-sm overflow-hidden">
                                <img src="{{ $gameData['icon_url'] }}" alt="{{ $gameName }}"
                                    class="w-full h-full object-cover rounded-[6px]" loading="lazy">
                            </div>
                            <h3 class="font-bold text-white text-sm">{{ $gameName }}</h3>
                            <span
                                class="ml-auto text-xs bg-slate-800/60 text-slate-300 px-2.5 py-1 rounded-full font-medium">{{ count($gameData['codes']) }}
                                codes</span>
                        </div>

                        {{-- Code Cards --}}
                        <div class="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                            @foreach ($gameData['codes'] as $codeEntry)
                                <div x-ref="code_{{ $codeEntry['code'] }}" data-code-card
                                    class="bg-[#0b0f19]/60 border border-slate-700/40 rounded-xl p-4 flex flex-col gap-3 hover:border-slate-500/50 transition-all group">
                                    {{-- Code --}}
                                    <div class="flex items-center justify-between gap-2">
                                        <code
                                            class="text-base font-bold text-white tracking-wider font-mono">{{ $codeEntry['code'] }}</code>
                                        <button type="button"
                                            @click="navigator.clipboard.writeText('{{ $codeEntry['code'] }}'); $dispatch('notify', {type: 'success', message: 'Code copied!'})"
                                            class="shrink-0 p-1.5 rounded-lg hover:bg-slate-700/50 text-slate-400 hover:text-white transition-colors"
                                            title="Copy code">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z">
                                                </path>
                                            </svg>
                                        </button>
                                    </div>

                                    {{-- Rewards --}}
                                    @if (!empty($codeEntry['rewards']))
                                        <p class="text-xs text-slate-400 leading-relaxed line-clamp-2">
                                            <span class="text-slate-500">Rewards:</span> {{ $codeEntry['rewards'] }}
                                        </p>
                                    @else
                                        <p class="text-xs text-slate-500 italic">Rewards not available</p>
                                    @endif

                                    {{-- Redeem Button --}}
                                    <button type="button"
                                        @click="redeemCode('{{ $codeEntry['code'] }}', '{{ $gameData['storage_key'] }}')"
                                        :disabled="redeemingCode === '{{ $codeEntry['code'] }}'"
                                        class="w-full mt-auto py-2 bg-gradient-to-r {{ $gameData['color'] }} hover:opacity-90 text-white text-xs font-bold rounded-lg transition-all active:scale-95 flex items-center justify-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed">
                                        <template x-if="redeemingCode === '{{ $codeEntry['code'] }}'">
                                            <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                                    stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor"
                                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                                </path>
                                            </svg>
                                        </template>
                                        <template x-if="redeemingCode !== '{{ $codeEntry['code'] }}'">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M5 13l4 4L19 7"></path>
                                            </svg>
                                        </template>
                                        <span
                                            x-text="redeemingCode === '{{ $codeEntry['code'] }}' ? 'Redeeming...' : 'Redeem'"></span>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <!-- Bottom Sections Grid: Builds & News -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 pb-10">
        <!-- Recommendations / Builds -->
        <section name="builds">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-white flex items-center"><svg class="w-5 h-5 mr-2 text-yellow-500"
                        fill="currentColor" viewBox="0 0 20 20">
                        <path
                            d="M17.707 9.293a1 1 0 010 1.414l-7 7a1 1 0 01-1.414 0l-7-7A.997.997 0 012 10V5a3 3 0 013-3h5c.256 0 .512.098.707.293l7 7zM5 6a1 1 0 100-2 1 1 0 000 2z">
                        </path>
                    </svg> Trending Builds</h2>
                <a href="#" class="text-blue-400 hover:text-blue-300 text-sm font-medium transition-colors">View
                    All
                    &rarr;</a>
            </div>
            <div class="space-y-4">
                @foreach ($builds as $build)
                    <div
                        class="{{ $build['bg'] }} border border-slate-700/50 rounded-xl p-4 flex items-center justify-between hover:scale-[1.02] transition-transform cursor-pointer">
                        <div class="flex items-center space-x-4">
                            <div
                                class="w-12 h-12 bg-slate-800 rounded-lg shrink-0 border border-slate-600 shadow-inner">
                            </div>
                            <div>
                                <div class="flex items-center space-x-2">
                                    <h4 class="font-bold text-white">{{ $build['name'] }}</h4>
                                    <span
                                        class="px-2 py-0.5 text-[10px] font-bold bg-yellow-500/20 text-yellow-500 rounded-full border border-yellow-500/30">{{ $build['tier'] }}
                                        Tier</span>
                                </div>
                                <p class="text-xs text-slate-400 mt-1">{{ $build['game'] }} •
                                    {{ $build['path'] }}
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <span
                                class="bg-blue-500/20 text-blue-400 text-xs px-3 py-1 rounded-lg font-medium">{{ $build['role'] }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <!-- Latest News / Events -->
        <section name="news">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-white flex items-center"><svg class="w-5 h-5 mr-2 text-blue-500"
                        fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                            clip-rule="evenodd"></path>
                    </svg> Game Announcements</h2>
            </div>
            <div class="space-y-4" x-data="{ expanded: '{{ count($news) > 0 ? array_key_first($news) : '' }}' }">
                @if (count($news) > 0)
                    @foreach ($news as $gameName => $gameNews)
                        <div class="border border-slate-700/50 rounded-xl overflow-hidden bg-[#1e293b]/30">
                            <button
                                @click="expanded = expanded === '{{ $gameName }}' ? null : '{{ $gameName }}'"
                                class="w-full flex items-center justify-between p-4 bg-[#111827]/80 hover:bg-slate-700/30 transition-colors">
                                <div class="flex items-center space-x-3">
                                    <span class="font-bold text-white">{{ $gameName }}</span>
                                    <span
                                        class="text-xs bg-blue-500/20 text-blue-400 px-2 py-0.5 rounded-full">{{ count($gameNews) }}</span>
                                </div>
                                <svg class="w-5 h-5 text-slate-400 transition-transform duration-200"
                                    :class="expanded === '{{ $gameName }}' ? 'rotate-180 text-blue-400' : ''"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7">
                                    </path>
                                </svg>
                            </button>

                            <div x-show="expanded === '{{ $gameName }}'" style="display: none;"
                                class="p-4 grid grid-cols-1 gap-4 bg-[#0b0f19] border-t border-slate-700/50">
                                @foreach ($gameNews as $n)
                                    <div
                                        class="bg-gradient-to-r {{ $n['bg'] }} to-[#1e293b]/50 border border-slate-700/50 p-4 rounded-xl flex flex-col hover:border-slate-500/80 transition-all cursor-pointer group">

                                        @if (!empty($n['banner']))
                                            <div class="mb-3 rounded-lg overflow-hidden shrink-0 w-full h-32 md:h-40">
                                                <img src="{{ $n['banner'] }}" alt="{{ $n['title'] }}"
                                                    class="w-full h-full object-cover opacity-90 group-hover:opacity-100 group-hover:scale-105 transition-all duration-300"
                                                    loading="lazy">
                                            </div>
                                        @endif

                                        <div class="flex-1 flex flex-col justify-between">
                                            <div>
                                                <div class="flex justify-between items-start mb-2">
                                                    <span
                                                        class="text-[10px] sm:text-xs font-bold {{ $n['tag_color'] }} uppercase tracking-widest">{{ $n['tag'] }}</span>
                                                    <span
                                                        class="text-xs text-slate-500 whitespace-nowrap ml-2">{{ $n['time'] }}</span>
                                                </div>
                                                <h3
                                                    class="text-white font-bold text-base sm:text-lg mb-2 group-hover:text-blue-300 transition-colors leading-tight">
                                                    {{ $n['title'] }}
                                                </h3>
                                                <p
                                                    class="text-xs sm:text-sm text-slate-400 line-clamp-2 leading-relaxed">
                                                    {{ $n['desc'] }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="text-center py-6 text-slate-500">
                        Tidak ada pengumuman hari ini.
                    </div>
                @endif
            </div>
        </section>
    </div>
</div>
