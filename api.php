<?php
session_start();
header('Content-Type: application/json; charset=utf-8');

$pdo = null;

function out($d, $c = 200) { http_response_code($c); echo json_encode($d, JSON_UNESCAPED_UNICODE); exit; }
function q($sql, $p = []) { global $pdo; $s = $pdo->prepare($sql); $s->execute($p); return $s; }
function uid() { if (empty($_SESSION['uid'])) out(['error' => 'Аввал ворид шавед'], 401); return (int)$_SESSION['uid']; }
function role($pid) {
    $r = q('SELECT role FROM project_members WHERE project_id=? AND user_id=?', [$pid, uid()])->fetch();
    if (!$r) out(['error' => 'Дастрасӣ нест'], 403);
    return $r['role'];
}
function hist($tid, $act, $det) { q('INSERT INTO task_history(task_id,user_id,action,details) VALUES(?,?,?,?)', [$tid, uid(), $act, $det]); }
function task($id) {
    $t = q('SELECT * FROM tasks WHERE id=?', [(int)$id])->fetch();
    if (!$t) out(['error' => 'Вазифа ёфт нашуд'], 404);
    return $t;
}
// Дастрасӣ ба вазифа: роҳбар ҳама, иштирокчӣ танҳо вазифаҳои худро
function taskAccess($t) {
    $r = role($t['project_id']);
    if ($r !== 'manager' && (int)$t['assignee_id'] !== uid()) out(['error' => 'Ин вазифа ба шумо тааллуқ надорад'], 403);
    return $r;
}
function uname($id) { if (!$id) return '—'; $r = q('SELECT name FROM users WHERE id=?', [$id])->fetch(); return $r ? $r['name'] : '—'; }
function checkAssignee($pid, $aid) {
    if (!$aid) return null;
    if (!q('SELECT 1 FROM project_members WHERE project_id=? AND user_id=?', [$pid, $aid])->fetch()) out(['error' => 'Иҷрокунанда узви лоиҳа нест'], 422);
    return (int)$aid;
}
function lastColumn($pid) {
    $r = q('SELECT id FROM columns WHERE project_id=? ORDER BY position DESC, id DESC LIMIT 1', [$pid])->fetch();
    return $r ? (int)$r['id'] : 0;
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=kanban;charset=utf8mb4', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    out(['error' => 'Нет подключения к БД. Запущен ли MySQL и импортирован ли schema.sql? ' . $e->getMessage()], 500);
}

$in = json_decode(file_get_contents('php://input'), true) ?: [];
$a = $_GET['action'] ?? '';

