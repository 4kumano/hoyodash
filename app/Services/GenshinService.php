<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GenshinService
{
    // Original HoYoLAB API Endpoint for Genshin overseas record
    protected string $baseUrl = 'https://bbs-api-os.hoyolab.com/game_record/genshin/api/';

    protected HoyolabService $hoyolabService;

    public function __construct(HoyolabService $hoyolabService)
    {
        $this->hoyolabService = $hoyolabService;
    }

    /**
     * Get Genshin user stats and characters
     */
    public function getUserData(int $uid, string $lang = 'en-us'): array
    {
        $server = $this->hoyolabService->recognizeServer($uid);

        // In PHP, we can fetch these sequentially
        $indexData = $this->requestGenshinRecord('index', $uid, $server, 'GET', $lang);
        $characterData = $this->requestGenshinRecord('character/list', $uid, $server, 'POST', $lang);

        return [
            'stats' => $indexData['data'] ?? [],
            'characters' => $characterData['data'] ?? [],
        ];
    }

    /**
     * Get an arbitrary genshin object
     */
    protected function requestGenshinRecord(string $endpoint, int $uid, string $server, string $method = 'GET', string $lang = 'en-us'): array
    {
        $url = $this->baseUrl . $endpoint;

        $payload = [
            'role_id' => (string) $uid,
            'server' => $server,
        ];

        // Retrieve valid cookie from session
        $cookie = session('hoyolab_cookie', '');

        // Standard headers for game_record API
        $headers = [
            'Accept' => 'application/json, text/plain, */*',
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'x-rpc-language' => $lang,
            'x-rpc-app_version' => '1.5.0',
            'x-rpc-client_type' => '4',
            'Cookie' => $cookie,
            'DS' => $this->generateDS()
        ];

        $http = Http::withHeaders($headers);

        if ($method === 'POST') {
            $response = $http->post($url, $payload);
        } else {
            $response = $http->get($url, $payload);
        }
        // dd($response->json());

        return $response->json() ?? [];
    }

    /**
     * Generates standard DS token for overseas API requests.
     */
    protected function generateDS(): string
    {
        // OS DS salt for HoYoLAB API
        $salt = "6s25p5ox5y14umn1p61aqyyvbvvl3lrt"; 
        $t = time();
        $r = substr(str_shuffle("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 6);
        $hash = md5("salt={$salt}&t={$t}&r={$r}");
        
        return "{$t},{$r},{$hash}";
    }
}
