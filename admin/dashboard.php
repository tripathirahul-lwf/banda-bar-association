<?php
/**
 * Admin Dashboard - Dynamic Seeding & Stats
 * District Bar Association, Banda
 */

$pageTitle = 'प्रशासक नियंत्रण पटल (Admin Control Panel)';
require_once __DIR__ . '/../includes/dashboard/header.php';

// Enforce Admin Role
requireRole('admin');

$db = Database::getConnection();
$total_members = 0;
$active_members = 0;
$pending_members = 0;
$suspended_members = 0;
$new_this_month = 0;
$active_notices = 0;
$recent_members = [];
$pending_id_cards = 0;
$active_wakalatnamas = 0;
$waka_downloads_today = 0;
$waka_downloads_month = 0;
$waka_pending_approvals = 0;

if ($db) {
    try {
        $total_members = $db->query("SELECT COUNT(*) FROM members")->fetchColumn();
        $active_members = $db->query("SELECT COUNT(*) FROM members WHERE membership_status = 'active'")->fetchColumn();
        $pending_members = $db->query("SELECT COUNT(*) FROM members WHERE membership_status = 'pending'")->fetchColumn();
        $suspended_members = $db->query("SELECT COUNT(*) FROM members WHERE membership_status = 'suspended'")->fetchColumn();
        
        $new_this_month = $db->query("SELECT COUNT(*) FROM members WHERE MONTH(member_since) = MONTH(CURRENT_DATE()) AND YEAR(member_since) = YEAR(CURRENT_DATE())")->fetchColumn();
        $active_notices = $db->query("SELECT COUNT(*) FROM notices WHERE status = 'published'")->fetchColumn();
        $pending_notices = $db->query("SELECT COUNT(*) FROM notices WHERE status = 'pending_approval'")->fetchColumn();
        $scheduled_notices = $db->query("SELECT COUNT(*) FROM notices WHERE status = 'scheduled'")->fetchColumn();
        $important_active_notices = $db->query("SELECT COUNT(*) FROM notices WHERE status = 'published' AND priority IN ('important', 'urgent')")->fetchColumn();
        $pending_id_cards = $db->query("SELECT COUNT(*) FROM id_card_applications WHERE status IN ('submitted', 'under_review')")->fetchColumn();
        
        $active_wakalatnamas = $db->query("SELECT COUNT(*) FROM wakalatnamas WHERE status = 'active'")->fetchColumn();
        $waka_downloads_today = $db->query("SELECT COUNT(*) FROM wakalatnama_downloads WHERE DATE(downloaded_at) = CURRENT_DATE() AND download_status = 'success'")->fetchColumn();
        $waka_downloads_month = $db->query("SELECT COUNT(*) FROM wakalatnama_downloads WHERE MONTH(downloaded_at) = MONTH(CURRENT_DATE()) AND YEAR(downloaded_at) = YEAR(CURRENT_DATE()) AND download_status = 'success'")->fetchColumn();
        $waka_pending_approvals = $db->query("SELECT COUNT(*) FROM wakalatnamas WHERE status = 'pending_approval'")->fetchColumn();
        
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

        // Fetch Bar Fee metrics (Phase 8)
        $bar_fee_collected_month = $db->query("SELECT SUM(amount) FROM bar_fee_payments WHERE MONTH(payment_date) = MONTH(CURRENT_DATE()) AND YEAR(payment_date) = YEAR(CURRENT_DATE()) AND status = 'confirmed'")->fetchColumn() ?: 0.00;
        $bar_fee_outstanding = $db->query("SELECT SUM(outstanding_amount) FROM member_fee_dues WHERE status != 'cancelled'")->fetchColumn() ?: 0.00;
        $bar_fee_overdue_count = $db->query("SELECT COUNT(DISTINCT member_id) FROM member_fee_dues WHERE status = 'overdue'")->fetchColumn() ?: 0;
        $bar_fee_payments_today = $db->query("SELECT COUNT(*) FROM bar_fee_payments WHERE DATE(payment_date) = CURRENT_DATE() AND status = 'confirmed'")->fetchColumn() ?: 0;

        // Fetch Chamber / Room Rent metrics (Phase 9)
        $chamber_total = $db->query("SELECT COUNT(*) FROM chambers")->fetchColumn() ?: 0;
        $chamber_available = $db->query("SELECT COUNT(*) FROM chambers WHERE status = 'available'")->fetchColumn() ?: 0;
        $chamber_occupied = $db->query("SELECT COUNT(*) FROM chambers WHERE status IN ('occupied', 'partially_occupied')")->fetchColumn() ?: 0;
        $chamber_pending_apps = $db->query("SELECT COUNT(*) FROM chamber_applications WHERE status = 'submitted'")->fetchColumn() ?: 0;
        $chamber_outstanding = $db->query("SELECT SUM(outstanding_amount) FROM chamber_rent_dues WHERE status NOT IN ('paid', 'waived', 'cancelled')")->fetchColumn() ?: 0.00;
        $chamber_collected_month = $db->query("SELECT SUM(amount) FROM chamber_rent_payments WHERE MONTH(payment_date) = MONTH(CURRENT_DATE()) AND YEAR(payment_date) = YEAR(CURRENT_DATE()) AND status = 'confirmed'")->fetchColumn() ?: 0.00;

        // Fetch Election metrics (Phase 10)
        $election_active = $db->query("SELECT * FROM elections WHERE status NOT IN ('completed', 'archived', 'cancelled') ORDER BY id DESC LIMIT 1")->fetch();
        $election_voters_count = 0;
        $election_candidates_count = 0;
        if ($election_active) {
            $election_voters_count = $db->query("SELECT COUNT(*) FROM election_voters WHERE election_id = {$election_active['id']} AND eligibility_status = 'eligible'")->fetchColumn() ?: 0;
            $election_candidates_count = $db->query("SELECT COUNT(*) FROM election_candidates WHERE election_id = {$election_active['id']} AND status = 'final_candidate'")->fetchColumn() ?: 0;
        }

        // Fetch Office Bearers metrics (Phase 11)
        $term_active = $db->query("SELECT * FROM office_bearer_terms WHERE status = 'active' LIMIT 1")->fetch();
        $active_president_name = '-';
        $active_mahasachiv_name = '-';
        $active_bearers_count = 0;
        if ($term_active) {
            $active_bearers_count = $db->query("SELECT COUNT(*) FROM office_bearers WHERE term_id = {$term_active['id']} AND status = 'active'")->fetchColumn() ?: 0;
            
            $pres_q = $db->query("
                SELECT display_name_snapshot 
                FROM office_bearers ob 
                JOIN office_bearer_positions p ON p.id = ob.position_id
                WHERE ob.term_id = {$term_active['id']} AND ob.status = 'active' AND p.code = 'PRESIDENT' 
                LIMIT 1
            ")->fetchColumn();
            if ($pres_q) $active_president_name = $pres_q;

            $sec_q = $db->query("
                SELECT display_name_snapshot 
                FROM office_bearers ob 
                JOIN office_bearer_positions p ON p.id = ob.position_id
                WHERE ob.term_id = {$term_active['id']} AND ob.status = 'active' AND p.code = 'MAHASACHIV' 
                LIMIT 1
            ")->fetchColumn();
            if ($sec_q) $active_mahasachiv_name = $sec_q;
        }

        // Fetch 5 most recent members
        $recent_stmt = $db->query("SELECT * FROM members ORDER BY id DESC LIMIT 5");
        $recent_members = $recent_stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to load admin dashboard dynamic stats: " . $e->getMessage());
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">प्रशासक नियंत्रण पटल (Admin Control Panel)</h4>
    <span class="badge bg-gold-custom text-navy-custom px-3 py-1 fw-bold">एडमिन ज़ोन (Secure)</span>
</div>

<!-- Dynamic Stat Cards Grid -->
<div class="row g-3 mb-4 font-hindi small">
    <!-- Total Members -->
    <div class="col-md-3 col-sm-6">
        <div class="card p-3 border-0 bg-navy-custom text-white h-100 shadow-sm" style="border-radius: 8px;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-white-50 fw-semibold d-block mb-1">कुल सदस्य (Total)</span>
                    <h3 class="fw-bold mb-0 text-white"><?php echo sanitize($total_members); ?></h3>
                </div>
                <div class="rounded-circle bg-white bg-opacity-15 d-flex align-items-center justify-content-center text-gold-custom" style="width: 44px; height: 44px; flex-shrink: 0;">
                    <i class="fa-solid fa-users fs-5 text-gold-custom"></i>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Active Members -->
    <div class="col-md-3 col-sm-6">
        <div class="card p-3 border-0 bg-success text-white h-100 shadow-sm" style="border-radius: 8px;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-white-50 fw-semibold d-block mb-1">सक्रिय सदस्य (Active)</span>
                    <h3 class="fw-bold mb-0 text-white"><?php echo sanitize($active_members); ?></h3>
                </div>
                <div class="rounded-circle bg-white bg-opacity-20 d-flex align-items-center justify-content-center text-white" style="width: 44px; height: 44px; flex-shrink: 0;">
                    <i class="fa-solid fa-circle-check fs-5"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Pending Members -->
    <div class="col-md-3 col-sm-6">
        <div class="card p-3 border-0 bg-warning text-navy-custom h-100 shadow-sm" style="border-radius: 8px;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-navy-custom text-opacity-75 fw-semibold d-block mb-1">लंबित सदस्य (Pending)</span>
                    <h3 class="fw-bold mb-0 text-navy-custom"><?php echo sanitize($pending_members); ?></h3>
                </div>
                <div class="rounded-circle bg-white bg-opacity-35 d-flex align-items-center justify-content-center text-navy-custom" style="width: 44px; height: 44px; flex-shrink: 0;">
                    <i class="fa-solid fa-clock-rotate-left fs-5"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Suspended Members -->
    <div class="col-md-3 col-sm-6">
        <div class="card p-3 border-0 bg-danger text-white h-100 shadow-sm" style="border-radius: 8px;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-white-50 fw-semibold d-block mb-1">निलंबित (Suspended)</span>
                    <h3 class="fw-bold mb-0 text-white"><?php echo sanitize($suspended_members); ?></h3>
                </div>
                <div class="rounded-circle bg-white bg-opacity-20 d-flex align-items-center justify-content-center text-white" style="width: 44px; height: 44px; flex-shrink: 0;">
                    <i class="fa-solid fa-circle-exclamation fs-5"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Secondary indicators -->
<div class="row g-3 mb-4 font-hindi small">
    <div class="col-md-6 col-12">
        <div class="card p-3 border-0 bg-white shadow-sm d-flex flex-row align-items-center gap-3" style="border-radius: 8px;">
            <div class="rounded-circle bg-primary bg-opacity-10 d-flex align-items-center justify-content-center text-primary" style="width: 44px; height: 44px; flex-shrink:0;">
                <i class="fa-solid fa-calendar-check fs-5"></i>
            </div>
            <div>
                <span class="text-secondary d-block mb-0.5">इस माह पंजीकृत नए सदस्य (New Members This Month)</span>
                <strong class="fs-5 text-navy-custom"><?php echo sanitize($new_this_month); ?> सदस्य</strong>
            </div>
        </div>
    </div>
    <div class="col-md-6 col-12">
        <div class="card p-3 border-0 bg-white shadow-sm d-flex flex-row align-items-center gap-3" style="border-radius: 8px;">
            <div class="rounded-circle bg-warning bg-opacity-10 d-flex align-items-center justify-content-center text-warning" style="width: 44px; height: 44px; flex-shrink:0;">
                <i class="fa-solid fa-bell fs-5"></i>
            </div>
            <div>
                <span class="text-secondary d-block mb-0.5">सक्रिय सूचनाएं (Active Notices count)</span>
                <strong class="fs-5 text-navy-custom"><?php echo sanitize($active_notices); ?> सूचनाएँ</strong>
            </div>
        </div>
    </div>
</div>

<!-- Wakalatnama Quick Metrics (Phase 5) -->
<div class="row g-3 mb-4 font-hindi small">
    <div class="col-12">
        <div class="card border-0 shadow-sm bg-white" style="border-radius: 8px;">
            <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between">
                <h6 class="fw-bold text-navy-custom mb-0"><i class="fa-solid fa-file-invoice text-gold-dark me-2"></i>वकालतनामा आवंटन सांख्यिकी (Wakalatnama Stats)</h6>
                <span class="badge bg-light text-navy-custom border px-2.5 py-1">अद्यतित (Live)</span>
            </div>
            <div class="card-body p-3">
                <div class="row g-3 text-center">
                    <div class="col-md-3 col-6">
                        <div class="p-2.5 border rounded bg-light-custom">
                            <span class="text-secondary d-block mb-1">सक्रिय दस्तावेज (Active)</span>
                            <strong class="text-navy-custom d-block fs-5"><?php echo $active_wakalatnamas; ?></strong>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-2.5 border rounded bg-light-custom">
                            <span class="text-secondary d-block mb-1">आज के डाउनलोड्स</span>
                            <strong class="text-success d-block fs-5"><?php echo $waka_downloads_today; ?></strong>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-2.5 border rounded bg-light-custom">
                            <span class="text-secondary d-block mb-1">इस माह के डाउनलोड्स</span>
                            <strong class="text-navy-custom d-block fs-5"><?php echo $waka_downloads_month; ?></strong>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-2.5 border rounded bg-light-custom">
                            <span class="text-secondary d-block mb-1">स्वीकृति लंबित</span>
                            <strong class="text-warning-dark d-block fs-5" style="color: #c27d00;"><?php echo $waka_pending_approvals; ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Notices Quick Metrics (Phase 6) -->
<div class="row g-3 mb-4 font-hindi small">
    <div class="col-12">
        <div class="card border-0 shadow-sm bg-white" style="border-radius: 8px;">
            <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between">
                <h6 class="fw-bold text-navy-custom mb-0"><i class="fa-solid fa-bell text-gold-dark me-2"></i>सूचना पट्ट सांख्यिकी (Notices Stats)</h6>
                <span class="badge bg-light text-navy-custom border px-2.5 py-1">सूचना प्रबंधन</span>
            </div>
            <div class="card-body p-3">
                <div class="row g-3 text-center">
                    <div class="col-md-3 col-6">
                        <div class="p-2.5 border rounded bg-light-custom">
                            <span class="text-secondary d-block mb-1">सक्रिय रूप से प्रकाशित</span>
                            <strong class="text-success d-block fs-5"><?php echo $active_notices; ?></strong>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-2.5 border rounded bg-light-custom">
                            <span class="text-secondary d-block mb-1">समीक्षा लंबित</span>
                            <strong class="text-warning-dark d-block fs-5" style="color: #c27d00;"><?php echo $pending_notices; ?></strong>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-2.5 border rounded bg-light-custom">
                            <span class="text-secondary d-block mb-1">अनुसूचित (Scheduled)</span>
                            <strong class="text-primary d-block fs-5"><?php echo $scheduled_notices; ?></strong>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-2.5 border rounded bg-light-custom">
                            <span class="text-secondary d-block mb-1">महत्वपूर्ण सक्रिय नोटिस</span>
                            <strong class="text-danger d-block fs-5"><?php echo $important_active_notices; ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Association Fund Quick Metrics (Phase 7) -->
<div class="row g-3 mb-4 font-hindi small">
    <div class="col-12">
        <div class="card border-0 shadow-sm bg-white" style="border-radius: 8px;">
            <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between">
                <h6 class="fw-bold text-navy-custom mb-0"><i class="fa-solid fa-vault text-gold-dark me-2"></i>संघीय कोष सांख्यिकी (Association Fund)</h6>
                <span class="badge bg-light text-navy-custom border px-2.5 py-1">वित्तीय सारांश</span>
            </div>
            <div class="card-body p-3">
                <div class="row g-3 text-center">
                    <div class="col-md-3 col-6">
                        <div class="p-2.5 border rounded bg-light-custom">
                            <span class="text-secondary d-block mb-1">कोष शेष (Current Balance)</span>
                            <strong class="text-success d-block fs-5">₹<?php echo number_format($fund_summary['current_balance'], 2); ?></strong>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-2.5 border rounded bg-light-custom">
                            <span class="text-secondary d-block mb-1">कुल प्राप्त आय (Income)</span>
                            <strong class="text-navy-custom d-block fs-5">₹<?php echo number_format($fund_summary['total_income'], 2); ?></strong>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-2.5 border rounded bg-light-custom">
                            <span class="text-secondary d-block mb-1">कुल स्वीकृत व्यय (Expense)</span>
                            <strong class="text-danger d-block fs-5">₹<?php echo number_format($fund_summary['total_expense'], 2); ?></strong>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-2.5 border rounded bg-light-custom">
                            <span class="text-secondary d-block mb-1">अनुमोदन लंबित</span>
                            <strong class="text-warning-dark d-block fs-5" style="color: #c27d00;"><?php echo $fund_pending_count; ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Advocate Bar Fee Quick Metrics (Phase 8) -->
<div class="row g-3 mb-4 font-hindi small">
    <div class="col-12">
        <div class="card border-0 shadow-sm bg-white" style="border-radius: 8px;">
            <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between">
                <h6 class="fw-bold text-navy-custom mb-0"><i class="fa-solid fa-indian-rupee-sign text-gold-dark me-2"></i>अधिवक्ता संघ शुल्क सांख्यिकी (Advocate Bar Fee Stats)</h6>
                <span class="badge bg-light text-navy-custom border px-2.5 py-1">सदस्य शुल्क</span>
            </div>
            <div class="card-body p-3">
                <div class="row g-3 text-center">
                    <div class="col-md-3 col-6">
                        <div class="p-2.5 border rounded bg-light-custom">
                            <span class="text-secondary d-block mb-1">इस माह कुल संग्रह (Collected)</span>
                            <strong class="text-success d-block fs-5">₹<?php echo number_format($bar_fee_collected_month, 2); ?></strong>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-2.5 border rounded bg-light-custom">
                            <span class="text-secondary d-block mb-1">कुल शेष बकाया (Outstanding)</span>
                            <strong class="text-danger d-block fs-5">₹<?php echo number_format($bar_fee_outstanding, 2); ?></strong>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-2.5 border rounded bg-light-custom">
                            <span class="text-secondary d-block mb-1">अवधिपार सदस्य (Overdue Members)</span>
                            <strong class="text-navy-custom d-block fs-5"><?php echo $bar_fee_overdue_count; ?></strong>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-2.5 border rounded bg-light-custom">
                            <span class="text-secondary d-block mb-1">आज प्राप्त भुगतान (Today)</span>
                            <strong class="text-warning-dark d-block fs-5" style="color: #c27d00;"><?php echo $bar_fee_payments_today; ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chamber & Room Rent Quick Metrics (Phase 9) -->
<div class="row g-3 mb-4 font-hindi small">
    <div class="col-12">
        <div class="card border-0 shadow-sm bg-white" style="border-radius: 8px;">
            <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between">
                <h6 class="fw-bold text-navy-custom mb-0"><i class="fa-solid fa-door-closed text-gold-dark me-2"></i>कक्ष / चैंबर किराया सांख्यिकी (Room Rent & Chamber Stats)</h6>
                <span class="badge bg-light text-navy-custom border px-2.5 py-1">चैंबर आवंटन</span>
            </div>
            <div class="card-body p-3">
                <div class="row g-3 text-center">
                    <div class="col-md-3 col-6">
                        <div class="p-2.5 border rounded bg-light-custom">
                            <span class="text-secondary d-block mb-1">कुल रिक्त कमरे (Available)</span>
                            <strong class="text-navy-custom d-block fs-5"><?php echo $chamber_available; ?> / <?php echo $chamber_total; ?></strong>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-2.5 border rounded bg-light-custom">
                            <span class="text-secondary d-block mb-1">कुल किराया बकाया (Outstanding)</span>
                            <strong class="text-danger d-block fs-5">₹<?php echo number_format($chamber_outstanding, 2); ?></strong>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-2.5 border rounded bg-light-custom">
                            <span class="text-secondary d-block mb-1">इस माह कुल संग्रह (Collected)</span>
                            <strong class="text-success d-block fs-5">₹<?php echo number_format($chamber_collected_month, 2); ?></strong>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-2.5 border rounded bg-light-custom">
                            <span class="text-secondary d-block mb-1">लंबित आवेदन (Pending Requests)</span>
                            <strong class="text-warning-dark d-block fs-5" style="color: #c27d00;"><?php echo $chamber_pending_apps; ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Election Quick Metrics (Phase 10) -->
<div class="row g-3 mb-4 font-hindi small">
    <div class="col-12">
        <div class="card border-0 shadow-sm bg-white" style="border-radius: 8px;">
            <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between">
                <h6 class="fw-bold text-navy-custom mb-0"><i class="fa-solid fa-square-check text-gold-dark me-2"></i>चुनाव व मतदाता सूची सांख्यिकी (Election & Voters Stats)</h6>
                <span class="badge bg-light text-navy-custom border px-2.5 py-1">चुनाव आयोग</span>
            </div>
            <div class="card-body p-3">
                <div class="row g-3 text-center">
                    <div class="col-md-3 col-6">
                        <div class="p-2.5 border rounded bg-light-custom">
                            <span class="text-secondary d-block mb-1">घोषित चुनाव</span>
                            <strong class="text-navy-custom d-block fs-5"><?php echo $election_active ? e($election_active['election_code']) : 'कोई नहीं'; ?></strong>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-2.5 border rounded bg-light-custom">
                            <span class="text-secondary d-block mb-1">चुनाव वर्तमान चरण (Stage)</span>
                            <strong class="text-danger d-block fs-5 text-uppercase"><?php echo $election_active ? e(str_replace('_', ' ', $election_active['status'])) : '-'; ?></strong>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-2.5 border rounded bg-light-custom">
                            <span class="text-secondary d-block mb-1">योग्य मतदाता सूची</span>
                            <strong class="text-success d-block fs-5"><?php echo $election_active ? $election_voters_count : 0; ?></strong>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-2.5 border rounded bg-light-custom">
                            <span class="text-secondary d-block mb-1">अंतिम प्रत्याशी (Candidates)</span>
                            <strong class="text-warning-dark d-block fs-5" style="color: #c27d00;"><?php echo $election_active ? $election_candidates_count : 0; ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Office Bearers Quick Metrics (Phase 11) -->
<div class="row g-3 mb-4 font-hindi small">
    <div class="col-12">
        <div class="card border-0 shadow-sm bg-white" style="border-radius: 8px;">
            <div class="card-header bg-transparent border-bottom py-3 d-flex align-items-center justify-content-between">
                <h6 class="fw-bold text-navy-custom mb-0"><i class="fa-solid fa-users-gear text-gold-dark me-2"></i>कार्यकारिणी समिति सांख्यिकी (Executive Committee Stats)</h6>
                <span class="badge bg-light text-navy-custom border px-2.5 py-1">पदाधिकारी</span>
            </div>
            <div class="card-body p-3">
                <div class="row g-3 text-center">
                    <div class="col-md-3 col-6">
                        <div class="p-2.5 border rounded bg-light-custom">
                            <span class="text-secondary d-block mb-1">सक्रिय कार्यकाल (Term)</span>
                            <strong class="text-navy-custom d-block fs-5"><?php echo $term_active ? e($term_active['title']) : 'कोई नहीं'; ?></strong>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-2.5 border rounded bg-light-custom">
                            <span class="text-secondary d-block mb-1">अध्यक्ष (President)</span>
                            <strong class="text-success d-block fs-5 text-truncate" style="max-width: 100%;"><?php echo e($active_president_name); ?></strong>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-2.5 border rounded bg-light-custom">
                            <span class="text-secondary d-block mb-1">महासचिव (Mahasachiv)</span>
                            <strong class="text-success d-block fs-5 text-truncate" style="max-width: 100%;"><?php echo e($active_mahasachiv_name); ?></strong>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="p-2.5 border rounded bg-light-custom">
                            <span class="text-secondary d-block mb-1">कुल पदाधिकारी</span>
                            <strong class="text-warning-dark d-block fs-5" style="color: #c27d00;"><?php echo $active_bearers_count; ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<div class="row g-4 mb-4">
    <!-- Left Column: Quick Links -->
    <div class="col-lg-5">
        <h5 class="text-navy-custom font-hindi fw-bold mb-3 border-bottom pb-2"><i class="fa-solid fa-cubes text-gold-custom me-2"></i>शीघ्र लिंक्स / मॉड्यूल</h5>
        <div class="list-group shadow-sm font-hindi small" style="border-radius: 8px; overflow: hidden;">
            <a href="<?php echo SITE_URL; ?>/admin/members/index.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2.5 px-3 border-light-subtle text-dark fw-semibold">
                <span><i class="fa-solid fa-users text-gold-dark me-2" style="width: 20px;"></i>अधिवक्ता सदस्य मास्टर (Members Master)</span>
                <i class="fa-solid fa-chevron-right text-muted" style="font-size: 0.75rem;"></i>
            </a>
            <a href="<?php echo SITE_URL; ?>/admin/notices/index.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2.5 px-3 border-light-subtle text-dark fw-semibold">
                <span><i class="fa-solid fa-bell text-gold-dark me-2" style="width: 20px;"></i>सूचना पट्ट प्रबंधन (Notices)</span>
                <i class="fa-solid fa-chevron-right text-muted" style="font-size: 0.75rem;"></i>
            </a>
            <a href="<?php echo SITE_URL; ?>/admin/office-bearers/index.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2.5 px-3 border-light-subtle text-dark fw-semibold">
                <span><i class="fa-solid fa-certificate text-gold-dark me-2" style="width: 20px;"></i>कार्यकारिणी समिति प्रबंधन (Bearers Master)</span>
                <i class="fa-solid fa-chevron-right text-muted" style="font-size: 0.75rem;"></i>
            </a>
            <a href="<?php echo SITE_URL; ?>/admin/id-cards/index.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2.5 px-3 border-light-subtle text-dark fw-semibold">
                <span><i class="fa-solid fa-id-card text-gold-dark me-2" style="width: 20px;"></i>डिजिटल ID Card प्रबंधन</span>
                <?php if ($pending_id_cards > 0): ?>
                    <span class="badge bg-danger bg-opacity-10 text-danger px-2.5 py-1" style="font-size: 0.7rem; font-weight: 600;"><?php echo $pending_id_cards; ?> लंबित</span>
                <?php else: ?>
                    <i class="fa-solid fa-chevron-right text-muted" style="font-size: 0.75rem;"></i>
                <?php endif; ?>
            </a>
            <a href="<?php echo SITE_URL; ?>/admin/wakalatnama/index.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2.5 px-3 border-light-subtle text-dark fw-semibold">
                <span><i class="fa-solid fa-file-invoice text-gold-dark me-2" style="width: 20px;"></i>वकालतनामा आवंटन (Wakalatnama)</span>
                <?php if ($waka_pending_approvals > 0): ?>
                    <span class="badge bg-warning bg-opacity-15 text-warning-dark px-2.5 py-1" style="color: #a36200; background-color: rgba(255, 193, 7, 0.15); font-size: 0.7rem; font-weight: 600;"><?php echo $waka_pending_approvals; ?> लंबित</span>
                <?php else: ?>
                    <i class="fa-solid fa-chevron-right text-muted" style="font-size: 0.75rem;"></i>
                <?php endif; ?>
            </a>
            <a href="<?php echo SITE_URL; ?>/admin/association-fund/index.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2.5 px-3 border-light-subtle text-dark fw-semibold">
                <span><i class="fa-solid fa-vault text-gold-dark me-2" style="width: 20px;"></i>संघीय कोष प्रबन्धन (Fund Management)</span>
                <?php if ($fund_pending_count > 0): ?>
                    <span class="badge bg-warning bg-opacity-15 text-warning-dark px-2.5 py-1" style="color: #a36200; background-color: rgba(255, 193, 7, 0.15); font-size: 0.7rem; font-weight: 600;"><?php echo $fund_pending_count; ?> लंबित</span>
                <?php else: ?>
                    <i class="fa-solid fa-chevron-right text-muted" style="font-size: 0.75rem;"></i>
                <?php endif; ?>
            </a>
            <a href="<?php echo SITE_URL; ?>/admin/bar-fee/index.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2.5 px-3 border-light-subtle text-dark fw-semibold">
                <span><i class="fa-solid fa-indian-rupee-sign text-gold-dark me-2" style="width: 20px;"></i>अधिवक्ता शुल्क प्रबन्धन (Bar Fee Management)</span>
                <i class="fa-solid fa-chevron-right text-muted" style="font-size: 0.75rem;"></i>
            </a>
            <a href="<?php echo SITE_URL; ?>/admin/rooms/index.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2.5 px-3 border-light-subtle text-dark fw-semibold">
                <span><i class="fa-solid fa-door-closed text-gold-dark me-2" style="width: 20px;"></i>कक्ष / चैंबर किराया (Room Rent & Chambers)</span>
                <?php if ($chamber_pending_apps > 0): ?>
                    <span class="badge bg-warning bg-opacity-15 text-warning-dark px-2.5 py-1" style="color: #a36200; background-color: rgba(255, 193, 7, 0.15); font-size: 0.7rem; font-weight: 600;"><?php echo $chamber_pending_apps; ?> आवेदन</span>
                <?php else: ?>
                    <i class="fa-solid fa-chevron-right text-muted" style="font-size: 0.75rem;"></i>
                <?php endif; ?>
            </a>
            <a href="<?php echo SITE_URL; ?>/admin/elections/index.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2.5 px-3 border-light-subtle text-dark fw-semibold">
                <span><i class="fa-solid fa-square-check text-gold-dark me-2" style="width: 20px;"></i>चुनाव व मतदाता सूची (Election Management)</span>
                <i class="fa-solid fa-chevron-right text-muted" style="font-size: 0.75rem;"></i>
            </a>
        </div>
    </div>

    <!-- Right Column: Universal Search & Audit Logs -->
    <div class="col-lg-7">
        <!-- Universal Header Search Box (Requirement 43) -->
        <div class="card border-0 shadow-sm mb-4 bg-white" style="border-radius: 8px;">
            <div class="card-header bg-transparent border-bottom py-3 fw-bold text-navy-custom">
                <i class="fa-solid fa-magnifying-glass text-gold-dark me-2"></i>वैश्विक खोज पटल (Universal Search)
            </div>
            <div class="card-body py-3">
                <form method="GET" action="<?php echo SITE_URL; ?>/admin/search.php" class="d-flex gap-2">
                    <input type="text" name="q" class="form-control form-control-sm py-1.5" required placeholder="अधिवक्ता नाम, सदस्य क्रमांक, रसीद नंबर, नोटिस नंबर दर्ज करें...">
                    <button type="submit" class="btn btn-sm btn-primary px-3 fw-bold">खोजें</button>
                </form>
            </div>
        </div>

        <!-- Recent Audit Logs (Requirement 24) -->
        <div class="card border-0 shadow-sm mb-4 bg-white" style="border-radius: 8px;">
            <div class="card-header bg-transparent border-bottom py-3 fw-bold text-navy-custom d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-user-shield text-gold-dark me-2"></i>नवीनतम ऑडिट गतिविधि (Audit Trail)</span>
                <a href="<?php echo SITE_URL; ?>/admin/audit-logs/index.php" class="btn btn-xs btn-link text-decoration-none p-0 fw-bold">सभी देखें</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.73rem;">
                        <thead>
                            <tr class="table-light text-muted">
                                <th class="px-3 py-2">समय</th>
                                <th class="py-2">यूज़र</th>
                                <th class="py-2">मॉड्यूल</th>
                                <th class="py-2">विवरण</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $recent_logs = [];
                            if ($db) {
                                try {
                                    $recent_logs = $db->query("
                                        SELECT a.created_at, COALESCE(u.username, 'System') AS username, a.module, a.action 
                                        FROM audit_logs a 
                                        LEFT JOIN users u ON u.id = a.user_id 
                                        ORDER BY a.id DESC LIMIT 4
                                    ")->fetchAll();
                                } catch (PDOException $e) {}
                            }
                            if (empty($recent_logs)): 
                            ?>
                                <tr>
                                    <td colspan="4" class="text-center py-3 text-muted px-3">कोई ऑडिट प्रविष्टि उपलब्ध नहीं है।</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_logs as $rl): ?>
                                    <tr>
                                        <td class="english-text text-muted px-3"><?php echo date('H:i A', strtotime($rl['created_at'])); ?></td>
                                        <td><strong><?php echo e($rl['username']); ?></strong></td>
                                        <td class="text-uppercase text-secondary fw-bold"><?php echo e($rl['module']); ?></td>
                                        <td class="text-dark"><?php echo e($rl['action']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Quick System Settings Controls (Requirement 44) -->
        <div class="card border-0 shadow-sm bg-white" style="border-radius: 8px;">
            <div class="card-header bg-transparent border-bottom py-3 fw-bold text-navy-custom">
                <i class="fa-solid fa-gears text-gold-dark me-2"></i>ग्लोबल सिस्टम सेटिंग्स एवं बैकअप
            </div>
            <div class="card-body py-3">
                <div class="row g-2">
                    <div class="col-6">
                        <a href="<?php echo SITE_URL; ?>/admin/settings/general.php" class="btn btn-outline-secondary btn-sm w-100 py-2"><i class="fa-solid fa-gear me-1"></i>सामान्य विन्यास</a>
                    </div>
                    <div class="col-6">
                        <a href="<?php echo SITE_URL; ?>/admin/settings/branding.php" class="btn btn-outline-secondary btn-sm w-100 py-2"><i class="fa-solid fa-palette me-1"></i>ब्रांडिंग सेटिंग्स</a>
                    </div>
                    <div class="col-6">
                        <a href="<?php echo SITE_URL; ?>/admin/settings/homepage.php" class="btn btn-outline-secondary btn-sm w-100 py-2"><i class="fa-solid fa-window-restore me-1"></i>होमपेज सेटिंग्स</a>
                    </div>
                    <div class="col-6">
                        <a href="<?php echo SITE_URL; ?>/admin/settings/backup.php" class="btn btn-outline-danger btn-sm w-100 py-2"><i class="fa-solid fa-download me-1"></i>डेटाबेस बैकअप</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../includes/dashboard/footer.php';
?>
