<?php
/**
 * Mahasachiv Dashboard - Dynamic Members & Pending Changes
 * District Bar Association, Banda
 */

$pageTitle = 'महासचिव डैशबोर्ड (Secretary Dashboard)';
require_once __DIR__ . '/../includes/dashboard/header.php';

// Enforce Mahasachiv Role
requireRole('mahasachiv');

// Database stats
$db = Database::getConnection();
$total_members = 0;
$pending_updates = 0;
$notices_count = 0;
$pending_id_cards = 0;
$pending_wakalatnamas = 0;
$chamber_pending_apps = 0;
$bar_fee_outstanding = 0.00;

if ($db) {
    try {
        $total_members = $db->query("SELECT COUNT(*) FROM members")->fetchColumn();
        $pending_updates = $db->query("SELECT COUNT(*) FROM member_profile_update_requests WHERE status = 'pending'")->fetchColumn();
        $notices_count = $db->query("SELECT COUNT(*) FROM notices WHERE status = 'published'")->fetchColumn();
        $pending_notices = $db->query("SELECT COUNT(*) FROM notices WHERE status = 'pending_approval'")->fetchColumn();
        $draft_notices = $db->query("SELECT COUNT(*) FROM notices WHERE status = 'draft'")->fetchColumn();
        $scheduled_notices = $db->query("SELECT COUNT(*) FROM notices WHERE status = 'scheduled'")->fetchColumn();
        $pending_id_cards = $db->query("SELECT COUNT(*) FROM id_card_applications WHERE status IN ('submitted', 'under_review')")->fetchColumn();
        $pending_wakalatnamas = $db->query("SELECT COUNT(*) FROM wakalatnamas WHERE status = 'pending_approval'")->fetchColumn();
        $chamber_pending_apps = $db->query("SELECT COUNT(*) FROM chamber_applications WHERE status = 'submitted'")->fetchColumn() ?: 0;
        $bar_fee_outstanding = $db->query("SELECT SUM(outstanding_amount) FROM member_fee_dues WHERE status != 'cancelled'")->fetchColumn() ?: 0.00;

        // Fetch Fund stats
        $fund_active_fy = $db->query("SELECT id FROM financial_years WHERE status = 'active' LIMIT 1")->fetch();
        $fund_summary = [
            'opening_balance' => 0.00,
            'total_income' => 0.00,
            'total_expense' => 0.00,
            'current_balance' => 0.00
        ];
        $fund_pending_count = 0;
        if ($fund_active_fy) {
            $fund_summary = getAssociationFundSummary($fund_active_fy['id'], $db);
            $fund_pending_count = $db->query("SELECT COUNT(*) FROM association_fund_transactions WHERE status = 'pending_approval'")->fetchColumn();
        }
    } catch (PDOException $e) {
        error_log("Failed to load secretary stats: " . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
    <h4 class="text-navy-custom font-hindi fw-bold mb-0">महासचिव कार्यालय पटल (General Secretary Office)</h4>
    <span class="badge bg-success text-white px-3 py-1 font-hindi fw-bold">सचिवालय जोन (Secure)</span>
</div>

<!-- Stats row -->
<div class="row g-3 mb-4 font-hindi small">
    <!-- Total members -->
    <div class="col-md-3 col-sm-6 col-12">
        <div class="card p-3 border-0 bg-navy-custom text-white h-100 shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span>कुल सदस्य (Members)</span>
                <i class="bi bi-people-fill fs-4 text-gold-custom"></i>
            </div>
            <h3 class="fw-bold mb-0"><?php echo sanitize($total_members); ?></h3>
        </div>
    </div>
    
    <!-- Member updates dynamic count -->
    <div class="col-md-3 col-sm-6 col-12">
        <div class="card p-3 border-0 bg-warning text-navy-custom h-100 shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span>सुधार अनुरोध (Pending Updates)</span>
                <i class="bi bi-person-lines-fill fs-4"></i>
            </div>
            <h3 class="fw-bold mb-0"><?php echo sanitize($pending_updates); ?></h3>
        </div>
    </div>

    <!-- ID Card Box -->
    <div class="col-md-3 col-sm-6 col-12">
        <div class="card p-3 border-0 bg-primary text-white h-100 shadow-sm">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span>आईडी कार्ड अनुरोध (ID Requests)</span>
                <i class="bi bi-card-image fs-4"></i>
            </div>
            <h3 class="fw-bold mb-0"><?php echo sanitize($pending_id_cards); ?></h3>
        </div>
    </div>

    <!-- Wakalatnama requests indicator -->
    <div class="col-md-3 col-sm-6 col-12">
        <a href="<?php echo SITE_URL; ?>/mahasachiv/wakalatnama/index.php" class="card p-3 border-0 bg-info text-white h-100 shadow-sm text-decoration-none d-block">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span>स्वीकृति लंबित वकालतनामा</span>
                <i class="bi bi-file-earmark-text fs-4 text-gold-custom"></i>
            </div>
            <h3 class="fw-bold mb-0"><?php echo sanitize($pending_wakalatnamas); ?></h3>
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Operations and quick stats -->
    <div class="col-lg-7 font-hindi small">
        <div class="border rounded-3 p-3 mb-4 bg-light-custom">
            <h6 class="fw-bold text-navy-custom mb-3 border-bottom pb-2">
                <i class="bi bi-journal-text text-gold-dark me-1"></i>कार्यालय कार्य विवरणी (Office Operations)
            </h6>
            
            <p class="text-muted mb-3" style="line-height: 1.7;">
                महासचिव के रूप में आप संघ के सचिवालय प्रमुख हैं। आपके दायित्वों में सदस्यता अभिलेखों का रख-रखाव, सूचनाओं का प्रकाशन, तथा संघ के चैंबरों/कक्षों के आवंटन को विनियमित करना शामिल है।
            </p>
            
            <div class="p-3 bg-white border rounded">
                <span class="fw-bold text-navy-custom d-block mb-2">सक्रिय नोटिस विवरण (Notices):</span>
                <div class="d-flex justify-content-between text-muted border-bottom py-1">
                    <span>सक्रिय सूचना पट्ट नोटिस (Published):</span>
                    <span class="fw-bold text-success"><?php echo sanitize($notices_count); ?> active</span>
                </div>
                <div class="d-flex justify-content-between text-muted border-bottom py-1">
                    <span>अनुमोदन लंबित (Pending Approval):</span>
                    <span class="fw-bold text-warning"><?php echo sanitize($pending_notices); ?> pending</span>
                </div>
                <div class="d-flex justify-content-between text-muted border-bottom py-1">
                    <span>अनुसूचित सूचनाएँ (Scheduled):</span>
                    <span class="fw-bold text-primary"><?php echo sanitize($scheduled_notices); ?></span>
                </div>
                <div class="d-flex justify-content-between text-muted py-1">
                    <span>ड्राफ्ट सूचनाएँ (Drafts):</span>
                    <span class="fw-bold text-secondary"><?php echo sanitize($draft_notices); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Quick actions panel -->
    <div class="col-lg-5 font-hindi small">
        <div class="border rounded-3 p-3 bg-light-custom h-100">
            <h6 class="fw-bold text-navy-custom mb-3 border-bottom pb-2">
                <i class="bi bi-briefcase-fill text-gold-custom me-1"></i>सचिवीय त्वरित कार्य (Quick Actions)
            </h6>
            
            <div class="d-flex flex-column gap-2">
                <a href="<?php echo SITE_URL; ?>/admin/members/create.php" class="btn btn-navy text-start py-2 d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-person-plus-fill me-2 text-gold-custom"></i>नया सदस्य जोड़ें (Add Member)</span>
                    <i class="bi bi-chevron-right text-gold-custom"></i>
                </a>
                <a href="<?php echo SITE_URL; ?>/admin/bar-fee/payments.php" class="btn btn-navy text-start py-2 d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-currency-rupee me-2 text-gold-custom"></i>शुल्क प्रविष्टि दर्ज करें (Record Fee)</span>
                    <i class="bi bi-chevron-right text-gold-custom"></i>
                </a>
                <a href="<?php echo SITE_URL; ?>/admin/rooms/applications.php" class="btn btn-navy text-start py-2 d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-door-closed-fill me-2 text-gold-custom"></i>चैंबर आवेदन सूची (Process Chamber)</span>
                    <?php if ($chamber_pending_apps > 0): ?>
                        <span class="badge bg-warning text-dark"><?php echo $chamber_pending_apps; ?> लंबित</span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo SITE_URL; ?>/admin/reports/index.php" class="btn btn-navy text-start py-2 d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-file-earmark-bar-graph-fill me-2 text-gold-custom"></i>केंद्रीय रिपोर्ट केंद्र (Reports)</span>
                    <i class="bi bi-chevron-right text-gold-custom"></i>
                </a>
                <a href="<?php echo SITE_URL; ?>/mahasachiv/notices/index.php" class="btn btn-navy text-start py-2 d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-bell-fill me-2 text-gold-custom"></i>नोटिस बोर्ड प्रकाशन (Notices)</span>
                    <i class="bi bi-chevron-right text-gold-custom"></i>
                </a>
                <a href="<?php echo SITE_URL; ?>/admin/id-cards/index.php" class="btn btn-navy text-start py-2 d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-card-image me-2 text-gold-custom"></i>आईडी कार्ड अनुरोध समीक्षा (ID Cards)</span>
                    <?php if ($pending_id_cards > 0): ?>
                        <span class="badge bg-danger"><?php echo $pending_id_cards; ?> लंबित</span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../includes/dashboard/footer.php';
?>
