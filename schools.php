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

// AJAX endpoint สำหรับโหลดข้อมูลหน่วยงานรายตัว (สำหรับ popup แก้ไข)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'school' && !empty($_GET['id'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $srow = null;
    $sr = $conn->query("SELECT id, agency_code, agency_name, department, sort_order FROM $tableName WHERE id = " . intval($_GET['id']) . " LIMIT 1");
    if ($sr) $srow = $sr->fetch_assoc();
    echo json_encode($srow ? $srow : array(), JSON_UNESCAPED_UNICODE);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$schoolId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
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
                    }
                }
            }
        }
        if ($isAjax) {
            header('Content-Type: application/json; charset=UTF-8');
            if (!empty($error)) {
                echo json_encode(array('success' => false, 'error' => $error), JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(array('success' => true, 'message' => $success), JSON_UNESCAPED_UNICODE);
            }
            exit;
        }
        if (!empty($error)) {
            // non-AJAX: แสดง error ด้านล่าง
        } else {
            header('Location: schools.php');
            exit;
        }
    } elseif (isset($_POST['reset_password'])) {
        $delId = isset($_POST['agency_record_id']) ? intval($_POST['agency_record_id']) : 0;
        $newPassword = trim(isset($_POST['new_password']) ? $_POST['new_password'] : '');
        if ($delId <= 0 || $newPassword === '') {
            $error = 'กรุณากรอกรหัสผ่านใหม่';
        } else {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $conn->query("UPDATE $tableName SET password = '" . $conn->real_escape_string($hash) . "' WHERE id = $delId");
            $success = 'รีเซ็ตรหัสผ่านเรียบร้อยแล้ว';
            logfile($conn, 'รีเซ็ตรหัสผ่าน', 'schools', $delId);
        }
        if ($isAjax) {
            header('Content-Type: application/json; charset=UTF-8');
            if (!empty($error)) {
                echo json_encode(array('success' => false, 'error' => $error), JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(array('success' => true, 'message' => $success), JSON_UNESCAPED_UNICODE);
            }
            exit;
        }
        header('Location: schools.php');
        exit;
    } elseif (isset($_POST['delete_school'])) {
        $delId = isset($_POST['agency_record_id']) ? intval($_POST['agency_record_id']) : 0;
        if ($delId > 0) {
            $delRes = $conn->query("SELECT id, agency_code, agency_name FROM $tableName WHERE id = $delId LIMIT 1");
            if ($delRes && $delRes->num_rows > 0) {
                $delRow = $delRes->fetch_assoc();
                // ลบหน่วยงาน (FK ตั้งค่า SET NULL ไว้ -> โครงการ/ผู้ใช้ที่อ้างอิงจะไม่ถูกลบ เพียงแต่ไม่มีหน่วยงาน)
                $conn->query("DELETE FROM $tableName WHERE id = $delId");
                logfile($conn, 'ลบ', 'schools', $delId, array(
                    'agency_code' => $delRow['agency_code'],
                    'agency_name' => $delRow['agency_name'],
                ));
                $success = 'ลบหน่วยงานเรียบร้อยแล้ว (โครงการที่อ้างอิงคงอยู่ แต่ไม่มีหน่วยงานกำกับ)';
                header('Location: schools.php');
                exit;
            } else {
                $error = 'ไม่พบหน่วยงานที่ต้องการลบ';
            }
        }
    }
}

$schools = array();
$result = $conn->query("SELECT * FROM $tableName ORDER BY sort_order ASC, agency_name ASC, id ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $schools[] = $row;
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
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                            <h2 class="h5 fw-bold mb-0">รายการหน่วยงาน</h2>
                            <button type="button" class="btn btn-primary btn-sm" onclick="openSchoolModal()">➕ เพิ่มหน่วยงาน</button>
                        </div>
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
                                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="openSchoolModal(<?= (int)$school['id'] ?>)">แก้ไข</button>
                                                    <button type="button" class="btn btn-sm btn-outline-warning" onclick="openResetModal(<?= (int)$school['id'] ?>)">รีเซ็ตพาส</button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                                        data-bs-toggle="modal" data-bs-target="#deleteAgencyModal"
                                                        data-id="<?= (int)$school['id'] ?>"
                                                        data-name="<?= htmlspecialchars($school['agency_name']) ?>">ลบ</button>
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
        </div>
    </div>
</main>
</div>

<!-- ===== Modal: เพิ่ม/แก้ไขหน่วยงาน (popup) ===== -->
<div class="modal fade" id="schoolModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="schoolForm" onsubmit="return saveSchool(event)">
                <input type="hidden" name="agency_record_id" id="schoolId" value="0">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="schoolModalTitle">➕ เพิ่มหน่วยงาน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 d-none" id="schoolModalError"></div>
                    <div class="mb-3">
                        <label class="form-label">รหัสหน่วยงาน</label>
                        <input class="form-control" type="text" name="agency_code" id="schoolCode" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ชื่อหน่วยงาน</label>
                        <input class="form-control" type="text" name="agency_name" id="schoolName" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">สังกัด</label>
                        <input class="form-control" type="text" name="department" id="schoolDept" placeholder="เช่น สพป.นธ.1, สพม.นราธิวาส">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ลำดับแสดงผล</label>
                        <input class="form-control" type="number" min="0" step="1" name="sort_order" id="schoolSort" value="0">
                        <div class="form-text">ใช้เรียงลำดับหน่วยงานบนหน้า Dashboard และรายงาน (น้อย = แสดงก่อน)</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" id="schoolPassLabel">รหัสผ่าน</label>
                        <input class="form-control" type="password" name="password" id="schoolPassword" autocomplete="new-password">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary" id="schoolSaveBtn">💾 บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== Modal: รีเซ็ตรหัสผ่าน (popup) ===== -->
<div class="modal fade" id="resetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="resetForm" onsubmit="return saveResetPassword(event)">
                <input type="hidden" name="agency_record_id" id="resetId" value="0">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">🔑 รีเซ็ตรหัสผ่าน</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 d-none" id="resetModalError"></div>
                    <div class="mb-3">
                        <label class="form-label">รหัสผ่านใหม่</label>
                        <input class="form-control" type="password" name="new_password" id="resetPassword" autocomplete="new-password" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-warning" id="resetSaveBtn">รีเซ็ต</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteAgencyModal" tabindex="-1" aria-labelledby="deleteAgencyModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="deleteAgencyModalLabel">ยืนยันการลบหน่วยงาน</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
            </div>
            <form method="post" action="schools.php">
                <input type="hidden" name="agency_record_id" id="deleteAgencyId" value="">
                <?= csrfField() ?>
                <div class="modal-body">
                    <p class="mb-2">ต้องการลบหน่วยงานนี้ใช่หรือไม่?</p>
                    <div class="fw-bold text-danger fs-5 mb-3" id="deleteAgencyName"></div>
                    <div class="alert alert-warning mb-0" role="alert">
                        ⚠️ หมายเหตุ: โครงการที่ผูกอยู่กับหน่วยงานนี้จะ<b>ยังคงอยู่</b> แต่จะไม่มีหน่วยงานกำกับ (ไม่ถูกลบ)
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" name="delete_school" class="btn btn-danger">ยืนยันการลบ</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    var modal = document.getElementById('deleteAgencyModal');
    document.querySelectorAll('[data-bs-target="#deleteAgencyModal"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('deleteAgencyId').value = btn.getAttribute('data-id');
            document.getElementById('deleteAgencyName').textContent = btn.getAttribute('data-name');
        });
    });
})();

