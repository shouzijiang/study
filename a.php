<?php
/**
 * 将 xiaohongshu-crawler/downloads2 下各子目录内的文件（递归）移动到 downloads2/062。
 * 根目录下的零散文件不处理；来源目录 062 跳过（避免无意义移动）。
 * 重名时在文件名前加上相对路径前缀，避免覆盖。
 *
 * 用法：php a.php
 * 可选：php a.php --dry-run  只打印将要执行的操作，不移动文件
 */

declare(strict_types=1);

$base = __DIR__ . DIRECTORY_SEPARATOR . 'xiaohongshu-crawler' . DIRECTORY_SEPARATOR . 'downloads2';
$destName = '062';
$destDir = $base . DIRECTORY_SEPARATOR . $destName;

$dryRun = in_array('--dry-run', $argv ?? [], true);

if (!is_dir($base)) {
    fwrite(STDERR, "目录不存在: {$base}\n");
    exit(1);
}

if (!is_dir($destDir)) {
    if ($dryRun) {
        echo "[dry-run] 将创建目录: {$destDir}\n";
    } else {
        if (!mkdir($destDir, 0777, true) && !is_dir($destDir)) {
            fwrite(STDERR, "无法创建目录: {$destDir}\n");
            exit(1);
        }
    }
}

$baseLen = strlen($base);

/**
 * 若目标已存在（或已被本次运行占用），生成不冲突的新路径（相对路径作前缀）。
 * 不在此函数内写入 $reserved；由调用方在确认移动后再登记。
 *
 * @param array<string, true> $reserved 本次运行已分配的目标路径
 */
function resolveTargetPath(
    string $destDir,
    string $srcFullPath,
    string $base,
    int $baseLen,
    array &$reserved
): string {
    $basename = basename($srcFullPath);
    $target = $destDir . DIRECTORY_SEPARATOR . $basename;
    $taken = static function (string $p) use (&$reserved): bool {
        return file_exists($p) || isset($reserved[$p]);
    };
    if (!$taken($target)) {
        return $target;
    }
    $rel = substr($srcFullPath, $baseLen);
    $rel = ltrim($rel, DIRECTORY_SEPARATOR . '/');
    $prefix = preg_replace('#[\\\\/]+#u', '_', $rel);
    $prefix = preg_replace('/_+/', '_', $prefix);
    $newBase = $prefix;
    $target = $destDir . DIRECTORY_SEPARATOR . $newBase;
    $n = 1;
    while ($taken($target)) {
        $pi = pathinfo($newBase);
        $stem = $pi['filename'] ?? $newBase;
        $ext = isset($pi['extension']) ? '.' . $pi['extension'] : '';
        $target = $destDir . DIRECTORY_SEPARATOR . $stem . '_' . $n . $ext;
        $n++;
    }
    return $target;
}

$moved = 0;
$failed = 0;
/** @var array<string, true> $reservedTargets */
$reservedTargets = [];

foreach (new DirectoryIterator($base) as $item) {
    if ($item->isDot() || !$item->isDir()) {
        continue;
    }
    if ($item->getFilename() === $destName) {
        continue;
    }

    $dirPath = $item->getPathname();
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dirPath, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }
        $src = $fileInfo->getPathname();
        $target = resolveTargetPath($destDir, $src, $base, $baseLen, $reservedTargets);

        if ($dryRun) {
            $reservedTargets[$target] = true;
            echo "[dry-run] rename\n从: {$src}\n到: {$target}\n";
            $moved++;
            continue;
        }

        if (@rename($src, $target)) {
            $reservedTargets[$target] = true;
            echo "已移动: {$src}\n -> {$target}\n";
            $moved++;
        } else {
            fwrite(STDERR, "失败: {$src}\n");
            $failed++;
        }
    }
}

echo $dryRun ? "\n[dry-run] 共 {$moved} 个文件将移动。\n" : "\n完成：成功 {$moved}，失败 {$failed}。\n";
exit($failed > 0 ? 2 : 0);
