<?php

/**
 * 使用 submit-answer 接口校验并修正 all_answers.json 中 1～253 题的答案。
 * 入参: { level: N, userAnswer: ["字","字",...] }
 * 返回: isCorrect 或 feedback { "0": { position, isCorrect }, ... }
 * 参考 pun/issue.php 的请求头、Cookie、SSL 配置。
 */

declare(strict_types=1);

set_time_limit(0);
ini_set('memory_limit', '512M');

$submitUrl = 'https://apaas.aiforce.cloud/spark/faas/app_4jke3a8u28zjs/api/game/submit-answer';
$levelDir = dirname(__DIR__);
$levelDir2 = __DIR__;
$allAnswersPath = $levelDir . DIRECTORY_SEPARATOR . 'all_answers.json';
$batchName = '批次2';
$allLevelsPath = $levelDir2 . DIRECTORY_SEPARATOR . 'all_levels2.json';
$partOutputPath = $levelDir2 . DIRECTORY_SEPARATOR . 'all_answers_part2.json';
$punDir = dirname($levelDir);
$headersFile = $punDir . DIRECTORY_SEPARATOR . 'headers.txt';
$cookieFile = $levelDir . DIRECTORY_SEPARATOR . 'cookies.txt';
$verifySsl = false;
$authCookie = getenv('CRAWLER_COOKIE') ?: '';
$authToken = getenv('CRAWLER_AUTH') ?: '';
$sleepMsOk = 0;      // 校验通过后不等待，减少总耗时
$sleepMsRetry = 80;   // 修正/重试时短暂等待，避免限流
$maxTriesPerLevel = 12000;

$extraHeaders = [
    'Accept: application/json, text/plain, */*',
    'Accept-Encoding: gzip, deflate',
    'Content-Type: application/json',
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

function hasHeader(array $headers, string $name): bool
{
    $name = strtolower($name);
    foreach ($headers as $h) {
        $pos = strpos($h, ':');
        if ($pos === false) continue;
        if (strtolower(trim(substr($h, 0, $pos))) === $name) return true;
    }
    return false;
}
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

function normalizeJsonText(string $text): string
{
    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
    $text = trim($text);
    if (str_starts_with($text, ")]}'")) {
        $pos = strpos($text, "\n");
        if ($pos !== false) $text = trim(substr($text, $pos + 1));
    }
    if ($text !== '' && !in_array($text[0], ['{', '['], true)) {
        $objPos = strpos($text, '{');
        $arrPos = strpos($text, '[');
        $candidates = array_filter([$objPos, $arrPos], static fn($v) => $v !== false);
        if (!empty($candidates)) {
            $text = trim(substr($text, min($candidates)));
        }
    }
    return $text;
}

function decodeResponseJson(string $response, ?array &$decoded): bool
{
    $normalized = normalizeJsonText($response);
    $tmp = json_decode($normalized, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $decoded = $tmp;
        return true;
    }
    if (preg_match('/\{[\s\S]*\}/', $normalized, $m) === 1) {
        $tmp = json_decode(trim($m[0]), true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $decoded = $tmp;
            return true;
        }
    }
    $decoded = null;
    return false;
}

/**
 * 调用 submit-answer 接口。若传入 $ch 则复用，否则新建并关闭。
 * @param resource|null $ch 复用 cURL handle 时可传入，避免重复建连
 * @return array{isCorrect: bool, feedback?: array, raw?: array}
 */
function submitAnswer(string $url, array $headers, string $cookieFile, bool $verifySsl, int $level, array $userAnswerArr, int $sleepAfterMs, $ch = null): array
{
    $body = json_encode(['level' => $level, 'userAnswer' => $userAnswerArr], JSON_UNESCAPED_UNICODE);
    $ownCh = false;
    if ($ch === null) {
        $ch = curl_init($url);
        $ownCh = true;
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_ENCODING => 'gzip,deflate',
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; PHP-JSON-Crawler/1.0)',
            CURLOPT_COOKIEFILE => $cookieFile,
            CURLOPT_COOKIEJAR => $cookieFile,
        ]);
    }
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    $response = curl_exec($ch);
    if ($ownCh) {
        curl_close($ch);
    }
    if ($sleepAfterMs > 0) {
        usleep($sleepAfterMs * 1000);
    }
    $decoded = null;
    if ($response !== false && decodeResponseJson($response, $decoded) && is_array($decoded)) {
        return $decoded;
    }
    return ['isCorrect' => false, 'raw' => $response];
}

