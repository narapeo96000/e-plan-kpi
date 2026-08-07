<?php
require_once __DIR__ . '/db.php';
requireLogin();

/**
 * @var mysqli $conn
 * @var PDO $pdo
 * @var string $fiscal_year
 */

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('error', 'คำขอไม่ถูกต้อง');
    header('Location: projects.php');
    exit;
}

// CSRF check
$submittedToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
    setFlash('error', 'CSRF token ไม่ถูกต้อง กรุณาลองใหม่');
    header('Location: project_form.php');
    exit;
}

$currentUser = currentUsername();
$isEdit = !empty($_POST['id']) && (int)$_POST['id'] > 0;
$projectId = $isEdit ? (int)$_POST['id'] : 0;
$oldValues = null;
$editedOnBehalf = 0;

// ===== Validate required fields =====
$title = trim(isset($_POST['title']) ? $_POST['title'] : '');
$fiscalYear = trim(isset($_POST['fiscal_year']) ? $_POST['fiscal_year'] : '');
$status = trim(isset($_POST['status']) ? $_POST['status'] : 'ยังไม่เริ่ม');
$ownerName = trim(isset($_POST['owner_name']) ? $_POST['owner_name'] : '');
if (!isAdmin()) {
    // Non-admin users can only attach projects to their own agency
    $agencyId = currentAgencyId();
} else {
    $agencyId = !empty($_POST['agency_id']) ? (int)$_POST['agency_id'] : null;
}

if (empty($title)) {
    setFlash('error', 'กรุณากรอกชื่อโครงการ');
    header('Location: project_form.php' . ($isEdit ? '?id=' . $projectId : ''));
    exit;
}
if (empty($fiscalYear)) {
    setFlash('error', 'กรุณาเลือกปีงบประมาณ');
    header('Location: project_form.php' . ($isEdit ? '?id=' . $projectId : ''));
    exit;
}
if (empty($ownerName)) {
    setFlash('error', 'กรุณากรอกชื่อผู้รับผิดชอบโครงการ');
    header('Location: project_form.php' . ($isEdit ? '?id=' . $projectId : ''));
    exit;
}

// ===== Handle "other" fields =====
// department
$department = trim(isset($_POST['department']) ? $_POST['department'] : '');
if (empty($department)) {
    $department = trim(isset($_POST['department_select']) ? $_POST['department_select'] : '');
}

// If department is new (not in group_office), add it
if (!empty($department) && $department !== '__other__') {
    try {
        $checkDept = $pdo->prepare("SELECT COUNT(*) FROM group_office WHERE groupname = ?");
        $checkDept->execute(array($department));
        if ((int)$checkDept->fetchColumn() === 0) {
            $insertDept = $pdo->prepare("INSERT INTO group_office (groupname) VALUES (?)");
            $insertDept->execute(array($department));
        }
    } catch (Exception $e) {
        // Non-critical; just proceed
    }
}

// budget_source
$budgetSource = trim(isset($_POST['budget_source']) ? $_POST['budget_source'] : '');
if (empty($budgetSource)) {
    $budgetSource = trim(isset($_POST['budget_source_select']) ? $_POST['budget_source_select'] : '');
}

// If budget_source is new (not in budget_income), add it
if (!empty($budgetSource) && $budgetSource !== '__other__') {
    try {
        $checkBs = $pdo->prepare("SELECT COUNT(*) FROM budget_income WHERE source_name = ? AND fiscal_year = ?");
        $checkBs->execute(array($budgetSource, $fiscalYear));
        if ((int)$checkBs->fetchColumn() === 0) {
            $insertBs = $pdo->prepare("INSERT INTO budget_income (fiscal_year, source_name, status) VALUES (?, ?, 'active')");
            $insertBs->execute(array($fiscalYear, $budgetSource));
        }
    } catch (Exception $e) {
        // Non-critical
    }
}

// ===== Collect all fields =====
$projectIdField = trim(isset($_POST['project_id']) ? $_POST['project_id'] : generateProjectId());
$coOwner = trim(isset($_POST['co_owner']) ? $_POST['co_owner'] : '');

