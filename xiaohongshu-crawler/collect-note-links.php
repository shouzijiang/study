<?php

declare(strict_types=1);

/**
 * 输入博主主页，输出可直接粘贴到 PHP 的文章页 URL 数组（explore 链接）。
 *
 * 用法:
 *   php collect-note-links.php "https://www.xiaohongshu.com/user/profile/xxx?...tab=note" --first-url="https://www.xiaohongshu.com/explore/xxx?... "
 *   php collect-note-links.php --profile="https://www.xiaohongshu.com/user/profile/xxx?...tab=note"
 */

$opts = getopt('', ['profile::', 'cookie-file::', 'first-url::', 'help']);
if (isset($opts['help'])) {
    fwrite(STDOUT, "用法: php collect-note-links.php [profileURL] [--cookie-file=path] [--first-url=exploreURL]\n");
    exit(0);
}

$profileUrl = (string)($opts['profile'] ?? '');
$firstUrl = trim((string)($opts['first-url'] ?? ''));
$cookieFile = (string)($opts['cookie-file'] ?? (__DIR__ . DIRECTORY_SEPARATOR . 'cookie.txt'));
for ($i = 1; $i < $argc; $i++) {
    $arg = (string)($argv[$i] ?? '');
    if (str_starts_with($arg, '--first-url=')) {
        $firstUrl = trim(substr($arg, strlen('--first-url=')));
        continue;
    }
    if (str_starts_with($arg, '--profile=')) {
        $profileUrl = trim(substr($arg, strlen('--profile=')));
        continue;
    }
    if (str_starts_with($arg, '--cookie-file=')) {
        $cookieFile = trim(substr($arg, strlen('--cookie-file=')));
        continue;
    }
    if ($arg !== '' && !str_starts_with($arg, '--')) {
        $profileUrl = $arg;
    }
}
if ($profileUrl === '' || !preg_match('#/user/profile/([a-f0-9]{24})#i', $profileUrl, $um)) {
    fwrite(STDERR, "请传入有效博主主页 URL（/user/profile/{24位ID}）。\n");
    exit(1);
}
$userId = strtolower($um[1]);

$cookie = is_readable($cookieFile) ? trim((string)file_get_contents($cookieFile)) : '';

$fromApi = fetchUserPostedNotesAll($userId, $cookie, 250, $profileUrl);
$fromHtml = extractExploreLinksFromProfileHtml(fetchUrl($profileUrl, $cookie, 'https://www.xiaohongshu.com/'), $userId);
fwrite(STDERR, "诊断: API=" . count($fromApi) . " 条, HTML=" . count($fromHtml) . " 条\n");

$out = [];
$seen = [];

if ($firstUrl !== '') {
    $norm = normalizeExploreUrl($firstUrl);
    if ($norm !== '') {
        $seen[$norm] = true;
        $out[] = $norm;
    }
}

foreach ($fromApi as $item) {
    $id = strtolower(trim((string)($item['note_id'] ?? '')));
    if (!preg_match('/^[0-9a-f]{24}$/', $id)) {
        continue;
    }
    $token = trim((string)($item['xsec_token'] ?? $item['xsecToken'] ?? ''));
    $source = normalizeSource((string)($item['xsec_source'] ?? $item['xsecSource'] ?? 'pc_user'));
    $url = 'https://www.xiaohongshu.com/explore/' . rawurlencode($id);
    if ($token !== '') {
        $url .= '?xsec_token=' . rawurlencode($token) . '&xsec_source=' . rawurlencode($source);
    }
    $url = normalizeExploreUrl($url);
    if ($url !== '' && !isset($seen[$url])) {
        $seen[$url] = true;
        $out[] = $url;
    }
}

foreach ($fromHtml as $u) {
    $u = normalizeExploreUrl($u);
    if ($u !== '' && !isset($seen[$u])) {
        $seen[$u] = true;
        $out[] = $u;
    }
}

