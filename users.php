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

$isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

// AJAX endpoint: โหลดข้อมูลผู้ใช้รายเดียว (สำหรับ popup แก้ไข)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'user' && !empty($_GET['id'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $ajaxId = intval($_GET['id']);
    $out = array();
    $ur = $conn->query("SELECT id, username, name, position, department, agency_id, role, status FROM users WHERE id = $ajaxId LIMIT 1");
    if ($ur && ($urow = $ur->fetch_assoc())) {
        if ($isOfficeUser) {
            if ((int)$urow['agency_id'] !== $myAgencyId || $urow['role'] !== 'user') {
                echo json_encode(array('error' => 'ไม่มีสิทธิ์แก้ไขผู้ใช้นี้'), JSON_UNESCAPED_UNICODE);
                exit;
            }
        }
        $out = $urow;
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
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
                        if ($isAjax) {
                            header('Content-Type: application/json; charset=UTF-8');
                            echo json_encode(array('success' => true, 'message' => $success), JSON_UNESCAPED_UNICODE);
                            exit;
                        }
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
                        if ($isAjax) {
                            header('Content-Type: application/json; charset=UTF-8');
                            echo json_encode(array('success' => true, 'message' => $success), JSON_UNESCAPED_UNICODE);
                            exit;
                        }
                        header('Location: users.php');
                        exit;
                    }
                }
            }
        }
    }
    if ($isAjax && !empty($error)) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array('success' => false, 'error' => $error), JSON_UNESCAPED_UNICODE);
        exit;
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

$agencies = array();
$agRes = $conn->query("SELECT id, agency_code, agency_name FROM agencies ORDER BY sort_order ASC, agency_name ASC");
if ($agRes) {
    while ($aRow = $agRes->fetch_assoc()) {
        $agencies[] = $aRow;
    }
}

