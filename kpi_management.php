<?php
require_once __DIR__ . '/db.php';

/**
 * Global vars from `db.php` for static analyzers
 * @var mysqli $conn
 * @var string $office_name
 * @var string $fiscal_year
 */

requirePlanOrAdmin();

// AJAX endpoint สำหรับโหลดเป้าประสงค์ตามปี
if (isset($_GET['ajax']) && $_GET['ajax'] === 'objectives' && !empty($_GET['year'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $ajYear = $conn->real_escape_string(trim($_GET['year']));
    $out = array();
    $r = $conn->query("
        SELECT o.id, o.objective_name, si.issue_no
        FROM objectives o
        LEFT JOIN strategic_issues si ON si.id = o.strategy_id
        WHERE o.fiscal_year = '$ajYear' AND (o.status = 'active' OR o.status IS NULL)
        ORDER BY si.issue_no ASC, o.sort_order ASC, o.id ASC
    ");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $out[] = array(
                'id' => (int)$row['id'],
                'issue_no' => $row['issue_no'],
                'objective_name' => $row['objective_name'],
            );
        }
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

// AJAX endpoint สำหรับโหลดข้อมูล KPI รายตัว (สำหรับ popup แก้ไข)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'kpi' && !empty($_GET['id'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $kpiRowAjax = null;
    $kr = $conn->query("SELECT * FROM kpi_definitions WHERE id = " . intval($_GET['id']) . " LIMIT 1");
    if ($kr) $kpiRowAjax = $kr->fetch_assoc();
    echo json_encode($kpiRowAjax ? $kpiRowAjax : array(), JSON_UNESCAPED_UNICODE);
    exit;
}

$currentFiscalYear = !empty($fiscal_year) ? $fiscal_year : (string)(date('Y') + 543);
$action = isset($_GET['action']) ? $_GET['action'] : '';
$kpiId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck('kpi_management.php');
    if (isset($_POST['save_kpi'])) {
        $kpiId = isset($_POST['kpi_id']) ? intval($_POST['kpi_id']) : 0;
        $fiscalYear = trim(isset($_POST['fiscal_year']) ? $_POST['fiscal_year'] : '');
        $objectiveId = isset($_POST['objective_id']) ? intval($_POST['objective_id']) : 0;
        $kpiName = trim(isset($_POST['kpi_name']) ? $_POST['kpi_name'] : '');
        $targetPercent = trim(isset($_POST['target_percent']) ? $_POST['target_percent'] : '0');
        $successIndicator = trim(isset($_POST['success_indicator']) ? $_POST['success_indicator'] : '');
        $scopeType = trim(isset($_POST['scope_type']) ? $_POST['scope_type'] : 'province');
        $sortOrder = isset($_POST['sort_order']) ? intval($_POST['sort_order']) : 0;
        $status = trim(isset($_POST['status']) ? $_POST['status'] : 'active');

        if (empty($fiscalYear) || $objectiveId <= 0 || empty($kpiName) || !is_numeric($targetPercent)) {
            $error = 'กรุณากรอกข้อมูลให้ครบถ้วน (ปีงบประมาณ, เป้าประสงค์, ชื่อ KPI, ค่าเป้าหมายร้อยละ)';
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
                $conn->query("UPDATE kpi_definitions SET fiscal_year = '$escYear', objective_id = $objectiveId, kpi_name = '$escName', target_percent = '$escTarget', success_indicator = '$escIndicator', scope_type = '$escScope', sort_order = $sortOrder, status = '$escStatus', updated_by = '$escUser' WHERE id = $kpiId");
                $success = 'บันทึกตัวชี้วัด KPI เรียบร้อยแล้ว';
                logfile($conn, 'แก้ไข', 'kpi_definitions', $kpiId, array(
                    'fiscal_year' => $fiscalYear,
                    'objective_id' => $objectiveId,
                    'kpi_name' => $kpiName,
                    'target_percent' => $targetPercent,
                    'scope_type' => $scopeType,
                    'sort_order' => $sortOrder,
                    'status' => $status,
                ));
            } else {
                $conn->query("INSERT INTO kpi_definitions (fiscal_year, objective_id, kpi_name, target_percent, success_indicator, scope_type, sort_order, status, created_by) VALUES ('$escYear', $objectiveId, '$escName', '$escTarget', '$escIndicator', '$escScope', $sortOrder, '$escStatus', '$escUser')");
                $newKpiId = (int)$conn->insert_id;
                $success = 'เพิ่มตัวชี้วัด KPI เรียบร้อยแล้ว';
                logfile($conn, 'เพิ่ม', 'kpi_definitions', $newKpiId, array(
                    'fiscal_year' => $fiscalYear,
                    'objective_id' => $objectiveId,
                    'kpi_name' => $kpiName,
                    'target_percent' => $targetPercent,
                    'scope_type' => $scopeType,
                    'sort_order' => $sortOrder,
                    'status' => $status,
                ));
            }
            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(array('success' => true, 'message' => $success), JSON_UNESCAPED_UNICODE);
                exit;
            }
            header('Location: kpi_management.php');
            exit;
        }
    }
    if ($isAjax && !empty($error)) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array('success' => false, 'error' => $error), JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if ($action === 'toggle_status' && $kpiId > 0) {
    // CSRF: state-changing action must carry the token
    $gotToken = isset($_GET['csrf']) ? $_GET['csrf'] : '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $gotToken)) {
        setFlash('error', 'CSRF token ไม่ถูกต้อง กรุณาลองใหม่');
        header('Location: kpi_management.php');
        exit;
    }
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
$kpiSql = "SELECT k.*, o.objective_name, o.strategy_id, si.issue_no, si.issue_name AS strategy_name
           FROM kpi_definitions k
           LEFT JOIN objectives o ON o.id = k.objective_id
           LEFT JOIN strategic_issues si ON si.id = o.strategy_id";
