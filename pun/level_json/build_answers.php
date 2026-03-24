<?php
/**
 * 从 1.json～253.json 读取关卡数据，从 guessed_answers.json 读取推断的答案，
 * 计算每题的 wordArrayIndices，输出 all_answers.json。
 * guessed_answers.json 格式: [ {"level":1,"userAnswer":"东坡肉"}, ... ]
 */
declare(strict_types=1);

$dir = __DIR__;
$guessedFile = $dir . DIRECTORY_SEPARATOR . 'guessed_answers.json';
$outFile = $dir . DIRECTORY_SEPARATOR . 'all_answers.json';

$guessed = json_decode(file_get_contents($guessedFile), true);
if (!is_array($guessed)) {
    die("invalid guessed_answers.json\n");
}
$byLevel = [];
foreach ($guessed as $row) {
    $byLevel[(int)$row['level']] = $row['userAnswer'];
}

$results = [];
$opts = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;

for ($level = 1; $level <= 253; $level++) {
    $file = $dir . DIRECTORY_SEPARATOR . $level . '.json';
    if (!is_file($file)) {
        $results[] = ['level' => $level, 'userAnswer' => $byLevel[$level] ?? '', 'wordArrayIndices' => [], 'error' => 'no level file'];
        continue;
    }
    $data = json_decode(file_get_contents($file), true);
    if (!is_array($data) || !isset($data['wordArray']) || !is_array($data['wordArray'])) {
        $results[] = ['level' => $level, 'userAnswer' => $byLevel[$level] ?? '', 'wordArrayIndices' => [], 'error' => 'invalid level data'];
        continue;
    }
    $wordArray = $data['wordArray'];
    $answer = $byLevel[$level] ?? '';
    if ($answer === '') {
        $results[] = ['level' => $level, 'userAnswer' => '', 'wordArrayIndices' => [], 'error' => 'no guess'];
        continue;
    }
    $indices = indicesFromAnswer($wordArray, $answer);
    $results[] = [
        'level' => $level,
        'userAnswer' => $answer,
        'wordArrayIndices' => $indices,
        'hintText' => $data['hintText'] ?? '',
        'answerLength' => $data['answerLength'] ?? 0,
    ];
}

file_put_contents($outFile, json_encode($results, $opts));
echo "written " . count($results) . " answers to all_answers.json\n";

function indicesFromAnswer(array $wordArray, string $answer): array {
    $len = mb_strlen($answer, 'UTF-8');
    $used = array_fill(0, count($wordArray), false);
    $indices = [];
    for ($i = 0; $i < $len; $i++) {
        $char = mb_substr($answer, $i, 1, 'UTF-8');
        $found = false;
        for ($j = 0; $j < count($wordArray); $j++) {
            if (!$used[$j] && $wordArray[$j] === $char) {
                $indices[] = $j;
                $used[$j] = true;
                $found = true;
                break;
            }
        }
        if (!$found) {
            return []; // 无法用 wordArray 拼出答案
        }
    }
    return $indices;
}