/**
 * 将答案字符串转为 UTF-8 单字数组。
 */
function answerStringToArray(string $s): array
{
    $arr = [];
    $len = mb_strlen($s, 'UTF-8');
    for ($i = 0; $i < $len; $i++) {
        $arr[] = mb_substr($s, $i, 1, 'UTF-8');
    }
    return $arr;
}

/**
 * 根据 feedback 得到正确位置上的字符；错误位置索引列表。
 */
function parseFeedback(array $feedback, array $currentArr): array
{
    $correctChars = [];
    $wrongIndices = [];
    foreach ($feedback as $key => $item) {
        if (!is_array($item)) continue;
        $pos = isset($item['position']) ? (int)$item['position'] : (int)$key;
        $ok = (bool)($item['isCorrect'] ?? false);
        if ($ok && isset($currentArr[$pos])) {
            $correctChars[$pos] = $currentArr[$pos];
        } else {
            $wrongIndices[] = $pos;
        }
    }
    ksort($correctChars);
    sort($wrongIndices);
    return ['correctChars' => $correctChars, 'wrongIndices' => $wrongIndices];
}

/**
 * 从 wordArray 中排除已在 correctChars 中占用的字，得到可用来填错位的字集合（按原顺序保留，便于枚举）。
 */
function getAvailableChars(array $wordArray, array $correctChars): array
{
    $used = array_count_values($correctChars);
    $avail = [];
    foreach ($wordArray as $c) {
        $cnt = $used[$c] ?? 0;
        if ($cnt > 0) {
            $used[$c] = $cnt - 1;
        } else {
            $avail[] = $c;
        }
    }
    return $avail;
}

/**
 * 递归枚举：用 available 中的字填满 wrongIndices 的排列，每次提交直到 isCorrect。
 * 若提交后 isCorrect 为 false，解析新 feedback 缩小错位再递归，减少请求次数。
 */
function tryFixAnswer(
    string $url,
    array $headers,
    string $cookieFile,
    bool $verifySsl,
    int $level,
    array $current,
    array $wrongIndices,
    int $nWrong,
    array $available,
    int $kAvail,
    array $used,
    array $wordArray,
    int $sleepMsRetry,
    int $maxTries,
    int &$tried,
    $curlCh
): ?array {
    if ($tried >= $maxTries) {
        return null;
    }
    $posIndex = count($used);
    if ($posIndex === $nWrong) {
        $tried++;
        $attemptStr = implode('', $current);
        if ($tried <= 3 || $tried % 50 === 0) {
            echo "      尝试 #{$tried}: {$attemptStr}\n";
        }
        $resp = submitAnswer($url, $headers, $cookieFile, $verifySsl, $level, $current, $sleepMsRetry, $curlCh);
        if (!empty($resp['isCorrect'])) {
            return $current;
        }
        $feedback = $resp['feedback'] ?? null;
        if (is_array($feedback) && !empty($feedback)) {
            $parsed = parseFeedback($feedback, $current);
            $newCorrect = $parsed['correctChars'];
            $newWrong = $parsed['wrongIndices'];
            if (empty($newWrong)) {
                return null;
            }
            // 仅当错位变少时才用新 feedback 递归，否则会重复提交同一 candidate 死循环
            if (count($newWrong) >= $nWrong) {
                return null;
            }
            foreach ($newCorrect as $p => $c) {
                $current[$p] = $c;
            }
            $newAvail = getAvailableChars($wordArray, $newCorrect);
            $newN = count($newWrong);
            $newK = count($newAvail);
            if ($newN <= $newK && $newK > 0) {
                $found = tryFixAnswer($url, $headers, $cookieFile, $verifySsl, $level, $current, $newWrong, $newN, $newAvail, $newK, [], $wordArray, $sleepMsRetry, $maxTries, $tried, $curlCh);
                if ($found !== null) {
                    return $found;
                }
            }
        }
        return null;
    }
    $pos = $wrongIndices[$posIndex];
    for ($i = 0; $i < $kAvail; $i++) {
        if (isset($used[$i])) continue;
        $char = $available[$i];
        $next = $current;
        $next[$pos] = $char;
        $nextUsed = $used;
        $nextUsed[$i] = true;
        $found = tryFixAnswer($url, $headers, $cookieFile, $verifySsl, $level, $next, $wrongIndices, $nWrong, $available, $kAvail, $nextUsed, $wordArray, $sleepMsRetry, $maxTries, $tried, $curlCh);
        if ($found !== null) {
            return $found;
        }
    }
    return null;
}

