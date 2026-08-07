<?php
require_once __DIR__ . '/db.php';

/**
 * Global vars from `db.php` for static analyzers
 * @var mysqli $conn
 * @var string $office_name
 */

requireAdmin();

$fUsername = isset($_GET['username']) ? trim($_GET['username']) : '';
$fModule   = isset($_GET['module']) ? trim($_GET['module']) : '';
$fFrom     = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$fTo       = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';

$where = array();
$params = array();
$types = '';

if ($fUsername !== '') {
    $where[] = 'username LIKE ?';
    $params[] = '%' . $fUsername . '%';
    $types .= 's';
}
if ($fModule !== '') {
    $where[] = 'module = ?';
    $params[] = $fModule;
    $types .= 's';
}
if ($fFrom !== '') {
    $where[] = 'created_at >= ?';
    $params[] = $fFrom . ' 00:00:00';
    $types .= 's';
}
if ($fTo !== '') {
    $where[] = 'created_at <= ?';
    $params[] = $fTo . ' 23:59:59';
    $types .= 's';
}

$whereClause = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Default view: 20 latest records (no pagination).
// When searching via the filter menu, show all matching records.
$hasFilter = ($fUsername !== '' || $fModule !== '' || $fFrom !== '' || $fTo !== '');
$limitSql = $hasFilter ? '' : ' LIMIT 20';
$orderSql = " ORDER BY created_at DESC, id DESC" . $limitSql;

// Fetch rows
if ($types !== '') {
    $stmt = $conn->prepare("SELECT * FROM logfile $whereClause$orderSql");
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $logs = array();
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        $stmt->close();
    } else {
        $logs = array();
    }
} else {
    $result = $conn->query("SELECT * FROM logfile$orderSql");
    $logs = array();
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
    }
}
$totalRows = count($logs);

// Distinct modules for filter dropdown
$moduleNames = array();
$modRes = $conn->query("SELECT DISTINCT module FROM logfile ORDER BY module ASC");
if ($modRes) {
    while ($modRow = $modRes->fetch_assoc()) {
        $moduleNames[] = $modRow['module'];
    }
}

function formatLogDetail($detail) {
    if ($detail === null || $detail === '') {
        return '';
    }
    $decoded = json_decode($detail, true);
    if (is_array($decoded)) {
        $parts = array();
        foreach ($decoded as $k => $v) {
            $parts[] = '<span class="text-muted">' . htmlspecialchars($k) . ':</span> ' . htmlspecialchars((string)$v);
        }
        return implode('<br>', $parts);
    }
    return htmlspecialchars($detail);
}

function formatJsonDiff($oldJson, $newJson) {
    $out = array();
    $oldDecoded = json_decode((string)$oldJson, true);
    $newDecoded = json_decode((string)$newJson, true);
    $keys = array_merge(
        is_array($oldDecoded) ? array_keys($oldDecoded) : array(),
        is_array($newDecoded) ? array_keys($newDecoded) : array()
    );
    $keys = array_unique($keys);
    foreach ($keys as $k) {
        $oldVal = is_array($oldDecoded) && isset($oldDecoded[$k]) ? $oldDecoded[$k] : '';
        $newVal = is_array($newDecoded) && isset($newDecoded[$k]) ? $newDecoded[$k] : '';
        if (is_array($oldVal) || is_array($newVal)) {
            $oldVal = json_encode($oldVal, JSON_UNESCAPED_UNICODE);
            $newVal = json_encode($newVal, JSON_UNESCAPED_UNICODE);
        }
        $oldStr = (string)$oldVal;
        $newStr = (string)$newVal;
        if ($oldStr === $newStr) {
            continue;
        }
        $out[] = '<span class="text-muted">' . htmlspecialchars($k) . ':</span> '
               . '<span class="text-danger text-decoration-line-through">' . htmlspecialchars($oldStr === '' ? '—' : $oldStr) . '</span>'
               . ' → '
               . '<span class="text-success">' . htmlspecialchars($newStr === '' ? '—' : $newStr) . '</span>';
    }
    return $out;
}