fwrite(STDOUT, "array(\n");
foreach ($out as $u) {
    fwrite(STDOUT, "    '" . str_replace("'", "\\'", $u) . "',\n");
}
fwrite(STDOUT, ");\n");
fwrite(STDERR, "共输出 " . count($out) . " 条文章链接。\n");
if (count($out) <= 1) {
    fwrite(STDERR, "提示: 当前账号数据下发很少，通常是 cookie 不完整或接口需要额外签名头（x-s/x-t）。\n");
}

function normalizeSource(string $s): string
{
    $s = strtolower(trim($s));
    return ($s === '' || $s === 'pc_search') ? 'pc_user' : $s;
}

function normalizeExploreUrl(string $url): string
{
    $url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    if ($url === '') {
        return '';
    }
    if (str_starts_with($url, '//')) {
        $url = 'https:' . $url;
    }
    if (str_starts_with($url, '/')) {
        $url = 'https://www.xiaohongshu.com' . $url;
    }
    if (!preg_match('#^https?://www\.xiaohongshu\.com/explore/([0-9a-f]{24})#i', $url, $m)) {
        return '';
    }
    $id = strtolower($m[1]);
    $q = parse_url($url, PHP_URL_QUERY);
    $token = '';
    $source = 'pc_user';
    if (is_string($q) && $q !== '') {
        parse_str($q, $arr);
        $token = trim((string)($arr['xsec_token'] ?? ''));
        $source = normalizeSource((string)($arr['xsec_source'] ?? $source));
    }
    $clean = 'https://www.xiaohongshu.com/explore/' . $id;
    if ($token !== '') {
        $clean .= '?xsec_token=' . rawurlencode($token) . '&xsec_source=' . rawurlencode($source);
    }
    return $clean;
}

function fetchUrl(string $url, string $cookie = '', string $referer = 'https://www.xiaohongshu.com/'): ?string
{
    $headers = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
        'Accept: text/html,application/xhtml+xml,application/json,text/plain,*/*',
        'Referer: ' . $referer,
        'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
    ];
    if ($cookie !== '') {
        $headers[] = 'Cookie: ' . $cookie;
    }
    return httpGet($url, $headers);
}

function httpGet(string $url, array $headerLines): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_ENCODING => '',
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return (is_string($body) && $body !== '') ? $body : null;
    }
    $ctx = stream_context_create([
        'http' => [
            'header' => implode("\r\n", $headerLines) . "\r\n",
            'timeout' => 45,
            'follow_location' => 1,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $data = @file_get_contents($url, false, $ctx);
    return ($data === false || $data === '') ? null : $data;
}

function fetchJson(string $url, string $cookie, string $referer): ?array
{
    $headers = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
        'Accept: application/json, text/plain, */*',
        'Referer: ' . $referer,
        'Origin: https://www.xiaohongshu.com',
        'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
        'Sec-Fetch-Dest: empty',
        'Sec-Fetch-Mode: cors',
        'Sec-Fetch-Site: same-site',
    ];
    if ($cookie !== '') {
        $headers[] = 'Cookie: ' . $cookie;
    }
    $raw = httpGet($url, $headers);
    if ($raw === null) {
        return null;
    }
    $j = json_decode($raw, true);
    return is_array($j) ? $j : null;
}

function toTruthy($v): bool
{
    if (is_bool($v)) {
        return $v;
    }
    if ($v === null) {
        return false;
    }
    if (is_int($v) || is_float($v)) {
        return (int)$v !== 0;
    }
    if (is_string($v)) {
        $s = strtolower(trim($v));
        return !($s === '' || $s === '0' || $s === 'false' || $s === 'no');
    }
    return !empty($v);
}

function readHasMore(array $data): bool
{
    if (array_key_exists('has_more', $data)) {
        return toTruthy($data['has_more']);
    }
    if (array_key_exists('hasMore', $data)) {
        return toTruthy($data['hasMore']);
    }
    return false;
}

function readCursor(array $data, array $notes): string
{
    foreach (['cursor', 'next_cursor', 'nextCursor', 'last_cursor', 'lastCursor'] as $k) {
        if (isset($data[$k]) && is_scalar($data[$k])) {
            $s = trim((string)$data[$k]);
            if ($s !== '') {
                return $s;
            }
        }
    }
    if ($notes !== []) {
        $last = $notes[count($notes) - 1];
        if (is_array($last)) {
            foreach (['cursor', 'note_cursor', 'noteCursor'] as $k) {
                if (isset($last[$k]) && is_scalar($last[$k])) {
                    $s = trim((string)$last[$k]);
                    if ($s !== '') {
                        return $s;
                    }
                }
            }
        }
    }
    return '';
}

function fetchUserPostedNotesAll(string $userId, string $cookie, int $delayMs, string $referer): array
{
    $bases = [
        'https://edith.xiaohongshu.com/api/sns/web/v1/user_posted',
        'https://www.xiaohongshu.com/api/sns/web/v1/user_posted',
    ];
    $all = [];
    $cursor = '';
    for ($page = 0; $page < 200; $page++) {
        $qs = http_build_query([
            'user_id' => $userId,
            'cursor' => $cursor,
            'num' => 30,
            'image_scenes' => 'FD_WM_WEBP',
        ]);
        $json = null;
        foreach ($bases as $b) {
            $try = fetchJson($b . '?' . $qs, $cookie, $referer);
            if ($try === null) {
                continue;
            }
            $code = isset($try['code']) ? (int)$try['code'] : 0;
            if ($code === 0) {
                $json = $try;
                break;
            }
            if ($code !== -1) {
                $json = $try;
                break;
            }
        }
        if (!is_array($json)) {
            break;
        }
        if (isset($json['code']) && (int)$json['code'] !== 0) {
            break;
        }
        $data = $json['data'] ?? null;
        if (!is_array($data)) {
            break;
        }
        $notes = $data['notes'] ?? [];
        if (!is_array($notes)) {
            $notes = [];
        }
        foreach ($notes as $n) {
            if (is_array($n)) {
                $all[] = $n;
            }
        }
        $hasMore = readHasMore($data);
        $next = readCursor($data, $notes);
        if (!$hasMore || $next === '' || $next === $cursor) {
            break;
        }
        $cursor = $next;
        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }
    }
    return $all;
}

