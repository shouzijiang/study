<?php
/**
 * 将 all_answers_part1.json、part2、part3 合并回 pun/level_json/all_answers.json
 * 在三个校验脚本并行跑完后执行此脚本。
 */
declare(strict_types=1);

$batchDir = __DIR__;
$levelDir = dirname($batchDir);
$fullPath = $levelDir . DIRECTORY_SEPARATOR . 'all_answers.json';
$parts = [
    $batchDir . DIRECTORY_SEPARATOR . 'all_answers_part1.json',
    $batchDir . DIRECTORY_SEPARATOR . 'all_answers_part2.json',
    $batchDir . DIRECTORY_SEPARATOR . 'all_answers_part3.json',
];

$byLevel = [];

if (is_file($fullPath)) {
    $all = json_decode(file_get_contents($fullPath), true);
    if (is_array($all)) {
        foreach ($all as $row) {
            $byLevel[(int)$row['level']] = $row;
        }
    }
}

foreach ($parts as $p) {
    if (!is_file($p)) {
        echo "跳过（不存在）：{$p}\n";
        continue;
    }
    $arr = json_decode(file_get_contents($p), true);
    if (!is_array($arr)) {
        echo "跳过（非数组）：{$p}\n";
        continue;
    }
    foreach ($arr as $row) {
        $lev = (int)($row['level'] ?? 0);
        if ($lev >= 1 && $lev <= 253) {
            $byLevel[$lev] = $row;
        }
    }
    echo "已合并：{$p}\n";
}

if (empty($byLevel)) {
    die("合并后无数据，请先运行校验脚本生成 part 文件。\n");
}

ksort($byLevel, SORT_NUMERIC);
$out = array_values($byLevel);
$opts = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
file_put_contents($fullPath, json_encode($out, $opts));

echo "已写入 " . count($out) . " 条到 {$fullPath}\n";
