<?php
/**
 * 按 urls.txt 逐行抓取：用户主页（笔记列表+正文多图）或单条 /explore/ 笔记全文图，输出到 downloads/<用户ID>/ 或 downloads/note_<noteId>/
 *
 * 用法:
 *   php batch-profiles.php
 *   php batch-profiles.php 我的地址列表.txt
 */

declare(strict_types=1);

// 兼容透传 cookie，用于让 mini-red-book.php 拿到 note.id 并抓正文多图
$options = getopt('', ['cookie::', 'cookie-file::', 'xsec-source::']);
$cookieHeader = (string)($options['cookie'] ?? '');
$cookieFile = (string)($options['cookie-file'] ?? '');
if ($cookieHeader === '' && $cookieFile !== '' && is_readable($cookieFile)) {
    $cookieHeader = trim((string)file_get_contents($cookieFile));
}
$xsecSource = (string)($options['xsec-source'] ?? '');

// 兼容：用户可能只传 --cookie-file，不传列表文件。
// 这里需要跳过所有以 -- 开头的参数，只取第一个“普通参数”作为 urls.txt。
$listFile = '';
for ($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i] ?? '';
    if ($arg === '' || str_starts_with($arg, '--')) {
        continue;
    }
    $listFile = $arg;
}
if ($listFile === '') {
    $listFile = (__DIR__ . DIRECTORY_SEPARATOR . 'urls.txt');
}
if (!is_readable($listFile)) {
    fwrite(STDERR, "找不到或无法读取列表文件: {$listFile}\n");
    exit(1);
}

$lines = file($listFile, FILE_IGNORE_NEW_LINES);
if ($lines === false) {
    fwrite(STDERR, "读取失败: {$listFile}\n");
    exit(1);
}

$php = PHP_BINARY;
$script = __DIR__ . DIRECTORY_SEPARATOR . 'mini-red-book.php';
$baseOut = __DIR__ . DIRECTORY_SEPARATOR . 'downloads';
if (!is_dir($baseOut) && !@mkdir($baseOut, 0755, true)) {
    fwrite(STDERR, "无法创建目录: {$baseOut}\n");
    exit(1);
}

$ran = 0;
foreach ($lines as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }
    $uid = '';
    if (preg_match('#/user/profile/([a-f0-9]+)#i', $line, $m)) {
        $uid = $m[1];
    } elseif (preg_match('#/explore/([0-9a-f]{20,})#i', $line, $m)) {
        // 单篇笔记：输出到 downloads/note_{noteId}/，避免与用户 ID 冲突
        $uid = 'note_' . $m[1];
    } else {
        fwrite(STDERR, "跳过（非用户主页 / 非 explore 笔记 URL）: {$line}\n");
        continue;
    }
    $out = $baseOut . DIRECTORY_SEPARATOR . $uid;
    fwrite(STDOUT, "\n=== {$uid} ===\n");
    $cmd = [
        $php,
        $script,
        '--out=' . $out,
        $line,
    ];
    // 优先透传 cookie-file，避免 cookie 字符串在命令行里被引号/分号破坏
    if ($cookieFile !== '' && is_readable($cookieFile)) {
        $cmd[] = '--cookie-file=' . $cookieFile;
    } elseif ($cookieHeader !== '') {
        $cmd[] = '--cookie=' . $cookieHeader;
    }
    if ($xsecSource !== '') {
        $cmd[] = '--xsec-source=' . $xsecSource;
    }
    $proc = proc_open(
        $cmd,
        [0 => ['pipe', 'r'], 1 => STDOUT, 2 => STDERR],
        $pipes,
        __DIR__
    );
    if (!is_resource($proc)) {
        fwrite(STDERR, "无法启动子进程\n");
        exit(1);
    }
    fclose($pipes[0]);
    proc_close($proc);
    $ran++;
}

fwrite(STDOUT, "\n批量结束，共处理 {$ran} 个主页。\n");
