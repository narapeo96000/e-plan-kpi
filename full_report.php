<?php
require_once __DIR__ . '/db.php';
requireLogin();

$fiscalYear = !empty($fiscal_year) ? $fiscal_year : date('Y') + 543;
$filterYear = isset($_GET['year']) ? trim($_GET['year']) : $fiscalYear;
$escapedYear = $conn->real_escape_string($filterYear);

// Scope: admin/plan see all, office/user see own agency only
$agencyScope = '';
$agencyLabel = 'ทุกหน่วยงาน';
if (isLoggedIn() && !isAdminOrPlan()) {
    $ua = (int)currentAgencyId();
    if ($ua > 0) {
        $agencyScope = " AND p.agency_id = " . $ua;
        $aRes = $conn->query("SELECT agency_name FROM agencies WHERE id = $ua LIMIT 1");
        if ($aRes && $aRow = $aRes->fetch_assoc()) {
            $agencyLabel = htmlspecialchars($aRow['agency_name']);
        }
    }
}

// Summary
$summary = array();
$res = $conn->query("
    SELECT COUNT(*) AS total_projects,
           COALESCE(SUM(p.budget_allocated), 0) AS total_allocated,
           COALESCE(SUM(p.budget_used), 0) AS total_used
    FROM projects p WHERE p.fiscal_year = '$escapedYear'
" . $agencyScope);
if ($res) $summary = $res->fetch_assoc();

$totalProjects = (int)(isset($summary['total_projects']) ? $summary['total_projects'] : 0);
$totalAllocated = (float)(isset($summary['total_allocated']) ? $summary['total_allocated'] : 0);
$totalUsed = (float)(isset($summary['total_used']) ? $summary['total_used'] : 0);
$totalRemain = max(0, $totalAllocated - $totalUsed);
$overallPercent = $totalAllocated > 0 ? round(($totalUsed / $totalAllocated) * 100, 1) : 0;

// Status stats
$statusStats = array();
$res = $conn->query("
    SELECT p.status, COUNT(*) AS cnt,
           COALESCE(SUM(p.budget_allocated), 0) AS alloc,
           COALESCE(SUM(p.budget_used), 0) AS used
    FROM projects p WHERE p.fiscal_year = '$escapedYear'
" . $agencyScope . " GROUP BY p.status ORDER BY cnt DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) $statusStats[] = $row;
}

// Agency summary
$agencyStats = array();
$res = $conn->query("
    SELECT a.agency_name,
           COUNT(*) AS project_count,
           COALESCE(SUM(p.budget_allocated), 0) AS alloc,
           COALESCE(SUM(p.budget_used), 0) AS used
    FROM projects p
    LEFT JOIN agencies a ON a.id = p.agency_id
    WHERE p.fiscal_year = '$escapedYear'
" . $agencyScope . " GROUP BY p.agency_id ORDER BY a.sort_order ASC, a.agency_name ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) $agencyStats[] = $row;
}

// All projects detail
$projects = array();
$res = $conn->query("
    SELECT p.*, a.agency_name AS school_name
    FROM projects p
    LEFT JOIN agencies a ON a.id = p.agency_id
    WHERE p.fiscal_year = '$escapedYear'
" . $agencyScope . " ORDER BY a.sort_order ASC, a.agency_name ASC, p.title ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) $projects[] = $row;
}

