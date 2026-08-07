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

$action = isset($_GET['action']) ? $_GET['action'] : '';
$strategyId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_strategy'])) {
    $strategyId = isset($_POST['strategy_id']) ? intval($_POST['strategy_id']) : 0;
    $fiscalYear = trim(isset($_POST['fiscal_year']) ? $_POST['fiscal_year'] : '');
    $issueNo = intval(isset($_POST['issue_no']) ? $_POST['issue_no'] : 0);
    $issueName = trim(isset($_POST['issue_name']) ? $_POST['issue_name'] : '');

    if (empty($fiscalYear) || $issueNo <= 0 || empty($issueName)) {
        $error = 'กรุณากรอกข้อมูลยุทธศาสตร์ให้ครบถ้วน';
    } else {
        if ($strategyId > 0) {
            $sql = "UPDATE strategic_issues SET fiscal_year = '" . $conn->real_escape_string($fiscalYear) . "', issue_no = $issueNo, issue_name = '" . $conn->real_escape_string($issueName) . "' WHERE id = $strategyId";
            $conn->query($sql);
            $success = 'แก้ไขยุทธศาสตร์เรียบร้อยแล้ว';
            logfile($conn, 'แก้ไข', 'strategies', $strategyId, array(
                'fiscal_year' => $fiscalYear,
                'issue_no' => $issueNo,
                'issue_name' => $issueName,
            ));
            header('Location: strategies.php');
            exit;
        } else {
            $sql = "INSERT INTO strategic_issues (fiscal_year, issue_no, issue_name, created_at) VALUES ('" . $conn->real_escape_string($fiscalYear) . "', $issueNo, '" . $conn->real_escape_string($issueName) . "', NOW())";
            $conn->query($sql);
            $success = 'เพิ่มยุทธศาสตร์ใหม่เรียบร้อยแล้ว';
            logfile($conn, 'เพิ่ม', 'strategies', $conn->insert_id, array(
                'fiscal_year' => $fiscalYear,
                'issue_no' => $issueNo,
                'issue_name' => $issueName,
            ));
            header('Location: strategies.php');
            exit;
        }
    }
}

if ($action === 'edit' && $strategyId > 0) {
    $res = $conn->query("SELECT * FROM strategic_issues WHERE id = $strategyId LIMIT 1");
    if ($res) {
        $strategy = $res->fetch_assoc();
    }
}

$strategies = [];
$res = $conn->query("SELECT * FROM strategic_issues ORDER BY fiscal_year DESC, issue_no ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $strategies[] = $row;
    }
}

$thaiYear = date('Y') + 543;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ยุทธศาสตร์ประจำปี | <?= htmlspecialchars($office_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/style.php'; ?>
</head>
<body>
<?php $activePage = 'strategies'; include __DIR__ . '/menu.php'; ?>
        <div class="container-fluid">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <div class="text-uppercase section-title mb-2">🎯 ยุทธศาสตร์</div>
                            <h1 class="h3 fw-bold mb-2">จัดการยุทธศาสตร์ประจำปี</h1>
                            <p class="text-muted mb-0">Admin สามารถเพิ่มหรือแก้ไขแผนยุทธศาสตร์ต่อปีได้ที่นี่</p>
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
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>ปีงบประมาณ</th>
                                            <th>ยุทธศาสตร์</th>
                                            <th>ชื่อยุทธศาสตร์</th>
                                            <th>การจัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($strategies as $index => $item): ?>
                                            <tr>
                                                <td><?= $index + 1 ?></td>
                                                <td><?= htmlspecialchars($item['fiscal_year']) ?></td>
                                                <td>ยุทธศาสตร์ที่ <?= intval($item['issue_no']) ?></td>
                                                <td><?= htmlspecialchars($item['issue_name']) ?></td>
                                                <td>
                                                    <a class="btn btn-sm btn-outline-primary" href="strategies.php?action=edit&id=<?= $item['id'] ?>">แก้ไข</a>
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
                            <h2 class="h5 fw-bold mb-3"><?= isset($strategy) ? 'แก้ไขยุทธศาสตร์' : 'เพิ่มยุทธศาสตร์ใหม่' ?></h2>
                            <form method="post">
                                <input type="hidden" name="strategy_id" value="<?= isset($strategy['id']) ? intval($strategy['id']) : 0 ?>">
                                <div class="mb-3">
                                    <label class="form-label">ปีงบประมาณ</label>
                                    <input class="form-control" type="text" name="fiscal_year" value="<?= htmlspecialchars(isset($strategy['fiscal_year']) ? $strategy['fiscal_year'] : $thaiYear) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">ลำดับยุทธศาสตร์</label>
                                    <input class="form-control" type="number" name="issue_no" min="1" value="<?= isset($strategy['issue_no']) ? intval($strategy['issue_no']) : 1 ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">ชื่อยุทธศาสตร์</label>
                                    <textarea class="form-control" name="issue_name" rows="3" required><?= htmlspecialchars(isset($strategy['issue_name']) ? $strategy['issue_name'] : '') ?></textarea>
                                </div>
                                <button class="btn btn-primary w-100" type="submit" name="save_strategy">บันทึกยุทธศาสตร์</button>
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
