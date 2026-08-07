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

requireAdmin();

$fiscalYear = !empty($fiscal_year) ? $fiscal_year : date('Y') + 543;
$action = isset($_GET['action']) ? $_GET['action'] : '';
$sourceId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_source'])) {
        $sourceId = isset($_POST['source_id']) ? (int)$_POST['source_id'] : 0;
        $fiscalYearInput = trim(isset($_POST['fiscal_year']) ? $_POST['fiscal_year'] : $fiscalYear);
        $sourceName = trim(isset($_POST['source_name']) ? $_POST['source_name'] : '');
        $status = trim(isset($_POST['status']) ? $_POST['status'] : 'active');

        if ($sourceName === '' || $fiscalYearInput === '') {
            $error = 'กรุณากรอกปีงบประมาณและชื่อแหล่งงบประมาณให้ครบถ้วน';
        } else {
            if ($sourceId > 0) {
                $stmt = $conn->prepare("UPDATE budget_income SET fiscal_year = ?, source_name = ?, status = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param('sssi', $fiscalYearInput, $sourceName, $status, $sourceId);
                    if ($stmt->execute()) {
                        $success = 'บันทึกข้อมูลแหล่งงบประมาณเรียบร้อยแล้ว';
                        logfile($conn, 'แก้ไข', 'budget_sources', $sourceId, array(
                            'fiscal_year' => $fiscalYearInput,
                            'source_name' => $sourceName,
                            'status' => $status,
                        ));
                        header('Location: budget_sources.php');
                        exit;
                    }
                    $error = 'บันทึกข้อมูลไม่สำเร็จ: ' . $stmt->error;
                    $stmt->close();
                } else {
                    $error = 'บันทึกข้อมูลไม่สำเร็จ: ' . $conn->error;
                }
            } else {
                $stmt = $conn->prepare("INSERT INTO budget_income (fiscal_year, source_name, status, created_at) VALUES (?, ?, ?, NOW())");
                if ($stmt) {
                    $stmt->bind_param('sss', $fiscalYearInput, $sourceName, $status);
                    if ($stmt->execute()) {
                        $success = 'เพิ่มแหล่งงบประมาณเรียบร้อยแล้ว';
                        logfile($conn, 'เพิ่ม', 'budget_sources', $conn->insert_id, array(
                            'fiscal_year' => $fiscalYearInput,
                            'source_name' => $sourceName,
                            'status' => $status,
                        ));
                        header('Location: budget_sources.php');
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

if ($action === 'toggle_status' && $sourceId > 0) {
    $sourceRes = $conn->query("SELECT status FROM budget_income WHERE id = $sourceId LIMIT 1");
    if ($sourceRes && $row = $sourceRes->fetch_assoc()) {
        $newStatus = $row['status'] === 'active' ? 'inactive' : 'active';
        $conn->query("UPDATE budget_income SET status = '$newStatus' WHERE id = $sourceId");
        logfile($conn, 'เปลี่ยนสถานะ', 'budget_sources', $sourceId, array(
            'source_name' => isset($row['source_name']) ? $row['source_name'] : '',
            'status' => $newStatus,
        ));
    }
    header('Location: budget_sources.php');
    exit;
}

$sources = array();
$result = $conn->query("SELECT * FROM budget_income ORDER BY fiscal_year DESC, source_name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $sources[] = $row;
    }
}

$editingSource = null;
if ($action === 'edit' && $sourceId > 0) {
    $sourceRes = $conn->query("SELECT * FROM budget_income WHERE id = $sourceId LIMIT 1");
    if ($sourceRes) {
        $editingSource = $sourceRes->fetch_assoc();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการแหล่งงบประมาณ | <?= htmlspecialchars($office_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/style.php'; ?>
</head>
<body>
<?php $activePage = 'budget_sources'; include __DIR__ . '/menu.php'; ?>
        <div class="container-fluid">
            <div class="card border-0 shadow-sm rounded-4 mb-4 hero-panel">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <div class="text-uppercase section-title mb-2">🏦 Budget Sources</div>
                            <h1 class="h3 fw-bold mb-2">จัดการแหล่งที่มาของงบประมาณ</h1>
                            <p class="text-muted mb-0">Admin สามารถเพิ่ม แก้ไข และระงับแหล่งงบประมาณได้จากหน้านี้</p>
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

            <div class="row g-4">
                <div class="col-12 col-xl-7">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>ปีงบประมาณ</th>
                                            <th>ชื่อแหล่งงบ</th>
                                            <th>สถานะ</th>
                                            <th>การจัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sources as $index => $source): ?>
                                            <tr>
                                                <td><?= $index + 1 ?></td>
                                                <td><?= htmlspecialchars($source['fiscal_year']) ?></td>
                                                <td><?= htmlspecialchars($source['source_name']) ?></td>
                                                <td><?= htmlspecialchars($source['status'] === 'active' ? 'ใช้งาน' : 'ระงับ') ?></td>
                                                <td>
                                                    <a class="btn btn-sm btn-outline-primary" href="budget_sources.php?action=edit&id=<?= (int)$source['id'] ?>">แก้ไข</a>
                                                    <a class="btn btn-sm btn-outline-secondary" href="budget_sources.php?action=toggle_status&id=<?= (int)$source['id'] ?>"><?= $source['status'] === 'active' ? 'ระงับ' : 'เปิดใช้งาน' ?></a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-5">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h2 class="h5 fw-bold mb-3"><?= $editingSource ? 'แก้ไขแหล่งงบประมาณ' : 'เพิ่มแหล่งงบประมาณใหม่' ?></h2>
                            <form method="post">
                                <input type="hidden" name="source_id" value="<?= $editingSource ? (int)$editingSource['id'] : 0 ?>">
                                <div class="mb-3">
                                    <label class="form-label">ปีงบประมาณ</label>
                                    <input class="form-control" type="text" name="fiscal_year" value="<?= htmlspecialchars($editingSource ? $editingSource['fiscal_year'] : $fiscalYear) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">ชื่อแหล่งงบประมาณ</label>
                                    <input class="form-control" type="text" name="source_name" value="<?= htmlspecialchars($editingSource ? $editingSource['source_name'] : '') ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">สถานะ</label>
                                    <select class="form-select" name="status">
                                        <option value="active" <?= ($editingSource && $editingSource['status'] === 'active') ? 'selected' : '' ?>>ใช้งาน</option>
                                        <option value="inactive" <?= ($editingSource && $editingSource['status'] === 'inactive') ? 'selected' : '' ?>>ระงับ</option>
                                    </select>
                                </div>
                                <button class="btn btn-primary w-100" type="submit" name="save_source">บันทึกข้อมูล</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
