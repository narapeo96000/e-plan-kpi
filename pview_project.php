<?php
require_once __DIR__ . '/db.php';
requireLogin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    die('Missing project id');
}

// Try okr_projects first, fallback to projects table
$res = $conn->query("SELECT p.*, u.username AS owner_username, u.name AS owner_name FROM okr_projects p LEFT JOIN users u ON u.id = p.owner_user_id WHERE p.id = $id LIMIT 1");
if ($res && $res->num_rows) {
    $project = $res->fetch_assoc();
    $fromOkr = true;
} else {
    // Fallback to main projects table
    $res2 = $conn->query("SELECT p.*, a.agency_name AS school_name FROM projects p LEFT JOIN agencies a ON a.id = p.agency_id WHERE p.id = $id LIMIT 1");
    if (!$res2 || !$res2->num_rows) {
        die('Project not found');
    }
    $project = $res2->fetch_assoc();
    $fromOkr = false;
}

// ===== Access control =====
// View:  admin & plan = all; office = own agency; user = own records/agency
// Edit:  admin & office(agency) = all/edit-on-behalf; user = own records only; plan = none
$currentUserId = currentUserId();
$currentAgencyId = (int)currentAgencyId();

$canView = false;
$canEdit = false;
if (isAdminOrPlan()) {
    $canView = true;
    $canEdit = isAdmin();
} elseif ($fromOkr) {
    if ((int)$project['owner_user_id'] === $currentUserId) {
        $canView = true;
        $canEdit = true;
    } elseif ($currentAgencyId > 0) {
        $tRes = $conn->query("SELECT COUNT(*) AS c FROM okr_agency_targets t JOIN okr_key_results kr ON kr.id = t.key_result_id WHERE kr.project_id = " . (int)$id . " AND t.agency_id = " . $currentAgencyId);
        if ($tRes && ($tRow = $tRes->fetch_assoc()) && (int)$tRow['c'] > 0) {
            $canView = true;
        }
    }
} else {
    $canView = canViewProject($project);
    $canEdit = canEditProject($project);
}
if (!$canView) {
    setFlash('error', 'คุณไม่มีสิทธิ์ดูโครงการนี้');
    header('Location: projects.php');
    exit;
}

// Load multi-strategy names (1-to-many via project_strategic_issues)
$strategyNames = array();
$strategySource = $fromOkr ? 'okr' : 'project';
$sRes = $conn->query("SELECT si.issue_name FROM project_strategic_issues psi JOIN strategic_issues si ON si.id = psi.strategic_issue_id WHERE psi.source = '" . $strategySource . "' AND psi.project_id = " . (int)$id . " ORDER BY si.issue_no ASC");
if ($sRes) {
    while ($sRow = $sRes->fetch_assoc()) {
        $strategyNames[] = $sRow['issue_name'];
    }
}
if (empty($strategyNames)) {
    $singleCol = $fromOkr ? (isset($project['strategic_issue_id']) ? $project['strategic_issue_id'] : '') : (isset($project['strategy_id']) ? $project['strategy_id'] : '');
    if ($singleCol) {
        $sRes2 = $conn->query("SELECT issue_name FROM strategic_issues WHERE id = " . (int)$singleCol . " LIMIT 1");
        if ($sRes2 && ($sRow2 = $sRes2->fetch_assoc())) {
            $strategyNames[] = $sRow2['issue_name'];
        }
    }
}
$project['strategic_links'] = implode(', ', $strategyNames);

// Load KPI alignment (1-to-many via project_kpis) — main projects only
$kpiLinks = array();
if (!$fromOkr) {
    $kRes = $conn->query("SELECT k.kpi_name, k.target_percent, k.scope_type FROM project_kpis pk JOIN kpi_definitions k ON k.id = pk.kpi_id WHERE pk.project_id = " . (int)$id . " ORDER BY k.id ASC");
    if ($kRes) {
        while ($kRow = $kRes->fetch_assoc()) {
            $kpiLinks[] = $kRow;
        }
    }
}

$objectives = [];
if ($fromOkr) {
    $oRes = $conn->query("SELECT * FROM okr_objectives WHERE project_id = $id ORDER BY id ASC");
    if ($oRes) {
        while ($o = $oRes->fetch_assoc()) {
            $o['krs'] = [];
            $kRes = $conn->query("SELECT * FROM okr_objective_krs WHERE objective_id = " . (int)$o['id'] . " ORDER BY id ASC");
            if ($kRes) {
                while ($k = $kRes->fetch_assoc()) {
                    $o['krs'][] = $k;
                }
            }
            $objectives[] = $o;
        }
    }
}