// Ownership: non-admin users always keep themselves as the owner
if (!isAdmin()) {
    $username = $currentUser;
} else {
    $username = trim(isset($_POST['username']) ? $_POST['username'] : $currentUser);
}
$isOfficeTotal = !empty($_POST['is_office_total']) ? 1 : 0;
$budgetAllocated = !empty($_POST['budget_allocated']) ? (float)$_POST['budget_allocated'] : 0;
$budgetUsed = !empty($_POST['budget_used']) ? (float)$_POST['budget_used'] : 0;
$progress = !empty($_POST['progress']) ? min(100, max(0, (int)$_POST['progress'])) : 0;
$objectives = trim(isset($_POST['objectives']) ? $_POST['objectives'] : '');
$targetQuantitative = trim(isset($_POST['target_quantitative']) ? $_POST['target_quantitative'] : '');
$targetQualitative = trim(isset($_POST['target_qualitative']) ? $_POST['target_qualitative'] : '');
$operationResults = trim(isset($_POST['operation_results']) ? $_POST['operation_results'] : '');
$operatedActivities = trim(isset($_POST['operated_activities']) ? $_POST['operated_activities'] : '');
$problemsSuggestions = trim(isset($_POST['problems_suggestions']) ? $_POST['problems_suggestions'] : '');
$summary = trim(isset($_POST['summary']) ? $_POST['summary'] : '');
$images = trim(isset($_POST['images']) ? $_POST['images'] : '');
$videoLinks = trim(isset($_POST['video_links']) ? $_POST['video_links'] : '');
$documentLinks = trim(isset($_POST['document_links']) ? $_POST['document_links'] : '');
$reportLinks = trim(isset($_POST['report_links']) ? $_POST['report_links'] : '');

// Result status (single-select checkboxes)
$resultStatus = isset($_POST['result_status']) ? trim($_POST['result_status']) : '';
$validResultStatuses = array('บรรลุ', 'ระหว่างดำเนินการ', 'ไม่บรรลุ');
if ($resultStatus !== '' && !in_array($resultStatus, $validResultStatuses, true)) {
    $resultStatus = '';
}

// Multi-strategy (1-to-many) selection
$strategyId = !empty($_POST['strategy_id']) ? (int)$_POST['strategy_id'] : null;
$strategicIssueIds = array();
if (isset($_POST['strategic_issues']) && is_array($_POST['strategic_issues'])) {
    foreach ($_POST['strategic_issues'] as $sid) {
        $sid = (int)$sid;
        if ($sid > 0) {
            $strategicIssueIds[$sid] = $sid;
        }
    }
}
if (empty($strategicIssueIds) && $strategyId) {
    $strategicIssueIds[$strategyId] = $strategyId;
}
$strategyId = !empty($strategicIssueIds) ? min($strategicIssueIds) : $strategyId;

// Multi-KPI alignment (1-to-many via project_kpis)
$kpiIds = array();
if (isset($_POST['kpi_ids']) && is_array($_POST['kpi_ids'])) {
    foreach ($_POST['kpi_ids'] as $kid) {
        $kid = (int)$kid;
        if ($kid > 0) {
            $kpiIds[$kid] = $kid;
        }
    }
}

