<?php
/**
 * 从 levels_compact.json 读取 253 关，根据 hintText/answerLength/wordArray 推断答案，
 * 校验答案严格由 wordArray 组成且长度正确，输出 guessed_answers.json。
 */
declare(strict_types=1);

$dir = __DIR__;
$compact = json_decode(file_get_contents($dir . '/levels_compact.json'), true);
if (!is_array($compact)) {
    die("invalid levels_compact.json\n");
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

// 已知谐音/双关答案（level => 答案），须能被对应 wordArray 拼出
$known = [
    1 => '东坡肉',
    2 => '老门桥',
    3 => '小道名细',   // 道长→小道，wordArray 无「长」
    4 => '蛋包饭',
    5 => '打火机',
    6 => '九老仪',    // 阿姨→老仪 等，wordArray 无「阿」「姨」
    7 => '手耳机果',
    8 => '短草叶',
    9 => '糍粑',
    10 => '计松机',
    11 => '梅花',
    12 => '社死',
    13 => '愚公报仇',
    14 => '黄瓜拌凉',
    15 => '假面干',
    16 => '卧龙马',
    17 => '黄帝',
    18 => '包饺子',
    19 => '木数',
    20 => '力烛肉',
    21 => '好牛',
    22 => '箱头',
    23 => '海底捞',
    24 => '插人',
    25 => '拉面酱',
    26 => '老虎',
    27 => '剁椒鱼头',
    28 => '上海',
    29 => '电风扇',
    30 => '鸭肉饭',
    31 => '相对论',
    32 => '世头师',
    33 => '猕猴桃',
    34 => '压鸭',
    35 => '开a大东桃木n',
    36 => '头囊',
    37 => '狐胡',
    38 => '淡蛋',
    39 => '老醋谢鸭',
    40 => '火鸡目',
    41 => '早根gao龟时r',
    42 => '江大猛',
    43 => '枣子男',
    44 => '扬眉吐气',
    45 => '北京',
    46 => '如门',
    47 => '掷一分孤',
    48 => '历历在目',
    49 => '蜜风',
    50 => '吴京',
    51 => '吉鸡',
    52 => '强联得医',
    53 => '子白酒',
    54 => '沙鹅',
    55 => '金豆人饼',
    56 => '狼嚎鬼哭',
    57 => '老灯酒',
    58 => '哈米狗',
    59 => '南去蚁',
    60 => '老叶柏',
    61 => '家好园',
    62 => '冰山一角',
    63 => '倒霉',
    64 => '同鱼',
    65 => '肉包狗',
    66 => '老号',
    67 => '松许',
    68 => '心一图团',
    69 => '太阳',
    70 => '不丁冷',
    71 => '俄罗斯',
    72 => '老生菜',
    73 => '辣条',
    74 => '石卧海平',
    75 => '杨林好',
    76 => '乐真盒',
    77 => '铃林',
    78 => '鸡犬升天',
    79 => '西吸枝',
    80 => '牛奶',
    81 => '蛋讶',
    82 => '不可思议',
    83 => '中米线东',
    84 => '图尼基',
    85 => '你钱佛狮春',
    86 => '灌李',
    87 => '狮',
    88 => '粥',
    89 => '姜',
    90 => '考试',
    91 => '一草一木',
    92 => '笋',
    93 => '苹果',
    94 => '芋圆',
    95 => '线',
    96 => '马',
    97 => '杯',
    98 => '鲸',
    99 => '松鼠',
    100 => '酥肉',
];

$out = [];
foreach ($compact as $row) {
    $level = (int) $row['level'];
    $hint = $row['hintText'] ?? '';
    $len = (int) ($row['answerLength'] ?? 0);
    $wordArray = $row['wordArray'] ?? [];
    $answer = $known[$level] ?? '';
    if ($answer === '' || mb_strlen($answer, 'UTF-8') !== $len || !canForm($answer, $wordArray)) {
        $answer = '';
        $raw = preg_replace('/^这是/', '', $hint);
        if (mb_strlen($raw, 'UTF-8') === $len && canForm($raw, $wordArray)) {
            $answer = $raw;
        }
    }
    $out[] = ['level' => $level, 'userAnswer' => $answer];
}

file_put_contents(
    $dir . '/guessed_answers.json',
    json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
);
echo "written " . count($out) . " to guessed_answers.json\n";
