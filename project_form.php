<?php
require_once __DIR__ . '/db.php';
requireLogin();

/**
 * @var mysqli $conn
 * @var PDO $pdo
 * @var string $office_name
 * @var string $fiscal_year
 */

$isEdit = false;
$project = [];
$error = '';

// CSRF token (PHP 5.6 compatible, no openssl required)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = sha1(uniqid(mt_rand(), true) . microtime(true) . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''));
}
$csrfToken = $_SESSION['csrf_token'];

// Load reference data using PDO if available
try {
    // agencies
    $stmtAgencies = $pdo->query("SELECT id, agency_code, agency_name FROM agencies ORDER BY sort_order ASC, agency_name ASC");
    $agencies = $stmtAgencies->fetchAll();

    // strategic_issues for current fiscal year
    $stmtIssues = $pdo->prepare("SELECT id, issue_no, issue_name FROM strategic_issues WHERE fiscal_year = ? ORDER BY issue_no ASC");
    $stmtIssues->execute([$fiscal_year]);
    $strategicIssues = $stmtIssues->fetchAll();

    // budget_income for current fiscal year
    $stmtIncome = $pdo->prepare("SELECT id, source_name FROM budget_income WHERE fiscal_year = ? AND (status = 'active' OR status IS NULL) ORDER BY source_name ASC");
    $stmtIncome->execute([$fiscal_year]);
    $budgetIncomes = $stmtIncome->fetchAll();

    // group_office
    $stmtGroup = $pdo->query("SELECT id, groupname FROM group_office ORDER BY groupname ASC");
    $groupOffices = $stmtGroup->fetchAll();

    // users (for co-owner selection)
    $stmtUsers = $pdo->query("SELECT username, name FROM users WHERE status = 'active' ORDER BY name ASC");
    $users = $stmtUsers->fetchAll();
} catch (Exception $e) {
    // Fallback to mysqli
    $agencies = getSchools($conn);
    $strategicIssues = [];
    $siRes = $conn->query("SELECT id, issue_no, issue_name FROM strategic_issues WHERE fiscal_year = '" . $conn->real_escape_string($fiscal_year) . "' ORDER BY issue_no ASC");
    if ($siRes) while ($r = $siRes->fetch_assoc()) $strategicIssues[] = $r;

    $budgetIncomes = [];
    $biRes = $conn->query("SELECT id, source_name FROM budget_income WHERE fiscal_year = '" . $conn->real_escape_string($fiscal_year) . "' AND (status = 'active' OR status IS NULL) ORDER BY source_name ASC");
    if ($biRes) while ($r = $biRes->fetch_assoc()) $budgetIncomes[] = $r;

    $groupOffices = [];
    $goRes = $conn->query("SELECT id, groupname FROM group_office ORDER BY groupname ASC");
    if ($goRes) while ($r = $goRes->fetch_assoc()) $groupOffices[] = $r;

    $users = [];
    $uRes = $conn->query("SELECT username, name FROM users WHERE status = 'active' ORDER BY name ASC");
    if ($uRes) while ($r = $uRes->fetch_assoc()) $users[] = $r;
}

