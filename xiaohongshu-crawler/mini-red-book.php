<?php

/**
 * 小红书：博主主页笔记 Tab / 单篇 explore 笔记页图片抓取。解析 SSR，按笔记分子目录保存（见 XHS_EXPORT_INDEX_START）。
 *
 * 说明：
 * - 传入 **笔记 explore 全文 URL**（含 xsec_token）时进入「单篇模式」，只扒该篇正文的图。
 * - 单页 SSR 多图笔记常需 cookie.txt 登录态才能拿全正文图。
 * - 请遵守小红书服务条款与版权，仅限个人备份与学习。
 *
 * 用法：
 *   php mini-red-book.php "https://www.xiaohongshu.com/explore/笔记ID?xsec_token=...&xsec_source=..."
 *   php mini-red-book.php
 *       # 无参数时读脚本常量：遍历 XHS_SINGLE_NOTE_URL_DEFAULT（同笔记 ID 去重、优先带 token），否则 XHS_PROFILE_URL_DEFAULT
 *   php mini-red-book.php --out=DIR --cookie-file=path
 */

declare(strict_types=1);

// —— 本地配置（不配命令行时从此读取） ——
/**
 * 单篇笔记 explore 全文 URL 列表（含 xsec_token）。
 * 无命令行 URL 时，按顺序遍历本数组；同一 noteId 只抓一次（优先保留带有效 xsec_token 的链接）。全空则回退 XHS_PROFILE_URL_DEFAULT。
 */
const XHS_SINGLE_NOTE_URL_DEFAULT = array(
    'https://www.xiaohongshu.com/explore/6a052ca80000000007024ca1?xsec_token=AB8EAUAPLg4UTJkG1XLa2G3-RXKvCjj52FS7WJFD6sSe4%3D&xsec_source=pc_user',
    'https://www.xiaohongshu.com/explore/6940bae4000000001f008769?xsec_token=ABIv8sEawUKZKgEZxHZDjmkAYxycTCU5cM4daUiaCapPA%3D&xsec_source=pc_user',
    'https://www.xiaohongshu.com/explore/6a2b6af80000000008003915?xsec_token=AB_62y4dDzhBz9ouDyqGYpyNN-qMhygVZ_fuqo4hnNNUk%3D&xsec_source=pc_user',
    'https://www.xiaohongshu.com/explore/6a262a290000000021019a3f?xsec_token=ABkW-VO1pLD6E9ufDRnlinl6ZgPGHB33AFvKFKG5JT0po%3D&xsec_source=pc_user',
    'https://www.xiaohongshu.com/explore/6a24dbf0000000002101517d?xsec_token=ABg5dGAY78Tas91bIiEyuKVAANDXJSoeuTWuoxpw6WbOc%3D&xsec_source=pc_user',
    'https://www.xiaohongshu.com/explore/6a22b8d6000000002200b02e?xsec_token=ABNfqtQKEi_I7Gj13nLSA5NlXL4FO5CoafXX3AE3Waq3k%3D&xsec_source=pc_user',
    'https://www.xiaohongshu.com/explore/6a1e3eb4000000002101aa79?xsec_token=ABs6ipFSJGNj7qtW00lyGJ3scObXnEOU-j6B7yqjfcZrk%3D&xsec_source=pc_user',
    'https://www.xiaohongshu.com/explore/6a18f4eb000000000702232c?xsec_token=ABRIRYRO1u1Mc1LV7TLXCHghON_cKyYfybFfyGQgGYm0I%3D&xsec_source=pc_user',
    'https://www.xiaohongshu.com/explore/6a1506a50000000006020201?xsec_token=ABEHpXPoguFNyluNe_gJk2QUrnO24qHHZZKQkKKUE7ssY%3D&xsec_source=pc_user',
    'https://www.xiaohongshu.com/explore/6a0fbed8000000000603046f?xsec_token=AB_VQH_3xrifu6EQwxHvaBkLw4dJeS6ZRmg7gAqLeSuOM%3D&xsec_source=pc_user',
    'https://www.xiaohongshu.com/explore/6a0d13cd000000000702fb51?xsec_token=ABicC_YY5RCswKlZ1LUBr0hjrydWQ81pGMY8UONO6g_sM%3D&xsec_source=pc_user',
    'https://www.xiaohongshu.com/explore/6a0681830000000008003c8d?xsec_token=AB1TiK19o99SQSeHBwNwcw_GnMAeJQ13i66tnlkmmNjhI%3D&xsec_source=pc_user',
    'https://www.xiaohongshu.com/explore/69ffe5b300000000060334c7?xsec_token=AB0TG4jvye51m6UqFrFK_mGkzvZX1HlA01nxa3FROiH0Y%3D&xsec_source=pc_user',
    'https://www.xiaohongshu.com/explore/69fde185000000002301e9c4?xsec_token=ABJDq-Qir9bQsAxrHpAetlc3StOf3ZTxjf_LEjPZYYeGM%3D&xsec_source=pc_user',
    'https://www.xiaohongshu.com/explore/69f556f8000000001e00f255?xsec_token=ABZXReamnhf9MDSHN-1J6U86KtAP5YSfd2SKIYCR1ANnU%3D&xsec_source=pc_user',
    'https://www.xiaohongshu.com/explore/69f2d283000000002301ead6?xsec_token=ABIZBQKD80dmrXGYLUXrhjCjO73znOgQ0g-0TXwDbbATw%3D&xsec_source=pc_user',
    'https://www.xiaohongshu.com/explore/69eb5457000000001b022f87?xsec_token=AB1OAnZzAMVz5YAPf4fyJdy9UiwlNhzahUaIF3t02YAko%3D&xsec_source=pc_user',
    'https://www.xiaohongshu.com/explore/6965b855000000001a0347b6?xsec_token=ABu_rDNR8bdOWNtXSNhrspc2V8G4Mq7u2GCPLeGKXfhbc%3D&xsec_source=pc_user',
    'https://www.xiaohongshu.com/explore/69606476000000001a02dfbd?xsec_token=ABiFAlSBb4vjxESUPTI7w0BO1rxPngObwzj9c5E5zt7eA%3D&xsec_source=pc_user',
    'https://www.xiaohongshu.com/explore/695c8050000000001a0267e5?xsec_token=AB_o1lKYGEPH9950rof7KLmjRp694tcpaQni8jZRskh6E%3D&xsec_source=pc_user',
    'https://www.xiaohongshu.com/explore/69573c3b000000001f006dac?xsec_token=ABw-qTz1jCyngZSdMQqMjL5Y7fYVlW8p8l2pLrOnceIE0%3D&xsec_source=pc_user',
    'https://www.xiaohongshu.com/explore/69534311000000001f00cf26?xsec_token=ABF3Z279mLRupPGd11zzahwFBU2MESocgBRde4HFtR6sI%3D&xsec_source=pc_user',
    'https://www.xiaohongshu.com/explore/694def3c000000001e0271ef?xsec_token=ABOALYxgyKo2QihGAGt7VoyMRM69EmDFAHLBtqXrKdKHI%3D&xsec_source=pc_user',
    'https://www.xiaohongshu.com/explore/6949f06b000000001e033296?xsec_token=ABIeIHxA1ftcFvumPlJ20W0kmagug8kgkMUu3Bc4uRJYM%3D&xsec_source=pc_user',
    'https://www.xiaohongshu.com/explore/6944c14e000000001f00acf9?xsec_token=ABpcGIEBlVT5FcI_lwhh3c9TpCzqGnlviTM5WxLeN5wZg%3D&xsec_source=pc_user',
    'https://www.xiaohongshu.com/explore/693b74b4000000001e025994?xsec_token=ABKYmTha5fIbjkCEChzcR3V_7b7sJQ_O2vj9OS79NKL24%3D&xsec_source=pc_user',
    'https://www.xiaohongshu.com/explore/6934d184000000001f009c7d?xsec_token=ABpknGMyRUZORvCtaAuqq_IxCS9yM3zerA2Z781k6sVCY%3D&xsec_source=pc_user',
    'https://www.xiaohongshu.com/explore/69322eb5000000001f00f9a4?xsec_token=ABv78wuHwYXonBeuIWNw2ZISdbq22d8IqDwVwUioLyjd8%3D&xsec_source=pc_user',
    'https://www.xiaohongshu.com/explore/692c1585000000001f00cae2?xsec_token=ABYpKa9HingB3l-gFLV4sNxlB82S8fS3xHKCx_hPXyD5M%3D&xsec_source=pc_user',
    'https://www.xiaohongshu.com/explore/692972a9000000001f00dd89?xsec_token=ABxlUUM-mdeHg9mSNLCJC0K4IMtui7CgSL0g01oIORzSQ%3D&xsec_source=pc_user',
    'https://www.xiaohongshu.com/explore/6922daff000000001e022d76?xsec_token=AB2kyZm_ial3Nm5LxEDrzHn8bvXhJKTN2XuceYRhrI4rI%3D&xsec_source=pc_user',
    'https://www.xiaohongshu.com/explore/6920380e000000001e02b548?xsec_token=ABm2gSk_YqP-wXkDwtOLri_PY0tMHbqqOF_FaW5J8gh00%3D&xsec_source=pc_user',
    'https://www.xiaohongshu.com/explore/6916fda3000000000402b46a?xsec_token=ABNKGRMFtoZ8hiZi1OiJpua59ndhJIgg8d_RCRxyZv5yU%3D&xsec_source=pc_user',
);
// echo count(XHS_SINGLE_NOTE_URL_DEFAULT);
// exit;
const XHS_PROFILE_URL_DEFAULT = '';
/** 空字符串 = 自动 downloads/用户ID 或 downloads/note_笔记ID */
const XHS_OUT_DIR_DEFAULT = '';
/**
 * Cookie 文件路径。空 = 使用脚本目录下 cookie.txt（不存在则不带 Cookie）。
 * 未登录时 SSR 里笔记没有 noteId，无法请求 explore，正文多图抓不全——请务必配置。
 */
