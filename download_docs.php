<?php
require_once __DIR__ . '/db.php';

/**
 * Global vars from `db.php` for static analyzers
 * @var mysqli $conn
 * @var string $office_name
 * @var string $logo_url
 * @var string $fiscal_year
 * @var string $office_developer
 * @var string $office_email
 * @var string $office_tel
 */

// ===== Upload (admin) =====
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_doc'])) {
    requireAdmin();
    csrfCheck('download_docs.php');
    $title = trim(isset($_POST['title']) ? $_POST['title'] : '');
    $description = trim(isset($_POST['description']) ? $_POST['description'] : '');
    $docUrl = trim(isset($_POST['doc_url']) ? $_POST['doc_url'] : '');
    $sortOrder = (int)(isset($_POST['sort_order']) ? $_POST['sort_order'] : 0);
    $status = trim(isset($_POST['status']) ? $_POST['status'] : 'active');
    $hasFile = isset($_FILES['docfile']) && $_FILES['docfile']['error'] === UPLOAD_ERR_OK;

    if ($title === '') {
        $error = 'กรุณากรอกชื่อเอกสาร';
    } elseif (!$hasFile && $docUrl === '') {
        $error = 'กรุณาใส่ลิงค์เอกสาร หรือเลือกไฟล์ที่ต้องการอัปโหลด';
    } elseif ($docUrl !== '' && !filter_var($docUrl, FILTER_VALIDATE_URL)) {
        $error = 'ลิงค์เอกสารไม่ถูกต้อง (ต้องขึ้นต้นด้วย http:// หรือ https://)';
    } elseif ($docUrl !== '') {
        // ===== Insert as link (no file) =====
        $stmt = $conn->prepare("INSERT INTO download_docs (title, description, doc_url, original_name, stored_name, file_path, file_size, mime_type, uploaded_by, status, sort_order) VALUES (?, ?, ?, '', '', '', 0, NULL, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param('sssssi', $title, $description, $docUrl, currentUsername(), $status, $sortOrder);
            if ($stmt->execute()) {
                $docId = (int)$stmt->insert_id;
                logfile($conn, 'เพิ่ม', 'download_docs', $docId, array(
                    'title' => $title,
                    'doc_url' => $docUrl,
                    'description' => $description,
                ));
                $success = 'เพิ่มลิงค์เอกสารเรียบร้อยแล้ว';
                header('Location: download_docs.php');
                exit;
            }
            $error = 'บันทึกข้อมูลไม่สำเร็จ: ' . $stmt->error;
            $stmt->close();
        } else {
            $error = 'บันทึกข้อมูลไม่สำเร็จ: ' . $conn->error;
        }
    } elseif ($hasFile) {
        $file = $_FILES['docfile'];
        $allowedExts = array('pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'csv', 'zip', 'rar');
        $maxSize = 10 * 1024 * 1024; // 10 MB
        $originalName = basename($file['name']);
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExts, true)) {
            $error = 'ไม่อนุญาตนามสกุล .' . $ext . ' (อนุญาต: ' . implode(', ', $allowedExts) . ')';
        } elseif ($file['size'] > $maxSize) {
            $error = 'ไฟล์มีขนาดเกิน 10 MB';
        } else {
            $uploadDir = __DIR__ . '/uploads/docs';
            if (!is_dir($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                    $error = 'ไม่สามารถสร้างโฟลเดอร์อัปโหลดได้ กรุณาติดต่อผู้ดูแลระบบ';
                }
            }
            if ($error === '') {
                $storedName = 'doc_' . date('YmdHis') . '_' . md5(uniqid(mt_rand(), true)) . '.' . $ext;
                $dest = $uploadDir . '/' . $storedName;
                if (!move_uploaded_file($file['tmp_name'], $dest)) {
                    $error = 'ไม่สามารถบันทึกไฟล์ได้';
                } else {
                    $filePath = 'uploads/docs/' . $storedName;
                    $mime = $file['type'] !== '' ? $file['type'] : null;
                    $stmt = $conn->prepare("INSERT INTO download_docs (title, description, original_name, stored_name, file_path, file_size, mime_type, uploaded_by, status, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt) {
                        $stmt->bind_param('sssssissis', $title, $description, $originalName, $storedName, $filePath, $file['size'], $mime, currentUsername(), $status, $sortOrder);
                        if ($stmt->execute()) {
                            $docId = (int)$stmt->insert_id;
                            logfile($conn, 'เพิ่ม', 'download_docs', $docId, array(
                                'title' => $title,
                                'original_name' => $originalName,
                                'file_size' => $file['size'],
                                'description' => $description,
                            ));
                            $success = 'อัปโหลดเอกสารเรียบร้อยแล้ว';
                            header('Location: download_docs.php');
                            exit;
                        }
                        $error = 'บันทึกข้อมูลไม่สำเร็จ: ' . $stmt->error;
                        $stmt->close();
                    } else {
                        $error = 'บันทึกข้อมูลไม่สำเร็จ: ' . $conn->error;
                    }
                }
            }
        }
    }
}