$lockedAgencyName = '';
if ($isOfficeUser) {
    foreach ($agencies as $agency) {
        if ((int)$agency['id'] === $myAgencyId) {
            $lockedAgencyName = $agency['agency_name'] . ' (' . $agency['agency_code'] . ')';
            break;
        }
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
                        <button type="button" class="btn btn-primary" onclick="openUserModal(0)">
                            ➕ เพิ่มผู้ใช้ใหม่
                        </button>
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
                                            <a class="btn btn-sm btn-outline-primary" href="javascript:void(0)" onclick="openUserModal(<?= (int)$user['id'] ?>)">แก้ไข</a>
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
    </main>
</div>

<!-- ===== Modal: เพิ่ม/แก้ไขผู้ใช้ (popup) ===== -->
<div class="modal fade" id="userModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="userForm" onsubmit="return saveUser(event)">
                <input type="hidden" name="user_id" id="userId" value="0">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="userModalTitle">➕ เพิ่มผู้ใช้ใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 d-none" id="userModalError"></div>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">ชื่อผู้ใช้ <span class="text-danger">*</span></label>
                            <input class="form-control" type="text" name="username" id="userUsername" placeholder="เช่น guest" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">คำนำหน้า-ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                            <input class="form-control" type="text" name="name" id="userName" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">ตำแหน่ง</label>
                            <input class="form-control" type="text" name="position" id="userPosition">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">หน่วยงาน (แผนก)</label>
                            <input class="form-control" type="text" name="department" id="userDepartment">
                        </div>
                        <div class="col-12">
                            <label class="form-label">หน่วยงานการศึกษา (สังกัด) <span class="text-danger">*</span></label>
                            <?php if ($isOfficeUser): ?>
                                <input type="hidden" name="agency_id" id="userAgencyId" value="<?= (int)$myAgencyId ?>">
                                <input class="form-control" type="text" id="userAgencyLocked" value="<?= htmlspecialchars($lockedAgencyName) ?>" readonly>
                                <div class="form-text">หน่วยงานถูกล็อกตามสิทธิ์ของคุณ ไม่สามารถเปลี่ยนได้</div>
                            <?php else: ?>
                                <select class="form-select" name="agency_id" id="userAgencyId" required>
                                    <option value="">-- เลือกหน่วยงานการศึกษา --</option>
                                    <?php foreach ($agencies as $agency): ?>
                                        <option value="<?= (int)$agency['id'] ?>">
                                            <?= htmlspecialchars($agency['agency_name'] . ' (' . $agency['agency_code'] . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">บทบาท</label>
                            <?php if ($isOfficeUser): ?>
                                <input type="hidden" name="role" id="userRole" value="user">
                                <input class="form-control" type="text" id="userRoleLocked" value="<?= htmlspecialchars(roleLabel('user')) ?>" readonly>
                                <div class="form-text">ผู้ประสานงานหน่วยงานกำหนดบทบาทได้เฉพาะผู้ใช้ทั่วไป (user)</div>
                            <?php else: ?>
                                <select class="form-select" name="role" id="userRole">
                                    <option value="user">ผู้ใช้ทั่วไป (user) — เพิ่ม/แก้ไข/รายงานโครงการของตนเองเท่านั้น</option>
                                    <option value="office">ผู้ประสานงานหน่วยงาน (office) — เพิ่ม/แก้ไข/รายงานโครงการของหน่วยงานตนเองได้</option>
                                    <option value="plan">ผู้กำหนดตัวชี้วัด KPI (plan) — กำหนดตัวชี้วัดร่วมสำหรับจังหวัด/หน่วยงาน</option>
                                    <option value="admin">ผู้ดูแลระบบ (admin) — เข้าถึงทุกอย่าง</option>
                                </select>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">สถานะ</label>
                            <select class="form-select" name="status" id="userStatus">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" id="userPasswordLabel">รหัสผ่าน <span class="text-danger">*</span></label>
                            <input class="form-control" type="password" name="password" id="userPassword" autocomplete="new-password" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary" id="userSaveBtn">💾 บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ===== Modal เพิ่ม/แก้ไขผู้ใช้ (popup) =====
var userModalEl = document.getElementById('userModal');
var isOfficeUser = <?= $isOfficeUser ? 'true' : 'false' ?>;

function openUserModal(id) {
    const form = document.getElementById('userForm');
    form.reset();
    document.getElementById('userId').value = id || 0;
    document.getElementById('userModalError').classList.add('d-none');
    document.getElementById('userSaveBtn').disabled = false;
    document.getElementById('userPassword').required = true;
    document.getElementById('userPasswordLabel').innerHTML = 'รหัสผ่าน <span class="text-danger">*</span>';
    document.getElementById('userModalTitle').textContent = '➕ เพิ่มผู้ใช้ใหม่';
    if (isOfficeUser) {
        document.getElementById('userAgencyId').value = '<?= (int)$myAgencyId ?>';
        document.getElementById('userRole').value = 'user';
    }
    if (id) {
        document.getElementById('userModalTitle').textContent = '✏️ แก้ไขผู้ใช้';
        document.getElementById('userPassword').required = false;
        document.getElementById('userPasswordLabel').innerHTML = 'รหัสผ่าน <span class="text-muted">(ถ้าต้องการเปลี่ยน)</span>';
        fetch('users.php?ajax=user&id=' + encodeURIComponent(id))
            .then(function (r) { return r.json(); })
            .then(function (u) {
                if (!u || u.error || !u.id) {
                    document.getElementById('userModalError').textContent = (u && u.error) ? u.error : 'ไม่พบข้อมูลผู้ใช้';
                    document.getElementById('userModalError').classList.remove('d-none');
                    return;
                }
                document.getElementById('userId').value = u.id;
                document.getElementById('userUsername').value = u.username || '';
                document.getElementById('userName').value = u.name || '';
                document.getElementById('userPosition').value = u.position || '';
                document.getElementById('userDepartment').value = u.department || '';
                if (!isOfficeUser) {
                    document.getElementById('userAgencyId').value = u.agency_id || '';
                    document.getElementById('userRole').value = u.role || 'user';
                }
                document.getElementById('userStatus').value = u.status || 'active';
            })
            .catch(function () {
                document.getElementById('userModalError').textContent = 'โหลดข้อมูลไม่สำเร็จ';
                document.getElementById('userModalError').classList.remove('d-none');
            });
    }
    var modal = bootstrap.Modal.getOrCreateInstance(userModalEl);
    modal.show();
}

function saveUser(event) {
    event.preventDefault();
    const form = document.getElementById('userForm');
    const errBox = document.getElementById('userModalError');
    const btn = document.getElementById('userSaveBtn');
    errBox.classList.add('d-none');
    btn.disabled = true;
    const fd = new FormData(form);
    fd.append('save_user', '1');
    fetch('users.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
    })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res && res.success) {
                bootstrap.Modal.getInstance(userModalEl).hide();
                location.reload();
            } else {
                errBox.textContent = (res && res.error) ? res.error : 'บันทึกไม่สำเร็จ';
                errBox.classList.remove('d-none');
                btn.disabled = false;
            }
        })
        .catch(function () {
            errBox.textContent = 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้';
            errBox.classList.remove('d-none');
            btn.disabled = false;
        });
    return false;
}
</script>
</body>
</html>
