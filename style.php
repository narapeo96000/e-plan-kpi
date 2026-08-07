<?php
// Shared styling for all pages
$theme_primary = isset($theme_primary) ? $theme_primary : '#2563eb';
$theme_secondary = isset($theme_secondary) ? $theme_secondary : '#0ea5e9';
?>
<style>
    :root {
        --primary: <?= htmlspecialchars($theme_primary) ?>;
        --secondary: <?= htmlspecialchars($theme_secondary) ?>;
        --sidebar-width: 260px;
        --sidebar-collapsed: 64px;
        --sidebar-bg: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
        --sidebar-hover: rgba(255,255,255,0.08);
        --sidebar-active: linear-gradient(90deg, rgba(37,99,235,0.3), transparent);
    }

    html {
        font-size: 84%;
    }

    body {
        font-family: 'Prompt', 'Noto Sans Thai', 'Segoe UI', sans-serif;
        font-size: 1rem;
        background: linear-gradient(135deg, #f8fbff 0%, #eef4ff 100%);
        color: #1f2937;
        overflow-x: hidden;
    }

    /* ===== PAGE WRAPPER (same as before for backward compat) ===== */
    .page-wrapper {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
    }

    /* ===== MOBILE HEADER ===== */
    .mobile-header {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        padding: 0.5rem 1rem;
        display: flex;
        align-items: center;
        box-shadow: 0 4px 20px rgba(0,0,0,0.12);
        z-index: 1040;
        position: sticky;
        top: 0;
    }

    .mobile-office-name {
        font-size: 0.85rem;
        font-weight: 600;
        color: #fff;
        line-height: 1.2;
    }

    .nav-logo {
        width: 36px;
        height: 36px;
        border-radius: 8px;
        object-fit: cover;
        flex-shrink: 0;
    }

    .mobile-offcanvas-logo {
        width: 40px;
        height: 40px;
        border-radius: 8px;
        object-fit: cover;
    }

    /* ===== DESKTOP SIDEBAR ===== */
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: var(--sidebar-width);
        height: 100vh;
        background: var(--sidebar-bg);
        color: rgba(255,255,255,0.85);
        z-index: 1030;
        overflow: hidden;
        transition: width 0.25s ease;
        box-shadow: 2px 0 20px rgba(0,0,0,0.15);
    }

    .sidebar.collapsed {
        width: var(--sidebar-collapsed);
    }

    .sidebar-header {
        display: flex;
        align-items: center;
        padding: 0.75rem;
        border-bottom: 1px solid rgba(255,255,255,0.08);
        flex-shrink: 0;
        min-height: 56px;
        gap: 0.5rem;
    }

    .sidebar-logo {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        object-fit: cover;
        flex-shrink: 0;
    }

    .sidebar-logo-placeholder {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: rgba(255,255,255,0.12);
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        color: rgba(255,255,255,0.6);
    }

    .sidebar-brand-text {
        white-space: nowrap;
        overflow: hidden;
        transition: opacity 0.2s;
    }

    .sidebar.collapsed .sidebar-brand-text {
        opacity: 0;
        width: 0;
    }

    .sidebar-office-name {
        font-size: 0.82rem;
        color: #fff;
        line-height: 1.3;
    }

    .sidebar-subtitle {
        font-size: 0.62rem;
        color: rgba(255,255,255,0.55);
    }

    .sidebar-toggle-btn {
        background: rgba(255,255,255,0.08);
        border: none;
        color: rgba(255,255,255,0.6);
        width: 28px;
        height: 28px;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        margin-left: auto;
        transition: transform 0.3s;
    }

    .sidebar-toggle-btn:hover {
        background: rgba(255,255,255,0.15);
        color: #fff;
    }

    .sidebar.collapsed .sidebar-toggle-btn {
        transform: rotate(180deg);
    }

    /* ===== SIDEBAR BODY (scrollable menu) ===== */
    .sidebar-body {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 0.5rem 0;
    }

    .sidebar-body::-webkit-scrollbar {
        width: 3px;
    }

    .sidebar-body::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.15);
        border-radius: 10px;
    }

    .sidebar-nav {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .sidebar-item {
        margin: 2px 0;
    }

    .sidebar-link {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.6rem 0.85rem;
        color: rgba(255,255,255,0.75);
        text-decoration: none;
        font-size: 0.82rem;
        border-radius: 0;
        margin: 0 0.5rem;
        border-radius: 8px;
        transition: all 0.15s;
        white-space: nowrap;
        overflow: hidden;
        cursor: pointer;
    }

    .sidebar-link:hover {
        background: var(--sidebar-hover);
        color: #fff;
    }

    .sidebar-link.active {
        background: var(--sidebar-active);
        color: #fff;
        font-weight: 500;
    }

    .sidebar-icon {
        font-size: 1.1rem;
        width: 24px;
        text-align: center;
        flex-shrink: 0;
        line-height: 1;
    }

    .sidebar-text {
        transition: opacity 0.2s;
        flex: 1;
    }

    .sidebar.collapsed .sidebar-text {
        opacity: 0;
        width: 0;
        overflow: hidden;
        flex: 0;
    }

    .sidebar.collapsed .sidebar-badge {
        display: none;
    }

    .sidebar.collapsed .sidebar-item .sidebar-link {
        justify-content: center;
        padding: 0.6rem 0;
        margin: 0 0.25rem;
    }

    .sidebar-arrow {
        font-size: 0.6rem;
        transition: transform 0.2s;
        flex-shrink: 0;
    }

    .sidebar-arrow svg {
        display: block;
    }

    .sidebar.collapsed .sidebar-arrow {
        display: none;
    }

    .sidebar-link[aria-expanded="true"] .sidebar-arrow {
        transform: rotate(90deg);
    }

    /* Submenu */
    .sidebar-sub {
        background: rgba(0,0,0,0.2);
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.25s ease;
    }

    .sidebar-sub.show {
        max-height: 400px;
    }

    .sidebar-sub ul {
        list-style: none;
        padding: 0.25rem 0;
        margin: 0;
    }

    .sidebar-sub li a {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 0.85rem 0.5rem 2.8rem;
        color: rgba(255,255,255,0.6);
        text-decoration: none;
        font-size: 0.78rem;
        transition: all 0.15s;
    }

    .sidebar-sub li a:hover {
        color: #fff;
        background: rgba(255,255,255,0.05);
    }

    .sidebar-sub li a.active {
        color: #93c5fd;
    }

    .sidebar.collapsed .sidebar-sub {
        display: none !important;
    }

    /* Divider & label */
    .sidebar-divider {
        height: 1px;
        background: rgba(255,255,255,0.08);
        margin: 0.5rem 0.85rem;
    }

    .sidebar-label {
        padding: 0.4rem 0.85rem;
        font-size: 0.68rem;
        color: rgba(255,255,255,0.35);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .sidebar.collapsed .sidebar-label {
        display: none;
    }

    /* Sidebar footer */
    .sidebar-footer {
        border-top: 1px solid rgba(255,255,255,0.08);
        padding: 0.5rem;
        flex-shrink: 0;
    }

    .sidebar-footer .sidebar-link {
        font-size: 0.78rem;
        padding: 0.5rem 0.75rem;
        margin: 0;
    }

    .sidebar-badge {
        font-size: 0.6rem;
        background: rgba(37,99,235,0.5);
        color: #fff;
        padding: 0.15rem 0.4rem;
        border-radius: 4px;
        font-weight: 600;
        letter-spacing: 0.03em;
    }

    .logout-link {
        opacity: 0.6;
    }

    .logout-link:hover {
        opacity: 1;
    }

    .sidebar.collapsed .user-card .sidebar-text {
        display: none;
    }

    /* ===== MAIN CONTENT ===== */
    .main-content {
        min-height: calc(100vh - 60px);
        padding: 1.5rem;
    }

    /* Desktop: push content to the right of sidebar */
    @media (min-width: 992px) {
        .main-content {
            margin-left: var(--sidebar-width);
            transition: margin-left 0.25s ease;
        }
        .page-wrapper .main-content {
            margin-left: var(--sidebar-width);
        }
        .sidebar.collapsed ~ .main-content,
        .sidebar.collapsed + .main-content {
            margin-left: var(--sidebar-collapsed);
        }
    }

    /* ===== MOBILE OFF-CANVAS ===== */
    .offcanvas-start {
        width: 300px !important;
    }

    .offcanvas-header {
        border-bottom: 1px solid rgba(0,0,0,0.06);
        padding: 1rem 1.25rem;
    }

    .offcanvas-title {
        font-size: 0.9rem;
    }

    .mobile-nav-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .mobile-user-card {
        padding: 1rem 1.25rem;
        background: #f8fafc;
        border-bottom: 1px solid #e5e7eb;
    }

    .mobile-nav-item {
        border-bottom: 1px solid rgba(0,0,0,0.04);
    }

    .mobile-nav-link {
        display: flex;
        align-items: center;
        gap: 0.65rem;
        padding: 0.75rem 1.25rem;
        color: #374151;
        text-decoration: none;
        font-size: 0.85rem;
        transition: all 0.1s;
        cursor: pointer;
    }

    .mobile-nav-link:hover {
        background: #f0f4ff;
        color: var(--primary);
    }

    .mobile-nav-link.active {
        background: #eff6ff;
        color: var(--primary);
        font-weight: 600;
    }

    .mobile-nav-link .arrow {
        font-size: 0.65rem;
        transition: transform 0.2s;
    }

    .mobile-nav-link[aria-expanded="true"] .arrow {
        transform: rotate(180deg);
    }

    .mobile-nav-item .collapse ul {
        list-style: none;
        padding: 0;
        margin: 0;
        background: #f9fafb;
    }

    .mobile-nav-item .collapse ul li a {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.6rem 1.25rem 0.6rem 3.2rem;
        color: #6b7280;
        text-decoration: none;
        font-size: 0.82rem;
    }

    .mobile-nav-item .collapse ul li a:hover {
        color: var(--primary);
        background: #f0f4ff;
    }

    .mobile-nav-item .collapse ul li a.active {
        color: var(--primary);
        font-weight: 500;
    }

    .mobile-nav-section {
        padding: 0.6rem 1.25rem 0.3rem;
        font-size: 0.7rem;
        text-transform: uppercase;
        color: #9ca3af;
        letter-spacing: 0.05em;
        font-weight: 600;
        border-top: 1px solid #e5e7eb;
        margin-top: 0.5rem;
    }

    /* ===== CARD & TABLE STYLES (preserved from before) ===== */
    .stat-card {
        border: 1px solid rgba(148,163,184,0.18);
        transition: transform .2s ease, box-shadow .2s ease;
    }

    .stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 14px 30px rgba(15,23,42,0.12);
    }

    .progress-bar-custom {
        height: 10px;
        border-radius: 999px;
        overflow: hidden;
    }

    .bar-ref-line {
        position: absolute;
        top: -4px;
        bottom: -4px;
        width: 2px;
        background: #ffffff;
        border-radius: 2px;
        box-shadow: 0 0 5px rgba(255,255,255,0.8);
        transform: translateX(-1px);
        pointer-events: none;
        z-index: 2;
    }

    .bar-pct-label {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        font-size: 0.68rem;
        font-weight: 700;
        line-height: 1;
        color: #ffffff;
        text-shadow: 0 1px 2px rgba(15,23,42,0.6);
        pointer-events: none;
        white-space: nowrap;
        z-index: 2;
    }

    .progress-bar-custom > div {
        background: linear-gradient(90deg, var(--primary), var(--secondary));
    }

    .small-text,
    .table-small {
        font-size: 0.78rem;
    }

    .stat-card .fs-3 {
        font-size: 1.1rem;
    }

    .display-6 {
        font-size: 1.4rem;
    }

    .badge-pill {
        font-size: 0.75rem;
    }

    .project-table th,
    .project-table td {
        font-size: 0.78rem;
    }

    .project-summary {
        font-size: 0.85rem;
    }

    .project-summary .fs-3 {
        font-size: 1rem;
    }

    .project-summary .small {
        font-size: 0.78rem;
    }

    .login-card {
        max-width: 460px;
        width: 100%;
        overflow: hidden;
    }

    .login-card .fw-bold {
        font-size: 1rem;
    }

    .login-card-hero {
        background: linear-gradient(135deg, var(--primary, #2563eb), var(--secondary, #0ea5e9));
        color: white;
    }

    .page-bg {
        background: linear-gradient(135deg, #f8fbff 0%, #eef4ff 100%);
    }

    .hero-panel {
        background: #ffffff;
        border: 1px solid rgba(15,23,42,0.06);
        border-left: 6px solid var(--primary);
        box-shadow: 0 24px 40px rgba(15,23,42,0.06);
    }

    .section-title {
        font-size: 1rem;
        font-weight: 700;
        color: #111827;
    }

    .section-action {
        color: var(--primary);
        font-weight: 700;
        text-decoration: none;
    }

    .section-action:hover {
        text-decoration: underline;
    }

    .list-row {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        background: #ffffff;
    }

    .list-row:last-child {
        border-bottom: none;
    }

    .list-row span {
        color: #374151;
        font-size: 0.95rem;
    }

    .list-row strong {
        color: var(--primary);
        font-size: 0.95rem;
    }

    .card {
        border-radius: 1.5rem;
    }

    .card-body {
        padding: 1.4rem;
    }

    .card.border-0 {
        border: none;
    }

    .project-table thead th {
        border-bottom: 2px solid #e5e7eb;
        background: #f8fafc;
        color: #111827;
        font-weight: 700;
        white-space: nowrap;
    }

    .project-table tbody tr {
        transition: all 0.2s ease;
    }

    .project-table tbody tr:hover {
        background: linear-gradient(90deg, rgba(37,99,235,0.09), rgba(14,165,233,0.05));
        box-shadow: inset 0 0 0 1px rgba(37,99,235,0.12);
        transform: translateY(-1px);
    }

    .project-table tbody tr td:first-child {
        position: relative;
        padding-left: 1.1rem;
    }

    .project-table tbody tr td:first-child::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0.75rem;
        bottom: 0.75rem;
        width: 0.25rem;
        border-radius: 999px;
        background: linear-gradient(180deg, var(--primary), var(--secondary));
        opacity: 0.8;
    }

    .project-title-text {
        color: #0f172a;
        margin-bottom: 0.45rem;
    }

    .project-meta-row {
        margin-top: 0.25rem;
    }

    .project-meta-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.25rem 0.55rem;
        border-radius: 999px;
        background: #f8fafc;
        color: #475569;
        font-size: 0.74rem;
    }

    .strategy-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.38rem 0.7rem;
        border-radius: 999px;
        background: linear-gradient(90deg, rgba(37,99,235,0.12), rgba(14,165,233,0.10));
        color: #1d4ed8;
        font-weight: 600;
        font-size: 0.78rem;
    }

    .budget-stack {
        min-width: 220px;
    }

    .updated-cell {
        min-width: 180px;
    }

    .status-indicator {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.38rem 0.7rem;
        border-radius: 999px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .status-indicator::before {
        content: '';
        width: 0.6rem;
        height: 0.6rem;
        border-radius: 50%;
        background: currentColor;
        opacity: 0.8;
    }

    .status-done {
        color: #047857;
        background: #ecfdf5;
        box-shadow: inset 0 0 0 1px rgba(4,120,87,0.14);
    }

    .status-progress {
        color: #1d4ed8;
        background: #eff6ff;
        box-shadow: inset 0 0 0 1px rgba(29,78,216,0.14);
    }

    .status-pending {
        color: #92400e;
        background: #fffbeb;
        box-shadow: inset 0 0 0 1px rgba(146,64,14,0.14);
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        border: none;
        box-shadow: 0 10px 20px rgba(37,99,235,0.12);
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #1d4ed8, #0ea5e9);
    }

    .project-table tbody tr:hover {
        background: rgba(59,130,246,0.04);
    }

    .badge {
        border-radius: 999px;
    }

    .text-muted {
        color: #6b7280 !important;
    }

    .text-primary {
        color: var(--primary) !important;
    }

    .bg-light {
        background-color: #f8fafc !important;
    }

    .btn-outline-primary {
        color: var(--primary);
        border-color: rgba(37,99,235,0.35);
    }

    .btn-outline-primary:hover {
        background: rgba(37,99,235,0.08);
    }

    .login-card .form-label,
    .login-card .form-control,
    .login-card .btn {
        font-size: 0.85rem;
    }

    .login-card .small {
        font-size: 0.78rem;
    }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 1199.98px) {
        .stat-card .fs-3 {
            font-size: 1.6rem;
        }
        .display-6 {
            font-size: 1.9rem;
        }
        .project-summary .fs-3 {
            font-size: 1.45rem;
        }
    }

    @media (max-width: 991.98px) {
        .main-content {
            padding: 1rem !important;
        }
        .table-responsive {
            font-size: 0.9rem;
        }
        h1.h3,
        h2.h4,
        .fw-bold {
            font-size: 1rem;
        }
        .display-6 {
            font-size: 1.5rem;
        }
        .stat-card .fs-3 {
            font-size: 1.3rem;
        }
        .badge {
            font-size: 0.78rem;
            padding: 0.45rem 0.7rem;
        }
        .card-body {
            padding: 1rem !important;
        }
    }

    @media (max-width: 576px) {
        .login-card {
            padding: 1.25rem !important;
        }
        .login-card .fw-bold {
            font-size: 0.95rem;
        }
        .login-card .form-label,
        .login-card .form-control,
        .login-card .btn {
            font-size: 0.82rem;
        }
        .login-card .small {
            font-size: 0.75rem;
        }
        .card-body {
            padding: 1rem !important;
        }
    }
</style>