try {
    // Use PDO for transaction
    if (!$pdo) {
        throw new Exception('ไม่สามารถเชื่อมต่อฐานข้อมูล (PDO)');
    }
    $pdo->beginTransaction();

    if ($isEdit) {
        // === UPDATE ===
        // Check permission
        $stmtCheck = $pdo->prepare("SELECT * FROM projects WHERE id = ? LIMIT 1");
        $stmtCheck->execute(array($projectId));
        $oldRow = $stmtCheck->fetch();
        if (!$oldRow) {
            throw new Exception('ไม่พบข้อมูลโครงการ');
        }
        if (!canEditProject($oldRow)) {
            throw new Exception('คุณไม่มีสิทธิ์แก้ไขโครงการนี้');
        }
        // แก้ไขแทนเจ้าของ: คงเจ้าของ (username) ไว้เดิม แล้วบันทึกว่าแก้ไขแทนเจ้าของ
        $username = trim((string)$oldRow['username']);
        $editedOnBehalf = isEditingOnBehalf($oldRow) ? 1 : 0;
        $oldValues = $oldRow;

        $sql = "UPDATE projects SET
            project_id = ?, title = ?, fiscal_year = ?, strategy_id = ?,
            username = ?, owner_name = ?, agency_id = ?, co_owner = ?,
            is_office_total = ?, department = ?, status = ?, progress = ?,
            budget_allocated = ?, budget_used = ?, budget_source = ?,
            objectives = ?, target_quantitative = ?, target_qualitative = ?,
            operation_results = ?, operated_activities = ?, problems_suggestions = ?,
            summary = ?, images = ?, video_links = ?, document_links = ?, report_links = ?,
            result_status = ?,
            last_updated_by = ?, edited_on_behalf = ?, updated_at = NOW()
            WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(array(
            $projectIdField, $title, $fiscalYear, $strategyId,
            $username, $ownerName, $agencyId, $coOwner,
            $isOfficeTotal, $department, $status, $progress,
            $budgetAllocated, $budgetUsed, $budgetSource,
            $objectives, $targetQuantitative, $targetQualitative,
            $operationResults, $operatedActivities, $problemsSuggestions,
            $summary, $images, $videoLinks, $documentLinks, $reportLinks,
            $resultStatus,
            $currentUser, $editedOnBehalf, $projectId
        ));
    } else {
        // === INSERT ===
        $sql = "INSERT INTO projects (
            project_id, title, fiscal_year, strategy_id,
            username, owner_name, agency_id, co_owner,
            is_office_total, department, status, progress,
            budget_allocated, budget_used, budget_source,
            objectives, target_quantitative, target_qualitative,
            operation_results, operated_activities, problems_suggestions,
            summary, images, video_links, document_links, report_links,
            result_status,
            last_updated_by, created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?,
            ?, NOW(), NOW()
        )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(array(
            $projectIdField, $title, $fiscalYear, $strategyId,
            $username, $ownerName, $agencyId, $coOwner,
            $isOfficeTotal, $department, $status, $progress,
            $budgetAllocated, $budgetUsed, $budgetSource,
            $objectives, $targetQuantitative, $targetQualitative,
            $operationResults, $operatedActivities, $problemsSuggestions,
            $summary, $images, $videoLinks, $documentLinks, $reportLinks,
            $resultStatus,
            $currentUser
        ));
        $projectId = (int)$pdo->lastInsertId();
    }

    // ===== Sync 1-to-many strategic issues =====
    $delStmt = $pdo->prepare("DELETE FROM project_strategic_issues WHERE source = 'project' AND project_id = ?");
    $delStmt->execute(array($projectId));
    $insStmt = $pdo->prepare("INSERT IGNORE INTO project_strategic_issues (source, project_id, strategic_issue_id) VALUES ('project', ?, ?)");
    foreach ($strategicIssueIds as $sid) {
        $insStmt->execute(array($projectId, $sid));
    }

    // ===== Sync 1-to-many KPI alignment =====
    $delKpiStmt = $pdo->prepare("DELETE FROM project_kpis WHERE project_id = ?");
    $delKpiStmt->execute(array($projectId));
    $insKpiStmt = $pdo->prepare("INSERT IGNORE INTO project_kpis (project_id, kpi_id) VALUES (?, ?)");
    foreach ($kpiIds as $kid) {
        $insKpiStmt->execute(array($projectId, $kid));
    }

    // ===== Audit log =====
    $newValues = array(
        'project_id' => $projectIdField,
        'title' => $title,
        'fiscal_year' => $fiscalYear,
        'strategy_id' => $strategyId,
        'status' => $status,
        'owner_name' => $ownerName,
        'budget_allocated' => $budgetAllocated,
        'budget_used' => $budgetUsed,
        'progress' => $progress,
        'budget_source' => $budgetSource,
        'result_status' => $resultStatus,
        'kpi_ids' => array_values($kpiIds),
        'edited_by' => $currentUser,
        'edited_on_behalf' => $editedOnBehalf,
    );
    logfile($conn, $isEdit ? 'แก้ไข' : 'เพิ่ม', 'projects', $projectId, $newValues, $oldValues, $newValues);

    $pdo->commit();

    $msg = $isEdit ? '✅ แก้ไขโครงการเรียบร้อยแล้ว' : '✅ เพิ่มโครงการใหม่เรียบร้อยแล้ว';
    setFlash('success', $msg);
    header('Location: projects.php');
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('project_save error: ' . $e->getMessage());
    setFlash('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
    $redirectId = $isEdit ? $projectId : 0;
    header('Location: project_form.php' . ($redirectId ? '?id=' . $redirectId : ''));
    exit;
}
