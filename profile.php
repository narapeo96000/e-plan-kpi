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

$userId = (int)$_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id, username, name, password, position, department, role, status FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    setFlash('error', 'ไม่พบข้อมูลผู้ใช้');
    header('Location: index.php');
    exit;
}

$errors = array();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck('profile.php');
    $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
    $position = trim(isset($_POST['position']) ? $_POST['position'] : '');
    $department = trim(isset($_POST['department']) ? $_POST['department'] : '');
    $currentPassword = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    if ($name === '') {
        $errors[] = 'กรุณากรอกชื่อ-นามสกุล';
    }

    if ($newPassword !== '') {
        if ($currentPassword === '') {
            $errors[] = 'กรุณากรอกรหัสผ่านปัจจุบันเพื่อเปลี่ยนรหัสผ่าน';
        } elseif (!password_verify($currentPassword, $user['password'])) {
            $errors[] = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
        }

        if (strlen($newPassword) < 6) {
            $errors[] = 'รหัสผ่านใหม่ต้องมีอย่างน้อย 6 ตัวอักษร';
        }

        if ($newPassword !== $confirmPassword) {
            $errors[] = 'ยืนยันรหัสผ่านใหม่ไม่ตรงกัน';
        }
    }

    if (empty($errors)) {
        $updateSql = "UPDATE users SET name = ?, position = ?, department = ?";
        $params = array($name, $position, $department);
        $types = 'sss';

        if ($newPassword !== '') {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $updateSql .= ", password = ?";
            $params[] = $hashedPassword;
            $types .= 's';
        }

        $updateSql .= " WHERE id = ?";
        $params[] = $userId;
        $types .= 'i';

        $stmt = $conn->prepare($updateSql);
        $stmt->bind_param($types, ...$params);
        $success = $stmt->execute();
        $stmt->close();

        if ($success) {
            $_SESSION['name'] = $name;
            $_SESSION['position'] = $position;
            $_SESSION['department'] = $department;
            logfile($conn, 'แก้ไข', 'profile', $userId, array(
                'name' => $name,
                'position' => $position,
                'department' => $department,
                'password_changed' => $newPassword !== '' ? 'yes' : 'no',
            ));
            setFlash('success', 'อัปเดตข้อมูลโปรไฟล์เรียบร้อยแล้ว');
            header('Location: profile.php');
            exit;
        }

        $errors[] = 'ไม่สามารถบันทึกข้อมูลได้ กรุณาลองใหม่';
    }
}

$displayName = isset($user['name']) ? $user['name'] : '';
$displayPosition = isset($user['position']) ? $user['position'] : '';
$displayDepartment = isset($user['department']) ? $user['department'] : '';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>โปรไฟล์ของฉัน | <?= htmlspecialchars($office_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/style.php'; ?>
</head>
<body>
<?php $activePage = 'profile'; include __DIR__ . '/menu.php'; ?>
    <div class="container-fluid">
        <div class="card border-0 shadow-sm rounded-4 mb-4 hero-panel">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <div class="text-uppercase section-title mb-2">👤 Profile</div>
                        <h1 class="h2 fw-bold mb-2" style="color: #111827;">แก้ไขข้อมูลส่วนตัว</h1>
                        <p class="text-muted mb-0">คุณสามารถแก้ไขข้อมูลของตัวเองและเปลี่ยนรหัสผ่านได้ที่นี่</p>
                    </div>
                    <a class="btn btn-primary" href="index.php">กลับหน้าหลัก</a>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <?php getFlash(); ?>
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <?= csrfField() ?>
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">ชื่อผู้ใช้งาน</label>
                            <input class="form-control" type="text" value="<?= htmlspecialchars($user['username']) ?>" readonly>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">ชื่อ-นามสกุล</label>
                            <input class="form-control" type="text" name="name" value="<?= htmlspecialchars($displayName) ?>" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">ตำแหน่ง</label>
                            <input class="form-control" type="text" name="position" value="<?= htmlspecialchars($displayPosition) ?>">
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">หน่วยงาน/กลุ่มงาน</label>
                            <input class="form-control" type="text" name="department" value="<?= htmlspecialchars($displayDepartment) ?>">
                        </div>
                        <div class="col-12">
                            <hr>
                            <h5 class="fw-bold mb-3">🔐 เปลี่ยนรหัสผ่าน</h5>
                            <div class="row g-3">
                                <div class="col-12 col-md-4">
                                    <label class="form-label">รหัสผ่านปัจจุบัน</label>
                                    <input class="form-control" type="password" name="current_password" autocomplete="current-password">
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label">รหัสผ่านใหม่</label>
                                    <input class="form-control" type="password" name="new_password" autocomplete="new-password">
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label">ยืนยันรหัสผ่านใหม่</label>
                                    <input class="form-control" type="password" name="confirm_password" autocomplete="new-password">
                                </div>
                            </div>
                            <div class="form-text mt-2">กรอกเฉพาะเมื่อต้องการเปลี่ยนรหัสผ่าน หากไม่ต้องการเปลี่ยนให้ปล่อยว่างไว้</div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button class="btn btn-primary" type="submit">บันทึกข้อมูล</button>
                        <a class="btn btn-outline-secondary" href="index.php">ยกเลิก</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>
</div>
</body>
</html>
