<?php
error_reporting(E_ALL);
// Show errors only in local development; hide in production to avoid leaking details
if (isset($_SERVER['SERVER_ADDR']) && in_array($_SERVER['SERVER_ADDR'], array('127.0.0.1', '::1'), true)) {
    ini_set('display_errors', 1);
} else {
    ini_set('display_errors', 0);
}
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

$fiscalYear = !empty($fiscal_year) ? $fiscal_year : date('Y') + 543;
$filterYear = isset($_GET['year']) ? trim($_GET['year']) : $fiscalYear;
$escapedYear = $conn->real_escape_string($filterYear);
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;

// Scope dashboard data to the logged-in user's parent agency (admin & plan see all)
$agencyScope = '';
$schoolScope = '';
if (isLoggedIn() && !isAdminOrPlan()) {
    $ua = (int)currentAgencyId();
    if ($ua > 0) {
        $agencyScope = " AND p.agency_id = " . $ua;
        $schoolScope = " AND a.id = " . $ua;
    }
}

$yearOptions = array();
$yearRes = $conn->query("SELECT DISTINCT fiscal_year FROM projects ORDER BY fiscal_year DESC LIMIT 5");
if ($yearRes) {
    while ($row = $yearRes->fetch_assoc()) $yearOptions[] = $row['fiscal_year'];
}
if (empty($yearOptions) || !in_array($fiscalYear, $yearOptions)) {
    $current = (int)$fiscalYear;
    $yearOptions = array($current);
    for ($i = 1; $i < 5; $i++) {
        $y = $current - $i;
        if ($y >= 2565) $yearOptions[] = (string)$y;
    }
}

// นับจำนวนโครงการทั้งหมด (ยกเว้น status='ระงับ')
$countSql = "SELECT COUNT(*) AS total FROM projects p WHERE p.fiscal_year = '$escapedYear' AND (p.status IS NULL OR p.status != 'ระงับ')" . $agencyScope;
$countRes = $conn->query($countSql);
$countRow = $countRes ? $countRes->fetch_assoc() : array('total' => 0);
$totalProjects = (int)$countRow['total'];

// งบประมาณเฉพาะ is_office_total = 1
$budgetSql = "
    SELECT
        COALESCE(SUM(CAST(p.budget_allocated AS DECIMAL(15,2))), 0) AS total_allocated,
        COALESCE(SUM(CAST(p.budget_used AS DECIMAL(15,2))), 0) AS total_used
    FROM projects p
    WHERE p.fiscal_year = '{$escapedYear}' AND p.is_office_total = 1
" . $agencyScope;
$budgetRes = $conn->query($budgetSql);
$budgetRow = $budgetRes ? $budgetRes->fetch_assoc() : array('total_allocated' => 0, 'total_used' => 0);
$totalAllocated = (float)$budgetRow['total_allocated'];
$totalUsed = (float)$budgetRow['total_used'];
$overallProgress = $totalAllocated > 0 ? round(($totalUsed / $totalAllocated) * 100, 2) : 0;

// งบประมาณภาพรวม (is_office_total = 1)
$officeTotalSql = "
    SELECT
        COUNT(*) AS cnt,
        COALESCE(SUM(CAST(p.budget_allocated AS DECIMAL(15,2))), 0) AS allocated,
        COALESCE(SUM(CAST(p.budget_used AS DECIMAL(15,2))), 0) AS used
    FROM projects p
    WHERE p.fiscal_year = '{$escapedYear}' AND p.is_office_total = 1
" . $agencyScope;
$officeTotalRes = $conn->query($officeTotalSql);
$officeTotal = $officeTotalRes ? $officeTotalRes->fetch_assoc() : array('cnt' => 0, 'allocated' => 0, 'used' => 0);
$officeAllocated = (float)$officeTotal['allocated'];
$officeUsed = (float)$officeTotal['used'];
$officeRemaining = max(0, $officeAllocated - $officeUsed);
$officeProgress = $officeAllocated > 0 ? round(($officeUsed / $officeAllocated) * 100, 2) : 0;

$statusSql = "
    SELECT COALESCE(NULLIF(TRIM(result_status), ''), 'ยังไม่ระบุ') AS rs, COUNT(*) AS cnt
    FROM projects p
    WHERE p.fiscal_year = '{$escapedYear}'
" . $agencyScope . "
    GROUP BY rs
";
$statusRes = $conn->query($statusSql);
$statusCounts = array();
while ($row = $statusRes->fetch_assoc()) {
    $statusCounts[$row['rs']] = (int)$row['cnt'];
}

