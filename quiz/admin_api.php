<?php
/**
 * admin_api.php — 管理员 API v2
 *
 * ── 用户 ──
 * GET  ?action=list_users[&archived=1]     → 所有用户列表
 * GET  ?action=user_sets&uid=              → 单用户各题库详情
 * GET  ?action=download_user&uid=          → 下载该用户全部数据（JSON）
 * POST ?action=import_user_set             → 上传/导入某套进度 JSON
 * POST ?action=delete_user&uid=            → 删除用户全部数据
 * POST ?action=archive_user&uid=           → 存档用户
 * POST ?action=unarchive_user&uid=         → 取消存档
 *
 * ── 题库 ──
 * GET  ?action=list_sets                   → 读取 sets_c3_2025.json
 * GET  ?action=download_set&name=          → 下载题库 JSON 文件
 * POST ?action=update_set_meta             → 修改标题/描述（body JSON）
 * POST ?action=copy_set                    → 复制题库（body JSON）
 * POST ?action=rename_set                  → 重命名 name/file（body JSON）
 * POST ?action=upload_set&name=&label=     → 上传新题库（body = 题目数组 JSON）
 * POST ?action=delete_set&name=            → 删除题库文件及注册表项
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

define('DATA_DIR',   __DIR__ . '/data/');
define('SETS_FILE',  __DIR__ . '/sets_c3_2025.json');
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

function sanitize_id(string $s): string {
    return substr(preg_replace('/[^a-zA-Z0-9_-]/', '', $s), 0, 32);
}
function sanitize_name(string $s): string {
    return substr(preg_replace('/[^a-zA-Z0-9_-]/', '', $s), 0, 64);
}
function atomic_write(string $path, string $content): void {
    $tmp = $path . '.tmp.' . getmypid();
    file_put_contents($tmp, $content);
    rename($tmp, $path);
}
function read_sets(): array {
    if (!file_exists(SETS_FILE)) return [];
    return json_decode(file_get_contents(SETS_FILE), true) ?: [];
}
function write_sets(array $sets): void {
    atomic_write(SETS_FILE, json_encode(array_values($sets),
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

// ════════════════ USER actions ════════════════════════════════════

if ($action === 'list_users') {
    if (!is_dir(DATA_DIR)) { echo json_encode(['users'=>[]]); exit; }
    $showArchived = !empty($_GET['archived']);
    $files = glob(DATA_DIR . 'progress_*_*.json') ?: [];
    $users = [];
    foreach ($files as $f) {
        $base = basename($f, '.json');
        $rest = substr($base, strlen('progress_'));
        $parts = explode('_', $rest, 2);
        if (count($parts) < 2) continue;
        $uid = $parts[0];
        if (!preg_match('/^[a-zA-Z0-9_-]{1,32}$/', $uid)) continue;

        $d = json_decode(file_get_contents($f), true);
        if (!$d) continue;
        $archived = !empty($d['_archived']);
        if ($archived !== $showArchived) continue;

        if (!isset($users[$uid])) {
            $users[$uid] = ['uid'=>$uid,'sets'=>0,'answered'=>0,'wrong'=>0,
                            'correct'=>0,'lastSync'=>'','archived'=>$archived,'setNames'=>[]];
        }
        $u = &$users[$uid];
        $u['sets']++;
        $u['answered'] += count($d['state']['answeredQs'] ?? []);
        $u['wrong']    += count($d['state']['wrongQs']    ?? []);
        $u['correct']  += $d['state']['correctCount']     ?? 0;
        $sl = $d['setLabel'] ?? $d['setName'] ?? '';
        if ($sl) $u['setNames'][] = $sl;
        $ts = $d['_syncedAt'] ?? ($d['exportedAt'] ?? '');
        if ($ts > $u['lastSync']) $u['lastSync'] = $ts;
        unset($u);
    }
    $list = array_values($users);
    usort($list, fn($a,$b) => strcmp($b['lastSync'], $a['lastSync']));
    echo json_encode(['users'=>$list, 'archived'=>$showArchived]);
    exit;
}

if ($action === 'user_sets') {
    $uid = sanitize_id($_GET['uid'] ?? '');
    if (!$uid) { http_response_code(400); echo json_encode(['error'=>'uid required']); exit; }
    $files = glob(DATA_DIR . "progress_{$uid}_*.json") ?: [];
    $sets = [];
    foreach ($files as $f) {
        $d = json_decode(file_get_contents($f), true);
        if (!$d) continue;
        $sets[] = [
            'setName'   => $d['setName']   ?? '',
            'setLabel'  => $d['setLabel']  ?? '',
            'syncedAt'  => $d['_syncedAt'] ?? ($d['exportedAt'] ?? ''),
            'answered'  => count($d['state']['answeredQs'] ?? []),
            'wrong'     => count($d['state']['wrongQs']    ?? []),
            'correct'   => $d['state']['correctCount']     ?? 0,
            'bookmarks' => count($d['state']['bookmarks']  ?? []),
            'total'     => $d['totalCount'] ?? 0,
            'archived'  => !empty($d['_archived']),
        ];
    }
    usort($sets, fn($a,$b) => strcmp($b['syncedAt'], $a['syncedAt']));
    echo json_encode(['uid'=>$uid, 'sets'=>$sets]);
    exit;
}

if ($action === 'download_user') {
    $uid = sanitize_id($_GET['uid'] ?? '');
    if (!$uid) { http_response_code(400); echo json_encode(['error'=>'uid required']); exit; }
    $files = glob(DATA_DIR . "progress_{$uid}_*.json") ?: [];
    $export = ['uid'=>$uid, 'exportedAt'=>date('c'), 'sets'=>[]];
    foreach ($files as $f) {
        $d = json_decode(file_get_contents($f), true);
        if ($d) $export['sets'][] = $d;
    }
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="user_' . $uid . '_' . date('Ymd') . '.json"');
    echo json_encode($export, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

if ($action === 'import_user_set' && $method === 'POST') {
    $body = file_get_contents('php://input');
    if (strlen($body) > 4 * 1024 * 1024) { http_response_code(413); echo json_encode(['error'=>'too large']); exit; }
    $data = json_decode($body, true);
    if (!$data) { http_response_code(400); echo json_encode(['error'=>'invalid json']); exit; }
    // Support both single-set and multi-set (download_user format)
    if (isset($data['sets']) && is_array($data['sets'])) {
        // bulk import
        $count = 0;
        foreach ($data['sets'] as $set) {
            $uid = sanitize_id($set['uid'] ?? $data['uid'] ?? '');
            $sn  = sanitize_id($set['setName'] ?? '');
            if (!$uid || !$sn) continue;
            $set['uid'] = $uid; $set['_syncedAt'] = date('c');
            atomic_write(DATA_DIR . "progress_{$uid}_{$sn}.json", json_encode($set, JSON_UNESCAPED_UNICODE));
            $count++;
        }
        echo json_encode(['ok'=>true, 'imported'=>$count]);
    } else {
        $uid = sanitize_id($data['uid'] ?? '');
        $sn  = sanitize_id($data['setName'] ?? $data['set'] ?? '');
        if (!$uid) { http_response_code(400); echo json_encode(['error'=>'uid required']); exit; }
        $data['uid'] = $uid; $data['_syncedAt'] = date('c');
        $file = DATA_DIR . "progress_{$uid}" . ($sn ? "_{$sn}" : '') . ".json";
        atomic_write($file, json_encode($data, JSON_UNESCAPED_UNICODE));
        echo json_encode(['ok'=>true, 'uid'=>$uid, 'set'=>$sn]);
    }
    exit;
}

if ($action === 'delete_user' && $method === 'POST') {
    $uid = sanitize_id($_GET['uid'] ?? '');
    if (!$uid) { http_response_code(400); echo json_encode(['error'=>'uid required']); exit; }
    $files = glob(DATA_DIR . "progress_{$uid}_*.json") ?: [];
    $deleted = 0;
    foreach ($files as $f) { if (unlink($f)) $deleted++; }
    echo json_encode(['ok'=>true, 'uid'=>$uid, 'deleted'=>$deleted]);
    exit;
}

if ($action === 'archive_user' && $method === 'POST') {
    $uid = sanitize_id($_GET['uid'] ?? '');
    if (!$uid) { http_response_code(400); echo json_encode(['error'=>'uid required']); exit; }
    foreach (glob(DATA_DIR . "progress_{$uid}_*.json") ?: [] as $f) {
        $d = json_decode(file_get_contents($f), true);
        if ($d) { $d['_archived'] = true; atomic_write($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }
    }
    echo json_encode(['ok'=>true, 'uid'=>$uid]);
    exit;
}

if ($action === 'unarchive_user' && $method === 'POST') {
    $uid = sanitize_id($_GET['uid'] ?? '');
    if (!$uid) { http_response_code(400); echo json_encode(['error'=>'uid required']); exit; }
    foreach (glob(DATA_DIR . "progress_{$uid}_*.json") ?: [] as $f) {
        $d = json_decode(file_get_contents($f), true);
        if ($d) { unset($d['_archived']); atomic_write($f, json_encode($d, JSON_UNESCAPED_UNICODE)); }
    }
    echo json_encode(['ok'=>true, 'uid'=>$uid]);
    exit;
}

// ════════════════ SET actions ══════════════════════════════════════

if ($action === 'list_sets') {
    if (!file_exists(SETS_FILE)) { echo json_encode([]); exit; }
    echo file_get_contents(SETS_FILE);
    exit;
}

if ($action === 'download_set') {
    $name = sanitize_name($_GET['name'] ?? '');
    if (!$name) { http_response_code(400); echo json_encode(['error'=>'name required']); exit; }
    $sets = read_sets();
    $entry = null;
    foreach ($sets as $s) { if ($s['name'] === $name) { $entry = $s; break; } }
    if (!$entry) { http_response_code(404); echo json_encode(['error'=>'set not found']); exit; }
    $path = __DIR__ . '/' . ltrim($entry['file'], '/');
    if (!file_exists($path)) { http_response_code(404); echo json_encode(['error'=>'file not found']); exit; }
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    readfile($path);
    exit;
}

if ($action === 'update_set_meta' && $method === 'POST') {
    $body = json_decode(file_get_contents('php://input'), true);
    $name = sanitize_name($body['name'] ?? '');
    if (!$name) { http_response_code(400); echo json_encode(['error'=>'name required']); exit; }
    $sets = read_sets(); $found = false;
    foreach ($sets as &$s) {
        if ($s['name'] === $name) {
            if (isset($body['label'])) $s['label'] = trim($body['label']);
            if (isset($body['desc']))  $s['desc']  = trim($body['desc']);
            $found = true; break;
        }
    }
    unset($s);
    if (!$found) { http_response_code(404); echo json_encode(['error'=>'not found']); exit; }
    write_sets($sets);
    echo json_encode(['ok'=>true]);
    exit;
}

if ($action === 'copy_set' && $method === 'POST') {
    $body    = json_decode(file_get_contents('php://input'), true);
    $srcName = sanitize_name($body['src']      ?? '');
    $newName = sanitize_name($body['newName']  ?? '');
    $newLabel= trim($body['newLabel'] ?? '');
    if (!$srcName || !$newName) { http_response_code(400); echo json_encode(['error'=>'src and newName required']); exit; }

    $sets = read_sets(); $srcEntry = null;
    foreach ($sets as $s) {
        if ($s['name'] === $newName) { http_response_code(409); echo json_encode(['error'=>'name taken']); exit; }
        if ($s['name'] === $srcName) $srcEntry = $s;
    }
    if (!$srcEntry) { http_response_code(404); echo json_encode(['error'=>'src not found']); exit; }

    $newFile = 'data/' . $newName . '.json';
    $srcPath = __DIR__ . '/' . ltrim($srcEntry['file'], '/');
    $newPath = __DIR__ . '/' . $newFile;
    if (file_exists($srcPath)) copy($srcPath, $newPath);

    $newEntry = array_merge($srcEntry, [
        'name'  => $newName,
        'file'  => $newFile,
        'label' => $newLabel ?: ($srcEntry['label'] . ' (副本)'),
    ]);
    $sets[] = $newEntry;
    write_sets($sets);
    echo json_encode(['ok'=>true, 'name'=>$newName]);
    exit;
}

if ($action === 'rename_set' && $method === 'POST') {
    $body    = json_decode(file_get_contents('php://input'), true);
    $oldName = sanitize_name($body['oldName'] ?? '');
    $newName = sanitize_name($body['newName'] ?? '');
    if (!$oldName || !$newName || $oldName === $newName) {
        http_response_code(400); echo json_encode(['error'=>'oldName and newName required']); exit;
    }
    $sets = read_sets(); $found = false;
    foreach ($sets as &$s) {
        if ($s['name'] === $newName && $s['name'] !== $oldName) {
            http_response_code(409); echo json_encode(['error'=>'name taken']); exit;
        }
    }
    foreach ($sets as &$s) {
        if ($s['name'] === $oldName) {
            $oldPath = __DIR__ . '/' . ltrim($s['file'], '/');
            $newFile = 'data/' . $newName . '.json';
            $newPath = __DIR__ . '/' . $newFile;
            if (file_exists($oldPath)) rename($oldPath, $newPath);
            $s['name'] = $newName;
            $s['file'] = $newFile;
            $found = true; break;
        }
    }
    unset($s);
    if (!$found) { http_response_code(404); echo json_encode(['error'=>'not found']); exit; }
    write_sets($sets);
    echo json_encode(['ok'=>true, 'newName'=>$newName]);
    exit;
}

if ($action === 'upload_set' && $method === 'POST') {
    $name  = sanitize_name($_GET['name']  ?? '');
    $label = trim($_GET['label'] ?? '');
    $desc  = trim($_GET['desc']  ?? '');
    if (!$name) { http_response_code(400); echo json_encode(['error'=>'name required']); exit; }
    $body = file_get_contents('php://input');
    if (strlen($body) > 20 * 1024 * 1024) { http_response_code(413); echo json_encode(['error'=>'too large (max 20MB)']); exit; }
    $questions = json_decode($body, true);
    if (!is_array($questions)) { http_response_code(400); echo json_encode(['error'=>'body must be JSON array']); exit; }

    $file = 'data/' . $name . '.json';
    atomic_write(__DIR__ . '/' . $file, json_encode($questions, JSON_UNESCAPED_UNICODE));

    $sets = read_sets(); $found = false;
    foreach ($sets as &$s) {
        if ($s['name'] === $name) {
            if ($label) $s['label'] = $label;
            if ($desc)  $s['desc']  = $desc;
            $s['count'] = count($questions);
            $found = true; break;
        }
    }
    unset($s);
    if (!$found) {
        $sets[] = ['file'=>$file,'name'=>$name,'label'=>$label?:$name,
                   'count'=>count($questions),'desc'=>$desc,'has_analysis'=>false];
    }
    write_sets($sets);
    echo json_encode(['ok'=>true, 'name'=>$name, 'count'=>count($questions)]);
    exit;
}

if ($action === 'delete_set' && $method === 'POST') {
    $name = sanitize_name($_GET['name'] ?? '');
    if (!$name) { http_response_code(400); echo json_encode(['error'=>'name required']); exit; }
    $sets = read_sets(); $entry = null;
    $sets = array_filter($sets, function($s) use ($name, &$entry) {
        if ($s['name'] === $name) { $entry = $s; return false; }
        return true;
    });
    write_sets(array_values($sets));
    $deleted = false;
    if ($entry && !empty($entry['file'])) {
        $path = __DIR__ . '/' . ltrim($entry['file'], '/');
        if (file_exists($path)) { unlink($path); $deleted = true; }
    }
    echo json_encode(['ok'=>true, 'name'=>$name, 'file_deleted'=>$deleted]);
    exit;
}

http_response_code(400);
echo json_encode(['error'=>'unknown action']);