function moduleLabel($module) {
    $map = array(
        'projects' => 'โครงการ',
        'project_documents' => 'เอกสาร/ร่องรอย',
        'okr_projects' => 'โครงการ (OKR)',
        'okr_agency_targets' => 'ผลงาน OKR หน่วยงาน',
        'users' => 'ผู้ใช้งาน',
        'schools' => 'หน่วยงานการศึกษา',
        'budget_transactions' => 'การเบิกจ่าย',
        'budget_sources' => 'แหล่งงบประมาณ',
        'strategies' => 'ยุทธศาสตร์',
        'setting' => 'ตั้งค่าระบบ',
        'profile' => 'โปรไฟล์',
    );
    return isset($map[$module]) ? $map[$module] : $module;
}

$queryString = array();
if ($fUsername !== '') $queryString['username'] = $fUsername;
if ($fModule !== '')   $queryString['module'] = $fModule;
if ($fFrom !== '')     $queryString['date_from'] = $fFrom;
if ($fTo !== '')       $queryString['date_to'] = $fTo;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>บันทึกการใช้งาน | <?= htmlspecialchars($office_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/style.php'; ?>
</head>
<body>
<?php $activePage = 'logfile'; include __DIR__ . '/menu.php'; ?>
        <div class="container-fluid">
            <div class="card border-0 shadow-sm rounded-4 mb-4 hero-panel">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <div class="text-uppercase section-title mb-2">📋 Logfile</div>
                            <h1 class="h3 fw-bold mb-2">บันทึกการใช้งานระบบ</h1>
                            <p class="text-muted mb-0">แสดงประวัติการบันทึก/แก้ไขข้อมูลทั้งหมด: ผู้ใช้, เวลา, รายการที่แก้ไข และ IP</p>
                        </div>
                        <div class="text-end">
                            <div class="fs-5 fw-bold text-primary"><?= number_format($totalRows) ?></div>
                            <div class="small text-muted"><?= $hasFilter ? 'รายการที่ค้นหาเจอ' : 'รายการที่แสดง (20 ล่าสุด)' ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <form method="get" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">ผู้ใช้งาน</label>
                            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($fUsername) ?>" placeholder="ค้นหาจาก username">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">รายการ/โมดูล</label>
                            <select name="module" class="form-select">
                                <option value="">-- ทั้งหมด --</option>
                                <?php foreach ($moduleNames as $mn): ?>
                                    <option value="<?= htmlspecialchars($mn) ?>" <?= $fModule === $mn ? 'selected' : '' ?>><?= htmlspecialchars(moduleLabel($mn)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">จากวันที่</label>
                            <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($fFrom) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">ถึงวันที่</label>
                            <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($fTo) ?>">
                        </div>
                        <div class="col-md-2 d-flex gap-2">
                            <button type="submit" class="btn btn-primary w-100">กรอง</button>
                            <a href="logfile.php" class="btn btn-outline-secondary w-100">รีเซ็ต</a>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>วัน/เวลา</th>
                                    <th>ผู้ใช้</th>
                                    <th>การกระทำ</th>
                                    <th>รายการ</th>
                                    <th>รายละเอียด</th>
                                    <th>IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($logs) === 0): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-4">ยังไม่มีบันทึกการใช้งาน</td></tr>
                                <?php endif; ?>
                                <?php foreach ($logs as $i => $log): ?>
                                    <tr>
                                        <td class="text-muted"><?= $i + 1 ?></td>
                                        <td class="text-nowrap"><?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($log['created_at']))) ?></td>
                                        <td class="fw-semibold"><?= htmlspecialchars($log['username'] ?: '-') ?></td>
                                        <td>
                                            <span class="badge" style="background: #eef2ff; color: #3730a3; padding: 0.4rem 0.6rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600;"><?= htmlspecialchars($log['action']) ?></span>
                                        </td>
                                        <td>
                                            <div class="fw-semibold small"><?= htmlspecialchars(moduleLabel($log['module'])) ?></div>
                                            <?php if ($log['record_id'] !== null && $log['record_id'] !== ''): ?>
                                                <div class="text-muted small">ID: <?= htmlspecialchars($log['record_id']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small" style="max-width: 380px;">
                                            <?php
                                            $diffRows = formatJsonDiff($log['old_values'], $log['new_values']);
                                            if (count($diffRows) > 0):
                                            ?>
                                                <div class="fw-semibold text-primary mb-1">✏️ การเปลี่ยนแปลง</div>
                                                <?= implode('<br>', $diffRows) ?>
                                            <?php else: ?>
                                                <?= formatLogDetail($log['detail']) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><code><?= htmlspecialchars($log['ip_address'] ?: '-') ?></code></td>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
