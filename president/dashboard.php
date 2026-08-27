<?php
/**
 * President Dashboard
 * District Bar Association, Banda
 */

$pageTitle = 'अध्यक्ष डैशबोर्ड (President Dashboard)';
require_once __DIR__ . '/../includes/dashboard/header.php';

// Enforce President Role
requireRole('president');

// Database stats
$db = Database::getConnection();
$total_members = 0;
$latest_notices_count = 0;

if ($db) {
    try {
        $total_members = $db->query("SELECT COUNT(*) FROM members")->fetchColumn();
        $latest_notices_count = $db->query("SELECT COUNT(*) FROM notices WHERE status = 'published'")->fetchColumn();

        // Fetch active President details (Phase 11)
        $active_term = $db->query("SELECT * FROM office_bearer_terms WHERE status = 'active' LIMIT 1")->fetch();
        $president_details = null;
        if ($active_term) {
            $pres_stmt = $db->prepare("
                SELECT ob.*, p.position_name_hindi 
                FROM office_bearers ob 
                JOIN office_bearer_positions p ON p.id = ob.position_id
                WHERE ob.term_id = ? AND ob.status = 'active' AND p.code = 'PRESIDENT' 
                LIMIT 1
            ");
            $pres_stmt->execute([$active_term['id']]);
            $president_details = $pres_stmt->fetch();
        }
        // Fetch all pending approvals across modules
        $pending_notices = $db->query("SELECT COUNT(*) FROM notices WHERE status = 'pending_approval'")->fetchColumn() ?: 0;
        $pending_id_cards = $db->query("SELECT COUNT(*) FROM id_card_applications WHERE status IN ('submitted', 'under_review')")->fetchColumn() ?: 0;
        $pending_funds = $db->query("SELECT COUNT(*) FROM association_fund_transactions WHERE status = 'pending_approval'")->fetchColumn() ?: 0;
        $pending_chambers = $db->query("SELECT COUNT(*) FROM chamber_applications WHERE status = 'submitted'")->fetchColumn() ?: 0;
        
        $pending_approvals = $pending_notices + $pending_id_cards + $pending_funds + $pending_chambers;

        // Fetch recent active notices
        $n_stmt = $db->query("SELECT * FROM notices WHERE status = 'published' ORDER BY id DESC LIMIT 2");
        $recent_notices_list = $n_stmt->fetchAll();

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
        error_log("Failed to fetch president stats: " . $e->getMessage());
    }
} else {
    $total_members = 5;
    $latest_notices_count = 3;
    $pending_approvals = 0;
    $recent_notices_list = [];
    $fund_summary = [
        'opening_balance' => 0.00,
        'total_income' => 0.00,
        'total_expense' => 0.00,
        'current_balance' => 0.00
    ];
    $fund_pending_count = 0;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">अध्यक्ष पटल (Presidential Desk)</h4>
    <a href="<?php echo SITE_URL; ?>/president/approvals/index.php" class="btn btn-xs btn-gold text-navy-custom fw-bold"><i class="bi bi-patch-check-fill me-1"></i>स्वीकृति केंद्र (Approvals Center)</a>
</div>

<!-- Stats widgets -->
<div class="row g-3 mb-4">
    <!-- Total members -->
    <div class="col-md-4">
        <div class="card p-3 border-0 bg-navy-custom text-white h-100">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="font-hindi small">कुल सदस्य (Total Members)</span>
                <i class="bi bi-people-fill fs-4 text-gold-custom"></i>
            </div>
            <h3 class="fw-bold mb-0"><?php echo sanitize($total_members); ?></h3>
        </div>
    </div>
    
    <!-- Pending approvals count -->
    <div class="col-md-4">
        <a href="<?php echo SITE_URL; ?>/president/approvals/index.php" class="card p-3 border-0 bg-warning text-navy-custom h-100 text-decoration-none d-block">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="font-hindi small">लंबित स्वीकृतियां (Pending Approvals)</span>
                <i class="bi bi-clock-history fs-4"></i>
            </div>
            <h3 class="fw-bold mb-0"><?php echo sanitize($pending_approvals); ?></h3>
        </a>
    </div>

    <!-- Active notices -->
    <div class="col-md-4">
        <div class="card p-3 border-0 bg-success text-white h-100">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="font-hindi small">सक्रिय नोटिस (Latest Notices)</span>
                <i class="bi bi-journal-check fs-4"></i>
            </div>
            <h3 class="fw-bold mb-0"><?php echo sanitize($latest_notices_count); ?></h3>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Left Column: Pending approvals list and association overview -->
    <div class="col-lg-7 font-hindi small text-navy-custom">
        <!-- Recent Approval Requests -->
        <div class="border rounded-3 p-3 mb-4 bg-light-custom">
            <h6 class="fw-bold text-navy-custom mb-2 border-bottom pb-2">
                <i class="bi bi-patch-question-fill text-gold-dark me-1"></i>स्वीकृति अनुरोध (Pending Approvals)
            </h6>
            <?php 
            $recent_approvals = [];
            if ($db) {
                try {
                    $stmt = $db->query("SELECT 'Notice' AS type, title AS descr, created_at FROM notices WHERE status = 'pending_approval' LIMIT 2");
                    while ($r = $stmt->fetch()) { $recent_approvals[] = $r; }
                    
                    $stmt = $db->query("SELECT 'ID Card' AS type, m.full_name AS descr, ic.created_at FROM id_card_applications ic JOIN members m ON m.id = ic.member_id WHERE ic.status IN ('submitted', 'under_review') LIMIT 2");
                    while ($r = $stmt->fetch()) { $recent_approvals[] = $r; }

                    $stmt = $db->query("SELECT 'Fund' AS type, remarks AS descr, created_at FROM association_fund_transactions WHERE status = 'pending_approval' LIMIT 2");
                    while ($r = $stmt->fetch()) { $recent_approvals[] = $r; }
                } catch (PDOException $e) {}
            }
            usort($recent_approvals, function($a, $b) { return strcmp($b['created_at'], $a['created_at']); });
            $recent_approvals = array_slice($recent_approvals, 0, 3);

            if (empty($recent_approvals)): 
            ?>
                <div class="text-center py-4 bg-white rounded border border-light text-muted">
                    <i class="bi bi-folder-check display-6 d-block mb-1"></i>
                    <p class="mb-0">कोई विचाराधीन अनुरोध नहीं मिला।</p>
                </div>
            <?php else: ?>
                <div class="bg-white rounded border border-light p-2">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($recent_approvals as $ra): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-1 py-1">
                                <div>
                                    <span class="badge bg-light text-navy-custom border me-2"><?php echo e($ra['type']); ?></span>
                                    <strong class="text-dark-custom"><?php echo e(mb_strimwidth($ra['descr'], 0, 35, '...')); ?></strong>
                                </div>
                                <a href="<?php echo SITE_URL; ?>/president/approvals/index.php" class="btn btn-xs btn-gold py-0.5 px-2">समीक्षा</a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

        <!-- Association Overview -->
        <div class="border rounded-3 p-3 bg-light-custom">
            <h6 class="fw-bold text-navy-custom mb-2 border-bottom pb-2">
                <i class="bi bi-info-circle-fill text-gold-dark me-1"></i>संघीय दृष्टिकोण (Association Overview)
            </h6>
            <p class="text-muted mb-2 mb-0" style="line-height: 1.6; font-size: 0.72rem;">
                अध्यक्ष के रूप में आपके मुख्य दायित्वों में संघ की लोकतांत्रिक मर्यादा की रक्षा करना, वित्तीय अनुशासन सुनिश्चित करना, तथा अधिवक्ताओं की विधिक समस्याओं के त्वरित समाधान हेतु कार्यकारिणी का नेतृत्व करना शामिल है।
            </p>
            <table class="table table-sm table-borderless bg-white rounded border border-light p-2 mb-0" style="font-size:0.75rem;">
                <tbody>
                    <tr>
                        <td class="fw-bold text-navy-custom" style="width: 50%;">संघीय वित्तीय शेष (Fund):</td>
                        <td class="text-success fw-bold">₹<?php echo number_format($fund_summary['current_balance'], 2); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-navy-custom">आगामी संघ चुनाव:</td>
                        <td class="text-secondary fw-semibold">२०२६-२७ (नियत समय)</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Right Column: Quick Actions & Recent Notices -->
    <div class="col-lg-5">
        <div class="border rounded-3 p-3 bg-light-custom font-hindi small">
            <h6 class="fw-bold text-navy-custom mb-3 border-bottom pb-2">
                <i class="bi bi-lightning-fill text-gold-custom me-1"></i>अध्यक्षीय त्वरित कार्य (Quick Actions)
            </h6>
            <div class="d-flex flex-column gap-2 mb-4">
                <a href="<?php echo SITE_URL; ?>/president/approvals/index.php" class="btn btn-navy text-start py-2 d-flex align-items-center gap-2">
                    <i class="bi bi-patch-check-fill text-gold-custom"></i>
                    <span>स्वीकृति केंद्र (Approvals Center)</span>
                    <?php if ($pending_approvals > 0): ?>
                        <span class="badge bg-danger ms-auto"><?php echo $pending_approvals; ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo SITE_URL; ?>/president/notices/index.php" class="btn btn-navy text-start py-2 d-flex align-items-center gap-2">
                    <i class="bi bi-bell-fill"></i>
                    <span>नोटिस समीक्षा (Review Notices)</span>
                </a>
                <a href="<?php echo SITE_URL; ?>/members.php" class="btn btn-navy text-start py-2 font-hindi d-flex align-items-center gap-2">
                    <i class="bi bi-people-fill"></i>
                    <span>सदस्य सूची देखें (View Members)</span>
                </a>
                <a href="<?php echo SITE_URL; ?>/president/association-fund/index.php" class="btn btn-navy text-start py-2 font-hindi d-flex align-items-center gap-2">
                    <i class="bi bi-bank"></i>
                    <span>वित्तीय स्थिति देखें (Financial Summary)</span>
                    <?php if ($fund_pending_count > 0): ?>
                        <span class="badge bg-warning text-dark ms-auto"><?php echo $fund_pending_count; ?> लंबित</span>
                    <?php endif; ?>
                </a>
                <button class="btn btn-navy text-start py-2 font-hindi d-flex align-items-center gap-2" onclick="alert('चुनाव मॉड्यूल अगले चरण में विकसित किया जाएगा।');">
                    <i class="bi bi-check2-square"></i>
                    <span>चुनाव प्रबंधन (Election info)</span>
                </button>
            </div>
            
            <h6 class="fw-bold font-hindi text-navy-custom mb-2 border-bottom pb-2">हालिया नोटिस (Recent Notices)</h6>
            <div class="d-flex flex-column gap-2">
                <?php if (empty($recent_notices_list)): ?>
                    <p class="text-muted small text-center py-2 mb-0">कोई हालिया नोटिस नहीं है।</p>
                <?php else: ?>
                    <?php foreach ($recent_notices_list as $rn): ?>
                        <div class="p-2 bg-white rounded border border-light small font-hindi">
                            <span class="badge <?php echo ($rn['priority'] === 'urgent') ? 'bg-danger text-white' : (($rn['priority'] === 'important') ? 'bg-warning text-dark' : 'bg-secondary text-white'); ?> font-size-xs px-2 py-0.5 mb-1"><?php echo e($rn['priority']); ?></span>
                            <strong class="d-block text-navy-custom text-truncate"><?php echo e($rn['title']); ?></strong>
                            <span class="text-muted font-size-xs"><?php echo date('d-m-Y', strtotime($rn['published_at'] ?: $rn['created_at'])); ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../includes/dashboard/footer.php';
?>
