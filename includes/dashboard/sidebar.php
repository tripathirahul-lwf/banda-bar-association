<?php
/**
 * Modern Dashboard Sidebar Menu
 * District Bar Association, Banda
 * Established: 1937
 */

// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit("Direct access forbidden.");
}

$current_page = basename($_SERVER['SCRIPT_NAME']);
$role = currentRole();
?>

<div class="d-flex flex-column gap-1">
    <?php if ($role === 'admin'): ?>
        <!-- ================= ADMIN LINKS ================= -->
        <div class="sidebar-heading-custom font-hindi">मुख्य नियंत्रण (Core Panel)</div>
        
        <a href="<?php echo SITE_URL; ?>/admin/dashboard.php" class="sidebar-btn-custom font-hindi <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-chart-line"></i>
            <span>कंट्रोल पैनल (Dashboard)</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/members.php" class="sidebar-btn-custom font-hindi <?php echo ($current_page === 'members.php' || $current_page === 'member-profile.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-users"></i>
            <span>सदस्य सूची (Members)</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/notices/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/notices/') !== false) ? 'active' : ''; ?>">
            <i class="fa-solid fa-bullhorn"></i>
            <span>नोटिस बोर्ड (Notices)</span>
        </a>

        <div class="sidebar-heading-custom font-hindi">सेवाएं एवं दस्तावेज़ (Services & Docs)</div>
        
        <a href="<?php echo SITE_URL; ?>/admin/id-cards/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/id-cards/') !== false) ? 'active' : ''; ?>">
            <i class="fa-solid fa-id-card"></i>
            <span>आईडी कार्ड सेवा (ID Cards)</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/wakalatnama/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/wakalatnama/') !== false) ? 'active' : ''; ?>">
            <i class="fa-solid fa-file-signature"></i>
            <span>वकालतनामा (Wakalatnama)</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/rooms/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/rooms/') !== false) ? 'active' : ''; ?>">
            <i class="fa-solid fa-door-closed"></i>
            <span>कक्ष किराया (Room Rent)</span>
        </a>

        <div class="sidebar-heading-custom font-hindi">कोष एवं प्रबंधन (Finance & Governance)</div>
        
        <a href="<?php echo SITE_URL; ?>/admin/association-fund/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/association-fund/') !== false) ? 'active' : ''; ?>">
            <i class="fa-solid fa-vault"></i>
            <span>संघीय कोष (Assoc. Fund)</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/advocate-fee.php" class="sidebar-btn-custom font-hindi <?php echo ($current_page === 'advocate-fee.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-indian-rupee-sign"></i>
            <span>शुल्क प्रबंधन (Bar Fee)</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/elections/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/elections/') !== false) ? 'active' : ''; ?>">
            <i class="fa-solid fa-square-check"></i>
            <span>चुनाव व्यवस्था (Election)</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/office-bearers/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/office-bearers/') !== false) ? 'active' : ''; ?>">
            <i class="fa-solid fa-sitemap"></i>
            <span>पदाधिकारी व्यवस्था (Bearers)</span>
        </a>

    <?php elseif ($role === 'president'): ?>
        <!-- ================= PRESIDENT LINKS ================= -->
        <div class="sidebar-heading-custom font-hindi">मुख्य नियंत्रण (Core Panel)</div>
        
        <a href="<?php echo SITE_URL; ?>/president/dashboard.php" class="sidebar-btn-custom font-hindi <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-gauge-high"></i>
            <span>अध्यक्ष पटल (Dashboard)</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/members.php" class="sidebar-btn-custom font-hindi <?php echo ($current_page === 'members.php' || $current_page === 'member-profile.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-users"></i>
            <span>सदस्य सूची (View Members)</span>
        </a>

        <div class="sidebar-heading-custom font-hindi">समीक्षा पटल (Approvals Board)</div>
        
        <a href="<?php echo SITE_URL; ?>/president/notices/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/president/notices/') !== false) ? 'active' : ''; ?>">
            <i class="fa-solid fa-clipboard-check"></i>
            <span>नोटिस समीक्षा (Review Notices)</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/president/wakalatnama/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/president/wakalatnama/') !== false) ? 'active' : ''; ?>">
            <i class="fa-solid fa-file-shield"></i>
            <span>वकालतनामा समीक्षा (Review Waka)</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/president/id-cards/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/president/id-cards/') !== false) ? 'active' : ''; ?>">
            <i class="fa-solid fa-id-card-clip"></i>
            <span>आईडी कार्ड समीक्षा (Review Cards)</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/president/association-fund/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/president/association-fund/') !== false) ? 'active' : ''; ?>">
            <i class="fa-solid fa-file-invoice-dollar"></i>
            <span>कोष समीक्षा (Fund Approvals)</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/president/elections/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/president/elections/') !== false) ? 'active' : ''; ?>">
            <i class="fa-solid fa-square-poll-vertical"></i>
            <span>चुनाव समीक्षा (Election Review)</span>
        </a>

    <?php elseif ($role === 'mahasachiv'): ?>
        <!-- ================= MAHASACHIV LINKS ================= -->
        <div class="sidebar-heading-custom font-hindi">मुख्य नियंत्रण (Core Panel)</div>
        
        <a href="<?php echo SITE_URL; ?>/mahasachiv/dashboard.php" class="sidebar-btn-custom font-hindi <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-gauge-high"></i>
            <span>महासचिव पटल (Dashboard)</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/members.php" class="sidebar-btn-custom font-hindi <?php echo ($current_page === 'members.php' || $current_page === 'member-profile.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-address-book"></i>
            <span>सदस्य रिकॉर्ड (Members)</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/office-bearers.php" class="sidebar-btn-custom font-hindi <?php echo ($current_page === 'office-bearers.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-sitemap"></i>
            <span>कार्यकारिणी सूची (Office Bearers)</span>
        </a>

        <div class="sidebar-heading-custom font-hindi">प्रशासकीय प्रबंधन (Administration)</div>
        
        <a href="<?php echo SITE_URL; ?>/mahasachiv/notices/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/mahasachiv/notices/') !== false) ? 'active' : ''; ?>">
            <i class="fa-solid fa-envelope-open-text"></i>
            <span>नोटिस बोर्ड प्रबंधन (Notices)</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/id-cards/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/id-cards/') !== false) ? 'active' : ''; ?>">
            <i class="fa-solid fa-id-card"></i>
            <span>आईडी कार्ड अनुरोध (ID Requests)</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/mahasachiv/wakalatnama/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/wakalatnama/') !== false || strpos($_SERVER['REQUEST_URI'], '/mahasachiv/wakalatnama/') !== false) ? 'active' : ''; ?>">
            <i class="fa-solid fa-file-contract"></i>
            <span>वकालतनामा अनुरोध (Wakalatnama)</span>
        </a>

        <div class="sidebar-heading-custom font-hindi">कोष एवं संसाधन (Finance & Assets)</div>
        
        <a href="<?php echo SITE_URL; ?>/admin/rooms/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/rooms/') !== false) ? 'active' : ''; ?>">
            <i class="fa-solid fa-house-laptop"></i>
            <span>चैंबर/कक्ष आबंटन (Rooms Allotment)</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/elections/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/elections/') !== false) ? 'active' : ''; ?>">
            <i class="fa-solid fa-square-check"></i>
            <span>चुनाव व्यवस्था (Election)</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/office-bearers/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/office-bearers/') !== false) ? 'active' : ''; ?>">
            <i class="fa-solid fa-people-group"></i>
            <span>पदाधिकारी व्यवस्था (Bearers)</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/association-fund/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/association-fund/') !== false) ? 'active' : ''; ?>">
            <i class="fa-solid fa-vault"></i>
            <span>संघीय कोष (Assoc. Fund)</span>
        </a>

    <?php elseif ($role === 'member'): ?>
        <!-- ================= MEMBER LINKS ================= -->
        <div class="sidebar-heading-custom font-hindi">मुख्य पटल (Member Dashboard)</div>
        
        <a href="<?php echo SITE_URL; ?>/member/dashboard.php" class="sidebar-btn-custom font-hindi <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>">
            <i class="fa-solid fa-chart-pie"></i>
            <span>सदस्य डैशबोर्ड (Dashboard)</span>
        </a>
        <?php if (!empty($user['member_id'])): ?>
            <a href="<?php echo SITE_URL; ?>/member-profile.php?id=<?php echo sanitize($user['member_id']); ?>" class="sidebar-btn-custom font-hindi <?php echo ($current_page === 'member-profile.php') ? 'active' : ''; ?>">
                <i class="fa-solid fa-user-circle"></i>
                <span>मेरी प्रोफाइल (My Profile)</span>
            </a>
        <?php endif; ?>

        <div class="sidebar-heading-custom font-hindi">व्यक्तिगत सेवाएं (Personal Services)</div>
        
        <a href="<?php echo SITE_URL; ?>/member/id-card.php" class="sidebar-btn-custom font-hindi <?php echo ($current_page === 'id-card.php' && strpos($_SERVER['REQUEST_URI'], '/member/') !== false) ? 'active' : ''; ?>">
            <i class="fa-solid fa-id-card-clip"></i>
            <span>डिजिटल ID Card (ID Card)</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/member/wakalatnama/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/member/wakalatnama/') !== false) ? 'active' : ''; ?>">
            <i class="fa-solid fa-file-lines"></i>
            <span>वकालतनामा खरीदें (Wakalatnama)</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/member/fees/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/member/fees/') !== false) ? 'active' : ''; ?>">
            <i class="fa-solid fa-money-check-dollar"></i>
            <span>वार्षिक संघ शुल्क (Bar Fee)</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/member/chamber/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/member/chamber/') !== false) ? 'active' : ''; ?>">
            <i class="fa-solid fa-building-user"></i>
            <span>कक्ष किराया अनुरोध (Room Rent)</span>
        </a>

        <div class="sidebar-heading-custom font-hindi">संघीय जानकारी (Association Info)</div>
        
        <a href="<?php echo SITE_URL; ?>/member/election/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/member/election/') !== false) ? 'active' : ''; ?>">
            <i class="fa-solid fa-check-to-slot"></i>
            <span>चुनाव केंद्र (Election Center)</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/member/notices/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/member/notices/') !== false) ? 'active' : ''; ?>">
            <i class="fa-solid fa-envelope-open-text"></i>
            <span>संघीय सूचनाएं (Notices)</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/member/association-fund.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/member/association-fund.php') !== false) ? 'active' : ''; ?>">
            <i class="fa-solid fa-receipt"></i>
            <span>वित्तीय रिपोर्ट (Assoc. Fund)</span>
        </a>
    <?php endif; ?>
</div>