const XHS_COOKIE_FILE_PATH = '';
/** 主页整块 HTML 再扫 CDN（易混无关图，默认关） */
const XHS_PROFILE_SCRAPE_HTML_EXTRA = false;
/** 每条笔记详情请求间隔（毫秒），减轻风控 */
const XHS_DETAIL_FETCH_DELAY_MS = 300;
/** 图片下载失败重试次数 */
const XHS_DOWNLOAD_RETRY = 2;
/** 每次重试间隔（毫秒） */
const XHS_DOWNLOAD_RETRY_DELAY_MS = 200;
/** 如果某篇 explore 提取到的图片行太少，则用 DOM 的 token/source 再拉一次 */
const XHS_REFETCH_IF_DETAIL_ROWS_LESS_EQUAL = 4;
/** 单篇详情页最多重拉次数（只考虑准确性，不考虑速度；每轮合并 URL，减少半屏 SSR 漏图） */
const XHS_DETAIL_FETCH_ATTEMPTS = 3;
/** 详情页重拉间隔（毫秒） */
const XHS_DETAIL_FETCH_ATTEMPT_DELAY_MS = 300;
/**
 * 连续若干轮合并后图片条数不再增加则提前结束重拉（省时间；0=始终拉满 XHS_DETAIL_FETCH_ATTEMPTS）。
 */
const XHS_DETAIL_EARLY_STOP_STABLE_ROUNDS = 2;
/** 全局图片序号起始值（每篇文章单独子目录，文件名仍为连续序号：1.webp、2.webp…） */
const XHS_EXPORT_INDEX_START = 4501;
/** 使用 edith user_posted 分页拉全博主笔记（需 Cookie；失败则退回下方 SSR 合并列表） */
const XHS_USE_USER_POSTED_API = true;
const XHS_USER_POSTED_PAGE_DELAY_MS = 450;

$options = getopt('', [
    'out::',
    'include-avatar',
    'keep-duplicates',
    'cookie::',
    'cookie-file::',
    'xsec-source::',
    'help',
]);
if (isset($options['help'])) {
    fwrite(STDOUT, <<<TXT
用法: php mini-red-book.php [选项] [用户主页URL 或 笔记 explore URL]

  笔记页（单篇）: 传入含 /explore/笔记ID 的完整链接，只下载该篇正文的图片到 downloads/note_笔记ID/。
  博主主页: 传入 /user/profile/... 链接，按笔记列表批量下载。

选项:
  --out=DIR           保存目录（默认本目录下 downloads）
  --include-avatar    同时下载头像等 sns-avatar 链接
  --keep-duplicates   不合并同一张图的预览/默认两种 CDN 变体
  --cookie=STR       Cookie 字符串（用于登录态，缺少时多图正文可能拿不到 note.id）
  --cookie-file=PATH 从文件读取 Cookie（覆盖脚本里默认的 cookie.txt 路径）
  --xsec-source=STR  xsec_source 参数（默认从主页 URL 取；没有则用 pc_feed）
  --help              显示此说明

无参数时: 脚本内 XHS_SINGLE_NOTE_URL_DEFAULT 全部有效 explore 链接（同 ID 去重、优先 token），否则 XHS_PROFILE_URL_DEFAULT（主页）。
正文多图建议在脚本目录放置 cookie.txt（浏览器登录 xiaohongshu.com 后导出整段 Cookie）。
explore URL 须原样复制地址栏完整 xsec_token，勿用「...」占位。

TXT);
    exit(0);
}

// 未显式传入 URL 时，$argv[$argc-1] 会落到脚本自身路径，导致抓取错误。
$profileUrl = '';
for ($i = 1; $i < $argc; $i++) {
    $arg = $argv[$i] ?? '';
    if ($arg === '' || str_starts_with($arg, '--')) {
        continue;
    }
    $profileUrl = $arg; // 取最后一个非选项参数作为 URL
}
$singleExploreQueue = [];
if ($profileUrl === '') {
    $singleExploreQueue = normalizeDefaultSingleNoteExploreUrls(XHS_SINGLE_NOTE_URL_DEFAULT);
    $profileUrl = $singleExploreQueue !== [] ? $singleExploreQueue[0] : XHS_PROFILE_URL_DEFAULT;
} elseif (isExploreNoteUrl($profileUrl)) {
    $singleExploreQueue = [$profileUrl];
}

$outDirOpt = isset($options['out']) ? (string)$options['out'] : '';
if ($outDirOpt !== '') {
    $outDir = $outDirOpt;
} elseif (XHS_OUT_DIR_DEFAULT !== '') {
    $outDir = XHS_OUT_DIR_DEFAULT;
} elseif (preg_match('#/user/profile/([a-f0-9]+)#i', $profileUrl, $um)) {
    $outDir = __DIR__ . DIRECTORY_SEPARATOR . 'downloads' . DIRECTORY_SEPARATOR . $um[1];
} elseif (count($singleExploreQueue) > 1) {
    $outDir = __DIR__ . DIRECTORY_SEPARATOR . 'downloads';
} elseif (preg_match('#/explore/([0-9a-f]{20,})#i', $profileUrl, $um)) {
    $outDir = __DIR__ . DIRECTORY_SEPARATOR . 'downloads' . DIRECTORY_SEPARATOR . 'note_' . $um[1];
} else {
    $outDir = __DIR__ . DIRECTORY_SEPARATOR . 'downloads';
}