// 主流程
if (!is_file($allAnswersPath)) {
    die("all_answers.json 不存在：{$allAnswersPath}\n");
}
$all = json_decode(file_get_contents($allAnswersPath), true);
if (!is_array($all)) {
    die("all_answers.json 格式错误\n");
}

$byLevel = [];
foreach ($all as $row) {
    $byLevel[(int)$row['level']] = $row;
}

$opts = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
$updated = 0;
$failed = [];

$levelDataCache = [];
if (is_file($allLevelsPath)) {
    $allLevels = json_decode(file_get_contents($allLevelsPath), true);
    if (is_array($allLevels)) {
        foreach ($allLevels as $item) {
            $lev = (int)($item['level'] ?? 0);
            if ($lev >= 1 && $lev <= 253 && isset($item['data']) && is_array($item['data'])) {
                $levelDataCache[$lev] = $item['data'];
            }
        }
    }
}
if (empty($levelDataCache)) {
    die("all_levels2.json 无有效关卡数据\n");
}
$levelsToProcess = array_keys($levelDataCache);
sort($levelsToProcess, SORT_NUMERIC);

echo "[{$batchName}] " . date('Y-m-d H:i:s') . " 开始执行，关卡 " . min($levelsToProcess) . "-" . max($levelsToProcess) . "，共 " . count($levelsToProcess) . " 题\n";
if (function_exists('flush')) {
    flush();
}

$curlCh = curl_init($submitUrl);
curl_setopt_array($curlCh, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => $extraHeaders,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_ENCODING => 'gzip,deflate',
    CURLOPT_SSL_VERIFYPEER => $verifySsl,
    CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; PHP-JSON-Crawler/1.0)',
    CURLOPT_COOKIEFILE => $cookieFile,
    CURLOPT_COOKIEJAR => $cookieFile,
]);