try {
switch ($a) {

case 'register':
    $name = trim($in['name'] ?? ''); $email = trim($in['email'] ?? ''); $pw = $in['password'] ?? '';
    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($pw) < 6) out(['error' => 'Маълумот нодуруст аст (парол ≥ 6 аломат)'], 422);
    if (q('SELECT 1 FROM users WHERE email=?', [$email])->fetch()) out(['error' => 'Ин email аллакай сабт шудааст'], 422);
    q('INSERT INTO users(name,email,password_hash) VALUES(?,?,?)', [$name, $email, password_hash($pw, PASSWORD_DEFAULT)]);
    $_SESSION['uid'] = (int)$pdo->lastInsertId();
    out(['ok' => true]);

case 'login':
    $u = q('SELECT * FROM users WHERE email=?', [trim($in['email'] ?? '')])->fetch();
    if (!$u || !password_verify($in['password'] ?? '', $u['password_hash'])) out(['error' => 'Email ё парол нодуруст аст'], 401);
    $_SESSION['uid'] = (int)$u['id'];
    out(['ok' => true]);

case 'logout':
    session_destroy(); out(['ok' => true]);

case 'me':
    if (empty($_SESSION['uid'])) out(['me' => null]);
    out(['me' => q('SELECT id,name,email FROM users WHERE id=?', [uid()])->fetch()]);

case 'projects':
    out(['projects' => q('SELECT p.id,p.name,p.description,pm.role FROM projects p JOIN project_members pm ON pm.project_id=p.id WHERE pm.user_id=? ORDER BY p.id DESC', [uid()])->fetchAll()]);

case 'project_create':
    $name = trim($in['name'] ?? '');
    if ($name === '') out(['error' => 'Номи лоиҳаро ворид кунед'], 422);
    q('INSERT INTO projects(name,description,owner_id) VALUES(?,?,?)', [$name, trim($in['description'] ?? ''), uid()]);
    $pid = (int)$pdo->lastInsertId();
    q("INSERT INTO project_members(project_id,user_id,role) VALUES(?,?,'manager')", [$pid, uid()]);
    foreach (['Барои иҷро', 'Дар иҷро', 'Иҷрошуда'] as $i => $t) q('INSERT INTO columns(project_id,title,position) VALUES(?,?,?)', [$pid, $t, $i]);
    out(['id' => $pid]);

case 'board':
    $pid = (int)($in['project_id'] ?? 0);
    $r = role($pid);
    $project = q('SELECT id,name,description FROM projects WHERE id=?', [$pid])->fetch();
    $members = q('SELECT u.id,u.name,u.email,pm.role FROM project_members pm JOIN users u ON u.id=pm.user_id WHERE pm.project_id=? ORDER BY u.name', [$pid])->fetchAll();
    $cols = q('SELECT id,title,position FROM columns WHERE project_id=? ORDER BY position,id', [$pid])->fetchAll();
    $where = 't.project_id=?'; $p = [$pid];
    if ($r !== 'manager') { $where .= ' AND t.assignee_id=?'; $p[] = uid(); }
    elseif (!empty($in['assignee'])) { $where .= ' AND t.assignee_id=?'; $p[] = (int)$in['assignee']; }
    if (!empty($in['from'])) { $where .= ' AND t.due_date>=?'; $p[] = $in['from']; }
    if (!empty($in['to']))   { $where .= ' AND t.due_date<=?'; $p[] = $in['to']; }
    $sel = 'SELECT t.*, u.name AS assignee_name FROM tasks t LEFT JOIN users u ON u.id=t.assignee_id';
    $tasks = q("$sel WHERE $where ORDER BY t.position,t.id", $p)->fetchAll();
    // Рӯйхати вазифаҳои гузашта аз мӯҳлат (сутуни охирин = «иҷрошуда»)
    $where2 = 't.project_id=? AND t.due_date<CURDATE() AND t.column_id<>?'; $p2 = [$pid, lastColumn($pid)];
    if ($r !== 'manager') { $where2 .= ' AND t.assignee_id=?'; $p2[] = uid(); }
    $overdue = q("$sel WHERE $where2 ORDER BY t.due_date", $p2)->fetchAll();
    out(['project' => $project, 'role' => $r, 'members' => $members, 'columns' => $cols, 'tasks' => $tasks, 'overdue' => $overdue, 'last_column' => lastColumn($pid)]);

case 'member_add':
    $pid = (int)$in['project_id'];
    if (role($pid) !== 'manager') out(['error' => 'Танҳо роҳбар'], 403);
    $u = q('SELECT id FROM users WHERE email=?', [trim($in['email'] ?? '')])->fetch();
    if (!$u) out(['error' => 'Корбар бо ин email ёфт нашуд (аввал бояд сабти ном шавад)'], 404);
    q("INSERT IGNORE INTO project_members(project_id,user_id,role) VALUES(?,?,'member')", [$pid, $u['id']]);
    out(['ok' => true]);

case 'column_create':
    $pid = (int)$in['project_id'];
    if (role($pid) !== 'manager') out(['error' => 'Танҳо роҳбар'], 403);
    $title = trim($in['title'] ?? '');
    if ($title === '') out(['error' => 'Номро ворид кунед'], 422);
    $pos = (int)q('SELECT COALESCE(MAX(position),-1)+1 AS n FROM columns WHERE project_id=?', [$pid])->fetch()['n'];
    q('INSERT INTO columns(project_id,title,position) VALUES(?,?,?)', [$pid, $title, $pos]);
    out(['ok' => true]);

case 'task_create':
    $pid = (int)$in['project_id'];
    if (role($pid) !== 'manager') out(['error' => 'Танҳо роҳбар вазифа месозад'], 403);
    $title = trim($in['title'] ?? '');
    if ($title === '') out(['error' => 'Номи вазифаро ворид кунед'], 422);
    $col = (int)$in['column_id'];
    if (!q('SELECT 1 FROM columns WHERE id=? AND project_id=?', [$col, $pid])->fetch()) out(['error' => 'Сутун нодуруст'], 422);
    $as = checkAssignee($pid, $in['assignee_id'] ?? null);
    $pr = in_array($in['priority'] ?? '', ['low', 'medium', 'high']) ? $in['priority'] : 'medium';
    $due = ($in['due_date'] ?? '') ?: null;
    $pos = (int)q('SELECT COALESCE(MAX(position),-1)+1 AS n FROM tasks WHERE column_id=?', [$col])->fetch()['n'];
    q('INSERT INTO tasks(project_id,column_id,title,description,assignee_id,due_date,priority,position,created_by) VALUES(?,?,?,?,?,?,?,?,?)',
      [$pid, $col, $title, trim($in['description'] ?? ''), $as, $due, $pr, $pos, uid()]);
    hist((int)$pdo->lastInsertId(), 'created', 'Вазифа сохта шуд');
    out(['ok' => true]);

case 'task_update':
    $t = task($in['id']);
    if (role($t['project_id']) !== 'manager') out(['error' => 'Танҳо роҳбар вазифаро таҳрир мекунад'], 403);
    $new = [
        'title' => trim($in['title'] ?? ''),
        'description' => trim($in['description'] ?? ''),
        'assignee_id' => checkAssignee($t['project_id'], $in['assignee_id'] ?? null),
        'due_date' => ($in['due_date'] ?? '') ?: null,
        'priority' => in_array($in['priority'] ?? '', ['low', 'medium', 'high']) ? $in['priority'] : $t['priority'],
    ];
    if ($new['title'] === '') out(['error' => 'Ном холӣ буда наметавонад'], 422);
    $labels = ['title' => 'ном', 'description' => 'тавсиф', 'assignee_id' => 'иҷрокунанда', 'due_date' => 'мӯҳлат', 'priority' => 'афзалият'];
    foreach ($new as $k => $v) {
        if ((string)$v !== (string)$t[$k]) {
            $old = $t[$k]; $nv = $v;
            if ($k === 'assignee_id') { $old = uname($old); $nv = uname($v); }
            hist($t['id'], 'update', $labels[$k] . ': «' . ($old ?: '—') . '» → «' . ($nv ?: '—') . '»');
        }
    }
    q('UPDATE tasks SET title=?,description=?,assignee_id=?,due_date=?,priority=? WHERE id=?',
      [$new['title'], $new['description'], $new['assignee_id'], $new['due_date'], $new['priority'], $t['id']]);
    out(['ok' => true]);

case 'task_move':
    $t = task($in['task_id']);
    $r = role($t['project_id']);
    if ($r !== 'manager' && (int)$t['assignee_id'] !== uid()) out(['error' => 'Шумо танҳо вазифаҳои худро кашида метавонед'], 403);
    $col = (int)$in['column_id'];
    $c = q('SELECT title FROM columns WHERE id=? AND project_id=?', [$col, $t['project_id']])->fetch();
    if (!$c) out(['error' => 'Сутун нодуруст'], 422);
    $ids = array_map('intval', array_column(q('SELECT id FROM tasks WHERE column_id=? AND id<>? ORDER BY position,id', [$col, $t['id']])->fetchAll(), 'id'));
    $idx = max(0, min((int)($in['position'] ?? count($ids)), count($ids)));
    array_splice($ids, $idx, 0, [(int)$t['id']]);
    $pdo->beginTransaction();
    q('UPDATE tasks SET column_id=? WHERE id=?', [$col, $t['id']]);
    foreach ($ids as $i => $id) q('UPDATE tasks SET position=? WHERE id=?', [$i, $id]);
    if ((int)$t['column_id'] !== $col) {
        $from = q('SELECT title FROM columns WHERE id=?', [$t['column_id']])->fetch()['title'];
        hist($t['id'], 'move', "сутун: «$from» → «{$c['title']}»");
    }
    $pdo->commit();
    out(['ok' => true]);

case 'task_detail':
    $t = task($in['id']); taskAccess($t);
    out([
        'task' => q('SELECT t.*, u.name AS assignee_name FROM tasks t LEFT JOIN users u ON u.id=t.assignee_id WHERE t.id=?', [$t['id']])->fetch(),
        'comments' => q('SELECT c.body,c.created_at,u.name FROM task_comments c JOIN users u ON u.id=c.user_id WHERE c.task_id=? ORDER BY c.id', [$t['id']])->fetchAll(),
        'history' => q('SELECT h.action,h.details,h.created_at,u.name FROM task_history h JOIN users u ON u.id=h.user_id WHERE h.task_id=? ORDER BY h.id DESC', [$t['id']])->fetchAll(),
        'files' => q('SELECT id,original_name,stored_name,created_at FROM task_attachments WHERE task_id=? ORDER BY id', [$t['id']])->fetchAll(),
    ]);

case 'comment_add':
    $t = task($in['task_id']); taskAccess($t);
    $b = trim($in['body'] ?? '');
    if ($b === '') out(['error' => 'Шарҳ холӣ аст'], 422);
    q('INSERT INTO task_comments(task_id,user_id,body) VALUES(?,?,?)', [$t['id'], uid(), $b]);
    hist($t['id'], 'comment', 'шарҳ илова шуд');
    out(['ok' => true]);

case 'upload':
    $t = task($_POST['task_id'] ?? 0); taskAccess($t);
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) out(['error' => 'Файл бор нашуд'], 422);
    $f = $_FILES['file'];
    if ($f['size'] > 5 * 1024 * 1024) out(['error' => 'Файл аз 5 МБ зиёд аст'], 422);
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip'])) out(['error' => 'Намуди файл иҷозат нест'], 422);
    $stored = bin2hex(random_bytes(10)) . '.' . $ext;
    move_uploaded_file($f['tmp_name'], __DIR__ . '/uploads/' . $stored);
    q('INSERT INTO task_attachments(task_id,user_id,original_name,stored_name) VALUES(?,?,?,?)', [$t['id'], uid(), basename($f['name']), $stored]);
    hist($t['id'], 'file', 'замима: ' . basename($f['name']));
    out(['ok' => true]);

default:
    out(['error' => 'Амали номаълум'], 404);
}
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    out(['error' => 'Хатои сервер: ' . $e->getMessage()], 500);
}
