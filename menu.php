<?php
// menu.php - Responsive sidebar (desktop) + off-canvas drawer (mobile)
if (!isset($activePage)) {
    $activePage = isset($_SERVER['PHP_SELF']) ? basename($_SERVER['PHP_SELF'], '.php') : '';
}

$logo_url = isset($logo_url) ? $logo_url : '';
$office_name = isset($office_name) ? $office_name : '';
$office_developer = isset($office_developer) ? $office_developer : '';
$office_email = isset($office_email) ? $office_email : '';
$office_tel = isset($office_tel) ? $office_tel : '';

function navActive($page) {
    global $activePage;
    return $activePage === $page ? 'active' : '';
}
function navParentActive($pages) {
    global $activePage;
    return in_array($activePage, $pages) ? 'active' : '';
}
function navParentShow($pages) {
    global $activePage;
    return in_array($activePage, $pages) ? 'show' : '';
}
?>
<div class="page-wrapper">

<?php /* ===== MOBILE HEADER (d-lg-none) ===== */ ?>
<nav class="mobile-header d-lg-none">
  <div class="d-flex align-items-center w-100 gap-2">
    <button class="btn btn-link text-white p-0" type="button"
            data-bs-toggle="offcanvas" data-bs-target="#mobileMenu"
            aria-label="Toggle navigation">
      <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="currentColor" viewBox="0 0 16 16">
        <path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5z"/>
      </svg>
    </button>
    <div class="d-flex align-items-center gap-2 flex-grow-1 justify-content-center">
      <?php if (!empty($logo_url)): ?>
        <img src="<?= htmlspecialchars($logo_url) ?>" alt="logo" class="nav-logo">
      <?php endif; ?>
      <div class="text-center">
        <div class="mobile-office-name"><?= htmlspecialchars($office_name) ?></div>
      </div>
    </div>
    <?php if (isLoggedIn()): ?>
    <a href="profile.php" class="btn btn-link text-white p-0">
      <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" viewBox="0 0 16 16">
        <path d="M3 14s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H3zm5-6a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
      </svg>
    </a>
    <?php endif; ?>
  </div>
</nav>

