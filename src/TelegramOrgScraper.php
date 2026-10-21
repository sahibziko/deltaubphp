<?php
namespace App;

class TelegramOrgScraper
{
    private const BASE_URL = 'https://my.telegram.org';
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

    /**
     * my.telegram.org saytına nömrə göndərib təsdiq şifrəsi tələb edir
     */
    public static function sendPassword(string $phone): array
    {
        $digits = preg_replace('/[^\d]/', '', $phone);
        if (str_starts_with($digits, '994') && strlen($digits) >= 12) {
            $cleanPhone = '+' . $digits;
        } elseif (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $cleanPhone = '+994' . substr($digits, 1);
        } elseif (strlen($digits) === 9 && in_array(substr($digits, 0, 2), ['50', '51', '55', '70', '77', '99'])) {
            $cleanPhone = '+994' . $digits;
        } else {
            $cleanPhone = '+' . $digits;
        }

        $ch = curl_init(self::BASE_URL . '/auth/send_password');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['phone' => $cleanPhone]));
        curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Requested-With: XMLHttpRequest',
            'Accept: application/json, text/javascript, */*; q=0.01',
            'Origin: https://my.telegram.org',
            'Referer: https://my.telegram.org/auth'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($res === false || $httpCode !== 200) {
            return [
                'success' => false,
                'error' => 'my.telegram.org ilə əlaqə qurulmadı (' . ($curlErr ?: "HTTP {$httpCode}") . ')'
            ];
        }

        $json = json_decode((string)$res, true);
        if (empty($json['random_hash'])) {
            return [
                'success' => false,
                'error' => 'Telegram kod göndərməkdən imtina etdi: ' . substr((string)$res, 0, 150)
            ];
        }

        return [
            'success' => true,
            'phone' => $cleanPhone,
            'random_hash' => $json['random_hash'],
            'message' => 'Telegram tətbiqinizə rəsmi Telegram tərəfindən Web giriş kodu göndərildi.'
        ];
    }

    /**
     * my.telegram.org saytına daxil olub API ID və API HASH məlumatlarını alır və ya avtomatik yaradır
     */
    public static function loginAndGetApp(string $phone, string $randomHash, string $password): array
    {
        $digits = preg_replace('/[^\d]/', '', $phone);
        if (str_starts_with($digits, '994') && strlen($digits) >= 12) {
            $cleanPhone = '+' . $digits;
        } elseif (str_starts_with($digits, '0') && strlen($digits) === 10) {
            $cleanPhone = '+994' . substr($digits, 1);
        } elseif (strlen($digits) === 9 && in_array(substr($digits, 0, 2), ['50', '51', '55', '70', '77', '99'])) {
            $cleanPhone = '+994' . $digits;
        } else {
            $cleanPhone = '+' . $digits;
        }
        $password = trim($password);

        // 1. Giriş sorğusu və cookie əldə etmə
        $cookieJar = tempnam(sys_get_temp_dir(), 'tg_cookie_');

        $ch = curl_init(self::BASE_URL . '/auth/login');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'phone' => $cleanPhone,
            'random_hash' => $randomHash,
            'password' => $password
        ]));
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
        curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-Requested-With: XMLHttpRequest',
            'Accept: application/json, text/javascript, */*; q=0.01',
            'Origin: https://my.telegram.org',
            'Referer: https://my.telegram.org/auth'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $res = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $resTrimmed = trim((string)$res);
        if ($resTrimmed !== 'true') {
            @unlink($cookieJar);
            return [
                'success' => false,
                'error' => 'Telegram kodu yanlışdır və ya vaxtı bitib: ' . strip_tags($resTrimmed)
            ];
        }

        // 2. /apps səhifəsini yoxlayırıq
        $ch = curl_init(self::BASE_URL . '/apps');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
        curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $html = curl_exec($ch);
        curl_close($ch);

        if (!$html) {
            @unlink($cookieJar);
            return ['success' => false, 'error' => '/apps səhifəsi yüklənmədi.'];
        }

        // Əgər tətbiq artıq mövcuddursa, API ID və HASH-i oxuyuruq
        $appData = self::parseAppCredentials((string)$html);
        if ($appData['api_id'] > 0 && !empty($appData['api_hash'])) {
            @unlink($cookieJar);
            return [
                'success' => true,
                'api_id' => $appData['api_id'],
                'api_hash' => $appData['api_hash'],
                'is_new' => false
            ];
        }

        // Əgər tətbiq yoxdursa, "Create new application" formasını doldururuq
        if (str_contains($html, 'name="hash"')) {
            preg_match('/name="hash"\s+value="([a-f0-9]+)"/i', $html, $mHash);
            $formHash = $mHash[1] ?? '';

            if (empty($formHash)) {
                @unlink($cookieJar);
                return ['success' => false, 'error' => 'App yaratma formu üçün təhlükəsizlik açarı (hash) tapılmadı.'];
            }

            $randomNum = rand(10000, 99999);
            $appTitle = 'DeltaUser ' . $randomNum;
            $appShortname = 'deltabot' . $randomNum;

            $createParams = [
                'hash' => $formHash,
                'app_title' => $appTitle,
                'app_shortname' => $appShortname,
                'app_url' => '',
                'app_platform' => 'desktop',
                'app_desc' => 'Delta PHP Telegram Userbot'
            ];

            $ch = curl_init(self::BASE_URL . '/apps/create');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($createParams));
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
            curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $createdRes = curl_exec($ch);
            curl_close($ch);

            if (trim((string)$createdRes) === 'ERROR') {
                @unlink($cookieJar);
                return ['success' => false, 'error' => 'Telegram avtomatik API yaratma sorğusunu təhlükəsizlik səbəbilə rədd etdi.'];
            }

            // Yenidən /apps səhifəsinə baxırıq
            $ch = curl_init(self::BASE_URL . '/apps');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
            curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $newHtml = curl_exec($ch);
            curl_close($ch);
            @unlink($cookieJar);

            $newAppData = self::parseAppCredentials((string)$newHtml);
            if ($newAppData['api_id'] > 0 && !empty($newAppData['api_hash'])) {
                return [
                    'success' => true,
                    'api_id' => $newAppData['api_id'],
                    'api_hash' => $newAppData['api_hash'],
                    'is_new' => true
                ];
            }
        }

        @unlink($cookieJar);
        return [
            'success' => false,
            'error' => 'my.telegram.org-dan API ID və Hash çıxarmaq mümkün olmadı.'
        ];
    }

    private static function parseAppCredentials(string $html): array
    {
        $apiId = 0;
        $apiHash = '';

        // 1. Standart input-xlarge uneditable-input klassı
        if (preg_match_all('/<span[^>]*class="[^"]*uneditable-input[^"]*"[^>]*>(.*?)<\/span>/is', $html, $matches)) {
            if (!empty($matches[1])) {
                foreach ($matches[1] as $val) {
                    $val = trim(strip_tags($val));
                    if (is_numeric($val) && (int)$val > 1000 && $apiId === 0) {
                        $apiId = (int)$val;
                    } elseif (preg_match('/^[a-f0-9]{32}$/i', $val) && empty($apiHash)) {
                        $apiHash = $val;
                    }
                }
            }
        }

        // 2. Alternativ regexlər
        if ($apiId === 0 && preg_match('/(?:App\s+)?api_id:?.*?(\d{5,12})/is', $html, $m)) {
            $apiId = (int)$m[1];
        }
        if (empty($apiHash) && preg_match('/(?:App\s+)?api_hash:?.*?([a-f0-9]{32})/is', $html, $m)) {
            $apiHash = $m[1];
        }

        return ['api_id' => $apiId, 'api_hash' => $apiHash];
    }
}
