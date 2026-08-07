<?php
require_once __DIR__ . '/db.php';
requireLogin();

$fiscalYear = !empty($fiscal_year) ? $fiscal_year : date('Y') + 543;
$filterYear = isset($_GET['year']) ? trim($_GET['year']) : $fiscalYear;
$escapedYear = $conn->real_escape_string($filterYear);

// Scope export to the logged-in user's parent agency (admin sees all)
$agencyScope = '';
if (isLoggedIn() && !isAdmin()) {
    $ua = (int)currentAgencyId();
    if ($ua > 0) {
        $agencyScope = " AND p.agency_id = " . $ua;
    }
}

$summary = array();
$res = $conn->query("
    SELECT COUNT(*) AS total_projects,
           COALESCE(SUM(p.budget_allocated), 0) AS total_allocated,
           COALESCE(SUM(p.budget_used), 0) AS total_used
    FROM projects p WHERE p.fiscal_year = '$escapedYear'
" . $agencyScope . "
");
if ($res) $summary = $res->fetch_assoc();

$totalProjects = (int)(isset($summary['total_projects']) ? $summary['total_projects'] : 0);
$totalAllocated = (float)(isset($summary['total_allocated']) ? $summary['total_allocated'] : 0);
$totalUsed = (float)(isset($summary['total_used']) ? $summary['total_used'] : 0);
$totalRemain = max(0, $totalAllocated - $totalUsed);
$overallPercent = $totalAllocated > 0 ? round(($totalUsed / $totalAllocated) * 100, 1) : 0;

$data = array();
$res = $conn->query("
    SELECT p.project_id, p.title, a.agency_name AS school_name,
           p.budget_source, p.budget_allocated, p.budget_used, p.status, p.owner_name
    FROM projects p
    LEFT JOIN agencies a ON a.id = p.agency_id
    WHERE p.fiscal_year = '$escapedYear'
" . $agencyScope . "
    ORDER BY p.title ASC
");
if ($res) {
    while ($row = $res->fetch_assoc()) $data[] = $row;
}

$officeName = htmlspecialchars($office_name);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายงานประจำปี <?= htmlspecialchars($filterYear) ?></title>
    <style>
        body { font-family: 'Prompt', 'Sarabun', sans-serif; font-size: 12px; color: #333; padding: 20px; }
        h1 { font-size: 18px; text-align: center; margin-bottom: 5px; }
        h2 { font-size: 14px; text-align: center; color: #666; margin-bottom: 20px; font-weight: normal; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { border: 1px solid #999; padding: 6px 8px; text-align: left; font-size: 11px; }
        th { background: #1e3a8a; color: #fff; text-align: center; }
        .summary-table td { text-align: center; font-weight: bold; font-size: 13px; }
        .summary-table td.label { text-align: left; font-weight: normal; }
        .text-right { text-align: right; }
        .footer { text-align: center; color: #999; font-size: 10px; margin-top: 30px; border-top: 1px solid #ccc; padding-top: 10px; }
        @media print { body { padding: 0; } }
    </style>
</head>
<body>
    <h1><?= $officeName ?></h1>
    <h2>รายงานสรุปผลการดำเนินงานประจำปีงบประมาณ <?= htmlspecialchars($filterYear) ?></h2>

    <table class="summary-table">
        <tr>
            <td class="label">จำนวนโครงการทั้งหมด</td>
            <td><?= number_format($totalProjects) ?> โครงการ</td>
            <td class="label">งบประมาณจัดสรร</td>
            <td><?= number_format($totalAllocated, 2) ?> บาท</td>
        </tr>
        <tr>
            <td class="label">เบิกจ่ายแล้ว</td>
            <td><?= number_format($totalUsed, 2) ?> บาท</td>
            <td class="label">คงเหลือ</td>
            <td><?= number_format($totalRemain, 2) ?> บาท</td>
        </tr>
        <tr>
            <td class="label">อัตราการใช้จ่าย</td>
            <td colspan="3"><?= $overallPercent ?>%</td>
        </tr>
    </table>

    <h3 style="margin-top:20px;">รายละเอียดโครงการทั้งหมด</h3>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>รหัสโครงการ</th>
                <th>ชื่อโครงการ</th>
                <th>หน่วยงาน</th>
                <th>แหล่งงบ</th>
                <th class="text-right">งบจัดสรร</th>
                <th class="text-right">เบิกจ่าย</th>
                <th>สถานะ</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($data as $i => $row): ?>
            <tr>
                <td style="text-align:center"><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($row['project_id'] ?: '-') ?></td>
                <td><?= htmlspecialchars($row['title']) ?></td>
                <td><?= htmlspecialchars($row['school_name'] ?: '-') ?></td>
                <td><?= htmlspecialchars($row['budget_source'] ?: '-') ?></td>
                <td class="text-right"><?= number_format((float)$row['budget_allocated'], 2) ?></td>
                <td class="text-right"><?= number_format((float)$row['budget_used'], 2) ?></td>
                <td style="text-align:center"><?= htmlspecialchars($row['status'] ?: '-') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer">
        รายงานนี้ถูกสร้างจากระบบ e-Plan Monitoring เมื่อ <?= date('d/m/Y H:i:s') ?> น.
    </div>
    <script>window.print();</script>
</body>
</html>
