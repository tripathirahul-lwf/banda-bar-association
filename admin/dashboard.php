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
$pending_notices = 0;
$scheduled_notices = 0;
$important_active_notices = 0;
$pending_id_cards = 0;
$issued_id_cards = 0;
$active_wakalatnamas = 0;
$waka_downloads_today = 0;
$waka_downloads_month = 0;
$waka_pending_approvals = 0;
$recent_members = [];
$total_db_tables = 0;
$mysql_version = 'MySQL';

// Chart.js 6-Month Trend Data Structures
$chart_months = [];
$chart_income = [];
$chart_expense = [];
for ($i = 5; $i >= 0; $i--) {
    $m_key = date('Y-m', strtotime("-$i months"));
    $chart_months[$m_key] = date('M y', strtotime("-$i months"));
    $chart_income[$m_key] = 0.00;
    $chart_expense[$m_key] = 0.00;
}

if ($db) {
    try {
        $total_members = (int)$db->query("SELECT COUNT(*) FROM members")->fetchColumn();
        $active_members = (int)$db->query("SELECT COUNT(*) FROM members WHERE membership_status = 'active'")->fetchColumn();
        $pending_members = (int)$db->query("SELECT COUNT(*) FROM members WHERE membership_status = 'pending'")->fetchColumn();
        $suspended_members = (int)$db->query("SELECT COUNT(*) FROM members WHERE membership_status = 'suspended'")->fetchColumn();
        
        $new_this_month = (int)$db->query("SELECT COUNT(*) FROM members WHERE MONTH(member_since) = MONTH(CURRENT_DATE()) AND YEAR(member_since) = YEAR(CURRENT_DATE())")->fetchColumn();
        $active_notices = (int)$db->query("SELECT COUNT(*) FROM notices WHERE status = 'published'")->fetchColumn();
        $pending_notices = (int)$db->query("SELECT COUNT(*) FROM notices WHERE status = 'pending_approval'")->fetchColumn();
        $scheduled_notices = (int)$db->query("SELECT COUNT(*) FROM notices WHERE status = 'scheduled'")->fetchColumn();
        $important_active_notices = (int)$db->query("SELECT COUNT(*) FROM notices WHERE status = 'published' AND priority IN ('important', 'urgent')")->fetchColumn();
        $pending_id_cards = (int)$db->query("SELECT COUNT(*) FROM id_card_applications WHERE status IN ('submitted', 'under_review')")->fetchColumn();
        $issued_id_cards = (int)$db->query("SELECT COUNT(*) FROM id_cards WHERE status = 'issued'")->fetchColumn();
        
        $active_wakalatnamas = (int)$db->query("SELECT COUNT(*) FROM wakalatnamas WHERE status = 'active'")->fetchColumn();
        $waka_downloads_today = (int)$db->query("SELECT COUNT(*) FROM wakalatnama_downloads WHERE DATE(downloaded_at) = CURRENT_DATE() AND download_status = 'success'")->fetchColumn();
        $waka_downloads_month = (int)$db->query("SELECT COUNT(*) FROM wakalatnama_downloads WHERE MONTH(downloaded_at) = MONTH(CURRENT_DATE()) AND YEAR(downloaded_at) = YEAR(CURRENT_DATE()) AND download_status = 'success'")->fetchColumn();
        $waka_pending_approvals = (int)$db->query("SELECT COUNT(*) FROM wakalatnamas WHERE status = 'pending_approval'")->fetchColumn();
        
        // Fetch Fund stats
        $fund_active_fy = $db->query("SELECT id FROM financial_years WHERE status = 'active' LIMIT 1")->fetch();
        $fund_summary = [
            'opening_balance' => 0.00,
            'total_income' => 0.00,
            'total_expense' => 0.00,
            'current_balance' => 0.00
        ];
        $fund_pending_count = 0;
        if ($fund_active_fy && function_exists('getAssociationFundSummary')) {
            $fund_summary = getAssociationFundSummary($fund_active_fy['id'], $db);
        } else {
            $tot_inc = (float)$db->query("SELECT SUM(amount) FROM association_fund_transactions WHERE transaction_type = 'income' AND status = 'approved'")->fetchColumn() ?: 0.00;
            $tot_exp = (float)$db->query("SELECT SUM(amount) FROM association_fund_transactions WHERE transaction_type = 'expense' AND status = 'approved'")->fetchColumn() ?: 0.00;
            $fund_summary['total_income'] = $tot_inc;
            $fund_summary['total_expense'] = $tot_exp;
            $fund_summary['current_balance'] = $tot_inc - $tot_exp;
        }
        $fund_pending_count = (int)$db->query("SELECT COUNT(*) FROM association_fund_transactions WHERE status = 'pending_approval'")->fetchColumn();

        // Fetch Bar Fee metrics (Phase 8)
        $bar_fee_collected_month = (float)$db->query("SELECT SUM(amount) FROM bar_fee_payments WHERE MONTH(payment_date) = MONTH(CURRENT_DATE()) AND YEAR(payment_date) = YEAR(CURRENT_DATE()) AND status = 'confirmed'")->fetchColumn() ?: 0.00;
        $bar_fee_outstanding = (float)$db->query("SELECT SUM(outstanding_amount) FROM member_fee_dues WHERE status != 'cancelled'")->fetchColumn() ?: 0.00;
        $bar_fee_overdue_count = (int)$db->query("SELECT COUNT(DISTINCT member_id) FROM member_fee_dues WHERE status = 'overdue'")->fetchColumn() ?: 0;
        $bar_fee_payments_today = (int)$db->query("SELECT COUNT(*) FROM bar_fee_payments WHERE DATE(payment_date) = CURRENT_DATE() AND status = 'confirmed'")->fetchColumn() ?: 0;

        // Fetch Chamber / Room Rent metrics (Phase 9)
        $chamber_total = (int)$db->query("SELECT COUNT(*) FROM chambers")->fetchColumn() ?: 0;
        $chamber_available = (int)$db->query("SELECT COUNT(*) FROM chambers WHERE status = 'available'")->fetchColumn() ?: 0;
        $chamber_occupied = (int)$db->query("SELECT COUNT(*) FROM chambers WHERE status IN ('occupied', 'partially_occupied')")->fetchColumn() ?: 0;
        $chamber_pending_apps = (int)$db->query("SELECT COUNT(*) FROM chamber_applications WHERE status = 'submitted'")->fetchColumn() ?: 0;
        $chamber_outstanding = (float)$db->query("SELECT SUM(outstanding_amount) FROM chamber_rent_dues WHERE status NOT IN ('paid', 'waived', 'cancelled')")->fetchColumn() ?: 0.00;
        $chamber_collected_month = (float)$db->query("SELECT SUM(amount) FROM chamber_rent_payments WHERE MONTH(payment_date) = MONTH(CURRENT_DATE()) AND YEAR(payment_date) = YEAR(CURRENT_DATE()) AND status = 'confirmed'")->fetchColumn() ?: 0.00;

        // Fetch Election metrics (Phase 10)
        $election_active = $db->query("SELECT * FROM elections WHERE status NOT IN ('completed', 'archived', 'cancelled') ORDER BY id DESC LIMIT 1")->fetch();
        $election_voters_count = 0;
        $election_candidates_count = 0;
        if ($election_active) {
            $election_voters_count = (int)$db->query("SELECT COUNT(*) FROM election_voters WHERE election_id = {$election_active['id']} AND eligibility_status = 'eligible'")->fetchColumn() ?: 0;
            $election_candidates_count = (int)$db->query("SELECT COUNT(*) FROM election_candidates WHERE election_id = {$election_active['id']} AND status = 'final_candidate'")->fetchColumn() ?: 0;
        }

        // Fetch Office Bearers metrics (Phase 11)
        $term_active = $db->query("SELECT * FROM office_bearer_terms WHERE status = 'active' LIMIT 1")->fetch();
        $active_president_name = '-';
        $active_mahasachiv_name = '-';
        $active_bearers_count = 0;
        if ($term_active) {
            $active_bearers_count = (int)$db->query("SELECT COUNT(*) FROM office_bearers WHERE term_id = {$term_active['id']} AND status = 'active'")->fetchColumn() ?: 0;
            
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

        // Fetch 5 most recent members with full details
        $recent_stmt = $db->query("
            SELECT id, full_name, membership_no, enrollment_no, membership_category, membership_status, member_since, photo 
            FROM members 
            ORDER BY id DESC LIMIT 5
        ");
        $recent_members = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch Telemetry Data
        $total_db_tables = (int)$db->query("SHOW TABLES")->rowCount();
        $mysql_version = $db->getAttribute(PDO::ATTR_SERVER_VERSION);

        // Fetch 6-Month Income/Expense for Chart.js
        $chart_tx = $db->query("
            SELECT DATE_FORMAT(transaction_date, '%Y-%m') as ym, transaction_type, SUM(amount) as total 
            FROM association_fund_transactions 
            WHERE status = 'approved' AND transaction_date >= DATE_SUB(CURRENT_DATE(), INTERVAL 6 MONTH)
            GROUP BY ym, transaction_type
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($chart_tx as $ctx) {
            $ym = $ctx['ym'];
            if (isset($chart_months[$ym])) {
                if ($ctx['transaction_type'] === 'income') {
                    $chart_income[$ym] = (float)$ctx['total'];
                } else {
                    $chart_expense[$ym] = (float)$ctx['total'];
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Failed to load admin dashboard dynamic stats: " . $e->getMessage());
    }
}

// Compute total pending actions across the portal
$total_pending_actions = $pending_members + $pending_id_cards + $pending_notices + $waka_pending_approvals + $fund_pending_count + $chamber_pending_apps;
?>

<!-- Load Chart.js CDN for Interactive Visual Analytics -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<!-- ========================================================= -->
<!-- 1. EXECUTIVE HEADER & REAL-TIME STATUS BAR                -->
<!-- ========================================================= -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-2 border-bottom font-hindi">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge bg-gold-custom text-navy-custom fw-bold px-2 py-0.5" style="font-size: 0.72rem;">
                <i class="fa-solid fa-shield-halved me-1"></i>एडमिन ज़ोन (Secure)
            </span>
            <span class="badge bg-white text-navy-custom border px-2 py-0.5" style="font-size: 0.72rem;">
                <i class="fa-regular fa-calendar-check text-gold-dark me-1"></i><?php echo date('d M Y'); ?>
            </span>
        </div>
        <h4 class="text-navy-custom fw-bold mb-0">प्रशासक नियंत्रण पटल (Admin Executive Cockpit)</h4>
    </div>
    <div class="d-flex align-items-center gap-2 mt-2 mt-sm-0">
        <a href="<?php echo SITE_URL; ?>/admin/settings/backup.php" class="btn btn-sm btn-outline-navy fw-semibold px-3 py-1.5 shadow-xs">
            <i class="fa-solid fa-database text-gold-dark me-1"></i>डेटाबेस बैकअप
        </a>
        <a href="<?php echo SITE_URL; ?>/index.php" target="_blank" class="btn btn-sm btn-navy fw-semibold px-3 py-1.5 shadow-xs">
            <i class="fa-solid fa-arrow-up-right-from-square text-gold-custom me-1"></i>मुख्य पोर्टल देखें
        </a>
    </div>
</div>

<!-- ========================================================= -->
<!-- 2. QUICK ACTION HUB (EXECUTIVE COMMAND CENTER)            -->
<!-- ========================================================= -->
<div class="mb-4">
    <div class="d-flex flex-wrap align-items-center gap-2 font-hindi">
        <a href="<?php echo SITE_URL; ?>/admin/members/create.php" class="admin-action-btn btn-primary-action shadow-xs">
            <i class="fa-solid fa-user-plus text-gold-custom"></i>
            <span>नया सदस्य जोड़ें</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/notices/create.php" class="admin-action-btn shadow-xs">
            <i class="fa-solid fa-bullhorn text-danger"></i>
            <span>नवीन नोटिस जारी करें</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/bar-fee/payments/create.php" class="admin-action-btn shadow-xs">
            <i class="fa-solid fa-receipt text-success"></i>
            <span>शुल्क संग्रह दर्ज करें</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/wakalatnama/index.php" class="admin-action-btn shadow-xs">
            <i class="fa-solid fa-file-invoice text-primary"></i>
            <span>वकालतनामा आवंटन</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/rooms/index.php" class="admin-action-btn shadow-xs">
            <i class="fa-solid fa-door-open text-warning"></i>
            <span>कक्ष / चैंबर आवंटन</span>
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/association-fund/income/create.php" class="admin-action-btn shadow-xs">
            <i class="fa-solid fa-circle-dollar-to-slot text-gold-dark"></i>
            <span>कोष आय / दान दर्ज करें</span>
        </a>
    </div>
</div>

<!-- ========================================================= -->
<!-- 3. TOP 4 KPI CARDS (HIGH-CONTRAST + FIXED ICONS + LINKS)  -->
<!-- ========================================================= -->
<div class="row g-3 mb-4 font-hindi">
    <!-- Total Members -->
    <div class="col-xl-3 col-md-6 col-12">
        <a href="<?php echo SITE_URL; ?>/admin/members/index.php" class="admin-kpi-card" style="background: linear-gradient(135deg, #0B2545 0%, #133B6B 100%); color: #ffffff;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-white-50 fw-semibold small d-block mb-1">कुल सदस्य (Total Members)</span>
                    <h2 class="fw-bold mb-0 text-white"><?php echo $total_members; ?></h2>
                    <span class="badge bg-white bg-opacity-15 text-gold-custom mt-2" style="font-size: 0.68rem; font-weight: 600;">
                        <i class="fa-solid fa-certificate me-1"></i>सत्र <?php echo date('Y'); ?>
                    </span>
                </div>
                <div class="admin-kpi-icon-box">
                    <i class="fa-solid fa-users text-gold-custom"></i>
                </div>
            </div>
        </a>
    </div>

    <!-- Active Members -->
    <div class="col-xl-3 col-md-6 col-12">
        <a href="<?php echo SITE_URL; ?>/admin/members/index.php?status=active" class="admin-kpi-card" style="background: linear-gradient(135deg, #15803D 0%, #16A34A 100%); color: #ffffff;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-white-50 fw-semibold small d-block mb-1">सक्रिय सदस्य (Active Advocates)</span>
                    <h2 class="fw-bold mb-0 text-white"><?php echo $active_members; ?></h2>
                    <span class="badge bg-white bg-opacity-20 text-white mt-2" style="font-size: 0.68rem; font-weight: 600;">
                        <i class="fa-solid fa-check me-1"></i><?php echo $total_members > 0 ? round(($active_members / $total_members) * 100) : 0; ?>% कुल सदस्यता
                    </span>
                </div>
                <div class="admin-kpi-icon-box">
                    <i class="fa-solid fa-circle-check text-white"></i>
                </div>
            </div>
        </a>
    </div>

    <!-- Pending Members -->
    <div class="col-xl-3 col-md-6 col-12">
        <a href="<?php echo SITE_URL; ?>/admin/members/index.php?status=pending" class="admin-kpi-card" style="background: linear-gradient(135deg, #D97706 0%, #F59E0B 100%); color: #ffffff;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-white-50 fw-semibold small d-block mb-1">लंबित सदस्य (Pending Approval)</span>
                    <h2 class="fw-bold mb-0 text-white"><?php echo $pending_members; ?></h2>
                    <span class="badge bg-white bg-opacity-20 text-white mt-2" style="font-size: 0.68rem; font-weight: 600;">
                        <i class="fa-solid fa-clock-rotate-left me-1"></i>समीक्षा प्रतीक्षित
                    </span>
                </div>
                <div class="admin-kpi-icon-box">
                    <i class="fa-solid fa-clock-rotate-left text-white"></i>
                </div>
            </div>
        </a>
    </div>

    <!-- Suspended Members -->
    <div class="col-xl-3 col-md-6 col-12">
        <a href="<?php echo SITE_URL; ?>/admin/members/index.php?status=suspended" class="admin-kpi-card" style="background: linear-gradient(135deg, #B91C1C 0%, #DC2626 100%); color: #ffffff;">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-white-50 fw-semibold small d-block mb-1">निलंबित (Suspended / Overdue)</span>
                    <h2 class="fw-bold mb-0 text-white"><?php echo $suspended_members; ?></h2>
                    <span class="badge bg-white bg-opacity-20 text-white mt-2" style="font-size: 0.68rem; font-weight: 600;">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i>शुल्क / अनुशासन रोक
                    </span>
                </div>
                <div class="admin-kpi-icon-box">
                    <i class="fa-solid fa-circle-exclamation text-white"></i>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- ========================================================= -->
<!-- 4. PRIORITY ATTENTION HUB (तत्काल ध्यानार्थ)               -->
<!-- ========================================================= -->
<div class="mb-4 font-hindi">
    <?php if ($total_pending_actions > 0): ?>
        <div class="admin-attention-hub shadow-xs">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="rounded-circle bg-warning text-navy-custom d-flex align-items-center justify-content-center" style="width: 36px; height: 36px; flex-shrink: 0;">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-navy-custom mb-0">तत्काल ध्यानार्थ: <?php echo $total_pending_actions; ?> कार्य समीक्षा व अनुमोदन हेतु लंबित हैं</h6>
                        <small class="text-muted">निम्नलिखित प्रभागों में एडमिन स्वीकृति की आवश्यकता है:</small>
                    </div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <?php if ($pending_members > 0): ?>
                        <a href="<?php echo SITE_URL; ?>/admin/members/index.php?status=pending" class="btn btn-sm btn-light border fw-semibold px-2.5 py-1 text-navy-custom">
                            <i class="fa-solid fa-user-clock text-warning me-1"></i>सदस्यता (<?php echo $pending_members; ?>)
                        </a>
                    <?php endif; ?>
                    <?php if ($pending_id_cards > 0): ?>
                        <a href="<?php echo SITE_URL; ?>/admin/id-cards/index.php" class="btn btn-sm btn-light border fw-semibold px-2.5 py-1 text-navy-custom">
                            <i class="fa-solid fa-id-card text-danger me-1"></i>ID कार्ड्स (<?php echo $pending_id_cards; ?>)
                        </a>
                    <?php endif; ?>
                    <?php if ($pending_notices > 0): ?>
                        <a href="<?php echo SITE_URL; ?>/admin/notices/index.php?status=pending_approval" class="btn btn-sm btn-light border fw-semibold px-2.5 py-1 text-navy-custom">
                            <i class="fa-solid fa-bell text-warning me-1"></i>नोटिस (<?php echo $pending_notices; ?>)
                        </a>
                    <?php endif; ?>
                    <?php if ($fund_pending_count > 0): ?>
                        <a href="<?php echo SITE_URL; ?>/admin/association-fund/transactions.php?status=pending_approval" class="btn btn-sm btn-light border fw-semibold px-2.5 py-1 text-navy-custom">
                            <i class="fa-solid fa-vault text-danger me-1"></i>कोष वाउचर (<?php echo $fund_pending_count; ?>)
                        </a>
                    <?php endif; ?>
                    <?php if ($waka_pending_approvals > 0): ?>
                        <a href="<?php echo SITE_URL; ?>/admin/wakalatnama/index.php" class="btn btn-sm btn-light border fw-semibold px-2.5 py-1 text-navy-custom">
                            <i class="fa-solid fa-file-signature text-primary me-1"></i>वकालतनामा (<?php echo $waka_pending_approvals; ?>)
                        </a>
                    <?php endif; ?>
                    <?php if ($chamber_pending_apps > 0): ?>
                        <a href="<?php echo SITE_URL; ?>/admin/rooms/index.php" class="btn btn-sm btn-light border fw-semibold px-2.5 py-1 text-navy-custom">
                            <i class="fa-solid fa-door-open text-warning me-1"></i>चैंबर आवेदन (<?php echo $chamber_pending_apps; ?>)
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="admin-attention-hub all-clear shadow-xs">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2.5">
                    <i class="fa-solid fa-circle-check text-success fs-5"></i>
                    <div>
                        <strong class="text-success d-block">सभी प्रशासनिक प्रभाग अद्यतन हैं (All Systems Clear)</strong>
                        <small class="text-muted">वर्तमान में कोई भी सदस्यता, आईडी कार्ड या कोष अनुमोदन अनुरोध लंबित नहीं है।</small>
                    </div>
                </div>
                <span class="badge bg-success bg-opacity-10 text-success fw-bold px-3 py-1.5">100% सुचारू</span>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ========================================================= -->
<!-- 5. INTERACTIVE VISUAL ANALYTICS (CHART.JS)                -->
<!-- ========================================================= -->
<div class="row g-4 mb-4 font-hindi">
    <!-- Chart 1: Monthly Cash Flow Trend -->
    <div class="col-lg-8 col-12">
        <div class="admin-hub-card">
            <div class="admin-hub-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-chart-column text-gold-dark fs-6"></i>
                    <h6 class="fw-bold text-navy-custom mb-0">वित्तीय मासिक आय-व्यय प्रवाह (Monthly Cash Flow Trend)</h6>
                </div>
                <span class="badge bg-light text-navy-custom border px-2.5 py-1 small">गत 6 माह</span>
            </div>
            <div class="p-3">
                <div style="height: 240px; position: relative;">
                    <canvas id="cashflowChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart 2: Membership Distribution Breakdown -->
    <div class="col-lg-4 col-12">
        <div class="admin-hub-card">
            <div class="admin-hub-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-chart-pie text-gold-dark fs-6"></i>
                    <h6 class="fw-bold text-navy-custom mb-0">सदस्यता संवर्ग विभाजन</h6>
                </div>
                <span class="badge bg-light text-navy-custom border px-2.5 py-1 small">कुल <?php echo $total_members; ?></span>
            </div>
            <div class="p-3">
                <div style="height: 200px; position: relative;">
                    <canvas id="membershipChart"></canvas>
                </div>
                <div class="d-flex justify-content-center gap-3 mt-2 small text-muted font-hindi">
                    <span><i class="fa-solid fa-circle text-success me-1"></i>सक्रिय: <?php echo $active_members; ?></span>
                    <span><i class="fa-solid fa-circle text-warning me-1"></i>लंबित: <?php echo $pending_members; ?></span>
                    <span><i class="fa-solid fa-circle text-danger me-1"></i>निलंबित: <?php echo $suspended_members; ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================= -->
<!-- 6. THREE CONSOLIDATED DOMAIN HUBS                         -->
<!-- ========================================================= -->
<div class="row g-4 mb-4 font-hindi">
    
    <!-- Hub 1: Financial & Revenue Control -->
    <div class="col-lg-4 col-md-6 col-12">
        <div class="admin-hub-card">
            <div class="admin-hub-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-vault text-gold-dark fs-6"></i>
                    <h6 class="fw-bold text-navy-custom mb-0">कोष एवं राजस्व सारांश</h6>
                </div>
                <a href="<?php echo SITE_URL; ?>/admin/association-fund/index.php" class="btn btn-xs btn-outline-navy py-0.5 px-2 fw-semibold" style="font-size: 0.72rem;">विवरण</a>
            </div>
            <div class="p-3">
                <div class="row g-2">
                    <div class="col-12">
                        <div class="admin-metric-tile">
                            <span class="text-secondary small d-block mb-1">संघीय कोष वर्तमान शेष (Fund Balance)</span>
                            <strong class="fs-5 text-success d-block">₹<?php echo number_format($fund_summary['current_balance'], 2); ?></strong>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="admin-metric-tile">
                            <span class="text-secondary small d-block mb-0.5">बार शुल्क संग्रह (माह)</span>
                            <strong class="text-navy-custom d-block">₹<?php echo number_format($bar_fee_collected_month, 2); ?></strong>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="admin-metric-tile">
                            <span class="text-secondary small d-block mb-0.5">कुल शुल्क बकाया</span>
                            <strong class="text-danger d-block">₹<?php echo number_format($bar_fee_outstanding, 2); ?></strong>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="admin-metric-tile">
                            <span class="text-secondary small d-block mb-0.5">चैंबर किराया संग्रह</span>
                            <strong class="text-navy-custom d-block">₹<?php echo number_format($chamber_collected_month, 2); ?></strong>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="admin-metric-tile">
                            <span class="text-secondary small d-block mb-0.5">रिक्त कक्ष / कुल</span>
                            <strong class="text-navy-custom d-block"><?php echo $chamber_available; ?> / <?php echo $chamber_total; ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hub 2: Legal Services & Document Ops -->
    <div class="col-lg-4 col-md-6 col-12">
        <div class="admin-hub-card">
            <div class="admin-hub-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-scale-balanced text-gold-dark fs-6"></i>
                    <h6 class="fw-bold text-navy-custom mb-0">विधिक सेवाएं व दस्तावेज़</h6>
                </div>
                <a href="<?php echo SITE_URL; ?>/admin/wakalatnama/index.php" class="btn btn-xs btn-outline-navy py-0.5 px-2 fw-semibold" style="font-size: 0.72rem;">विवरण</a>
            </div>
            <div class="p-3">
                <div class="row g-2">
                    <div class="col-6">
                        <div class="admin-metric-tile">
                            <span class="text-secondary small d-block mb-0.5">सक्रिय वकालतनामा</span>
                            <strong class="fs-5 text-navy-custom d-block"><?php echo $active_wakalatnamas; ?></strong>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="admin-metric-tile">
                            <span class="text-secondary small d-block mb-0.5">आज के डाउनलोड्स</span>
                            <strong class="fs-5 text-success d-block"><?php echo $waka_downloads_today; ?></strong>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="admin-metric-tile">
                            <span class="text-secondary small d-block mb-0.5">मासिक डाउनलोड्स</span>
                            <strong class="text-navy-custom d-block"><?php echo $waka_downloads_month; ?></strong>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="admin-metric-tile">
                            <span class="text-secondary small d-block mb-0.5">जारी ID कार्ड्स</span>
                            <strong class="text-navy-custom d-block"><?php echo $issued_id_cards; ?></strong>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="admin-metric-tile d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-secondary small d-block">सक्रिय नोटिस / प्रकाशन</span>
                                <strong class="text-navy-custom"><?php echo $active_notices; ?> प्रकाशित (<?php echo $important_active_notices; ?> मुख्य)</strong>
                            </div>
                            <a href="<?php echo SITE_URL; ?>/admin/notices/index.php" class="btn btn-xs btn-outline-navy">देखें</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Hub 3: Governance, Bearers & Elections -->
    <div class="col-lg-4 col-12">
        <div class="admin-hub-card">
            <div class="admin-hub-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-users-gear text-gold-dark fs-6"></i>
                    <h6 class="fw-bold text-navy-custom mb-0">कार्यकारिणी व चुनाव</h6>
                </div>
                <a href="<?php echo SITE_URL; ?>/admin/office-bearers/index.php" class="btn btn-xs btn-outline-navy py-0.5 px-2 fw-semibold" style="font-size: 0.72rem;">विवरण</a>
            </div>
            <div class="p-3">
                <div class="row g-2">
                    <div class="col-12">
                        <div class="admin-metric-tile">
                            <span class="text-secondary small d-block mb-1">सक्रिय सत्र (Term)</span>
                            <strong class="text-navy-custom d-block text-truncate"><?php echo $term_active ? e($term_active['title']) : 'सत्र 2026'; ?></strong>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="admin-metric-tile">
                            <span class="text-secondary small d-block mb-0.5">अध्यक्ष (President)</span>
                            <strong class="text-navy-custom d-block small text-truncate"><?php echo e($active_president_name); ?></strong>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="admin-metric-tile">
                            <span class="text-secondary small d-block mb-0.5">महासचिव (Secretary)</span>
                            <strong class="text-navy-custom d-block small text-truncate"><?php echo e($active_mahasachiv_name); ?></strong>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="admin-metric-tile d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-secondary small d-block">सक्रिय चुनाव प्रक्रिया</span>
                                <strong class="text-navy-custom"><?php echo $election_active ? e($election_active['election_code']) : 'कोई चुनाव सक्रिय नहीं'; ?></strong>
                            </div>
                            <span class="badge <?php echo $election_active ? 'bg-primary' : 'bg-light text-muted border'; ?> small">
                                <?php echo $election_active ? $election_voters_count . ' मतदाता' : 'निष्क्रिय'; ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================= -->
<!-- 7. TWO-COLUMN WORK CENTER (MEMBERS TABLE + AUDIT LOGS)    -->
<!-- ========================================================= -->
<div class="row g-4 mb-4 font-hindi">
    <!-- Left Column: Recently Registered Members Table -->
    <div class="col-lg-7 col-12">
        <div class="admin-hub-card">
            <div class="admin-hub-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-user-plus text-gold-dark fs-6"></i>
                    <h6 class="fw-bold text-navy-custom mb-0">नवीनतम पंजीकृत अधिवक्ता (Recent Registrations)</h6>
                </div>
                <a href="<?php echo SITE_URL; ?>/admin/members/index.php" class="btn btn-xs btn-outline-navy py-0.5 px-2 fw-semibold" style="font-size: 0.74rem;">
                    सभी सदस्य (<?php echo $total_members; ?>) <i class="fa-solid fa-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size: 0.8rem;">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3 py-2.5">अधिवक्ता विवरण</th>
                            <th class="py-2.5">सदस्यता संख्या</th>
                            <th class="py-2.5">पंजीकरण तिथि</th>
                            <th class="py-2.5">स्थिति</th>
                            <th class="text-end pe-3 py-2.5">कार्य</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_members)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">कोई सदस्य रिकॉर्ड उपलब्ध नहीं है।</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_members as $rm): ?>
                                <tr>
                                    <td class="ps-3 py-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="rounded-circle bg-light border d-flex align-items-center justify-content-center text-navy-custom fw-bold" style="width: 32px; height: 32px; font-size: 0.75rem; flex-shrink: 0;">
                                                <?php echo mb_substr($rm['full_name'], 0, 1); ?>
                                            </div>
                                            <div>
                                                <strong class="text-navy-custom d-block"><?php echo e($rm['full_name']); ?></strong>
                                                <small class="text-muted" style="font-size: 0.7rem;"><?php echo e($rm['enrollment_no']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-2">
                                        <span class="badge bg-light text-navy-custom border font-monospace">
                                            <?php echo e($rm['membership_no']); ?>
                                        </span>
                                    </td>
                                    <td class="py-2 text-muted english-text">
                                        <?php echo date('d M Y', strtotime($rm['member_since'])); ?>
                                    </td>
                                    <td class="py-2">
                                        <?php if ($rm['membership_status'] === 'active'): ?>
                                            <span class="badge bg-success bg-opacity-10 text-success fw-semibold px-2 py-0.5">सक्रिय</span>
                                        <?php elseif ($rm['membership_status'] === 'pending'): ?>
                                            <span class="badge bg-warning bg-opacity-15 text-warning-dark fw-semibold px-2 py-0.5">लंबित</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger bg-opacity-10 text-danger fw-semibold px-2 py-0.5"><?php echo e($rm['membership_status']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-3 py-2">
                                        <a href="<?php echo SITE_URL; ?>/admin/members/view.php?id=<?php echo $rm['id']; ?>" class="btn btn-xs btn-outline-navy py-0.5 px-2" title="प्रोफाइल देखें">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Right Column: Live Audit Trail & System Telemetry -->
    <div class="col-lg-5 col-12">
        <!-- Live Audit Trail Card -->
        <div class="admin-hub-card mb-4">
            <div class="admin-hub-header">
                <div class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-user-shield text-gold-dark fs-6"></i>
                    <h6 class="fw-bold text-navy-custom mb-0">ऑडिट गतिविधि (Live Audit Trail)</h6>
                </div>
                <a href="<?php echo SITE_URL; ?>/admin/audit-logs/index.php" class="btn btn-xs btn-outline-navy py-0.5 px-2 fw-semibold" style="font-size: 0.74rem;">
                    सभी लॉग्स <i class="fa-solid fa-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.75rem;">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3 py-2">समय</th>
                                <th class="py-2">यूज़र</th>
                                <th class="py-2">मॉड्यूल</th>
                                <th class="py-2 pe-3">विवरण</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_logs)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-3 text-muted">कोई ऑडिट प्रविष्टि उपलब्ध नहीं है।</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_logs as $rl): ?>
                                    <tr>
                                        <td class="ps-3 py-2 english-text text-muted"><?php echo date('H:i A', strtotime($rl['created_at'])); ?></td>
                                        <td class="py-2"><strong><?php echo e($rl['username']); ?></strong></td>
                                        <td class="py-2"><span class="badge bg-light text-navy-custom border text-uppercase" style="font-size: 0.65rem;"><?php echo e($rl['module']); ?></span></td>
                                        <td class="py-2 pe-3 text-dark text-truncate" style="max-width: 140px;"><?php echo e($rl['action']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- System Telemetry & Quick Backup Card -->
        <div class="admin-hub-card mb-4">
            <div class="admin-hub-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-2 d-flex align-items-center justify-content-center bg-navy-custom bg-opacity-10 text-navy-custom" style="width: 34px; height: 34px;">
                        <i class="fa-solid fa-server text-gold-dark fs-6"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-navy-custom mb-0 font-hindi">सिस्टम स्थिति व सुरक्षा नियंत्रण</h6>
                        <small class="text-muted font-hindi" style="font-size: 0.72rem;">सर्वर स्वास्थ्य एवं बैकअप प्रबंधन</small>
                    </div>
                </div>
                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 fw-semibold px-2.5 py-1 rounded-pill d-flex align-items-center gap-1.5" style="font-size: 0.72rem;">
                    <span class="status-indicator-pulse"></span> ऑनलाइन
                </span>
            </div>
            <div class="p-3">
                <div class="d-flex flex-column gap-2 mb-3">
                    <div class="telemetry-item">
                        <span class="telemetry-label"><i class="fa-brands fa-php text-primary"></i>PHP रनटाइम:</span>
                        <strong class="english-text text-dark"><?php echo PHP_VERSION; ?></strong>
                    </div>
                    <div class="telemetry-item">
                        <span class="telemetry-label"><i class="fa-solid fa-database text-warning"></i>डेटाबेस इंजन:</span>
                        <strong class="english-text text-dark"><?php echo strtok($mysql_version, '-'); ?> <span class="text-muted font-hindi fw-normal" style="font-size: 0.75rem;">(<?php echo $total_db_tables; ?> टेबल्स)</span></strong>
                    </div>
                    <div class="telemetry-item">
                        <span class="telemetry-label"><i class="fa-solid fa-folder-tree text-info"></i>स्टोरेज डायरेक्टरी:</span>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 fw-semibold">सक्रिय व सुरक्षित</span>
                    </div>
                    <div class="telemetry-item">
                        <span class="telemetry-label"><i class="fa-solid fa-shield-halved text-success"></i>सुरक्षा प्रोटोकॉल:</span>
                        <span class="badge bg-navy-custom bg-opacity-10 text-navy-custom border border-navy-custom border-opacity-20 px-2 py-1 fw-semibold">256-Bit SSL / CSRF</span>
                    </div>
                </div>
                
                <div class="d-grid">
                    <button type="button" class="btn btn-navy font-hindi fw-semibold py-2 px-3 d-flex align-items-center justify-content-center gap-2 shadow-xs rounded-3" data-bs-toggle="modal" data-bs-target="#quickBackupModal">
                        <i class="fa-solid fa-cloud-arrow-down text-gold-custom fs-6"></i>
                        <span>त्वरित डेटाबेस बैकअप डाउनलोड करें</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Database Backup Modal -->
<div class="modal fade" id="quickBackupModal" tabindex="-1" aria-labelledby="quickBackupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 14px; overflow: hidden;">
            <div class="modal-header bg-navy-custom text-white py-3 px-4">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="rounded-circle bg-gold-custom text-navy-custom d-flex align-items-center justify-content-center" style="width: 34px; height: 34px;">
                        <i class="fa-solid fa-database"></i>
                    </div>
                    <div>
                        <h6 class="modal-title fw-bold mb-0 font-hindi" id="quickBackupModalLabel">त्वरित डेटाबेस बैकअप डाउनलोड</h6>
                        <small class="text-white-50 font-hindi" style="font-size: 0.72rem;">सिस्टम सुरक्षा सत्यापन एवं संपूर्ण SQL एक्सपोर्ट</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="<?php echo SITE_URL; ?>/admin/settings/backup.php">
                <?php insertCSRF(); ?>
                <input type="hidden" name="action" value="download_sql">
                
                <div class="modal-body p-4 font-hindi">
                    <div class="alert alert-light border d-flex gap-3 align-items-center mb-3 py-2.5 px-3 rounded-3">
                        <i class="fa-solid fa-shield-halved text-success fs-3"></i>
                        <div class="small">
                            <span class="fw-bold text-navy-custom">सुरक्षित डेटाबेस एक्सपोर्ट:</span>
                            <span class="text-muted d-block" style="font-size: 0.78rem;">यह प्रक्रिया वर्तमान डेटाबेस के सभी <strong><?php echo $total_db_tables; ?> टेबल्स</strong> का संपूर्ण बैकअप (.SQL) तैयार करेगी।</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-navy-custom">व्यवस्थापक पासवर्ड (Admin Password) *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light text-muted"><i class="fa-solid fa-lock"></i></span>
                            <input type="password" name="confirm_password" class="form-control" required placeholder="सत्यापन हेतु अपना एडमिन पासवर्ड दर्ज करें...">
                        </div>
                        <small class="text-muted d-block mt-1" style="font-size: 0.72rem;">अनधिकृत डाउनलोड से सुरक्षा के लिए पासवर्ड सत्यापन अनिवार्य है।</small>
                    </div>
                </div>

                <div class="modal-footer bg-light px-4 py-2.5 d-flex justify-content-between align-items-center">
                    <a href="<?php echo SITE_URL; ?>/admin/settings/backup.php" class="small text-decoration-none text-navy-custom fw-semibold">
                        <i class="fa-solid fa-sliders me-1 text-gold-dark"></i>बैकअप सेटिंग्स देखें
                    </a>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-secondary px-3" data-bs-dismiss="modal">रद्द करें</button>
                        <button type="submit" class="btn btn-sm btn-navy px-3 fw-semibold">
                            <i class="fa-solid fa-download me-1.5 text-gold-custom"></i>बैकअप डाउनलोड करें
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================================= -->
<!-- 8. CHART.JS INITIALIZATION SCRIPT                         -->
<!-- ========================================================= -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Cashflow Trend Chart
    const ctxCashflow = document.getElementById('cashflowChart');
    if (ctxCashflow) {
        new Chart(ctxCashflow, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_values($chart_months)); ?>,
                datasets: [
                    {
                        label: 'प्राप्त आय (Income)',
                        data: <?php echo json_encode(array_values($chart_income)); ?>,
                        backgroundColor: 'rgba(22, 163, 74, 0.85)',
                        borderColor: '#16a34a',
                        borderWidth: 1,
                        borderRadius: 6
                    },
                    {
                        label: 'स्वीकृत व्यय (Expense)',
                        data: <?php echo json_encode(array_values($chart_expense)); ?>,
                        backgroundColor: 'rgba(220, 38, 38, 0.85)',
                        borderColor: '#dc2626',
                        borderWidth: 1,
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { font: { family: "'Noto Sans Devanagari', 'Inter', sans-serif" } }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: {
                            callback: function(value) { return '₹' + value.toLocaleString('en-IN'); }
                        }
                    },
                    x: {
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // 2. Membership Distribution Doughnut Chart
    const ctxMembership = document.getElementById('membershipChart');
    if (ctxMembership) {
        new Chart(ctxMembership, {
            type: 'doughnut',
            data: {
                labels: ['सक्रिय (Active)', 'लंबित (Pending)', 'निलंबित (Suspended)'],
                datasets: [{
                    data: [<?php echo $active_members; ?>, <?php echo $pending_members; ?>, <?php echo $suspended_members; ?>],
                    backgroundColor: [
                        '#16a34a',
                        '#f59e0b',
                        '#dc2626'
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }
});
</script>

<?php 
require_once __DIR__ . '/../includes/dashboard/footer.php';
?>