$resultMet = (int)(isset($statusCounts['บรรลุ']) ? $statusCounts['บรรลุ'] : 0);
$resultInProgress = (int)(isset($statusCounts['ระหว่างดำเนินการ']) ? $statusCounts['ระหว่างดำเนินการ'] : 0);
$resultNotMet = (int)(isset($statusCounts['ไม่บรรลุ']) ? $statusCounts['ไม่บรรลุ'] : 0);
$resultUnknown = (int)(isset($statusCounts['ยังไม่ระบุ']) ? $statusCounts['ยังไม่ระบุ'] : 0);

$strategySql = "
    SELECT si.issue_no, si.issue_name, COUNT(p.id) AS project_count, COALESCE(SUM(CAST(p.budget_allocated AS DECIMAL(15,2))), 0) AS budget_total
    FROM strategic_issues si
    LEFT JOIN project_strategic_issues psi ON psi.strategic_issue_id = si.id AND psi.source = 'project'
    LEFT JOIN projects p ON p.id = psi.project_id AND p.fiscal_year = '{$escapedYear}'
" . $agencyScope . "
    WHERE si.fiscal_year = '{$escapedYear}'
    GROUP BY si.id, si.issue_no, si.issue_name
    ORDER BY si.issue_no ASC
";
$strategyRes = $conn->query($strategySql);
$strategies = array();
while ($row = $strategyRes->fetch_assoc()) {
    $strategies[] = $row;
}

$schoolSql = "
    SELECT a.agency_name AS school_name, a.sort_order, COUNT(p.id) AS project_count,
           COALESCE(SUM(CAST(p.budget_allocated AS DECIMAL(15,2))), 0) AS budget_total,
           COALESCE(SUM(CAST(p.budget_used AS DECIMAL(15,2))), 0) AS budget_used_total
    FROM agencies a
    LEFT JOIN projects p ON p.agency_id = a.id AND p.fiscal_year = '{$escapedYear}'
    WHERE 1=1
" . $schoolScope . "
    GROUP BY a.id, a.agency_name, a.sort_order
    HAVING COUNT(p.id) > 0
    ORDER BY a.sort_order ASC, a.agency_name ASC
";
$schoolRes = $conn->query($schoolSql);
$schools = array();
while ($row = $schoolRes->fetch_assoc()) {
    $schools[] = $row;
}

$sourceSql = "
    SELECT bi.source_name, COUNT(p.id) AS project_count, COALESCE(SUM(CAST(p.budget_allocated AS DECIMAL(15,2))), 0) AS budget_total
    FROM budget_income bi
    LEFT JOIN projects p ON p.budget_source = bi.source_name AND p.fiscal_year = '{$escapedYear}'
" . $agencyScope . "
    WHERE bi.fiscal_year = '{$escapedYear}'
    GROUP BY bi.id, bi.source_name
    ORDER BY budget_total DESC, bi.source_name ASC
";
$sourceRes = $conn->query($sourceSql);
$sources = array();
while ($row = $sourceRes->fetch_assoc()) {
    $sources[] = $row;
}

$allProjectsCountSql = "
    SELECT COUNT(*) AS total_projects
    FROM projects p
    WHERE p.fiscal_year = '{$escapedYear}'