if ($yearFilter !== '') {
    $kpiSql .= " WHERE k.fiscal_year = '" . $conn->real_escape_string($yearFilter) . "'";
}
$kpiSql .= " ORDER BY k.fiscal_year DESC, k.sort_order ASC, k.id ASC";
$kpiRes = $conn->query($kpiSql);
if ($kpiRes) {
    while ($row = $kpiRes->fetch_assoc()) {
        $kpies[] = $row;
    }
}

// เป้าประสงค์ตามปีที่เลือก (สำหรับ dropdown ในฟอร์ม)
$objectiveOptions = array();
$objRes = $conn->query("
    SELECT o.id, o.objective_name, o.sort_order, si.issue_no, si.issue_name AS strategy_name
    FROM objectives o
    LEFT JOIN strategic_issues si ON si.id = o.strategy_id
    WHERE o.fiscal_year = '" . $conn->real_escape_string($yearFilter) . "' AND (o.status = 'active' OR o.status IS NULL)
    ORDER BY si.issue_no ASC, o.sort_order ASC, o.id ASC
");
if ($objRes) {
    while ($row = $objRes->fetch_assoc()) {
        $objectiveOptions[] = $row;
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
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                <h2 class="h5 fw-bold mb-0">รายการตัวชี้วัด</h2>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <form class="d-flex align-items-center gap-2" method="get">
                                        <label class="form-label mb-0 small text-muted">ปีงบประมาณ</label>
                                        <select class="form-select form-select-sm w-auto" name="year" onchange="this.form.submit()">
                                            <option value="">ทั้งหมด</option>
                                            <?php foreach ($yearsList as $y): ?>
                                                <option value="<?= htmlspecialchars($y) ?>" <?= $yearFilter === $y ? 'selected' : '' ?>><?= htmlspecialchars($y) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="openKpiModal()">➕ เพิ่มตัวชี้วัด</button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>ลำดับ</th>
                                            <th>ปี<br>งบประมาณ</th>
                                            <th>ชื่อตัวชี้วัด</th>
                                            <th>เป้าประสงค์</th>
                                            <th>ค่าเป้าหมาย<br>(ร้อยละ)</th>
                                            <th>ระดับ</th>
                                            <th>สถานะ</th>
                                            <th>การจัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($kpies)): ?>
                                            <tr><td colspan="9" class="text-muted text-center py-4">ยังไม่มีตัวชี้วัด KPI ในปีงบประมาณนี้</td></tr>
                                        <?php endif; ?>
                                        <?php foreach ($kpies as $index => $kpi): ?>
                                            <tr>
                                                <td><?= $index + 1 ?></td>
                                                <td class="text-center"><?= intval($kpi['sort_order']) ?></td>
                                                <td><?= htmlspecialchars($kpi['fiscal_year']) ?></td>
                                                <td>
                                                    <div class="fw-semibold"><?= htmlspecialchars($kpi['kpi_name']) ?></div>
                                                    <?php if (!empty($kpi['success_indicator'])): ?>
                                                        <div class="small text-muted" style="max-width: 320px;"><?= htmlspecialchars($kpi['success_indicator']) ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($kpi['objective_name'])): ?>
                                                        <span class="badge bg-primary-subtle text-primary-emphasis">ยุทธศาสตร์ที่ <?= intval($kpi['issue_no']) ?></span>
                                                        <div class="small text-muted" style="max-width: 280px;"><?= htmlspecialchars($kpi['objective_name']) ?></div>
                                                    <?php else: ?>
                                                        <span class="text-muted small">— ไม่ระบุ —</span>
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
                                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="openKpiModal(<?= (int)$kpi['id'] ?>)">แก้ไข</button>
                                                    <a class="btn btn-sm btn-outline-secondary" href="kpi_management.php?action=toggle_status&id=<?= (int)$kpi['id'] ?>&csrf=<?= urlencode(csrfToken()) ?>">
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
            </div>
        </div>
    </main>
</div>

<!-- ===== Modal: เพิ่ม/แก้ไข KPI (popup) ===== -->
<div class="modal fade" id="kpiModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="kpiForm" onsubmit="return saveKpi(event)">
                <input type="hidden" name="kpi_id" id="kpiId" value="0">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="kpiModalTitle">➕ เพิ่มตัวชี้วัด KPI</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 d-none" id="kpiModalError"></div>
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label">ปีงบประมาณ <span class="text-danger">*</span></label>
                            <input class="form-control" type="text" maxlength="4" name="fiscal_year" id="kpiFiscalYear" value="<?= htmlspecialchars($currentFiscalYear) ?>" required>
                        </div>
                        <div class="col-12 col-md-8">
                            <label class="form-label">ลำดับการแสดงผล</label>
                            <input class="form-control" type="number" step="1" min="0" name="sort_order" id="kpiSortOrder" value="0" placeholder="เช่น 1, 2, 3">
                        </div>
                        <div class="col-12">
                            <label class="form-label">เป้าประสงค์ <span class="text-danger">*</span> <span class="text-muted small">(เลือก 1 เป้าประสงค์)</span></label>
                            <select class="form-select" name="objective_id" id="kpiObjective" required>
                                <option value="">-- เลือกเป้าประสงค์ --</option>
                            </select>
                            <div class="form-text text-warning d-none" id="kpiNoObjective">ยังไม่มีเป้าประสงค์ในปีนี้ — เพิ่มก่อนผ่านเมนู "เป้าประสงค์"</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">ชื่อตัวชี้วัด <span class="text-danger">*</span></label>
                            <input class="form-control" type="text" name="kpi_name" id="kpiName" placeholder="เช่น ร้อยละโครงการที่บรรลุเป้าหมาย" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">ค่าเป้าหมาย (ร้อยละ) <span class="text-danger">*</span></label>
                            <input class="form-control" type="number" step="0.01" min="0" max="100" name="target_percent" id="kpiTargetPercent" placeholder="เช่น 80" required>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">ระดับการใช้งาน</label>
                            <select class="form-select" name="scope_type" id="kpiScopeType">
                                <option value="province">จังหวัด (ทุกหน่วยงาน)</option>
                                <option value="agency">หน่วยงาน</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">สถานะ</label>
                            <select class="form-select" name="status" id="kpiStatus">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">ตัวชี้วัดความสำเร็จ</label>
                            <textarea class="form-control" rows="3" name="success_indicator" id="kpiSuccessIndicator" placeholder="ระบุหลักฐาน/ตัวชี้วัดความสำเร็จที่ใช้ประเมิน"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary" id="kpiSaveBtn">💾 บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ===== Modal เพิ่ม/แก้ไข KPI (popup) =====
var kpiModalEl = document.getElementById('kpiModal');

function loadObjectives(year, selectedId) {
    const sel = document.getElementById('kpiObjective');
    const noObj = document.getElementById('kpiNoObjective');
    if (!/^\d{4}$/.test(year)) {
        sel.innerHTML = '<option value="">-- เลือกเป้าประสงค์ --</option>';
        return;
    }
    fetch('kpi_management.php?year=' + encodeURIComponent(year) + '&ajax=objectives')
        .then(function (r) { return r.json(); })
        .then(function (list) {
            sel.innerHTML = '<option value="">-- เลือกเป้าประสงค์ --</option>';
            list.forEach(function (o) {
                const opt = document.createElement('option');
                opt.value = o.id;
                opt.textContent = '[ยุทธศาสตร์ที่ ' + o.issue_no + '] ' + o.objective_name;
                if (selectedId && String(o.id) === String(selectedId)) opt.selected = true;
                sel.appendChild(opt);
            });
            noObj.classList.toggle('d-none', list.length > 0);
        })
        .catch(function () {});
}

function openKpiModal(id) {
    const form = document.getElementById('kpiForm');
    form.reset();
    document.getElementById('kpiId').value = id || 0;
    document.getElementById('kpiModalError').classList.add('d-none');
    document.getElementById('kpiSaveBtn').disabled = false;
    if (id) {
        document.getElementById('kpiModalTitle').textContent = '✏️ แก้ไขตัวชี้วัด KPI';
        fetch('kpi_management.php?ajax=kpi&id=' + encodeURIComponent(id))
            .then(function (r) { return r.json(); })
            .then(function (k) {
                if (!k || !k.id) { document.getElementById('kpiModalError').textContent = 'ไม่พบข้อมูลตัวชี้วัด'; document.getElementById('kpiModalError').classList.remove('d-none'); return; }
                document.getElementById('kpiFiscalYear').value = k.fiscal_year || '';
                document.getElementById('kpiSortOrder').value = k.sort_order || 0;
                document.getElementById('kpiName').value = k.kpi_name || '';
                document.getElementById('kpiTargetPercent').value = k.target_percent || '';
                document.getElementById('kpiScopeType').value = k.scope_type || 'province';
                document.getElementById('kpiStatus').value = k.status || 'active';
                document.getElementById('kpiSuccessIndicator').value = k.success_indicator || '';
                loadObjectives(k.fiscal_year, k.objective_id);
            })
            .catch(function () {
                document.getElementById('kpiModalError').textContent = 'โหลดข้อมูลไม่สำเร็จ';
                document.getElementById('kpiModalError').classList.remove('d-none');
            });
    } else {
        document.getElementById('kpiModalTitle').textContent = '➕ เพิ่มตัวชี้วัด KPI';
        loadObjectives(document.getElementById('kpiFiscalYear').value, null);
    }
    var modal = bootstrap.Modal.getOrCreateInstance(kpiModalEl);
    modal.show();
}

function saveKpi(event) {
    event.preventDefault();
    const form = document.getElementById('kpiForm');
    const errBox = document.getElementById('kpiModalError');
    const btn = document.getElementById('kpiSaveBtn');
    errBox.classList.add('d-none');
    btn.disabled = true;
    const fd = new FormData(form);
    fd.append('save_kpi', '1');
    fetch('kpi_management.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
    })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res && res.success) {
                bootstrap.Modal.getInstance(kpiModalEl).hide();
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

document.getElementById('kpiFiscalYear').addEventListener('input', function () {
    loadObjectives(this.value.trim(), null);
});
</script>
</body>
</html>
