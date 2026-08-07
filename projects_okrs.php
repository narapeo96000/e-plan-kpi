<?php
require_once __DIR__ . '/db.php';
requireLogin();

// Fetch projects (Admin sees all; users see OKRs they own or that belong to their agency)
$isAdmin = isAdmin();
$currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$currentAgencyId = (int)currentAgencyId();

if ($isAdmin) {
    $sql = "SELECT DISTINCT p.* FROM okr_projects p ORDER BY p.created_at DESC";
} else {
    $sql = "SELECT DISTINCT p.* FROM okr_projects p
            LEFT JOIN okr_key_results kr ON kr.project_id = p.id
            LEFT JOIN okr_agency_targets t ON t.key_result_id = kr.id AND t.agency_id = " . $currentAgencyId . "
            WHERE p.owner_user_id = " . $currentUserId . " OR t.id IS NOT NULL
            ORDER BY p.created_at DESC";
}
$res = $conn->query($sql);
$projects = [];
if ($res) {
    while ($row = $res->fetch_assoc()) $projects[] = $row;
}

?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>รายการโครงการ | OKR</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-800">
  <div class="max-w-6xl mx-auto py-8">
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-semibold">รายการโครงการ</h1>
      <div class="space-x-2">
        <a href="project_edit.php" class="px-4 py-2 bg-green-600 text-white rounded">+ สร้างโครงการใหม่</a>
      </div>
    </div>

    <div class="space-y-4">
      <?php if (empty($projects)): ?>
        <div class="bg-white p-4 rounded shadow">ไม่มีโครงการ</div>
      <?php endif; ?>
      <?php foreach ($projects as $p): ?>
        <div class="bg-white p-4 rounded shadow flex items-center justify-between">
          <div>
            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($p['project_code']); ?> • <?php echo htmlspecialchars($p['fiscal_year']); ?></div>
            <div class="text-lg font-medium"><?php echo htmlspecialchars($p['project_name']); ?></div>
            <div class="text-sm text-gray-600 mt-1">หน่วยงาน: <?php echo htmlspecialchars($p['department']); ?> | งบประมาณ: <?php echo number_format($p['budget'],2); ?> บาท</div>
            <div class="text-sm text-gray-600 mt-1">สถานะ: <?php echo htmlspecialchars($p['status']); ?> | ความก้าวหน้า: <?php echo htmlspecialchars($p['progress_percent']); ?>%</div>
            <div class="mt-1"><?php echo projectAchievedBadge(checkProjectAchieved($conn, 'okr', (int)$p['id'])); ?></div>
          </div>
          <div class="flex items-center space-x-2">
            <a class="px-3 py-1 border rounded" href="pview_project.php?id=<?php echo $p['id']; ?>">ดู</a>
            <?php if ($isAdmin || ($p['owner_user_id'] && $p['owner_user_id'] == $currentUserId)): ?>
              <a class="px-3 py-1 bg-blue-600 text-white rounded" href="project_edit.php?id=<?php echo $p['id']; ?>">แก้ไข</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</body>
</html>