" . $agencyScope . "
";
$allProjectsCountRes = $conn->query($allProjectsCountSql);
$totalProjectRows = 0;
if ($allProjectsCountRes) {
    $countRow = $allProjectsCountRes->fetch_assoc();
    $totalProjectRows = isset($countRow['total_projects']) ? (int)$countRow['total_projects'] : 0;
}
$totalPages = max(1, (int)ceil($totalProjectRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$allProjectsSql = "
    SELECT p.id, p.project_id, p.title, p.status, p.progress, p.budget_allocated, p.budget_used, p.department, p.result_status,
           a.agency_name AS school_name, u.name AS owner_name,
           (SELECT GROUP_CONCAT(si.issue_name SEPARATOR ' / ') FROM project_strategic_issues psi JOIN strategic_issues si ON si.id = psi.strategic_issue_id WHERE psi.source = 'project' AND psi.project_id = p.id) AS strategy_names
    FROM projects p
    LEFT JOIN agencies a ON a.id = p.agency_id
    LEFT JOIN users u ON u.username = p.username
    WHERE p.fiscal_year = '{$escapedYear}'
" . $agencyScope . "
    ORDER BY p.created_at DESC
    LIMIT " . (int)$perPage . " OFFSET " . (int)$offset . "
";
$allProjectsRes = $conn->query($allProjectsSql);
$allProjects = array();
while ($row = $allProjectsRes->fetch_assoc()) {
    $allProjects[] = $row;
}

$chartTotal = max(1, $resultMet + $resultInProgress + $resultNotMet + $resultUnknown);
$resultMetPct = round(($resultMet / $chartTotal) * 100, 1);
$resultInProgressPct = round(($resultInProgress / $chartTotal) * 100, 1);
$resultNotMetPct = round(($resultNotMet / $chartTotal) * 100, 1);
$resultUnknownPct = round(($resultUnknown / $chartTotal) * 100, 1);

// ---- กราฟแท่ง: สัดส่วนการดำเนินการโครงการ พร้อมแสดงจำนวนและร้อยละ ----
$statusOrder = array('บรรลุ', 'ระหว่างดำเนินการ', 'ไม่บรรลุ', 'ยังไม่ระบุ');
$statusColors = array(
    'บรรลุ' => '#86efac',
    'ระหว่างดำเนินการ' => '#93c5fd',
    'ไม่บรรลุ' => '#fdba74',
    'ยังไม่ระบุ' => '#e2e8f0',
);
$statusCounts = array(
    'บรรลุ' => $resultMet,
    'ระหว่างดำเนินการ' => $resultInProgress,
    'ไม่บรรลุ' => $resultNotMet,
    'ยังไม่ระบุ' => $resultUnknown,
);
$chartBars = array();
foreach ($statusOrder as $statusLabel) {
    $chartBars[] = array(
        'label' => $statusLabel,
        'color' => $statusColors[$statusLabel],
        'count' => (int)$statusCounts[$statusLabel],
        'frac'  => (int)$statusCounts[$statusLabel] / $chartTotal,
        'pct'   => round(((int)$statusCounts[$statusLabel] / $chartTotal) * 100, 1),
    );
}

// KPI overview: shared provincial KPIs for the selected year + aligned project count
$kpiSql = "
    SELECT k.id, k.kpi_name, k.target_percent, k.success_indicator, k.scope_type, k.status,
           (SELECT COUNT(*) FROM project_kpis pk WHERE pk.kpi_id = k.id) AS aligned_projects
    FROM kpi_definitions k
    WHERE k.fiscal_year = '{$escapedYear}' AND k.status = 'active'
    ORDER BY k.id ASC
";
$kpiRes = $conn->query($kpiSql);
$kpis = array();
if ($kpiRes) {
    while ($krow = $kpiRes->fetch_assoc()) {
        $kpis[] = $krow;
    }
}

function buildDashboardProjectPageUrl($page, $filterYear)
{
    return 'index.php?year=' . urlencode($filterYear) . '&page=' . (int)$page;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | <?= htmlspecialchars($office_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/style.php'; ?>
</head>
<body>
<?php $activePage = 'index'; include __DIR__ . '/menu.php'; ?>
        <div class="container-fluid">

            <div class="card border-0 shadow-sm rounded-4 mb-4 hero-panel">
                <div class="card-body p-4">
                    <div>
                        <div class="text-uppercase section-title mb-2">📍 <?= $project_name_thai ?></div>
                        <h1 class="h2 fw-bold mb-2" style="color: #111827;"><?= $project_name_eng ?></h1>
                    </div>
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <span class="text-muted"><?= htmlspecialchars($office_name) ?> • ปีงบประมาณ :</span>
                        <form method="get" class="d-flex align-items-center gap-1 mb-0">
                            <select name="year" class="form-select form-select-sm" style="width:auto;min-width:100px;" onchange="this.form.submit()">
                                <?php foreach ($yearOptions as $y): ?>
                                    <option value="<?= htmlspecialchars($y) ?>" <?= $filterYear === $y ? 'selected' : '' ?>><?= htmlspecialchars($y) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <a href="projects.php" class="section-action">โครงการทั้งหมด →</a>
                    </div>
                </div>
            </div>

            <?php if ($officeAllocated > 0): ?>
            <div class="rounded-4 p-4 text-white shadow-lg mb-4" style="background: linear-gradient(135deg, var(--primary), var(--secondary));">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <h2 class="h4 fw-bold mb-2">📊 สรุปงบประมาณภาพรวม</h2>
                        <p class="mb-0 opacity-90">ติดตามความก้าวหน้าของโครงการด้านการศึกษาในจังหวัดนราธิวาส<br>พร้อมแสดงการใช้จ่ายงบประมาณแบบเรียลไทม์</p>
                    </div>
                </div>
                <div class="row g-3 mt-2">
                    <div class="col-6 col-md-3">
                        <div class="small opacity-75">📋 จำนวนโครงการ</div>
                        <div class="fw-bold fs-5"><?= number_format($totalProjects) ?></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="small opacity-75">💰 งบจัดสรร</div>
                        <div class="fw-bold fs-5">฿<?= number_format($officeAllocated, 2) ?></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="small opacity-75">✅ ใช้ไปแล้ว</div>
                        <div class="fw-bold fs-5">฿<?= number_format($officeUsed, 2) ?></div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="small opacity-75">🧮 คงเหลือ</div>
                        <div class="fw-bold fs-5 <?= $officeRemaining > 0 ? 'text-warning' : 'text-danger' ?>">฿<?= number_format($officeRemaining, 2) ?></div>
                    </div>
                </div>
                <div class="row g-3 mt-2">
                    <div class="col-12 col-md-6">
                        <div class="small opacity-75 mb-1">ใช้ไปแล้ว</div>
                        <div class="fw-bold fs-5"><?= $officeProgress ?>%</div>
                        <div class="progress mt-1" style="height:8px;background:rgba(255,255,255,0.2);">
                            <div class="progress-bar bg-white" style="width: <?= $officeProgress ?>%"></div>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="small opacity-75 mb-1">📊 สัดส่วนการดำเนินการโครงการ</div>
                        <div class="d-flex flex-column gap-2">
                            <?php foreach ($chartBars as $chartBar): ?>
                                <div>
                                    <div class="d-flex justify-content-between align-items-center small mb-1" style="line-height:1.2;">
                                        <span class="text-white"><span style="color:<?= $chartBar['color'] ?>">●</span> <?= $chartBar['label'] ?></span>
                                        <span class="text-white-50"><?= $chartBar['count'] ?> โครงการ (<?= $chartBar['pct'] ?>%)</span>
                                    </div>
                                    <div style="position:relative;">
                                        <div class="progress-bar-custom" style="height:12px;background:rgba(255,255,255,0.20);box-shadow:inset 0 1px 4px rgba(15,23,42,0.35);">
                                            <div style="width: <?= round($chartBar['frac'] * 100, 3) ?>%; background: linear-gradient(180deg, rgba(255,255,255,0.55) 0%, rgba(255,255,255,0.18) 45%, rgba(15,23,42,0.12) 100%), <?= $chartBar['color'] ?>; box-shadow: inset 0 1px 0 rgba(255,255,255,0.65), 0 2px 5px rgba(15,23,42,0.35); border-radius:999px;"></div>
                                        </div>
                                        <?php if ($chartBar['count'] > 0): ?>
                                            <div class="bar-ref-line" style="left: <?= round($chartBar['frac'] * 100, 3) ?>%;"></div>
                                            <?php if ($chartBar['frac'] * 100 >= 90): ?>
                                                <span class="bar-pct-label" style="right: <?= round((1 - $chartBar['frac']) * 100, 3) ?>%; margin-right:4px;"><?= $chartBar['pct'] ?>%</span>
                                            <?php else: ?>
                                                <span class="bar-pct-label" style="left: <?= round($chartBar['frac'] * 100, 3) ?>%; margin-left:4px;"><?= $chartBar['pct'] ?>%</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="row g-4 mb-4">
                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="section-title mb-0">🎯 โครงการแยกตามยุทธศาสตร์</h5>
                            </div>
                            <?php foreach ($strategies as $row): ?>
                                <div class="list-row">
                                    <span>
                                        <div>ยุทธศาสตร์ที่ <?= (int)$row['issue_no'] ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars($row['issue_name']) ?></div>
                                    </span>
                                    <strong><?= (int)$row['project_count'] ?><br> โครงการ</strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="section-title mb-0">🏫 โครงการแยกตามหน่วยงานการศึกษา</h5>
                            </div>
                            <?php foreach ($schools as $row): ?>
                                <div class="list-row"><span><?= htmlspecialchars($row['school_name']) ?></span><strong><?= (int)$row['project_count'] ?><br> โครงการ</strong></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="section-title mb-0">💰 งบประมาณที่ได้รับแยกตามหน่วยงาน</h5>
                            </div>
                            <?php foreach ($schools as $row): ?>
                                <div class="list-row"><span><?= htmlspecialchars($row['school_name']) ?></span><strong>฿<?= number_format((float)$row['budget_total'], 2) ?></strong></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h5 class="fw-bold mb-3">🏦 แหล่งที่มาของงบประมาณ</h5>
                            <?php foreach ($sources as $row): ?>
                                <div class="d-flex justify-content-between py-2 border-bottom small"><span><?= htmlspecialchars($row['source_name']) ?></span><strong>฿<?= number_format((float)$row['budget_total'], 2) ?></strong></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end mb-2">
                <a class="btn btn-primary btn-sm" href="projects.php">ดูโครงการทั้งหมด →</a>
            </div>

            <?php if (!empty($kpis)): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h5 class="fw-bold mb-0">📐 ตัวชี้วัด KPI ของจังหวัด (ปีงบประมาณ <?= htmlspecialchars($filterYear) ?>)</h5>
                        <?php if (isAdminOrPlan()): ?>
                            <a class="section-action small" href="kpi_management.php">จัดการ KPI →</a>
                        <?php endif; ?>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>ตัวชี้วัด</th>
                                    <th class="text-center">ค่าเป้าหมาย</th>
                                    <th class="text-center">ระดับ</th>
                                    <th class="text-center">โครงการที่สอดคล้อง</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($kpis as $ki => $kpi): ?>
                                    <tr>
                                        <td class="text-muted"><?= $ki + 1 ?></td>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($kpi['kpi_name']) ?></div>
                                            <?php if (!empty($kpi['success_indicator'])): ?>
                                                <div class="small text-muted" style="max-width:560px;"><?= htmlspecialchars($kpi['success_indicator']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center fw-bold text-primary"><?= htmlspecialchars(rtrim(rtrim(number_format((float)$kpi['target_percent'], 2), '0'), '.')) ?>%</td>
                                        <td class="text-center"><span class="badge bg-primary-subtle text-primary-emphasis"><?= $kpi['scope_type'] === 'province' ? 'จังหวัด' : 'หน่วยงาน' ?></span></td>
                                        <td class="text-center"><span class="badge rounded-pill <?= (int)$kpi['aligned_projects'] > 0 ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' ?>"><?= (int)$kpi['aligned_projects'] ?> โครงการ</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">📋 ตารางแสดงสถานะความก้าวหน้าโครงการ</h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>ลำดับ</th>
                                    <th>ชื่อโครงการ/หน่วยงาน/ยุทธศาสตร์/ผู้รับผิดชอบ</th>
                                    <th>สถานะความก้าวหน้า</th>
                                    <th>งบที่จัดสรร/ใช้ไป</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allProjects as $index => $project): ?>
                                    <?php
                                    $rowNumber = (($page - 1) * $perPage) + $index + 1;
                                    $achieve = checkProjectAchieved($conn, 'project', (int)$project['id']);
                                    ?>
                                    <tr>
                                        <td><?= $rowNumber ?></td>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars($project['title']) ?></div>
                                            <div class="small text-muted">หน่วยงาน: <?= htmlspecialchars($project['school_name'] ?: '-') ?></div>
                                            <div class="small text-muted">ยุทธศาสตร์: <?= htmlspecialchars($project['strategy_names'] ?: '-') ?></div>
                                            <div class="small text-muted">ผู้รับผิดชอบ: <?= htmlspecialchars($project['owner_name'] ?: '-') ?></div>
                                            <div class="mt-1"><?= projectAchievedBadge($achieve) ?></div>
                                        </td>
                                        <td>
                                            <?php
                                            $dashRs = resultStatusBadge(isset($project['result_status']) ? $project['result_status'] : '');
                                            echo $dashRs !== '' ? $dashRs : '<span class="badge rounded-pill bg-success-subtle text-success-emphasis">' . htmlspecialchars($project['status'] ?: '-') . '</span>';
                                            ?>
                                            <br>
                                            <span class="small text-muted"><?= (int)$project['progress'] ?>%</span>
                                        </td>
                                        <td>
                                            <div class="small">จัดสรร <font color="green"><?= number_format((float)$project['budget_allocated'], 2) ?></font></div>
                                            <div class="small text-muted">ใช้ไป <font color="red"><?= number_format((float)$project['budget_used'], 2) ?></font></div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($totalPages > 1): ?>
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 mt-4">
                            <div class="small text-muted">แสดงหน้า <?= $page ?> จาก <?= $totalPages ?> หน้า · รวม <?= number_format($totalProjectRows) ?> โครงการ</div>
                            <nav aria-label="Pagination">
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= buildDashboardProjectPageUrl($page - 1, $filterYear) ?>">ก่อนหน้า</a>
                                    </li>
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                            <a class="page-link" href="<?= buildDashboardProjectPageUrl($i, $filterYear) ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= buildDashboardProjectPageUrl($page + 1, $filterYear) ?>">ถัดไป</a>
                                    </li>
                                </ul>
                            </nav>
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
