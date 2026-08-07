<?php
require_once __DIR__ . '/db.php';

// Build PDO using credentials from db.php (which defines $host, $user, $pass, $db)
try {
    $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (Exception $e) {
    die('Database (PDO) connection failed: ' . $e->getMessage());
}

// Simple helper to redirect back
function redirectBack($msgType = 'error', $msg = '') {
    if (!headers_sent()) {
        if (!empty($msg)) {
            setFlash($msgType, $msg);
        }
        header('Location: okr_form.php');
    }
    exit;
}

// Validate input
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectBack('error', 'Invalid request method.');
}

$fiscal_year = isset($_POST['fiscal_year']) ? trim($_POST['fiscal_year']) : '';
$project_name = isset($_POST['project_name']) ? trim($_POST['project_name']) : '';
$objective_text = isset($_POST['objective_text']) ? trim($_POST['objective_text']) : '';

if ($fiscal_year === '' || $project_name === '' || $objective_text === '') {
    redirectBack('error', 'กรุณากรอกข้อมูลที่จำเป็น: ปีงบประมาณ, ชื่อโครงการ, Objective');
}

$target_group_count = isset($_POST['target_group_count']) && $_POST['target_group_count'] !== '' ? (int)$_POST['target_group_count'] : null;
$budget = isset($_POST['budget']) && $_POST['budget'] !== '' ? (float)$_POST['budget'] : 0.0;
$department = isset($_POST['department']) ? trim($_POST['department']) : null;

$key_results = isset($_POST['key_results']) && is_array($_POST['key_results']) ? $_POST['key_results'] : [];

try {
    $pdo->beginTransaction();

    // Insert project / objective
    $stmt = $pdo->prepare("INSERT INTO okr_projects (project_code, fiscal_year, project_name, target_group_count, budget, department, objective_text) VALUES (:project_code, :fiscal_year, :project_name, :target_group_count, :budget, :department, :objective_text)");
    $project_code = function_exists('generateProjectId') ? generateProjectId() : uniqid('PRJ');
    $stmt->bindValue(':project_code', $project_code, PDO::PARAM_STR);
    $stmt->bindValue(':fiscal_year', $fiscal_year, PDO::PARAM_STR);
    $stmt->bindValue(':project_name', $project_name, PDO::PARAM_STR);
    $stmt->bindValue(':target_group_count', $target_group_count, PDO::PARAM_INT);
    $stmt->bindValue(':budget', $budget);
    $stmt->bindValue(':department', $department, PDO::PARAM_STR);
    $stmt->bindValue(':objective_text', $objective_text, PDO::PARAM_STR);
    $stmt->execute();

    $projectId = (int)$pdo->lastInsertId();

    // Prepare KR insert
    $krStmt = $pdo->prepare("INSERT INTO okr_key_results (project_id, kr_text, target_number, unit, initiative_text, status) VALUES (:project_id, :kr_text, :target_number, :unit, :initiative_text, :status)");

    foreach ($key_results as $kr) {
        // Skip empty KR entries
        $kr_text = isset($kr['kr_text']) ? trim($kr['kr_text']) : '';
        if ($kr_text === '') continue;

        $target_number = isset($kr['target_number']) && $kr['target_number'] !== '' ? (float)$kr['target_number'] : null;
        $unit = isset($kr['unit']) ? trim($kr['unit']) : null;
        $initiative_text = isset($kr['initiative_text']) ? trim($kr['initiative_text']) : null;
        $status = isset($kr['status']) ? trim($kr['status']) : 'ยังไม่เริ่ม';

        $krStmt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
        $krStmt->bindValue(':kr_text', $kr_text, PDO::PARAM_STR);
        if ($target_number === null) {
            $krStmt->bindValue(':target_number', null, PDO::PARAM_NULL);
        } else {
            $krStmt->bindValue(':target_number', $target_number);
        }
        $krStmt->bindValue(':unit', $unit, PDO::PARAM_STR);
        $krStmt->bindValue(':initiative_text', $initiative_text, PDO::PARAM_STR);
        $krStmt->bindValue(':status', $status, PDO::PARAM_STR);
        $krStmt->execute();
    }

    $pdo->commit();

    logfile($conn, 'เพิ่ม', 'okr_projects', $projectId, array(
        'fiscal_year' => $fiscal_year,
        'project_name' => $project_name,
        'objective_text' => mb_substr($objective_text, 0, 200),
        'kr_count' => count(array_filter($key_results, function ($kr) {
            return isset($kr['kr_text']) && trim($kr['kr_text']) !== '';
        })),
    ));

    redirectBack('success', 'บันทึก OKR โครงการเรียบร้อยแล้ว');

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('OKR submit error: ' . $e->getMessage());
    redirectBack('error', 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage());
}
