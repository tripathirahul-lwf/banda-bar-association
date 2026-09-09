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
<header class="py-3 bg-white border-bottom border-gold-custom" style="position: relative; z-index: 1030;">
    <div class="container">
        <div class="row align-items-center">
            <!-- Left Side: Shield Logo & Organization Title -->
            <div class="col-lg-8 col-md-7 col-12 d-flex align-items-center mb-3 mb-md-0">
                <a href="<?php echo SITE_URL; ?>/index.php" class="text-decoration-none me-3 flex-shrink-0">
                    <img src="<?php echo SITE_URL; ?>/assets/images/logo.png" alt="DBA Banda Official Emblem" class="rounded-circle shadow-sm" style="width: 66px; height: 66px; object-fit: cover; border: 2.5px solid var(--gold-accent);" onerror="this.src='<?php echo SITE_URL; ?>/assets/images/logo.svg'">
                </a>
                <div>
                    <h1 class="h4 mb-0 fw-bold text-navy-custom hindi-text d-flex align-items-center gap-2">
                        <?php echo e(getSetting('association_name_hi', 'जिला अधिवक्ता संघ, बांदा')); ?>
                    </h1>
                    <h2 class="h6 mb-0 fw-semibold text-secondary-custom english-text text-uppercase tracking-wider" style="font-size: 0.95rem; letter-spacing: 0.8px;">
                        <?php echo e(getSetting('association_name_en', 'District Bar Association, Banda')); ?>
                    </h2>
                    <div class="d-flex align-items-center gap-2 mt-1">
                        <span class="badge bg-gold-custom text-navy-custom font-hindi px-2.5 py-1 fw-bold" style="font-size: 0.72rem;">
                            <i class="fa-solid fa-award me-1"></i>स्थापना वर्ष <?php echo e(getSetting('established_year', '१९३७')); ?>
                        </span>
                        <span class="text-muted small english-text fw-medium" style="font-size: 0.75rem;">
                            | Estd. <?php echo e(getSetting('established_year', '1937')); ?> • Uttar Pradesh
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Right Side: Login CTA / Member Info -->
            <div class="col-lg-4 col-md-5 col-12 d-flex justify-content-md-end justify-content-start align-items-center">
                <?php if (is_logged_in()): ?>
                    <div class="dropdown">
                        <button class="btn btn-outline-navy btn-sm dropdown-toggle d-flex align-items-center gap-2 px-3 py-2 shadow-sm rounded-pill" type="button" id="userMenuButton" data-bs-toggle="dropdown" aria-expanded="false" style="border: 1.5px solid var(--gold-accent);">
                            <i class="fa-solid fa-circle-user fs-5 text-gold-custom"></i>
                            <span class="fw-medium text-truncate" style="max-width: 140px;"><?php echo sanitize($_SESSION['username']); ?></span>
                            <span class="badge bg-gold-custom text-navy-custom font-size-xs px-2"><?php echo strtoupper(sanitize($_SESSION['user_role'])); ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2" aria-labelledby="userMenuButton">
                            <li>
                                <a class="dropdown-item py-2 d-flex align-items-center gap-2" href="<?php echo SITE_URL; ?>/<?php echo sanitize($_SESSION['user_role']); ?>/index.php">
                                    <i class="fa-solid fa-gauge-high text-navy-custom"></i>
                                    <span>डैशबोर्ड (Dashboard)</span>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item py-2 text-danger d-flex align-items-center gap-2" href="<?php echo SITE_URL; ?>/login.php?action=logout">
                                    <i class="fa-solid fa-right-from-bracket"></i>
                                    <span>लॉगआउट (Logout)</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-navy d-inline-flex align-items-center gap-2 px-3 py-2 shadow-sm rounded-pill" style="border: 1.5px solid var(--gold-accent);">
                        <i class="fa-solid fa-lock text-gold-custom"></i>
                        <span class="fw-semibold font-hindi">सदस्य लॉगिन / Member Login</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<!-- Main Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-navy-custom py-2 shadow-sm sticky-top">
    <div class="container">
        <!-- Toggler for Mobile Navigation -->
        <button class="navbar-toggler border-0 ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 w-100 justify-content-between text-uppercase fw-semibold" style="font-size: 0.85rem; letter-spacing: 0.5px;">
                <li class="nav-item">
                    <a class="nav-link <?php echo is_page_active('index.php'); ?>" href="<?php echo SITE_URL; ?>/index.php">
                        <i class="bi bi-house-door-fill me-1"></i>मुख्य पृष्ठ (Home)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo is_page_active('about.php'); ?>" href="<?php echo SITE_URL; ?>/about.php">
                        परिचय (About Us)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo is_page_active('office-bearers.php'); ?>" href="<?php echo SITE_URL; ?>/office-bearers.php">
                        पदाधिकारी (Office Bearers)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo is_page_active('members.php'); ?> <?php echo is_page_active('member-profile.php'); ?>" href="<?php echo SITE_URL; ?>/members.php">
                        सदस्य (Members)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo is_page_active('notices.php'); ?>" href="<?php echo SITE_URL; ?>/notices.php">
                        सूचनाएं (Notices)
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
                        संपर्क (Contact)
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<!-- Main Container Start -->
<main class="flex-shrink-0 py-4">
    <div class="container">
        <!-- Global Flash Messages Notification Block -->
        <?php foreach (['success', 'danger', 'warning', 'info'] as $type): ?>
            <?php if (has_flash_message($type)): ?>
                <div class="alert alert-<?php echo $type; ?> alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4" role="alert">
                    <div class="d-flex align-items-center gap-2">
                        <?php if ($type === 'success'): ?>
                            <i class="bi bi-check-circle-fill text-success fs-5"></i>
                        <?php elseif ($type === 'danger'): ?>
                            <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
                        <?php elseif ($type === 'warning'): ?>
                            <i class="bi bi-exclamation-circle-fill text-warning fs-5"></i>
                        <?php else: ?>
                            <i class="bi bi-info-circle-fill text-info fs-5"></i>
                        <?php endif; ?>
                        <div><?php echo get_flash_message($type); ?></div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
