<?php
require_once __DIR__ . '/db.php';
requireLogin();

try {
    $pdo = new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass, array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ));
} catch (PDOException $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}

$agencyId = currentAgencyId();
if ($agencyId === null) {
    setFlash('error', 'ไม่พบข้อมูลหน่วยงานของคุณ กรุณาติดต่อผู้ดูแลระบบ');
    header('Location: index.php');
    exit;
}

$agencyStmt = $pdo->prepare("SELECT agency_name FROM agencies WHERE id = ? LIMIT 1");
$agencyStmt->bindValue(1, $agencyId, PDO::PARAM_INT);
$agencyStmt->execute();
$agencyInfo = $agencyStmt->fetch();
$agencyStmt = null;

$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_targets'])) {
    if (!isset($_POST['current_value']) || !is_array($_POST['current_value'])) {
        $errors[] = 'ข้อมูลไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง';
    } else {
        $currentValues = $_POST['current_value'];
        $updatedCount = 0;
        $oldValuesBatch = array();
        $newValuesBatch = array();

        foreach ($currentValues as $targetId => $currentValueRaw) {
            $targetId = (int)$targetId;
            $currentValueRaw = trim($currentValueRaw);
            if ($currentValueRaw === '') {
                $currentValue = 0.00;
            } else {
                $currentValue = floatval(str_replace(',', '', $currentValueRaw));
            }

            $selectStmt = $pdo->prepare("SELECT current_value, target_value FROM okr_agency_targets WHERE id = ? AND agency_id = ? LIMIT 1");
            $selectStmt->bindValue(1, $targetId, PDO::PARAM_INT);
            $selectStmt->bindValue(2, $agencyId, PDO::PARAM_INT);
            $selectStmt->execute();
            $row = $selectStmt->fetch();
            $selectStmt = null;

            if (!$row) {
                continue;
            }

            $oldCurrent = (float)$row['current_value'];
            $newCurrent = round($currentValue, 2);
            if ($oldCurrent === $newCurrent) {
                continue;
            }

            $updateStmt = $pdo->prepare("UPDATE okr_agency_targets SET current_value = ? WHERE id = ? AND agency_id = ?");
            $updateStmt->bindValue(1, $newCurrent, PDO::PARAM_STR);
            $updateStmt->bindValue(2, $targetId, PDO::PARAM_INT);
            $updateStmt->bindValue(3, $agencyId, PDO::PARAM_INT);
            if ($updateStmt->execute() && $updateStmt->rowCount() > 0) {
                $oldValuesBatch[$targetId] = number_format($oldCurrent, 2, '.', '');
                $newValuesBatch[$targetId] = number_format($newCurrent, 2, '.', '');
                $updatedCount++;
            }
            $updateStmt = null;
        }

        if ($updatedCount > 0) {
            logfile($conn, 'แก้ไข', 'okr_agency_targets', null, array(
                'agency_id' => $agencyId,
                'agency_name' => isset($agencyInfo['agency_name']) ? $agencyInfo['agency_name'] : '',
                'updated_targets' => $updatedCount,
            ), $oldValuesBatch, $newValuesBatch);
            setFlash('success', 'อัปเดตผลงาน OKR สำเร็จ ' . $updatedCount . ' รายการ');
        } else {
            setFlash('success', 'ไม่มีการเปลี่ยนแปลงข้อมูลหรือข้อมูลถูกบันทึกไว้แล้ว');
        }

        header('Location: okr_agency_targets.php');
        exit;
    }
}

$sql = "
    SELECT t.id,
           t.key_result_id,
           t.agency_id,
           t.target_value,
           t.current_value,
           kr.kr_text AS key_result_title,
           kr.unit,
           p.objective_text AS objective_title,
           p.fiscal_year,
           p.owner_user_id AS owner_agency_id
    FROM okr_agency_targets t
    JOIN okr_key_results kr ON kr.id = t.key_result_id
    LEFT JOIN okr_projects p ON p.id = kr.project_id
    WHERE t.agency_id = ?
    ORDER BY p.fiscal_year DESC, p.project_name ASC, kr.kr_text ASC
";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(1, $agencyId, PDO::PARAM_INT);
$stmt->execute();
$targets = $stmt->fetchAll();
$stmt = null;

function formatPercent($current, $target) {
    if ($target <= 0) {
        return 0;
    }
    return round(($current / $target) * 100, 2);
}

function gradeLabel($percent) {
    if ($percent >= 80) {
        return array('label' => 'ดีมาก', 'color' => '#059669');
    }
    if ($percent >= 60) {
        return array('label' => 'ดี', 'color' => '#10b981');
    }
    if ($percent >= 40) {
        return array('label' => 'พอใช้', 'color' => '#f59e0b');
    }
    return array('label' => 'ควรปรับปรุง', 'color' => '#ef4444');
}

$totalTargets = count($targets);
$totalTargetValue = 0;
$totalCurrentValue = 0;
foreach ($targets as $target) {
    $totalTargetValue += (float)$target['target_value'];
    $totalCurrentValue += (float)$target['current_value'];
}

