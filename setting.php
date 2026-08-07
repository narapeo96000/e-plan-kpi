<?php
// setting.php - ตั้งค่าระบบ (admin only)
require_once 'db.php';

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
$page = 'setting';

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and validate inputs
    $office = isset($_POST['office_name']) ? trim($_POST['office_name']) : '';
    $year   = isset($_POST['fiscal_year']) ? trim($_POST['fiscal_year']) : '';
    $logo   = isset($_POST['logo_url']) ? trim($_POST['logo_url']) : '';
    $pri    = isset($_POST['theme_primary']) ? trim($_POST['theme_primary']) : '#1e3a8a';
    $sec    = isset($_POST['theme_secondary']) ? trim($_POST['theme_secondary']) : '#3b82f6';
    $dev    = isset($_POST['office_develop']) ? trim($_POST['office_develop']) : '';
    $email  = isset($_POST['office_email']) ? trim($_POST['office_email']) : '';
    $tel    = isset($_POST['office_tel']) ? trim($_POST['office_tel']) : '';

    // Basic validation
    if ($office === '') {
        setFlash('error', 'กรุณากรอกชื่อสำนักงาน');
        header('Location: setting.php');
        exit;
    }

    // Fiscal year should be numeric (Thai year like 2569)
    if ($year !== '' && !preg_match('/^[0-9]{4,4}$/', $year)) {
        setFlash('error', 'ปีงบประมาณไม่ถูกต้อง');
        header('Location: setting.php');
        exit;
    }

    // Validate color hex (3 or 6 hex digits)
    if ($pri !== '' && !preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $pri)) {
        $pri = '#1e3a8a';
    }
    if ($sec !== '' && !preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $sec)) {
        $sec = '#3b82f6';
    }

    // Validate URL
    if ($logo !== '' && !filter_var($logo, FILTER_VALIDATE_URL)) {
        $logo = '';
    }

    // Sanitize values before DB bind
    $office_s = $office;
    $year_s   = $year;
    $logo_s   = $logo;
    $pri_s    = $pri;
    $sec_s    = $sec;
    $dev_s    = $dev;
    $email_s  = $email;
    $tel_s    = $tel;

    // Use prepared statements for safety
    $r = $conn->query("SELECT id FROM setting LIMIT 1");
    if ($r && $r->num_rows > 0) {
        $stmt = $conn->prepare("UPDATE setting SET office_name=?, fiscal_year=?, logo_url=?, theme_primary=?, theme_secondary=?, office_develop=?, office_email=?, office_tel=? WHERE id=1");
        if ($stmt) {
            $stmt->bind_param('ssssssss', $office_s, $year_s, $logo_s, $pri_s, $sec_s, $dev_s, $email_s, $tel_s);
            $stmt->execute();
            $stmt->close();
        } else {
            // Fallback to safe escaped query
            $conn->query("UPDATE setting SET office_name='" . $conn->real_escape_string($office_s) . "', fiscal_year='" . $conn->real_escape_string($year_s) . "', logo_url='" . $conn->real_escape_string($logo_s) . "', theme_primary='" . $conn->real_escape_string($pri_s) . "', theme_secondary='" . $conn->real_escape_string($sec_s) . "', office_develop='" . $conn->real_escape_string($dev_s) . "', office_email='" . $conn->real_escape_string($email_s) . "', office_tel='" . $conn->real_escape_string($tel_s) . "' WHERE id=1");
        }
    } else {
        $stmt = $conn->prepare("INSERT INTO setting (office_name, fiscal_year, logo_url, theme_primary, theme_secondary, office_develop, office_email, office_tel) VALUES (?,?,?,?,?,?,?,?)");
        if ($stmt) {
            $stmt->bind_param('ssssssss', $office_s, $year_s, $logo_s, $pri_s, $sec_s, $dev_s, $email_s, $tel_s);
            $stmt->execute();
            $stmt->close();
        } else {
            $conn->query("INSERT INTO setting (office_name, fiscal_year, logo_url, theme_primary, theme_secondary, office_develop, office_email, office_tel) VALUES ('" . $conn->real_escape_string($office_s) . "','" . $conn->real_escape_string($year_s) . "','" . $conn->real_escape_string($logo_s) . "','" . $conn->real_escape_string($pri_s) . "','" . $conn->real_escape_string($sec_s) . "','" . $conn->real_escape_string($dev_s) . "','" . $conn->real_escape_string($email_s) . "','" . $conn->real_escape_string($tel_s) . "')");
        }
    }

    logfile($conn, 'แก้ไข', 'setting', 1, array(
        'office_name' => $office_s,
        'fiscal_year' => $year_s,
        'office_develop' => $dev_s,
        'office_email' => $email_s,
        'office_tel' => $tel_s,
    ));

    setFlash('success', '✅ บันทึกการตั้งค่าเรียบร้อยแล้ว');
    header("Location: setting.php"); exit;
}

