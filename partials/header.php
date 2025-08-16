<?php
if (defined('LAYOUT_BOOTSTRAPPED')) { return; }
define('LAYOUT_BOOTSTRAPPED', true);
?>

<?php
// partials/header.php - Design Moderno VisitorFlow
require_once __DIR__ . '/../app/config.php';
require_once __DIR__ . '/../app/helpers.php';

$me = current_user();
$currentPage = $_GET['page'] ?? 'dashboard';
$isPublic = (!$me && in_array($currentPage, ['login'], true));
$isAdmin = $me && !empty($me['is_admin']);
?><!doctype html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>VisitorFlow - Gestão de Visitantes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
            --secondary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #4ecdc4 0%, #44a08d 100%);
            --warning-gradient: linear-gradient(135deg, #ffb75e 0%, #ed8f47 100%);
            --danger-gradient: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            --sidebar-bg: #2c3e50;
            --sidebar-hover: #34495e;
            --card-shadow: 0 10px 30px rgba(0,0,0,0.1);
            --border-radius: 12px;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            margin: 0;
            min-height: 100vh;
        }

        /* Sidebar Moderna */
        .modern-sidebar {
            background: var(--sidebar-bg);
            width: 260px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 1000;
            transition: all 0.3s ease;
            box-shadow: 5px 0 20px rgba(0,0,0,0.1);
        }

        .modern-sidebar.collapsed {
            width: 70px;
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidebar-logo {
            width: 40px;
            height: 40px;
            background: var(--primary-gradient);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            font-weight: bold;
        }

        .sidebar-title {
            color: white;
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            transition: opacity 0.3s;
        }

        .modern-sidebar.collapsed .sidebar-title {
            opacity: 0;
        }

        .sidebar-menu {
            padding: 20px 0;
            list-style: none;
            margin: 0;
        }

        .sidebar-menu li {
            margin: 5px 15px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 15px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: var(--primary-gradient);
            color: white;
            transform: translateX(5px);
        }

        .sidebar-menu i {
            width: 20px;
            text-align: center;
            font-size: 16px;
        }

        .modern-sidebar.collapsed .menu-text {
            display: none;
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
        }

        .main-content.expanded {
            margin-left: 70px;
        }

        /* Top Bar */
        .top-bar {
            background: white;
            padding: 15px 25px;
            box-shadow: var(--card-shadow);
            display: flex;
            justify-content: between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .top-bar-left {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .sidebar-toggle {
            background: none;
            border: none;
            font-size: 18px;
            color: #666;
            cursor: pointer;
            padding: 8px;
            border-radius: 6px;
            transition: all 0.3s;
        }

        .sidebar-toggle:hover {
            background: #f8f9fa;
            color: #333;
        }

        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }

        .top-bar-right {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-left: auto;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 15px;
            background: #f8f9fa;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .user-info:hover {
            background: #e9ecef;
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            background: var(--primary-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .user-name {
            font-weight: 500;
            color: #333;
        }

        /* Dashboard Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin: 25px;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }

        .stat-card.secondary::before { background: var(--secondary-gradient); }
        .stat-card.success::before { background: var(--success-gradient); }
        .stat-card.warning::before { background: var(--warning-gradient); }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-title {
            color: #666;
            font-size: 14px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }

        .stat-icon.primary { background: var(--primary-gradient); }
        .stat-icon.secondary { background: var(--secondary-gradient); }
        .stat-icon.success { background: var(--success-gradient); }
        .stat-icon.warning { background: var(--warning-gradient); }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .stat-change {
            font-size: 12px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .stat-change.positive { color: #27ae60; }
        .stat-change.negative { color: #e74c3c; }

        /* Content Card */
        .content-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            margin: 25px;
            overflow: hidden;
        }

        .content-header {
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .content-title {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }

        .content-body {
            padding: 25px;
        }

        /* Buttons */
        .btn-modern {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary-modern {
            background: var(--primary-gradient);
            color: white;
        }

        .btn-primary-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 107, 53, 0.3);
        }

        .btn-secondary-modern {
            background: var(--secondary-gradient);
            color: white;
        }

        .btn-icon {
            background: none;
            border: none;
            padding: 8px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            color: #666;
        }

        .btn-icon:hover {
            background: #f8f9fa;
            color: #333;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .modern-sidebar {
                transform: translateX(-100%);
            }
            
            .modern-sidebar.mobile-open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                margin: 15px;
            }
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1080;
        }

        .toast-modern {
            background: white;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            border: none;
            overflow: hidden;
        }

        .toast-modern::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .toast-modern.success::before { background: var(--success-gradient); }
        .toast-modern.danger::before { background: var(--danger-gradient); }
        .toast-modern.warning::before { background: var(--warning-gradient); }
    </style>
</head>
<body>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer">
    <?php if (!empty($_SESSION['flash'])): ?>
        <?php foreach ($_SESSION['flash'] as $type => $messages): ?>
            <?php foreach ((array)$messages as $msg): ?>
                <div class="toast toast-modern <?= e($type) ?>" role="alert" data-bs-delay="4000">
                    <div class="toast-body d-flex align-items-center gap-3">
                        <i class="fas fa-<?= $type === 'success' ? 'check-circle' : ($type === 'danger' ? 'exclamation-circle' : 'info-circle') ?>"></i>
                        <span><?= e($msg) ?></span>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endforeach; $_SESSION['flash'] = []; ?>
    <?php endif; ?>
</div>

<?php if (!$isPublic): ?>
<!-- Sidebar -->
<nav class="modern-sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <i class="fas fa-user-friends"></i>
        </div>
        <h1 class="sidebar-title">VisitorFlow</h1>
    </div>
    
    <ul class="sidebar-menu">
        <li>
            <a href="?page=dashboard" class="<?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span class="menu-text">Dashboard</span>
            </a>
        </li>
        <li>
            <a href="?page=visitors" class="<?= $currentPage === 'visitors' ? 'active' : '' ?>">
                <i class="fas fa-users"></i>
                <span class="menu-text">Visitantes</span>
            </a>
        </li>
        <li>
            <a href="?page=access" class="<?= $currentPage === 'access' ? 'active' : '' ?>">
                <i class="fas fa-door-open"></i>
                <span class="menu-text">Controle de Acesso</span>
            </a>
        </li>
        <li>
            <a href="?page=analytics" class="<?= $currentPage === 'analytics' ? 'active' : '' ?>">
                <i class="fas fa-chart-bar"></i>
                <span class="menu-text">Relatórios</span>
            </a>
        </li>
        <?php if ($isAdmin): ?>
        <li>
            <a href="?page=users" class="<?= $currentPage === 'users' ? 'active' : '' ?>">
                <i class="fas fa-user-shield"></i>
                <span class="menu-text">Usuários</span>
            </a>
        </li>
        <li>
            <a href="?page=settings" class="<?= $currentPage === 'settings' ? 'active' : '' ?>">
                <i class="fas fa-cog"></i>
                <span class="menu-text">Configurações</span>
            </a>
        </li>
        <?php endif; ?>
    </ul>
</nav>

<!-- Main Content -->
<div class="main-content" id="mainContent">
    <!-- Top Bar -->
    <div class="top-bar">
        <div class="top-bar-left">
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <h1 class="page-title">
                <?php
                $pageTitles = [
                    'dashboard' => 'Dashboard',
                    'visitors' => 'Visitantes', 
                    'access' => 'Controle de Acesso',
                    'analytics' => 'Relatórios',
                    'users' => 'Usuários',
                    'settings' => 'Configurações'
                ];
                echo $pageTitles[$currentPage] ?? 'Dashboard';
                ?>
            </h1>
        </div>
        
        <div class="top-bar-right">
            <button class="btn-icon" title="Notificações">
                <i class="fas fa-bell"></i>
            </button>
            
            <div class="user-info dropdown" data-bs-toggle="dropdown">
                <div class="user-avatar">
                    <?= strtoupper(substr(current_user()['name'] ?? 'A', 0, 1)) ?>
                </div>
                <span class="user-name d-none d-md-inline">
                    <?= e(current_user()['name'] ?? 'Administrador') ?>
                </span>
                <i class="fas fa-chevron-down ms-2"></i>
                
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="?page=profile">
                        <i class="fas fa-user me-2"></i>Meu Perfil
                    </a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item text-danger" href="?action=logout">
  <i class="bi bi-box-arrow-right"></i> Sair
</a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Page Content -->
    <div class="page-content">
<?php else: ?>
    <!-- Layout Público -->
    <div class="container py-5">
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar Toggle
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const sidebarToggle = document.getElementById('sidebarToggle');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('mobile-open');
            } else {
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
            }
        });
    }
    
    // Auto-show toasts
    const toasts = document.querySelectorAll('.toast');
    toasts.forEach(toast => {
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
    });
    
    // Close mobile sidebar when clicking outside
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768 && 
            !sidebar.contains(e.target) && 
            !sidebarToggle.contains(e.target)) {
            sidebar.classList.remove('mobile-open');
        }
    });
});
</script>