$includeAvatar = isset($options['include-avatar']);
$keepDuplicates = isset($options['keep-duplicates']);
$cookieHeader = (string)($options['cookie'] ?? '');
$cookieFile = (string)($options['cookie-file'] ?? '');
if ($cookieFile === '') {
    $cookieFile = XHS_COOKIE_FILE_PATH !== ''
        ? XHS_COOKIE_FILE_PATH
        : (__DIR__ . DIRECTORY_SEPARATOR . 'cookie.txt');
}
if ($cookieHeader === '' && is_readable($cookieFile)) {
    $cookieHeader = trim((string)file_get_contents($cookieFile));
}

$profileQuery = [];
$profileQueryStr = parse_url($profileUrl, PHP_URL_QUERY);
if (is_string($profileQueryStr) && $profileQueryStr !== '') {
    parse_str($profileQueryStr, $profileQuery);
}
$xsecSource = (string)($options['xsec-source'] ?? '');
if ($xsecSource === '') {
    $xsecSource = (string)($profileQuery['xsec_source'] ?? 'pc_feed');
}
$xsecSource = normalizeDetailXsecSource($xsecSource);

$isExplorePageEarly = $singleExploreQueue !== [];
if ($isExplorePageEarly && $cookieHeader === '') {
    fwrite(STDERR, "提示: 单篇 explore 未带 Cookie；正文多图可能不全。建议在脚本目录放置 cookie.txt（登录后导出整段 Cookie）。\n");
}
if (!$isExplorePageEarly && $cookieHeader === '') {
    if (!is_readable($cookieFile)) {
        fwrite(STDERR, "提示: 未找到 Cookie 文件（尝试过: {$cookieFile}）。\n");
    } else {
        fwrite(STDERR, "提示: Cookie 文件为空。\n");
    }
    fwrite(STDERR, "      未登录时主页 SSR 通常不下发笔记 noteId，无法请求 /explore/ 拉正文全部图片，往往只剩封面。\n");
    fwrite(STDERR, "      请将浏览器已登录 xiaohongshu.com 后的整段 Cookie 保存为 cookie.txt（同目录）。\n\n");
}

if (!ensureOutDirExists($outDir)) {
    fwrite(STDERR, "无法创建输出目录: {$outDir}\n");
    exit(1);
}

$isExplorePage = $singleExploreQueue !== [];

