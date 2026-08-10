<?php
require_once __DIR__ . '/db.php';

/**
 * Global vars from `db.php` for static analyzers
 * @var mysqli $conn
 * @var string $office_name
 * @var string $logo_url
 * @var string $fiscal_year
 * @var string $office_developer
 * @var string $office_email
 * @var string $office_tel
 */

requireLogin();

// ควบคุมสิทธิ์: admin/plan จัดการได้ทุกคน, office จัดการได้เฉพาะผู้ใช้ (user) ในหน่วยงานของตนเอง, user เข้าถึงไม่ได้
$isAdminUser = isAdmin();
$isPlanUser = isPlan();
$isOfficeUser = isOffice();
$isManageAll = $isAdminUser || $isPlanUser;
$currentUserId = currentUserId();
$myAgencyId = $isOfficeUser ? (int)currentAgencyId() : 0;
if (!$isManageAll && !$isOfficeUser) {
    header('Location: index.php');
    exit;
}

$fiscalYear = !empty($fiscal_year) ? $fiscal_year : date('Y') + 543;
$action = isset($_GET['action']) ? $_GET['action'] : '';
$userId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck('users.php');
    if (isset($_POST['save_user'])) {
        $userId = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
        $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
        $position = trim(isset($_POST['position']) ? $_POST['position'] : '');
        $department = trim(isset($_POST['department']) ? $_POST['department'] : '');
        $role = trim(isset($_POST['role']) ? $_POST['role'] : 'user');
        $status = trim(isset($_POST['status']) ? $_POST['status'] : 'active');
        $password = trim(isset($_POST['password']) ? $_POST['password'] : '');
        $agencyId = isset($_POST['agency_id']) ? intval($_POST['agency_id']) : 0;

        if (empty($username) || empty($name) || empty($role) || empty($status)) {
            $error = 'กรุณากรอกข้อมูลให้ครบถ้วน';
        } elseif ($isOfficeUser && $agencyId !== $myAgencyId) {
            $error = 'ผู้ประสานงานหน่วยงานไม่สามารถเปลี่ยนหน่วยงานได้';
        } elseif ($isOfficeUser && $role !== 'user') {
            $error = 'ผู้ประสานงานหน่วยงานสามารถกำหนดสิทธิ์เป็นผู้ใช้ทั่วไป (user) เท่านั้น';
        } elseif ($agencyId <= 0) {
            $error = 'กรุณาเลือกหน่วยงานการศึกษา (สังกัด)';
        } elseif (($agencyRes = $conn->query("SELECT id FROM agencies WHERE id = $agencyId LIMIT 1")) === false || $agencyRes->num_rows === 0) {
            $error = 'หน่วยงานที่เลือกไม่ถูกต้อง กรุณาเลือกใหม่';
        } else {
            if ($userId > 0) {
                // office: แก้ไขได้เฉพาะผู้ใช้ในหน่วยงานของตนเองและเป็นบทบาท user เท่านั้น
                if ($isOfficeUser) {
                    $targetAgency = 0;
                    $targetRole = '';
                    $tRes = $conn->query("SELECT agency_id, role FROM users WHERE id = $userId LIMIT 1");
                    if ($tRes && ($tRow = $tRes->fetch_assoc())) {
                        $targetAgency = (int)$tRow['agency_id'];
                        $targetRole = $tRow['role'];
                    }
                    if ($targetAgency !== $myAgencyId) {
                        $error = 'ไม่สามารถแก้ไขผู้ใช้ต่างหน่วยงานได้';
                    } elseif ($targetRole !== 'user') {
                        $error = 'ไม่สามารถแก้ไขผู้ใช้ที่มีบทบาทสูงกว่าได้';
                    }
                }
                if (empty($error)) {
                    $checkSql = "SELECT id FROM users WHERE username = '" . $conn->real_escape_string($username) . "' AND id != $userId LIMIT 1";
                    $checkRes = $conn->query($checkSql);
                    if ($checkRes && $checkRes->num_rows > 0) {
                        $error = 'ชื่อผู้ใช้นี้ถูกใช้งานแล้ว';
                    } else {
                        $updateFields = "username='" . $conn->real_escape_string($username) . "', name='" . $conn->real_escape_string($name) . "', position='" . $conn->real_escape_string($position) . "', department='" . $conn->real_escape_string($department) . "', role='" . $conn->real_escape_string($role) . "', status='" . $conn->real_escape_string($status) . "', agency_id=$agencyId";
                        if (!empty($password)) {
                            $hash = password_hash($password, PASSWORD_DEFAULT);
                            $updateFields .= ", password='" . $conn->real_escape_string($hash) . "'";
                        }
                        $conn->query("UPDATE users SET $updateFields WHERE id = $userId");
                        $success = 'บันทึกข้อมูลผู้ใช้งานเรียบร้อยแล้ว';
                        logfile($conn, 'แก้ไข', 'users', $userId, array(
                            'username' => $username,
                            'name' => $name,
                            'role' => $role,
                            'status' => $status,
                        ));
                        header('Location: users.php');
                        exit;
                    }
                }
            } else {
                $checkSql = "SELECT id FROM users WHERE username = '" . $conn->real_escape_string($username) . "' LIMIT 1";
                $checkRes = $conn->query($checkSql);
                if ($checkRes && $checkRes->num_rows > 0) {
                    $error = 'ชื่อผู้ใช้นี้ถูกใช้งานแล้ว';
                } else {
                    if (empty($password)) {
                        $error = 'กรุณากรอกรหัสผ่านสำหรับผู้ใช้ใหม่';
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $conn->query("INSERT INTO users (username, name, password, position, department, agency_id, role, status) VALUES ('" . $conn->real_escape_string($username) . "', '" . $conn->real_escape_string($name) . "', '" . $conn->real_escape_string($hash) . "', '" . $conn->real_escape_string($position) . "', '" . $conn->real_escape_string($department) . "', $agencyId, '" . $conn->real_escape_string($role) . "', '" . $conn->real_escape_string($status) . "')");
                        $newUserId = (int)$conn->insert_id;
                        $success = 'เพิ่มผู้ใช้งานเรียบร้อยแล้ว';
                        logfile($conn, 'เพิ่ม', 'users', $newUserId, array(
                            'username' => $username,
                            'name' => $name,
                            'role' => $role,
                            'status' => $status,
                        ));
                        header('Location: users.php');
                        exit;
                    }
                }
            }
        }
    }
}

