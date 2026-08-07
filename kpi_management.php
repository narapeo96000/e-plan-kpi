<?php
require_once __DIR__ . '/db.php';

/**
 * Global vars from `db.php` for static analyzers
 * @var mysqli $conn
 * @var string $office_name
 * @var string $fiscal_year
 */

requirePlanOrAdmin();

$currentFiscalYear = !empty($fiscal_year) ? $fiscal_year : (string)(date('Y') + 543);
$action = isset($_GET['action']) ? $_GET['action'] : '';
$kpiId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_kpi'])) {
        $kpiId = isset($_POST['kpi_id']) ? intval($_POST['kpi_id']) : 0;
        $fiscalYear = trim(isset($_POST['fiscal_year']) ? $_POST['fiscal_year'] : '');
        $kpiName = trim(isset($_POST['kpi_name']) ? $_POST['kpi_name'] : '');
        $targetPercent = trim(isset($_POST['target_percent']) ? $_POST['target_percent'] : '0');
        $successIndicator = trim(isset($_POST['success_indicator']) ? $_POST['success_indicator'] : '');
        $scopeType = trim(isset($_POST['scope_type']) ? $_POST['scope_type'] : 'province');
        $status = trim(isset($_POST['status']) ? $_POST['status'] : 'active');

        if (empty($fiscalYear) || empty($kpiName) || !is_numeric($targetPercent)) {
            $error = 'กรุณากรอกข้อมูลให้ครบถ้วน (ปีงบประมาณ, ชื่อ KPI, ค่าเป้าหมายร้อยละ)';
        } elseif ($targetPercent < 0 || $targetPercent > 100) {
            $error = 'ค่าเป้าหมายร้อยละต้องอยู่ระหว่าง 0-100';
        } else {
            $escYear = $conn->real_escape_string($fiscalYear);
            $escName = $conn->real_escape_string($kpiName);
            $escIndicator = $conn->real_escape_string($successIndicator);
            $escScope = $conn->real_escape_string($scopeType);
            $escStatus = $conn->real_escape_string($status);
            $escTarget = $conn->real_escape_string($targetPercent);
            $escUser = $conn->real_escape_string(currentUsername());

            if ($kpiId > 0) {
                $conn->query("UPDATE kpi_definitions SET fiscal_year = '$escYear', kpi_name = '$escName', target_percent = '$escTarget', success_indicator = '$escIndicator', scope_type = '$escScope', status = '$escStatus', updated_by = '$escUser' WHERE id = $kpiId");
                $success = 'บันทึกตัวชี้วัด KPI เรียบร้อยแล้ว';
                logfile($conn, 'แก้ไข', 'kpi_definitions', $kpiId, array(
                    'fiscal_year' => $fiscalYear,
                    'kpi_name' => $kpiName,
                    'target_percent' => $targetPercent,
                    'scope_type' => $scopeType,
                    'status' => $status,
                ));
            } else {
                $conn->query("INSERT INTO kpi_definitions (fiscal_year, kpi_name, target_percent, success_indicator, scope_type, status, created_by) VALUES ('$escYear', '$escName', '$escTarget', '$escIndicator', '$escScope', '$escStatus', '$escUser')");
                $newKpiId = (int)$conn->insert_id;
                $success = 'เพิ่มตัวชี้วัด KPI เรียบร้อยแล้ว';
                logfile($conn, 'เพิ่ม', 'kpi_definitions', $newKpiId, array(
                    'fiscal_year' => $fiscalYear,
                    'kpi_name' => $kpiName,
                    'target_percent' => $targetPercent,
                    'scope_type' => $scopeType,
                    'status' => $status,
                ));
            }
            header('Location: kpi_management.php');
            exit;
        }
    }
}

