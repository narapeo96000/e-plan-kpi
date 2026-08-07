<?php
require_once __DIR__ . '/db.php';
requireLogin();

/**
 * @var mysqli $conn
 * @var PDO $pdo
 */

// ลบไฟล์เอกสาร/ร่องรอย ของโครงการ (admin หรือเจ้าของโครงการเท่านั้น)
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

$docId = !empty($_POST['id']) ? (int)$_POST['id'] : 0;
if ($docId <= 0) {
    setFlash('error', 'ไม่พบไฟล์ที่ต้องการลบ');
    header('Location: projects.php');
    exit;
}

$stmtDoc = $pdo->prepare("SELECT d.*, p.username AS owner_username, p.agency_id AS owner_agency_id FROM project_documents d JOIN projects p ON p.id = d.project_id WHERE d.id = ? LIMIT 1");
$stmtDoc->execute(array($docId));
$doc = $stmtDoc->fetch();
if (!$doc) {
    setFlash('error', 'ไม่พบไฟล์ที่ต้องการลบ');
    header('Location: projects.php');
    exit;
}

$projectId = (int)$doc['project_id'];
$docRow = array(
    'username'  => $doc['owner_username'],
    'agency_id' => $doc['owner_agency_id'],
);
if (!canEditProject($docRow)) {
    setFlash('error', 'คุณไม่มีสิทธิ์ลบไฟล์นี้');
    header('Location: project_form.php?id=' . $projectId);
    exit;
}

// ลบไฟล์บนดิสก์ (ถ้ามี)
$fullPath = __DIR__ . '/' . $doc['file_path'];
if (is_file($fullPath)) {
    @unlink($fullPath);
}

$delStmt = $pdo->prepare("DELETE FROM project_documents WHERE id = ?");
$delStmt->execute(array($docId));

logfile($conn, 'ลบไฟล์', 'project_documents', $docId, array(
    'project_id'    => $projectId,
    'original_name' => $doc['original_name'],
    'stored_name'   => $doc['stored_name'],
    'file_path'     => $doc['file_path'],
), array(
    'project_id'    => $projectId,
    'original_name' => $doc['original_name'],
    'stored_name'   => $doc['stored_name'],
    'file_path'     => $doc['file_path'],
), null);

setFlash('success', 'ลบไฟล์ "' . $doc['original_name'] . '" เรียบร้อยแล้ว');
header('Location: project_form.php?id=' . $projectId);
exit;
