<?php
/**
 * Modern Dashboard Reusable Header Layout
 * District Bar Association, Banda
 * Established: 1937
 */

// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit("Direct access forbidden.");
}

require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../permissions.php';

// Enforce login
requireLogin();

$user = currentUser();
$role = currentRole();
$name = $user['username'] ?? 'User';
$photo = 'profile/default_advocate.png';
$term_title = '';
$db = Database::getConnection();

if ($user && !empty($user['member_id']) && $db) {
    try {
        $m_stmt = $db->prepare("SELECT full_name, photo FROM members WHERE id = ?");
        $m_stmt->execute([$user['member_id']]);
        $m_info = $m_stmt->fetch();
        if ($m_info) {
            $name = $m_info['full_name'];
            $photo = $m_info['photo'] ?: 'profile/default_advocate.png';
            
            // Fetch active term if president or mahasachiv
            if (in_array($role, ['president', 'mahasachiv'])) {
                $pos_code = strtoupper($role);
                $term_stmt = $db->prepare("
                    SELECT t.title 
                    FROM office_bearers ob 
                    JOIN office_bearer_positions p ON p.id = ob.position_id
                    JOIN office_bearer_terms t ON t.id = ob.term_id
                    WHERE ob.member_id = ? AND ob.status = 'active' AND p.code = ? AND t.status = 'active'
                    LIMIT 1
                ");
                $term_stmt->execute([$user['member_id'], $pos_code]);
                $term_t = $term_stmt->fetchColumn();
                if ($term_t) {
                    $term_title = $term_t;
                }
            }
        }
    } catch (PDOException $e) {}
}

// Fetch unread notifications count
$unread_notif_count = 0;
if ($db) {
    try {
        $user_id = $_SESSION['user_id'] ?? 0;
        $member_id = $_SESSION['member_id'] ?? 0;
        $role = $_SESSION['role'] ?? '';
        
        $notif_stmt = $db->prepare("
            SELECT COUNT(*) FROM `notifications` 
            WHERE (`user_id` = ? OR (`member_id` = ? AND ? > 0) OR `role_target` = ?) AND `is_read` = 0
        ");
        $notif_stmt->execute([$user_id, $member_id, $member_id, $role]);
        $unread_notif_count = $notif_stmt->fetchColumn() ?: 0;
    } catch (PDOException $e) {}
}

$association_name_hi = getSetting('association_name_hi', 'जिला अधिवक्ता संघ, बांदा');
$association_name_en = getSetting('association_name_en', 'District Bar Association, Banda');
$fullTitle = (isset($pageTitle) ? $pageTitle . " | " : "") . $association_name_hi . " - Dashboard";
?>
<!DOCTYPE html>
<html lang="hi" class="h-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sanitize($fullTitle); ?></title>
    
    <!-- Google Fonts: Poppins, Noto Sans Devanagari & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Noto+Sans+Devanagari:wght@400;500;600;700;800&family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    
    <!-- FontAwesome 6 CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css">
    
    <!-- Custom Style Sheet -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
</head>
<body class="bg-light-custom text-dark-custom">

<div class="dashboard-wrapper">
    <!-- Collapsible Sidebar (Left Side) -->
    <div class="dashboard-sidebar" id="dashboardSidebar">
        <div class="dashboard-sidebar-header">
            <a href="<?php echo SITE_URL; ?>/index.php" class="dashboard-sidebar-brand text-decoration-none">
                <i class="fa-solid fa-scale-balanced"></i>
                <span class="font-hindi" style="font-size: 0.95rem;"><?php echo e(getSetting('established_year', '1937')); ?> DBA Banda</span>
            </a>
        </div>
        
        <div class="dashboard-sidebar-menu">
            <?php require_once __DIR__ . '/sidebar.php'; ?>
        </div>
    </div>

    <!-- Content Area (Right Side) -->
    <div class="dashboard-content-area">
        <!-- Sticky Topbar -->
        <header class="dashboard-topbar-custom">
            <!-- Sidebar toggle & Breadcrumbs -->
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-navy btn-sm" id="sidebarToggleBtn" title="Toggle Navigation Menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="d-none d-md-block">
                    <span class="text-secondary small font-hindi">डैशबोर्ड पटल (Secure) &bull; </span>
                    <span class="fw-semibold text-navy-custom small font-hindi"><?php echo sanitize($pageTitle); ?></span>
                </div>
            </div>

            <!-- Profile & Notification Panel -->
            <div class="d-flex align-items-center gap-2">
                <!-- Notifications -->
                <a href="<?php echo SITE_URL; ?>/account/notifications.php" class="btn btn-light btn-sm text-navy-custom position-relative border" title="Notifications" style="width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; border-radius: 50%;">
                    <i class="fa-solid fa-bell <?php echo $unread_notif_count > 0 ? 'text-danger animate-pulse' : 'text-secondary'; ?>"></i>
                    <?php if ($unread_notif_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.55rem; margin-left: -5px; margin-top: 5px;">
                            <?php echo $unread_notif_count; ?>
                        </span>
                    <?php endif; ?>
                </a>

                <!-- User Dropdown Menu -->
                <div class="dropdown">
                    <button class="btn btn-light btn-sm border dropdown-toggle d-flex align-items-center gap-2" type="button" id="topbarUserDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius: 20px; padding: 0.25rem 0.75rem 0.25rem 0.25rem;">
                        <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $photo; ?>" class="rounded-circle" style="width: 28px; height: 28px; object-fit: cover;" onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default_advocate.png'">
                        <span class="d-none d-sm-inline font-hindi fw-medium text-navy-custom" style="font-size: 0.8rem;"><?php echo sanitize($name); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2" aria-labelledby="topbarUserDropdown">
                        <li class="dropdown-header font-hindi">
                            <span class="d-block text-navy-custom fw-bold"><?php echo sanitize($name); ?></span>
                            <span class="badge bg-gold-custom text-navy-custom font-size-xs px-2 py-0.5 mt-1"><?php echo strtoupper(sanitize($role)); ?></span>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item font-hindi py-2 d-flex align-items-center gap-2" href="<?php echo SITE_URL; ?>/account/change-password.php">
                                <i class="fa-solid fa-key text-muted"></i>
                                <span>पासवर्ड बदलें (Security)</span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item font-hindi py-2 d-flex align-items-center gap-2" href="<?php echo SITE_URL; ?>/account/sessions.php">
                                <i class="fa-solid fa-laptop-code text-muted"></i>
                                <span>सक्रिय सत्र (Sessions)</span>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item font-hindi py-2 text-danger d-flex align-items-center gap-2" href="<?php echo SITE_URL; ?>/actions/logout.php">
                                <i class="fa-solid fa-right-from-bracket"></i>
                                <span>लॉगआउट (Secure Logout)</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Main Body Wrapper -->
        <main class="dashboard-main-body">
            <div class="dashboard-main-card">