if ($action === 'toggle_status' && $kpiId > 0) {
    $kpiRes = $conn->query("SELECT kpi_name, status FROM kpi_definitions WHERE id = $kpiId LIMIT 1");
    if ($kpiRes && $row = $kpiRes->fetch_assoc()) {
        $newStatus = $row['status'] === 'active' ? 'inactive' : 'active';
        $conn->query("UPDATE kpi_definitions SET status = '$newStatus', updated_by = '" . $conn->real_escape_string(currentUsername()) . "' WHERE id = $kpiId");
        logfile($conn, 'เปลี่ยนสถานะ', 'kpi_definitions', $kpiId, array(
            'kpi_name' => isset($row['kpi_name']) ? $row['kpi_name'] : '',
            'status' => $newStatus,
        ));
    }
    header('Location: kpi_management.php');
    exit;
}

$yearFilter = isset($_GET['year']) ? trim($_GET['year']) : $currentFiscalYear;
$kpies = array();
$kpiSql = "SELECT * FROM kpi_definitions";
if ($yearFilter !== '') {
    $kpiSql .= " WHERE fiscal_year = '" . $conn->real_escape_string($yearFilter) . "'";
}
$kpiSql .= " ORDER BY fiscal_year DESC, id ASC";
$kpiRes = $conn->query($kpiSql);
if ($kpiRes) {
    while ($row = $kpiRes->fetch_assoc()) {
        $kpies[] = $row;
    }
}

$yearsList = array();
$yearRes = $conn->query("SELECT DISTINCT fiscal_year FROM kpi_definitions ORDER BY fiscal_year DESC");
if ($yearRes) {
    while ($yRow = $yearRes->fetch_assoc()) {
        $yearsList[] = $yRow['fiscal_year'];
    }
}
if (empty($yearsList)) {
    $yearsList[] = $currentFiscalYear;
}

