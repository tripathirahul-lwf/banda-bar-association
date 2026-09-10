<?php
/**
 * Navigation bar and institutional top header for District Bar Association, Banda
 */

// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit("Direct access forbidden.");
}
?>
<!-- Top Institutional Header Banner -->
<header class="py-2 py-md-3 bg-white border-bottom border-gold-custom brand-header-wrap" style="position: relative; z-index: 1030;">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <!-- Left Side: Shield Logo & Organization Title -->
            <div class="d-flex align-items-center flex-grow-1" style="min-width: 200px;">
                <a href="<?php echo SITE_URL; ?>/index.php" class="text-decoration-none me-2.5 me-md-3 flex-shrink-0">
                    <img src="<?php echo SITE_URL; ?>/assets/images/logo.png" alt="DBA Banda Official Emblem" class="rounded-circle shadow-sm brand-logo-img" style="width: 62px; height: 62px; object-fit: cover; border: 2.5px solid var(--gold-accent);" onerror="this.src='<?php echo SITE_URL; ?>/assets/images/logo.svg'">
                </a>
                <div class="overflow-hidden">
                    <h1 class="h4 mb-0 fw-bold text-navy-custom hindi-text brand-title-hi text-truncate">
                        <?php echo e(getSetting('association_name_hi', 'जिला अधिवक्ता संघ, बांदा')); ?>
                    </h1>
                    <h2 class="h6 mb-0 fw-semibold text-secondary-custom english-text text-uppercase tracking-wider brand-title-en text-truncate" style="font-size: 0.92rem; letter-spacing: 0.8px;">
                        <?php echo e(getSetting('association_name_en', 'District Bar Association, Banda')); ?>
                    </h2>
                    <div class="d-flex align-items-center gap-2 mt-0.5">
                        <span class="badge bg-gold-custom text-navy-custom font-hindi px-2 py-0.5 fw-bold brand-badge-estd" style="font-size: 0.7rem;">
                            <i class="fa-solid fa-award me-1"></i>स्थापना: <?php echo e(getSetting('established_year', '1937')); ?>
                        </span>
                        <span class="text-muted small english-text fw-medium d-none d-sm-inline" style="font-size: 0.72rem;">
                            • Uttar Pradesh
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Right Side: Login CTA / Member Info -->
            <div class="d-flex align-items-center flex-shrink-0 ms-auto">
                <?php if (is_logged_in()): ?>
                    <div class="dropdown">
                        <button class="btn btn-outline-navy btn-sm dropdown-toggle d-flex align-items-center gap-1.5 px-2.5 px-md-3 py-1.5 py-md-2 shadow-sm rounded-pill mobile-user-btn" type="button" id="userMenuButton" data-bs-toggle="dropdown" aria-expanded="false" style="border: 1.5px solid var(--gold-accent);">
                            <i class="fa-solid fa-circle-user fs-5 text-gold-custom"></i>
                            <span class="fw-medium text-truncate d-none d-sm-inline" style="max-width: 120px;"><?php echo sanitize($_SESSION['username']); ?></span>
                            <span class="badge bg-gold-custom text-navy-custom font-size-xs px-2"><?php echo strtoupper(sanitize($_SESSION['user_role'])); ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2" aria-labelledby="userMenuButton">
                            <li>
                                <a class="dropdown-item py-2 d-flex align-items-center gap-2 font-hindi" href="<?php echo SITE_URL; ?>/<?php echo sanitize($_SESSION['user_role']); ?>/index.php">
                                    <i class="fa-solid fa-gauge-high text-navy-custom"></i>
                                    <span>डैशबोर्ड (Dashboard)</span>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item py-2 text-danger d-flex align-items-center gap-2 font-hindi" href="<?php echo SITE_URL; ?>/login.php?action=logout">
                                    <i class="fa-solid fa-right-from-bracket"></i>
                                    <span>लॉगआउट (Logout)</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-navy d-inline-flex align-items-center gap-1.5 px-2.5 px-md-3 py-1.5 py-md-2 shadow-sm rounded-pill mobile-user-btn" style="border: 1.5px solid var(--gold-accent);">
                        <i class="fa-solid fa-lock text-gold-custom"></i>
                        <span class="fw-semibold font-hindi small">लॉगिन<span class="d-none d-md-inline"> / Login</span></span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<!-- Main Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-navy-custom py-1.5 py-lg-2 shadow-sm sticky-top">
    <div class="container">
        <!-- Mobile Bar Brand / Status Hint -->
        <span class="d-lg-none text-white-50 font-hindi small fw-medium">
            <i class="fa-solid fa-shield-halved text-gold-custom me-1"></i>अधिकृत पोर्टल
        </span>
        
        <!-- Toggler for Mobile Navigation with Clear Label -->
        <button class="navbar-toggler navbar-toggler-custom border-0 ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon me-1"></span>
            <span class="font-hindi small fw-bold">मेन्यू</span>
        </button>
        
        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 w-100 justify-content-between text-uppercase fw-semibold" style="font-size: 0.85rem; letter-spacing: 0.5px;">
                <li class="nav-item">
                    <a class="nav-link <?php echo is_page_active('index.php'); ?>" href="<?php echo SITE_URL; ?>/index.php">
                        <i class="bi bi-house-door-fill me-1 text-gold-custom"></i>मुख्य पृष्ठ (Home)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo is_page_active('about.php'); ?>" href="<?php echo SITE_URL; ?>/about.php">
                        <i class="fa-solid fa-landmark me-1 d-lg-none text-gold-custom"></i>परिचय (About Us)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo is_page_active('office-bearers.php'); ?>" href="<?php echo SITE_URL; ?>/office-bearers.php">
                        <i class="fa-solid fa-users-viewfinder me-1 d-lg-none text-gold-custom"></i>पदाधिकारी (Office Bearers)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo is_page_active('members.php'); ?> <?php echo is_page_active('member-profile.php'); ?>" href="<?php echo SITE_URL; ?>/members.php">
                        <i class="fa-solid fa-address-book me-1 d-lg-none text-gold-custom"></i>सदस्य (Members)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo is_page_active('notices.php'); ?>" href="<?php echo SITE_URL; ?>/notices.php">
                        <i class="fa-solid fa-bullhorn me-1 d-lg-none text-gold-custom"></i>सूचनाएं (Notices)
                    </a>
                </li>
                
                <!-- Dropdown for Member modules (keeps desktop navbar clean but highly detailed) -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-gold-custom fw-bold" href="#" id="servicesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fa-solid fa-layer-group me-1"></i>डिजिटल सेवाएं (Services)
                    </a>
                    <ul class="dropdown-menu border-0 shadow-lg mt-1 p-2" aria-labelledby="servicesDropdown" style="border-radius: 10px; min-width: 240px; border-top: 3px solid var(--gold-accent) !important;">
                        <li>
                            <a class="dropdown-item py-2 rounded d-flex align-items-center <?php echo is_page_active('id-card.php'); ?>" href="<?php echo SITE_URL; ?>/id-card.php">
                                <i class="fa-solid fa-id-card text-gold-dark me-2" style="width: 20px;"></i>डिजिटल ID Card
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2 rounded d-flex align-items-center <?php echo is_page_active('wakalatnama.php'); ?>" href="<?php echo SITE_URL; ?>/wakalatnama.php">
                                <i class="fa-solid fa-file-signature text-gold-dark me-2" style="width: 20px;"></i>वकालतनामा (Wakalatnama)
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2 rounded d-flex align-items-center <?php echo is_page_active('rooms.php'); ?>" href="<?php echo SITE_URL; ?>/rooms.php">
                                <i class="fa-solid fa-door-open text-gold-dark me-2" style="width: 20px;"></i>कक्ष/रूम किराया (Room Rent)
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2 rounded d-flex align-items-center <?php echo is_page_active('election.php'); ?>" href="<?php echo SITE_URL; ?>/election.php">
                                <i class="fa-solid fa-square-poll-vertical text-gold-dark me-2" style="width: 20px;"></i>चुनाव व्यवस्था (Election)
                            </a>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <a class="dropdown-item py-2 rounded d-flex align-items-center <?php echo is_page_active('association-fund.php'); ?>" href="<?php echo SITE_URL; ?>/association-fund.php">
                                <i class="fa-solid fa-building-columns text-gold-dark me-2" style="width: 20px;"></i>संघीय कोष (Assoc. Fund)
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2 rounded d-flex align-items-center <?php echo is_page_active('advocate-fee.php'); ?>" href="<?php echo SITE_URL; ?>/advocate-fee.php">
                                <i class="fa-solid fa-receipt text-gold-dark me-2" style="width: 20px;"></i>अधिवक्ता शुल्क (Bar Fee)
                            </a>
                        </li>
                    </ul>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?php echo is_page_active('contact.php'); ?>" href="<?php echo SITE_URL; ?>/contact.php">
                        <i class="fa-solid fa-envelope me-1 d-lg-none text-gold-custom"></i>संपर्क (Contact)
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Main Container Start -->
<main class="flex-shrink-0 py-4">
    <div class="container">
        <!-- Global Flash Messages Notification Block (Deferred on login page for inline card placement) -->
        <?php 
        $current_script = basename($_SERVER['SCRIPT_NAME'] ?? '');
        if ($current_script !== 'login.php'): 
            foreach (['success', 'danger', 'warning', 'info'] as $type): 
                if (has_flash_message($type)): ?>
                    <div class="alert alert-<?php echo $type; ?> alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4" role="alert">
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($type === 'success'): ?>
                                <i class="fa-solid fa-circle-check text-success fs-5"></i>
                            <?php elseif ($type === 'danger'): ?>
                                <i class="fa-solid fa-triangle-exclamation text-danger fs-5"></i>
                            <?php elseif ($type === 'warning'): ?>
                                <i class="fa-solid fa-circle-exclamation text-warning fs-5"></i>
                            <?php else: ?>
                                <i class="fa-solid fa-circle-info text-info fs-5"></i>
                            <?php endif; ?>
                            <div><?php echo get_flash_message($type); ?></div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; 
            endforeach; 
        endif; ?>