<?php /* ===== DESKTOP SIDEBAR (d-none d-lg-flex) ===== */ ?>
<aside class="sidebar d-none d-lg-flex flex-column" id="desktopSidebar">
  <div class="sidebar-header">
    <div class="d-flex align-items-center gap-2 overflow-hidden">
      <?php if (!empty($logo_url)): ?>
        <img src="<?= htmlspecialchars($logo_url) ?>" alt="logo" class="sidebar-logo flex-shrink-0">
      <?php else: ?>
        <div class="sidebar-logo-placeholder flex-shrink-0">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
            <path d="M8 1a2.5 2.5 0 0 1 2.5 2.5V4h-5v-.5A2.5 2.5 0 0 1 8 1zm3.5 3v-.5a3.5 3.5 0 1 0-7 0V4H1v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V4h-3.5z"/>
          </svg>
        </div>
      <?php endif; ?>
      <div class="sidebar-brand-text text-truncate">
        <div class="fw-bold sidebar-office-name"><?= htmlspecialchars($office_name) ?></div>
        <div class="sidebar-subtitle"><?= htmlspecialchars($project_name_eng) ?></div>  
      </div>
    </div>
    <button class="sidebar-toggle-btn" onclick="toggleSidebar()" title="ย่อ/ขยายเมนู">
      <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16">
        <path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
      </svg>
    </button>
  </div>

  <div class="sidebar-body">
    <ul class="sidebar-nav">

      <!-- Dashboard -->
      <li class="sidebar-item">
        <a class="sidebar-link <?= navActive('index') ?>" href="index.php">
          <span class="sidebar-icon">📊</span>
          <span class="sidebar-text">แดชบอร์ด</span>
        </a>
      </li>

      <!-- OKR (ซ่อนชั่วคราว — ยังไม่ใช้) -->
      <?php if (false): ?>
      <li class="sidebar-item has-sub">
        <a class="sidebar-link <?= navParentActive(array('okr_agency_targets', 'okr_project_form')) ?>" href="javascript:void(0)" onclick="toggleSidebarSub(this)">
          <span class="sidebar-icon">🎯</span>
          <span class="sidebar-text">ติดตาม OKR</span>
          <span class="sidebar-arrow ms-auto">
            <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" fill="currentColor" viewBox="0 0 16 16">
              <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
            </svg>
          </span>
        </a>
        <div class="sidebar-sub <?= navParentShow(array('okr_agency_targets', 'okr_project_form')) ?>" id="okrSub">
          <ul>
            <li><a class="<?= navActive('okr_agency_targets') ?>" href="okr_agency_targets.php">📊 รายงานความก้าวหน้า</a></li>
            <?php if (isLoggedIn() && canCreateProject()): ?>
            <li><a class="<?= navActive('okr_project_form') ?>" href="okr_project_form.php">➕ เพิ่มโครงการ (OKR)</a></li>
            <?php endif; ?>
          </ul>
        </div>
      </li>
      <?php endif; ?>

      <!-- Projects -->
      <li class="sidebar-item has-sub">
        <a class="sidebar-link <?= navParentActive(array('projects', 'project_form', 'pview_project')) ?>" href="javascript:void(0)" onclick="toggleSidebarSub(this)">
          <span class="sidebar-icon">📋</span>
          <span class="sidebar-text">โครงการ & แผนงาน</span>
          <span class="sidebar-arrow ms-auto">
            <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" fill="currentColor" viewBox="0 0 16 16">
              <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
            </svg>
          </span>
        </a>
        <div class="sidebar-sub <?= navParentShow(array('projects', 'project_form', 'pview_project')) ?>" id="projectSub">
          <ul>
            <li><a class="<?= navActive('projects') ?>" href="projects.php">📋 รายการโครงการทั้งหมด</a></li>
            <?php if (isLoggedIn()): ?>
            <li><a class="<?= navActive('project_form') ?>" href="project_form.php">➕ เพิ่ม/แก้ไขโครงการ</a></li>
            <?php endif; ?>
          </ul>
        </div>
      </li>

      <!-- Budget -->
      <li class="sidebar-item has-sub">
        <a class="sidebar-link <?= navParentActive(array('budget_transactions', 'budget_income', 'budget_sources')) ?>" href="javascript:void(0)" onclick="toggleSidebarSub(this)">
          <span class="sidebar-icon">💰</span>
          <span class="sidebar-text">บริหารงบประมาณ</span>
          <span class="sidebar-arrow ms-auto">
            <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" fill="currentColor" viewBox="0 0 16 16">
              <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
            </svg>
          </span>
        </a>
        <div class="sidebar-sub <?= navParentShow(array('budget_transactions', 'budget_income', 'budget_sources')) ?>" id="budgetSub">
          <ul>
            <?php /* บันทึกการเบิกจ่าย (ซ่อนชั่วคราว) */ if (false): ?>
            <li><a class="<?= navActive('budget_transactions') ?>" href="budget_transactions.php">💳 บันทึกการเบิกจ่าย</a></li>
            <?php endif; ?>
            <li><a class="<?= navActive('budget_income') ?>" href="budget_income.php">📊 สรุปตามแหล่งเงิน</a></li>
            <li><a class="<?= navActive('budget_sources') ?>" href="budget_sources.php">🏦 แหล่งงบประมาณ</a></li>
          </ul>
        </div>
      </li>

      <!-- Agencies -->
      <li class="sidebar-item">
        <a class="sidebar-link <?= navActive('schools') ?>" href="schools.php">
          <span class="sidebar-icon">🏛️</span>
          <span class="sidebar-text">หน่วยงานการศึกษา</span>
        </a>
      </li>

      <!-- Download docs -->
      <li class="sidebar-item">
        <a class="sidebar-link <?= navActive('download_docs') ?>" href="download_docs.php">
          <span class="sidebar-icon">📁</span>
          <span class="sidebar-text">เอกสารดาวน์โหลด</span>
        </a>
      </li>

      <!-- Strategy / Objectives / KPI (plan + admin) -->
      <?php if (isAdminOrPlan()): ?>
      <li class="sidebar-item has-sub">
        <a class="sidebar-link <?= navParentActive(array('strategies', 'objectives', 'kpi_management')) ?>" href="javascript:void(0)" onclick="toggleSidebarSub(this)">
          <span class="sidebar-icon">🎯</span>
          <span class="sidebar-text">กำหนดยุทธศาสตร์ เป้าประสงค์ ตัวชี้วัด</span>
          <span class="sidebar-arrow ms-auto">
            <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" fill="currentColor" viewBox="0 0 16 16">
              <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
            </svg>
          </span>
        </a>
        <div class="sidebar-sub <?= navParentShow(array('strategies', 'objectives', 'kpi_management')) ?>" id="strategyPlanSub">
          <ul>
            <li><a class="<?= navActive('strategies') ?>" href="strategies.php">🎯 ยุทธศาสตร์</a></li>
            <li><a class="<?= navActive('objectives') ?>" href="objectives.php">🎯 เป้าประสงค์</a></li>
            <li><a class="<?= navActive('kpi_management') ?>" href="kpi_management.php">📐 ตัวชี้วัด KPI</a></li>
          </ul>
        </div>
      </li>
      <?php endif; ?>

      <!-- Reports -->
      <li class="sidebar-item has-sub">
        <a class="sidebar-link <?= navParentActive(array('report', 'export_excel', 'export_pdf')) ?>" href="javascript:void(0)" onclick="toggleSidebarSub(this)">
          <span class="sidebar-icon">📈</span>
          <span class="sidebar-text">รายงาน & ส่งออก</span>
          <span class="sidebar-arrow ms-auto">
            <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" fill="currentColor" viewBox="0 0 16 16">
              <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
            </svg>
          </span>
        </a>
        <div class="sidebar-sub <?= navParentShow(array('report', 'export_excel', 'export_pdf')) ?>" id="reportSub">
          <ul>
            <li><a class="<?= navActive('report') ?>" href="report.php">📄 สรุปรายงานประจำปี</a></li>
            <li><a class="<?= navActive('export_excel') ?>" href="export_excel.php">📤 ส่งออก Excel</a></li>
            <li><a class="<?= navActive('export_pdf') ?>" href="export_pdf.php">📤 ส่งออก PDF</a></li>
          </ul>
        </div>
      </li>

      <!-- Divider -->
      <li class="sidebar-divider"></li>
      <li class="sidebar-label"><span class="sidebar-text">ระบบ & ความปลอดภัย</span></li>

      <!-- Admin only items -->
      <?php if (isAdmin()): ?>
      <li class="sidebar-item">
        <a class="sidebar-link <?= navActive('users') ?>" href="users.php">
          <span class="sidebar-icon">👥</span>
          <span class="sidebar-text">จัดการผู้ใช้งาน</span>
        </a>
      </li>
      <li class="sidebar-item">
        <a class="sidebar-link <?= navActive('logfile') ?>" href="logfile.php">
          <span class="sidebar-icon">📋</span>
          <span class="sidebar-text">บันทึกการใช้งาน</span>
        </a>
      </li>
      <?php endif; ?>

      <li class="sidebar-item">
        <a class="sidebar-link <?= navActive('setting') ?>" href="setting.php">
          <span class="sidebar-icon">⚙️</span>
          <span class="sidebar-text">ตั้งค่าระบบ</span>
        </a>
      </li>
    </ul>
  </div>

  <div class="sidebar-footer">
    <?php if (isLoggedIn()): ?>
    <a class="sidebar-link user-card" href="profile.php">
      <span class="sidebar-icon">👤</span>
      <span class="sidebar-text text-truncate"><?= htmlspecialchars(currentName()) ?></span>
      <span class="sidebar-badge ms-auto"><?= htmlspecialchars(strtoupper(currentRole())) ?></span>
    </a>
    <a class="sidebar-link logout-link" href="logout.php">
      <span class="sidebar-icon">🚪</span>
      <span class="sidebar-text">ออกจากระบบ</span>
    </a>
    <?php else: ?>
    <a class="sidebar-link" href="login.php">
      <span class="sidebar-icon">🔐</span>
      <span class="sidebar-text">ลงชื่อเข้าใช้</span>
    </a>
    <?php endif; ?>
  </div>
