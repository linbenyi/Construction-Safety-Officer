<?php
/**
 * admin_api.php — 管理员 API
 *
 * GET  ?action=list_users               → 列出所有用户 UID 及摘要
 * GET  ?action=user_sets&uid=<id>       → 该用户所有题库进度详情
 * POST ?action=delete_user&uid=<id>     → 删除该用户所有数据
 * GET  ?action=list_sets                → 读取 sets_c3_2025.json 题库列表
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

define('DATA_DIR', __DIR__ . '/data/');
define('SETS_FILE', __DIR__ . '/sets_c3_2025.json');

function sanitize_id(string $s): string {
    return substr(preg_replace('/[^a-zA-Z0-9_-]/', '', $s), 0, 32);
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ── list_sets ────────────────────────────────────────────────────
if ($action === 'list_sets') {
    if (!file_exists(SETS_FILE)) {
        echo json_encode(['error' => 'sets file not found']);
        exit;
    }
    echo file_get_contents(SETS_FILE);
    exit;
}

// ── list_users ───────────────────────────────────────────────────
if ($action === 'list_users') {
    if (!is_dir(DATA_DIR)) {
        echo json_encode(['users' => []]);
        exit;
    }
    $files = glob(DATA_DIR . 'progress_*_*.json');
    $users = [];
    foreach ($files as $f) {
        // filename: progress_{uid}_{set}.json
        $base = basename($f, '.json');
        // strip leading "progress_"
        $rest = substr($base, strlen('progress_'));
        // last segment after final _ is the set name (may contain underscores)
        // uid is everything before the first _ that produces a valid uid
        // Strategy: uid is 8-hex chars, always first segment
        $parts = explode('_', $rest, 2);
        if (count($parts) < 2) continue;
        $uid = $parts[0];
        if (!preg_match('/^[a-zA-Z0-9_-]{1,32}$/', $uid)) continue;

        $d = json_decode(file_get_contents($f), true);
        if (!$d) continue;

        $answered  = count($d['state']['answeredQs'] ?? []);
        $wrong     = count($d['state']['wrongQs']    ?? []);
        $correct   = $d['state']['correctCount']     ?? 0;
        $syncedAt  = $d['_syncedAt'] ?? ($d['exportedAt'] ?? '');
        $setLabel  = $d['setLabel']  ?? '';
        $setName   = $d['setName']   ?? $parts[1] ?? '';

        if (!isset($users[$uid])) {
            $users[$uid] = [
                'uid'       => $uid,
                'sets'      => 0,
                'answered'  => 0,
                'wrong'     => 0,
                'correct'   => 0,
                'lastSync'  => '',
                'setNames'  => [],
            ];
        }
        $users[$uid]['sets']++;
        $users[$uid]['answered']  += $answered;
        $users[$uid]['wrong']     += $wrong;
        $users[$uid]['correct']   += $correct;
        $users[$uid]['setNames'][] = $setLabel ?: $setName;
        if ($syncedAt > $users[$uid]['lastSync']) {
            $users[$uid]['lastSync'] = $syncedAt;
        }
    }

    // Sort by lastSync desc
    $list = array_values($users);
    usort($list, fn($a,$b) => strcmp($b['lastSync'], $a['lastSync']));
    echo json_encode(['users' => $list]);
    exit;
}

// ── user_sets ────────────────────────────────────────────────────
if ($action === 'user_sets') {
    $uid = sanitize_id($_GET['uid'] ?? '');
    if (!$uid) { http_response_code(400); echo json_encode(['error'=>'uid required']); exit; }

    $files = glob(DATA_DIR . "progress_{$uid}_*.json");
    $sets = [];
    foreach ($files as $f) {
        $d = json_decode(file_get_contents($f), true);
        if (!$d) continue;
        $answered = count($d['state']['answeredQs'] ?? []);
        $wrong    = count($d['state']['wrongQs']    ?? []);
        $correct  = $d['state']['correctCount']     ?? 0;
        $bm       = count($d['state']['bookmarks']  ?? []);
        $total    = $d['totalCount'] ?? 0;
        $sets[] = [
            'setName'   => $d['setName']   ?? '',
            'setLabel'  => $d['setLabel']  ?? '',
            'syncedAt'  => $d['_syncedAt'] ?? ($d['exportedAt'] ?? ''),
            'answered'  => $answered,
            'wrong'     => $wrong,
            'correct'   => $correct,
            'bookmarks' => $bm,
            'total'     => $total,
            'pct'       => $answered > 0 ? round($correct / $answered * 100) : 0,
        ];
    }
    usort($sets, fn($a,$b) => strcmp($b['syncedAt'], $a['syncedAt']));
    echo json_encode(['uid' => $uid, 'sets' => $sets]);
    exit;
}

// ── delete_user ──────────────────────────────────────────────────
if ($action === 'delete_user' && $method === 'POST') {
    $uid = sanitize_id($_GET['uid'] ?? '');
    if (!$uid) { http_response_code(400); echo json_encode(['error'=>'uid required']); exit; }

    $files   = glob(DATA_DIR . "progress_{$uid}_*.json");
    $deleted = 0;
    foreach ($files as $f) {
        if (unlink($f)) $deleted++;
    }
    echo json_encode(['ok' => true, 'uid' => $uid, 'deleted' => $deleted]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'unknown action']);
