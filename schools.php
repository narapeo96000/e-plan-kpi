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

requireAdmin();

function resolveAgencyTable($conn) {
    foreach (array('agencies', 'school', 'schools') as $table) {
        $res = $conn->query("SHOW TABLES LIKE '" . $conn->real_escape_string($table) . "'");
        if ($res && $res->num_rows > 0) {
            if ($table !== 'agencies') {
                $conn->query("RENAME TABLE $table TO agencies");
            }
            return 'agencies';
        }
    }

    $conn->query("CREATE TABLE IF NOT EXISTS agencies (
        id INT(11) NOT NULL AUTO_INCREMENT,
        agency_code VARCHAR(50) NOT NULL,
        password VARCHAR(255) NOT NULL,
        agency_name VARCHAR(255) NOT NULL,
        department VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY agency_code (agency_code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    return 'agencies';
}

function ensureAgencyTable($conn, $tableName) {
    $createSql = "CREATE TABLE IF NOT EXISTS agencies (
            id INT(11) NOT NULL AUTO_INCREMENT,
            agency_code VARCHAR(50) NOT NULL,
            password VARCHAR(255) NOT NULL,
            agency_name VARCHAR(255) NOT NULL,
            department VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY agency_code (agency_code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($createSql);

    $cols = $conn->query("SHOW COLUMNS FROM $tableName");
    if ($cols) {
        $columns = array();
        while ($col = $cols->fetch_assoc()) {
            $columns[] = $col['Field'];
        }
        if (!in_array('agency_code', $columns, true) && in_array('school_id', $columns, true)) {
            $conn->query("ALTER TABLE $tableName CHANGE COLUMN school_id agency_code VARCHAR(50) NOT NULL");
        }
        if (!in_array('agency_name', $columns, true) && in_array('school_name', $columns, true)) {
            $conn->query("ALTER TABLE $tableName CHANGE COLUMN school_name agency_name VARCHAR(255) NOT NULL");
        }
        if (!in_array('agency_code', $columns, true)) {
            $conn->query("ALTER TABLE $tableName ADD COLUMN agency_code VARCHAR(50) NOT NULL AFTER id");
        }
        if (!in_array('agency_name', $columns, true)) {
            $conn->query("ALTER TABLE $tableName ADD COLUMN agency_name VARCHAR(255) NOT NULL AFTER agency_code");
        }
        if (!in_array('password', $columns, true)) {
            $conn->query("ALTER TABLE $tableName ADD COLUMN password VARCHAR(255) NOT NULL DEFAULT '' AFTER agency_code");
        }
        if (!in_array('department', $columns, true)) {
            $conn->query("ALTER TABLE $tableName ADD COLUMN department VARCHAR(255) DEFAULT NULL AFTER agency_name");
        }
        if (!in_array('sort_order', $columns, true)) {
            $conn->query("ALTER TABLE $tableName ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER department");
        }
        if (!in_array('created_at', $columns, true)) {
            $conn->query("ALTER TABLE $tableName ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP AFTER department");
        }
        if (!in_array('updated_at', $columns, true)) {
            $conn->query("ALTER TABLE $tableName ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
        }
    }
}

$tableName = resolveAgencyTable($conn);
ensureAgencyTable($conn, $tableName);

$action = isset($_GET['action']) ? $_GET['action'] : '';
$schoolId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck('schools.php');
    if (isset($_POST['save_school'])) {
        $agencyCodeInput = isset($_POST['agency_code']) ? trim($_POST['agency_code']) : '';
        $agencyName = isset($_POST['agency_name']) ? trim($_POST['agency_name']) : '';
        $department = isset($_POST['department']) ? trim($_POST['department']) : '';
        $sortOrder = isset($_POST['sort_order']) ? max(0, (int)$_POST['sort_order']) : 0;
        $password = isset($_POST['password']) ? trim($_POST['password']) : '';
        $editId = isset($_POST['agency_record_id']) ? intval($_POST['agency_record_id']) : 0;

        if ($agencyCodeInput === '' || $agencyName === '') {
            $error = 'กรุณากรอกรหัสหน่วยงานและชื่อหน่วยงานให้ครบถ้วน';
        } else {
            $checkSql = "SELECT id FROM $tableName WHERE agency_code = '" . $conn->real_escape_string($agencyCodeInput) . "'";
            if ($editId > 0) {
                $checkSql .= " AND id != $editId";
            }
            $checkSql .= " LIMIT 1";
            $checkRes = $conn->query($checkSql);
            if ($checkRes && $checkRes->num_rows > 0) {
                $error = 'รหัสหน่วยงานนี้ถูกใช้งานแล้ว';
            } else {
                if ($editId > 0) {
                    $updateFields = "agency_code = '" . $conn->real_escape_string($agencyCodeInput) . "', agency_name = '" . $conn->real_escape_string($agencyName) . "', department = '" . $conn->real_escape_string($department) . "', sort_order = $sortOrder";
                    if ($password !== '') {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $updateFields .= ", password = '" . $conn->real_escape_string($hash) . "'";
                    }
                    $conn->query("UPDATE $tableName SET $updateFields WHERE id = $editId");
                    $success = 'บันทึกข้อมูลหน่วยงานเรียบร้อยแล้ว';
                    logfile($conn, 'แก้ไข', 'schools', $editId, array(
                        'agency_code' => $agencyCodeInput,
                        'agency_name' => $agencyName,
                        'department' => $department,
                        'sort_order' => $sortOrder,
                    ));
                    header('Location: schools.php');
                    exit;
                } else {
                    if ($password === '') {
                        $error = 'กรุณากรอกรหัสผ่านสำหรับหน่วยงานใหม่';
                    } else {
                        $hash = password_hash($password, PASSWORD_DEFAULT);
                        $conn->query("INSERT INTO $tableName (agency_code, password, agency_name, department, sort_order) VALUES ('" . $conn->real_escape_string($agencyCodeInput) . "', '" . $conn->real_escape_string($hash) . "', '" . $conn->real_escape_string($agencyName) . "', '" . $conn->real_escape_string($department) . "', $sortOrder)");
                        $newSchoolId = (int)$conn->insert_id;
                        $success = 'เพิ่มหน่วยงานเรียบร้อยแล้ว';
                        logfile($conn, 'เพิ่ม', 'schools', $newSchoolId, array(
                            'agency_code' => $agencyCodeInput,
                            'agency_name' => $agencyName,
                            'department' => $department,
                            'sort_order' => $sortOrder,
                        ));
                        header('Location: schools.php');
                        exit;
                    }
                }
            }
        }
    }
}

if ($action === 'reset' && $schoolId > 0 && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $newPassword = trim(isset($_POST['new_password']) ? $_POST['new_password'] : '');
    if ($newPassword === '') {
        $error = 'กรุณากรอกรหัสผ่านใหม่';
    } else {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $conn->query("UPDATE $tableName SET password = '" . $conn->real_escape_string($hash) . "' WHERE id = $schoolId");
        $success = 'รีเซ็ตรหัสผ่านเรียบร้อยแล้ว';
        logfile($conn, 'รีเซ็ตรหัสผ่าน', 'schools', $schoolId);
        header('Location: schools.php');
        exit;
    }
}

$schools = array();
$result = $conn->query("SELECT * FROM $tableName ORDER BY sort_order ASC, agency_name ASC, id ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $schools[] = $row;
    }
}

$editingSchool = null;
if ($action === 'edit' && $schoolId > 0) {
    $schoolRes = $conn->query("SELECT * FROM $tableName WHERE id = $schoolId LIMIT 1");
    if ($schoolRes) {
        $editingSchool = $schoolRes->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการหน่วยงานทางการศึกษา | <?= htmlspecialchars($office_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/style.php'; ?>
</head>
<body>
<?php $activePage = 'schools'; include __DIR__ . '/menu.php'; ?>
    <div class="container-fluid">
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <div class="text-uppercase section-title mb-2">🏫 หน่วยงานทางการศึกษา</div>
                        <h1 class="h3 fw-bold mb-2">จัดการหน่วยงานทางการศึกษา</h1>
                        <p class="text-muted mb-0">ผู้ดูแลระบบสามารถเพิ่ม แก้ไข และรีเซ็ตรหัสผ่านหน่วยงานได้จากหน้านี้</p>
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

        <div class="row g-4">
            <div class="col-12 col-xl-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>ลำดับ</th>
                                        <th>รหัสหน่วยงาน</th>
                                        <th>ชื่อหน่วยงาน</th>
                                        <th>สังกัด</th>
                                        <th>จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($schools as $index => $school): ?>
                                        <tr>
                                            <td><?= $index + 1 ?></td>
                                            <td><?= (int)$school['sort_order'] ?></td>
                                            <td><?= htmlspecialchars($school['agency_code']) ?></td>
                                            <td><?= htmlspecialchars($school['agency_name']) ?></td>
                                            <td><?= htmlspecialchars($school['department'] ?: '-') ?></td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-2">
                                                    <a class="btn btn-sm btn-outline-primary" href="schools.php?action=edit&id=<?= (int)$school['id'] ?>">แก้ไข</a>
                                                </div>
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
                        <h2 class="h5 fw-bold mb-3"><?= $editingSchool ? 'แก้ไขหน่วยงาน' : 'เพิ่มหน่วยงานใหม่' ?></h2>
                        <form method="post">
                            <input type="hidden" name="agency_record_id" value="<?= $editingSchool ? (int)$editingSchool['id'] : 0 ?>">
                            <?= csrfField() ?>
                            <div class="mb-3">
                                <label class="form-label">รหัสหน่วยงาน</label>
                                <input class="form-control" type="text" name="agency_code" value="<?= htmlspecialchars($editingSchool ? $editingSchool['agency_code'] : '') ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">ชื่อหน่วยงาน</label>
                                <input class="form-control" type="text" name="agency_name" value="<?= htmlspecialchars($editingSchool ? $editingSchool['agency_name'] : '') ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">สังกัด</label>
                                <input class="form-control" type="text" name="department" value="<?= htmlspecialchars($editingSchool ? ($editingSchool['department'] ?: '') : '') ?>" placeholder="เช่น สพป.นธ.1, สพม.นราธิวาส">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">ลำดับแสดงผล</label>
                                <input class="form-control" type="number" min="0" step="1" name="sort_order" value="<?= htmlspecialchars($editingSchool ? (int)$editingSchool['sort_order'] : 0) ?>">
                                <div class="form-text">ใช้เรียงลำดับหน่วยงานบนหน้า Dashboard และรายงาน (น้อย = แสดงก่อน)</div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">รหัสผ่าน<?= $editingSchool ? ' (เว้นว่างหากไม่ต้องการเปลี่ยน)' : '' ?></label>
                                <input class="form-control" type="password" name="password" <?= $editingSchool ? '' : 'required' ?>>
                            </div>
                            <button class="btn btn-primary w-100" type="submit" name="save_school">บันทึกข้อมูล</button>
                        </form>
                    </div>
                </div>

                <?php if ($action === 'reset' && $schoolId > 0): ?>
                    <div class="card border-0 shadow-sm mt-4">
                        <div class="card-body">
                            <h2 class="h5 fw-bold mb-3">รีเซ็ตรหัสผ่าน</h2>
                            <form method="post" action="schools.php?action=reset&id=<?= $schoolId ?>">
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
