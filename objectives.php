<?php
require_once __DIR__ . '/db.php';

/**
 * Global vars from `db.php` for static analyzers
 * @var mysqli $conn
 * @var string $office_name
 * @var string $fiscal_year
 */

requirePlanOrAdmin();

// AJAX endpoint สำหรับโหลดข้อมูลเป้าประสงค์รายตัว (สำหรับ popup แก้ไข)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'objective' && !empty($_GET['id'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $orow = null;
    $orr = $conn->query("SELECT o.*, si.issue_no, si.issue_name FROM objectives o LEFT JOIN strategic_issues si ON si.id = o.strategy_id WHERE o.id = " . intval($_GET['id']) . " LIMIT 1");
    if ($orr) $orow = $orr->fetch_assoc();
    echo json_encode($orow ? $orow : array(), JSON_UNESCAPED_UNICODE);
    exit;
}

// AJAX endpoint สำหรับโหลดยุทธศาสตร์ตามปี
if (isset($_GET['ajax']) && $_GET['ajax'] === 'strategies' && !empty($_GET['year'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $ajYear = $conn->real_escape_string(trim($_GET['year']));
    $out = array();
    $r = $conn->query("SELECT id, issue_no, issue_name FROM strategic_issues WHERE fiscal_year = '$ajYear' ORDER BY issue_no ASC");
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $out[] = array('id' => (int)$row['id'], 'issue_no' => $row['issue_no'], 'issue_name' => $row['issue_name']);
        }
    }
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

$currentFiscalYear = !empty($fiscal_year) ? $fiscal_year : (string)(date('Y') + 543);
$yearFilter = isset($_GET['year']) ? trim($_GET['year']) : $currentFiscalYear;
$isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfCheck('objectives.php');
    if (isset($_POST['save_objective'])) {
        $objId = isset($_POST['objective_id']) ? intval($_POST['objective_id']) : 0;
        $fiscalYear = trim(isset($_POST['fiscal_year']) ? $_POST['fiscal_year'] : '');
        $strategyId = intval(isset($_POST['strategy_id']) ? $_POST['strategy_id'] : 0);
        $objectiveName = trim(isset($_POST['objective_name']) ? $_POST['objective_name'] : '');
        $sortOrder = intval(isset($_POST['sort_order']) ? $_POST['sort_order'] : 0);
        $status = trim(isset($_POST['status']) ? $_POST['status'] : 'active');

        if (empty($fiscalYear) || $strategyId <= 0 || empty($objectiveName)) {
            $error = 'กรุณากรอกข้อมูลให้ครบถ้วน (ปีงบประมาณ, ยุทธศาสตร์, ชื่อเป้าประสงค์)';
        } else {
            $escYear = $conn->real_escape_string($fiscalYear);
            $escName = $conn->real_escape_string($objectiveName);
            $escStatus = $conn->real_escape_string($status);

            if ($objId > 0) {
                $conn->query("UPDATE objectives SET fiscal_year = '$escYear', strategy_id = $strategyId, objective_name = '$escName', sort_order = $sortOrder, status = '$escStatus' WHERE id = $objId");
                $success = 'แก้ไขเป้าประสงค์เรียบร้อยแล้ว';
                logfile($conn, 'แก้ไข', 'objectives', $objId, array(
                    'fiscal_year' => $fiscalYear,
                    'strategy_id' => $strategyId,
                    'objective_name' => $objectiveName,
                ));
            } else {
                $conn->query("INSERT INTO objectives (fiscal_year, strategy_id, objective_name, sort_order, status) VALUES ('$escYear', $strategyId, '$escName', $sortOrder, '$escStatus')");
                $newObjId = (int)$conn->insert_id;
                $success = 'เพิ่มเป้าประสงค์เรียบร้อยแล้ว';
                logfile($conn, 'เพิ่ม', 'objectives', $newObjId, array(
                    'fiscal_year' => $fiscalYear,
                    'strategy_id' => $strategyId,
                    'objective_name' => $objectiveName,
                ));
            }
            if ($isAjax) {
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(array('success' => true, 'message' => $success), JSON_UNESCAPED_UNICODE);
                exit;
            }
            header('Location: objectives.php?year=' . urlencode($fiscalYear));
            exit;
        }
        if ($isAjax) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(array('success' => false, 'error' => $error), JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

$objId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'toggle_status' && $objId > 0) {
    $gotToken = isset($_GET['csrf']) ? $_GET['csrf'] : '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $gotToken)) {
        setFlash('error', 'CSRF token ไม่ถูกต้อง กรุณาลองใหม่');
        header('Location: objectives.php?year=' . urlencode($yearFilter));
        exit;
    }
    $objRes = $conn->query("SELECT objective_name, status FROM objectives WHERE id = $objId LIMIT 1");
    if ($objRes && $row = $objRes->fetch_assoc()) {
        $newStatus = $row['status'] === 'active' ? 'inactive' : 'active';
        $conn->query("UPDATE objectives SET status = '$newStatus' WHERE id = $objId");
        logfile($conn, 'เปลี่ยนสถานะ', 'objectives', $objId, array(
            'objective_name' => isset($row['objective_name']) ? $row['objective_name'] : '',
            'status' => $newStatus,
        ));
    }
    header('Location: objectives.php?year=' . urlencode($yearFilter));
    exit;
}

// ปีที่ใช้ใน dropdown ปีงบประมาณ
$yearOptions = array();
$yearRes = $conn->query("SELECT DISTINCT fiscal_year FROM objectives ORDER BY fiscal_year DESC");
if ($yearRes) {
    while ($yRow = $yearRes->fetch_assoc()) {
        $yearOptions[] = $yRow['fiscal_year'];
    }
}
if (empty($yearOptions)) {
    $yearOptions[] = $currentFiscalYear;
}

// ยุทธศาสตร์ตามปีที่เลือก (สำหรับ dropdown ในฟอร์ม)
$strategyOptions = array();
$siRes = $conn->query("SELECT id, issue_no, issue_name FROM strategic_issues WHERE fiscal_year = '" . $conn->real_escape_string($yearFilter) . "' ORDER BY issue_no ASC");
if ($siRes) {
    while ($row = $siRes->fetch_assoc()) {
        $strategyOptions[] = $row;
    }
}

// รายการเป้าประสงค์
$objectives = array();
$objSql = "
    SELECT o.id, o.fiscal_year, o.strategy_id, o.objective_name, o.sort_order, o.status,
           si.issue_no, si.issue_name
    FROM objectives o
    LEFT JOIN strategic_issues si ON si.id = o.strategy_id
    WHERE o.fiscal_year = '" . $conn->real_escape_string($yearFilter) . "'
    ORDER BY si.issue_no ASC, o.sort_order ASC, o.id ASC
";
$objRes = $conn->query($objSql);
if ($objRes) {
    while ($row = $objRes->fetch_assoc()) {
        $objectives[] = $row;
    }
}

// สถานะ default สำหรับ dropdown (เพิ่มใหม่)
$defaultStatus = 'active';
$defaultSort = 0;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการเป้าประสงค์ | <?= htmlspecialchars($office_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/style.php'; ?>
</head>
<body>
<?php $activePage = 'objectives'; include __DIR__ . '/menu.php'; ?>
        <div class="container-fluid">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <div class="text-uppercase section-title mb-2">🎯 เป้าประสงค์</div>
                            <h1 class="h3 fw-bold mb-2">จัดการเป้าประสงค์ (Objectives)</h1>
                            <p class="text-muted mb-0">1 ยุทธศาสตร์สามารถกำหนดได้มากกว่า 1 เป้าประสงค์</p>
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
                                <h2 class="h5 fw-bold mb-0">รายการเป้าประสงค์</h2>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <form class="d-flex align-items-center gap-2" method="get">
                                        <label class="form-label mb-0 small text-muted">ปีงบประมาณ</label>
                                        <select class="form-select form-select-sm w-auto" name="year" onchange="this.form.submit()">
                                            <?php foreach ($yearOptions as $y): ?>
                                                <option value="<?= htmlspecialchars($y) ?>" <?= $yearFilter === $y ? 'selected' : '' ?>><?= htmlspecialchars($y) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </form>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="openObjectiveModal()">➕ เพิ่มเป้าประสงค์</button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>ยุทธศาสตร์</th>
                                            <th>ชื่อเป้าประสงค์</th>
                                            <th>ลำดับ</th>
                                            <th>สถานะ</th>
                                            <th>การจัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($objectives)): ?>
                                            <tr><td colspan="6" class="text-muted text-center py-4">ยังไม่มีเป้าประสงค์ในปีงบประมาณนี้</td></tr>
                                        <?php endif; ?>
                                        <?php foreach ($objectives as $index => $obj): ?>
                                            <tr>
                                                <td><?= $index + 1 ?></td>
                                                <td>
                                                    <span class="badge bg-primary-subtle text-primary-emphasis">ยุทธศาสตร์ที่ <?= intval($obj['issue_no']) ?></span>
                                                    <div class="small text-muted" style="max-width: 260px;"><?= htmlspecialchars($obj['issue_name']) ?></div>
                                                </td>
                                                <td class="fw-semibold"><?= htmlspecialchars($obj['objective_name']) ?></td>
                                                <td><?= intval($obj['sort_order']) ?></td>
                                                <td>
                                                    <span class="badge <?= $obj['status'] === 'active' ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' ?>">
                                                        <?= $obj['status'] === 'active' ? 'ใช้งาน' : 'ระงับ' ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="openObjectiveModal(<?= (int)$obj['id'] ?>)">แก้ไข</button>
                                                    <a class="btn btn-sm btn-outline-secondary" href="objectives.php?year=<?= urlencode($yearFilter) ?>&action=toggle_status&id=<?= (int)$obj['id'] ?>&csrf=<?= urlencode(csrfToken()) ?>">
                                                        <?= $obj['status'] === 'active' ? 'ระงับ' : 'เปิดใช้งาน' ?>
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

<!-- ===== Modal: เพิ่ม/แก้ไขเป้าประสงค์ (popup) ===== -->
<div class="modal fade" id="objectiveModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form id="objectiveForm" onsubmit="return saveObjective(event)">
                <input type="hidden" name="objective_id" id="objectiveId" value="0">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="objectiveModalTitle">➕ เพิ่มเป้าประสงค์</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 d-none" id="objectiveModalError"></div>
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label">ปีงบประมาณ <span class="text-danger">*</span></label>
                            <select class="form-select" name="fiscal_year" id="objFiscalYear" onchange="loadObjStrategies()">
                                <?php foreach ($yearOptions as $y): ?>
                                    <option value="<?= htmlspecialchars($y) ?>" <?= $yearFilter === $y ? 'selected' : '' ?>><?= htmlspecialchars($y) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">ลำดับ (sort order)</label>
                            <input class="form-control" type="number" min="0" name="sort_order" id="objSortOrder" value="0">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">สถานะ</label>
                            <select class="form-select" name="status" id="objStatus">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">ยุทธศาสตร์ <span class="text-danger">*</span></label>
                            <select class="form-select" name="strategy_id" id="objStrategy" required>
                                <option value="">-- เลือกยุทธศาสตร์ --</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">ชื่อเป้าประสงค์ <span class="text-danger">*</span></label>
                            <textarea class="form-control" rows="3" name="objective_name" id="objName" placeholder="ระบุเป้าประสงค์" required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary" id="objSaveBtn">💾 บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ===== Modal เพิ่ม/แก้ไขเป้าประสงค์ (popup) =====
var objectiveModalEl = document.getElementById('objectiveModal');

function loadObjStrategies(selectedId) {
    const year = document.getElementById('objFiscalYear').value;
    const sel = document.getElementById('objStrategy');
    if (!year) return;
    fetch('objectives.php?year=' + encodeURIComponent(year) + '&ajax=strategies')
        .then(function (r) { return r.json(); })
        .then(function (list) {
            sel.innerHTML = '<option value="">-- เลือกยุทธศาสตร์ --</option>';
            list.forEach(function (s) {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = 'ยุทธศาสตร์ที่ ' + s.issue_no + ': ' + s.issue_name;
                if (selectedId && String(s.id) === String(selectedId)) opt.selected = true;
                sel.appendChild(opt);
            });
        })
        .catch(function () {});
}

function openObjectiveModal(id) {
    const form = document.getElementById('objectiveForm');
    form.reset();
    document.getElementById('objectiveId').value = id || 0;
    document.getElementById('objectiveModalError').classList.add('d-none');
    document.getElementById('objSaveBtn').disabled = false;
    if (id) {
        document.getElementById('objectiveModalTitle').textContent = '✏️ แก้ไขเป้าประสงค์';
        fetch('objectives.php?ajax=objective&id=' + encodeURIComponent(id))
            .then(function (r) { return r.json(); })
            .then(function (o) {
                if (!o || !o.id) { document.getElementById('objectiveModalError').textContent = 'ไม่พบข้อมูลเป้าประสงค์'; document.getElementById('objectiveModalError').classList.remove('d-none'); return; }
                document.getElementById('objFiscalYear').value = o.fiscal_year || '';
                document.getElementById('objSortOrder').value = o.sort_order || 0;
                document.getElementById('objStatus').value = o.status || 'active';
                document.getElementById('objName').value = o.objective_name || '';
                loadObjStrategies(o.strategy_id);
            })
            .catch(function () {
                document.getElementById('objectiveModalError').textContent = 'โหลดข้อมูลไม่สำเร็จ';
                document.getElementById('objectiveModalError').classList.remove('d-none');
            });
    } else {
        document.getElementById('objectiveModalTitle').textContent = '➕ เพิ่มเป้าประสงค์';
        document.getElementById('objSortOrder').value = '0';
        document.getElementById('objStatus').value = 'active';
        loadObjStrategies(null);
    }
    var modal = bootstrap.Modal.getOrCreateInstance(objectiveModalEl);
    modal.show();
}

function saveObjective(event) {
    event.preventDefault();
    const form = document.getElementById('objectiveForm');
    const errBox = document.getElementById('objectiveModalError');
    const btn = document.getElementById('objSaveBtn');
    errBox.classList.add('d-none');
    btn.disabled = true;
    const fd = new FormData(form);
    fd.append('save_objective', '1');
    fetch('objectives.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
    })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res && res.success) {
                bootstrap.Modal.getInstance(objectiveModalEl).hide();
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
