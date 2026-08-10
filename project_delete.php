<?php
require_once __DIR__ . '/db.php';
requireLogin();

/**
 * @var mysqli $conn
 * @var PDO $pdo
 */

// ลบโครงการ (admin/plan ลบได้ทุกโครงการ, office ลบได้เฉพาะโครงการหน่วยงานตัวเอง, user ลบได้แต่โครงการของตัวเอง)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setFlash('error', 'คำขอไม่ถูกต้อง');
    header('Location: projects.php');
    exit;
}

// CSRF check
$submittedToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submittedToken)) {
    setFlash('error', 'CSRF token ไม่ถูกต้อง กรุณาลองใหม่');
    header('Location: projects.php');
    exit;
}

$projectId = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
if ($projectId <= 0) {
    setFlash('error', 'ไม่พบโครงการที่ต้องการลบ');
    header('Location: projects.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? LIMIT 1");
$stmt->execute(array($projectId));
$project = $stmt->fetch();
if (!$project) {
    setFlash('error', 'ไม่พบโครงการที่ต้องการลบ');
    header('Location: projects.php');
    exit;
}

if (!canDeleteProject($project)) {
    setFlash('error', 'คุณไม่มีสิทธิ์ลบโครงการนี้');
    header('Location: projects.php');
    exit;
}

// ลบไฟล์เอกสาร/ร่องรอยบนดิสก์ (ถ้ามี)
$docRes = $pdo->prepare("SELECT * FROM project_documents WHERE project_id = ?");
$docRes->execute(array($projectId));
$documents = $docRes->fetchAll();
foreach ($documents as $doc) {
    $fullPath = __DIR__ . '/' . $doc['file_path'];
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }
}

try {
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
    }

    // ลบข้อมูลอ้างอิง (project_kpis / project_strategic_issues / project_documents)
    // budget_transactions ลบอัตโนมัติผ่าน FK ON DELETE CASCADE
    $pdo->prepare("DELETE FROM project_kpis WHERE project_id = ?")->execute(array($projectId));
    $pdo->prepare("DELETE FROM project_strategic_issues WHERE source = 'project' AND project_id = ?")->execute(array($projectId));
    $pdo->prepare("DELETE FROM project_documents WHERE project_id = ?")->execute(array($projectId));

    $delStmt = $pdo->prepare("DELETE FROM projects WHERE id = ?");
    $delStmt->execute(array($projectId));

    logfile($conn, 'ลบ', 'projects', $projectId, array(
        'project_id' => isset($project['project_id']) ? $project['project_id'] : '',
        'title'      => isset($project['title']) ? $project['title'] : '',
        'fiscal_year'=> isset($project['fiscal_year']) ? $project['fiscal_year'] : '',
        'deleted_by' => currentUsername(),
    ), array(
        'project_id' => isset($project['project_id']) ? $project['project_id'] : '',
        'title'      => isset($project['title']) ? $project['title'] : '',
        'fiscal_year'=> isset($project['fiscal_year']) ? $project['fiscal_year'] : '',
    ), null);

    $pdo->commit();

    setFlash('success', 'ลบโครงการ "' . (isset($project['title']) ? $project['title'] : '') . '" เรียบร้อยแล้ว');
    header('Location: projects.php');
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('project_delete error: ' . $e->getMessage());
    setFlash('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
    header('Location: projects.php');
    exit;
}
