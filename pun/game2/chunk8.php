<?php
$path = __DIR__ . '/a.json';
$s = file_get_contents($path);
$parts = preg_split('/\s+/', trim($s), -1, PREG_SPLIT_NO_EMPTY);
$chunks = array_chunk($parts, 8);
$out = implode("\n", array_map(function ($c) {
    return implode(' ', $c);
}, $chunks));
file_put_contents($path, $out);
echo "Done. Lines: " . count($chunks) . "\n";