// Map fields for unified display
if ($fromOkr) {
    $budget_received = isset($project['budget_received']) ? (float)$project['budget_received'] : 0;
    $budget_spent = isset($project['budget_spent']) ? (float)$project['budget_spent'] : 0;
    $progress_percent = isset($project['progress_percent']) ? (float)$project['progress_percent'] : 0;
} else {
    $budget_received = isset($project['budget_allocated']) ? (float)$project['budget_allocated'] : 0;
    $budget_spent = isset($project['budget_used']) ? (float)$project['budget_used'] : 0;
    $progress_percent = isset($project['progress']) ? (float)$project['progress'] : 0;
    // Map project fields for template compatibility
    $project['project_name'] = isset($project['title']) ? $project['title'] : '';
    $project['project_code'] = isset($project['project_id']) ? $project['project_id'] : '';
    $project['owner_username'] = isset($project['username']) ? $project['username'] : '';
    $project['owner_name'] = isset($project['owner_name']) ? $project['owner_name'] : (isset($project['username']) ? $project['username'] : '');
    $project['department'] = isset($project['department']) ? $project['department'] : '';
    $project['funding_source'] = isset($project['budget_source']) ? $project['budget_source'] : '';
    $project['overall_result'] = isset($project['operation_results']) ? $project['operation_results'] : (isset($project['summary']) ? $project['summary'] : '');
    $project['activities_summary'] = isset($project['operated_activities']) ? $project['operated_activities'] : '';
    $project['issues_summary'] = isset($project['problems_suggestions']) ? $project['problems_suggestions'] : '';
    $project['image_links'] = isset($project['images']) ? $project['images'] : '';
    $project['video_links'] = isset($project['video_links']) ? $project['video_links'] : '';
    $project['document_links'] = isset($project['document_links']) ? $project['document_links'] : '';
    $project['report_links'] = isset($project['report_links']) ? $project['report_links'] : '';
    $project['last_updated_at'] = isset($project['updated_at']) ? $project['updated_at'] : '';
}
$budget_percent = $budget_received > 0 ? round(($budget_spent / $budget_received) * 100, 2) : 0;
$achieve = checkProjectAchieved($conn, $fromOkr ? 'okr' : 'project', (int)$id);

function parseLinks($text) {
    if (!$text) {
        return [];
    }
    $lines = preg_split('/\r?\n/', $text);
    $out = [];
    foreach ($lines as $line) {
        $value = trim($line);
        if ($value !== '') {
            $out[] = $value;
        }
    }
    return $out;
}

function badgeForStatus($status) {
    $status = trim((string)$status);
    $map = [
        'ยังไม่เริ่ม' => ['class' => 'bg-secondary-subtle text-secondary-emphasis', 'icon' => '⏳'],
        'ระหว่างดำเนินการ' => ['class' => 'bg-primary-subtle text-primary-emphasis', 'icon' => '🔄'],
        'ดำเนินการ' => ['class' => 'bg-primary-subtle text-primary-emphasis', 'icon' => '🔄'],
        'เสร็จสิ้น' => ['class' => 'bg-success-subtle text-success-emphasis', 'icon' => '✅'],
        'ระงับ' => ['class' => 'bg-danger-subtle text-danger-emphasis', 'icon' => '⛔'],
    ];
    $info = isset($map[$status]) ? $map[$status] : ['class' => 'bg-light text-dark', 'icon' => '•'];
    return '<span class="badge ' . $info['class'] . ' px-3 py-2">' . $info['icon'] . ' ' . htmlspecialchars($status) . '</span>';
}

function renderLinkList($links, $label) {
    if (empty($links)) {
        return '<div class="text-muted small">— ไม่มี' . $label . 'ที่บันทึกไว้ —</div>';
    }

    $html = '<ul class="list-group list-group-flush">';
    foreach ($links as $link) {
        $html .= '<li class="list-group-item px-0"><a href="' . htmlspecialchars($link) . '" target="_blank" rel="noopener noreferrer" class="text-primary text-decoration-none">' . htmlspecialchars($link) . '</a></li>';
    }
    $html .= '</ul>';
    return $html;
}