// ===== Modal เพิ่ม/แก้ไขหน่วยงาน (popup) =====
var schoolModalEl = document.getElementById('schoolModal');

function openSchoolModal(id) {
    const form = document.getElementById('schoolForm');
    form.reset();
    document.getElementById('schoolId').value = id || 0;
    document.getElementById('schoolModalError').classList.add('d-none');
    document.getElementById('schoolSaveBtn').disabled = false;
    if (id) {
        document.getElementById('schoolModalTitle').textContent = '✏️ แก้ไขหน่วยงาน';
        document.getElementById('schoolPassLabel').textContent = 'รหัสผ่าน (เว้นว่างหากไม่ต้องการเปลี่ยน)';
        fetch('schools.php?ajax=school&id=' + encodeURIComponent(id))
            .then(function (r) { return r.json(); })
            .then(function (s) {
                if (!s || !s.id) { document.getElementById('schoolModalError').textContent = 'ไม่พบข้อมูลหน่วยงาน'; document.getElementById('schoolModalError').classList.remove('d-none'); return; }
                document.getElementById('schoolCode').value = s.agency_code || '';
                document.getElementById('schoolName').value = s.agency_name || '';
                document.getElementById('schoolDept').value = s.department || '';
                document.getElementById('schoolSort').value = s.sort_order || 0;
            })
            .catch(function () {
                document.getElementById('schoolModalError').textContent = 'โหลดข้อมูลไม่สำเร็จ';
                document.getElementById('schoolModalError').classList.remove('d-none');
            });
    } else {
        document.getElementById('schoolModalTitle').textContent = '➕ เพิ่มหน่วยงาน';
        document.getElementById('schoolPassLabel').textContent = 'รหัสผ่าน';
        document.getElementById('schoolSort').value = '0';
    }
    var modal = bootstrap.Modal.getOrCreateInstance(schoolModalEl);
    modal.show();
}

function saveSchool(event) {
    event.preventDefault();
    const form = document.getElementById('schoolForm');
    const errBox = document.getElementById('schoolModalError');
    const btn = document.getElementById('schoolSaveBtn');
    errBox.classList.add('d-none');
    btn.disabled = true;
    const fd = new FormData(form);
    fd.append('save_school', '1');
    fetch('schools.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
    })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res && res.success) {
                bootstrap.Modal.getInstance(schoolModalEl).hide();
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

// ===== Modal รีเซ็ตรหัสผ่าน (popup) =====
var resetModalEl = document.getElementById('resetModal');

function openResetModal(id) {
    document.getElementById('resetId').value = id || 0;
    document.getElementById('resetPassword').value = '';
    document.getElementById('resetModalError').classList.add('d-none');
    document.getElementById('resetSaveBtn').disabled = false;
    var modal = bootstrap.Modal.getOrCreateInstance(resetModalEl);
    modal.show();
}

function saveResetPassword(event) {
    event.preventDefault();
    const form = document.getElementById('resetForm');
    const errBox = document.getElementById('resetModalError');
    const btn = document.getElementById('resetSaveBtn');
    errBox.classList.add('d-none');
    btn.disabled = true;
    const fd = new FormData(form);
    fd.append('reset_password', '1');
    fetch('schools.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
    })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res && res.success) {
                bootstrap.Modal.getInstance(resetModalEl).hide();
                location.reload();
            } else {
                errBox.textContent = (res && res.error) ? res.error : 'รีเซ็ตรหัสผ่านไม่สำเร็จ';
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