$overallPercent = $totalTargetValue > 0 ? round(($totalCurrentValue / $totalTargetValue) * 100, 2) : 0;
$overallGrade = gradeLabel($overallPercent);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อัปเดต OKR | <?= htmlspecialchars($office_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/style.php'; ?>
</head>
<body>
<?php $activePage = 'okr_agency_targets'; include __DIR__ . '/menu.php'; ?>
        <div class="container-fluid">
            <div class="card border-0 shadow-sm rounded-4 mb-4 hero-panel">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <div class="text-uppercase section-title mb-2">📊 อัปเดตผลงาน OKR</div>
                            <h1 class="h2 fw-bold mb-2" style="color: #111827;">หน่วยงาน <?= htmlspecialchars(isset($agencyInfo['agency_name']) ? $agencyInfo['agency_name'] : 'ไม่ระบุ') ?></h1>
                            <p class="text-muted mb-0">แก้ไขผลงานตัวชี้วัด OKR ของหน่วยงานคุณ ประมวลผลร้อยละความสำเร็จและค่าความสำเร็จอัตโนมัติ</p>
                        </div>
                        <a href="index.php" class="section-action">กลับหน้าหลัก →</a>
                    </div>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php getFlash(); ?>

            <div class="row g-4 mb-4">
                <div class="col-12 col-md-4">
                    <div class="card stat-card border-0 h-100">
                        <div class="card-body">
                            <div class="text-muted small">จำนวนตัวชี้วัด</div>
                            <div class="fs-3 fw-bold text-primary"><?= number_format($totalTargets) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card stat-card border-0 h-100">
                        <div class="card-body">
                            <div class="text-muted small">เป้าหมายรวม</div>
                            <div class="fs-3 fw-bold text-primary"><?= number_format($totalTargetValue, 2) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card stat-card border-0 h-100">
                        <div class="card-body">
                            <div class="text-muted small">ผลงานรวมปัจจุบัน</div>
                            <div class="fs-3 fw-bold text-primary"><?= number_format($totalCurrentValue, 2) ?></div>
                            <div class="small text-muted">ความสำเร็จ <?= number_format($overallPercent, 2) ?>% • <span style="color: <?= htmlspecialchars($overallGrade['color']) ?>; font-weight: 700;"><?= htmlspecialchars($overallGrade['label']) ?></span></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <form method="post">
                        <input type="hidden" name="save_targets" value="1">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle project-table mb-0">
                                <thead>
                                    <tr>
                                        <th style="width:4%;">#</th>
                                        <th style="width:18%;">ปีงบประมาณ / วัตถุประสงค์</th>
                                        <th style="width:18%;">ตัวชี้วัดหลัก</th>
                                        <th style="width:12%;">เป้าหมาย</th>
                                        <th style="width:12%;">ผลงานปัจจุบัน</th>
                                        <th style="width:12%;">ร้อยละความสำเร็จ</th>
                                        <th style="width:12%;">ค่าความสำเร็จ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($targets)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">ไม่พบตัวชี้วัด OKR สำหรับหน่วยงานนี้</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($targets as $index => $target): ?>
                                            <?php
                                                $percent = formatPercent((float)$target['current_value'], (float)$target['target_value']);
                                                $grade = gradeLabel($percent);
                                            ?>
                                            <tr>
                                                <td><?= $index + 1 ?></td>
                                                <td>
                                                    <div class="fw-semibold">ปี <?= htmlspecialchars($target['fiscal_year']) ?></div>
                                                    <div class="small text-muted mt-1"><?= htmlspecialchars($target['objective_title']) ?></div>
                                                </td>
                                                <td>
                                                    <div class="fw-semibold"><?= htmlspecialchars($target['key_result_title']) ?></div>
                                                    <div class="small text-muted mt-1">หน่วย: <?= htmlspecialchars($target['unit']) ?></div>
                                                </td>
                                                <td> <?= number_format((float)$target['target_value'], 2) ?> </td>
                                                <td>
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        name="current_value[<?= (int)$target['id'] ?>]"
                                                        class="form-control"
                                                        value="<?= number_format((float)$target['current_value'], 2, '.', '') ?>"
                                                        required
                                                    >
                                                </td>
                                                <td>
                                                    <div class="fw-semibold"><?= number_format($percent, 2) ?>%</div>
                                                    <div class="progress progress-bar-custom mt-2">
                                                        <div class="progress-bar" role="progressbar" style="width: <?= min(100, max(0, $percent)) ?>%;"></div>
                                                    </div>
                                                </td>
                                                <td><span class="badge" style="background: <?= htmlspecialchars($grade['color']) ?>; color: #fff;
                                                    padding: 0.55rem 0.75rem; border-radius: 999px; font-size: 0.85rem; font-weight: 600;"><?= htmlspecialchars($grade['label']) ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <?php if (!empty($targets)): ?>
                            <div class="mt-4 d-flex gap-2 flex-wrap">
                                <button type="submit" class="btn btn-primary">บันทึกผลงาน</button>
                                <a href="index.php" class="btn btn-outline-secondary">ยกเลิก</a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>
