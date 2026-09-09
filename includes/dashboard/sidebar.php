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
        <div class="sidebar-heading-custom font-hindi">मुख्य नियंत्रण</div>
        
        <a href="<?php echo SITE_URL; ?>/admin/dashboard.php" class="sidebar-btn-custom font-hindi <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>" title="कंट्रोल पैनल / Dashboard">
            <i class="fa-solid fa-gauge-high"></i>
            <span>कंट्रोल पैनल</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/members.php" class="sidebar-btn-custom font-hindi <?php echo ($current_page === 'members.php' || $current_page === 'member-profile.php') ? 'active' : ''; ?>" title="सदस्य डायरेक्टरी / Members">
            <i class="fa-solid fa-users"></i>
            <span>सदस्य डायरेक्टरी</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/notices/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/notices/') !== false) ? 'active' : ''; ?>" title="नोटिस बोर्ड / Notices">
            <i class="fa-solid fa-bullhorn"></i>
            <span>नोटिस बोर्ड</span>
        </a>

        <div class="sidebar-heading-custom font-hindi">डिजिटल सेवाएं</div>
        
        <a href="<?php echo SITE_URL; ?>/admin/id-cards/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/id-cards/') !== false) ? 'active' : ''; ?>" title="डिजिटल आईडी कार्ड / ID Cards">
            <i class="fa-solid fa-id-card"></i>
            <span>डिजिटल ID कार्ड</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/wakalatnama/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/wakalatnama/') !== false) ? 'active' : ''; ?>" title="वकालतनामा प्रबंधन / Wakalatnama">
            <i class="fa-solid fa-file-signature"></i>
            <span>वकालतनामा प्रबंधन</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/rooms/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/rooms/') !== false) ? 'active' : ''; ?>" title="कक्ष / रूम आवंटन / Room Rent">
            <i class="fa-solid fa-door-open"></i>
            <span>कक्ष / रूम आवंटन</span>
        </a>

        <div class="sidebar-heading-custom font-hindi">कोष एवं प्रशासन</div>
        
        <a href="<?php echo SITE_URL; ?>/admin/association-fund/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/association-fund/') !== false) ? 'active' : ''; ?>" title="संघीय कोष / Association Fund">
            <i class="fa-solid fa-vault"></i>
            <span>संघीय कोष</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/advocate-fee.php" class="sidebar-btn-custom font-hindi <?php echo ($current_page === 'advocate-fee.php') ? 'active' : ''; ?>" title="बार शुल्क प्रबंधन / Bar Fee">
            <i class="fa-solid fa-indian-rupee-sign"></i>
            <span>शुल्क प्रबंधन</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/elections/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/elections/') !== false) ? 'active' : ''; ?>" title="चुनाव व्यवस्था / Elections">
            <i class="fa-solid fa-square-check"></i>
            <span>चुनाव व्यवस्था</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/office-bearers/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/office-bearers/') !== false) ? 'active' : ''; ?>" title="कार्यकारिणी पदाधिकारी / Office Bearers">
            <i class="fa-solid fa-sitemap"></i>
            <span>कार्यकारिणी व्यवस्था</span>
        </a>

    <?php elseif ($role === 'president'): ?>
        <!-- ================= PRESIDENT LINKS ================= -->
        <div class="sidebar-heading-custom font-hindi">मुख्य नियंत्रण</div>
        
        <a href="<?php echo SITE_URL; ?>/president/dashboard.php" class="sidebar-btn-custom font-hindi <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>" title="अध्यक्ष पटल / President Dashboard">
            <i class="fa-solid fa-gauge-high"></i>
            <span>अध्यक्ष पटल</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/members.php" class="sidebar-btn-custom font-hindi <?php echo ($current_page === 'members.php' || $current_page === 'member-profile.php') ? 'active' : ''; ?>" title="सदस्य सूची / View Members">
            <i class="fa-solid fa-users"></i>
            <span>सदस्य सूची</span>
        </a>

        <div class="sidebar-heading-custom font-hindi">समीक्षा एवं अनुमोदन</div>
        
        <a href="<?php echo SITE_URL; ?>/president/notices/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/president/notices/') !== false) ? 'active' : ''; ?>" title="नोटिस समीक्षा / Review Notices">
            <i class="fa-solid fa-clipboard-check"></i>
            <span>नोटिस समीक्षा</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/president/wakalatnama/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/president/wakalatnama/') !== false) ? 'active' : ''; ?>" title="वकालतनामा समीक्षा / Review Wakalatnama">
            <i class="fa-solid fa-file-shield"></i>
            <span>वकालतनामा समीक्षा</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/president/id-cards/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/president/id-cards/') !== false) ? 'active' : ''; ?>" title="आईडी कार्ड समीक्षा / Review ID Cards">
            <i class="fa-solid fa-id-card-clip"></i>
            <span>आईडी कार्ड समीक्षा</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/president/association-fund/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/president/association-fund/') !== false) ? 'active' : ''; ?>" title="कोष स्वीकृति / Fund Approvals">
            <i class="fa-solid fa-file-invoice-dollar"></i>
            <span>कोष स्वीकृति</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/president/elections/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/president/elections/') !== false) ? 'active' : ''; ?>" title="चुनाव समीक्षा / Election Review">
            <i class="fa-solid fa-square-poll-vertical"></i>
            <span>चुनाव समीक्षा</span>
        </a>

    <?php elseif ($role === 'mahasachiv'): ?>
        <!-- ================= MAHASACHIV LINKS ================= -->
        <div class="sidebar-heading-custom font-hindi">मुख्य नियंत्रण</div>
        
        <a href="<?php echo SITE_URL; ?>/mahasachiv/dashboard.php" class="sidebar-btn-custom font-hindi <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>" title="महासचिव पटल / Dashboard">
            <i class="fa-solid fa-gauge-high"></i>
            <span>महासचिव पटल</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/members.php" class="sidebar-btn-custom font-hindi <?php echo ($current_page === 'members.php' || $current_page === 'member-profile.php') ? 'active' : ''; ?>" title="सदस्य रिकॉर्ड / Members">
            <i class="fa-solid fa-address-book"></i>
            <span>सदस्य रिकॉर्ड</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/office-bearers.php" class="sidebar-btn-custom font-hindi <?php echo ($current_page === 'office-bearers.php') ? 'active' : ''; ?>" title="कार्यकारिणी सूची / Office Bearers">
            <i class="fa-solid fa-sitemap"></i>
            <span>कार्यकारिणी सूची</span>
        </a>

        <div class="sidebar-heading-custom font-hindi">प्रशासकीय प्रबंधन</div>
        
        <a href="<?php echo SITE_URL; ?>/mahasachiv/notices/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/mahasachiv/notices/') !== false) ? 'active' : ''; ?>" title="नोटिस बोर्ड प्रबंधन / Notices">
            <i class="fa-solid fa-envelope-open-text"></i>
            <span>नोटिस बोर्ड प्रबंधन</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/id-cards/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/id-cards/') !== false) ? 'active' : ''; ?>" title="आईडी कार्ड अनुरोध / ID Requests">
            <i class="fa-solid fa-id-card"></i>
            <span>आईडी कार्ड अनुरोध</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/mahasachiv/wakalatnama/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/wakalatnama/') !== false || strpos($_SERVER['REQUEST_URI'], '/mahasachiv/wakalatnama/') !== false) ? 'active' : ''; ?>" title="वकालतनामा अनुरोध / Wakalatnama">
            <i class="fa-solid fa-file-contract"></i>
            <span>वकालतनामा अनुरोध</span>
        </a>

        <div class="sidebar-heading-custom font-hindi">कोष एवं संसाधन</div>
        
        <a href="<?php echo SITE_URL; ?>/admin/rooms/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/rooms/') !== false) ? 'active' : ''; ?>" title="कक्ष / चैंबर आवंटन / Rooms Allotment">
            <i class="fa-solid fa-house-laptop"></i>
            <span>कक्ष / चैंबर आवंटन</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/elections/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/elections/') !== false) ? 'active' : ''; ?>" title="चुनाव व्यवस्था / Election">
            <i class="fa-solid fa-square-check"></i>
            <span>चुनाव व्यवस्था</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/office-bearers/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/office-bearers/') !== false) ? 'active' : ''; ?>" title="कार्यकारिणी व्यवस्था / Bearers">
            <i class="fa-solid fa-people-group"></i>
            <span>कार्यकारिणी व्यवस्था</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/association-fund/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/admin/association-fund/') !== false) ? 'active' : ''; ?>" title="संघीय कोष / Assoc. Fund">
            <i class="fa-solid fa-vault"></i>
            <span>संघीय कोष</span>
        </a>

    <?php elseif ($role === 'member'): ?>
        <!-- ================= MEMBER LINKS ================= -->
        <div class="sidebar-heading-custom font-hindi">मुख्य पटल</div>
        
        <a href="<?php echo SITE_URL; ?>/member/dashboard.php" class="sidebar-btn-custom font-hindi <?php echo ($current_page === 'dashboard.php') ? 'active' : ''; ?>" title="सदस्य डैशबोर्ड / Dashboard">
            <i class="fa-solid fa-chart-pie"></i>
            <span>सदस्य डैशबोर्ड</span>
        </a>
        <?php if (!empty($user['member_id'])): ?>
            <a href="<?php echo SITE_URL; ?>/member-profile.php?id=<?php echo sanitize($user['member_id']); ?>" class="sidebar-btn-custom font-hindi <?php echo ($current_page === 'member-profile.php') ? 'active' : ''; ?>" title="मेरी प्रोफाइल / My Profile">
                <i class="fa-solid fa-user-circle"></i>
                <span>मेरी प्रोफाइल</span>
            </a>
        <?php endif; ?>

        <div class="sidebar-heading-custom font-hindi">व्यक्तिगत सेवाएं</div>
        
        <a href="<?php echo SITE_URL; ?>/member/id-card.php" class="sidebar-btn-custom font-hindi <?php echo ($current_page === 'id-card.php' && strpos($_SERVER['REQUEST_URI'], '/member/') !== false) ? 'active' : ''; ?>" title="डिजिटल आईडी कार्ड / ID Card">
            <i class="fa-solid fa-id-card-clip"></i>
            <span>डिजिटल ID कार्ड</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/member/wakalatnama/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/member/wakalatnama/') !== false) ? 'active' : ''; ?>" title="वकालतनामा जारी करें / Wakalatnama">
            <i class="fa-solid fa-file-lines"></i>
            <span>वकालतनामा जारी करें</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/member/fees/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/member/fees/') !== false) ? 'active' : ''; ?>" title="वार्षिक संघ शुल्क / Bar Fee">
            <i class="fa-solid fa-money-check-dollar"></i>
            <span>वार्षिक संघ शुल्क</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/member/chamber/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/member/chamber/') !== false) ? 'active' : ''; ?>" title="कक्ष किराया अनुरोध / Room Rent">
            <i class="fa-solid fa-building-user"></i>
            <span>कक्ष किराया अनुरोध</span>
        </a>

        <div class="sidebar-heading-custom font-hindi">संघीय जानकारी</div>
        
        <a href="<?php echo SITE_URL; ?>/member/election/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/member/election/') !== false) ? 'active' : ''; ?>" title="चुनाव केंद्र / Election Center">
            <i class="fa-solid fa-check-to-slot"></i>
            <span>चुनाव केंद्र</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/member/notices/index.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/member/notices/') !== false) ? 'active' : ''; ?>" title="संघीय सूचनाएं / Notices">
            <i class="fa-solid fa-envelope-open-text"></i>
            <span>संघीय सूचनाएं</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/member/association-fund.php" class="sidebar-btn-custom font-hindi <?php echo (strpos($_SERVER['REQUEST_URI'], '/member/association-fund.php') !== false) ? 'active' : ''; ?>" title="वित्तीय रिपोर्ट / Financial Report">
            <i class="fa-solid fa-receipt"></i>
            <span>वित्तीय रिपोर्ट</span>
        </a>
    <?php endif; ?>
</div>