</aside>

<?php /* ===== MOBILE OFF-CANVAS DRAWER ===== */ ?>
<div class="offcanvas offcanvas-start d-lg-none" tabindex="-1" id="mobileMenu" aria-label="เมนูหลัก">
  <div class="offcanvas-header">
    <div class="d-flex align-items-center gap-2">
      <?php if (!empty($logo_url)): ?>
        <img src="<?= htmlspecialchars($logo_url) ?>" alt="logo" class="mobile-offcanvas-logo">
      <?php endif; ?>
      <div>
        <div class="fw-bold offcanvas-title"><?= htmlspecialchars($office_name) ?></div>
        <div class="small text-muted">ระบบติดตามงบประมาณและโครงการ</div>
      </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body p-0">
    <ul class="mobile-nav-list">
      <!-- User info -->
      <?php if (isLoggedIn()): ?>
      <li class="mobile-user-card">
        <div class="d-flex align-items-center gap-2">
          <span>👤</span>
          <div>
            <div class="fw-medium"><?= htmlspecialchars(currentName()) ?></div>
            <div class="small text-muted"><?= htmlspecialchars(currentPosition() ?: currentRole()) ?></div>
          </div>
          <span class="badge bg-primary ms-auto"><?= htmlspecialchars(roleLabel(currentRole())) ?></span>
        </div>
      </li>
      <?php endif; ?>

      <!-- Dashboard -->
      <li class="mobile-nav-item">
        <a class="mobile-nav-link <?= navActive('index') ?>" href="index.php">
          <span>📊</span> แดชบอร์ด
        </a>
      </li>

      <!-- OKR (ซ่อนชั่วคราว — ยังไม่ใช้) -->
      <?php if (false): ?>
      <li class="mobile-nav-item has-sub">
        <a class="mobile-nav-link <?= navParentActive(array('okr_agency_targets', 'okr_project_form')) ?>" href="#mOkrSub" data-bs-toggle="collapse">
          <span>🎯</span> ติดตาม OKR
          <span class="arrow ms-auto">▼</span>
        </a>
        <div class="collapse <?= navParentShow(array('okr_agency_targets', 'okr_project_form')) ?>" id="mOkrSub">
          <ul>
            <li><a class="<?= navActive('okr_agency_targets') ?>" href="okr_agency_targets.php">📊 รายงานความก้าวหน้า</a></li>
            <?php if (isLoggedIn() && canCreateProject()): ?>
            <li><a class="<?= navActive('okr_project_form') ?>" href="okr_project_form.php">➕ เพิ่มโครงการ (OKR)</a></li>
            <?php endif; ?>
          </ul>
        </div>
      </li>
      <?php endif; ?>

      <!-- Projects -->
      <li class="mobile-nav-item has-sub">
        <a class="mobile-nav-link <?= navParentActive(array('projects', 'project_form', 'pview_project')) ?>" href="#mProjectSub" data-bs-toggle="collapse">
          <span>📋</span> โครงการ & แผนงาน
          <span class="arrow ms-auto">▼</span>
        </a>
        <div class="collapse <?= navParentShow(array('projects', 'project_form', 'pview_project')) ?>" id="mProjectSub">
          <ul>
            <li><a class="<?= navActive('projects') ?>" href="projects.php">📋 รายการโครงการทั้งหมด</a></li>
            <?php if (isLoggedIn()): ?>
            <li><a class="<?= navActive('project_form') ?>" href="project_form.php">➕ เพิ่ม/แก้ไขโครงการ</a></li>
            <?php endif; ?>
          </ul>
        </div>
      </li>

      <!-- Budget -->
      <li class="mobile-nav-item has-sub">
        <a class="mobile-nav-link <?= navParentActive(array('budget_transactions', 'budget_income', 'budget_sources')) ?>" href="#mBudgetSub" data-bs-toggle="collapse">
          <span>💰</span> บริหารงบประมาณ
          <span class="arrow ms-auto">▼</span>
        </a>
        <div class="collapse <?= navParentShow(array('budget_transactions', 'budget_income', 'budget_sources')) ?>" id="mBudgetSub">
          <ul>
            <?php /* บันทึกการเบิกจ่าย (ซ่อนชั่วคราว) */ if (false): ?>
            <li><a class="<?= navActive('budget_transactions') ?>" href="budget_transactions.php">💳 บันทึกการเบิกจ่าย</a></li>
            <?php endif; ?>
            <li><a class="<?= navActive('budget_income') ?>" href="budget_income.php">📊 สรุปตามแหล่งเงิน</a></li>
            <li><a class="<?= navActive('budget_sources') ?>" href="budget_sources.php">🏦 แหล่งงบประมาณ</a></li>
          </ul>
        </div>
      </li>

      <!-- Agencies -->
      <li class="mobile-nav-item">
        <a class="mobile-nav-link <?= navActive('schools') ?>" href="schools.php">
          <span>🏛️</span> หน่วยงานการศึกษา
        </a>
      </li>

      <!-- Download docs -->
      <li class="mobile-nav-item">
        <a class="mobile-nav-link <?= navActive('download_docs') ?>" href="download_docs.php">
          <span>📁</span> เอกสารดาวน์โหลด
        </a>
      </li>

      <!-- Strategy / Objectives / KPI (plan + admin) -->
      <?php if (isAdminOrPlan()): ?>
      <li class="mobile-nav-item has-sub">
        <a class="mobile-nav-link <?= navParentActive(array('strategies', 'objectives', 'kpi_management')) ?>" href="#mStrategyPlanSub" data-bs-toggle="collapse">
          <span>🎯</span> กำหนดยุทธศาสตร์ เป้าประสงค์ ตัวชี้วัด
          <span class="arrow ms-auto">▼</span>
        </a>
        <div class="collapse <?= navParentShow(array('strategies', 'objectives', 'kpi_management')) ?>" id="mStrategyPlanSub">
          <ul>
            <li><a class="<?= navActive('strategies') ?>" href="strategies.php">🎯 ยุทธศาสตร์</a></li>
            <li><a class="<?= navActive('objectives') ?>" href="objectives.php">🎯 เป้าประสงค์</a></li>
            <li><a class="<?= navActive('kpi_management') ?>" href="kpi_management.php">📐 ตัวชี้วัด KPI</a></li>
          </ul>
        </div>
      </li>
      <?php endif; ?>

      <!-- Reports -->
      <li class="mobile-nav-item has-sub">
        <a class="mobile-nav-link <?= navParentActive(array('report', 'export_excel', 'export_pdf')) ?>" href="#mReportSub" data-bs-toggle="collapse">
          <span>📈</span> รายงาน & ส่งออก
          <span class="arrow ms-auto">▼</span>
        </a>
        <div class="collapse <?= navParentShow(array('report', 'export_excel', 'export_pdf')) ?>" id="mReportSub">
          <ul>
            <li><a class="<?= navActive('report') ?>" href="report.php">📄 สรุปรายงานประจำปี</a></li>
            <li><a class="<?= navActive('export_excel') ?>" href="export_excel.php">📤 ส่งออก Excel</a></li>
            <li><a class="<?= navActive('export_pdf') ?>" href="export_pdf.php">📤 ส่งออก PDF</a></li>
          </ul>
        </div>
      </li>

      <!-- Admin section -->
      <?php if (isAdmin()): ?>
      <li class="mobile-nav-section">ระบบ & ความปลอดภัย</li>
      <li class="mobile-nav-item">
        <a class="mobile-nav-link <?= navActive('users') ?>" href="users.php">
          <span>👥</span> จัดการผู้ใช้งาน
        </a>
      </li>
      <li class="mobile-nav-item">
        <a class="mobile-nav-link <?= navActive('logfile') ?>" href="logfile.php">
          <span>📋</span> บันทึกการใช้งาน
        </a>
      </li>
      <?php endif; ?>
      <li class="mobile-nav-item">
        <a class="mobile-nav-link <?= navActive('setting') ?>" href="setting.php">
          <span>⚙️</span> ตั้งค่าระบบ
        </a>
      </li>

      <!-- Logout -->
      <?php if (isLoggedIn()): ?>
      <li class="mobile-nav-item mt-2 border-top pt-2">
        <a class="mobile-nav-link text-danger" href="logout.php">
          <span>🚪</span> ออกจากระบบ
        </a>
      </li>
      <?php endif; ?>
    </ul>
  </div>
</div>

<script>
function toggleSidebar() {
    var sidebar = document.getElementById('desktopSidebar');
    if (!sidebar) return;
    sidebar.classList.toggle('collapsed');
    localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed') ? '1' : '0');
}
function toggleSidebarSub(link) {
    var sub = link.parentNode.querySelector('.sidebar-sub');
    if (!sub) return;
    sub.classList.toggle('show');
}
(function() {
    var sidebar = document.getElementById('desktopSidebar');
    if (sidebar && localStorage.getItem('sidebarCollapsed') === '1') {
        sidebar.classList.add('collapsed');
    }
})();
</script>
<main class="main-content">