// Reload setting
$setting_res = $conn->query("SELECT * FROM setting LIMIT 1");
$setting = $setting_res ? $setting_res->fetch_assoc() : array();
$office_name     = isset($setting['office_name']) ? $setting['office_name'] : 'สำนักงานศึกษาธิการจังหวัดนราธิวาส';
$fiscal_year     = isset($setting['fiscal_year']) ? $setting['fiscal_year'] : '2569';
$logo_url        = isset($setting['logo_url']) ? $setting['logo_url'] : '';
$theme_primary   = isset($setting['theme_primary']) ? $setting['theme_primary'] : '#1e3a8a';
$theme_secondary = isset($setting['theme_secondary']) ? $setting['theme_secondary'] : '#3b82f6';
$office_develop   = isset($setting['office_develop']) ? $setting['office_develop'] : 'ศน.ธนะวัฒน์ เลิศประเสริฐ';
$office_email     = isset($setting['office_email']) ? $setting['office_email'] : 'peo.nara96000@sueksa.go.th';
$office_tel       = isset($setting['office_tel']) ? $setting['office_tel'] : '073 530 515';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>การตั้งค่า - <?= htmlspecialchars($office_name) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/style.php'; ?>
</head>
<body>
<?php $activePage = 'setting'; include __DIR__ . '/menu.php'; ?>
        <div class="container-fluid">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        <div>
                            <div class="text-uppercase section-title mb-2">การตั้งค่า</div>
                            <h1 class="h3 fw-bold mb-2">⚙️ การตั้งค่าระบบ</h1>
                            <p class="text-muted mb-0">ปรับแต่งข้อมูลสำนักงานและธีมสีของระบบ</p>
                        </div>
                    </div>
                </div>
            </div>

            <?php getFlash(); ?>

            <div style="max-width:700px;">
            <form method="POST">
                <!-- Office Settings -->
                <div class="glass-card" style="margin-bottom:20px;">
                    <div class="form-section-title">🏛️ ข้อมูลสำนักงาน</div>
                    <div class="form-group" style="margin-bottom:16px;">
                        <label class="form-label">ชื่อสำนักงาน <span class="req">*</span></label>
                        <input type="text" name="office_name" class="form-control" required
                            value="<?php echo htmlspecialchars($office_name); ?>">
                    </div>
                    <div class="form-group" style="margin-bottom:16px;">
                        <label class="form-label">ปีงบประมาณปัจจุบัน <span class="req">*</span></label>
                        <select name="fiscal_year" class="form-control">
                            <?php for ($y=2570; $y>=2560; $y--): ?>
                            <option value="<?php echo $y; ?>" <?php echo $fiscal_year==$y?'selected':''; ?>><?php echo $y; ?></option>
                            <?php endfor; ?>
                        </select>
                        <div class="form-hint">ใช้สำหรับกรองข้อมูลเริ่มต้นและ Dashboard</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">URL โลโก้สำนักงาน</label>
                        <input type="text" name="logo_url" class="form-control"
                            placeholder="https://..."
                            value="<?php echo htmlspecialchars($logo_url); ?>">
                        <div class="form-hint">URL รูปภาพโลโก้ที่จะแสดงใน Sidebar</div>
                        <?php if ($logo_url && strpos($logo_url,'http')===0): ?>
                        <div style="margin-top:8px;display:flex;align-items:center;gap:10px;">
                            <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="logo preview" style="width:50px;height:50px;object-fit:contain;border-radius:8px;border:1px solid #dbeafe;">
                            <span style="font-size:12px;color:#64748b;">พรีวิวโลโก้ปัจจุบัน</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Credit & Contact Settings -->
                <div class="glass-card" style="margin-bottom:20px;">
                    <div class="form-section-title">👨‍💻 ข้อมูลผู้พัฒนาและติดต่อ</div>
                    <div class="form-group" style="margin-bottom:16px;">
                        <label class="form-label">ชื่อผู้พัฒนาโปรแกรม (Credit)</label>
                        <input type="text" name="office_develop" class="form-control"
                            value="<?php echo htmlspecialchars($office_develop); ?>">
                    </div>
                    <div class="form-grid" style="margin-bottom:16px;">
                        <div class="form-group">
                            <label class="form-label">อีเมลติดต่อ</label>
                            <input type="email" name="office_email" class="form-control"
                                value="<?php echo htmlspecialchars($office_email); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">เบอร์โทรศัพท์สำนักงาน</label>
                            <input type="text" name="office_tel" class="form-control"
                                value="<?php echo htmlspecialchars($office_tel); ?>">
                        </div>
                    </div>
                </div>

                <!-- Theme Settings -->
                <div class="glass-card" style="margin-bottom:20px;">
                    <div class="form-section-title">🎨 ธีมสี</div>
                    <div class="form-grid" style="margin-bottom:16px;">
                        <div class="form-group">
                            <label class="form-label">สีหลัก (Primary)</label>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <input type="color" name="theme_primary" id="colorPrimary"
                                    value="<?php echo $theme_primary; ?>"
                                    style="width:50px;height:42px;border-radius:8px;border:1.5px solid #cbd5e1;cursor:pointer;padding:2px;">
                                <input type="text" id="colorPrimaryText"
                                    value="<?php echo $theme_primary; ?>"
                                    class="form-control"
                                    onchange="document.getElementById('colorPrimary').value=this.value"
                                    style="font-family:monospace;">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">สีรอง (Secondary)</label>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <input type="color" name="theme_secondary" id="colorSecondary"
                                    value="<?php echo $theme_secondary; ?>"
                                    style="width:50px;height:42px;border-radius:8px;border:1.5px solid #cbd5e1;cursor:pointer;padding:2px;">
                                <input type="text" id="colorSecondaryText"
                                    value="<?php echo $theme_secondary; ?>"
                                    class="form-control"
                                    onchange="document.getElementById('colorSecondary').value=this.value"
                                    style="font-family:monospace;">
                            </div>
                        </div>
                    </div>
                    <!-- Color Preview -->
                    <div style="border-radius:12px;overflow:hidden;box-shadow:var(--shadow-md);">
                        <div id="previewBar" style="background:<?php echo $theme_primary; ?>;padding:14px 18px;color:white;font-weight:700;font-size:14px;">
                            🏛️ <?php echo htmlspecialchars($office_name); ?> — ตัวอย่างสีหลัก
                        </div>
                        <div id="previewAccent" style="background:<?php echo $theme_secondary; ?>;padding:8px 18px;color:white;font-size:12px;font-weight:500;">
                            ระบบติดตามโครงการ e-Budget — สีรอง
                        </div>
                    </div>
                    <div class="form-hint" style="margin-top:8px;">⚠️ หมายเหตุ: การเปลี่ยนสีจะมีผลกับ Sidebar และ UI หลัก (ต้องรีเฟรชหน้า)</div>
                </div>

                <div style="display:flex;gap:12px;justify-content:flex-end;">
                    <a href="index.php" class="btn btn-secondary">ยกเลิก</a>
                    <button type="submit" class="btn btn-success" style="padding:12px 28px;">💾 บันทึกการตั้งค่า</button>
                </div>
            </form>
        </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    (function(){
        var cp = document.getElementById('colorPrimary');
        var cpText = document.getElementById('colorPrimaryText');
        var previewBar = document.getElementById('previewBar');
        if (cp && cpText && previewBar) {
            cp.addEventListener('input', function(){ cpText.value = this.value; previewBar.style.background = this.value; });
            cpText.addEventListener('change', function(){ cp.value = this.value; previewBar.style.background = this.value; });
        }
        var cs = document.getElementById('colorSecondary');
        var csText = document.getElementById('colorSecondaryText');
        var previewAccent = document.getElementById('previewAccent');
        if (cs && csText && previewAccent) {
            cs.addEventListener('input', function(){ csText.value = this.value; previewAccent.style.background = this.value; });
            csText.addEventListener('change', function(){ cs.value = this.value; previewAccent.style.background = this.value; });
        }
    })();
</script>
</body>
</html>
