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

$fiscalYear = !empty($fiscal_year) ? $fiscal_year : date('Y') + 543;
$searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';
$filterYear = isset($_GET['year']) ? trim($_GET['year']) : $fiscalYear;
$filterSchool = isset($_GET['school']) ? trim($_GET['school']) : (isset($_GET['department']) ? trim($_GET['department']) : '');
$filterStatus = isset($_GET['status']) ? trim($_GET['status']) : '';
$filterResult = isset($_GET['result']) ? trim($_GET['result']) : '';
$filterSource = isset($_GET['source']) ? trim($_GET['source']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;

$escapedYear = $conn->real_escape_string($filterYear);
$conditions = array();
$conditions[] = "p.fiscal_year = '" . $escapedYear . "'";

if (isLoggedIn() && !isAdminOrPlan()) {
    $currentAgencyId = (int)currentAgencyId();
    if ($currentAgencyId > 0) {
        $conditions[] = "p.agency_id = " . $currentAgencyId;
    } else {
        $currentUsername = $conn->real_escape_string(currentUsername());
        $conditions[] = "p.username = '" . $currentUsername . "'";
    }
}

if ($searchQuery !== '') {
    $escapedSearch = $conn->real_escape_string($searchQuery);
    $conditions[] = "(p.title LIKE '%" . $escapedSearch . "%' OR p.department LIKE '%" . $escapedSearch . "%' OR p.owner_name LIKE '%" . $escapedSearch . "%' OR si.issue_name LIKE '%" . $escapedSearch . "%' OR a.agency_name LIKE '%" . $escapedSearch . "%' OR p.id IN (SELECT psi.project_id FROM project_strategic_issues psi JOIN strategic_issues si2 ON si2.id = psi.strategic_issue_id WHERE psi.source = 'project' AND si2.issue_name LIKE '%" . $escapedSearch . "%') OR p.id IN (SELECT pk.project_id FROM project_kpis pk JOIN kpi_definitions k ON k.id = pk.kpi_id WHERE k.kpi_name LIKE '%" . $escapedSearch . "%'))";
}
if ($filterSchool !== '') {
    $conditions[] = "p.agency_id = " . (int)$filterSchool;
}
if ($filterStatus !== '') {
    $conditions[] = "p.status = '" . $conn->real_escape_string($filterStatus) . "'";
}
if ($filterResult !== '') {
    if ($filterResult === 'ยังไม่ระบุ') {
        $conditions[] = "(p.result_status IS NULL OR TRIM(p.result_status) = '')";
    } else {
        $conditions[] = "p.result_status = '" . $conn->real_escape_string($filterResult) . "'";
    }
}
if ($filterSource !== '') {
    $conditions[] = "p.budget_source = '" . $conn->real_escape_string($filterSource) . "'";
}

$whereClause = implode(' AND ', $conditions);
$countSql = "
    SELECT COUNT(*) AS total
    FROM projects p
    LEFT JOIN agencies a ON a.id = p.agency_id
    LEFT JOIN strategic_issues si ON si.id = p.strategy_id
    WHERE " . $whereClause . "
";
$countResult = $conn->query($countSql);
$totalProjects = 0;
if ($countResult) {
    $countRow = $countResult->fetch_assoc();
    $countTotal = isset($countRow['total']) ? $countRow['total'] : 0;
    $totalProjects = (int)$countTotal;
}
$totalPages = max(1, (int)ceil($totalProjects / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "
    SELECT p.id, p.project_id, p.title, p.status, p.progress, p.budget_allocated, p.budget_used, p.department,
           p.username, p.owner_name, p.co_owner, p.budget_source, p.is_office_total, p.updated_at, p.last_updated_by, p.result_status,
           p.edited_on_behalf,
           a.agency_name AS school_name, si.issue_name,
           COALESCE((SELECT GROUP_CONCAT(si2.issue_name ORDER BY si2.issue_no SEPARATOR ', ')
                     FROM project_strategic_issues psi
                     JOIN strategic_issues si2 ON si2.id = psi.strategic_issue_id
                     WHERE psi.source = 'project' AND psi.project_id = p.id), si.issue_name) AS strategy_names,
           COALESCE((SELECT GROUP_CONCAT(k.kpi_name SEPARATOR ', ')
                     FROM project_kpis pk
                     JOIN kpi_definitions k ON k.id = pk.kpi_id
                     WHERE pk.project_id = p.id), '') AS kpi_names
    FROM projects p
    LEFT JOIN agencies a ON a.id = p.agency_id
    LEFT JOIN strategic_issues si ON si.id = p.strategy_id
    WHERE " . $whereClause . "
    ORDER BY p.created_at DESC
    LIMIT " . (int)$perPage . " OFFSET " . (int)$offset . "
";
$result = $conn->query($sql);
$projects = array();
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $projects[] = $row;
    }
}

$totalAllocated = 0;
$totalUsed = 0;
foreach ($projects as $project) {
    $totalAllocated += (float)$project['budget_allocated'];
    $totalUsed += (float)$project['budget_used'];
}
$usageRate = $totalAllocated > 0 ? min(100, round(($totalUsed / $totalAllocated) * 100, 1)) : 0;

$schoolOptions = array();
$statusOptions = array();
$sourceOptions = array();
$yearOptions = array();
$yearRes = $conn->query("SELECT DISTINCT fiscal_year FROM projects ORDER BY fiscal_year DESC");
while ($row = $yearRes->fetch_assoc()) {
    $yearOptions[] = $row['fiscal_year'];
}
$schoolRes = $conn->query("SELECT id, agency_name AS school_name FROM agencies WHERE agency_name != '' ORDER BY agency_name ASC");
while ($row = $schoolRes->fetch_assoc()) {
    $schoolOptions[] = $row;
}
$statusRes = $conn->query("SELECT DISTINCT status FROM projects WHERE status != '' ORDER BY status ASC");
while ($row = $statusRes->fetch_assoc()) {
    $statusOptions[] = $row['status'];
}
$resultOptions = array('บรรลุ', 'ระหว่างดำเนินการ', 'ไม่บรรลุ', 'ยังไม่ระบุ');
$sourceRes = $conn->query("SELECT DISTINCT source_name FROM budget_income WHERE fiscal_year = '" . $conn->real_escape_string($filterYear) . "' ORDER BY source_name ASC");
while ($row = $sourceRes->fetch_assoc()) {
    $sourceOptions[] = $row['source_name'];
}

function buildProjectPaginationUrl($page, $searchQuery, $filterYear, $filterSchool, $filterStatus, $filterResult, $filterSource)
{
    $params = array();
    if ($searchQuery !== '') {
        $params[] = 'q=' . urlencode($searchQuery);
    }
    if ($filterYear !== '') {
        $params[] = 'year=' . urlencode($filterYear);
    }
    if ($filterSchool !== '') {
        $params[] = 'school=' . urlencode($filterSchool);
    }
    if ($filterStatus !== '') {
        $params[] = 'status=' . urlencode($filterStatus);
    }
    if ($filterResult !== '') {
        $params[] = 'result=' . urlencode($filterResult);
    }
    if ($filterSource !== '') {
        $params[] = 'source=' . urlencode($filterSource);
    }
    $params[] = 'page=' . (int)$page;
    return 'projects.php' . ($params ? '?' . implode('&', $params) : '');
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดโครงการ | <?= htmlspecialchars($office_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/style.php'; ?>
</head>
<body>
<?php $activePage = 'projects'; include __DIR__ . '/menu.php'; ?>
        <div class="container-fluid">
            <div class="card border-0 shadow-sm rounded-4 mb-4 hero-panel">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <div class="text-uppercase section-title mb-2">📋 Project Details</div>
                            <h1 class="h2 fw-bold mb-2" style="color: #111827;">รายละเอียดโครงการ</h1>
                            <p class="text-muted mb-0">รายการโครงการทั้งหมดในปีงบประมาณ <?= htmlspecialchars($fiscalYear) ?></p>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <?php if (isLoggedIn() && canCreateProject()): ?>
                                <a class="btn btn-success" href="project_form.php">➕ เพิ่มโครงการ</a>
                            <?php elseif (!isLoggedIn()): ?>
                                <a class="btn btn-outline-primary" href="login.php">🔐 เข้าสู่ระบบเพื่อเพิ่ม/แก้ไข</a>
                            <?php endif; ?>
                            <a class="btn btn-primary" href="index.php">กลับหน้าหลัก</a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mb-4 filter-panel">
                <div class="card-body p-4">
                    <form method="get" class="row g-3 align-items-end" id="project-filter-form">
                        <input type="hidden" name="page" value="1">
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label">ค้นหา</label>
                                <input type="text" name="q" id="project-search-input" class="form-control" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="ค้นหาชื่อโครงการ, หน่วยงาน, เจ้าของโครงการ, ยุทธศาสตร์, แหล่งงบประมาณ">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">ปีงบประมาณ</label>
                                <select name="year" class="form-select">
                                    <?php foreach ($yearOptions as $yearOption): ?>
                                        <option value="<?= htmlspecialchars($yearOption) ?>" <?= $filterYear === $yearOption ? 'selected' : '' ?>><?= htmlspecialchars($yearOption) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-12 col-md-4">
                                <label class="form-label">หน่วยงาน</label>
                                <select name="school" class="form-select">
                                    <option value="">-- ทุกหน่วยงาน --</option>
                                    <?php foreach ($schoolOptions as $school): ?>
                                        <option value="<?= (int)$school['id'] ?>" <?= (string)$filterSchool === (string)$school['id'] ? 'selected' : '' ?>><?= htmlspecialchars($school['school_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label">สถานะ</label>
                                <select name="status" class="form-select">
                                    <option value="">-- ทุกสถานะ --</option>
                                    <?php foreach ($statusOptions as $statusOpt): ?>
                                        <option value="<?= htmlspecialchars($statusOpt) ?>" <?= $filterStatus === $statusOpt ? 'selected' : '' ?>><?= htmlspecialchars($statusOpt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label">ผลการดำเนินการ</label>
                                <select name="result" class="form-select">
                                    <option value="">-- ทุกผลการดำเนินการ --</option>
                                    <?php foreach ($resultOptions as $resultOpt): ?>
                                        <option value="<?= htmlspecialchars($resultOpt) ?>" <?= $filterResult === $resultOpt ? 'selected' : '' ?>><?= htmlspecialchars($resultOpt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label">แหล่งงบ</label>
                                <select name="source" class="form-select">
                                    <option value="">-- ทุกแหล่ง --</option>
                                    <?php foreach ($sourceOptions as $sourceOpt): ?>
                                        <option value="<?= htmlspecialchars($sourceOpt) ?>" <?= $filterSource === $sourceOpt ? 'selected' : '' ?>><?= htmlspecialchars($sourceOpt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-1">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">ค้นหา</button>
                            </div>
                            <div class="col-12 col-md-1">
                                <label class="form-label">&nbsp;</label>
                                <a href="projects.php" class="btn btn-outline-secondary w-100">รีเซ็ต</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row g-4 mb-4">
                <div class="col-12 col-md-4">
                    <div class="card stat-card border-0 h-100">
                        <div class="card-body">
                            <div class="text-muted small">📊 จำนวนโครงการ</div>
                            <div class="fs-3 fw-bold text-primary"><?= number_format($totalProjects) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card stat-card border-0 h-100">
                        <div class="card-body">
                            <div class="text-muted small">💰 งบประมาณที่จัดสรร</div>
                            <div class="fs-3 fw-bold text-primary">฿<?= number_format($totalAllocated, 2) ?></div>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card stat-card border-0 h-100">
                        <div class="card-body">
                            <div class="text-muted small">📈 ร้อยละการใช้จ่ายงบประมาณ</div>
                            <div class="fs-3 fw-bold text-primary"><?= $usageRate ?>%</div>
                            <div class="small text-muted">ภาพรวมงบประมาณเฉพาะโครงการที่ค้นหา</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <h5 class="section-title mb-0">📋 รายงานรายละเอียดโครงการ</h5>
                        <div class="small text-muted">แสดงข้อมูลล่าสุดตามปีงบประมาณ <?= htmlspecialchars($fiscalYear) ?></div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle project-table">
                            <thead>
                                <tr>
                                    <th>📌 ลำดับ</th>
                                    <th>📁 ชื่อโครงการ</th>
                                    <th>🎯 ยุทธศาสตร์</th>
                                    <th>🔄 สถานะโครงการ</th>
                                    <th>💰 งบที่ได้รับจัดสรร</th>
                                    <th>🕒 ปรับปรุงล่าสุด</th>
                                    <th>⚙️ จัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($projects as $index => $project): ?>
                                    <?php
                                    $rowNumber = (($page - 1) * $perPage) + $index + 1;
                                    $canEditProject = canEditProject($project);
                                    $status = trim((string)($project['status'] ?: '-'));
                                    $statusClass = 'status-pending';
                                    $statusIcon = '⏳';
                                    if ($status === 'เสร็จสิ้น') {
                                        $statusClass = 'status-done';
                                        $statusIcon = '✅';
                                    } elseif ($status === 'ระหว่างดำเนินการ' || $status === 'ดำเนินการ') {
                                        $statusClass = 'status-progress';
                                        $statusIcon = '🔄';
                                    } elseif ($status === 'ยังไม่เริ่ม') {
                                        $statusClass = 'status-pending';
                                        $statusIcon = '📝';
                                    }

                                    $allocated = (float)$project['budget_allocated'];
                                    $used = (float)$project['budget_used'];
                                    $usagePercent = $allocated > 0 ? min(100, round(($used / $allocated) * 100, 1)) : 0;
                                    $achieve = checkProjectAchieved($conn, 'project', (int)$project['id']);
                                    ?>
                                    <tr>
                                        <td class="fw-semibold text-muted"><?= $rowNumber ?></td>
                                        <td>
                                            <div class="fw-semibold project-title-text"><?= htmlspecialchars($project['title']) ?></div>
                                            <div class="project-meta-row"><?= projectAchievedBadge($achieve) ?></div>
                                            <div class="project-meta-row">
                                                <span class="project-meta-badge">🏛️ <?= htmlspecialchars($project['school_name'] ?: ($project['department'] ?: 'ไม่ระบุหน่วยงาน')) ?></span>
                                            </div>
                                            <div class="project-meta-row">
                                                <span class="project-meta-badge">👤 <?= htmlspecialchars($project['owner_name'] ?: 'ไม่ระบุผู้รับผิดชอบหลัก') ?></span>
                                            </div>
                                            <?php if (!empty($project['co_owner'])): ?>
                                                <div class="project-meta-row">
                                                    <span class="project-meta-badge">🤝 <?= htmlspecialchars($project['co_owner']) ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ((int)$project['is_office_total'] === 1): ?>
                                                <div class="badge bg-success-subtle text-success mt-2">📊 สรุปงบรวม</div>
                                            <?php endif; ?>
                                            <?php if (!empty($project['kpi_names'])): ?>
                                                <div class="project-meta-row">
                                                    <span class="project-meta-badge" title="ตัวชี้วัด KPI ที่สอดคล้อง">📐 <?= htmlspecialchars($project['kpi_names']) ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="strategy-pill">🎯 <?= htmlspecialchars(($project['strategy_names'] ?: $project['issue_name']) ?: '-') ?></span>
                                        </td>
                                        <td>
                                            <?php
                                            $rsBadge = resultStatusBadge(isset($project['result_status']) ? $project['result_status'] : '');
                                            echo $rsBadge !== '' ? $rsBadge : '<span class="status-indicator ' . $statusClass . '">' . $statusIcon . ' ' . htmlspecialchars($status) . '</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <div class="budget-stack">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="small fw-semibold text-dark">จัดสรร <?= number_format($allocated, 0) ?> บาท</span>
                                                    <span class="small fw-semibold text-primary"><?= $usagePercent ?>%</span>
                                                </div>
                                                <div class="progress progress-bar-custom">
                                                    <div class="progress-bar" role="progressbar" style="width: <?= $usagePercent ?>%;"></div>
                                                </div>
                                                <div class="d-flex justify-content-between mt-2 small text-muted">
                                                    <span>ใช้ไป <?= number_format($used, 0) ?> บาท</span>
                                                    <span>คงเหลือ <?= number_format(max(0, $allocated - $used), 0) ?> บาท</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="updated-cell">
                                                <div class="fw-semibold text-dark">🗓️ <?= htmlspecialchars(!empty($project['updated_at']) ? date('d/m/Y H:i', strtotime($project['updated_at'])) : '-') ?></div>
                                                <div class="small text-muted">✏️ <?= htmlspecialchars($project['last_updated_by'] ?: '-') ?></div>
                                                <?php if ((int)$project['edited_on_behalf'] === 1): ?>
                                                    <div class="small"><span class="badge bg-warning-subtle text-warning-emphasis mt-1">✏️ แก้ไขแทนเจ้าของ</span></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (isLoggedIn()): ?>
                                                <a class="btn btn-sm btn-outline-info" href="pview_project.php?id=<?= (int)$project['id'] ?>">👁️</a>
                                            <?php endif; ?>
                                            <?php if ($canEditProject): ?>
                                                <a class="btn btn-sm btn-outline-primary" href="project_form.php?id=<?= (int)$project['id'] ?>">✏️</a>
                                            <?php endif; ?>
                                            <?php if (!isLoggedIn()): ?>
                                                <span class="text-muted small">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($totalPages > 1): ?>
                        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-3 mt-4">
                            <div class="small text-muted">
                                แสดงหน้า <?= $page ?> จาก <?= $totalPages ?> หน้า · รวม <?= number_format($totalProjects) ?> โครงการ
                            </div>
                            <nav aria-label="Pagination">
                                <ul class="pagination pagination-sm mb-0">
                                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= buildProjectPaginationUrl($page - 1, $searchQuery, $filterYear, $filterSchool, $filterStatus, $filterResult, $filterSource) ?>">ก่อนหน้า</a>
                                    </li>
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                            <a class="page-link" href="<?= buildProjectPaginationUrl($i, $searchQuery, $filterYear, $filterSchool, $filterStatus, $filterResult, $filterSource) ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                        <a class="page-link" href="<?= buildProjectPaginationUrl($page + 1, $searchQuery, $filterYear, $filterSchool, $filterStatus, $filterResult, $filterSource) ?>">ถัดไป</a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('project-filter-form');
    const searchInput = document.getElementById('project-search-input');
    if (!form) return;

    let debounceTimeout;
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimeout);
            debounceTimeout = setTimeout(function() {
                form.submit();
            }, 300);
        });
    }

    const filterElements = form.querySelectorAll('select[name="year"], select[name="school"], select[name="status"], select[name="result"], select[name="source"]');
    filterElements.forEach(function(el) {
        el.addEventListener('change', function() {
            form.submit();
        });
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