function extractExploreLinksFromProfileHtml(?string $html, string $userId): array
{
    if (!is_string($html) || $html === '') {
        return [];
    }
    $set = [];

    // HTML 里可能是 /explore/xxx、\/explore\/xxx、或 profile 子路由形式，统一先提 noteId。
    $idSet = [];
    if (preg_match_all('#(?:/|\\\\/)explore(?:/|\\\\/)([0-9a-f]{24})#i', $html, $m1)) {
        foreach (($m1[1] ?? []) as $id) {
            $idSet[strtolower((string)$id)] = true;
        }
    }
    if (preg_match_all('#(?:/|\\\\/)user(?:/|\\\\/)profile(?:/|\\\\/)' . preg_quote($userId, '#') . '(?:/|\\\\/)([0-9a-f]{24})#i', $html, $m2)) {
        foreach (($m2[1] ?? []) as $id) {
            $idSet[strtolower((string)$id)] = true;
        }
    }

    foreach (array_keys($idSet) as $id) {
        $token = '';
        $source = 'pc_user';

        // 在同一段字符串中尽量找该 noteId 对应 token/source（兼容 &amp; 与 JSON 转义）。
        $re = '#(?:/|\\\\/)explore(?:/|\\\\/)' . preg_quote($id, '#')
            . '(?:\?([^"\'<>\s]*xsec_token=([^&"\'<>\s]+)[^"\'<>\s]*))?#i';
        if (preg_match($re, $html, $mm) && isset($mm[2])) {
            $token = html_entity_decode((string)$mm[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $rawQ = html_entity_decode((string)($mm[1] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($rawQ !== '') {
                parse_str(str_replace('\\/', '/', $rawQ), $qArr);
                $source = normalizeSource((string)($qArr['xsec_source'] ?? $source));
            }
        }

        $url = 'https://www.xiaohongshu.com/explore/' . $id;
        if ($token !== '') {
            $url .= '?xsec_token=' . rawurlencode($token) . '&xsec_source=' . rawurlencode($source);
        }
        $set[$url] = true;
    }

    return array_keys($set);
}
