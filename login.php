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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim(isset($_POST['username']) ? $_POST['username'] : '');
    $password = trim(isset($_POST['password']) ? $_POST['password'] : '');
    $captchaInput = trim(isset($_POST['captcha']) ? $_POST['captcha'] : '');

    if (empty($username) || empty($password)) {
        $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } elseif (strtoupper($captchaInput) !== (isset($_SESSION['captcha_code']) ? $_SESSION['captcha_code'] : '')) {
        $error = 'Captcha ไม่ถูกต้อง';
    } else {
        $stmt = $conn->prepare("SELECT id, username, name, password, role, position, department, agency_id FROM users WHERE username = ? AND status = 'active'");
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user['password'])) {
            // Prevent session fixation: issue a fresh session id on successful login
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['position'] = $user['position'];
            $_SESSION['department'] = $user['department'];
            $_SESSION['agency_id'] = isset($user['agency_id']) ? $user['agency_id'] : null;
            $_SESSION['last_activity'] = time();
            header('Location: projects.php');
            exit;
        } else {
            $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
        }
    }
}

if (isset($_GET['timeout']) && empty($error)) {
    $error = 'เซสชันหมดอายุ กรุณาเข้าสู่ระบบใหม่';
}

$captcha = substr(strtoupper(md5(uniqid('', true))), 0, 5);
$_SESSION['captcha_code'] = $captcha;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ | <?= htmlspecialchars($office_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/style.php'; ?>
</head>
<body>
<div class="min-vh-100 d-flex align-items-center justify-content-center p-2 p-sm-3 page-bg">
    <div class="card border-0 shadow-lg rounded-4 login-card">
        <div class="p-4 p-sm-5 login-card-hero">
            <div class="fw-bold fs-4">🔐 เข้าสู่ระบบ</div>
            <div class="small opacity-90 mt-1">Performance reporting system</div>
        </div>
        <div class="card-body p-3 p-sm-4 p-lg-5">
            <div class="text-center mb-4">
                <div class="fw-bold fs-5 text-primary"><?= htmlspecialchars($office_name) ?></div>
                <div class="text-muted small">กรุณากรอกข้อมูลเพื่อเข้าสู่ระบบ</div>
            </div>
            <?php if (!empty($error)): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">ชื่อผู้ใช้</label>
                    <input class="form-control" type="text" name="username" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">รหัสผ่าน</label>
                    <input class="form-control" type="password" name="password" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Captcha</label>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-light text-dark px-3 py-2 fw-bold"><?= htmlspecialchars($captcha) ?></span>
                        <input class="form-control" type="text" name="captcha" required placeholder="ใส่ captcha">
                    </div>
                </div>
                <button class="btn btn-primary w-100" type="submit">เข้าสู่ระบบ</button>
            </form>
            <div class="text-center mt-3"><a href="index.php" class="text-decoration-none">กลับหน้าหลัก</a></div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
