<?php
/** 导出 1～253 关的 hintText, answerLength, wordArray 到 levels_compact.json 便于推断答案 */
$dir = __DIR__;
$out = [];
for ($i = 1; $i <= 253; $i++) {
    $f = $dir . DIRECTORY_SEPARATOR . $i . '.json';
    if (!is_file($f)) continue;
    $j = json_decode(file_get_contents($f), true);
    if (!is_array($j)) continue;
    $out[] = [
        'level' => (int)$j['level'],
        'hintText' => $j['hintText'] ?? '',
        'answerLength' => (int)($j['answerLength'] ?? 0),
        'wordArray' => $j['wordArray'] ?? [],
    ];
}
file_put_contents($dir . DIRECTORY_SEPARATOR . 'levels_compact.json', json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo count($out) . " levels exported.\n";
