<?php
/**
 * 将 a.json 按「拼音 + 后面的中文分类」拆成数组，每个条目一行。
 * 规则：仅在「拼音数据」后紧跟「已知分类词」时换行，该中文为一个条目结尾。
 */
$path = __DIR__ . '/a.json';
$s = file_get_contents($path);

// 若当前已是 JSON 数组，先还原成一行（用空格拼接）
if (preg_match('/^\s*\[/', $s)) {
    $arr = json_decode($s, true);
    if (is_array($arr)) {
        $s = implode(' ', $arr);
    }
}

// 已知分类词（拼音后面的那一个中文），只在这些词后换行
$categories = [
    '词语', '成语', '食物', '生活用品', '地名', '职业', '名人', '歌名', '行为', '身体部位', '品牌', '活动', '国家', '乐器',
    '日常用语', '动物', '机构', '软件', '角色', '植物', '水果', '物理现象', '历史人物', '文具', '文物', '动漫', '俗语', '场地',
    '自然', '新年用品', '机械', '游戏', '运动', '居住场所', '诗歌', '神话', '节日', '生物', '电子产品', '祝福语', '避雷', '文件',
    '伙伴', '电影', '景区', '典故', '流行语', '称谓', '节日用品', '交通工具', '神 话', '成 语',
];
$catAlternation = implode('|', array_map('preg_quote', $categories));

// 匹配：拼音串 + 空格 + 分类词，在分类词后插入换行
$pattern = '/(\w+\d(?:\s+\w+\d)*)\s+(' . $catAlternation . ')\s+/u';
$replacement = '$1 $2' . "\n";
$withNewlines = preg_replace($pattern, $replacement, $s);

// 按行拆成数组，去掉空行，trim
$lines = array_filter(array_map('trim', explode("\n", $withNewlines)));

// 输出为 JSON 数组
$json = json_encode($lines, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
file_put_contents($path, $json);

echo 'Done. Entries: ' . count($lines) . "\n";