$editingKpi = null;
if ($action === 'edit' && $kpiId > 0) {
    $kpiRes = $conn->query("SELECT * FROM kpi_definitions WHERE id = $kpiId LIMIT 1");
    if ($kpiRes) {
        $editingKpi = $kpiRes->fetch_assoc();
    }
}

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการตัวชี้วัด KPI | <?= htmlspecialchars($office_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/style.php'; ?>
</head>
<body>
<?php $activePage = 'kpi_management'; include __DIR__ . '/menu.php'; ?>
        <div class="container-fluid">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <div class="text-uppercase section-title mb-2">📐 KPI ร่วม</div>
                            <h1 class="h3 fw-bold mb-2">กำหนดตัวชี้วัดความสำเร็จ (KPI)</h1>
                            <p class="text-muted mb-0">กำหนด KPI ร่วมสำหรับจังหวัด/หน่วยงาน โดย <?= htmlspecialchars(roleLabel(currentRole())) ?> ระดับ <?= htmlspecialchars($currentFiscalYear) ?></p>
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
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                <h2 class="h5 fw-bold mb-0">รายการตัวชี้วัด</h2>
                                <form class="d-flex align-items-center gap-2" method="get">
                                    <label class="form-label mb-0 small text-muted">ปีงบประมาณ</label>
                                    <select class="form-select form-select-sm w-auto" name="year" onchange="this.form.submit()">
                                        <option value="">ทั้งหมด</option>
                                        <?php foreach ($yearsList as $y): ?>
                                            <option value="<?= htmlspecialchars($y) ?>" <?= $yearFilter === $y ? 'selected' : '' ?>><?= htmlspecialchars($y) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </div>
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>ปี<br>งบประมาณ</th>
                                            <th>ชื่อตัวชี้วัด</th>
                                            <th>ค่าเป้าหมาย<br>(ร้อยละ)</th>
                                            <th>ระดับ</th>
                                            <th>สถานะ</th>
                                            <th>การจัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($kpies)): ?>
                                            <tr><td colspan="7" class="text-muted text-center py-4">ยังไม่มีตัวชี้วัด KPI ในปีงบประมาณนี้</td></tr>
                                        <?php endif; ?>
                                        <?php foreach ($kpies as $index => $kpi): ?>
                                            <tr>
                                                <td><?= $index + 1 ?></td>
                                                <td><?= htmlspecialchars($kpi['fiscal_year']) ?></td>
                                                <td>
                                                    <div class="fw-semibold"><?= htmlspecialchars($kpi['kpi_name']) ?></div>
                                                    <?php if (!empty($kpi['success_indicator'])): ?>
                                                        <div class="small text-muted" style="max-width: 320px;"><?= htmlspecialchars($kpi['success_indicator']) ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="fw-bold text-primary"><?= htmlspecialchars(rtrim(rtrim(number_format((float)$kpi['target_percent'], 2), '0'), '.')) ?>%</td>
                                                <td><?= $kpi['scope_type'] === 'province' ? 'จังหวัด' : 'หน่วยงาน' ?></td>
                                                <td>
                                                    <span class="badge <?= $kpi['status'] === 'active' ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' ?>">
                                                        <?= $kpi['status'] === 'active' ? 'ใช้งาน' : 'ระงับ' ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <a class="btn btn-sm btn-outline-primary" href="kpi_management.php?action=edit&id=<?= (int)$kpi['id'] ?>">แก้ไข</a>
                                                    <a class="btn btn-sm btn-outline-secondary" href="kpi_management.php?action=toggle_status&id=<?= (int)$kpi['id'] ?>">
                                                        <?= $kpi['status'] === 'active' ? 'ระงับ' : 'เปิดใช้งาน' ?>
                                                    </a>
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
                            <h2 class="h5 fw-bold mb-3"><?= $editingKpi ? 'แก้ไขตัวชี้วัด' : 'เพิ่มตัวชี้วัดใหม่' ?></h2>
                            <form method="post">
                                <input type="hidden" name="kpi_id" value="<?= $editingKpi ? intval($editingKpi['id']) : 0 ?>">
                                <div class="mb-3">
                                    <label class="form-label">ปีงบประมาณ <span class="text-danger">*</span></label>
                                    <input class="form-control" type="text" maxlength="4" name="fiscal_year" value="<?= htmlspecialchars(isset($editingKpi['fiscal_year']) ? $editingKpi['fiscal_year'] : $currentFiscalYear) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">ชื่อตัวชี้วัด <span class="text-danger">*</span></label>
                                    <input class="form-control" type="text" name="kpi_name" value="<?= htmlspecialchars(isset($editingKpi['kpi_name']) ? $editingKpi['kpi_name'] : '') ?>" placeholder="เช่น ร้อยละโครงการที่บรรลุเป้าหมาย" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">ค่าเป้าหมาย (ร้อยละ) <span class="text-danger">*</span></label>
                                    <input class="form-control" type="number" step="0.01" min="0" max="100" name="target_percent" value="<?= htmlspecialchars(isset($editingKpi['target_percent']) ? $editingKpi['target_percent'] : '') ?>" placeholder="เช่น 80" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">ตัวชี้วัดความสำเร็จ</label>
                                    <textarea class="form-control" rows="3" name="success_indicator" placeholder="ระบุหลักฐาน/ตัวชี้วัดความสำเร็จที่ใช้ประเมิน"><?= htmlspecialchars(isset($editingKpi['success_indicator']) ? $editingKpi['success_indicator'] : '') ?></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">ระดับการใช้งาน</label>
                                    <select class="form-select" name="scope_type">
                                        <option value="province" <?= isset($editingKpi['scope_type']) && $editingKpi['scope_type'] === 'province' ? 'selected' : '' ?>>จังหวัด (ทุกหน่วยงาน)</option>
                                        <option value="agency" <?= isset($editingKpi['scope_type']) && $editingKpi['scope_type'] === 'agency' ? 'selected' : '' ?>>หน่วยงาน</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">สถานะ</label>
                                    <select class="form-select" name="status">
                                        <option value="active" <?= isset($editingKpi['status']) && $editingKpi['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="inactive" <?= isset($editingKpi['status']) && $editingKpi['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    </select>
                                </div>
                                <button class="btn btn-primary w-100" type="submit" name="save_kpi">บันทึกข้อมูล</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
