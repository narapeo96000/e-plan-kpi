<?php
require_once __DIR__ . '/db.php';
requireLogin();

/**
 * @var mysqli $conn
 * @var PDO $pdo
 */

// Upload เอกสาร/ร่องรอย ของโครงการ (สูงสุด 5 ไฟล์ต่อโครงการ)
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

$projectId = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : 0;
if ($projectId <= 0) {
    setFlash('error', 'ไม่พบโครงการ กรุณาลองใหม่อีกครั้ง');
    header('Location: projects.php');
    exit;
}

// Load project + permission (admin / office หน่วยงาน / เจ้าของโครงการเท่านั้น)
$stmtCheck = $pdo->prepare("SELECT id, username, agency_id FROM projects WHERE id = ? LIMIT 1");
$stmtCheck->execute(array($projectId));
$projectRow = $stmtCheck->fetch();
if (!$projectRow) {
    setFlash('error', 'ไม่พบข้อมูลโครงการ');
    header('Location: projects.php');
    exit;
}
if (!canEditProject($projectRow)) {
    setFlash('error', 'คุณไม่มีสิทธิ์แนบไฟล์ในโครงการนี้');
    header('Location: project_form.php?id=' . $projectId);
    exit;
}

// ป้องกัน path traversal จาก project_id
$projectId = (int)$projectRow['id'];

// ตรวจจำนวนไฟล์เดิม
$countStmt = $pdo->prepare("SELECT COUNT(*) AS c FROM project_documents WHERE project_id = ?");
$countStmt->execute(array($projectId));
$existingCount = (int)$countStmt->fetchColumn();

if (!isset($_FILES['documents'])) {
    setFlash('error', 'กรุณาเลือกไฟล์ที่ต้องการแนบ');
    header('Location: project_form.php?id=' . $projectId);
    exit;
}

// normalise $_FILES['documents'] (single or multiple)
$files = array();
if (is_array($_FILES['documents']['name'])) {
    $count = count($_FILES['documents']['name']);
    for ($i = 0; $i < $count; $i++) {
        if ($_FILES['documents']['error'][$i] === UPLOAD_ERR_OK) {
            $files[] = array(
                'name'     => $_FILES['documents']['name'][$i],
                'tmp_name' => $_FILES['documents']['tmp_name'][$i],
                'size'     => $_FILES['documents']['size'][$i],
                'type'     => $_FILES['documents']['type'][$i],
                'error'    => $_FILES['documents']['error'][$i],
            );
        }
    }
} else {
    $files[] = array(
        'name'     => $_FILES['documents']['name'],
        'tmp_name' => $_FILES['documents']['tmp_name'],
        'size'     => $_FILES['documents']['size'],
        'type'     => $_FILES['documents']['type'],
        'error'    => $_FILES['documents']['error'],
    );
}

if (empty($files)) {
    setFlash('error', 'กรุณาเลือกไฟล์ที่ต้องการแนบ (อัปโหลดไม่สำเร็จ)');
    header('Location: project_form.php?id=' . $projectId);
    exit;
}

if ($existingCount + count($files) > 5) {
    setFlash('error', 'แนบไฟล์ได้สูงสุด 5 ไฟล์ต่อโครงการ (มีอยู่แล้ว ' . $existingCount . ' ไฟล์)');
    header('Location: project_form.php?id=' . $projectId);
    exit;
}

$description = trim(isset($_POST['description']) ? $_POST['description'] : '');

$allowedExts = array('pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'csv', 'zip', 'rar', 'jpg', 'jpeg', 'png', 'gif');
$maxSize = 10 * 1024 * 1024; // 10 MB ต่อไฟล์

$uploadDir = __DIR__ . '/uploads/projects/' . $projectId;
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        setFlash('error', 'ไม่สามารถสร้างโฟลเดอร์อัปโหลดได้ กรุณาติดต่อผู้ดูแลระบบ');
        header('Location: project_form.php?id=' . $projectId);
        exit;
    }
}

$uploaded = 0;
$failed = array();

foreach ($files as $file) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $failed[] = $file['name'] . ' (เกิดข้อผิดพลาดขณะอัปโหลด)';
        continue;
    }
    if ($file['size'] > $maxSize) {
        $failed[] = $file['name'] . ' (ขนาดเกิน 10 MB)';
        continue;
    }
    $originalName = basename($file['name']);
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts, true)) {
        $failed[] = $originalName . ' (ไม่อนุญาตนามสกุล .' . $ext . ')';
        continue;
    }
    $storedName = 'doc_' . date('YmdHis') . '_' . md5(uniqid(mt_rand(), true)) . '.' . $ext;
    $dest = $uploadDir . '/' . $storedName;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        $failed[] = $originalName . ' (ไม่สามารถบันทึกไฟล์ได้)';
        continue;
    }

    $fileSize = (int)$file['size'];
    $filePath = 'uploads/projects/' . $projectId . '/' . $storedName;
    $mime = $file['type'] !== '' ? $file['type'] : null;

    $ins = $pdo->prepare("INSERT INTO project_documents (project_id, original_name, stored_name, file_path, file_size, mime_type, description, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $ins->execute(array(
        $projectId,
        $originalName,
        $storedName,
        $filePath,
        $fileSize,
        $mime,
        $description,
        currentUsername(),
    ));
    $docId = (int)$pdo->lastInsertId();

    logfile($conn, 'แนบไฟล์', 'project_documents', $docId, array(
        'project_id'   => $projectId,
        'original_name' => $originalName,
        'stored_name'   => $storedName,
        'file_size'     => $fileSize,
        'description'   => $description,
    ), null, array(
        'project_id'   => $projectId,
        'original_name' => $originalName,
        'stored_name'   => $storedName,
        'file_size'     => $fileSize,
        'description'   => $description,
    ));

    $uploaded++;
}

if ($uploaded > 0) {
    setFlash('success', 'อัปโหลดไฟล์เรียบร้อยแล้ว ' . $uploaded . ' ไฟล์');
} else {
    setFlash('error', 'อัปโหลดไฟล์ไม่สำเร็จ: ' . implode('; ', $failed));
}
header('Location: project_form.php?id=' . $projectId);
exit;
