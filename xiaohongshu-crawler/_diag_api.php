<?php
$cookie = trim(file_get_contents(__DIR__ . '/cookie.txt'));
$uid = '67ac70ce000000000d009fb3';
$qs = http_build_query(['user_id' => $uid, 'cursor' => '', 'num' => 30, 'image_scenes' => 'FD_WM_WEBP']);
$url = 'https://edith.xiaohongshu.com/api/sns/web/v1/user_posted?' . $qs;
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_ENCODING => '',
    CURLOPT_HTTPHEADER => [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/124.0.0.0 Safari/537.36',
        'Accept: application/json, */*',
        'Referer: https://www.xiaohongshu.com/user/profile/' . $uid,
        'Origin: https://www.xiaohongshu.com',
        'Cookie: ' . $cookie,
    ],
]);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP $code\n";
echo substr((string)$body, 0, 500) . "\n";
