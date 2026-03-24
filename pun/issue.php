<?php

/**
 * 批量抓取关卡 JSON：level/1 ~ level/253
 * 接口示例：https://apaas.aiforce.cloud/spark/faas/app_4jke3a8u28zjs/api/game/level/1
 */

declare(strict_types=1);

set_time_limit(0);
ini_set('memory_limit', '512M');

$baseUrl = 'https://apaas.aiforce.cloud/spark/faas/app_4jke3a8u28zjs/api/game/level/';
$start = 254;
$end = 270;
$maxRetry = 3;
$sleepMsBetweenRequests = 120;
$verifySsl = false; // 若证书链异常可关闭校验
$authCookie = getenv('CRAWLER_COOKIE') ?: ''; // 从浏览器复制完整 Cookie 字符串
$authToken = getenv('CRAWLER_AUTH') ?: '';   // 可选：Authorization 值，如 Bearer xxxxx
$headersFile = __DIR__ . DIRECTORY_SEPARATOR . 'headers.txt';

// 可按需补充请求头（如果后续接口校验更严格）
$extraHeaders = [
    'Accept: application/json, text/plain, */*',
    'Accept-Encoding: gzip, deflate',
    'Referer: https://apaas.aiforce.cloud/',
    'Origin: https://apaas.aiforce.cloud',
    'Sec-Fetch-Site: same-origin',
    'Sec-Fetch-Mode: cors',
    'Sec-Fetch-Dest: empty',
    'Cache-Control: no-cache',
    'Pragma: no-cache',
];
if ($authToken !== '') {
    $extraHeaders[] = 'Authorization: ' . $authToken;
}
if ($authCookie !== '') {
    $extraHeaders[] = 'Cookie: ' . $authCookie;
}

// 支持从 headers.txt 读取完整请求头（每行一个 Header: value）
if (is_file($headersFile)) {
    $lines = file($headersFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lines)) {
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, ':')) {
                continue;
            }
            $extraHeaders[] = $line;
        }
    }
}

/**
 * 判断某个头是否已存在（不区分大小写）。
 */
function hasHeader(array $headers, string $name): bool
{
    $name = strtolower($name);
    foreach ($headers as $h) {
        $pos = strpos($h, ':');
        if ($pos === false) {
            continue;
        }
        if (strtolower(trim(substr($h, 0, $pos))) === $name) {
            return true;
        }
    }
    return false;
}

// 若 Cookie 中有 suda-csrf-token，自动补充 CSRF 相关头（双头兜底）
foreach ($extraHeaders as $h) {
    if (stripos($h, 'cookie:') === 0) {
        $cookieValue = trim(substr($h, strpos($h, ':') + 1));
        if (preg_match('/(?:^|;\s*)suda-csrf-token=([^;]+)/i', $cookieValue, $m) === 1) {
            $csrf = trim($m[1]);
            if ($csrf !== '') {
                if (!hasHeader($extraHeaders, 'x-csrf-token')) {
                    $extraHeaders[] = 'X-CSRF-Token: ' . $csrf;
                }
                if (!hasHeader($extraHeaders, 'x-suda-csrf-token')) {
                    $extraHeaders[] = 'X-Suda-Csrf-Token: ' . $csrf;
                }
            }
        }
        break;
    }
}

$saveDir = __DIR__ . DIRECTORY_SEPARATOR . 'level_json';
if (!is_dir($saveDir) && !mkdir($saveDir, 0777, true) && !is_dir($saveDir)) {
    exit("创建目录失败：{$saveDir}\n");
}
$debugDir = $saveDir . DIRECTORY_SEPARATOR . '_debug';
if (!is_dir($debugDir) && !mkdir($debugDir, 0777, true) && !is_dir($debugDir)) {
    exit("创建调试目录失败：{$debugDir}\n");
}
$cookieFile = $saveDir . DIRECTORY_SEPARATOR . 'cookies.txt';

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

$success = 0;
$failed = [];
$allData = [];

/**
 * 清理响应中的 BOM、XSSI 前缀等非 JSON 内容。
 */
function normalizeJsonText(string $text): string
{
    // 去掉 UTF-8 BOM
    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
    $text = trim($text);

    // 常见 XSSI 前缀处理：)]}',\n
    if (str_starts_with($text, ")]}'")) {
        $pos = strpos($text, "\n");
        if ($pos !== false) {
            $text = trim(substr($text, $pos + 1));
        }
    }

    // 若前面有非 JSON 噪音，截取到第一个 { 或 [
    if ($text !== '' && !in_array($text[0], ['{', '['], true)) {
        $objPos = strpos($text, '{');
        $arrPos = strpos($text, '[');
        $candidates = array_filter([$objPos, $arrPos], static fn($v) => $v !== false);
        if (!empty($candidates)) {
            $startPos = min($candidates);
            $text = trim(substr($text, $startPos));
        }
    }

    return $text;
}

/**
 * 从响应体中尽量提取 JSON（兼容 JSONP / 前后包裹文本）。
 */
