<?php
/**
 * 读取 all_answers.json，找出 wordArrayIndices 为空的 level；
 * 从 N.json 取 wordArray、answerLength，用「按顺序取前 answerLength 个字」作为占位答案；
 * 再根据已知谐音/双关修正部分关卡，写回 guessed_answers.json。
 */
declare(strict_types=1);

$dir = __DIR__;
$all = json_decode(file_get_contents($dir . '/all_answers.json'), true);
$guessed = json_decode(file_get_contents($dir . '/guessed_answers.json'), true);
$byLevel = [];
foreach ($guessed as $row) {
    $byLevel[(int)$row['level']] = $row['userAnswer'];
}

$emptyLevels = [];
foreach ($all as $r) {
    if (isset($r['wordArrayIndices']) && $r['wordArrayIndices'] === []) {
        $emptyLevels[] = (int)$r['level'];
    }
}

// 已知需修正的 level => 正确谐音/双关答案（必须能被该关 wordArray 拼出）
$fixes = [
    17 => '黄师',      // 皇上，无「帝」
    26 => '虎霜',      // 虎
    34 => '压美',      // 鸭，无「鸭」
    38 => '盐淡',      // 蛋
    40 => '火姬目',    // 鸡→姬
    41 => '早根gao龟时',
    42 => '下江大猛',
    51 => '吉一',      // 鸡
    69 => '冬阳',      // 太阳
    72 => '老生菜',
];

foreach ($emptyLevels as $level) {
    $file = $dir . DIRECTORY_SEPARATOR . $level . '.json';
    if (!is_file($file)) {
        continue;
    }
    $data = json_decode(file_get_contents($file), true);
    if (!is_array($data) || !isset($data['wordArray']) || !isset($data['answerLength'])) {
        continue;
    }
    $wordArray = $data['wordArray'];
    $len = (int)$data['answerLength'];
    if (isset($fixes[$level])) {
        $answer = $fixes[$level];
        if (mb_strlen($answer, 'UTF-8') === $len && canForm($answer, $wordArray)) {
            $byLevel[$level] = $answer;
            continue;
        }
    }
    $answer = implode('', array_slice($wordArray, 0, $len));
    $byLevel[$level] = $answer;
}

function canForm(string $answer, array $wordArray): bool {
    $len = mb_strlen($answer, 'UTF-8');
    $used = array_fill(0, count($wordArray), false);
    for ($i = 0; $i < $len; $i++) {
        $char = mb_substr($answer, $i, 1, 'UTF-8');
        $found = false;
        foreach ($wordArray as $j => $c) {
            if (!$used[$j] && $c === $char) {
                $used[$j] = true;
                $found = true;
                break;
            }
        }
        if (!$found) return false;
    }
    return true;
}

$out = [];
for ($level = 1; $level <= 253; $level++) {
    $out[] = ['level' => $level, 'userAnswer' => $byLevel[$level] ?? ''];
}
file_put_contents(
    $dir . '/guessed_answers.json',
    json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
);
echo "Fixed " . count($emptyLevels) . " levels, written guessed_answers.json\n";
