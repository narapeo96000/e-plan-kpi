<?php
require_once __DIR__ . '/db.php';
requireLogin();

// ผู้กำหนดตัวชี้วัด KPI (plan) เป็นสิทธิ์อ่านอย่างเดียว ไม่สามารถสร้างโครงการ
if (isPlan()) {
    setFlash('error', 'สิทธิ์ผู้กำหนดตัวชี้วัด KPI ไม่สามารถสร้างโครงการได้');
    header('Location: projects.php');
    exit;
}

$fiscalYears = array();
$res = $conn->query("SELECT DISTINCT fiscal_year FROM strategic_issues ORDER BY fiscal_year DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $fiscalYears[] = $row['fiscal_year'];
    }
}
if (!in_array($fiscal_year, $fiscalYears)) {
    $fiscalYears[] = $fiscal_year;
}
$fiscalYears = array_values(array_unique($fiscalYears));

$strategicIssues = getStrategicIssues($conn);

$usersList = array();
$res = $conn->query("SELECT id, name, position FROM users WHERE status = 'active' ORDER BY name ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $usersList[] = $row;
    }
}

// Non-admin users may only create OKR projects owned by themselves
if (!isAdmin()) {
    $currentUserId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    $usersList = array(array(
        'id'       => $currentUserId,
        'name'     => currentName(),
        'position' => currentPosition(),
    ));
}

