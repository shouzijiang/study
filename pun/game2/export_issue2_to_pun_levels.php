<?php
/**
 * 将 game2/issue2.json 的数据“静态导出”为 pun_levels 风格 PHP 文件：
 * return [
 *   1 => ['东','坡','肉'],
 * ];
 *
 * 作用：
 * - 键：level（来自 issue2.json）
 * - 值：answer 拆成的字符数组
 * - 丢弃 level < 1（后端校验 level>=1）
 */

$issueJsonPath = __DIR__ . DIRECTORY_SEPARATOR . 'issue2.json';
$outPath = __DIR__ . DIRECTORY_SEPARATOR . 'pun_levels_issue2_static.php';

$raw = @file_get_contents($issueJsonPath);
if ($raw === false) {
    fwrite(STDERR, "读取失败: {$issueJsonPath}\n");
    exit(1);
}

$data = json_decode($raw, true);
if (!is_array($data)) {
    fwrite(STDERR, "JSON 解析失败: {$issueJsonPath}\n");
    exit(1);
}

$result = [];
foreach ($data as $row) {
    if (!is_array($row)) {
        continue;
    }

    $level = (int)($row['level'] ?? 0);
    if ($level < 1) {
        continue;
    }

    $answer = (string)($row['answer'] ?? '');
    $chars = preg_split('//u', $answer, -1, PREG_SPLIT_NO_EMPTY);
    if (!is_array($chars) || empty($chars)) {
        $chars = [$answer];
    }

    $arrParts = [];
    foreach ($chars as $ch) {
        // 输出文件里使用 PHP 单引号：只需要转义反斜杠与单引号
        $ch = str_replace('\\', '\\\\', $ch);
        $ch = str_replace("'", "\\'", $ch);
        $arrParts[] = "'{$ch}'";
    }

    $result[$level] = '[' . implode(',', $arrParts) . ']';
}

ksort($result, SORT_NUMERIC);

$lines = [];
foreach ($result as $level => $arrStr) {
    $lines[] = "    {$level} => {$arrStr}";
}

$out = "<?php\n\nreturn [\n" . implode(",\n", $lines) . ",\n];\n";

$ok = @file_put_contents($outPath, $out);
if ($ok === false) {
    fwrite(STDERR, "写入失败: {$outPath}\n");
    exit(1);
}

echo "已导出: {$outPath}，条数: " . count($result) . "\n";

