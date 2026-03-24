<?php
$a = json_decode(file_get_contents(__DIR__ . '/all_answers.json'), true);
$empty = [];
foreach ($a as $r) {
    if (isset($r['wordArrayIndices']) && $r['wordArrayIndices'] === []) {
        $empty[] = $r['level'];
    }
}
echo "Empty count: " . count($empty) . "\n";
echo implode(',', $empty);