if ($action === 'toggle_status' && $userId > 0) {
    // CSRF: state-changing action must carry the token
    $gotToken = isset($_GET['csrf']) ? $_GET['csrf'] : '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $gotToken)) {
        setFlash('error', 'CSRF token ไม่ถูกต้อง กรุณาลองใหม่');
        header('Location: users.php');
        exit;
    }
    $userRes = $conn->query("SELECT username, status, role, agency_id FROM users WHERE id = $userId LIMIT 1");
    if ($userRes && $row = $userRes->fetch_assoc()) {
        if ($isOfficeUser && (int)$row['agency_id'] !== $myAgencyId) {
            setFlash('error', 'ไม่สามารถระงับ/เปิดใช้งานผู้ใช้ต่างหน่วยงานได้');
            header('Location: users.php');
            exit;
        }
        if ($isOfficeUser && $row['role'] !== 'user') {
            setFlash('error', 'ผู้ประสานงานหน่วยงานสามารถจัดการได้เฉพาะผู้ใช้ทั่วไป (user) เท่านั้น');
            header('Location: users.php');
            exit;
        }
        if ($userId === $currentUserId) {
            setFlash('error', 'ไม่สามารถระงับการใช้งานบัญชีของตนเองได้');
            header('Location: users.php');
            exit;
        }
        $newStatus = $row['status'] === 'active' ? 'inactive' : 'active';
        $conn->query("UPDATE users SET status = '$newStatus' WHERE id = $userId");
        logfile($conn, 'เปลี่ยนสถานะ', 'users', $userId, array(
            'username' => isset($row['username']) ? $row['username'] : '',
            'status' => $newStatus,
        ));
    }
    header('Location: users.php');
    exit;
}