$imageLinks = parseLinks(isset($project['image_links']) ? $project['image_links'] : '');
$videoLinks = parseLinks(isset($project['video_links']) ? $project['video_links'] : '');
$docLinks = array_merge(
    parseLinks(isset($project['document_links']) ? $project['document_links'] : ''),
    parseLinks(isset($project['report_links']) ? $project['report_links'] : '')
);
// Uploaded files (เอกสาร/ร่องรอย) — only for main projects table
$projectDocuments = array();
if (!$fromOkr) {
    $projectDocuments = getProjectDocuments($conn, (int)$id);
}
?>
<!doctype html>
<html lang="th">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>สรุปโครงการ: <?= htmlspecialchars($project['project_name']) ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <?php include __DIR__ . '/style.php'; ?>
</head>
<body>
<?php $activePage = 'projects'; include __DIR__ . '/menu.php'; ?>
<div class="container-fluid py-4">
  <div class="card border-0 shadow-sm rounded-4 mb-4 hero-panel">
    <div class="card-body p-4">
      <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
          <div class="text-uppercase section-title mb-2">📋 Project Summary</div>
          <h1 class="h2 fw-bold mb-2"><?= htmlspecialchars($project['project_name']) ?></h1>
          <p class="text-muted mb-0">รหัสโครงการ <?= htmlspecialchars($project['project_code'] ?: '-') ?> • ปีงบประมาณ <?= htmlspecialchars($project['fiscal_year'] ?: '-') ?></p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <a class="btn btn-outline-secondary" href="projects.php">กลับหน้ารายการ</a>
          <?php if ($canEdit): ?>
            <?php if ($fromOkr): ?>
              <a class="btn btn-primary" href="project_edit.php?id=<?= (int)$project['id'] ?>">แก้ไขโครงการ</a>
            <?php else: ?>
              <a class="btn btn-primary" href="project_form.php?id=<?= (int)$project['id'] ?>">แก้ไขโครงการ</a>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4 mb-4">
    <div class="col-12 col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small mb-1">สถานะโครงการ</div>
          <div class="mb-2"><?= badgeForStatus(isset($project['status']) ? $project['status'] : '') ?></div>
          <div class="mb-2"><?= resultStatusBadge(isset($project['result_status']) ? $project['result_status'] : '') ?></div>
          <div class="mb-2"><?= projectAchievedBadge($achieve) ?></div>
          <div class="text-muted small">ความก้าวหน้า</div>
          <div class="fw-bold fs-4"><?= number_format($progress_percent, 1) ?>%</div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small mb-1">ผู้รับผิดชอบ</div>
          <div class="fw-semibold"><?= htmlspecialchars(($project['owner_name'] ?: $project['owner_username']) ?: '-') ?></div>
          <div class="text-muted small mt-2">หน่วยงาน: <?= htmlspecialchars($project['department'] ?: '-') ?></div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small mb-1">งบประมาณที่ได้รับ</div>
          <div class="fw-bold fs-4">฿<?= number_format($budget_received, 2) ?></div>
          <div class="text-muted small mt-2">งบที่ใช้ไป: ฿<?= number_format($budget_spent, 2) ?></div>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small mb-1">สัดส่วนการใช้จ่าย</div>
          <div class="fw-bold fs-4"><?= number_format($budget_percent, 1) ?>%</div>
          <div class="progress mt-2" style="height: 8px;">
            <div class="progress-bar" role="progressbar" style="width: <?= min(100, max(0, $budget_percent)) ?>%;"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-12 col-xl-7">
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
          <h3 class="h5 fw-bold mb-3">ภาพรวมโครงการ</h3>
          <div class="row g-3">
            <div class="col-md-6">
              <div class="border rounded-3 p-3 h-100">
                <div class="text-muted small mb-1">แหล่งงบประมาณ</div>
                <div class="fw-semibold"><?= htmlspecialchars($project['funding_source'] ?: '-') ?></div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="border rounded-3 p-3 h-100">
                <div class="text-muted small mb-1">ยุทธศาสตร์ / Link ที่เกี่ยวข้อง</div>
                <div class="fw-semibold"><?= htmlspecialchars($project['strategic_links'] ?: '-') ?></div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="border rounded-3 p-3 h-100">
                <div class="text-muted small mb-1">ตัวชี้วัด KPI ที่สอดคล้อง</div>
                <?php if (empty($kpiLinks)): ?>
                  <div class="fw-semibold">-</div>
                <?php else: ?>
                  <?php foreach ($kpiLinks as $kn): ?>
                    <span class="badge bg-primary-subtle text-primary-emphasis me-1 mb-1"><?= htmlspecialchars($kn['kpi_name']) ?></span>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>
            <div class="col-12">
              <div class="border rounded-3 p-3">
                <div class="text-muted small mb-1">สรุปผลการดำเนินโครงการ</div>
                <div class="mt-2 text-dark" style="white-space: pre-wrap;">
                  <?= nl2br(htmlspecialchars($project['overall_result'] ?: '-')) ?>
                </div>
              </div>
            </div>
            <div class="col-12">
              <div class="border rounded-3 p-3">
                <div class="text-muted small mb-1">กิจกรรมที่ดำเนิน</div>
                <div class="mt-2 text-dark" style="white-space: pre-wrap;">
                  <?= nl2br(htmlspecialchars($project['activities_summary'] ?: '-')) ?>
                </div>
              </div>
            </div>
            <div class="col-12">
              <div class="border rounded-3 p-3">
                <div class="text-muted small mb-1">ปัญหา / อุปสรรค / ข้อเสนอแนะ</div>
                <div class="mt-2 text-dark" style="white-space: pre-wrap;">
                  <?= nl2br(htmlspecialchars($project['issues_summary'] ?: '-')) ?>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="col-12 col-xl-5">
      <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
          <h3 class="h5 fw-bold mb-3">OKR Summary</h3>
          <?php if (empty($objectives)): ?>
            <div class="text-muted">ยังไม่มี Objective หรือ Key Result ที่บันทึกไว้</div>
          <?php else: ?>
            <?php foreach ($objectives as $objective): ?>
              <div class="border rounded-3 p-3 mb-3">
                <div class="fw-semibold mb-2">Objective</div>
                <div class="text-dark mb-3" style="white-space: pre-wrap;"><?= nl2br(htmlspecialchars(isset($objective['objective_text']) ? $objective['objective_text'] : '-')) ?></div>
                <?php if (!empty($objective['krs'])): ?>
                  <div class="small text-muted mb-2">Key Results</div>
                  <?php foreach ($objective['krs'] as $kr): ?>
                    <div class="border rounded-2 p-3 mb-2 bg-light">
                      <div class="fw-semibold mb-1"><?= htmlspecialchars(isset($kr['kr_text']) ? $kr['kr_text'] : '-') ?></div>
                      <div class="small text-muted">เป้าหมาย: <?= htmlspecialchars(($kr['target_number'] !== null && $kr['target_number'] !== '') ? $kr['target_number'] : '-') ?> <?= htmlspecialchars($kr['unit'] ?: '') ?></div>
                      <div class="small text-muted">สถานะ: <?= htmlspecialchars($kr['status'] ?: '-') ?></div>
                      <?php if (!empty($kr['initiative_text'])): ?>
                        <div class="small mt-2 text-dark">กิจกรรมย่อย: <?= htmlspecialchars($kr['initiative_text']) ?></div>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="small text-muted">ยังไม่มี Key Result ใน Objective นี้</div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-12 col-md-4">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <h3 class="h5 fw-bold mb-3">รูปภาพกิจกรรม</h3>
          <?= renderLinkList($imageLinks, 'รูปภาพ') ?>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <h3 class="h5 fw-bold mb-3">วิดีโอ</h3>
          <?= renderLinkList($videoLinks, 'วิดีโอ') ?>
        </div>
      </div>
    </div>
    <div class="col-12 col-md-4">
      <div class="card border-0 shadow-sm">
        <div class="card-body">
          <h3 class="h5 fw-bold mb-3">เอกสาร / รายงาน</h3>
          <?= renderLinkList($docLinks, 'เอกสาร') ?>
          <?php if (!empty($projectDocuments)): ?>
            <h6 class="fw-bold text-secondary mt-4 mb-2">📎 ไฟล์แนบ</h6>
            <ul class="list-group list-group-flush">
              <?php foreach ($projectDocuments as $doc): ?>
                <li class="list-group-item px-0">
                  <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" rel="noopener noreferrer" class="text-primary text-decoration-none">📄 <?= htmlspecialchars($doc['original_name']) ?></a>
                  <?php if ($doc['description'] !== ''): ?><div class="small text-muted mt-1"><?= htmlspecialchars($doc['description']) ?></div><?php endif; ?>
                  <div class="small text-muted"><?= formatBytes((int)$doc['file_size']) ?> · แนบโดย <?= htmlspecialchars((string)$doc['uploaded_by']) ?> · <?= htmlspecialchars(date('d/m/Y H:i', strtotime($doc['created_at']))) ?></div>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
</main>
</div>
</body>
</html>
