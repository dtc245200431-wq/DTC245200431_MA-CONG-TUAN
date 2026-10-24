<?php
declare(strict_types=1);
require __DIR__ . '/config.php';
require __DIR__ . '/auth.php';
date_default_timezone_set('Asia/Ho_Chi_Minh');

function h(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $submittedToken = (string)($_POST['csrf_token'] ?? '');
        if ($submittedToken === '' || !hash_equals(csrfToken(), $submittedToken)) {
            throw new RuntimeException('Phien lam viec het han. Hay tai lai trang va thu lai.');
        }

        $action = (string)($_POST['action'] ?? '');
        if ($action === 'login') {
            $adminUsername = getenv('ADMIN_USERNAME') ?: '';
            $adminPasswordHash = getenv('ADMIN_PASSWORD_HASH') ?: '';
            $username = (string)($_POST['username'] ?? '');
            $password = (string)($_POST['password'] ?? '');
            $attempts = $_SESSION['login_attempts'] ?? ['count' => 0, 'first' => time()];
            if (!is_array($attempts) || time() - (int)($attempts['first'] ?? 0) > 900) {
                $attempts = ['count' => 0, 'first' => time()];
            }
            if ((int)$attempts['count'] >= 5) {
                throw new RuntimeException('Dang nhap tam thoi bi khoa. Vui long thu lai sau 15 phut.');
            }
            if ($adminUsername === '' || $adminPasswordHash === '') {
                throw new RuntimeException('Chua cau hinh tai khoan quan tri. Hay xem huong dan trong README.md.');
            }
            if (!hash_equals($adminUsername, $username) || !password_verify($password, $adminPasswordHash)) {
                $attempts['count'] = (int)$attempts['count'] + 1;
                $_SESSION['login_attempts'] = $attempts;
                throw new RuntimeException('Ten dang nhap hoac mat khau khong dung.');
            }
            session_regenerate_id(true);
            unset($_SESSION['login_attempts']);
            $_SESSION['admin_username'] = $adminUsername;
            header('Location: /');
            exit;
        }
        if (!isAuthenticated()) {
            throw new RuntimeException('Vui long dang nhap de tiep tuc.');
        }
        if ($action === 'logout') {
            $_SESSION = [];
            session_destroy();
            header('Location: /');
            exit;
        }

        if ($action === 'add_student') {
            $code = trim((string)($_POST['student_code'] ?? ''));
            $name = trim((string)($_POST['full_name'] ?? ''));
            $class = trim((string)($_POST['class_name'] ?? ''));
            if ($code === '' || $name === '' || $class === ''
                || mb_strlen($code) > 30 || mb_strlen($name) > 120 || mb_strlen($class) > 80) {
                throw new RuntimeException('Vui long nhap day du ma sinh vien, ho ten va lop.');
            }
            $stmt = $pdo->prepare('INSERT INTO students (student_code, full_name, class_name) VALUES (?, ?, ?)');
            $stmt->execute([$code, $name, $class]);
            header('Location: /?msg=student_added'); exit;
        }
        if ($action === 'create_session') {
            $name = trim((string)($_POST['session_name'] ?? ''));
            $date = (string)($_POST['session_date'] ?? date('Y-m-d'));
            $shift = trim((string)($_POST['shift_name'] ?? 'Ca sang'));
            $dateParts = explode('-', $date);
            $validDate = count($dateParts) === 3
                && ctype_digit($dateParts[0])
                && ctype_digit($dateParts[1])
                && ctype_digit($dateParts[2])
                && checkdate((int)$dateParts[1], (int)$dateParts[2], (int)$dateParts[0]);
            if ($name === '' || mb_strlen($name) > 120 || !$validDate
                || !in_array($shift, ['Ca sang', 'Ca chieu', 'Ca toi'], true)) {
                throw new RuntimeException('Thong tin buoi hoc khong hop le.');
            }
            $stmt = $pdo->prepare('INSERT INTO sessions (session_name, session_date, shift_name) VALUES (?, ?, ?)');
            $stmt->execute([$name, $date, $shift]);
            header('Location: /?session=' . (int)$pdo->lastInsertId() . '&msg=session_added'); exit;
        }
        if ($action === 'save_attendance') {
            $sessionId = (int)($_POST['session_id'] ?? 0);
            $statuses = $_POST['status'] ?? [];
            $notes = $_POST['note'] ?? [];
            if ($sessionId < 1 || !is_array($statuses) || !is_array($notes)) {
                throw new RuntimeException('Du lieu diem danh khong hop le.');
            }
            $sessionCheck = $pdo->prepare('SELECT id FROM sessions WHERE id = ?');
            $sessionCheck->execute([$sessionId]);
            if (!$sessionCheck->fetch()) {
                throw new RuntimeException('Buoi hoc khong ton tai.');
            }
            $validStudentIds = array_map(
                'intval',
                $pdo->query('SELECT id FROM students')->fetchAll(PDO::FETCH_COLUMN)
            );
            $submittedStudentIds = array_map('intval', array_keys($statuses));
            sort($validStudentIds);
            sort($submittedStudentIds);
            if ($validStudentIds !== $submittedStudentIds) {
                throw new RuntimeException('Danh sach sinh vien da thay doi. Hay tai lai trang.');
            }
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO attendance (student_id, session_id, status, note) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE status=VALUES(status), note=VALUES(note)");
            foreach ($statuses as $studentId => $status) {
                if (!in_array($status, ['Co mat','Vang mat','Di muon'], true)) {
                    throw new RuntimeException('Trang thai diem danh khong hop le.');
                }
                $note = $notes[$studentId] ?? '';
                if (!is_string($note) || mb_strlen($note) > 255) {
                    throw new RuntimeException('Ghi chu toi da 255 ky tu.');
                }
                $stmt->execute([(int)$studentId, $sessionId, $status, trim($note)]);
            }
            $pdo->commit();
            header('Location: /?session=' . $sessionId . '&msg=attendance_saved'); exit;
        }
        throw new RuntimeException('Thao tac khong hop le.');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($e instanceof PDOException) {
            error_log($e->__toString());
            $error = $e->getCode() === '23000'
                ? 'Ma sinh vien da ton tai hoac du lieu tham chieu khong con hop le.'
                : 'Khong the luu du lieu. Vui long thu lai va kiem tra log may chu neu van gap loi.';
        } else {
            $error = $e->getMessage();
        }
    }
}
if (!isAuthenticated()) {
    $adminConfigured = (getenv('ADMIN_USERNAME') ?: '') !== ''
        && (getenv('ADMIN_PASSWORD_HASH') ?: '') !== '';
    ?>
    <!doctype html>
    <html lang="vi">
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dang nhap - He thong diem danh</title>
    <style>
    *{box-sizing:border-box}body{margin:0;background:#f4f7fb;color:#172033;font:15px/1.5 Arial,Helvetica,sans-serif;min-height:100vh;display:grid;place-items:center;padding:20px}
    .panel{width:min(100%,420px);background:white;border:1px solid #e6eaf0;border-radius:16px;padding:28px;box-shadow:0 8px 30px #18223010}
    h1{font-size:22px;margin:0 0 6px}.muted{color:#667085;margin:0 0 22px}.field{margin-bottom:14px}label{display:block;font-weight:bold;margin-bottom:6px}
    input{width:100%;border:1px solid #d5dbe5;border-radius:9px;padding:11px;font:inherit}button{width:100%;border:0;border-radius:9px;background:#2457d6;color:white;padding:12px;font:inherit;font-weight:bold;cursor:pointer}
    .alert{padding:12px;border-radius:9px;margin-bottom:16px;background:#fff0f0;color:#a32632}.setup{padding:12px;border-radius:9px;margin-bottom:16px;background:#fff4df;color:#80520a}
    </style>
    </head>
    <body><main class="panel"><h1>Dang nhap quan tri</h1><p class="muted">He thong quan ly diem danh sinh vien</p>
    <?php if ($error): ?><div class="alert"><?= h($error) ?></div><?php endif; ?>
    <?php if (!$adminConfigured): ?><div class="setup">Can cau hinh ADMIN_USERNAME va ADMIN_PASSWORD_HASH trong file .env theo README.md truoc khi dang nhap.</div><?php endif; ?>
    <form method="post" autocomplete="on">
    <input type="hidden" name="action" value="login">
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <div class="field"><label for="username">Ten dang nhap</label><input id="username" name="username" autocomplete="username" required></div>
    <div class="field"><label for="password">Mat khau</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>
    <button type="submit">Dang nhap</button></form></main></body></html>
    <?php
    exit;
}
if (($_GET['msg'] ?? '') === 'student_added') $success = 'Da them sinh vien thanh cong.';
if (($_GET['msg'] ?? '') === 'session_added') $success = 'Da tao buoi diem danh thanh cong.';
if (($_GET['msg'] ?? '') === 'attendance_saved') $success = 'Da luu ket qua diem danh thanh cong.';

$students = $pdo->query('SELECT * FROM students ORDER BY student_code')->fetchAll();
$sessions = $pdo->query('SELECT * FROM sessions ORDER BY session_date DESC, id DESC')->fetchAll();
$selectedSession = (int)($_GET['session'] ?? ($sessions[0]['id'] ?? 0));
$currentSession = null;
foreach ($sessions as $s) if ((int)$s['id'] === $selectedSession) $currentSession = $s;
if ($currentSession === null && $sessions) {
    $currentSession = $sessions[0];
    $selectedSession = (int)$currentSession['id'];
}
$records = [];
if ($selectedSession > 0) {
    $stmt = $pdo->prepare('SELECT student_id, status, note FROM attendance WHERE session_id = ?');
    $stmt->execute([$selectedSession]);
    foreach ($stmt->fetchAll() as $r) $records[(int)$r['student_id']] = $r;
}
$totalStudents = count($students);
$presentCount = 0; $absentCount = 0; $lateCount = 0;
foreach ($records as $r) {
    if ($r['status'] === 'Co mat') $presentCount++;
    elseif ($r['status'] === 'Vang mat') $absentCount++;
    elseif ($r['status'] === 'Di muon') $lateCount++;
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>He thong diem danh</title>
<style>
:root{--blue:#2457d6;--ink:#172033;--muted:#667085;--line:#e6eaf0;--bg:#f4f7fb;--green:#16845b;--red:#c43d4b;--amber:#a96708}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.5 Arial,Helvetica,sans-serif}
header{background:#fff;border-bottom:1px solid var(--line)}.head{max-width:1180px;margin:auto;padding:22px 20px;display:flex;align-items:center;justify-content:space-between;gap:14px}
.brand{display:flex;align-items:center;gap:12px}.logo{background:var(--blue);color:white;width:44px;height:44px;border-radius:12px;display:grid;place-items:center;font-weight:bold;font-size:19px}
h1{font-size:22px;margin:0}.sub{color:var(--muted);font-size:13px}.wrap{max-width:1180px;margin:26px auto;padding:0 20px}
.alert{padding:12px 15px;border-radius:10px;margin-bottom:16px;background:#e7f7ee;color:#146c43}.alert.err{background:#fff0f0;color:#a32632}
.stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:20px}.stat,.panel{background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:0 4px 16px #18223008}
.stat{padding:17px}.stat .label{color:var(--muted);font-size:13px}.stat strong{display:block;font-size:26px;margin-top:6px}
.layout{display:grid;grid-template-columns:330px 1fr;gap:18px;align-items:start}.panel{padding:20px;margin-bottom:18px}.panel h2{font-size:17px;margin:0 0 15px}.field{margin-bottom:12px}label{display:block;font-size:13px;font-weight:bold;margin-bottom:6px}
input,select{width:100%;border:1px solid #d5dbe5;border-radius:9px;padding:10px 11px;background:white;font:inherit;min-width:0}
button,.btn{border:0;border-radius:9px;background:var(--blue);color:#fff;padding:10px 14px;font-weight:bold;cursor:pointer;font:inherit;text-decoration:none;display:inline-block}
button:hover,.btn:hover{filter:brightness(.95)}.btn.secondary{background:#edf2ff;color:#2449a5}.btn.full{width:100%}
.toolbar{display:flex;gap:10px;align-items:end;justify-content:space-between;flex-wrap:wrap;margin-bottom:14px}.toolbar .field{margin:0;min-width:220px}
.table-wrap{overflow-x:auto}table{border-collapse:collapse;width:100%;min-width:650px}th,td{text-align:left;padding:12px 10px;border-bottom:1px solid var(--line);vertical-align:middle}th{font-size:12px;color:var(--muted);text-transform:uppercase;background:#fafbfc}
.badge{display:inline-block;padding:4px 8px;border-radius:99px;font-size:12px;font-weight:bold}.present{background:#e4f7ec;color:var(--green)}.absent{background:#fff0f0;color:var(--red)}.late{background:#fff4df;color:var(--amber)}
.status-select{min-width:125px}.note{min-width:140px}.empty{padding:28px;text-align:center;color:var(--muted)}
@media(max-width:850px){.layout{grid-template-columns:1fr}.stats{grid-template-columns:repeat(2,minmax(0,1fr))}.head{align-items:flex-start}.head .sub{text-align:right}}
@media(max-width:480px){.wrap{padding:0 12px;margin:16px auto}.head{padding:16px 12px}.stats{gap:8px}.stat{padding:12px}.stat strong{font-size:22px}.panel{padding:14px}h1{font-size:18px}}
</style>
</head>
<body>
<header><div class="head"><div class="brand"><div class="logo">DD</div><div><h1>He thong diem danh</h1><div class="sub">Quan ly sinh vien va ket qua diem danh</div></div></div><div class="sub"><?= date('d/m/Y') ?><br>Dang nhap: <?= h($_SESSION['admin_username']) ?><form method="post" style="margin-top:8px"><input type="hidden" name="action" value="logout"><input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><button type="submit">Dang xuat</button></form></div></div></header>
<main class="wrap">
<?php if ($success): ?><div class="alert"><?= h($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert err"><?= h($error) ?></div><?php endif; ?>
<section class="stats">
<div class="stat"><div class="label">Tong sinh vien</div><strong><?= $totalStudents ?></strong></div>
<div class="stat"><div class="label">Co mat</div><strong><?= $presentCount ?></strong></div>
<div class="stat"><div class="label">Vang mat</div><strong><?= $absentCount ?></strong></div>
<div class="stat"><div class="label">Di muon</div><strong><?= $lateCount ?></strong></div>
</section>
<div class="layout">
<aside>
<section class="panel"><h2>+ Them sinh vien</h2>
<form method="post"><input type="hidden" name="action" value="add_student"><input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
<div class="field"><label for="student_code">Ma sinh vien</label><input id="student_code" name="student_code" maxlength="30" required placeholder="VD: SV006"></div>
<div class="field"><label for="full_name">Ho va ten</label><input id="full_name" name="full_name" maxlength="120" required placeholder="Nhap ho ten"></div>
<div class="field"><label for="class_name">Lop</label><input id="class_name" name="class_name" maxlength="80" required placeholder="VD: CNTT K10"></div>
<button class="full" type="submit">Them sinh vien</button></form>
</section>
<section class="panel"><h2>+ Tao buoi diem danh</h2>
<form method="post"><input type="hidden" name="action" value="create_session"><input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
<div class="field"><label for="session_name">Ten buoi hoc</label><input id="session_name" name="session_name" maxlength="120" required placeholder="VD: Lap trinh PHP"></div>
<div class="field"><label for="session_date">Ngay hoc</label><input type="date" id="session_date" name="session_date" required value="<?= date('Y-m-d') ?>"></div>
<div class="field"><label for="shift_name">Ca hoc</label><select id="shift_name" name="shift_name"><option>Ca sang</option><option>Ca chieu</option><option>Ca toi</option></select></div>
<button class="full" type="submit">Tao buoi hoc</button></form>
</section>
<section class="panel"><h2>Danh sach sinh vien</h2>
<div class="table-wrap"><table style="min-width:0"><thead><tr><th>Ma SV</th><th>Ho ten</th></tr></thead><tbody>
<?php foreach ($students as $st): ?><tr><td><?= h($st['student_code']) ?></td><td><?= h($st['full_name']) ?></td></tr><?php endforeach; ?>
<?php if (!$students): ?><tr><td colspan="2">Chua co sinh vien.</td></tr><?php endif; ?>
</tbody></table></div></section>
</aside>
<section class="panel">
<h2>Ghi nhan diem danh</h2>
<div class="toolbar"><form method="get" class="field"><label for="session">Chon buoi hoc</label><select name="session" id="session" onchange="this.form.submit()">
<?php foreach ($sessions as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)$s['id']===$selectedSession?'selected':'' ?>><?= h($s['session_name']) ?> - <?= h(date('d/m/Y', strtotime($s['session_date']))) ?></option><?php endforeach; ?>
</select></form>
<?php if ($currentSession): ?><span class="sub"><?= h($currentSession['shift_name']) ?> · <?= h(date('d/m/Y', strtotime($currentSession['session_date']))) ?></span><?php endif; ?></div>
<?php if (!$sessions): ?><div class="empty">Chua co buoi hoc. Hay tao buoi diem danh truoc.</div>
<?php elseif (!$students): ?><div class="empty">Chua co sinh vien. Hay them sinh vien truoc.</div>
<?php else: ?>
<form method="post"><input type="hidden" name="action" value="save_attendance"><input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>"><input type="hidden" name="session_id" value="<?= $selectedSession ?>">
<div class="table-wrap"><table><thead><tr><th>Ma SV</th><th>Ho va ten</th><th>Trang thai</th><th>Ghi chu</th></tr></thead><tbody>
<?php foreach ($students as $st): $r=$records[(int)$st['id']]??['status'=>'Co mat','note'=>'']; ?>
<tr><td><?= h($st['student_code']) ?></td><td><?= h($st['full_name']) ?><div class="sub"><?= h($st['class_name']) ?></div></td>
<td><select class="status-select" name="status[<?= (int)$st['id'] ?>]">
<?php foreach (['Co mat','Vang mat','Di muon'] as $status): ?><option value="<?= h($status) ?>" <?= $r['status']===$status?'selected':'' ?>><?= h($status) ?></option><?php endforeach; ?>
</select></td><td><input class="note" name="note[<?= (int)$st['id'] ?>]" maxlength="255" value="<?= h($r['note']) ?>" placeholder="Ghi chu (neu co)"></td></tr>
<?php endforeach; ?>
</tbody></table></div><div style="display:flex;justify-content:flex-end;margin-top:16px"><button type="submit">Luu diem danh</button></div></form>
<?php endif; ?>
</section>
</div>
<section class="panel"><h2>Lich su diem danh</h2>
<div class="table-wrap"><table><thead><tr><th>Ngay</th><th>Buoi hoc</th><th>Ma SV</th><th>Ho ten</th><th>Trang thai</th><th>Ghi chu</th></tr></thead><tbody>
<?php
$history = $pdo->query("SELECT se.session_date,se.session_name,st.student_code,st.full_name,a.status,a.note FROM attendance a JOIN students st ON st.id=a.student_id JOIN sessions se ON se.id=a.session_id ORDER BY se.session_date DESC,se.id DESC,st.student_code")->fetchAll();
foreach ($history as $item):
$class = $item['status']==='Co mat'?'present':($item['status']==='Vang mat'?'absent':'late');
?>
<tr><td><?= h(date('d/m/Y',strtotime($item['session_date']))) ?></td><td><?= h($item['session_name']) ?></td><td><?= h($item['student_code']) ?></td><td><?= h($item['full_name']) ?></td><td><span class="badge <?= $class ?>"><?= h($item['status']) ?></span></td><td><?= h($item['note']) ?></td></tr>
<?php endforeach; if (!$history): ?><tr><td colspan="6" class="empty">Chua co du lieu diem danh.</td></tr><?php endif; ?>
</tbody></table></div></section>
</main>
</body></html>
