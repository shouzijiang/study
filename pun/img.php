<?php

/**
 * 批量下载图片：1.png ~ 253.png
 * 源地址：https://lf3-static.bytednsdoc.com/obj/eden-cn/pbyhpqnuvr/
 */

declare(strict_types=1);

set_time_limit(0);
ini_set('memory_limit', '512M');

$baseUrl = 'https://lf3-static.bytednsdoc.com/obj/eden-cn/pbyhpqnuvr/';
$start = 254;
$end = 270;
$maxRetry = 3;
$sleepMsBetweenFiles = 120;
$verifySsl = false; // 遇到自签名证书链时可关闭校验

$saveDir = __DIR__ . DIRECTORY_SEPARATOR . 'downloads';
if (!is_dir($saveDir) && !mkdir($saveDir, 0777, true) && !is_dir($saveDir)) {
    exit("创建目录失败：{$saveDir}\n");
}

// CLI 或 Web 环境都能看清输出
if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

$success = 0;
$failed = [];

for ($i = $start; $i <= $end; $i++) {
    $filename = "{$i}.png";
    $url = $baseUrl . $filename;
    $savePath = $saveDir . DIRECTORY_SEPARATOR . $filename;

    $ok = false;
    $lastError = '';

    for ($attempt = 1; $attempt <= $maxRetry; $attempt++) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => $verifySsl,
            CURLOPT_SSL_VERIFYHOST => $verifySsl ? 2 : 0,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; PHP-Image-Downloader/1.0)',
        ]);

        $data = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($data !== false && $httpCode === 200) {
            $writeOk = file_put_contents($savePath, $data);
            if ($writeOk !== false && $writeOk > 0) {
                $ok = true;
                break;
            }
            $lastError = '写入文件失败';
        } else {
            $lastError = $curlErr !== '' ? $curlErr : "HTTP {$httpCode}";
        }

        usleep(200000);
    }

    if ($ok) {
        $success++;
        echo "[成功] {$filename}\n";
    } else {
        $failed[] = "{$filename} ({$lastError})";
        echo "[失败] {$filename} - {$lastError}\n";
    }

    usleep($sleepMsBetweenFiles * 1000);
}

echo "\n下载完成：成功 {$success} 张，失败 " . count($failed) . " 张。\n";
if (!empty($failed)) {
    echo "失败列表：\n" . implode("\n", $failed) . "\n";
}