// ===== Delete (admin) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_doc'])) {
    requireAdmin();
    csrfCheck('download_docs.php');
    $docId = (int)(isset($_POST['doc_id']) ? $_POST['doc_id'] : 0);
    if ($docId > 0) {
        $res = $conn->query("SELECT file_path, title FROM download_docs WHERE id = $docId LIMIT 1");
        if ($res && $row = $res->fetch_assoc()) {
            $absPath = __DIR__ . '/' . $row['file_path'];
            if (!empty($row['file_path']) && is_file($absPath)) {
                @unlink($absPath);
            }
            $stmt = $conn->prepare("DELETE FROM download_docs WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('i', $docId);
                $stmt->execute();
                $stmt->close();
            }
            logfile($conn, 'ลบ', 'download_docs', $docId, array(
                'title' => isset($row['title']) ? $row['title'] : '',
            ));
            $success = 'ลบเอกสารเรียบร้อยแล้ว';
        }
    }
    header('Location: download_docs.php');
    exit;
}

// ===== Toggle status (admin) =====
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status') {
    requireAdmin();
    $docId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $gotToken = isset($_GET['csrf']) ? $_GET['csrf'] : '';
    if ($docId > 0 && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $gotToken)) {
        $res = $conn->query("SELECT status, title FROM download_docs WHERE id = $docId LIMIT 1");
        if ($res && $row = $res->fetch_assoc()) {
            $newStatus = $row['status'] === 'active' ? 'inactive' : 'active';
            $conn->query("UPDATE download_docs SET status = '$newStatus' WHERE id = $docId");
            logfile($conn, 'เปลี่ยนสถานะ', 'download_docs', $docId, array(
                'title' => isset($row['title']) ? $row['title'] : '',
                'status' => $newStatus,
            ));
        }
    }
    header('Location: download_docs.php');
    exit;
}

// ===== Download (public, only active docs) =====
if (isset($_GET['download'])) {
    $docId = (int)$_GET['download'];
    $stmt = $conn->prepare("SELECT * FROM download_docs WHERE id = ? AND status = 'active' LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('i', $docId);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            // Increment download counter
            $updateStmt = $conn->prepare("UPDATE download_docs SET download_count = download_count + 1 WHERE id = ?");
            if ($updateStmt) {
                $updateStmt->bind_param('i', $docId);
                $updateStmt->execute();
                $updateStmt->close();
            }
            // Link doc: redirect to the external URL
            if (!empty($row['doc_url'])) {
                header('Location: ' . $row['doc_url']);
                exit;
            }
            $absPath = __DIR__ . '/' . $row['file_path'];
            if (is_file($absPath)) {
                $displayName = $row['original_name'];
                header('Content-Type: application/octet-stream');
                header('Content-Disposition: attachment; filename="' . basename($displayName) . '"');
                header('Content-Length: ' . filesize($absPath));
                header('X-Content-Type-Options: nosniff');
                readfile($absPath);
                exit;
            }
        }
        $stmt->close();
    }
    setFlash('error', 'ไม่พบไฟล์ที่ต้องการดาวน์โหลด');
    header('Location: download_docs.php');
    exit;
}

