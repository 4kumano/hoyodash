<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class HoyolabService
{
    protected string $baseUrl = 'https://api-os-takumi.hoyoverse.com';
    
    public function getUserFullInfo(?string $cookie): array
    {
        if (empty($cookie)) {
            return [];
        }
        
        try {
            $response = Http::withHeaders([
                'Cookie' => $cookie,
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'application/json, text/plain, */*',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Connection' => 'keep-alive',
                'origin' => 'https://act.hoyolab.com',
                'Referer' => 'https://act.hoyolab.com/',
                'x-rpc-lang' => 'en-us',
                'x-rpc-language' => 'en-us',
            ])->get('https://bbs-api-os.hoyolab.com/community/user/wapi/getUserFullInfo?gid=2');

            $data = $response->json();
            // dd($data);

            if (isset($data['message']) && $data['message'] === 'OK') {
                $userInfo = [
                    'hoyolab_uid' => $data['data']['user_info']['uid'] ?? null,
                    'nickname' => $data['data']['user_info']['nickname'] ?? null,
                    'avatar'=>$data['data']['user_info']['avatar_url'] ?? 'https://ui-avatars.com/api/?name=Commander&background=2563eb&color=fff',
                ];
                return $userInfo;
            }
        } catch (\Exception $e) {
            // handle error
        }

        return [];
    }
    // Game Specific API Routes (Base URL for Announcements, Events, etc)
    const HK4E_URL = 'https://sg-hk4e-api.hoyoverse.com/common/hk4e_global/'; // Genshin Impact
    const NAP_URL  = 'https://sg-announcement-static.hoyoverse.com/common/nap_global/'; // Zenless Zone Zero (ZZZ)
    const HKRPG_URL = 'https://sg-hkrpg-api.hoyoverse.com/common/hkrpg_global/'; // Honkai: Star Rail

    /**
     * Validate the cookie and get the user's game roles.
     * Required cookies: ltoken_v2, ltuid_v2 (or ltoken, ltuid)
     */
    public function loginWithCookie(string $cookie): array
    {
        try {
            $response = Http::withHeaders([
                'Host' => 'api-account-os.hoyolab.com',
                'Accept' => 'application/json, text/plain, */*',
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Connection' => 'keep-alive',
                'Cookie' => $cookie,
            ])->get('https://api-account-os.hoyolab.com/binding/api/getUserGameRolesByCookie');

            $data = $response->json();

            if (!is_array($data)) {
                return [
                    'retcode' => -1,
                    // If it's returning HTML, just show generic error.
                    'message' => 'Layanan HoYoLAB sedang tidak dapat diakses atau Cookie Anda ditolak oleh server (Status: ' . $response->status() . ').',
                ];
            }

            return $data;
        } catch (\Exception $e) {
            return [
                'retcode' => -1,
                'message' => 'Connection error: ' . $e->getMessage()
            ];
        }
    }


    /**
     * Gets the official game icon URL based on game code.
     * 
     * @param string $gameBiz
     * @return string
     */
    public function getIcon(string $gameBiz): string
    {
        if (str_contains($gameBiz, 'hk4e')) {
            return 'https://img-os-static.hoyolab.com/communityWeb/upload/1d7dd8f33c5ccdfdeac86e1e86ddd652.png'; // Genshin
        } elseif (str_contains($gameBiz, 'hkrpg')) {
            return 'https://img-os-static.hoyolab.com/communityWeb/upload/473afd1250b71ba470744aa240f6d638.png'; // Honkai: Starrail
        } elseif (str_contains($gameBiz, 'bh3')) {
            return 'https://img-os-static.hoyolab.com/communityWeb/upload/bbb364aaa7d51d168c96aaa6a1939cba.png'; // Honkai Impact 3rd
        } elseif (str_contains($gameBiz, 'nap')) {
            return 'https://img-os-static.hoyolab.com/communityWeb/upload/30ef8803324044b40ccbc69973da9848.png'; // ZZZ
        }
        
        // Default placeholder icon jika tidak ada kecocokan
        return 'https://ui-avatars.com/api/?name=Game&background=1e293b&color=fff';
    }

    /**
     * Gets the latest news/announcements for a specific game code.
     * 
     * @param string $gameBiz
     * @param string|int $uid
     * @param string $region
     * @param int $level
     * @param string $lang
     * @return array
     */
    public function getNews(string $gameBiz, $uid = 0, string $region = '', int $level = 1, string $lang = 'id-id'): array
    {
        $url = '';
        $params = [
            'game_biz' => $gameBiz,
            'bundle_id' => $gameBiz,
            'platform' => 'pc',
            'region' => $region,
            'uid' => $uid,
            'level' => $level,
            'lang' => $lang,
        ];

        if (str_contains($gameBiz, 'hk4e')) {
            $params['game'] = 'hk4e';
            $url = self::HK4E_URL . 'announcement/api/getAnnList';
        } elseif (str_contains($gameBiz, 'nap')) {
            $params['game'] = 'nap';
            $url = self::NAP_URL . 'announcement/api/getAnnList';
        } elseif (str_contains($gameBiz, 'hkrpg')) {
            $params['game'] = 'hkrpg';
            $params['channel_id'] = 1;
            $url = self::HKRPG_URL . 'announcement/api/getAnnList';
        } else {
            return []; // Unrecognized game config
        }

        try {
            $response = Http::get($url, $params);
            $data = $response->json();
            // dd($data);
            
            $newsList = [];
            
            // Format struktur respon getAnnList dari Hoyoverse
            $listData = $data['data']['list'] ?? [];
            if (!empty($listData) && is_array($listData)) {
                foreach ($listData as $item) {
                    // Cek apakah data dibungkus kategori (mengandung array 'list' di dalamnya)
                    if (isset($item['list']) && is_array($item['list'])) {
                        $tag = $item['type_label'] ?? 'Berita';
                        // Tentukan warna tag berdasar nama kategori
                        $tagColor = str_contains(strtolower($tag), 'event') ? 'text-purple-400' : 'text-blue-400';
                        $bg = str_contains(strtolower($tag), 'event') ? 'from-purple-900/40' : 'from-blue-900/40';

                        foreach ($item['list'] as $ann) {
                            $newsList[] = [
                                'game' => $gameBiz,
                                'tag'  => $tag,
                                'tag_color' => $tagColor,
                                'time' => isset($ann['start_time']) ? \Carbon\Carbon::parse($ann['start_time'])->diffForHumans() : 'Baru saja',
                                'title' => $ann['title'] ?? 'Tanpa Judul',
                                'desc' => $ann['subtitle'] ?? 'Baca selengkapnya di dalam game.',
                                'banner' => $ann['banner'] ?? '',
                                'bg' => $bg,
                            ];
                        }
                    } else {
                        // Data langsung berupa array of announcements (tanpa kategori)
                        $contentStripped = strip_tags($item['content'] ?? '');
                        // Gunakan subtitle, jika kosong ambil potongan text dari content
                        $desc = !empty($item['subtitle']) ? $item['subtitle'] : ($contentStripped ? mb_substr($contentStripped, 0, 100) . '...' : 'Baca selengkapnya di dalam game.');

                        $newsList[] = [
                            'game' => $gameBiz,
                            'tag'  => 'Berita',
                            'tag_color' => 'text-emerald-400',
                            'time' => 'Baru saja', // Struktur ini kebetulan tak punya start_time di sample
                            'title' => $item['title'] ?? 'Tanpa Judul',
                            'desc' => $desc,
                            'banner' => $item['banner'] ?? '',
                            'bg' => 'from-emerald-900/40',
                        ];
                    }
                }
            }
            // dd($newsList);
            return $newsList;
            
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Converts epoch timestamp to date string.
     * 
     * @param string|int $epoch
     * @param string $format
     * @return string
     */
    public function epochconverter($epoch, string $format = 'd M Y, H:i'): string
    {
        if (empty($epoch)) {
            return '-';
        }
        return \Carbon\Carbon::createFromTimestamp($epoch)->format($format);
    }

    /**
     * Fetch and parse active redeem codes from Hoyolab guide/material API.
     * No cookie required.
     *
     * @param int $gameId  Game ID (2 = Genshin Impact, 6 = Honkai: Star Rail, 8 = Zenless Zone Zero)
     * @return array{retcode: int, message: string, codes?: string[]}
     */
    public function parseByHoyolab(int $gameId = 2): array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            ])->get('https://bbs-api-os.hoyolab.com/community/painter/wapi/circle/channel/guide/material', [
                'game_id' => $gameId,
            ]);

            $data = $response->json();

            if (!isset($data['data']['modules'])) {
                return [
                    'retcode' => $data['retcode'] ?? -1,
                    'message' => $data['message'] ?? 'Gagal mengambil data modul.',
                ];
            }

            $codes = [];

            foreach ($data['data']['modules'] as $module) {
                $exchangeGroup = $module['exchange_group'] ?? null;
                if ($exchangeGroup === null) {
                    continue;
                }

                $bonuses = $exchangeGroup['bonuses'] ?? [];
                foreach ($bonuses as $bonus) {
                    $rawCode = $bonus['exchange_code'] ?? '';
                    $code = $this->sanitizeCode($rawCode);
                    if (!empty($code)) {
                        $codes[] = $code;
                    }
                }
            }

            return [
                'retcode' => 0,
                'message' => 'OK',
                'codes' => $codes,
            ];

        } catch (\Exception $e) {
            return [
                'retcode' => -1,
                'message' => 'Terjadi kesalahan koneksi: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Sanitize a raw redeem code string.
     * Removes URLs, bracket indices, "Quick Redeem", "NEW!" text, then uppercases.
     */
    public function sanitizeCode(string $code): string
    {
        if (str_contains($code, '/')) {
            $code = explode('/', $code, 2)[0];
        }

        if (str_contains($code, ';')) {
            $code = explode(';', $code, 2)[0];
        }

        $code = preg_replace('/\[\d+\]/', '', $code);
        $code = str_replace(['Quick Redeem', 'NEW!'], '', $code);

        return strtoupper(trim($code));
    }

    /**
     * Validate a redeem code response retcode.
     *
     * @param int $retcode
     * @return array{status: string, description: string}
     */
    public function validateRedeemResponse(int $retcode): array
    {
        // Valid: successfully redeemed or already redeemed (still active)
        if (in_array($retcode, [0, -2017, -2018, -2021, -2011])) {
            $descriptions = [
                0     => 'Code redeemed successfully.',
                -2017 => 'Code already redeemed.',
                -2018 => 'Code already redeemed.',
                -2021 => 'Game level too low, but code is valid.',
                -2011 => 'Game level too low, but code is valid.',
            ];
            return ['status' => 'valid', 'description' => $descriptions[$retcode]];
        }

        // Expired
        if ($retcode === -2001) {
            return ['status' => 'expired', 'description' => 'Code has expired.'];
        }

        // Invalid / does not exist
        if (in_array($retcode, [-1065, -2003, -2004, -2006, -2014])) {
            $descriptions = [
                -1065 => 'Invalid code.',
                -2003 => 'Incorrectly formatted code.',
                -2004 => 'Invalid code.',
                -2006 => 'Max usage limit reached.',
                -2014 => 'Code not activated yet.',
            ];
            return ['status' => 'invalid', 'description' => $descriptions[$retcode]];
        }

        // Cooldown (rate limited)
        if ($retcode === -2016) {
            return ['status' => 'cooldown', 'description' => 'Redemption on cooldown. Please wait and try again.'];
        }

        // Credentials error
        if (in_array($retcode, [-1071, -1073, -1075])) {
            $descriptions = [
                -1071 => 'Invalid or expired cookies.',
                -1073 => 'No game account bound to this HoYoLab account.',
                -1075 => 'No character on this server.',
            ];
            return ['status' => 'credentials_error', 'description' => $descriptions[$retcode]];
        }

        return ['status' => 'unknown', 'description' => "Unknown retcode: {$retcode}"];
    }

    /**
     * Redeem a cdkey code for any Hoyoverse game via the official API.
     *
     * Genshin: GET to webExchangeCdkey
     * HSR/ZZZ: POST to webExchangeCdkeyRisk (with device_uuid from _MHYUUID cookie)
     *
     * @param string|null $cookie    User's HoYoLAB cookie string
     * @param string      $uid       Game UID
     * @param string      $region    Game region/server
     * @param string      $cdkey     The redeem code
     * @param string      $gameCode  Game code (hk4e, hkrpg, nap)
     * @param string      $gameBiz   Game biz code (hk4e_global, hkrpg_global, nap_global)
     * @return array{retcode: int, message: string, status: string, description: string}
     */
    public function redeemCode(?string $cookie, string $uid, string $region, string $cdkey, string $gameCode, string $gameBiz): array
    {
        if (empty($cookie) || empty($uid) || empty($cdkey)) {
            return [
                'retcode' => -1,
                'message' => 'Parameter tidak valid.',
                'status' => 'invalid',
                'description' => 'Cookie, UID, atau kode tidak boleh kosong.',
            ];
        }

        // Game-specific configuration
        $gameConfigs = [
            'hk4e' => [
                'origin' => 'https://genshin.hoyoverse.com',
                'referer' => 'https://genshin.hoyoverse.com/',
                'endpoint' => 'webExchangeCdkey',
                'method' => 'GET',
            ],
            'hkrpg' => [
                'origin' => 'https://hsr.hoyoverse.com',
                'referer' => 'https://hsr.hoyoverse.com/',
                'endpoint' => 'webExchangeCdkeyRisk',
                'method' => 'POST',
            ],
            'nap' => [
                'origin' => 'https://zenless.hoyoverse.com',
                'referer' => 'https://zenless.hoyoverse.com/',
                'endpoint' => 'webExchangeCdkeyRisk',
                'method' => 'POST',
            ],
        ];

        $config = $gameConfigs[$gameCode] ?? $gameConfigs['hk4e'];
        $apiUrl = "https://public-operation-{$gameCode}.hoyoverse.com/common/apicdkey/api/{$config['endpoint']}";

        // Ensure cookie string is clean (no newlines/carriage returns from textarea)
        $cleanCookie = preg_replace('/\\r|\\n/', '', trim($cookie));

        // CRITICAL FIX: The Hoyoverse webExchangeCdkey API rejects a raw _v2 cookie string 
        // with -1071 (Please log in first) unless the account_id_v2, account_mid_v2, and 
        // cookie_token_v2 are explicitly appended again at the end with a specific spacing format. 
        // This replicates how Postman sends it when it merges its internal cookie jar.
        $accId = $this->extractCookieValue($cleanCookie, 'account_id_v2');
        $accMid = $this->extractCookieValue($cleanCookie, 'account_mid_v2');
        $cookieToken = $this->extractCookieValue($cleanCookie, 'cookie_token_v2');
        
        if ($accId && $cookieToken) {
            $duplicateSuffix = "; account_id_v2={$accId}; account_mid_v2={$accMid}; cookie_token_v2={$cookieToken}";
            $cleanCookie .= $duplicateSuffix;
        }

        $headers = [
            'Cookie' => $cleanCookie,
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'application/json, text/plain, */*',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Accept-Language' => 'en-US,en;q=0.9,id;q=0.8',
            'Connection' => 'keep-alive',
            'Origin' => $config['origin'],
            'Referer' => $config['referer'],
        ];

        try {
            if ($config['method'] === 'GET') {
                // Genshin Impact — GET with query params
                $response = Http::withHeaders($headers)->get($apiUrl, [
                    'uid' => $uid,
                    'region' => $region,
                    'lang' => 'id',
                    'cdkey' => $cdkey,
                    'game_biz' => $gameBiz,
                    'sLangKey' => 'en-us',
                ]);
            } else {
                // HSR / ZZZ — POST with JSON payload
                $deviceUuid = $this->extractCookieValue($cleanCookie, '_MHYUUID')
                    ?: (string) \Illuminate\Support\Str::uuid();

                $response = Http::withHeaders($headers)->post($apiUrl, [
                    't' => (int) (microtime(true) * 1000),
                    'lang' => 'id',
                    'game_biz' => $gameBiz,
                    'uid' => $uid,
                    'region' => $region,
                    'cdkey' => $cdkey,
                    'platform' => '4',
                    'device_uuid' => $deviceUuid,
                ]);
            }

            $data = $response->json();
            $retcode = $data['retcode'] ?? -1;
            $message = $data['message'] ?? 'Unknown error';

            $validation = $this->validateRedeemResponse($retcode);

            return [
                'retcode' => $retcode,
                'message' => $message,
                'status' => $validation['status'],
                'description' => $validation['description'],
            ];

        } catch (\Exception $e) {
            return [
                'retcode' => -1,
                'message' => 'Terjadi kesalahan koneksi: ' . $e->getMessage(),
                'status' => 'unknown',
                'description' => 'Gagal terhubung ke server Hoyoverse.',
            ];
        }
    }

    /**
     * Extract a specific cookie value from a cookie string.
     *
     * @param string $cookieString  e.g. "ltoken_v2=xxx; _MHYUUID=abc-123; ..."
     * @param string $name          Cookie name to extract
     * @return string|null
     */
    private function extractCookieValue(string $cookieString, string $name): ?string
    {
        foreach (explode(';', $cookieString) as $part) {
            $segments = explode('=', trim($part), 2);
            if (count($segments) === 2 && trim($segments[0]) === $name) {
                return trim($segments[1]);
            }
        }
        return null;
    }
}
