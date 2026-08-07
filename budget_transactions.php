<?php
require_once __DIR__ . '/db.php';
requireLogin();

$fiscalYear = !empty($fiscal_year) ? $fiscal_year : date('Y') + 543;
$error = '';
$success = '';

$filterProject = isset($_GET['project_id']) ? (int)$_GET['project_id'] : 0;
$filterDateFrom = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$filterDateTo = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'add' || $action === 'edit') {
        $txId = isset($_POST['tx_id']) ? (int)$_POST['tx_id'] : 0;
        $projectId = isset($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
        $docNo = trim(isset($_POST['doc_no']) ? $_POST['doc_no'] : '');
        $docDate = trim(isset($_POST['doc_date']) ? $_POST['doc_date'] : '');
        $amount = trim(isset($_POST['amount']) ? $_POST['amount'] : '0');
        $detail = trim(isset($_POST['detail']) ? $_POST['detail'] : '');

        if ($projectId <= 0 || $docDate === '' || (float)$amount <= 0) {
            $error = 'กรุณาเลือกโครงการ ระบุวันที่ และจำนวนเงินที่ถูกต้อง';
        } else {
            $amount = str_replace(',', '', $amount);
            $username = currentUsername();

            if ($action === 'add') {
                $stmt = $conn->prepare("INSERT INTO budget_transactions (project_id, doc_no, doc_date, amount, detail, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                if ($stmt) {
                    $stmt->bind_param('issdss', $projectId, $docNo, $docDate, $amount, $detail, $username);
                    if ($stmt->execute()) {
                        $success = 'บันทึกรายการเบิกจ่ายเรียบร้อยแล้ว';
                        logfile($conn, 'เพิ่ม', 'budget_transactions', $conn->insert_id, array(
                            'project_id' => $projectId,
                            'doc_no' => $docNo,
                            'doc_date' => $docDate,
                            'amount' => $amount,
                            'detail' => mb_substr($detail, 0, 200),
                        ));
                    } else {
                        $error = 'บันทึกไม่สำเร็จ: ' . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error = 'บันทึกไม่สำเร็จ: ' . $conn->error;
                }
            } else {
                $stmt = $conn->prepare("UPDATE budget_transactions SET project_id = ?, doc_no = ?, doc_date = ?, amount = ?, detail = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param('issdsi', $projectId, $docNo, $docDate, $amount, $detail, $txId);
                    if ($stmt->execute()) {
                        $success = 'แก้ไขรายการเบิกจ่ายเรียบร้อยแล้ว';
                        logfile($conn, 'แก้ไข', 'budget_transactions', $txId, array(
                            'project_id' => $projectId,
                            'doc_no' => $docNo,
                            'doc_date' => $docDate,
                            'amount' => $amount,
                            'detail' => mb_substr($detail, 0, 200),
                        ));
                    } else {
                        $error = 'แก้ไขไม่สำเร็จ: ' . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error = 'แก้ไขไม่สำเร็จ: ' . $conn->error;
                }
            }

            if ($success) {
                $sumResult = $conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM budget_transactions WHERE project_id = $projectId");
                $sumRow = $sumResult ? $sumResult->fetch_assoc() : array('total' => 0);
                $totalUsed = (float)$sumRow['total'];
                $conn->query("UPDATE projects SET budget_used = $totalUsed WHERE id = $projectId");
            }
        }
    }

    if ($action === 'delete') {
        $txId = isset($_POST['tx_id']) ? (int)$_POST['tx_id'] : 0;
        if ($txId > 0) {
            $res = $conn->query("SELECT project_id, doc_no, detail FROM budget_transactions WHERE id = $txId LIMIT 1");
            $row = $res ? $res->fetch_assoc() : null;
            $projectId = $row ? (int)$row['project_id'] : 0;

            $stmt = $conn->prepare("DELETE FROM budget_transactions WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $txId);
                if ($stmt->execute()) {
                    $success = 'ลบรายการเบิกจ่ายเรียบร้อยแล้ว';
                    logfile($conn, 'ลบ', 'budget_transactions', $txId, array(
                        'project_id' => $projectId,
                        'doc_no' => $row && isset($row['doc_no']) ? $row['doc_no'] : '',
                        'detail' => $row && isset($row['detail']) ? mb_substr($row['detail'], 0, 200) : '',
                    ));
                } else {
                    $error = 'ลบไม่สำเร็จ: ' . $stmt->error;
                }
                $stmt->close();
            }

            if ($success && $projectId > 0) {
                $sumResult = $conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM budget_transactions WHERE project_id = $projectId");
                $sumRow = $sumResult ? $sumResult->fetch_assoc() : array('total' => 0);
                $totalUsed = (float)$sumRow['total'];
                $conn->query("UPDATE projects SET budget_used = $totalUsed WHERE id = $projectId");
            }
        }
    }

    if ($error === '' && $success !== '') {
        $redirect = 'budget_transactions.php';
        if ($filterProject > 0) $redirect .= '?project_id=' . $filterProject;
        header('Location: ' . $redirect);
        exit;
    }
}

$where = array();
$params = array();
$types = '';

if ($filterProject > 0) {
    $where[] = 't.project_id = ?';
    $params[] = $filterProject;
    $types .= 'i';
}
if ($filterDateFrom !== '') {
    $where[] = 't.doc_date >= ?';
    $params[] = $filterDateFrom;
    $types .= 's';
}
if ($filterDateTo !== '') {
    $where[] = 't.doc_date <= ?';
    $params[] = $filterDateTo;
    $types .= 's';
}

$whereClause = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

if ($types !== '') {
    $stmt = $conn->prepare("
        SELECT t.*, p.title AS project_title, p.budget_allocated, p.budget_used, p.fiscal_year
        FROM budget_transactions t
        JOIN projects p ON p.id = t.project_id
        $whereClause
        ORDER BY t.doc_date DESC, t.created_at DESC
    ");
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $transactions = array();
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
        $stmt->close();
    } else {
        $transactions = array();
    }
} else {
    $result = $conn->query("
        SELECT t.*, p.title AS project_title, p.budget_allocated, p.budget_used, p.fiscal_year
        FROM budget_transactions t
        JOIN projects p ON p.id = t.project_id
        ORDER BY t.doc_date DESC, t.created_at DESC
    ");
    $transactions = array();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $transactions[] = $row;
        }
    }
}

$projectsResult = $conn->query("SELECT id, title, fiscal_year, budget_allocated, budget_used FROM projects ORDER BY fiscal_year DESC, title ASC");
$allProjects = array();
if ($projectsResult) {
    while ($row = $projectsResult->fetch_assoc()) {
        $allProjects[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>บันทึกการเบิกจ่าย | <?= htmlspecialchars($office_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/style.php'; ?>
</head>
<body>
<?php $activePage = 'budget_transactions'; include __DIR__ . '/menu.php'; ?>
    <div class="container-fluid">
        <div class="card border-0 shadow-sm rounded-4 mb-4 hero-panel">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <div class="text-uppercase section-title mb-2">💳 Budget Transactions</div>
                        <h1 class="h2 fw-bold mb-2">บันทึกการเบิกจ่าย</h1>
                        <p class="text-muted mb-0">จัดการรายการเบิกจ่ายงบประมาณของโครงการ ปี <?= htmlspecialchars($fiscalYear) ?></p>
                    </div>
                    <button class="btn btn-primary" onclick="openAddModal()">➕ เพิ่มรายการเบิกจ่าย</button>
                </div>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">โครงการ</label>
                        <select name="project_id" class="form-select">
                            <option value="">-- ทุกโครงการ --</option>
                            <?php foreach ($allProjects as $p): ?>
                                <option value="<?= (int)$p['id'] ?>" <?= $filterProject === (int)$p['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['fiscal_year'] . ' - ' . $p['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">จากวันที่</label>
                        <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($filterDateFrom) ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">ถึงวันที่</label>
                        <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($filterDateTo) ?>">
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary w-100">กรอง</button>
                        <a href="budget_transactions.php" class="btn btn-outline-secondary w-100">รีเซ็ต</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>เลขที่เอกสาร</th>
                                <th>วันที่</th>
                                <th>โครงการ</th>
                                <th>ปีงบ</th>
                                <th class="text-end">จำนวนเงิน</th>
                                <th>รายละเอียด</th>
                                <th>ผู้บันทึก</th>
                                <th>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($transactions) === 0): ?>
                                <tr><td colspan="9" class="text-center text-muted py-4">ยังไม่มีรายการเบิกจ่าย</td></tr>
                            <?php endif; ?>
                            <?php foreach ($transactions as $i => $tx): ?>
                                <tr>
                                    <td class="text-muted"><?= $i + 1 ?></td>
                                    <td><?= htmlspecialchars($tx['doc_no'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars(date('d/m/Y', strtotime($tx['doc_date']))) ?></td>
                                    <td>
                                        <div class="fw-semibold small"><?= htmlspecialchars($tx['project_title']) ?></div>
                                        <?php
                                        $alloc = (float)$tx['budget_allocated'];
                                        $used = (float)$tx['budget_used'];
                                        $pct = $alloc > 0 ? round(($used / $alloc) * 100, 1) : 0;
                                        ?>
                                        <div class="progress progress-bar-custom mt-1" style="height:4px;">
                                            <div class="progress-bar" style="width:<?= $pct ?>%"></div>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($tx['fiscal_year']) ?></td>
                                    <td class="text-end fw-semibold"><?= number_format((float)$tx['amount'], 2) ?></td>
                                    <td><span class="small text-muted"><?= htmlspecialchars(mb_substr($tx['detail'] ?: '-', 0, 80)) ?></span></td>
                                    <td><span class="small"><?= htmlspecialchars($tx['created_by'] ?: '-') ?></span></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" onclick="openEditModal(<?= (int)$tx['id'] ?>)">✏️</button>
                                        <form method="post" style="display:inline" onsubmit="return confirm('ยืนยันการลบรายการนี้?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="tx_id" value="<?= (int)$tx['id'] ?>">
                                            <button class="btn btn-sm btn-outline-danger">🗑️</button>
                                        </form>
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

<div class="modal fade" id="txModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" id="txAction" value="add">
                <input type="hidden" name="tx_id" id="txId" value="0">
                <div class="modal-header">
                    <h5 class="modal-title" id="txModalTitle">เพิ่มรายการเบิกจ่าย</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">โครงการ <span class="text-danger">*</span></label>
                        <select name="project_id" id="txProjectId" class="form-select" required>
                            <option value="">-- เลือกโครงการ --</option>
                            <?php foreach ($allProjects as $p): ?>
                                <option value="<?= (int)$p['id'] ?>" data-alloc="<?= (float)$p['budget_allocated'] ?>" data-used="<?= (float)$p['budget_used'] ?>">
                                    <?= htmlspecialchars($p['fiscal_year'] . ' - ' . $p['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">เลขที่เอกสาร</label>
                        <input type="text" name="doc_no" id="txDocNo" class="form-control" placeholder="เลขที่ใบเบิก/ใบสำคัญ">
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">วันที่เอกสาร <span class="text-danger">*</span></label>
                            <input type="date" name="doc_date" id="txDocDate" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">จำนวนเงิน <span class="text-danger">*</span></label>
                            <input type="text" name="amount" id="txAmount" class="form-control" placeholder="0.00" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">รายละเอียด</label>
                        <textarea name="detail" id="txDetail" class="form-control" rows="3" placeholder="รายละเอียดการเบิกจ่าย"></textarea>
                    </div>
                    <div id="txBudgetInfo" class="alert alert-info small d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
var transactions = <?= json_encode($transactions, JSON_UNESCAPED_UNICODE) ?>;

function openAddModal() {
    document.getElementById('txAction').value = 'add';
    document.getElementById('txId').value = '0';
    document.getElementById('txModalTitle').textContent = '➕ เพิ่มรายการเบิกจ่าย';
    document.getElementById('txProjectId').value = '';
    document.getElementById('txDocNo').value = '';
    document.getElementById('txDocDate').value = '<?= date('Y-m-d') ?>';
    document.getElementById('txAmount').value = '';
    document.getElementById('txDetail').value = '';
    document.getElementById('txBudgetInfo').classList.add('d-none');
    var modal = new bootstrap.Modal(document.getElementById('txModal'));
    modal.show();
}

function openEditModal(id) {
    var tx = transactions.find(function(t) { return parseInt(t.id) === parseInt(id); });
    if (!tx) return;
    document.getElementById('txAction').value = 'edit';
    document.getElementById('txId').value = tx.id;
    document.getElementById('txModalTitle').textContent = '✏️ แก้ไขรายการเบิกจ่าย';
    document.getElementById('txProjectId').value = tx.project_id;
    document.getElementById('txDocNo').value = tx.doc_no || '';
    document.getElementById('txDocDate').value = tx.doc_date;
    document.getElementById('txAmount').value = tx.amount;
    document.getElementById('txDetail').value = tx.detail || '';
    showBudgetInfo(tx.project_id);
    var modal = new bootstrap.Modal(document.getElementById('txModal'));
    modal.show();
}

function showBudgetInfo(projectId) {
    var sel = document.getElementById('txProjectId');
    var info = document.getElementById('txBudgetInfo');
    for (var i = 0; i < sel.options.length; i++) {
        if (parseInt(sel.options[i].value) === parseInt(projectId)) {
            var alloc = parseFloat(sel.options[i].getAttribute('data-alloc')) || 0;
            var used = parseFloat(sel.options[i].getAttribute('data-used')) || 0;
            var remain = Math.max(0, alloc - used);
            info.innerHTML = '💰 งบจัดสรร: ' + numberFormat(alloc) + ' | ใช้ไป: ' + numberFormat(used) + ' | คงเหลือ: ' + numberFormat(remain);
            info.classList.remove('d-none');
            return;
        }
    }
    info.classList.add('d-none');
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('txProjectId').addEventListener('change', function() {
        showBudgetInfo(this.value);
    });
    document.getElementById('txAmount').addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9.]/g, '');
    });
});

function numberFormat(n) {
    return n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
