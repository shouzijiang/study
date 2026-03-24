<?php

/**
 * 仅将 1.json～253.json 的 imageUrl 改为 https://sofun.online/static/punGame/img/N.png（N 为文件名数字），其他字段不变。
 */

declare(strict_types=1);

$dir = __DIR__;
$opts = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
$baseUrl = 'https://sofun.online/static/punGame/img/';
$done = 0;

for ($i = 254; $i <= 270; $i++) {
    $file = $dir . DIRECTORY_SEPARATOR . $i . '.json';
    if (!is_file($file)) {
        continue;
    }
    $raw = file_get_contents($file);
    $json = json_decode($raw, true);
    if (!is_array($json)) {
        continue;
    }
    $json['imageUrl'] = $baseUrl . $i . '.png';
    file_put_contents($file, json_encode($json, $opts));
    $done++;
}

echo "done: updated imageUrl in {$done} files (1–253).\n";
