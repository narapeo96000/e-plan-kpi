<?php
require_once __DIR__ . '/db.php';

$fiscalYear = !empty($fiscal_year) ? $fiscal_year : date('Y') + 543;
$filterYear = isset($_GET['year']) ? trim($_GET['year']) : $fiscalYear;

$sources = array();
$result = $conn->query("
    SELECT bi.id, bi.source_name, bi.fiscal_year, bi.status,
           COUNT(p.id) AS project_count,
           COALESCE(SUM(p.budget_allocated), 0) AS total_allocated,
           COALESCE(SUM(p.budget_used), 0) AS total_used,
           CASE WHEN COALESCE(SUM(p.budget_allocated), 0) > 0
                THEN ROUND((COALESCE(SUM(p.budget_used), 0) / COALESCE(SUM(p.budget_allocated), 0)) * 100, 1)
                ELSE 0 END AS usage_percent
    FROM budget_income bi
    LEFT JOIN projects p ON p.budget_source = bi.source_name AND p.fiscal_year = '" . $conn->real_escape_string($filterYear) . "'
    WHERE bi.status = 'active'
    GROUP BY bi.id, bi.source_name, bi.fiscal_year, bi.status
    ORDER BY bi.source_name ASC
");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $sources[] = $row;
    }
}

$projectTotal = 0;
$allocTotal = 0;
$usedTotal = 0;
foreach ($sources as $s) {
    $projectTotal += (int)$s['project_count'];
    $allocTotal += (float)$s['total_allocated'];
    $usedTotal += (float)$s['total_used'];
}
$overallPercent = $allocTotal > 0 ? round(($usedTotal / $allocTotal) * 100, 1) : 0;

$years = array();
$yearRes = $conn->query("SELECT DISTINCT fiscal_year FROM budget_income WHERE status = 'active' ORDER BY fiscal_year DESC");
if ($yearRes) {
    while ($row = $yearRes->fetch_assoc()) {
        $years[] = $row['fiscal_year'];
    }
}
if (empty($years)) {
    $years = array($fiscalYear);
}

// budget_income table: same data as budget_income above but for the "ตารางสรุป"

$budgetResult = $conn->query("
    SELECT bi.id, bi.source_name, bi.fiscal_year, bi.status, bi.is_active,
           COUNT(p.id) AS project_count,
           COALESCE(SUM(p.budget_allocated), 0) AS total_allocated,
           COALESCE(SUM(p.budget_used), 0) AS total_used
    FROM budget_income bi
    LEFT JOIN projects p ON p.budget_source = bi.source_name AND p.fiscal_year = '" . $conn->real_escape_string($filterYear) . "'
    GROUP BY bi.id, bi.source_name, bi.fiscal_year, bi.status, bi.is_active
    ORDER BY bi.source_name ASC
");
$budgetRows = array();
if ($budgetResult) {
    while ($row = $budgetResult->fetch_assoc()) {
        $budgetRows[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สรุปตามแหล่งเงิน | <?= htmlspecialchars($office_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/style.php'; ?>
</head>
<body>
<?php $activePage = 'budget_income'; include __DIR__ . '/menu.php'; ?>
    <div class="container-fluid">
        <div class="card border-0 shadow-sm rounded-4 mb-4 hero-panel">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <div class="text-uppercase section-title mb-2">📊 Budget Summary</div>
                        <h1 class="h2 fw-bold mb-2">สรุปงบประมาณตามแหล่งเงิน</h1>
                        <p class="text-muted mb-0">ข้อมูลสรุปงบประมาณรายแหล่งเงิน ปี <?= htmlspecialchars($filterYear) ?></p>
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
                        <button type="submit" class="btn btn-primary w-100">ดูข้อมูล</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-12 col-md-3">
                <div class="card stat-card border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">🏦 แหล่งเงินที่ใช้งาน</div>
                        <div class="fs-3 fw-bold text-primary"><?= count($sources) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="card stat-card border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">📋 จำนวนโครงการ</div>
                        <div class="fs-3 fw-bold text-primary"><?= number_format($projectTotal) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="card stat-card border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">💰 งบประมาณจัดสรรทั้งหมด</div>
                        <div class="fs-3 fw-bold text-success">฿<?= number_format($allocTotal, 2) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="card stat-card border-0 h-100">
                    <div class="card-body">
                        <div class="text-muted small">📈 อัตราการใช้จ่าย</div>
                        <div class="fs-3 fw-bold text-info"><?= $overallPercent ?>%</div>
                        <div class="small text-muted">ใช้ไป ฿<?= number_format($usedTotal, 2) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="section-title mb-3">📊 สรุปงบประมาณรายแหล่งเงิน ปี <?= htmlspecialchars($filterYear) ?></h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>แหล่งงบประมาณ</th>
                                <th>จำนวนโครงการ</th>
                                <th class="text-end">งบจัดสรร</th>
                                <th class="text-end">เบิกจ่ายแล้ว</th>
                                <th class="text-end">คงเหลือ</th>
                                <th>อัตราการใช้จ่าย</th>
                                <th>สถานะ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($budgetRows) === 0): ?>
                                <tr><td colspan="8" class="text-center text-muted py-4">ไม่พบข้อมูลแหล่งงบประมาณสำหรับปีที่เลือก</td></tr>
                            <?php endif; ?>
                            <?php foreach ($budgetRows as $i => $row): ?>
                                <?php
                                $alloc = (float)$row['total_allocated'];
                                $used = (float)$row['total_used'];
                                $remain = max(0, $alloc - $used);
                                $pct = $alloc > 0 ? round(($used / $alloc) * 100, 1) : 0;
                                $isActive = (int)$row['is_active'] === 1 && $row['status'] === 'active';
                                ?>
                                <tr class="<?= $isActive ? '' : 'text-muted' ?>">
                                    <td><?= $i + 1 ?></td>
                                    <td class="fw-semibold"><?= htmlspecialchars($row['source_name']) ?></td>
                                    <td><?= (int)$row['project_count'] ?></td>
                                    <td class="text-end"><?= number_format($alloc, 2) ?></td>
                                    <td class="text-end"><?= number_format($used, 2) ?></td>
                                    <td class="text-end"><?= number_format($remain, 2) ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="progress progress-bar-custom flex-grow-1" style="height:6px;">
                                                <div class="progress-bar" style="width:<?= $pct ?>%"></div>
                                            </div>
                                            <span class="small fw-semibold <?= $pct >= 80 ? 'text-success' : ($pct >= 50 ? 'text-warning' : 'text-muted') ?>"><?= $pct ?>%</span>
                                        </div>
                                    </td>
                                    <td><?= $isActive ? '<span class="badge bg-success-subtle text-success">ใช้งาน</span>' : '<span class="badge bg-secondary-subtle text-secondary">ระงับ</span>' ?></td>
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