// ลบผู้ใช้ — เฉพาะ admin/plan เท่านั้น
if ($action === 'delete' && $userId > 0 && $isManageAll) {
    $gotToken = isset($_GET['csrf']) ? $_GET['csrf'] : '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $gotToken)) {
        setFlash('error', 'CSRF token ไม่ถูกต้อง กรุณาลองใหม่');
        header('Location: users.php');
        exit;
    }
    if ($userId === $currentUserId) {
        setFlash('error', 'ไม่สามารถลบบัญชีของตนเองได้');
        header('Location: users.php');
        exit;
    }
    $userRes = $conn->query("SELECT username, role FROM users WHERE id = $userId LIMIT 1");
    if ($userRes && $row = $userRes->fetch_assoc()) {
        if ($row['role'] === 'admin' && $userId === 1) {
            setFlash('error', 'ไม่สามารถลบบัญชีผู้ดูแลระบบหลักได้');
            header('Location: users.php');
            exit;
        }
        $conn->query("DELETE FROM users WHERE id = $userId");
        logfile($conn, 'ลบ', 'users', $userId, array(
            'username' => isset($row['username']) ? $row['username'] : '',
        ));
        setFlash('success', 'ลบผู้ใช้งานเรียบร้อยแล้ว');
    }
    header('Location: users.php');
    exit;
}

if ($action === 'reset' && $userId > 0 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    if ($isOfficeUser) {
        $tRes = $conn->query("SELECT role, agency_id FROM users WHERE id = $userId LIMIT 1");
        $tRow = ($tRes) ? $tRes->fetch_assoc() : null;
        if ($tRow && (int)$tRow['agency_id'] !== $myAgencyId) {
            setFlash('error', 'ไม่สามารถรีเซ็ตรหัสผ่านผู้ใช้ต่างหน่วยงานได้');
            header('Location: users.php');
            exit;
        }
        if ($tRow && $tRow['role'] !== 'user') {
            setFlash('error', 'ผู้ประสานงานหน่วยงานสามารถรีเซ็ตรหัสผ่านได้เฉพาะผู้ใช้ทั่วไป (user) เท่านั้น');
            header('Location: users.php');
            exit;
        }
    }
    $newPassword = trim(isset($_POST['new_password']) ? $_POST['new_password'] : '');
    if (empty($newPassword)) {
        $error = 'กรุณากรอกรหัสผ่านใหม่';
    } else {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $conn->query("UPDATE users SET password = '" . $conn->real_escape_string($hash) . "' WHERE id = $userId");
        $success = 'รีเซ็ตรหัสผ่านเรียบร้อยแล้ว';
        logfile($conn, 'รีเซ็ตรหัสผ่าน', 'users', $userId);
        header('Location: users.php');
        exit;
    }
}

