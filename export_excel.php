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

$data = array();
$res = $conn->query("
    SELECT p.project_id, p.title, a.agency_name AS school_name, p.department,
           p.budget_source, p.budget_allocated, p.budget_used,
           (p.budget_allocated - p.budget_used) AS budget_remain,
           CASE WHEN p.budget_allocated > 0
                THEN ROUND((p.budget_used / p.budget_allocated) * 100, 1)
                ELSE 0 END AS usage_pct,
           p.status, p.owner_name, p.updated_at
    FROM projects p
    LEFT JOIN agencies a ON a.id = p.agency_id
    WHERE p.fiscal_year = '$escapedYear'
" . $agencyScope . "
    ORDER BY p.title ASC
");
if ($res) {
    while ($row = $res->fetch_assoc()) $data[] = $row;
}

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="report_' . $filterYear . '.xls"');
header('Cache-Control: no-cache, no-store, must-revalidate');

echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
echo '<head><meta charset="UTF-8"><!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>รายงาน</x:Name></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]--></head>';
echo '<body>';
echo '<h2>สรุปรายงานประจำปีงบประมาณ ' . htmlspecialchars($filterYear) . '</h2>';
echo '<table border="1" cellpadding="4" cellspacing="0" style="border-collapse:collapse;">';
echo '<thead>';
echo '<tr style="background:#1e3a8a;color:#fff;">';
echo '<th>รหัสโครงการ</th><th>ชื่อโครงการ</th><th>หน่วยงาน</th><th>แหล่งงบ</th>';
echo '<th>งบจัดสรร</th><th>เบิกจ่าย</th><th>คงเหลือ</th><th>% การใช้จ่าย</th><th>สถานะ</th><th>ผู้รับผิดชอบ</th>';
echo '</tr>';
echo '</thead><tbody>';
$totalAlloc = 0;
$totalUsed = 0;
foreach ($data as $row) {
    $totalAlloc += (float)$row['budget_allocated'];
    $totalUsed += (float)$row['budget_used'];
    echo '<tr>';
    echo '<td>' . htmlspecialchars($row['project_id'] ?: '-') . '</td>';
    echo '<td>' . htmlspecialchars($row['title']) . '</td>';
    echo '<td>' . htmlspecialchars($row['school_name'] ?: $row['department'] ?: '-') . '</td>';
    echo '<td>' . htmlspecialchars($row['budget_source'] ?: '-') . '</td>';
    echo '<td align="right">' . number_format((float)$row['budget_allocated'], 2) . '</td>';
    echo '<td align="right">' . number_format((float)$row['budget_used'], 2) . '</td>';
    echo '<td align="right">' . number_format(max(0, (float)$row['budget_remain']), 2) . '</td>';
    echo '<td align="right">' . (float)$row['usage_pct'] . '%</td>';
    echo '<td>' . htmlspecialchars($row['status'] ?: '-') . '</td>';
    echo '<td>' . htmlspecialchars($row['owner_name'] ?: '-') . '</td>';
    echo '</tr>';
}
echo '<tr style="font-weight:bold;background:#f0f0f0;">';
echo '<td colspan="4" align="right">รวม</td>';
echo '<td align="right">' . number_format($totalAlloc, 2) . '</td>';
echo '<td align="right">' . number_format($totalUsed, 2) . '</td>';
echo '<td align="right">' . number_format(max(0, $totalAlloc - $totalUsed), 2) . '</td>';
echo '<td align="right">' . ($totalAlloc > 0 ? round(($totalUsed / $totalAlloc) * 100, 1) : 0) . '%</td>';
echo '<td colspan="2"></td>';
echo '</tr>';
echo '</tbody></table>';
echo '</body></html>';