// Edit mode
if (isset($_GET['id'])) {
    $editId = (int)$_GET['id'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? LIMIT 1");
        $stmt->execute([$editId]);
        $project = $stmt->fetch();
        if (!$project) {
            setFlash('error', 'ไม่พบข้อมูลโครงการ');
            header('Location: projects.php');
            exit;
        }
        if (!canEditProject($project)) {
            setFlash('error', 'คุณไม่มีสิทธิ์แก้ไขโครงการนี้');
            header('Location: projects.php');
            exit;
        }
        $isEdit = true;
    } catch (Exception $e) {
        // Fallback to mysqli
        $stmt = $conn->prepare("SELECT * FROM projects WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $editId);
        $stmt->execute();
        $res = $stmt->get_result();
        $project = $res->fetch_assoc();
        if (!$project) {
            setFlash('error', 'ไม่พบข้อมูลโครงการ');
            header('Location: projects.php');
            exit;
        }
        if (!canEditProject($project)) {
            setFlash('error', 'คุณไม่มีสิทธิ์แก้ไขโครงการนี้');
            header('Location: projects.php');
            exit;
        }
        $isEdit = true;
    }
}

// ===== ตัวชี้วัดร่วม KPI (1 โครงการ สอดคล้องได้หลาย KPI) =====
$kpiFormYear = $isEdit && !empty($project['fiscal_year']) ? $project['fiscal_year'] : $fiscal_year;
$kpiDefs = array();
try {
    $stmtK = $pdo->prepare("SELECT id, objective_id, kpi_name, target_percent, scope_type, success_indicator FROM kpi_definitions WHERE status = 'active' AND fiscal_year = ? ORDER BY id ASC");
    $stmtK->execute(array($kpiFormYear));
    $kpiDefs = $stmtK->fetchAll();
} catch (Exception $e) {
    $kpiDefs = array();
}

// Map KPI -> objective -> strategy_id (เพื่อกรอง KPI ตามยุทธศาสตร์ที่เลือก)
$objectivesByYear = array();
try {
    $stmtO = $pdo->prepare("SELECT id, strategy_id FROM objectives WHERE fiscal_year = ? AND (status = 'active' OR status IS NULL)");
    $stmtO->execute(array($kpiFormYear));
    $objectivesByYear = $stmtO->fetchAll();
} catch (Exception $e) {
    $objectivesByYear = array();
}
$objectiveStrategyMap = array();
foreach ($objectivesByYear as $o) {
    $objectiveStrategyMap[(int)$o['id']] = (int)$o['strategy_id'];
}
$kpiStrategyMap = array();
foreach ($kpiDefs as $k) {
    $objId = isset($k['objective_id']) ? (int)$k['objective_id'] : 0;
    $kpiStrategyMap[(int)$k['id']] = isset($objectiveStrategyMap[$objId]) ? $objectiveStrategyMap[$objId] : 0;
}
$kpiStrategyMapJson = json_encode($kpiStrategyMap, JSON_UNESCAPED_UNICODE);

// Default values (PHP 5.6 compatible)
$defaults = array();
$defaults['project_id'] = isset($project['project_id']) ? $project['project_id'] : ($isEdit ? '' : generateProjectId());
$defaults['fiscal_year'] = isset($project['fiscal_year']) ? $project['fiscal_year'] : $fiscal_year;
$defaults['title'] = isset($project['title']) ? $project['title'] : '';
$defaults['strategy_id'] = isset($project['strategy_id']) ? $project['strategy_id'] : '';
$defaults['strategic_issue_ids'] = array();
if ($isEdit) {
    try {
        $stmtJ = $pdo->prepare("SELECT strategic_issue_id FROM project_strategic_issues WHERE source = 'project' AND project_id = ?");
        $stmtJ->execute(array((int)$project['id']));
        while ($jr = $stmtJ->fetch()) {
            $defaults['strategic_issue_ids'][] = (int)$jr['strategic_issue_id'];
        }
    } catch (Exception $e) {
        // junction table unavailable; fall back to single column
    }
}
if (empty($defaults['strategic_issue_ids']) && !empty($defaults['strategy_id'])) {
    $defaults['strategic_issue_ids'][] = (int)$defaults['strategy_id'];
}
$defaults['kpi_ids'] = array();
if ($isEdit) {
    try {
        $stmtK = $pdo->prepare("SELECT kpi_id FROM project_kpis WHERE project_id = ?");
        $stmtK->execute(array((int)$project['id']));
        while ($kr = $stmtK->fetch()) {
            $defaults['kpi_ids'][] = (int)$kr['kpi_id'];
        }
    } catch (Exception $e) {
        // junction table unavailable
    }
}
$defaults['status'] = isset($project['status']) ? $project['status'] : 'ยังไม่เริ่ม';
$defaults['owner_name'] = isset($project['owner_name']) ? $project['owner_name'] : currentName();
$defaults['username'] = isset($project['username']) ? $project['username'] : currentUsername();
$defaults['co_owner'] = isset($project['co_owner']) ? $project['co_owner'] : '';
$defaults['agency_id'] = isset($project['agency_id']) ? $project['agency_id'] : currentAgencyId();
$defaults['department'] = isset($project['department']) ? $project['department'] : currentDepartment();
$defaults['budget_allocated'] = isset($project['budget_allocated']) ? (float)$project['budget_allocated'] : 0;
$defaults['budget_used'] = isset($project['budget_used']) ? (float)$project['budget_used'] : 0;
$defaults['progress'] = isset($project['progress']) ? (int)$project['progress'] : 0;
$defaults['budget_source'] = isset($project['budget_source']) ? $project['budget_source'] : '';
$defaults['is_office_total'] = isset($project['is_office_total']) ? (int)$project['is_office_total'] : 1;
$defaults['objectives'] = isset($project['objectives']) ? $project['objectives'] : '';
$defaults['target_quantitative'] = isset($project['target_quantitative']) ? $project['target_quantitative'] : '';
$defaults['target_qualitative'] = isset($project['target_qualitative']) ? $project['target_qualitative'] : '';
$defaults['operation_results'] = isset($project['operation_results']) ? $project['operation_results'] : '';
$defaults['operated_activities'] = isset($project['operated_activities']) ? $project['operated_activities'] : '';
$defaults['problems_suggestions'] = isset($project['problems_suggestions']) ? $project['problems_suggestions'] : '';
$defaults['summary'] = isset($project['summary']) ? $project['summary'] : '';
$defaults['images'] = isset($project['images']) ? $project['images'] : '';
$defaults['video_links'] = isset($project['video_links']) ? $project['video_links'] : '';
$defaults['document_links'] = isset($project['document_links']) ? $project['document_links'] : '';
$defaults['report_links'] = isset($project['report_links']) ? $project['report_links'] : '';
$defaults['result_status'] = (isset($project['result_status']) && $project['result_status'] !== '') ? $project['result_status'] : 'ระหว่างดำเนินการ';

// Uploaded documents (เอกสาร/ร่องรอย) — only relevant in edit mode
$projectDocuments = array();
if ($isEdit) {
    $projectDocuments = getProjectDocuments($conn, (int)$project['id']);
}

// Export defaults as JS-safe JSON
$defaultsJson = json_encode($defaults, JSON_UNESCAPED_UNICODE);

// KPI name map for the preview (id => kpi_name)
$kpiNameMap = array();
foreach ($kpiDefs as $k) {
    $kpiNameMap[(int)$k['id']] = $k['kpi_name'];
}
$kpiNameMapJson = json_encode($kpiNameMap, JSON_UNESCAPED_UNICODE);

function selected($a, $b) { return (string)$a === (string)$b ? 'selected' : ''; }
function checked($a, $b) { return (int)$a === (int)$b ? 'checked' : ''; }
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isEdit ? 'แก้ไขโครงการ' : 'เพิ่มโครงการใหม่' ?> | <?= htmlspecialchars($office_name) ?></title>
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
                    <div class="text-uppercase section-title mb-2"><?= $isEdit ? '✏️' : '➕' ?> Project Form</div>
                    <h1 class="h2 fw-bold mb-2"><?= $isEdit ? 'แก้ไขโครงการ' : 'เพิ่มโครงการใหม่' ?></h1>
                    <p class="text-muted mb-0">กรอกข้อมูลโครงการให้ครบถ้วนทุกหมวด</p>
                </div>
                <a class="btn btn-outline-secondary" href="projects.php">← กลับหน้ารายการ</a>
            </div>
        </div>
    </div>

    <?php getFlash(); ?>

    <?php if ($isEdit && isEditingOnBehalf($project)): ?>
        <div class="alert alert-warning d-flex align-items-center gap-2">
            <span>✏️</span>
            <span>คุณกำลังแก้ไขโครงการนี้แทนเจ้าของโครงการ (<strong><?= htmlspecialchars($project['username']) ?></strong>) — ระบบจะบันทึกว่าเป็นการแก้ไขแทนเจ้าของ</span>
        </div>
    <?php endif; ?>

    <form id="projectForm" method="post" action="project_save.php" novalidate>
        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
        <input type="hidden" name="id" value="<?= $isEdit ? (int)$project['id'] : '' ?>">
        <input type="hidden" name="username" value="<?= htmlspecialchars($defaults['username']) ?>">

        <!-- ===== หมวด 1: ข้อมูลทั่วไป ===== -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <h3 class="h5 fw-bold mb-3">📋 ข้อมูลทั่วไป</h3>
                <div class="row g-3">
                    <div class="col-12 col-md-3">
                        <label class="form-label">รหัสโครงการ</label>
                        <input type="text" class="form-control bg-light" readonly value="<?= htmlspecialchars($defaults['project_id']) ?>">
                        <input type="hidden" name="project_id" value="<?= htmlspecialchars($defaults['project_id']) ?>">
                        <div class="form-text">ออกให้อัตโนมัติ</div>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">ปีงบประมาณ <span class="text-danger">*</span></label>
                        <select name="fiscal_year" class="form-select" required>
                            <?php for ($y = 2570; $y >= 2560; $y--): ?>
                                <option value="<?= $y ?>" <?= selected($defaults['fiscal_year'], $y) ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">ชื่อโครงการ <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($defaults['title']) ?>" placeholder="ระบุชื่อโครงการให้ชัดเจน">
                    </div>
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-12 col-md-6">
                        <label class="form-label">ยุทธศาสตร์ <span class="text-danger">*</span> <span class="text-muted small">(เลือกได้หลายยุทธศาสตร์)</span></label>
                        <div class="border rounded-3 p-3 bg-light">
                            <?php if (empty($strategicIssues)): ?>
                                <div class="form-text">ไม่มียุทธศาสตร์ในปีงบประมาณ <?= htmlspecialchars($fiscal_year) ?></div>
                            <?php else: ?>
                                <?php foreach ($strategicIssues as $si): ?>
                                    <div class="form-check">
                                        <input class="form-check-input strategy-check" type="checkbox" name="strategic_issues[]" value="<?= (int)$si['id'] ?>" id="si_<?= (int)$si['id'] ?>" <?= in_array((int)$si['id'], $defaults['strategic_issue_ids']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="si_<?= (int)$si['id'] ?>">
                                            ยุทธศาสตร์ที่ <?= htmlspecialchars($si['issue_no']) ?>: <?= htmlspecialchars($si['issue_name']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">สถานะโครงการ <span class="text-danger">*</span></label>
                        <select name="status" class="form-select" required>
                            <?php $statuses = ['ยังไม่เริ่ม', 'ระหว่างดำเนินการ', 'เสร็จสิ้น', 'ระงับ']; ?>
                            <?php foreach ($statuses as $st): ?>
                                <option value="<?= htmlspecialchars($st) ?>" <?= selected($defaults['status'], $st) ?>><?= htmlspecialchars($st) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">สรุปงบรวม</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" id="isOfficeTotal" name="is_office_total" value="1" <?= checked($defaults['is_office_total'], 1) ?>>
                            <label class="form-check-label" for="isOfficeTotal">แสดงเป็นงบประมาณภาพรวม</label>
                        </div>
                    </div>
                </div>

                <!-- ===== ความสอดคล้องตัวชี้วัดร่วม KPI (อยู่ใต้ยุทธศาสตร์, กรองตามยุทธศาสตร์ที่เลือก) ===== -->
                <div class="row g-3 mt-2">
                    <div class="col-12">
                        <label class="form-label">🎯 ความสอดคล้องตัวชี้วัดร่วม KPI ของจังหวัด</label>
                        <?php if (empty($kpiDefs)): ?>
                            <div class="alert alert-light border py-2 small mb-0">
                                ยังไม่มีตัวชี้วัด KPI ที่ใช้งานในปีงบประมาณ <?= htmlspecialchars($kpiFormYear) ?> — ผู้กำหนดตัวชี้วัด (<?= roleLabel('plan') ?>) ต้องเพิ่มก่อนผ่านเมนู "กำหนดตัวชี้วัด KPI"
                            </div>
                        <?php else: ?>
                            <div class="small text-muted mb-2">เลือกตัวชี้วัด KPI ร่วมที่โครงการนี้สอดคล้อง/สนับสนุน — ระบบจะแสดงเฉพาะ KPI ที่ตรงกับยุทธศาสตร์ที่เลือกด้านบน</div>
                            <div class="border rounded-3 p-3 bg-light" id="kpiBox">
                                <div class="row g-3">
                                    <?php foreach ($kpiDefs as $k): ?>
                                        <?php $kpiSid = isset($kpiStrategyMap[(int)$k['id']]) ? (int)$kpiStrategyMap[(int)$k['id']] : 0; ?>
                                        <div class="col-12 col-md-6 kpi-item" data-strategy="<?= $kpiSid ?>">
                                            <div class="form-check border rounded-3 p-3 bg-white h-100 mb-0">
                                                <input class="form-check-input kpi-check" type="checkbox" name="kpi_ids[]" value="<?= (int)$k['id'] ?>" id="kpi_<?= (int)$k['id'] ?>" <?= in_array((int)$k['id'], $defaults['kpi_ids']) ? 'checked' : '' ?>>
                                                <label class="form-check-label w-100" for="kpi_<?= (int)$k['id'] ?>">
                                                    <div class="fw-semibold">
                                                        <?= htmlspecialchars($k['kpi_name']) ?>
                                                        <span class="badge bg-primary-subtle text-primary-emphasis ms-1"><?= htmlspecialchars(rtrim(rtrim(number_format((float)$k['target_percent'], 2), '0'), '.')) ?>%</span>
                                                    </div>
                                                    <?php if (!empty($k['success_indicator'])): ?>
                                                        <div class="small text-muted mt-1"><?= htmlspecialchars($k['success_indicator']) ?></div>
                                                    <?php endif; ?>
                                                    <?php if ($k['scope_type'] === 'province'): ?>
                                                        <div class="small text-muted mt-1">ระดับ: จังหวัด</div>
                                                    <?php endif; ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="small text-muted mt-2" id="kpiFilterHint"></div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-12 col-md-4">
                        <label class="form-label">ชื่อผู้รับผิดชอบหลัก <span class="text-danger">*</span></label>
                        <input type="text" name="owner_name" class="form-control" required value="<?= htmlspecialchars($defaults['owner_name']) ?>" placeholder="คำนำหน้า ชื่อ นามสกุล">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">ชื่อผู้รับผิดชอบร่วม</label>
                        <textarea name="co_owner" class="form-control" rows="2" placeholder="ระบุชื่อผู้ร่วมรับผิดชอบ คั่นด้วย , (คอมม่า)"><?= htmlspecialchars($defaults['co_owner']) ?></textarea>
                        <div class="form-text">ถ้ามีหลายคน ให้คั่นด้วยเครื่องหมายคอมม่า (,)</div>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">หน่วยงาน (เจ้าของโครงการ) <span class="text-danger">*</span></label>
                        <?php if (isAdmin()): ?>
                            <select name="agency_id" class="form-select" required>
                                <option value="">-- เลือกหน่วยงาน --</option>
                                <?php foreach ($agencies as $a): ?>
                                    <?php $aId = is_array($a) ? $a['id'] : $a->id; ?>
                                    <?php $aName = is_array($a) ? (isset($a['agency_name']) ? $a['agency_name'] : $a['school_name']) : $a->agency_name; ?>
                                    <option value="<?= (int)$aId ?>" <?= selected($defaults['agency_id'], $aId) ?>><?= htmlspecialchars($aName) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <?php
                            $lockedAgencyName = 'หน่วยงานของคุณ';
                            foreach ($agencies as $a) {
                                $aId = is_array($a) ? $a['id'] : $a->id;
                                $aName = is_array($a) ? (isset($a['agency_name']) ? $a['agency_name'] : $a['school_name']) : $a->agency_name;
                                if ((int)$aId === (int)$defaults['agency_id']) {
                                    $lockedAgencyName = $aName;
                                    break;
                                }
                            }
                            ?>
                            <input type="hidden" name="agency_id" value="<?= (int)$defaults['agency_id'] ?>">
                            <input type="text" class="form-control" value="<?= htmlspecialchars($lockedAgencyName) ?>" disabled readonly>
                            <div class="form-text text-muted">🔒 คุณมีสิทธิ์ใช้หน่วยงานของตนเองเท่านั้น</div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-12 col-md-6">
                        <label class="form-label">กลุ่มงาน <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <select name="department_select" id="departmentSelect" class="form-select" onchange="toggleDeptInput(this)">
                                <option value="">-- เลือกกลุ่มงาน --</option>
                                <?php foreach ($groupOffices as $g): ?>
                                    <?php $gName = is_array($g) ? $g['groupname'] : $g->groupname; ?>
                                    <option value="<?= htmlspecialchars($gName) ?>" <?= selected($defaults['department'], $gName) ?>><?= htmlspecialchars($gName) ?></option>
                                <?php endforeach; ?>
                                <option value="__other__">➕ เพิ่มกลุ่มงานใหม่</option>
                            </select>
                            <input type="text" name="department" id="departmentInput" class="form-control" value="<?= htmlspecialchars($defaults['department']) ?>" placeholder="ระบุกลุ่มงาน" style="display:none;">
                        </div>
                        <div class="form-text">เลือกจากรายการ หรือเลือก "เพิ่มกลุ่มงานใหม่" เพื่อพิมพ์เอง</div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">แหล่งงบประมาณ <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <select name="budget_source_select" id="budgetSourceSelect" class="form-select" onchange="toggleBudgetSourceInput(this)">
                                <option value="">-- เลือกแหล่งงบประมาณ --</option>
                                <?php foreach ($budgetIncomes as $bi): ?>
                                    <?php $biName = is_array($bi) ? $bi['source_name'] : $bi->source_name; ?>
                                    <option value="<?= htmlspecialchars($biName) ?>" <?= selected($defaults['budget_source'], $biName) ?>><?= htmlspecialchars($biName) ?></option>
                                <?php endforeach; ?>
                                <option value="__other__">➕ เพิ่มแหล่งงบประมาณใหม่</option>
                            </select>
                            <input type="text" name="budget_source" id="budgetSourceInput" class="form-control" value="<?= htmlspecialchars($defaults['budget_source']) ?>" placeholder="ระบุแหล่งงบประมาณ" style="display:none;">
                        </div>
                        <div class="form-text">เลือกจากรายการ หรือเลือก "เพิ่มแหล่งงบประมาณใหม่" เพื่อพิมพ์เอง</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== หมวด 2: งบประมาณ ===== -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <h3 class="h5 fw-bold mb-3">💰 งบประมาณ</h3>
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label">งบที่ได้รับจัดสรร (บาท) <span class="text-danger">*</span></label>
                        <input type="number" name="budget_allocated" id="budgetAllocated" class="form-control" step="0.01" min="0" required value="<?= htmlspecialchars(number_format($defaults['budget_allocated'], 2, '.', '')) ?>">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">งบที่ใช้จริง (บาท)</label>
                        <input type="number" name="budget_used" id="budgetUsed" class="form-control" step="0.01" min="0" value="<?= htmlspecialchars(number_format($defaults['budget_used'], 2, '.', '')) ?>" oninput="autoCalcProgress()">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">ใช้ไปแล้ว (%)</label>
                        <div class="input-group">
                            <input type="number" name="progress" id="progressPercent" class="form-control" min="0" max="100" step="0.1" value="<?= (int)$defaults['progress'] ?>" oninput="manualProgress=true">
                            <span class="input-group-text">%</span>
                        </div>
                        <div class="form-text">
                            <span id="progressHint">คำนวณอัตโนมัติจากงบที่ใช้ / งบจัดสรร</span>
                            <div class="progress mt-1" style="height:6px;">
                                <div id="progressBar" class="progress-bar" role="progressbar" style="width:<?= (int)$defaults['progress'] ?>%;"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== หมวด 3: รายละเอียดโครงการ ===== -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <h3 class="h5 fw-bold mb-3">📝 รายละเอียดโครงการ</h3>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">วัตถุประสงค์โครงการ</label>
                        <textarea name="objectives" class="form-control" rows="3" placeholder="ระบุวัตถุประสงค์ของโครงการ"><?= htmlspecialchars($defaults['objectives']) ?></textarea>
                    </div>
                </div>
                <div class="row g-3 mt-2">
                    <div class="col-12 col-md-6">
                        <label class="form-label">เป้าหมายเชิงปริมาณ</label>
                        <textarea name="target_quantitative" class="form-control" rows="3" placeholder="เช่น จำนวนผู้เข้าร่วม จำนวนครั้ง ฯลฯ"><?= htmlspecialchars($defaults['target_quantitative']) ?></textarea>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">เป้าหมายเชิงคุณภาพ</label>
                        <textarea name="target_qualitative" class="form-control" rows="3" placeholder="เช่น ระดับความพึงพอใจ คุณภาพผลงาน ฯลฯ"><?= htmlspecialchars($defaults['target_qualitative']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== หมวด 4: ผลการดำเนินงาน ===== -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <h3 class="h5 fw-bold mb-3">📝 ผลการดำเนินงาน</h3>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">สรุปผลการดำเนินโครงการ</label>
                        <textarea name="operation_results" class="form-control" rows="3" placeholder="สรุปผลการดำเนินงานที่ผ่านมา"><?= htmlspecialchars($defaults['operation_results']) ?></textarea>
                    </div>
                </div>
                <div class="row g-3 mt-2">
                    <div class="col-12">
                        <label class="form-label">สรุปผลการดำเนินโครงการ</label>
                        <textarea name="operation_results" class="form-control" rows="3" placeholder="สรุปผลการดำเนินงานที่ผ่านมา"><?= htmlspecialchars($defaults['operation_results']) ?></textarea>
                    </div>
                </div>
                <div class="row g-3 mt-2">
                    <div class="col-12 col-md-6">
                        <label class="form-label">กิจกรรมที่ดำเนินการ</label>
                        <textarea name="operated_activities" class="form-control" rows="3" placeholder="ระบุกิจกรรมที่ได้ดำเนินการไปแล้ว"><?= htmlspecialchars($defaults['operated_activities']) ?></textarea>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">ปัญหาและข้อเสนอแนะ</label>
                        <textarea name="problems_suggestions" class="form-control" rows="3" placeholder="ปัญหาที่พบและข้อเสนอแนะ"><?= htmlspecialchars($defaults['problems_suggestions']) ?></textarea>
                    </div>
                </div>
                <div class="row g-3 mt-2">
                    <div class="col-12">
                        <label class="form-label">สรุปโครงการ</label>
                        <textarea name="summary" class="form-control" rows="3" placeholder="สรุปภาพรวมโครงการ"><?= htmlspecialchars($defaults['summary']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== หมวด 5: เอกสาร ร่องรอย ===== -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <h3 class="h5 fw-bold mb-3">📷 เอกสาร ร่องรอย</h3>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">📎 ลิงค์รูปภาพกิจกรรม</label>
                        <textarea name="images" class="form-control" rows="2" placeholder="https://example.com/image1.jpg (หนึ่งลิงค์ต่อบรรทัด)"><?= htmlspecialchars($defaults['images']) ?></textarea>
                        <div class="form-text">ใส่ลิงค์รูปภาพ หนึ่งลิงค์ต่อบรรทัด</div>
                    </div>
                </div>
                <div class="row g-3 mt-2">
                    <div class="col-12 col-md-4">
                        <label class="form-label">🎬 ลิงค์วิดีโอ (YouTube)</label>
                        <textarea name="video_links" class="form-control" rows="2" placeholder="https://youtube.com/watch?v=..."><?= htmlspecialchars($defaults['video_links']) ?></textarea>
                        <div class="form-text">หนึ่งลิงค์ต่อบรรทัด</div>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">📄 ลิงค์ไฟล์โครงการ</label>
                        <textarea name="document_links" class="form-control" rows="2" placeholder="https://drive.google.com/file/..."><?= htmlspecialchars($defaults['document_links']) ?></textarea>
                        <div class="form-text">หนึ่งลิงค์ต่อบรรทัด</div>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">📑 ลิงค์ไฟล์รายงาน</label>
                        <textarea name="report_links" class="form-control" rows="2" placeholder="https://drive.google.com/file/..."><?= htmlspecialchars($defaults['report_links']) ?></textarea>
                        <div class="form-text">หนึ่งลิงค์ต่อบรรทัด</div>
                    </div>
                </div>

                <?php if ($isEdit): ?>
                    <!-- ===== อัปโหลดไฟล์เอกสาร/ร่องรอย ===== -->
                    <hr class="my-4">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                        <h4 class="h6 fw-bold mb-0">📎 แนบไฟล์เอกสาร (PDF / Word / Excel ฯลฯ)</h4>
                        <span class="badge text-bg-light border fw-normal"><?= count($projectDocuments) ?>/5 ไฟล์</span>
                    </div>

                    <?php if (count($projectDocuments) > 0): ?>
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>ไฟล์</th>
                                        <th>รายละเอียด</th>
                                        <th class="text-nowrap">ผู้แนบ</th>
                                        <th class="text-end">ขนาด</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($projectDocuments as $doc): ?>
                                        <tr>
                                            <td class="text-nowrap">
                                                <a href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank" class="text-primary text-decoration-none">📄 <?= htmlspecialchars($doc['original_name']) ?></a>
                                            </td>
                                            <td class="small"><?= htmlspecialchars($doc['description'] !== '' ? $doc['description'] : $doc['original_name']) ?></td>
                                            <td class="small text-muted text-nowrap"><?= htmlspecialchars((string)$doc['uploaded_by']) ?></td>
                                            <td class="small text-muted text-end text-nowrap"><?= formatBytes((int)$doc['file_size']) ?></td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteDocModal" data-id="<?= (int)$doc['id'] ?>" data-name="<?= htmlspecialchars($doc['original_name']) ?>">🗑 ลบ</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-muted small mb-3">— ยังไม่มีไฟล์แนบ —</div>
                    <?php endif; ?>

                    <?php if (count($projectDocuments) < 5): ?>
                        <div class="border rounded-3 p-3 bg-light">
                            <div class="row g-2 align-items-end">
                                <div class="col-12 col-md-5">
                                    <label class="form-label small mb-1">เลือกไฟล์ (เลือกได้หลายไฟล์)</label>
                                    <input type="file" name="documents[]" form="docUploadForm" class="form-control" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.rtf,.csv,.zip,.jpg,.jpeg,.png,.gif">
                                    <div class="form-text small">PDF / Word / Excel / รูปภาพ — สูงสุด <?= 5 - count($projectDocuments) ?> ไฟล์, ไฟล์ละไม่เกิน 10 MB</div>
                                </div>
                                <div class="col-12 col-md-5">
                                    <label class="form-label small mb-1">รายละเอียด / ชื่อไฟล์</label>
                                    <input type="text" name="description" form="docUploadForm" class="form-control" placeholder="เช่น หนังสือสั่งการ/รายงานผล/ภาพกิจกรรม" maxlength="255">
                                </div>
                                <div class="col-12 col-md-2 text-md-end">
                                    <button type="submit" form="docUploadForm" class="btn btn-primary w-100">📎 แนบไฟล์</button>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning py-2 small mb-0">⚠️ แนบไฟล์ครบ 5 ไฟล์แล้ว (สูงสุด 5 ไฟล์ต่อโครงการ)</div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info py-2 small mb-0">💡 บันทึกโครงการก่อน แล้วจึงกลับมาแนบไฟล์เอกสาร/ร่องรอยได้ (สูงสุด 5 ไฟล์)</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== Footer: บันทึก / Preview ===== -->
        <div class="card border-0 shadow-sm rounded-4 mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div class="small text-muted">
                        <?php if ($isEdit): ?>
                            ✏️ แก้ไขล่าสุด: <strong><?= htmlspecialchars(isset($project['last_updated_by']) ? $project['last_updated_by'] : (isset($project['username']) ? $project['username'] : '-')) ?></strong>
                            เมื่อ <?= htmlspecialchars(date('d/m/Y H:i', strtotime(isset($project['updated_at']) ? $project['updated_at'] : $project['created_at']))) ?>
                            <?php if (isset($project['edited_on_behalf']) && (int)$project['edited_on_behalf'] === 1): ?>
                                <span class="badge bg-warning-subtle text-warning-emphasis">แก้ไขแทนเจ้าของ</span>
                            <?php endif; ?>
                        <?php else: ?>
                            👤 ผู้บันทึก: <strong><?= htmlspecialchars(currentName()) ?></strong>
                        <?php endif; ?>
                        <div class="mt-3 pb-2">
                            <label class="form-label mb-2 fw-semibold">ผลการดำเนินโครงการ <span class="fw-normal text-secondary">(เลือกได้เพียง 1 รายการ)</span></label>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="form-check">
                                    <input class="form-check-input result-status" type="checkbox" name="result_status" value="บรรลุ" id="rs_met" <?= $defaults['result_status'] === 'บรรลุ' ? 'checked' : '' ?>>
                                    <label class="form-check-label text-success fw-semibold" for="rs_met">✅ บรรลุ</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input result-status" type="checkbox" name="result_status" value="ระหว่างดำเนินการ" id="rs_progress" <?= $defaults['result_status'] === 'ระหว่างดำเนินการ' ? 'checked' : '' ?>>
                                    <label class="form-check-label text-primary fw-semibold" for="rs_progress">🔄 ระหว่างดำเนินการ</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input result-status" type="checkbox" name="result_status" value="ไม่บรรลุ" id="rs_fail" <?= $defaults['result_status'] === 'ไม่บรรลุ' ? 'checked' : '' ?>>
                                    <label class="form-check-label text-danger fw-semibold" for="rs_fail">⛔ ไม่บรรลุ</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-info text-white" onclick="showPreview()">👁️ Preview</button>
                        <button type="submit" class="btn btn-success"><?= $isEdit ? '💾 บันทึกการแก้ไข' : '💾 บันทึกโครงการ' ?></button>
                        <a class="btn btn-outline-secondary" href="projects.php">ยกเลิก</a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- ===== Upload form แยกจาก projectForm (HTML ห้าม form ซ้อน form) ===== -->
<?php if ($isEdit): ?>
<form id="docUploadForm" method="post" action="document_upload.php" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
    <input type="hidden" name="project_id" value="<?= (int)$project['id'] ?>">
</form>
<?php endif; ?>

<!-- ===== Preview Modal ===== -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">👁️ Preview ข้อมูลโครงการ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="previewBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
                <button type="button" class="btn btn-success" onclick="submitForm()">💾 ยืนยันบันทึก</button>
            </div>
        </div>
    </div>
</div>

    </main>
</div>
<div class="modal fade" id="deleteDocModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="document_delete.php">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="id" id="deleteDocId" value="">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">ยืนยันการลบไฟล์</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
                </div>
                <div class="modal-body">
                    ต้องการลบไฟล์ <span class="fw-bold text-danger" id="deleteDocName"></span> ใช่หรือไม่?<br>
                    <span class="text-muted small">การลบจะไม่สามารถกู้คืนได้</span>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-danger">ยืนยันการลบ</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ===== Budget auto calc =====
let manualProgress = false;

const kpiNameMap = <?= $kpiNameMapJson ?>;

function autoCalcProgress() {
    if (manualProgress) return;
    const allocated = parseFloat(document.getElementById('budgetAllocated').value) || 0;
    const used = parseFloat(document.getElementById('budgetUsed').value) || 0;
    const pct = allocated > 0 ? Math.min(100, Math.round((used / allocated) * 1000) / 10) : 0;
    document.getElementById('progressPercent').value = pct;
    document.getElementById('progressBar').style.width = pct + '%';
    document.getElementById('progressHint').textContent =
        'คำนวณอัตโนมัติ: ' + used.toLocaleString() + ' / ' + allocated.toLocaleString() + ' = ' + pct + '%';
}

document.getElementById('budgetAllocated').addEventListener('input', autoCalcProgress);
document.getElementById('budgetUsed').addEventListener('input', autoCalcProgress);

document.getElementById('progressPercent').addEventListener('input', function() {
    manualProgress = true;
    document.getElementById('progressBar').style.width = Math.min(100, Math.max(0, parseFloat(this.value) || 0)) + '%';
});

// ===== Result status: single-select checkboxes =====
document.querySelectorAll('.result-status').forEach(function (cb) {
    cb.addEventListener('change', function () {
        if (this.checked) {
            document.querySelectorAll('.result-status').forEach(function (other) {
                if (other !== cb) other.checked = false;
            });
        }
    });
});

function getResultStatusText() {
    const checked = document.querySelector('input[name="result_status"]:checked');
    return checked ? checked.value : '-';
}

// ===== Toggle department input =====
function toggleDeptInput(select) {
    const input = document.getElementById('departmentInput');
    if (select.value === '__other__') {
        select.style.display = 'none';
        input.style.display = 'block';
        input.value = '';
        input.focus();
    } else {
        input.value = select.value;
    }
}
// If department value doesn't match any option, show input
(function() {
    const sel = document.getElementById('departmentSelect');
    const input = document.getElementById('departmentInput');
    if (input.value && !Array.from(sel.options).some(o => o.value === input.value)) {
        sel.value = '__other__';
        sel.style.display = 'none';
        input.style.display = 'block';
    }
})();

// ===== Toggle budget source input =====
function toggleBudgetSourceInput(select) {
    const input = document.getElementById('budgetSourceInput');
    if (select.value === '__other__') {
        select.style.display = 'none';
        input.style.display = 'block';
        input.value = '';
        input.focus();
    } else {
        input.value = select.value;
    }
}
(function() {
    const sel = document.getElementById('budgetSourceSelect');
    const input = document.getElementById('budgetSourceInput');
    if (input.value && !Array.from(sel.options).some(o => o.value === input.value)) {
        sel.value = '__other__';
        sel.style.display = 'none';
        input.style.display = 'block';
    }
})();

// ===== Preview =====
function getFormData() {
    const form = document.getElementById('projectForm');
    const fd = new FormData(form);
    const data = {};
    fd.forEach((v, k) => { data[k] = v; });
    return data;
}

function showPreview() {
    if (!hasStrategySelected()) {
        alert('กรุณาเลือกยุทธศาสตร์อย่างน้อย 1 ยุทธศาสตร์');
        return;
    }
    const data = getFormData();
    const d = data;
    const pct = parseFloat(d.progress) || 0;
    const allocated = parseFloat(d.budget_allocated) || 0;
    const used = parseFloat(d.budget_used) || 0;
    const remaining = Math.max(0, allocated - used);

    let html = '<div class="row g-4">';

    // Section 1
    html += '<div class="col-12"><h6 class="fw-bold text-primary">📋 ข้อมูลทั่วไป</h6><table class="table table-bordered table-sm">';
    html += '<tr><td style="width:200px">รหัสโครงการ</td><td>' + esc(d.project_id) + '</td></tr>';
    html += '<tr><td>ปีงบประมาณ</td><td>' + esc(d.fiscal_year) + '</td></tr>';
    html += '<tr><td>ชื่อโครงการ</td><td class="fw-semibold">' + esc(d.title) + '</td></tr>';
    html += '<tr><td>ยุทธศาสตร์</td><td>' + esc(getCheckedStrategies() || '-') + '</td></tr>';
    html += '<tr><td>ตัวชี้วัด KPI ที่สอดคล้อง</td><td>' + esc(getCheckedKpis() || '-') + '</td></tr>';
    html += '<tr><td>สถานะโครงการ</td><td><span class="badge bg-primary">' + esc(d.status) + '</span></td></tr>';
    html += '<tr><td>ผู้รับผิดชอบหลัก</td><td>' + esc(d.owner_name) + '</td></tr>';
    html += '<tr><td>ผู้รับผิดชอบร่วม</td><td>' + esc(d.co_owner || '-') + '</td></tr>';
    html += '<tr><td>หน่วยงาน</td><td>' + esc(getSelectText('agency_id')) + '</td></tr>';
    html += '<tr><td>กลุ่มงาน</td><td>' + esc(d.department || '-') + '</td></tr>';
    html += '<tr><td>แหล่งงบประมาณ</td><td>' + esc(d.budget_source || '-') + '</td></tr>';
    html += '<tr><td>สรุปงบรวม</td><td>' + (d.is_office_total ? '✅ แสดงงบภาพรวม' : '❌ ไม่แสดง') + '</td></tr>';
    html += '</table></div>';

    // Section 2
    html += '<div class="col-12"><h6 class="fw-bold text-success">💰 งบประมาณ</h6><table class="table table-bordered table-sm">';
    html += '<tr><td style="width:200px">งบที่ได้รับจัดสรร</td><td>฿ ' + allocated.toLocaleString(undefined, {minimumFractionDigits:2}) + '</td></tr>';
    html += '<tr><td>งบที่ใช้จริง</td><td>฿ ' + used.toLocaleString(undefined, {minimumFractionDigits:2}) + '</td></tr>';
    html += '<tr><td>คงเหลือ</td><td>฿ ' + remaining.toLocaleString(undefined, {minimumFractionDigits:2}) + '</td></tr>';
    html += '<tr><td>ใช้ไปแล้ว</td><td><div class="progress" style="height:10px;"><div class="progress-bar" style="width:' + pct + '%"></div></div><span class="fw-bold">' + pct + '%</span></td></tr>';
    html += '</table></div>';

    // Section 3
    html += '<div class="col-12"><h6 class="fw-bold text-info">📝 รายละเอียดโครงการ</h6><table class="table table-bordered table-sm">';
    html += '<tr><td style="width:200px">วัตถุประสงค์</td><td style="white-space:pre-wrap">' + esc(d.objectives || '-') + '</td></tr>';
    html += '<tr><td>เป้าหมายเชิงปริมาณ</td><td style="white-space:pre-wrap">' + esc(d.target_quantitative || '-') + '</td></tr>';
    html += '<tr><td>เป้าหมายเชิงคุณภาพ</td><td style="white-space:pre-wrap">' + esc(d.target_qualitative || '-') + '</td></tr>';
    html += '</table></div>';

    // Section 4
    html += '<div class="col-12"><h6 class="fw-bold text-warning">📝 ผลการดำเนินงาน</h6><table class="table table-bordered table-sm">';
    html += '<tr><td style="width:200px">ผลการดำเนินโครงการ</td><td>' + esc(getResultStatusText()) + '</td></tr>';
    html += '<tr><td style="width:200px">สรุปผลการดำเนินโครงการ</td><td style="white-space:pre-wrap">' + esc(d.operation_results || '-') + '</td></tr>';
    html += '<tr><td>กิจกรรมที่ดำเนินการ</td><td style="white-space:pre-wrap">' + esc(d.operated_activities || '-') + '</td></tr>';
    html += '<tr><td>ปัญหาและข้อเสนอแนะ</td><td style="white-space:pre-wrap">' + esc(d.problems_suggestions || '-') + '</td></tr>';
    html += '<tr><td>สรุปโครงการ</td><td style="white-space:pre-wrap">' + esc(d.summary || '-') + '</td></tr>';
    html += '</table></div>';

    // Section 5
    html += '<div class="col-12"><h6 class="fw-bold text-secondary">📷 เอกสาร ร่องรอย</h6><table class="table table-bordered table-sm">';
    html += '<tr><td style="width:200px">รูปภาพกิจกรรม</td><td style="white-space:pre-wrap">' + esc(d.images || '-') + '</td></tr>';
    html += '<tr><td>วิดีโอ (YouTube)</td><td style="white-space:pre-wrap">' + esc(d.video_links || '-') + '</td></tr>';
    html += '<tr><td>ไฟล์โครงการ</td><td style="white-space:pre-wrap">' + esc(d.document_links || '-') + '</td></tr>';
    html += '<tr><td>ไฟล์รายงาน</td><td style="white-space:pre-wrap">' + esc(d.report_links || '-') + '</td></tr>';
    var docItems = <?= json_encode($projectDocuments, JSON_UNESCAPED_UNICODE) ?>;
    if (docItems && docItems.length) {
        html += '<tr><td>ไฟล์แนบ</td><td>';
        docItems.forEach(function (doc) {
            html += '<div>📄 <a href="' + esc(doc.file_path) + '" target="_blank" class="text-primary text-decoration-none">' + esc(doc.original_name) + '</a>'
                 + ' <span class="text-muted small">' + esc(doc.description || '') + '</span></div>';
        });
        html += '</td></tr>';
    }
    html += '</table></div>';

    html += '</div>';

    document.getElementById('previewBody').innerHTML = html;
    const modal = new bootstrap.Modal(document.getElementById('previewModal'));
    modal.show();
}

function esc(s) {
    const div = document.createElement('div');
    div.textContent = s;
    return div.innerHTML;
}

function getSelectText(name) {
    const el = document.querySelector('[name="' + name + '"]');
    if (!el) return '';
    if (el.tagName === 'SELECT') {
        const opt = el.options[el.selectedIndex];
        return opt ? opt.text : '';
    }
    return el.value;
}

function getCheckedStrategies() {
    const checks = document.querySelectorAll('input[name="strategic_issues[]"]:checked');
    const names = [];
    checks.forEach(function (c) {
        const wrap = c.closest('.form-check');
        const label = wrap ? wrap.querySelector('label') : null;
        if (label) names.push(label.textContent.trim());
    });
    return names.join(', ');
}

// ===== กรอง KPI ตามยุทธศาสตร์ที่เลือก (data-strategy on .kpi-item) =====
const kpiStrategyMap = <?= $kpiStrategyMapJson ?: '{}' ?>;

function filterKpis() {
    const selected = [];
    document.querySelectorAll('input[name="strategic_issues[]"]:checked').forEach(function (c) {
        selected.push(parseInt(c.value, 10));
    });
    let visible = 0;
    let total = 0;
    document.querySelectorAll('.kpi-item').forEach(function (item) {
        total++;
        const strat = parseInt(item.getAttribute('data-strategy'), 10) || 0;
        const checked = item.querySelector('.kpi-check') ? item.querySelector('.kpi-check').checked : false;
        const show = checked || selected.length === 0 || strat === 0 || selected.indexOf(strat) !== -1;
        item.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    const hint = document.getElementById('kpiFilterHint');
    if (hint) {
        if (selected.length === 0) {
            hint.textContent = 'แสดง KPI ทั้งหมด ' + total + ' รายการ — เลือกยุทธศาสตร์ด้านบนเพื่อกรอง';
        } else {
            hint.textContent = 'แสดง KPI ' + visible + '/' + total + ' รายการ ที่สอดคล้องกับยุทธศาสตร์ที่เลือก (รวมที่เลือกไว้แล้ว)';
        }
    }
}

document.querySelectorAll('input[name="strategic_issues[]"]').forEach(function (cb) {
    cb.addEventListener('change', filterKpis);
});
filterKpis();

const deleteDocModal = document.getElementById('deleteDocModal');
if (deleteDocModal) {
    document.querySelectorAll('[data-bs-target="#deleteDocModal"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('deleteDocId').value = btn.getAttribute('data-id');
            document.getElementById('deleteDocName').textContent = btn.getAttribute('data-name');
        });
    });
}

function getCheckedKpis() {
    const checks = document.querySelectorAll('input[name="kpi_ids[]"]:checked');
    const names = [];
    checks.forEach(function (c) {
        if (kpiNameMap[c.value]) names.push(kpiNameMap[c.value]);
    });
    return names.join(', ');
}

function hasStrategySelected() {
    return document.querySelectorAll('input[name="strategic_issues[]"]:checked').length > 0;
}

function submitForm() {
    if (!hasStrategySelected()) {
        alert('กรุณาเลือกยุทธศาสตร์อย่างน้อย 1 ยุทธศาสตร์');
        return;
    }
    document.getElementById('projectForm').submit();
}

// Auto-trigger initial calc
autoCalcProgress();
</script>
</body>
</html>