$users = array();
$userSql = "SELECT u.*, a.agency_name, a.agency_code FROM users u LEFT JOIN agencies a ON a.id = u.agency_id";
if ($isOfficeUser) {
    // office: เห็นเฉพาะผู้ใช้ในหน่วยงานของตนเอง
    $userSql .= " WHERE u.agency_id = " . (int)$myAgencyId;
}
$userSql .= " ORDER BY u.id ASC";
$result = $conn->query($userSql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

$editingUser = null;
if ($action === 'edit' && $userId > 0) {
    $userRes = $conn->query("SELECT * FROM users WHERE id = $userId LIMIT 1");
    if ($userRes && $editingUser = $userRes->fetch_assoc()) {
        if ($isOfficeUser && (int)$editingUser['agency_id'] !== $myAgencyId) {
            setFlash('error', 'ไม่สามารถแก้ไขผู้ใช้ต่างหน่วยงานได้');
            header('Location: users.php');
            exit;
        }
        if ($isOfficeUser && $editingUser['role'] !== 'user') {
            setFlash('error', 'ผู้ประสานงานหน่วยงานสามารถแก้ไขได้เฉพาะผู้ใช้ทั่วไป (user) เท่านั้น');
            header('Location: users.php');
            exit;
        }
    }
}

$agencies = array();
$agRes = $conn->query("SELECT id, agency_code, agency_name FROM agencies ORDER BY sort_order ASC, agency_name ASC");
if ($agRes) {
    while ($aRow = $agRes->fetch_assoc()) {
        $agencies[] = $aRow;
    }
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการผู้ใช้ | <?= htmlspecialchars($office_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/style.php'; ?>
</head>
<body>
<?php $activePage = 'users'; include __DIR__ . '/menu.php'; ?>
        <div class="container-fluid">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <div class="text-uppercase section-title mb-2">👥 ผู้ใช้ระบบ</div>
                            <h1 class="h3 fw-bold mb-2">จัดการผู้ใช้งาน</h1>
                            <p class="text-muted mb-0"><?= $isManageAll ? 'ผู้ดูแลระบบ/ผู้กำหนดตัวชี้วัด KPI สามารถเพิ่ม ลบ แก้ไข รีเซ็ตรหัสผ่าน และระงับการใช้งานบัญชีทั้งหมดได้' : 'ผู้ประสานงานหน่วยงานสามารถเพิ่ม แก้ไข รีเซ็ตรหัสผ่าน และระงับการใช้งานเฉพาะผู้ใช้ทั่วไป (user) ในหน่วยงานของตนเองเท่านั้น' ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php getFlash(); ?>

            <div class="row g-4">
                <div class="col-12 col-xl-7">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>ชื่อ<br>username</th>
                                            <th>ตำแหน่ง/หน่วยงาน</th>
                                            <th>สิทธิ์</th>
                                            <th>สถานะ</th>
                                            <th>การจัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $index => $user): ?>
                                            <tr>
                                                <td><?= $index + 1 ?></td>
                                                <td><?= htmlspecialchars($user['name']) ?><br><font color="green"><?= htmlspecialchars($user['username']) ?></font></td>
                                                <td><?= htmlspecialchars($user['position']) ?><br><?= htmlspecialchars($user['department']) ?><br><span class="text-primary">🏛️ <?= htmlspecialchars($user['agency_name'] ?: 'ไม่ระบุหน่วยงาน') ?></span></td>
                                                <td><?= htmlspecialchars(roleLabel($user['role'])) ?></td>
                                                <td><?= htmlspecialchars($user['status']) ?></td>
                                                <td>
                                                    <a class="btn btn-sm btn-outline-primary" href="users.php?action=edit&id=<?= $user['id'] ?>">แก้ไข</a>
                                                    <a class="btn btn-sm btn-outline-secondary" href="users.php?action=toggle_status&id=<?= $user['id'] ?>&csrf=<?= urlencode(csrfToken()) ?>">
                                                        <?= $user['status'] === 'active' ? 'ระงับ' : 'เปิดใช้งาน' ?>
                                                    </a>
                                                    <?php if ($isManageAll && (int)$user['id'] !== $currentUserId): ?>
                                                        <a class="btn btn-sm btn-outline-danger" href="users.php?action=delete&id=<?= $user['id'] ?>&csrf=<?= urlencode(csrfToken()) ?>" onclick="return confirm('ยืนยันการลบผู้ใช้ <?= htmlspecialchars($user['name'], ENT_QUOTES) ?> ?');">ลบ</a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-5">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h2 class="h5 fw-bold mb-3"><?= $editingUser ? 'แก้ไขผู้ใช้' : 'เพิ่มผู้ใช้ใหม่' ?></h2>
                            <form method="post">
                                <input type="hidden" name="user_id" value="<?= $editingUser ? intval($editingUser['id']) : 0 ?>">
                                <?= csrfField() ?>
                                <div class="mb-3">
                                    <input class="form-control" type="text" placeholder="ชื่อผู้ใช้ เช่น guest" name="username" value="<?= htmlspecialchars(isset($editingUser['username']) ? $editingUser['username'] : '') ?>" required>
                                </div>
                                <div class="mb-3">
                                    <input class="form-control" type="text" placeholder="คำนำหน้า-ชื่อ-นามสกุล" name="name" value="<?= htmlspecialchars(isset($editingUser['name']) ? $editingUser['name'] : '') ?>" required>
                                </div>
                                <div class="mb-3">
                                    <input class="form-control" type="text" placeholder="ตำแหน่ง" name="position" value="<?= htmlspecialchars(isset($editingUser['position']) ? $editingUser['position'] : '') ?>">
                                </div>
                                <div class="mb-3">
                                    <input class="form-control" type="text" placeholder="หน่วยงาน" name="department" value="<?= htmlspecialchars(isset($editingUser['department']) ? $editingUser['department'] : '') ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">หน่วยงานการศึกษา (สังกัด) <span class="text-danger">*</span></label>
                                    <?php if ($isOfficeUser): ?>
                                        <?php
                                        $lockedAgencyName = '';
                                        foreach ($agencies as $agency) {
                                            if ((int)$agency['id'] === $myAgencyId) {
                                                $lockedAgencyName = $agency['agency_name'] . ' (' . $agency['agency_code'] . ')';
                                                break;
                                            }
                                        }
                                        ?>
                                        <input type="hidden" name="agency_id" value="<?= (int)$myAgencyId ?>">
                                        <input class="form-control" type="text" value="<?= htmlspecialchars($lockedAgencyName) ?>" readonly>
                                        <div class="form-text">หน่วยงานถูกล็อกตามสิทธิ์ของคุณ ไม่สามารถเปลี่ยนได้</div>
                                    <?php else: ?>
                                        <select class="form-select" name="agency_id" required>
                                            <option value="">-- เลือกหน่วยงานการศึกษา --</option>
                                            <?php foreach ($agencies as $agency): ?>
                                                <option value="<?= (int)$agency['id'] ?>" <?= (isset($editingUser['agency_id']) && (int)$editingUser['agency_id'] === (int)$agency['id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($agency['agency_name'] . ' (' . $agency['agency_code'] . ')') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">บทบาท</label>
                                    <?php if ($isOfficeUser): ?>
                                        <input type="hidden" name="role" value="user">
                                        <input class="form-control" type="text" value="<?= htmlspecialchars(roleLabel('user')) ?>" readonly>
                                        <div class="form-text">ผู้ประสานงานหน่วยงานกำหนดบทบาทได้เฉพาะผู้ใช้ทั่วไป (user)</div>
                                    <?php else: ?>
                                        <select class="form-select" name="role">
                                            <option value="user" <?= isset($editingUser['role']) && $editingUser['role'] === 'user' ? 'selected' : '' ?>>ผู้ใช้ทั่วไป (user) — เพิ่ม/แก้ไข/รายงานโครงการของตนเองเท่านั้น</option>
                                            <option value="office" <?= isset($editingUser['role']) && $editingUser['role'] === 'office' ? 'selected' : '' ?>>ผู้ประสานงานหน่วยงาน (office) — เพิ่ม/แก้ไข/รายงานโครงการของหน่วยงานตนเองได้</option>
                                            <option value="plan" <?= isset($editingUser['role']) && $editingUser['role'] === 'plan' ? 'selected' : '' ?>>ผู้กำหนดตัวชี้วัด KPI (plan) — กำหนดตัวชี้วัดร่วมสำหรับจังหวัด/หน่วยงาน</option>
                                            <option value="admin" <?= isset($editingUser['role']) && $editingUser['role'] === 'admin' ? 'selected' : '' ?>>ผู้ดูแลระบบ (admin) — เข้าถึงทุกอย่าง</option>
                                        </select>
                                    <?php endif; ?>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">สถานะ</label>
                                    <select class="form-select" name="status">
                                        <option value="active" <?= isset($editingUser['status']) && $editingUser['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="inactive" <?= isset($editingUser['status']) && $editingUser['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">รหัสผ่าน<?= $editingUser ? ' (ถ้าต้องการเปลี่ยน)' : '' ?></label>
                                    <input class="form-control" type="password" name="password" <?= $editingUser ? '' : 'required' ?>>
                                </div>
                                <button class="btn btn-primary w-100" type="submit" name="save_user">บันทึกข้อมูล</button>
                            </form>
                        </div>
                    </div>

                    <?php if ($action === 'reset' && $userId > 0): ?>
                        <div class="card border-0 shadow-sm mt-4">
                            <div class="card-body">
                                <h2 class="h5 fw-bold mb-3">รีเซ็ตรหัสผ่าน</h2>
                                <form method="post" action="users.php?action=reset&id=<?= $userId ?>">
                                    <?= csrfField() ?>
                                    <div class="mb-3">
                                        <label class="form-label">รหัสผ่านใหม่</label>
                                        <input class="form-control" type="password" name="new_password" required>
                                    </div>
                                    <button class="btn btn-warning w-100" type="submit" name="reset_password">รีเซ็ต</button>
                                </form>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
