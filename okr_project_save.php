<?php
// okr_project_save.php - Save OKR project + key results (JSON API)
// Uses PDO prepared statements with a DB transaction. Returns JSON.

require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=UTF-8');

function jsonResponse($success, $message, $extra = array()) {
    $out = array_merge(array(
        'success' => $success,
        'message' => $message,
    ), $extra);
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isLoggedIn()) {
    http_response_code(401);
    jsonResponse(false, 'กรุณาเข้าสู่ระบบก่อนใช้งาน');
}

if (!$pdo) {
    jsonResponse(false, 'ไม่สามารถเชื่อมต่อฐานข้อมูล (PDO) ได้');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Method ไม่ถูกต้อง (ต้องเป็น POST)');
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) {
    $data = $_POST;
}

// CSRF: accept token from JSON payload or form field
$submittedToken = isset($data['csrf_token']) ? $data['csrf_token'] : '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
    jsonResponse(false, 'CSRF token ไม่ถูกต้อง กรุณาลองใหม่');
}

$fiscalYear   = isset($data['fiscal_year']) ? trim($data['fiscal_year']) : '';
$strategyId   = isset($data['strategic_issue_id']) && $data['strategic_issue_id'] !== '' ? (int)$data['strategic_issue_id'] : null;
$strategicIssueIds = array();
if (isset($data['strategic_issues']) && is_array($data['strategic_issues'])) {
    foreach ($data['strategic_issues'] as $sid) {
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
$title        = isset($data['title']) ? trim($data['title']) : '';
$objective    = isset($data['objective']) ? trim($data['objective']) : '';
$budget       = isset($data['budget_allocated']) ? trim($data['budget_allocated']) : '0';
$department   = isset($data['department']) ? trim($data['department']) : '';
$ownerId      = isset($data['owner_id']) && $data['owner_id'] !== '' ? (int)$data['owner_id'] : null;
$keyResults   = isset($data['key_results']) && is_array($data['key_results']) ? $data['key_results'] : array();

// Non-admin users may only create OKR projects owned by themselves
if (!isAdmin()) {
    $ownerId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    if ($ownerId <= 0) {
        jsonResponse(false, 'ไม่สามารถระบุผู้รับผิดชอบได้');
    }
}

if ($fiscalYear === '') {
    jsonResponse(false, 'กรุณาระบุปีงบประมาณ');
}
if ($title === '') {
    jsonResponse(false, 'กรุณาระบุชื่อโครงการ');
}
if ($objective === '') {
    jsonResponse(false, 'กรุณาระบุ Objective');
}
if (count($keyResults) === 0) {
    jsonResponse(false, 'กรุณาเพิ่ม Key Results อย่างน้อย 1 รายการ');
}

$budgetClean = str_replace(',', '', $budget);
if (!is_numeric($budgetClean) || $budgetClean < 0) {
    $budgetClean = 0;
}

$cleanKrs = array();
foreach ($keyResults as $i => $kr) {
    if (!is_array($kr)) {
        continue;
    }
    $krTitle = isset($kr['kr_title']) ? trim($kr['kr_title']) : '';
    $target  = isset($kr['target_value']) ? str_replace(',', '', trim($kr['target_value'])) : '';
    $current = isset($kr['current_value']) ? str_replace(',', '', trim($kr['current_value'])) : '';
    $unit    = isset($kr['unit']) ? trim($kr['unit']) : '';
    $initiative = isset($kr['initiative_name']) ? trim($kr['initiative_name']) : '';

    if ($krTitle === '') {
        jsonResponse(false, 'กรุณากรอกชื่อผลลัพธ์หลัก ที่ ' . ($i + 1));
    }
    if ($target === '' || !is_numeric($target) || $target <= 0) {
        jsonResponse(false, 'กรุณากำหนดเป้าหมาย (Target) มากกว่า 0 ที่ผลลัพธ์หลัก ' . ($i + 1));
    }
    if ($current === '' || !is_numeric($current) || $current < 0) {
        $current = 0;
    }

    $cleanKrs[] = array(
        'kr_title'       => $krTitle,
        'target_value'   => $target,
        'current_value'  => $current,
        'unit'           => $unit,
        'initiative_name'=> $initiative,
    );
}

if (count($cleanKrs) === 0) {
    jsonResponse(false, 'กรุณากรอกข้อมูล Key Results ให้ถูกต้อง');
}

$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare(
        "INSERT INTO okr_projects
            (project_code, fiscal_year, strategic_issue_id, title, objective, budget_allocated,
             project_name, budget, department, objective_text, owner_user_id, owner_id,
             last_updated_by, last_updated_at, edited_by_role, created_at)
         VALUES
            (:project_code, :fiscal_year, :strategic_issue_id, :title, :objective, :budget_allocated,
             :project_name, :budget, :department, :objective_text, :owner_user_id, :owner_id,
             :last_updated_by, NOW(), :edited_by_role, NOW())"
    );

    $projectId = null;
    $projectCode = '';
    $nextSeq = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM okr_projects")->fetchColumn();

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $projectCode = 'PRJ-OKR-' . $fiscalYear . '-' . str_pad((string)$nextSeq, 4, '0', STR_PAD_LEFT);

        $stmt->bindValue(':project_code', $projectCode, PDO::PARAM_STR);
        $stmt->bindValue(':fiscal_year', $fiscalYear, PDO::PARAM_STR);
        $stmt->bindValue(':strategic_issue_id', $strategyId, PDO::PARAM_INT);
        $stmt->bindValue(':title', $title, PDO::PARAM_STR);
        $stmt->bindValue(':objective', $objective, PDO::PARAM_STR);
        $stmt->bindValue(':budget_allocated', $budgetClean, PDO::PARAM_STR);
        $stmt->bindValue(':project_name', $title, PDO::PARAM_STR);
        $stmt->bindValue(':budget', $budgetClean, PDO::PARAM_STR);
        $stmt->bindValue(':department', $department, PDO::PARAM_STR);
        $stmt->bindValue(':objective_text', $objective, PDO::PARAM_STR);
        $stmt->bindValue(':owner_user_id', $ownerId, PDO::PARAM_INT);
        $stmt->bindValue(':owner_id', $ownerId, PDO::PARAM_INT);
        $stmt->bindValue(':last_updated_by', currentUsername(), PDO::PARAM_STR);
        $stmt->bindValue(':edited_by_role', currentRole(), PDO::PARAM_STR);

        try {
            $stmt->execute();
            $projectId = (int)$pdo->lastInsertId();
            break;
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ($e->getCode() === '23000' && strpos($e->getMessage(), 'project_code') !== false) {
                $pdo->beginTransaction();
                $nextSeq++;
                continue;
            }
            throw $e;
        }
    }

    if ($projectId === null) {
        throw new Exception('ไม่สามารถสร้างรหัสโครงการได้ กรุณาลองใหม่อีกครั้ง');
    }

    $krStmt = $pdo->prepare(
        "INSERT INTO okr_key_results
            (project_id, kr_title, target_value, current_value, kr_text, target_number, unit, initiative_text, initiative_name, status, created_at)
         VALUES
            (:project_id, :kr_title, :target_value, :current_value, :kr_text, :target_number, :unit, :initiative_text, :initiative_name, 'ยังไม่เริ่ม', NOW())"
    );

    foreach ($cleanKrs as $kr) {
        $krStmt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $krStmt->bindValue(':kr_title', $kr['kr_title'], PDO::PARAM_STR);
        $krStmt->bindValue(':target_value', $kr['target_value'], PDO::PARAM_STR);
        $krStmt->bindValue(':current_value', $kr['current_value'], PDO::PARAM_STR);
        $krStmt->bindValue(':kr_text', $kr['kr_title'], PDO::PARAM_STR);
        $krStmt->bindValue(':target_number', $kr['target_value'], PDO::PARAM_STR);
        $krStmt->bindValue(':unit', $kr['unit'], PDO::PARAM_STR);
        $krStmt->bindValue(':initiative_text', $kr['initiative_name'], PDO::PARAM_STR);
        $krStmt->bindValue(':initiative_name', $kr['initiative_name'], PDO::PARAM_STR);
        $krStmt->execute();
    }

    // Sync 1-to-many strategic issues
    $delStmt = $pdo->prepare("DELETE FROM project_strategic_issues WHERE source = 'okr' AND project_id = ?");
    $delStmt->execute(array($projectId));
    $insStmt = $pdo->prepare("INSERT IGNORE INTO project_strategic_issues (source, project_id, strategic_issue_id) VALUES ('okr', ?, ?)");
    foreach ($strategicIssueIds as $sid) {
        $insStmt->execute(array($projectId, $sid));
    }

    $pdo->commit();

    logfile($conn, 'เพิ่ม', 'okr_projects', $projectId, array(
        'project_code' => $projectCode,
        'title'        => $title,
        'fiscal_year'  => $fiscalYear,
        'kr_count'     => count($cleanKrs),
    ), null, array(
        'project_code' => $projectCode,
        'title'        => $title,
        'fiscal_year'  => $fiscalYear,
        'kr_count'     => count($cleanKrs),
    ));

    jsonResponse(true, 'บันทึกโครงการเรียบร้อยแล้ว', array(
        'project_id'   => $projectId,
        'project_code' => $projectCode,
        'kr_count'     => count($cleanKrs),
    ));
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('OKR save failed: ' . $e->getMessage());
    jsonResponse(false, 'บันทึกไม่สำเร็จ: ' . $e->getMessage());
}