// Transaction summary
$txSummary = array('tx_count' => 0, 'tx_total' => 0);
$res = $conn->query("
    SELECT COALESCE(COUNT(*), 0) AS tx_count, COALESCE(SUM(t.amount), 0) AS tx_total
    FROM budget_transactions t
    JOIN projects p ON p.id = t.project_id
    WHERE p.fiscal_year = '$escapedYear'
" . $agencyScope);
if ($res && $row = $res->fetch_assoc()) {
    $txSummary = $row;
}

// Year list
$years = array();
$yearsSql = "SELECT DISTINCT fiscal_year FROM projects";
if (!isAdminOrPlan()) {
    $ua = (int)currentAgencyId();
    if ($ua > 0) $yearsSql .= " WHERE agency_id = $ua";
}
$yearsSql .= " ORDER BY fiscal_year DESC";
$res = $conn->query($yearsSql);
if ($res) {
    while ($row = $res->fetch_assoc()) $years[] = $row['fiscal_year'];
}
if (empty($years)) $years = array($fiscalYear);

$statusMap = array(
    'ยังไม่เริ่ม' => 'bg-secondary-subtle text-secondary-emphasis',
    'ระหว่างดำเนินการ' => 'bg-primary-subtle text-primary-emphasis',
    'เสร็จสิ้น' => 'bg-success-subtle text-success-emphasis',
    'ยกเลิก' => 'bg-danger-subtle text-danger-emphasis',
);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายงานแบบเต็มรูปแบบ | <?= htmlspecialchars($office_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/style.php'; ?>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .card { box-shadow: none !important; border: 1px solid #ddd !important; }
            .main-content { margin-left: 0 !important; padding: 0 !important; }
            .sidebar, .mobile-header, .offcanvas, .no-print { display: none !important; }
        }
        .report-header { border-bottom: 3px double #333; padding-bottom: 1rem; margin-bottom: 1.5rem; }
        .report-title { font-size: 1.5rem; font-weight: 700; }
        .report-subtitle { font-size: 1.1rem; color: #555; }
        .stat-box { background: #f8fafc; border-radius: .75rem; padding: 1.25rem; text-align: center; border: 1px solid #e2e8f0; }
        .stat-number { font-size: 1.6rem; font-weight: 700; color: var(--primary, #731e8a); }
        .table-report th { background: #f1f5f9; font-weight: 600; }
        .section-title-report { font-size: 1.1rem; font-weight: 700; color: #1e293b; margin: 1.5rem 0 .75rem; border-left: 4px solid var(--primary, #731e8a); padding-left: .75rem; }
    </style>
</head>
<body>
<?php $activePage = 'full_report'; include __DIR__ . '/menu.php'; ?>
    <div class="container-fluid">
        <div class="card border-0 shadow-sm rounded-4 mb-4 no-print">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <div class="text-uppercase section-title mb-2">📄 Full Report Preview</div>
                        <h1 class="h3 fw-bold mb-2">รายงานแบบเต็มรูปแบบ</h1>
                        <p class="text-muted mb-0">ดูตัวอย่างก่อนพิมพ์ หรือส่งออก PDF</p>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-outline-secondary" onclick="window.print()">🖨️ พิมพ์ / Print Preview</button>
                        <a class="btn btn-danger" href="export_pdf.php?year=<?= urlencode($filterYear) ?>">📤 ส่งออก PDF</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 mb-4 no-print">
            <div class="card-body p-4">
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-12 col-md-4 col-lg-3">
                        <label class="form-label">ปีงบประมาณ</label>
                        <select name="year" class="form-select" onchange="this.form.submit()">
                            <?php foreach ($years as $y): ?>
                                <option value="<?= htmlspecialchars($y) ?>" <?= $y === $filterYear ? 'selected' : '' ?>><?= htmlspecialchars($y) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-4 col-lg-3">
                        <label class="form-label">ขอบเขต</label>
                        <input type="text" class="form-control" value="<?= $agencyLabel ?>" readonly>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <div class="report-header text-center">
                    <div class="report-title"><?= htmlspecialchars($office_name) ?></div>
                    <div class="report-subtitle">รายงานสรุปผลการดำเนินงานประจำปีงบประมาณ <?= htmlspecialchars($filterYear) ?></div>
                    <div class="small text-muted mt-1">ขอบเขต: <?= $agencyLabel ?> • วันที่พิมพ์: <?= date('d/m/') . (date('Y') + 543) ?></div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="stat-box">
                            <div class="stat-number"><?= number_format($totalProjects) ?></div>
                            <div class="small text-muted">โครงการทั้งหมด</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-box">
                            <div class="stat-number"><?= number_format($totalAllocated, 2) ?></div>
                            <div class="small text-muted">งบประมาณจัดสรร (บาท)</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-box">
                            <div class="stat-number"><?= number_format($totalUsed, 2) ?></div>
                            <div class="small text-muted">เบิกจ่ายแล้ว (บาท)</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="stat-box">
                            <div class="stat-number"><?= number_format($totalRemain, 2) ?></div>
                            <div class="small text-muted">คงเหลือ (บาท)</div>
                        </div>
                    </div>
                </div>

                <div class="d-flex align-items-center mb-4">
                    <div class="flex-grow-1 me-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span>อัตราการใช้จ่าย</span>
                            <span><?= $overallPercent ?>%</span>
                        </div>
                        <div class="progress" style="height: 18px;">
                            <div class="progress-bar" role="progressbar" style="width: <?= min(100, $overallPercent) ?>%;" aria-valuenow="<?= $overallPercent ?>" aria-valuemin="0" aria-valuemax="100"><?= $overallPercent ?>%</div>
                        </div>
                    </div>
                </div>

                <div class="section-title-report">สรุปตามสถานะโครงการ</div>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-report align-middle">
                        <thead>
                            <tr>
                                <th>สถานะ</th>
                                <th class="text-center">จำนวนโครงการ</th>
                                <th class="text-end">งบจัดสรร (บาท)</th>
                                <th class="text-end">เบิกจ่าย (บาท)</th>
                                <th class="text-center">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($statusStats as $s): ?>
                                <?php $pct = (float)$s['alloc'] > 0 ? round(((float)$s['used'] / (float)$s['alloc']) * 100, 1) : 0; ?>
                                <tr>
                                    <td><?= htmlspecialchars($s['status'] ?: '-') ?></td>
                                    <td class="text-center"><?= number_format((int)$s['cnt']) ?></td>
                                    <td class="text-end"><?= number_format((float)$s['alloc'], 2) ?></td>
                                    <td class="text-end"><?= number_format((float)$s['used'], 2) ?></td>
                                    <td class="text-center"><?= $pct ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($statusStats)): ?>
                                <tr><td colspan="5" class="text-center text-muted">ไม่มีข้อมูล</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="section-title-report">สรุปตามหน่วยงาน</div>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-report align-middle">
                        <thead>
                            <tr>
                                <th>หน่วยงาน</th>
                                <th class="text-center">จำนวนโครงการ</th>
                                <th class="text-end">งบจัดสรร (บาท)</th>
                                <th class="text-end">เบิกจ่าย (บาท)</th>
                                <th class="text-center">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($agencyStats as $a): ?>
                                <?php $pct = (float)$a['alloc'] > 0 ? round(((float)$a['used'] / (float)$a['alloc']) * 100, 1) : 0; ?>
                                <tr>
                                    <td><?= htmlspecialchars($a['agency_name'] ?: 'ไม่ระบุหน่วยงาน') ?></td>
                                    <td class="text-center"><?= number_format((int)$a['project_count']) ?></td>
                                    <td class="text-end"><?= number_format((float)$a['alloc'], 2) ?></td>
                                    <td class="text-end"><?= number_format((float)$a['used'], 2) ?></td>
                                    <td class="text-center"><?= $pct ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($agencyStats)): ?>
                                <tr><td colspan="5" class="text-center text-muted">ไม่มีข้อมูล</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="section-title-report">รายละเอียดโครงการทั้งหมด</div>
                <div class="table-responsive">
                    <table class="table table-bordered table-report align-middle">
                        <thead>
                            <tr>
                                <th class="text-center" style="width:40px">#</th>
                                <th>รหัสโครงการ</th>
                                <th>ชื่อโครงการ</th>
                                <th>หน่วยงาน</th>
                                <th>เจ้าของโครงการ</th>
                                <th>สถานะ</th>
                                <th class="text-end">งบจัดสรร</th>
                                <th class="text-end">เบิกจ่าย</th>
                                <th class="text-center">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($projects as $i => $p): ?>
                                <?php $pct = (float)$p['budget_allocated'] > 0 ? round(((float)$p['budget_used'] / (float)$p['budget_allocated']) * 100, 1) : 0; ?>
                                <tr>
                                    <td class="text-center"><?= $i + 1 ?></td>
                                    <td><?= htmlspecialchars($p['project_id'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars($p['title']) ?></td>
                                    <td><?= htmlspecialchars($p['school_name'] ?: '-') ?></td>
                                    <td><?= htmlspecialchars($p['owner_name'] ?: '-') ?></td>
                                    <td><span class="badge <?= isset($statusMap[$p['status']]) ? $statusMap[$p['status']] : 'bg-light text-dark' ?>"><?= htmlspecialchars($p['status'] ?: '-') ?></span></td>
                                    <td class="text-end"><?= number_format((float)$p['budget_allocated'], 2) ?></td>
                                    <td class="text-end"><?= number_format((float)$p['budget_used'], 2) ?></td>
                                    <td class="text-center"><?= $pct ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($projects)): ?>
                                <tr><td colspan="9" class="text-center text-muted">ไม่มีข้อมูลโครงการ</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ((int)$txSummary['tx_count'] > 0): ?>
                <div class="section-title-report">สรุปรายการเบิกจ่าย</div>
                <div class="table-responsive">
                    <table class="table table-bordered table-report align-middle">
                        <thead>
                            <tr>
                                <th>จำนวนรายการเบิกจ่าย</th>
                                <th class="text-end">ยอดรวมเบิกจ่าย</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><?= number_format((int)$txSummary['tx_count']) ?> รายการ</td>
                                <td class="text-end"><?= number_format((float)$txSummary['tx_total'], 2) ?> บาท</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <div class="text-center text-muted small mt-4 no-print">
                    รายงานนี้เป็นข้อมูลสรุปจากระบบติดตามและรายงานผลการดำเนินงานตามแผนพัฒนาการศึกษาจังหวัดนราธิวาส
                </div>
            </div>
        </div>
    </div>
</body>
</html>