function decodeResponseJson(string $response, ?array &$decoded, string &$usedPayload): bool
{
    $normalized = normalizeJsonText($response);

    $candidates = [$normalized];

    // JSONP: callback({...}) / foo.bar([...]);
    if (preg_match('/^[a-zA-Z_$][\w.$]*\(([\s\S]*)\)\s*;?\s*$/', $normalized, $m) === 1) {
        $candidates[] = trim($m[1]);
    }

    // 提取最外层对象
    if (preg_match('/\{[\s\S]*\}/', $normalized, $m) === 1) {
        $candidates[] = trim($m[0]);
    }

    // 提取最外层数组
    if (preg_match('/\[[\s\S]*\]/', $normalized, $m) === 1) {
        $candidates[] = trim($m[0]);
    }

    $candidates = array_values(array_unique(array_filter($candidates, static fn($v) => $v !== '')));

    foreach ($candidates as $candidate) {
        $tmp = json_decode($candidate, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $decoded = $tmp;
            $usedPayload = $candidate;
            return true;
        }
    }

    $decoded = null;
    $usedPayload = $normalized;
    return false;
}

/**
 * 判断是否命中登录页 HTML。
 */
function isLoginHtml(string $response): bool
{
    $lower = strtolower($response);
    return str_contains($lower, '<!doctype html')
        && (str_contains($response, '欢迎登录') || str_contains($lower, '<title>欢迎登录</title>'));
}

for ($level = $start; $level <= $end; $level++) {
    $url = $baseUrl . $level;
    $filePath = $saveDir . DIRECTORY_SEPARATOR . $level . '.json';

    $ok = false;
    $lastError = '';
    $decoded = null;
    $rawBody = '';
    $lastHttpCode = 0;
    $usedPayload = '';
    $responseHeadersRaw = '';

    for ($attempt = 1; $attempt <= $maxRetry; $attempt++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_ENCODING => 'gzip,deflate', // 当前环境 libcurl 不支持 br
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; PHP-JSON-Crawler/1.0)',
            CURLOPT_COOKIEFILE => $cookieFile,
            CURLOPT_COOKIEJAR => $cookieFile,
            CURLOPT_HTTPHEADER => $extraHeaders,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $lastHttpCode = $httpCode;

        if ($response !== false) {
            $responseHeadersRaw = substr($response, 0, $headerSize);
            $rawBody = substr($response, $headerSize);
        }

        if ($response !== false && $httpCode === 200) {
            if (isLoginHtml($rawBody)) {
                $lastError = '命中登录页，缺少鉴权（请传 CRAWLER_COOKIE / CRAWLER_AUTH）';
                usleep(200000);
                continue;
            }
            if (decodeResponseJson($rawBody, $decoded, $usedPayload)) {
                $written = file_put_contents(
                    $filePath,
                    json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
                );
                if ($written !== false) {
                    $ok = true;
                    break;
                }
                $lastError = '写入 JSON 文件失败';
            } else {
                $lastError = '响应不是合法 JSON: ' . json_last_error_msg();
            }
        } else {
            $lastError = $curlErr !== '' ? $curlErr : "HTTP {$httpCode}";
            if ($httpCode === 403) {
                $lastError .= '（可能是 Cookie 失效或缺少 CSRF 头）';
            }
        }

        usleep(200000);
    }

    if ($ok) {
        $success++;
        echo "[成功] level {$level}\n";
        $allData[] = [
            'level' => $level,
            'http_code' => $lastHttpCode,
            'data' => $decoded,
        ];
    } else {
        echo "[失败] level {$level} - {$lastError}\n";
        $failed[] = "level {$level} ({$lastError})";

        // 保存原始响应，方便定位到底返回了什么（如 HTML、网关提示、验证码页等）
        $debugPath = $debugDir . DIRECTORY_SEPARATOR . "level_{$level}.txt";
        $debugText = "URL: {$url}\n"
            . "HTTP: {$lastHttpCode}\n"
            . "Error: {$lastError}\n"
            . "---- RESPONSE HEADERS ----\n{$responseHeadersRaw}\n"
            . "---- USED PAYLOAD ----\n{$usedPayload}\n"
            . "---- RAW RESPONSE ----\n{$rawBody}\n";
        file_put_contents($debugPath, $debugText);

        $allData[] = [
            'level' => $level,
            'http_code' => $lastHttpCode,
            'error' => $lastError,
            'raw' => $rawBody,
            'debug_file' => $debugPath,
        ];
    }

    usleep($sleepMsBetweenRequests * 1000);
}

$summaryPath = $saveDir . DIRECTORY_SEPARATOR . 'all_levels.json';
file_put_contents(
    $summaryPath,
    json_encode($allData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
);

echo "\n抓取完成：成功 {$success} 条，失败 " . count($failed) . " 条。\n";
echo "汇总文件：{$summaryPath}\n";
if (!empty($failed)) {
    echo "失败列表：\n" . implode("\n", $failed) . "\n";
}