if ($isExplorePage) {
    $batchCount = count($singleExploreQueue);
    if ($batchCount > 1) {
        fwrite(STDERR, "单篇笔记批量模式: 依 XHS_SINGLE_NOTE_URL_DEFAULT 共 {$batchCount} 篇，输出在 downloads/ 下按笔记分子目录（或 --out）。\n");
    } else {
        fwrite(STDERR, "单篇笔记模式: 仅抓取本页正文图片，输出 downloads/note_* / 或 --out 指定目录。\n");
    }
    $collected = [];
    $anyStateMissing = false;
    foreach ($singleExploreQueue as $qi => $explorePageUrl) {
        if (isSuspiciousExploreUrl($explorePageUrl)) {
            fwrite(STDERR, "提示: 下列链接可能缺有效 xsec_token，建议从浏览器复制完整地址栏 URL：\n  {$explorePageUrl}\n");
        }
        $html = fetchUrl($explorePageUrl, $cookieHeader, 'https://www.xiaohongshu.com/');
        if ($html === null || $html === '') {
            fwrite(STDERR, "获取笔记页失败，跳过: {$explorePageUrl}\n");
            continue;
        }
        $detailState = extractInitialState($html);
        if ($detailState === null) {
            $anyStateMissing = true;
        }
        $singleTitle = extractNoteTitleFromState($detailState);
        if ($singleTitle === '') {
            $singleTitle = 'note';
        }
        $noteIdx = $qi + 1;
        $detailRows = fetchDetailRowsRobust(
            $explorePageUrl,
            $cookieHeader,
            'https://www.xiaohongshu.com/',
            $includeAvatar,
            $noteIdx,
            $singleTitle
        );
        foreach ($detailRows as $row) {
            $collected[] = $row;
        }
        if ($qi + 1 < $batchCount && XHS_DETAIL_FETCH_DELAY_MS > 0) {
            usleep(XHS_DETAIL_FETCH_DELAY_MS * 1000);
        }
    }
    if ($anyStateMissing) {
        fwrite(STDERR, "警告: 部分笔记页未解析到 __INITIAL_STATE__，仅使用 HTML 正则兜底，张数可能不全。\n");
    }
} else {
    $html = fetchUrl($profileUrl, $cookieHeader, 'https://www.xiaohongshu.com/');
    if ($html === null || $html === '') {
        fwrite(STDERR, "获取页面失败。\n");
        exit(1);
    }

    $state = extractInitialState($html);
    if ($state === null) {
        fwrite(STDERR, "未解析到 window.__INITIAL_STATE__，页面结构可能已变更。\n");
        exit(1);
    }

    $profileUserIdForApi = '';
    if (preg_match('#/user/profile/([a-f0-9]{24})#i', $profileUrl, $uidm)) {
        $profileUserIdForApi = strtolower($uidm[1]);
    }
    $useApiFeed = false;
    $notes = [];
    if (XHS_USE_USER_POSTED_API && $profileUserIdForApi !== '' && $cookieHeader !== '') {
        $apiList = fetchUserPostedNotesAll(
            $profileUserIdForApi,
            $cookieHeader,
            max(XHS_USER_POSTED_PAGE_DELAY_MS, XHS_DETAIL_FETCH_DELAY_MS),
            $profileUrl
        );
        if ($apiList !== []) {
            $notes = $apiList;
            $useApiFeed = true;
            fwrite(STDERR, '提示: user_posted 已拉取 ' . count($notes) . " 篇笔记。\n");
        }
    }
    if (!$useApiFeed) {
        $notes = extractProfileNotesList($state);
    }

    $collected = [];
    $index = 0;
    $detailFetched = [];
    $missingNoteIdCount = 0;
    $totalNotesCount = count($notes);
    $jsonHasNoteIdCount = 0;
    /** noteId => collectRowsFromNoteDetailHtml 的行数 */
    $detailRowsCount = [];
    /** noteId => 主页 feed 中的顺序（1-based），供 DOM 补抓时对齐原文章的排序与去重分组 */
    $noteIdToFeedIndex = [];
    /** noteId => 该条在 feed 里展示标题 */
    $noteIdToTitle = [];
    foreach ($notes as $item) {
        if (!is_array($item)) {
            continue;
        }
        $index++;
        if ($useApiFeed) {
            $card = [];
            $title = sanitizeFilename((string)(
                $item['display_title'] ?? $item['title'] ?? $item['displayTitle'] ?? ('note_' . $index)
            ));
            $noteId = strtolower(trim((string)($item['note_id'] ?? '')));
            if ($noteId === '' || !preg_match('/^[0-9a-f]{24}$/', $noteId)) {
                $noteId = extractNoteIdFromFeedItem($item);
            }
            $noteXsecToken = (string)($item['xsec_token'] ?? $item['xsecToken'] ?? '');
            $noteXsecSource = normalizeDetailXsecSource((string)($item['xsec_source'] ?? $xsecSource));
        } else {
            $card = $item['noteCard'] ?? [];
            $title = sanitizeFilename((string)($card['displayTitle'] ?? ('note_' . $index)));
            $noteId = extractNoteIdFromFeedItem($item);
            $noteXsecToken = (string)($item['xsecToken'] ?? $card['xsecToken'] ?? '');
            $noteXsecSource = normalizeDetailXsecSource((string)($item['xsecSource'] ?? $item['xsec_source'] ?? $card['xsecSource'] ?? $card['xsec_source'] ?? $xsecSource));
        }
        if ($noteId === '') {
            $missingNoteIdCount++;
        }
        if ($noteId !== '') {
            $jsonHasNoteIdCount++;
            $noteIdToFeedIndex[$noteId] = $index;
            $noteIdToTitle[$noteId] = $title;
        }

        $urls = [];
        // 优先从 note 对象本身取（有登录态时可能包含 noteCard.imageList）
        collectUrlsFromValue($item, $urls);

        foreach ($urls as $u) {
            $u = html_entity_decode($u, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $u = cleanXhsCdnUrl($u);
            $u = normalizeMaybeSchemeRelativeUrl($u);
            if (!is_string($u) || !str_starts_with($u, 'http')) {
                continue;
            }
            if (!isLikelyXhsImageUrl($u)) {
                continue;
            }
            if (!$includeAvatar && str_contains($u, 'sns-avatar')) {
                continue;
            }
            $collected[] = ['title' => $title, 'note_index' => $index, 'url' => $u, 'referer' => $profileUrl];
        }

        // 有 noteId 即拉详情；无 xsec_token 时仍试裸 explore（避免漏篇）
        if ($noteId !== '' && !isset($detailFetched[$noteId])) {
            $detailFetched[$noteId] = true;
            $detailUrl = buildExploreNoteUrl(
                $noteId,
                $noteXsecToken,
                $noteXsecSource !== '' ? $noteXsecSource : $xsecSource
            );

            $detailRows = fetchDetailRowsRobust(
                $detailUrl,
                $cookieHeader,
                $profileUrl,
                $includeAvatar,
                $index,
                $title
            );
            foreach ($detailRows as $row) {
                $collected[] = $row;
            }
            $detailRowsCount[$noteId] = count($detailRows);
            if (count($detailRows) <= 1) {
                fwrite(STDERR, '详情图偏少: noteId=' . $noteId . ' source=' . $noteXsecSource . ' rows=' . count($detailRows) . "\n");
            }
            if (XHS_DETAIL_FETCH_DELAY_MS > 0) {
                usleep(XHS_DETAIL_FETCH_DELAY_MS * 1000);
            }
        }
    }

    if ($missingNoteIdCount > 0) {
        fwrite(STDERR, "提示: 共 {$missingNoteIdCount} 条笔记缺少 noteId，已无法拉 explore 正文多图（请用 cookie.txt 提供登录态）。\n");
    }

    // 兜底：noteId 可能不在解析到的 JSON 字段里，而是在主页 HTML 的 DOM href 里。
    // 例如：
    // href="/user/profile/<userId>/<noteId>?xsec_token=...&amp;xsec_source=pc_user"
    {
        $profileUserId = '';
        $domList = [];
        $domFetchedCount = 0;
        if (preg_match('#/user/profile/([a-f0-9]{24})#i', $profileUrl, $um)) {
            $profileUserId = strtolower($um[1]);
        }
        if ($profileUserId !== '') {
            // DOM 里出现、但不在 feed JSON 的极少数情况，顺序接在 feed 后面
            $extraFeedIndex = $index;

            // 1) user/profile 形式（通常带 xsec_source，也可能缺失）
            $reUserProfile = '#href="/user/profile/' . preg_quote($profileUserId, '#') .
                '/([0-9a-f]{24})\\?xsec_token=([^&"\'<>]+)(?:&amp;|&)?(?:xsec_source=([^&"\'<>]+))?"#i';

            // 2) 直接 explore 形式（通常 token 在 query 里；source 可能缺失）
            $reExplore = '#href="/explore/([0-9a-f]{24})\\?xsec_token=([^&"\'<>]+)(?:&amp;|&)?(?:xsec_source=([^&"\'<>]+))?"#i';

            // 3) 裸 /explore/{id}（无 query），避免 HTML 里只有短链时漏篇
            $reExploreBare = '#href="/explore/([0-9a-f]{24})"#i';

            $domSeen = [];

            $pushCandidate = function (string $nid, string $token, string $source) use (&$domSeen, &$domList, $xsecSource): void {
                $nid = strtolower(trim($nid));
                $token = trim($token);
                if ($nid === '') {
                    return;
                }
                if (isset($domSeen[$nid])) {
                    return;
                }
                $domSeen[$nid] = true;
                $domList[] = [
                    'noteId' => $nid,
                    'token' => $token,
                    'source' => normalizeDetailXsecSource($source !== '' ? $source : (string)$xsecSource),
                ];
            };

            $mm1 = [];
            if (preg_match_all($reUserProfile, $html, $mm1, PREG_SET_ORDER) && is_array($mm1)) {
                foreach ($mm1 as $m) {
                    $domNoteId = (string)($m[1] ?? '');
                    $domToken = html_entity_decode((string)($m[2] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $domSource = html_entity_decode((string)($m[3] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $pushCandidate($domNoteId, $domToken, $domSource);
                }
            }

            $mm2 = [];
            if (preg_match_all($reExplore, $html, $mm2, PREG_SET_ORDER) && is_array($mm2)) {
                foreach ($mm2 as $m) {
                    $domNoteId = (string)($m[1] ?? '');
                    $domToken = html_entity_decode((string)($m[2] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $domSource = html_entity_decode((string)($m[3] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    $pushCandidate($domNoteId, $domToken, $domSource);
                }
            }

            $mm3 = [];
            if (preg_match_all($reExploreBare, $html, $mm3, PREG_SET_ORDER) && is_array($mm3)) {
                foreach ($mm3 as $m) {
                    $domNoteId = (string)($m[1] ?? '');
                    $pushCandidate($domNoteId, '', '');
                }
            }

            foreach ($domList as $cand) {
                $domNoteId = (string)($cand['noteId'] ?? '');
                $domToken = (string)($cand['token'] ?? '');
                $domSource = normalizeDetailXsecSource((string)($cand['source'] ?? $xsecSource));

                if ($domNoteId === '') {
                    continue;
                }
                $alreadyFetched = isset($detailFetched[$domNoteId]);
                $haveRows = isset($detailRowsCount[$domNoteId]) ? (int)$detailRowsCount[$domNoteId] : 0;
                // 如果已经抓过但提取到的图片行太少，说明可能返回不完整内容，尝试用 DOM 的 token/source 再拉一次
                if ($alreadyFetched && $haveRows > XHS_REFETCH_IF_DETAIL_ROWS_LESS_EQUAL) {
                    continue;
                }

                $detailFetched[$domNoteId] = true;
                $domFetchedCount++;
                $detailUrl = buildExploreNoteUrl($domNoteId, $domToken, $domSource);

                $feedIdx = $noteIdToFeedIndex[$domNoteId] ?? null;
                if ($feedIdx !== null) {
                    $useIndex = $feedIdx;
                    $useTitle = $noteIdToTitle[$domNoteId] ?? 'note_dom';
                } else {
                    $extraFeedIndex++;
                    $useIndex = $extraFeedIndex;
                    $useTitle = 'note_dom';
                }

                $detailRows = fetchDetailRowsRobust(
                    $detailUrl,
                    $cookieHeader,
                    $profileUrl,
                    $includeAvatar,
                    $useIndex,
                    $useTitle
                );
                foreach ($detailRows as $row) {
                    $collected[] = $row;
                }
                $detailRowsCount[$domNoteId] = max(
                    (int)($detailRowsCount[$domNoteId] ?? 0),
                    count($detailRows)
                );

                if (XHS_DETAIL_FETCH_DELAY_MS > 0) {
                    usleep(XHS_DETAIL_FETCH_DELAY_MS * 1000);
                }
            }
        }

        $domListCount = count($domList);
        fwrite(STDERR, "提示: feed笔记 {$totalNotesCount}，JSON noteId {$jsonHasNoteIdCount}；DOM候选 {$domListCount}，新增拉详情 {$domFetchedCount}。\n");
    }

    // 从整页 HTML 再扫 CDN（易产生无关图，默认关闭）
    if (XHS_PROFILE_SCRAPE_HTML_EXTRA) {
        foreach (scrapeImageUrlsFromHtmlString($html) as $u) {
            $collected[] = ['title' => '_page_extra', 'note_index' => 0, 'url' => $u, 'referer' => $profileUrl];
        }
    }
}

if (!$keepDuplicates) {
    // 只在同一篇文章内去重，避免跨文章合并导致“看起来少图”
    $grouped = [];
    foreach ($collected as $row) {
        $ni = (int)($row['note_index'] ?? 0);
        $grouped[$ni][] = $row;
    }
    ksort($grouped, SORT_NUMERIC);
    $deduped = [];
    foreach ($grouped as $ni => $rows) {
        $deduped = array_merge($deduped, dedupeByFileIdPreferDft($rows));
    }
    $collected = $deduped;
}

// 导出顺序：先按博主 feed 中的笔记顺序（note_index），再按收集时的先后顺序（同篇内尽量接近轮播顺序）
$sortBuf = [];
foreach ($collected as $oi => $row) {
    if (!is_array($row)) {
        continue;
    }
    $row['_ord'] = $oi;
    $sortBuf[] = $row;
}
usort($sortBuf, static function (array $a, array $b): int {
    $c = ((int)($a['note_index'] ?? 0)) <=> ((int)($b['note_index'] ?? 0));
    if ($c !== 0) {
        return $c;
    }
    return ((int)($a['_ord'] ?? 0)) <=> ((int)($b['_ord'] ?? 0));
});
$collected = [];
foreach ($sortBuf as $row) {
    unset($row['_ord']);
    $collected[] = $row;
}

// 抓取可能较久，写入前再确保根输出目录存在
if (!ensureOutDirExists($outDir)) {
    fwrite(STDERR, "无法创建输出目录: {$outDir}\n");
    exit(1);
}

// 按笔记序号分组（一篇一个子目录），组内顺序即导出顺序
$byNoteIndex = [];
foreach ($collected as $row) {
    if (!is_array($row)) {
        continue;
    }
    $ni = (int)($row['note_index'] ?? 0);
    $byNoteIndex[$ni][] = $row;
}
ksort($byNoteIndex, SORT_NUMERIC);

$seenUrl = [];
$n = 0;
$exportSeq = XHS_EXPORT_INDEX_START;

foreach ($byNoteIndex as $noteIndex => $rows) {
    if ($rows === []) {
        continue;
    }
    $firstTitle = (string)($rows[0]['title'] ?? 'note');
    $noteSubDir = buildNoteSubdirName($noteIndex, $firstTitle);
    $noteDir = $outDir . DIRECTORY_SEPARATOR . $noteSubDir;
    if (!ensureOutDirExists($noteDir)) {
        fwrite(STDERR, "无法创建笔记目录: {$noteDir}\n");
        continue;
    }

    foreach ($rows as $row) {
        $u = $row['url'];
        if (isset($seenUrl[$u])) {
            continue;
        }
        $seenUrl[$u] = true;

        $ext = guessExt($u);
        $safe = (string)$exportSeq . $ext;
        $path = $noteDir . DIRECTORY_SEPARATOR . $safe;

        $referer = (string)($row['referer'] ?? $profileUrl);
        $bin = null;
        for ($try = 0; $try < XHS_DOWNLOAD_RETRY; $try++) {
            $bin = fetchUrl($u, $cookieHeader, $referer);
            if ($bin !== null && $bin !== '') {
                break;
            }
            if ($try + 1 < XHS_DOWNLOAD_RETRY && XHS_DOWNLOAD_RETRY_DELAY_MS > 0) {
                usleep(XHS_DOWNLOAD_RETRY_DELAY_MS * 1000);
            }
        }
        if ($bin === null || $bin === '') {
            fwrite(STDERR, "跳过（下载失败）: {$u}\n");
            continue;
        }
        $written = @file_put_contents($path, $bin);
        if ($written !== false) {
            $n++;
            $exportSeq++;
            fwrite(STDOUT, "已保存 {$path}\n");
        } else {
            fwrite(STDERR, "写入失败（请检查目录是否可写、磁盘空间）: {$path}\n");
        }
    }
}

fwrite(STDOUT, "\n共写入 {$n} 个文件，根目录: {$outDir}（每篇笔记一个子文件夹，文件名为全局连续序号）\n");

if ($n === 0) {
    $hint = [];
    $cnt = count($collected);
    if ($cnt === 0) {
        $hint[] = '未收集到任何图片链接。';
        if ($isExplorePage) {
            $hint[] = '- 笔记页须使用完整 explore URL，xsec_token 与浏览器地址栏一致（勿用省略号代替真实 token）。';
            $hint[] = '- 若仍为空，可加 --cookie-file=已登录后导出的 Cookie；或检查是否被要求验证/登录。';
        }
    } else {
        $hint[] = "已收集 {$cnt} 个图片地址，但下载全部失败（CDN 或网络）。可尝试 --cookie-file，并确认本机可访问图片域名。";
    }
    fwrite(STDERR, "\n" . implode("\n", $hint) . "\n");
}

// ---------- helpers ----------

/**
 * 递归创建输出目录；若路径已存在且为文件则失败。
 */
function ensureOutDirExists(string $dir): bool
{
    if ($dir === '') {
        return false;
    }
    if (is_dir($dir)) {
        return true;
    }
    if (file_exists($dir) && !is_dir($dir)) {
        fwrite(STDERR, "输出路径已存在且不是目录，请删除或更换 --out: {$dir}\n");
        return false;
    }
    return @mkdir($dir, 0755, true);
}

/**
 * 一篇笔记对应一个子目录名：001_标题；note_index=0 的兜底为 00_extra。
 */
function buildNoteSubdirName(int $noteIndex, string $title): string
{
    if ($noteIndex === 0) {
        return '00_extra';
    }
    $t = sanitizeFilename($title);
    if ($t === '' || $t === '_page_extra') {
        $t = 'note';
    }
    $t = mb_substr($t, 0, 80);
    return sprintf('%03d_%s', $noteIndex, $t);
}

function fetchUrl(string $url, string $cookieHeader = '', ?string $referer = null): ?string
{
    $cookieHeaderLine = $cookieHeader !== '' ? ("Cookie: {$cookieHeader}\r\n") : '';
    $ref = $referer !== null && $referer !== '' ? $referer : 'https://www.xiaohongshu.com/';
    $ctx = stream_context_create([
        'http' => [
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36\r\n" .
                "Accept: text/html,application/xhtml+xml,image/webp,*/*;q=0.8\r\n" .
                $cookieHeaderLine .
                "Referer: {$ref}\r\n",
            'timeout' => 30,
            'follow_location' => 1,
        ],
        // 这里关闭 SSL 校验是为了避免在当前环境证书链无法验证时
        // 导致详情页（https://.../explore/）请求失败，进而无法提取正文多图。
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $data = @file_get_contents($url, false, $ctx);
    return $data !== false ? $data : null;
}

/**
 * 同一篇详情页做多轮抓取并合并，优先准确率。
 *
 * @return list<array{title:string, note_index:int, url:string, referer:string}>
 */
function fetchDetailRowsRobust(
    string $detailUrl,
    string $cookieHeader,
    string $profileUrl,
    bool $includeAvatar,
    int $noteIndex,
    string $title
): array {
    $merged = [];
    $seen = [];
    $noNewStreak = 0;

    for ($attempt = 1; $attempt <= XHS_DETAIL_FETCH_ATTEMPTS; $attempt++) {
        $sizeBefore = count($merged);
        $detailHtml = fetchUrl($detailUrl, $cookieHeader, $profileUrl);
        if (!is_string($detailHtml) || $detailHtml === '') {
            if ($attempt < XHS_DETAIL_FETCH_ATTEMPTS && XHS_DETAIL_FETCH_ATTEMPT_DELAY_MS > 0) {
                usleep(XHS_DETAIL_FETCH_ATTEMPT_DELAY_MS * 1000);
            }
            continue;
        }

        $rows = collectRowsFromNoteDetailHtml($detailHtml, $includeAvatar, $noteIndex, $title, null, $detailUrl);
        foreach ($rows as $row) {
            $u = (string)($row['url'] ?? '');
            if ($u === '' || isset($seen[$u])) {
                continue;
            }
            $seen[$u] = true;
            $merged[] = $row;
        }

        $sizeAfter = count($merged);
        if ($sizeAfter === $sizeBefore && $attempt > 1) {
            $noNewStreak++;
            if (
                XHS_DETAIL_EARLY_STOP_STABLE_ROUNDS > 0
                && $noNewStreak >= XHS_DETAIL_EARLY_STOP_STABLE_ROUNDS
            ) {
                break;
            }
        } else {
            $noNewStreak = 0;
        }

        if ($attempt < XHS_DETAIL_FETCH_ATTEMPTS && XHS_DETAIL_FETCH_ATTEMPT_DELAY_MS > 0) {
            usleep(XHS_DETAIL_FETCH_ATTEMPT_DELAY_MS * 1000);
        }
    }

    return $merged;
}

/**
 * 从「用户主页笔记流」单条 item 中取笔记 OID（24 位十六进制），兼容多种字段形态。
 */
function extractNoteIdFromFeedItem(array $item): string
{
    $card = isset($item['noteCard']) && is_array($item['noteCard']) ? $item['noteCard'] : [];
    $candidates = [
        $item['note_id'] ?? null,
        $item['id'] ?? null,
        $item['noteId'] ?? null,
        $card['noteId'] ?? null,
    ];
    $nestedNote = isset($card['note']) && is_array($card['note']) ? $card['note'] : [];
    if ($nestedNote !== []) {
        $candidates[] = $nestedNote['noteId'] ?? null;
        $candidates[] = $nestedNote['id'] ?? null;
    }
    foreach ($candidates as $c) {
        if (!is_string($c)) {
            continue;
        }
        $c = trim($c);
        if (preg_match('/^[0-9a-f]{24}$/i', $c)) {
            return strtolower($c);
        }
    }

    // 兜底：有些 SSR 结构下 noteId 不在常规字段，而是嵌在 item 其他子字段里。
    // 注意 userId 也是 24 位 hex，所以要排除掉 userId。
    $userId = '';
    $possibleUserId =
        $card['user']['userId'] ??
        $card['userId'] ??
        ($item['user']['userId'] ?? null);
    if (is_string($possibleUserId)) {
        $userId = strtolower(trim($possibleUserId));
    }

    $encoded = json_encode($item, JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded) || $encoded === '') {
        return '';
    }
    if (preg_match_all('/([0-9a-f]{24})/i', $encoded, $mm)) {
        foreach (($mm[1] ?? []) as $m) {
            $m = strtolower((string)$m);
            if ($userId !== '' && $m === $userId) {
                continue;
            }
            return $m;
        }
    }
    return '';
}

function isLikelyFeedNoteItem(array $item): bool
{
    if (isset($item['noteCard']) || isset($item['note_id']) || isset($item['noteId'])) {
        return true;
    }
    if (isset($item['display_title']) || isset($item['displayTitle'])) {
        return true;
    }
    if (isset($item['id']) && is_string($item['id']) && preg_match('/^[0-9a-f]{24}$/i', $item['id'])) {
        return true;
    }
    return false;
}

/**
 * @param mixed $raw
 * @return list<array<string,mixed>>
 */
function mergeRawNotesArray($raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    if (isset($raw['list']) && is_array($raw['list'])) {
        return mergeRawNotesArray($raw['list']);
    }
    if (isset($raw['notes']) && is_array($raw['notes'])) {
        return mergeRawNotesArray($raw['notes']);
    }
    $merged = [];
    $first = reset($raw);
    if (is_array($first) && isLikelyFeedNoteItem($first)) {
        foreach ($raw as $item) {
            if (is_array($item) && isLikelyFeedNoteItem($item)) {
                $merged[] = $item;
            }
        }
        return $merged;
    }
    foreach ($raw as $tab) {
        if (!is_array($tab)) {
            continue;
        }
        if (isset($tab['items']) && is_array($tab['items'])) {
            foreach ($tab['items'] as $item) {
                if (is_array($item) && isLikelyFeedNoteItem($item)) {
                    $merged[] = $item;
                }
            }
            continue;
        }
        if (isLikelyFeedNoteItem($tab)) {
            $merged[] = $tab;
            continue;
        }
        foreach ($tab as $item) {
            if (is_array($item) && isLikelyFeedNoteItem($item)) {
                $merged[] = $item;
            }
        }
    }
    return $merged;
}

/**
 * @return list<array<string,mixed>>
 */
function extractProfileNotesList(array $state): array
{
    $user = $state['user'] ?? null;
    if (!is_array($user)) {
        return extractProfileNotesListFromGlobal($state);
    }
    $paths = [
        ['notes'],
        ['noteList'],
        ['posted'],
        ['userPageData', 'notes'],
    ];
    foreach ($paths as $path) {
        $raw = $user;
        $ok = true;
        foreach ($path as $seg) {
            if (!is_array($raw) || !isset($raw[$seg])) {
                $ok = false;
                break;
            }
            $raw = $raw[$seg];
        }
        if ($ok) {
            $merged = mergeRawNotesArray($raw);
            if ($merged !== []) {
                return $merged;
            }
        }
    }
    $raw = $user['notes'] ?? null;
    if (is_array($raw)) {
        $merged = mergeRawNotesArray($raw);
        if ($merged !== []) {
            return $merged;
        }
    }
    return extractProfileNotesListFromGlobal($state);
}

/**
 * @return list<array<string,mixed>>
 */
function extractProfileNotesListFromGlobal(array $state): array
{
    foreach ($state as $v) {
        if (!is_array($v)) {
            continue;
        }
        if (isset($v['notes']) && is_array($v['notes'])) {
            $m = mergeRawNotesArray($v['notes']);
            if ($m !== []) {
                return $m;
            }
        }
    }
    return [];
}

function normalizeDetailXsecSource(string $source): string
{
    $s = strtolower(trim($source));
    // 详情场景下 pc_search 不稳定，统一回落到 pc_user。
    if ($s === '' || $s === 'pc_search') {
        return 'pc_user';
    }
    return $s;
}

function buildExploreNoteUrl(string $noteId, string $token, string $xsecSource): string
{
    $base = 'https://www.xiaohongshu.com/explore/' . rawurlencode($noteId);
    if ($token !== '') {
        $src = normalizeDetailXsecSource($xsecSource);
        return $base . '?xsec_token=' . rawurlencode($token) . '&xsec_source=' . rawurlencode($src);
    }
    return $base;
}

function httpGetBodyCurlOrStream(string $url, array $headerLines): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_ENCODING => '',
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        if ($body === false || $body === '') {
            return null;
        }
        return $body;
    }
    $h = implode("\r\n", $headerLines);
    $ctx = stream_context_create([
        'http' => [
            'header' => $h . "\r\n",
            'timeout' => 45,
            'follow_location' => 1,
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false],
    ]);
    $data = @file_get_contents($url, false, $ctx);
    return ($data === false || $data === '') ? null : $data;
}

/**
 * @return array<string,mixed>|null
 */
function fetchJsonApi(string $url, string $cookieHeader, string $referer = 'https://www.xiaohongshu.com/'): ?array
{
    $headers = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
        'Accept: application/json, text/plain, */*',
        'Referer: ' . $referer,
        'Origin: https://www.xiaohongshu.com',
        'Accept-Language: zh-CN,zh;q=0.9,en;q=0.8',
        'Sec-Fetch-Dest: empty',
        'Sec-Fetch-Mode: cors',
        'Sec-Fetch-Site: same-site',
    ];
    if ($cookieHeader !== '') {
        $headers[] = 'Cookie: ' . $cookieHeader;
    }
    $data = httpGetBodyCurlOrStream($url, $headers);
    if ($data === null || $data === '') {
        return null;
    }
    $j = json_decode($data, true);
    return is_array($j) ? $j : null;
}

/**
 * @return list<array<string,mixed>>
 */
function fetchUserPostedNotesAll(string $userId, string $cookieHeader, int $pageDelayMs, string $profilePageReferer = ''): array
{
    if ($profilePageReferer === '') {
        $profilePageReferer = 'https://www.xiaohongshu.com/user/profile/' . rawurlencode($userId)
            . '?xsec_source=pc_feed&tab=note';
    }
    $apiBases = [
        'https://edith.xiaohongshu.com/api/sns/web/v1/user_posted',
        'https://www.xiaohongshu.com/api/sns/web/v1/user_posted',
    ];
    $all = [];
    $cursor = '';
    for ($page = 0; $page < 200; $page++) {
        $qs = http_build_query([
            'user_id' => $userId,
            'cursor' => $cursor,
            'num' => 30,
            'image_scenes' => 'FD_WM_WEBP',
        ]);
        $json = null;
        $lastFailure = null;
        $gotAnyBody = false;
        foreach ($apiBases as $base) {
            $tryUrl = $base . '?' . $qs;
            $try = fetchJsonApi($tryUrl, $cookieHeader, $profilePageReferer);
            if ($try === null) {
                continue;
            }
            $gotAnyBody = true;
            $code = isset($try['code']) ? (int)$try['code'] : 0;
            if ($code === 0) {
                $json = $try;
                break;
            }
            $lastFailure = $try;
            if ($code !== -1) {
                $json = $try;
                break;
            }
        }
        if ($json === null) {
            $json = $lastFailure;
        }
        if ($json === null) {
            if ($page === 0 && !$gotAnyBody) {
                fwrite(STDERR, "提示: user_posted 无响应，将使用主页 SSR 笔记列表。\n");
            }
            break;
        }
        if (isset($json['code']) && (int)$json['code'] !== 0) {
            if ($page === 0) {
                $msg = (string)($json['msg'] ?? $json['message'] ?? '');
                fwrite(STDERR, '提示: user_posted code=' . (int)$json['code'] . ($msg !== '' ? "（{$msg}）" : '') . "，改用 SSR。\n");
            }
            break;
        }
        $data = $json['data'] ?? null;
        if (!is_array($data)) {
            break;
        }
        $notes = $data['notes'] ?? [];
        if (!is_array($notes)) {
            $notes = [];
        }
        foreach ($notes as $n) {
            if (is_array($n)) {
                $all[] = $n;
            }
        }
        $hasMore = !empty($data['has_more']);
        $cursor = (string)($data['cursor'] ?? '');
        if (!$hasMore || $cursor === '') {
            break;
        }
        if ($pageDelayMs > 0) {
            usleep($pageDelayMs * 1000);
        }
    }
    return $all;
}

function isExploreNoteUrl(string $url): bool
{
    return (bool)preg_match('#/explore/([0-9a-f]{20,})#i', $url);
}

/**
 * 脚本常量里的 explore 列表：按 noteId 去重，保留首次出现顺序；同一 ID 若后出现带「可用」xsec_token 的 URL 则替换。
 *
 * @return list<string>
 */
function normalizeDefaultSingleNoteExploreUrls(array $urls): array
{
    $byIdOrder = [];
    /** @var array<string, string> */
    $byIdUrl = [];
    foreach ($urls as $u) {
        if (!is_string($u)) {
            continue;
        }
        $u = trim($u);
        if ($u === '' || !isExploreNoteUrl($u)) {
            continue;
        }
        if (!preg_match('#/explore/([0-9a-f]{24})#i', $u, $m)) {
            continue;
        }
        $nid = strtolower($m[1]);
        $hasUsableToken = false;
        $qs = parse_url($u, PHP_URL_QUERY);
        if (is_string($qs) && $qs !== '') {
            parse_str($qs, $q);
            $tok = (string)($q['xsec_token'] ?? '');
            $hasUsableToken = $tok !== '' && !str_contains($tok, '...') && strlen($tok) >= 40;
        }
        if (!isset($byIdUrl[$nid])) {
            $byIdOrder[] = $nid;
            $byIdUrl[$nid] = $u;
            continue;
        }
        $prev = $byIdUrl[$nid];
        $prevHas = false;
        $prevQs = parse_url($prev, PHP_URL_QUERY);
        if (is_string($prevQs) && $prevQs !== '') {
            parse_str($prevQs, $pq);
            $pt = (string)($pq['xsec_token'] ?? '');
            $prevHas = $pt !== '' && !str_contains($pt, '...') && strlen($pt) >= 40;
        }
        if ($hasUsableToken && !$prevHas) {
            $byIdUrl[$nid] = $u;
        }
    }
    $out = [];
    foreach ($byIdOrder as $nid) {
        $out[] = $byIdUrl[$nid];
    }
    return $out;
}

/** explore 链接缺有效 xsec_token 时 SSR 往往无图（常见于把文档里的「...」照抄进命令行） */
function isSuspiciousExploreUrl(string $url): bool
{
    $qs = parse_url($url, PHP_URL_QUERY);
    if (!is_string($qs) || $qs === '') {
        return true;
    }
    parse_str($qs, $q);
    $tok = (string)($q['xsec_token'] ?? '');
    if ($tok === '' || str_contains($tok, '...')) {
        return true;
    }
    // 正常 token 通常远长于占位符
    return strlen($tok) < 40;
}

/** @param mixed $state */
function extractNoteTitleFromState($state): string
{
    if (!is_array($state)) {
        return '';
    }
    $map = $state['note']['noteDetailMap'] ?? null;
    if (is_array($map)) {
        foreach ($map as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $n = $entry['note'] ?? null;
            if (!is_array($n)) {
                continue;
            }
            $t = (string)($n['title'] ?? $n['displayTitle'] ?? '');
            if ($t !== '') {
                return sanitizeFilename($t);
            }
        }
    }
    // 兼容少量变体结构
    $first = $state['note']['firstNote'] ?? null;
    if (is_array($first)) {
        $t = (string)($first['title'] ?? $first['displayTitle'] ?? '');
        if ($t !== '') {
            return sanitizeFilename($t);
        }
    }
    return '';
}

/**
 * 从笔记详情页 HTML 收集可下载图片行（SSR state + 正文正则兜底）。
 *
 * @return list<array{title:string, note_index:int, url:string}>
 */
function collectRowsFromNoteDetailHtml(
    string $html,
    bool $includeAvatar,
    int $noteIndex,
    string $title,
    ?array $preparsedState,
    string $refererUrl
): array {
    $collected = [];
    $seen = [];
    $add = function (string $u) use (&$collected, &$seen, $includeAvatar, $noteIndex, $title, $refererUrl): void {
        $u = html_entity_decode($u, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $u = cleanXhsCdnUrl($u);
        $u = normalizeMaybeSchemeRelativeUrl($u);
        if ($u === '' || !str_starts_with($u, 'http')) {
            return;
        }
        if (!isLikelyXhsImageUrl($u)) {
            return;
        }
        if (!$includeAvatar && str_contains($u, 'sns-avatar')) {
            return;
        }
        if (isset($seen[$u])) {
            return;
        }
        $seen[$u] = true;
        $collected[] = [
            'title' => $title,
            'note_index' => $noteIndex,
            'url' => $u,
            'referer' => $refererUrl,
        ];
    };

    $state = $preparsedState ?? extractInitialState($html);
    if (is_array($state)) {
        $buf = [];
        collectUrlsFromValue($state, $buf);
        foreach ($buf as $u) {
            if (is_string($u)) {
                $add($u);
            }
        }
    }
    foreach (scrapeImageUrlsFromHtmlString($html) as $u) {
        $add($u);
    }
    return $collected;
}

/** @return list<string> */
function scrapeImageUrlsFromHtmlString(string $html): array
{
    $out = [];
    $patterns = [
        '#https?://sns-webpic[^"\'\\s<>]+#i',
        '#//sns-webpic[^"\'\\s<>]+#i',
        // xhscdn 域名上的笔记图（含 webp、不同业务子域）
        '#https?://[a-z0-9.-]*xhscdn\\.com[^"\'\\s<>]*?1040g[0-9a-z]*[^"\'\\s<>]*#i',
        '#//[a-z0-9.-]*xhscdn\\.com[^"\'\\s<>]*?1040g[0-9a-z]*[^"\'\\s<>]*#i',
    ];
    foreach ($patterns as $re) {
        if (preg_match_all($re, $html, $m)) {
            foreach ($m[0] as $u) {
                if (is_string($u) && str_contains($u, '1040g')) {
                    $out[] = normalizeMaybeSchemeRelativeUrl($u);
                }
            }
        }
    }
    return $out;
}

function extractInitialState(string $html): ?array
{
    $needle = 'window.__INITIAL_STATE__=';
    $pos = strpos($html, $needle);
    if ($pos === false) {
        return null;
    }
    $brace = strpos($html, '{', $pos);
    if ($brace === false) {
        return null;
    }
    $json = extractBalancedJsonObject($html, $brace);
    if ($json === null) {
        return null;
    }
    $json = sanitizeJsObjectLiteralAsJson($json);
    $data = json_decode($json, true);
    if (!is_array($data)) {
        return null;
    }
    return $data;
}

/** 将内嵌脚本里的类 JSON（含 undefined 等）尽量转成可 json_decode 的字符串 */
function sanitizeJsObjectLiteralAsJson(string $json): string
{
    $json = preg_replace('/:\s*undefined\b/', ':null', $json) ?? $json;
    $json = preg_replace('/:\s*NaN\b/', ':0', $json) ?? $json;
    return $json;
}

/** 从首个 { 起截取配平的 JSON 对象（避免正则回溯过大导致失败） */
function extractBalancedJsonObject(string $s, int $start): ?string
{
    $len = strlen($s);
    if ($start >= $len || $s[$start] !== '{') {
        return null;
    }
    $depth = 0;
    $inString = false;
    $escape = false;
    for ($i = $start; $i < $len; $i++) {
        $c = $s[$i];
        if ($inString) {
            if ($escape) {
                $escape = false;
                continue;
            }
            if ($c === '\\') {
                $escape = true;
                continue;
            }
            if ($c === '"') {
                $inString = false;
            }
            continue;
        }
        if ($c === '"') {
            $inString = true;
            continue;
        }
        if ($c === '{') {
            $depth++;
        } elseif ($c === '}') {
            $depth--;
            if ($depth === 0) {
                return substr($s, $start, $i - $start + 1);
            }
        }
    }
    return null;
}

/** @param mixed $v */
function collectUrlsFromValue($v, array &$out): void
{
    if (is_string($v)) {
        // 兼容协议相对 URL：//sns-webpic...、//xhscdn...（补 https:）
        if (str_starts_with($v, '//')) {
            $v = normalizeMaybeSchemeRelativeUrl($v);
        }
        if (str_starts_with($v, 'http') && (
            str_contains($v, '1040g') ||
            str_contains($v, 'sns-webpic') ||
            str_contains($v, 'sns-avatar') ||
            str_contains($v, 'xhscdn.com')
        )) {
            $out[] = $v;
        }
        return;
    }
    if (!is_array($v)) {
        return;
    }
    foreach ($v as $child) {
        collectUrlsFromValue($child, $out);
    }
}

function cleanXhsCdnUrl(string $url): string
{
    // 有些 HTML/JS 把 `url(...)` 后紧跟的 CSS 片段拼进来了，例如:
    // http://.../1040g...!nd_prv...);background-repeat:no-repeat...
    // 这里截断到 ')' / ';' / ',' 之前，恢复为纯 CDN URL。
    $u = trim($url);
    foreach ([']', ')', ';', ','] as $ch) {
        // ']' 是为了防御某些场景的轻微变体
        $pos = strpos($u, $ch);
        if ($pos !== false) {
            // ')'/' ;'/' ,' 是最常见分隔符；']' 只是保守处理
            if ($pos > 0) {
                $u = substr($u, 0, $pos);
            }
            break;
        }
    }
    return trim($u);
}

function normalizeMaybeSchemeRelativeUrl(string $url): string
{
    $u = trim($url);
    if ($u === '') {
        return $u;
    }
    // 处理 scheme-relative：//sns-webpic... -> https://sns-webpic...
    if (str_starts_with($u, '//')) {
        return 'https:' . $u;
    }
    return $u;
}

function sanitizeFilename(string $s): string
{
    $s = preg_replace('/[^\p{L}\p{N}\-_]+/u', '_', $s) ?? 'note';
    $s = trim($s, '_');
    return $s !== '' ? mb_substr($s, 0, 80) : 'note';
}

/**
 * 同一资源在 CDN 上常有 prv / mw 两套 URL，按路径中的 1040g… 文件 id 去重，优先 WB_DFT / mw。
 *
 * @param list<array{title:string, note_index:int, url:string}> $rows
 * @return list<array{title:string, note_index:int, url:string}>
 */
function dedupeByFileIdPreferDft(array $rows): array
{
    $best = [];
    /** @var list<string> */
    $firstSeenIds = [];
    foreach ($rows as $row) {
        $id = fileIdFromXhsUrl($row['url']);
        if ($id === null) {
            $id = 'full_' . sha1($row['url']);
        }
        if (!isset($best[$id])) {
            $firstSeenIds[] = $id;
        }
        $score = urlQualityScore($row['url']);
        if (!isset($best[$id]) || $score > $best[$id]['score']) {
            $best[$id] = ['score' => $score, 'row' => $row];
        }
    }
    $out = [];
    foreach ($firstSeenIds as $id) {
        $out[] = $best[$id]['row'];
    }
    return $out;
}

function fileIdFromXhsUrl(string $url): ?string
{
    if (preg_match('#/(?:notes_pre_post/)?(1040g[0-9a-z]+)#i', $url, $m)) {
        return $m[1];
    }
    return null;
}

function urlQualityScore(string $url): int
{
    $s = 0;
    if (str_contains($url, 'WB_DFT') || str_contains($url, 'nc_n_nwebp_mw_')) {
        $s += 10;
    }
    if (str_contains($url, 'prv') || str_contains($url, 'WB_PRV')) {
        $s -= 3;
    }
    if (str_contains($url, 'webp')) {
        $s += 1;
    }
    return $s;
}

function guessExt(string $url): string
{
    $path = parse_url($url, PHP_URL_PATH) ?? '';
    if (preg_match('/\.(jpe?g|png|gif|webp)$/i', $path, $m)) {
        return '.' . strtolower($m[1] === 'jpeg' ? 'jpg' : $m[1]);
    }
    return '.webp';
}

function isLikelyXhsImageUrl(string $url): bool
{
    if (!str_contains($url, '1040g')) {
        return false;
    }
    $path = parse_url($url, PHP_URL_PATH);
    return is_string($path) && $path !== '' && $path !== '/';
}