// ===== List =====
$docs = array();
$docRes = $conn->query("SELECT * FROM download_docs ORDER BY status ASC, sort_order ASC, id DESC");
if ($docRes) {
    while ($row = $docRes->fetch_assoc()) {
        $docs[] = $row;
    }
}

function docSizeLabel($bytes) {
    $bytes = (int)$bytes;
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

function docIcon($name) {
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($ext === 'pdf') return '📕';
    if (in_array($ext, array('doc', 'docx'), true)) return '📘';
    if (in_array($ext, array('xls', 'xlsx', 'csv'), true)) return '📗';
    if (in_array($ext, array('ppt', 'pptx'), true)) return '📙';
    if (in_array($ext, array('zip', 'rar'), true)) return '🗜️';
    return '📄';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เอกสารดาวน์โหลด | <?= htmlspecialchars($office_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/style.php'; ?>
</head>
<body>
<?php $activePage = 'download_docs'; include __DIR__ . '/menu.php'; ?>
        <div class="container-fluid">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <div class="text-uppercase section-title mb-2">📁 เอกสารดาวน์โหลด</div>
                            <h1 class="h3 fw-bold mb-2">เอกสารสำหรับดาวน์โหลด</h1>
                            <p class="text-muted mb-0">รวบรวมเอกสาร ประกาศ คำสั่ง คู่มือ และแบบฟอร์มที่เกี่ยวข้อง</p>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php echo getFlash(); ?>

            <div class="row g-4">
                <div class="col-12 <?= isAdmin() ? 'col-xl-8' : '' ?>">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h2 class="h5 fw-bold mb-3">รายการเอกสาร</h2>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>เอกสาร</th>
                                            <th>ขนาด</th>
                                            <th class="text-center">ดาวน์โหลด</th>
                                            <?php if (isAdmin()): ?>
                                                <th>สถานะ</th>
                                            <?php endif; ?>
                                            <th class="text-end">การจัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($docs)): ?>
                                            <tr><td colspan="<?= isAdmin() ? 6 : 5 ?>" class="text-muted text-center py-4">ยังไม่มีเอกสารให้ดาวน์โหลด</td></tr>
                                        <?php endif; ?>
                                        <?php foreach ($docs as $index => $doc): ?>
                                            <tr class="<?= $doc['status'] !== 'active' ? 'opacity-50' : '' ?>">
                                                <td><?= $index + 1 ?></td>
                                                <td>
                                                    <div class="fw-semibold"><?= !empty($doc['doc_url']) ? '🔗' : docIcon($doc['original_name']) ?> <?= htmlspecialchars($doc['title']) ?></div>
                                                    <?php if (!empty($doc['description'])): ?>
                                                        <div class="small text-muted" style="max-width: 380px;"><?= htmlspecialchars($doc['description']) ?></div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($doc['doc_url'])): ?>
                                                        <div class="small text-muted" style="max-width: 380px; overflow-wrap: anywhere;">🔗 <?= htmlspecialchars($doc['doc_url']) ?></div>
                                                    <?php endif; ?>
                                                    <div class="small text-muted"><?= !empty($doc['doc_url']) ? 'ลิงค์ภายนอก' : htmlspecialchars($doc['original_name']) ?> • <?= htmlspecialchars((string)$doc['uploaded_by']) ?> • <?= htmlspecialchars(date('d/m/Y', strtotime($doc['created_at']))) ?></div>
                                                </td>
                                                <td class="text-nowrap"><?= !empty($doc['doc_url']) ? '—' : docSizeLabel($doc['file_size']) ?></td>
                                                <td class="text-center text-nowrap"><?= number_format((int)$doc['download_count']) ?> ครั้ง</td>
                                                <?php if (isAdmin()): ?>
                                                    <td>
                                                        <span class="badge <?= $doc['status'] === 'active' ? 'bg-success-subtle text-success-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' ?>">
                                                            <?= $doc['status'] === 'active' ? 'แสดง' : 'ซ่อน' ?>
                                                        </span>
                                                    </td>
                                                <?php endif; ?>
                                                <td class="text-end text-nowrap">
                                                    <a class="btn btn-sm btn-primary" href="download_docs.php?download=<?= (int)$doc['id'] ?>"><?= !empty($doc['doc_url']) ? '🔗 เปิดลิงค์' : '⬇ ดาวน์โหลด' ?></a>
                                                    <?php if (isAdmin()): ?>
                                                        <a class="btn btn-sm btn-outline-secondary" href="download_docs.php?action=toggle_status&id=<?= (int)$doc['id'] ?>&csrf=<?= urlencode(csrfToken()) ?>">
                                                            <?= $doc['status'] === 'active' ? 'ซ่อน' : 'แสดง' ?>
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteDocModal" data-id="<?= (int)$doc['id'] ?>" data-name="<?= htmlspecialchars($doc['title']) ?>">ลบ</button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (isAdmin()): ?>
                <div class="col-12 col-xl-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h2 class="h5 fw-bold mb-3">➕ เพิ่มเอกสาร</h2>
                            <form method="post" enctype="multipart/form-data">
                                <?= csrfField() ?>
                                <div class="mb-3">
                                    <label class="form-label">ชื่อเอกสาร <span class="text-danger">*</span></label>
                                    <input class="form-control" type="text" name="title" maxlength="255" required placeholder="เช่น คำสั่งแต่งตั้งคณะทำงาน">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">รายละเอียด</label>
                                    <textarea class="form-control" name="description" rows="3" placeholder="คำอธิบายเพิ่มเติม (ถ้ามี)"></textarea>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">ลิงค์เอกสาร</label>
                                    <input class="form-control" type="url" name="doc_url" placeholder="https://drive.google.com/... หรือ https://example.com/file.pdf">
                                    <div class="form-text">ใส่ลิงค์ภายนอก (Google Drive, เว็บไซต์ ฯลฯ) หรือเลือกไฟล์อัปโหลดด้านล่าง — อย่างใดอย่างหนึ่ง</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">หรืออัปโหลดไฟล์</label>
                                    <input class="form-control" type="file" name="docfile" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.rtf,.csv,.zip,.rar">
                                    <div class="form-text">อนุญาต: <?= htmlspecialchars(implode(', ', array('pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'csv', 'zip', 'rar'))) ?> — สูงสุด 10 MB</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">ลำดับการแสดง</label>
                                    <input class="form-control" type="number" name="sort_order" min="0" value="0">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">สถานะ</label>
                                    <select class="form-select" name="status">
                                        <option value="active">แสดง</option>
                                        <option value="inactive">ซ่อน</option>
                                    </select>
                                </div>
                                <button class="btn btn-primary w-100" type="submit" name="upload_doc">บันทึกเอกสาร</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Delete confirm modal -->
                <div class="modal fade" id="deleteDocModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <form method="post">
                                <?= csrfField() ?>
                                <input type="hidden" name="doc_id" id="deleteDocId" value="">
                                <div class="modal-header">
                                    <h5 class="modal-title fw-bold">ยืนยันการลบเอกสาร</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="ปิด"></button>
                                </div>
                                <div class="modal-body">
                                    ต้องการลบเอกสาร <span class="fw-bold text-danger" id="deleteDocName"></span> ใช่หรือไม่?<br>
                                    <span class="text-muted small">การลบจะไม่สามารถกู้คืนได้</span>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                    <button type="submit" name="delete_doc" class="btn btn-danger">ยืนยันการลบ</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var modal = document.getElementById('deleteDocModal');
        if (modal) {
            document.querySelectorAll('[data-bs-target="#deleteDocModal"]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    document.getElementById('deleteDocId').value = btn.getAttribute('data-id');
                    document.getElementById('deleteDocName').textContent = btn.getAttribute('data-name');
                });
            });
        }
    });
</script>
</body>
</html>
