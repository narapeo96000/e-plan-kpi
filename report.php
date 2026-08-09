<?php
require_once __DIR__ . '/db.php';
requireLogin();

$fiscalYear = !empty($fiscal_year) ? $fiscal_year : date('Y') + 543;
$filterYear = isset($_GET['year']) ? trim($_GET['year']) : $fiscalYear;
$escapedYear = $conn->real_escape_string($filterYear);

// Scope report data to the logged-in user's parent agency (admin & plan see all)
$agencyScope = '';
if (isLoggedIn() && !isAdminOrPlan()) {
    $ua = (int)currentAgencyId();
    if ($ua > 0) {
        $agencyScope = " AND p.agency_id = " . $ua;
    }
}

$summary = array();
$res = $conn->query("
    SELECT
        COUNT(*) AS total_projects,
        COALESCE(SUM(p.budget_allocated), 0) AS total_allocated,
        COALESCE(SUM(p.budget_used), 0) AS total_used
    FROM projects p WHERE p.fiscal_year = '$escapedYear'
" . $agencyScope . "
");
if ($res) $summary = $res->fetch_assoc();

$totalProjects = (int)(isset($summary['total_projects']) ? $summary['total_projects'] : 0);
$totalAllocated = (float)(isset($summary['total_allocated']) ? $summary['total_allocated'] : 0);
$totalUsed = (float)(isset($summary['total_used']) ? $summary['total_used'] : 0);
$overallPercent = $totalAllocated > 0 ? round(($totalUsed / $totalAllocated) * 100, 1) : 0;
$totalRemain = max(0, $totalAllocated - $totalUsed);

$statusStats = array();
$res = $conn->query("
    SELECT p.status, COUNT(*) AS cnt, COALESCE(SUM(p.budget_allocated), 0) AS alloc, COALESCE(SUM(p.budget_used), 0) AS used
    FROM projects p WHERE p.fiscal_year = '$escapedYear'
" . $agencyScope . "
    GROUP BY p.status ORDER BY cnt DESC
");
if ($res) {
    while ($row = $res->fetch_assoc()) $statusStats[] = $row;
}

$topProjects = array();
$res = $conn->query("
    SELECT p.title, p.budget_allocated, p.budget_used,
           ROUND((p.budget_used / p.budget_allocated) * 100, 1) AS usage_pct,
           a.agency_name AS school_name
    FROM projects p
    LEFT JOIN agencies a ON a.id = p.agency_id
    WHERE p.fiscal_year = '$escapedYear' AND p.budget_allocated > 0
" . $agencyScope . "
    ORDER BY p.budget_allocated DESC LIMIT 15
");
if ($res) {
    while ($row = $res->fetch_assoc()) $topProjects[] = $row;
}

$transactionSummary = array();
$res = $conn->query("
    SELECT COALESCE(COUNT(*), 0) AS tx_count, COALESCE(SUM(t.amount), 0) AS tx_total
    FROM budget_transactions t
    JOIN projects p ON p.id = t.project_id
    WHERE p.fiscal_year = '$escapedYear'
" . $agencyScope . "
");
if ($res) $transactionSummary = $res->fetch_assoc();
$txCount = (int)(isset($transactionSummary['tx_count']) ? $transactionSummary['tx_count'] : 0);
$txTotal = (float)(isset($transactionSummary['tx_total']) ? $transactionSummary['tx_total'] : 0);

$years = array();
$yearsSql = "SELECT DISTINCT fiscal_year FROM projects";
if (isLoggedIn() && !isAdminOrPlan()) {
    $ua = (int)currentAgencyId();
    if ($ua > 0) {
        $yearsSql .= " WHERE agency_id = " . $ua;
    }
}
$yearsSql .= " ORDER BY fiscal_year DESC";
$res = $conn->query($yearsSql);
if ($res) {
    while ($row = $res->fetch_assoc()) $years[] = $row['fiscal_year'];
}
if (empty($years)) $years = array($fiscalYear);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สรุปรายงานประจำปี | <?= htmlspecialchars($office_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/style.php'; ?>
</head>
<body>
<?php $activePage = 'report'; include __DIR__ . '/menu.php'; ?>
    <div class="container-fluid">
        <div class="card border-0 shadow-sm rounded-4 mb-4 hero-panel">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <div class="text-uppercase section-title mb-2">📄 Annual Report</div>
                        <h1 class="h2 fw-bold mb-2">สรุปรายงานประจำปี</h1>
                        <p class="text-muted mb-0">ภาพรวมการดำเนินงานโครงการ ปีงบประมาณ <?= htmlspecialchars($filterYear) ?></p>
                    </div>
                    <div class="d-flex gap-2">
                        <a class="btn btn-success" href="export_excel.php?year=<?= urlencode($filterYear) ?>">📤 ส่งออก Excel</a>
                        <a class="btn btn-danger" href="export_pdf.php?year=<?= urlencode($filterYear) ?>">📤 ส่งออก PDF</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">ปีงบประมาณ</label>
                        <select name="year" class="form-select">
                            <?php foreach ($years as $y): ?>
                                <option value="<?= htmlspecialchars($y) ?>" <?= $filterYear === $y ? 'selected' : '' ?>><?= htmlspecialchars($y) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">ดูรายงาน</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-6 col-md-3">
                <div class="card stat-card border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">📋 โครงการทั้งหมด</div>
                        <div class="fs-4 fw-bold text-primary"><?= number_format($totalProjects) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">💰 งบจัดสรรทั้งหมด</div>
                        <div class="fs-4 fw-bold text-success">฿<?= number_format($totalAllocated, 2) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">💳 เบิกจ่ายแล้ว</div>
                        <div class="fs-4 fw-bold text-info">฿<?= number_format($totalUsed, 2) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">📈 คงเหลือ</div>
                        <div class="fs-4 fw-bold" style="color:#f59e0b">฿<?= number_format($totalRemain, 2) ?></div>
                        <div class="small text-muted"><?= $overallPercent ?>% ของงบทั้งหมด</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-12 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="section-title mb-3">🔄 สถานะโครงการ</h5>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>สถานะ</th>
                                        <th class="text-end">จำนวน</th>
                                        <th class="text-end">งบจัดสรร</th>
                                        <th class="text-end">เบิกจ่าย</th>
                                        <th>%</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($statusStats as $s): ?>
                                        <?php $pct = (float)$s['alloc'] > 0 ? round(((float)$s['used'] / (float)$s['alloc']) * 100, 1) : 0; ?>
                                        <tr>
                                            <td><?= htmlspecialchars($s['status'] ?: 'ไม่ระบุ') ?></td>
                                            <td class="text-end"><?= (int)$s['cnt'] ?></td>
                                            <td class="text-end"><?= number_format((float)$s['alloc'], 2) ?></td>
                                            <td class="text-end"><?= number_format((float)$s['used'], 2) ?></td>
                                            <td><?= $pct ?>%</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h5 class="section-title mb-3">💳 สรุปการเบิกจ่าย</h5>
                        <div class="row g-3">
                            <div class="col-6">
                                <div class="text-center p-3 rounded-4" style="background:#f0f9ff;">
                                    <div class="text-muted small">จำนวนรายการเบิก</div>
                                    <div class="fs-3 fw-bold text-primary"><?= number_format($txCount) ?></div>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center p-3 rounded-4" style="background:#f0fdf4;">
                                    <div class="text-muted small">ยอดเบิกทั้งหมด</div>
                                    <div class="fs-3 fw-bold text-success">฿<?= number_format($txTotal, 2) ?></div>
                                </div>
                            </div>
                            <div class="col-12 mt-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-muted small">อัตราการใช้จ่าย</span>
                                    <span class="fw-bold"><?= $overallPercent ?>%</span>
                                </div>
                                <div class="progress progress-bar-custom mt-1" style="height:10px;">
                                    <div class="progress-bar" style="width:<?= $overallPercent ?>%"></div>
                                </div>
                                <div class="d-flex justify-content-between small text-muted mt-1">
                                    <span>฿<?= number_format($totalUsed, 2) ?></span>
                                    <span>฿<?= number_format($totalAllocated, 2) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="section-title mb-3">🏆 โครงการที่มีงบประมาณสูงสุด (Top 15)</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>โครงการ</th>
                                <th>หน่วยงาน</th>
                                <th class="text-end">งบจัดสรร</th>
                                <th class="text-end">เบิกจ่าย</th>
                                <th>อัตราการใช้จ่าย</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topProjects as $i => $p): ?>
                                <?php $pct = min(100, (float)$p['usage_pct']); ?>
                                <tr>
                                    <td><?= $i + 1 ?></td>
                                    <td class="fw-semibold"><?= htmlspecialchars($p['title']) ?></td>
                                    <td><span class="small"><?= htmlspecialchars($p['school_name'] ?: '-') ?></span></td>
                                    <td class="text-end"><?= number_format((float)$p['budget_allocated'], 2) ?></td>
                                    <td class="text-end"><?= number_format((float)$p['budget_used'], 2) ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress progress-bar-custom flex-grow-1" style="height:6px;">
                                                <div class="progress-bar" style="width:<?= $pct ?>%"></div>
                                            </div>
                                            <span class="small fw-semibold"><?= $pct ?>%</span>
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
</main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