$defaultYear = $fiscal_year;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>บันทึกโครงการ OKR | <?= htmlspecialchars($office_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <?php include __DIR__ . '/style.php'; ?>
</head>
<body>
<?php $activePage = 'okr_project_form'; include __DIR__ . '/menu.php'; ?>
    <div class="container-fluid">
        <div class="card border-0 shadow-sm rounded-4 mb-4 hero-panel">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                    <div>
                        <div class="text-uppercase section-title mb-2">🎯 OKR Project</div>
                        <h1 class="h2 fw-bold mb-2">บันทึกโครงการตามแนวคิด OKR</h1>
                        <p class="text-muted mb-0">บันทึกเป้าหมาย (Objective) และผลลัพธ์หลัก (Key Results) ของโครงการ</p>
                    </div>
                    <a href="okr_agency_targets.php" class="btn btn-outline-secondary">📊 ดูรายงานความก้าวหน้า</a>
                </div>
            </div>
        </div>

        <form id="okrForm" class="mb-4">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3">📋 ข้อมูลทั่วไป</h5>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <label class="form-label">ปีงบประมาณ <span class="text-danger">*</span></label>
                            <select name="fiscal_year" id="fiscalYear" class="form-select">
                                <?php foreach ($fiscalYears as $fy): ?>
                                    <option value="<?= htmlspecialchars($fy) ?>" <?= $fy === $defaultYear ? 'selected' : '' ?>><?= htmlspecialchars($fy) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">ยุทธศาสตร์ <span class="text-muted small">(เลือกได้หลายยุทธศาสตร์)</span></label>
                            <div class="border rounded-3 p-3 bg-light" id="strategicIssueList">
                                <?php foreach ($strategicIssues as $si): ?>
                                    <div class="form-check" data-fy="<?= htmlspecialchars($si['fiscal_year']) ?>">
                                        <input class="form-check-input" type="checkbox" name="strategic_issues[]" value="<?= (int)$si['id'] ?>" id="si_<?= (int)$si['id'] ?>">
                                        <label class="form-check-label" for="si_<?= (int)$si['id'] ?>">
                                            ยุทธศาสตร์ที่ <?= htmlspecialchars($si['issue_no']) ?> - <?= htmlspecialchars($si['issue_name']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                                <div id="noStrategicIssues" class="form-text" style="display:none;">-- ไม่มียุทธศาสตร์ในปีที่เลือก --</div>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">หน่วยงาน / กลุ่มงาน</label>
                            <input type="text" name="department" class="form-control" value="<?= htmlspecialchars(currentDepartment()) ?>" placeholder="เช่น กลุ่มนโยบายและแผน">
                        </div>
                        <div class="lg:col-span-2">
                            <label class="form-label">ชื่อโครงการ <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" placeholder="ชื่อโครงการ / ชื่อกิจกรรม" required>
                        </div>
                        <div>
                            <label class="form-label">งบประมาณที่ได้รับจัดสรร (บาท)</label>
                            <input type="number" name="budget_allocated" class="form-control" min="0" step="0.01" placeholder="0.00">
                        </div>
                        <div class="lg:col-span-2">
                            <label class="form-label">ผู้รับผิดชอบ</label>
                            <select name="owner_id" class="form-select">
                                <option value="">-- เลือกผู้รับผิดชอบ --</option>
                                <?php foreach ($usersList as $u): ?>
                                    <option value="<?= (int)$u['id'] ?>">
                                        <?= htmlspecialchars($u['name']) ?><?= !empty($u['position']) ? ' (' . htmlspecialchars($u['position']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="lg:col-span-3">
                            <label class="form-label">Objective (เป้าหมายหลักของโครงการ) <span class="text-danger">*</span></label>
                            <textarea name="objective" rows="3" class="form-control" placeholder="ระบุเป้าหมายหลักที่ต้องการให้สำเร็จ" required></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0">🎯 Key Results (ผลลัพธ์หลัก)</h5>
                        <button type="button" class="btn btn-success btn-sm" onclick="addKrRow()">➕ เพิ่มผลลัพธ์หลัก</button>
                    </div>
                    <p class="text-muted small mb-3">กำหนดผลลัพธ์ที่วัดผลได้อย่างน้อย 1 รายการ เพื่อติดตามความสำเร็จของ Objective</p>
                    <div id="krContainer"></div>
                </div>
            </div>

            <div class="d-flex gap-3">
                <button type="submit" class="btn btn-primary px-4 py-2 rounded-lg shadow">💾 บันทึกโครงการ</button>
                <button type="reset" class="btn btn-outline-secondary px-4 py-2 rounded-lg" onclick="return confirm('ต้องการล้างข้อมูลในฟอร์มหรือไม่?')">↺ ล้างฟอร์ม</button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    var krRowIndex = 0;

    function krRowHtml() {
        var html = '';
        html += '<div class="kr-row border rounded-3 p-3 mb-3 bg-gray-50">';
        html += '  <div class="d-flex justify-content-between align-items-center mb-2">';
        html += '    <span class="badge bg-primary">ผลลัพธ์หลัก #' + (krRowIndex + 1) + '</span>';
        html += '    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeKrRow(this)">🗑 ลบ</button>';
        html += '  </div>';
        html += '  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">';
        html += '    <div class="lg:col-span-4">';
        html += '      <label class="form-label small">ชื่อผลลัพธ์หลัก <span class="text-danger">*</span></label>';
        html += '      <input type="text" name="kr_title" class="form-control" placeholder="เช่น จำนวนครูที่เข้ารับการอบรม AI" required>';
        html += '    </div>';
        html += '    <div>';
        html += '      <label class="form-label small">เป้าหมาย (Target) <span class="text-danger">*</span></label>';
        html += '      <input type="number" name="target_value" class="form-control" min="0" step="0.0001" placeholder="100" required>';
        html += '    </div>';
        html += '    <div>';
        html += '      <label class="form-label small">ค่าเริ่มต้น (Current)</label>';
        html += '      <input type="number" name="current_value" class="form-control" min="0" step="0.0001" value="0" placeholder="0">';
        html += '    </div>';
        html += '    <div>';
        html += '      <label class="form-label small">หน่วยนับ</label>';
        html += '      <input type="text" name="unit" class="form-control" placeholder="เช่น คน / ร้อยละ / ครั้ง">';
        html += '    </div>';
        html += '    <div>';
        html += '      <label class="form-label small">แนวทาง/กิจกรรม (Initiative)</label>';
        html += '      <input type="text" name="initiative_name" class="form-control" placeholder="เช่น จัดอบรมเชิงปฏิบัติการ">';
        html += '    </div>';
        html += '  </div>';
        html += '</div>';
        return html;
    }

    function addKrRow() {
        var container = document.getElementById('krContainer');
        var div = document.createElement('div');
        div.innerHTML = krRowHtml();
        container.appendChild(div.firstChild);
        krRowIndex++;
        renumberKrRows();
    }

    function removeKrRow(btn) {
        var row = btn.closest('.kr-row');
        if (row) {
            row.remove();
            renumberKrRows();
        }
    }

    function renumberKrRows() {
        var rows = document.querySelectorAll('#krContainer .kr-row');
        rows.forEach(function (row, idx) {
            var badge = row.querySelector('.badge');
            if (badge) badge.textContent = 'ผลลัพธ์หลัก #' + (idx + 1);
        });
    }

    function getSelectedStrategyIds() {
        var checks = document.querySelectorAll('input[name="strategic_issues[]"]:checked');
        var ids = [];
        checks.forEach(function (c) { ids.push(c.value); });
        return ids;
    }

    function getSelectedStrategyId() {
        var ids = getSelectedStrategyIds();
        return ids.length ? ids[0] : null;
    }

    document.getElementById('fiscalYear').addEventListener('change', function () {
        var year = this.value;
        var list = document.getElementById('strategicIssueList');
        var items = list.querySelectorAll('.form-check[data-fy]');
        var matched = 0;
        items.forEach(function (item) {
            if (item.getAttribute('data-fy') === year) {
                item.style.display = '';
                matched++;
            } else {
                item.style.display = 'none';
                var cb = item.querySelector('input[type="checkbox"]');
                if (cb) cb.checked = false;
            }
        });
        var emptyMsg = document.getElementById('noStrategicIssues');
        if (emptyMsg) emptyMsg.style.display = matched === 0 ? '' : 'none';
    });

    document.getElementById('okrForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var form = this;

        if (!form.title.value.trim()) {
            Swal.fire({ icon: 'warning', title: 'กรุณาระบุชื่อโครงการ' });
            return;
        }
        if (!form.objective.value.trim()) {
            Swal.fire({ icon: 'warning', title: 'กรุณาระบุ Objective' });
            return;
        }
        if (getSelectedStrategyIds().length === 0) {
            Swal.fire({ icon: 'warning', title: 'กรุณาเลือกยุทธศาสตร์อย่างน้อย 1 ยุทธศาสตร์' });
            return;
        }
        var krTitles = form.querySelectorAll('[name="kr_title"]');
        if (krTitles.length === 0) {
            Swal.fire({ icon: 'warning', title: 'กรุณาเพิ่ม Key Results อย่างน้อย 1 รายการ' });
            return;
        }
        for (var i = 0; i < krTitles.length; i++) {
            var val = krTitles[i].value.trim();
            var target = form.querySelectorAll('[name="target_value"]')[i];
            if (!val) {
                Swal.fire({ icon: 'warning', title: 'กรุณากรอกชื่อผลลัพธ์หลักที่ ' + (i + 1) });
                return;
            }
            if (target && parseFloat(target.value) <= 0) {
                Swal.fire({ icon: 'warning', title: 'กรุณากำหนดเป้าหมาย (Target) มากกว่า 0 ที่ผลลัพธ์หลัก ' + (i + 1) });
                return;
            }
        }

        var payload = {
            csrf_token: '<?= csrfToken() ?>',
            fiscal_year: form.fiscal_year.value,
            strategic_issue_id: getSelectedStrategyId(),
            strategic_issues: getSelectedStrategyIds(),
            title: form.title.value.trim(),
            objective: form.objective.value.trim(),
            budget_allocated: form.budget_allocated.value || '0',
            department: form.department.value.trim(),
            owner_id: form.owner_id.value || null,
            key_results: []
        };

        var rows = document.querySelectorAll('#krContainer .kr-row');
        rows.forEach(function (row) {
            payload.key_results.push({
                kr_title: row.querySelector('[name="kr_title"]').value.trim(),
                target_value: row.querySelector('[name="target_value"]').value,
                current_value: row.querySelector('[name="current_value"]').value || '0',
                unit: row.querySelector('[name="unit"]').value.trim(),
                initiative_name: row.querySelector('[name="initiative_name"]').value.trim()
            });
        });

        Swal.fire({
            title: 'กำลังบันทึก...',
            text: 'กรุณารอสักครู่',
            allowOutsideClick: false,
            didOpen: function () { Swal.showLoading(); }
        });

        fetch('okr_project_save.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'บันทึกเรียบร้อย',
                    html: data.message + '<br>รหัสโครงการ: <strong>' + data.project_code + '</strong>',
                    confirmButtonText: 'OK'
                }).then(function () {
                    form.reset();
                    document.getElementById('krContainer').innerHTML = '';
                    krRowIndex = 0;
                });
            } else {
                Swal.fire({ icon: 'error', title: 'บันทึกไม่สำเร็จ', text: data.message || 'เกิดข้อผิดพลาดที่ไม่ทราบสาเหตุ' });
            }
        })
        .catch(function (err) {
            Swal.fire({ icon: 'error', title: 'เกิดข้อผิดพลาด', text: 'ไม่สามารถเชื่อมต่อเซิร์ฟเวอร์ได้: ' + err.message });
        });
    });

    addKrRow();
    var yearTrigger = document.getElementById('fiscalYear');
    if (yearTrigger) {
        yearTrigger.dispatchEvent(new Event('change'));
    }
    </script>
</body>
</html>