$totalLevels = count($levelsToProcess);
$currentIdx = 0;
foreach ($levelsToProcess as $level) {
    $currentIdx++;
    // echo "[{$batchName}] 正在执行 level {$level} (第 {$currentIdx}/{$totalLevels} 题)\n";
    if (function_exists('flush')) {
        flush();
    }
    $row = $byLevel[$level] ?? null;
    $levelData = $levelDataCache[$level] ?? null;
    if (!$row || $levelData === null) {
        $failed[] = "level {$level}: 无数据或无关卡文件";
        continue;
    }
    $wordArray = $levelData['wordArray'] ?? [];
    $answerLength = (int)($levelData['answerLength'] ?? $row['answerLength'] ?? 0);
    if ($answerLength === 0 || empty($wordArray)) {
        $failed[] = "level {$level}: 无 wordArray 或 answerLength";
        continue;
    }

    $userAnswer = $row['userAnswer'] ?? '';
    $arr = answerStringToArray($userAnswer);
    if (count($arr) !== $answerLength) {
        $arr = array_slice($wordArray, 0, $answerLength);
        $userAnswer = implode('', $arr);
    }

    $resp = submitAnswer($submitUrl, $extraHeaders, $cookieFile, $verifySsl, $level, $arr, $sleepMsOk, $curlCh);

    if (!empty($resp['isCorrect'])) {
        $byLevel[$level]['userAnswer'] = $userAnswer;
        $indices = indicesFromAnswer($wordArray, $userAnswer);
        $byLevel[$level]['wordArrayIndices'] = $indices;
        echo "[{$level}] OK: {$userAnswer}\n";
        $updated++;
        continue;
    }

    $feedback = $resp['feedback'] ?? null;
    if (!is_array($feedback) || empty($feedback)) {
        $failed[] = "level {$level}: 接口未返回 isCorrect 或 feedback";
        continue;
    }

    $parsed = parseFeedback($feedback, $arr);
    $correctChars = $parsed['correctChars'];
    $wrongIndices = $parsed['wrongIndices'];
    if (empty($wrongIndices)) {
        $byLevel[$level]['userAnswer'] = $userAnswer;
        $byLevel[$level]['wordArrayIndices'] = indicesFromAnswer($wordArray, $userAnswer);
        echo "[{$level}] OK(反馈全对): {$userAnswer}\n";
        $updated++;
        continue;
    }

    $available = getAvailableChars($wordArray, $correctChars);
    $n = count($wrongIndices);
    $k = count($available);
    if ($n > $k || $k === 0) {
        $failed[] = "level {$level}: 可用字不足 wrong={$n} avail={$k}";
        continue;
    }
    $permLimit = 1;
    for ($i = 0; $i < $n; $i++) $permLimit *= ($k - $i);
    // 不再因排列数大而直接跳过，仍尝试最多 maxTriesPerLevel 次

    $candidate = $arr;
    $tried = 0;
    $fixed = tryFixAnswer(
        $submitUrl,
        $extraHeaders,
        $cookieFile,
        $verifySsl,
        $level,
        $candidate,
        $wrongIndices,
        $n,
        $available,
        $k,
        [],
        $wordArray,
        $sleepMsRetry,
        $maxTriesPerLevel,
        $tried,
        $curlCh
    );

    if ($fixed !== null) {
        $fixedStr = implode('', $fixed);
        $byLevel[$level]['userAnswer'] = $fixedStr;
        $byLevel[$level]['wordArrayIndices'] = indicesFromAnswer($wordArray, $fixedStr);
        echo "[{$level}] FIXED: {$userAnswer} -> {$fixedStr} (tries={$tried})\n";
        $updated++;
    } else {
        $failed[] = "level {$level}: 未在限定次数内找到正确答案 (tried={$tried})";
    }
}

curl_close($curlCh);

function indicesFromAnswer(array $wordArray, string $answer): array
{
    $len = mb_strlen($answer, 'UTF-8');
    $used = array_fill(0, count($wordArray), false);
    $indices = [];
    for ($i = 0; $i < $len; $i++) {
        $char = mb_substr($answer, $i, 1, 'UTF-8');
        for ($j = 0; $j < count($wordArray); $j++) {
            if (!$used[$j] && $wordArray[$j] === $char) {
                $indices[] = $j;
                $used[$j] = true;
                break;
            }
        }
    }
    return $indices;
}

$out = [];
foreach ($levelsToProcess as $lev) {
    if (isset($byLevel[$lev])) {
        $out[] = $byLevel[$lev];
    }
}
file_put_contents($partOutputPath, json_encode($out, $opts));

echo "\n[{$batchName}] " . date('Y-m-d H:i:s') . " 完成：已更新 {$updated} 题，失败 " . count($failed) . " 题。输出：{$partOutputPath}\n";
if (!empty($failed)) {
    echo implode("\n", $failed) . "\n";
}
