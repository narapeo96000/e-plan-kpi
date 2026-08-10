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

requirePlanOrAdmin();

// AJAX endpoint สำหรับโหลดข้อมูลยุทธศาสตร์รายตัว (สำหรับ popup แก้ไข)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'strategy' && !empty($_GET['id'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $srow = null;
    $sr = $conn->query("SELECT * FROM strategic_issues WHERE id = " . intval($_GET['id']) . " LIMIT 1");
    if ($sr) $srow = $sr->fetch_assoc();
    echo json_encode($srow ? $srow : array(), JSON_UNESCAPED_UNICODE);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$strategyId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$isAjax = (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_strategy'])) {
    csrfCheck('strategies.php');
    $strategyId = isset($_POST['strategy_id']) ? intval($_POST['strategy_id']) : 0;
    $fiscalYear = trim(isset($_POST['fiscal_year']) ? $_POST['fiscal_year'] : '');
    $issueNo = intval(isset($_POST['issue_no']) ? $_POST['issue_no'] : 0);
    $issueName = trim(isset($_POST['issue_name']) ? $_POST['issue_name'] : '');

    if (empty($fiscalYear) || $issueNo <= 0 || empty($issueName)) {
        $error = 'กรุณากรอกข้อมูลยุทธศาสตร์ให้ครบถ้วน';
    } else {
        if ($strategyId > 0) {
            $sql = "UPDATE strategic_issues SET fiscal_year = '" . $conn->real_escape_string($fiscalYear) . "', issue_no = $issueNo, issue_name = '" . $conn->real_escape_string($issueName) . "' WHERE id = $strategyId";
            $conn->query($sql);
            $success = 'แก้ไขยุทธศาสตร์เรียบร้อยแล้ว';
            logfile($conn, 'แก้ไข', 'strategies', $strategyId, array(
                'fiscal_year' => $fiscalYear,
                'issue_no' => $issueNo,
                'issue_name' => $issueName,
            ));
        } else {
            $sql = "INSERT INTO strategic_issues (fiscal_year, issue_no, issue_name, created_at) VALUES ('" . $conn->real_escape_string($fiscalYear) . "', $issueNo, '" . $conn->real_escape_string($issueName) . "', NOW())";
            $conn->query($sql);
            $success = 'เพิ่มยุทธศาสตร์ใหม่เรียบร้อยแล้ว';
            logfile($conn, 'เพิ่ม', 'strategies', $conn->insert_id, array(
                'fiscal_year' => $fiscalYear,
                'issue_no' => $issueNo,
                'issue_name' => $issueName,
            ));
        }
        if ($isAjax) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(array('success' => true, 'message' => $success), JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: strategies.php');
        exit;
    }
    if ($isAjax) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array('success' => false, 'error' => $error), JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$strategies = [];
$res = $conn->query("SELECT * FROM strategic_issues ORDER BY fiscal_year DESC, issue_no ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $strategies[] = $row;
    }
}

$thaiYear = date('Y') + 543;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ยุทธศาสตร์ประจำปี | <?= htmlspecialchars($office_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/style.php'; ?>
</head>
<body>
<?php $activePage = 'strategies'; include __DIR__ . '/menu.php'; ?>
        <div class="container-fluid">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <div class="text-uppercase section-title mb-2">🎯 ยุทธศาสตร์</div>
                            <h1 class="h3 fw-bold mb-2">จัดการยุทธศาสตร์ประจำปี</h1>
                            <p class="text-muted mb-0">Admin สามารถเพิ่มหรือแก้ไขแผนยุทธศาสตร์ต่อปีได้ที่นี่</p>
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
                                <h2 class="h5 fw-bold mb-0">รายการยุทธศาสตร์</h2>
                                <button type="button" class="btn btn-primary btn-sm" onclick="openStrategyModal()">➕ เพิ่มยุทธศาสตร์</button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>ปีงบประมาณ</th>
                                            <th>ยุทธศาสตร์</th>
                                            <th>ชื่อยุทธศาสตร์</th>
                                            <th>การจัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($strategies as $index => $item): ?>
                                            <tr>
                                                <td><?= $index + 1 ?></td>
                                                <td><?= htmlspecialchars($item['fiscal_year']) ?></td>
                                                <td>ยุทธศาสตร์ที่ <?= intval($item['issue_no']) ?></td>
                                                <td><?= htmlspecialchars($item['issue_name']) ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="openStrategyModal(<?= (int)$item['id'] ?>)">แก้ไข</button>
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

<!-- ===== Modal: เพิ่ม/แก้ไขยุทธศาสตร์ (popup) ===== -->
<div class="modal fade" id="strategyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="strategyForm" onsubmit="return saveStrategy(event)">
                <input type="hidden" name="strategy_id" id="strategyId" value="0">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="strategyModalTitle">➕ เพิ่มยุทธศาสตร์</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger py-2 d-none" id="strategyModalError"></div>
                    <div class="mb-3">
                        <label class="form-label">ปีงบประมาณ</label>
                        <input class="form-control" type="text" name="fiscal_year" id="strategyFiscalYear" value="<?= htmlspecialchars($thaiYear) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ลำดับยุทธศาสตร์</label>
                        <input class="form-control" type="number" name="issue_no" id="strategyIssueNo" min="1" value="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ชื่อยุทธศาสตร์</label>
                        <textarea class="form-control" name="issue_name" id="strategyIssueName" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary" id="strategySaveBtn">💾 บันทึกยุทธศาสตร์</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ===== Modal เพิ่ม/แก้ไขยุทธศาสตร์ (popup) =====
var strategyModalEl = document.getElementById('strategyModal');

function openStrategyModal(id) {
    const form = document.getElementById('strategyForm');
    form.reset();
    document.getElementById('strategyId').value = id || 0;
    document.getElementById('strategyModalError').classList.add('d-none');
    document.getElementById('strategySaveBtn').disabled = false;
    if (id) {
        document.getElementById('strategyModalTitle').textContent = '✏️ แก้ไขยุทธศาสตร์';
        fetch('strategies.php?ajax=strategy&id=' + encodeURIComponent(id))
            .then(function (r) { return r.json(); })
            .then(function (s) {
                if (!s || !s.id) { document.getElementById('strategyModalError').textContent = 'ไม่พบข้อมูลยุทธศาสตร์'; document.getElementById('strategyModalError').classList.remove('d-none'); return; }
                document.getElementById('strategyFiscalYear').value = s.fiscal_year || '';
                document.getElementById('strategyIssueNo').value = s.issue_no || 1;
                document.getElementById('strategyIssueName').value = s.issue_name || '';
            })
            .catch(function () {
                document.getElementById('strategyModalError').textContent = 'โหลดข้อมูลไม่สำเร็จ';
                document.getElementById('strategyModalError').classList.remove('d-none');
            });
    } else {
        document.getElementById('strategyModalTitle').textContent = '➕ เพิ่มยุทธศาสตร์';
        document.getElementById('strategyFiscalYear').value = '<?= htmlspecialchars($thaiYear) ?>';
        document.getElementById('strategyIssueNo').value = '1';
    }
    var modal = bootstrap.Modal.getOrCreateInstance(strategyModalEl);
    modal.show();
}

function saveStrategy(event) {
    event.preventDefault();
    const form = document.getElementById('strategyForm');
    const errBox = document.getElementById('strategyModalError');
    const btn = document.getElementById('strategySaveBtn');
    errBox.classList.add('d-none');
    btn.disabled = true;
    const fd = new FormData(form);
    fd.append('save_strategy', '1');
    fetch('strategies.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: fd
    })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res && res.success) {
                bootstrap.Modal.getInstance(strategyModalEl).hide();
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
