<?php
/**
 * Member Dashboard
 * District Bar Association, Banda
 */

$pageTitle = 'सदस्य डैशबोर्ड (Member Dashboard)';
require_once __DIR__ . '/../includes/dashboard/header.php';

// Enforce Member Role
requireRole('member');

// Fetch linked member record from DB
$user = currentUser();
$member_id = $user['member_id'] ?? 0;
$member = null;
$card = null;

$db = Database::getConnection();
if ($db && $member_id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->execute([$member_id]);
        $member = $stmt->fetch();
        
        $card_stmt = $db->prepare("SELECT * FROM id_cards WHERE member_id = ? ORDER BY id DESC LIMIT 1");
        $card_stmt->execute([$member_id]);
        $card = $card_stmt->fetch();

        // Fetch unread notices count
        $unread_stmt = $db->prepare("
            SELECT COUNT(*) FROM notices n
            WHERE n.status = 'published' AND n.publish_at <= NOW() AND (n.expire_at IS NULL OR n.expire_at >= NOW())
              AND (SELECT COUNT(*) FROM notice_views WHERE notice_id = n.id AND member_id = ?) = 0
        ");
        $unread_stmt->execute([$member_id]);
        $unread_notices_count = $unread_stmt->fetchColumn();

        // Fetch bar fee summary
        $fee_summary = getMemberFeeSummary($member_id, $db);

        // Fetch chamber summary (Phase 9)
        $chamber_summary = getMemberChamberSummary($member_id, $db);

        // Fetch election voter summary (Phase 10)
        $voter_summary = null;
        if ($db) {
            $voter_stmt = $db->prepare("
                SELECT ev.*, e.title, e.election_code 
                FROM election_voters ev
                JOIN elections e ON e.id = ev.election_id
                WHERE ev.member_id = ?
                ORDER BY e.election_year DESC, e.id DESC LIMIT 1
            ");
            $voter_stmt->execute([$member_id]);
            $voter_summary = $voter_stmt->fetch();
        }
    } catch (PDOException $e) {
        error_log("Failed to load member profile on dashboard: " . $e->getMessage());
    }
} else {
    $unread_notices_count = 0;
    $fee_summary = [
        'total_due' => 0.00,
        'total_paid' => 0.00,
        'total_outstanding' => 0.00,
        'overdue_amount' => 0.00
    ];
    $chamber_summary = [
        'has_chamber' => false,
        'chamber_no' => 'कोई नहीं (None)',
        'outstanding_rent' => 0.00
    ];
}

// Fallback mock array if DB connection fails
if (!$member) {
    $member = [
        'id' => 3,
        'full_name' => 'Adv. Amit Mishra',
        'membership_no' => 'DBA-003',
        'enrollment_no' => 'UP/3921/2010',
        'status' => 'Active',
        'chamber_no' => 'Chamber 28',
        'enrollment_year' => 2010,
        'member_since' => '2010-11-05'
    ];
    $fee_summary = [
        'total_due' => 1500.00,
        'total_paid' => 1000.00,
        'total_outstanding' => 500.00,
        'overdue_amount' => 500.00
    ];
    $chamber_summary = [
        'has_chamber' => true,
        'chamber_no' => 'A-12',
        'outstanding_rent' => 1000.00
    ];
    $voter_summary = [
        'voter_no' => 'V-003',
        'eligibility_status' => 'eligible',
        'election_code' => 'DBA-ELECTION-2026'
    ];
}

$display_name = $member['full_name'] ?? ($member['name'] ?? '');
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
    <h4 class="text-navy-custom font-hindi fw-bold mb-0">अधिवक्ता सदस्य सेवा पटल (Member Portal)</h4>
    <span class="badge bg-primary text-white px-3 py-1 font-hindi fw-bold">सदस्य ज़ोन (Secure)</span>
</div>

<!-- Welcome and Profile Card -->
<div class="card-custom bg-white p-4 mb-4 border border-light shadow-sm">
    <div class="row align-items-center g-3">
        <!-- Photo Placeholder -->
        <div class="col-md-2 col-12 text-center">
            <div class="mx-auto rounded-circle overflow-hidden bg-light border border-gold-custom border-2 d-flex align-items-center justify-content-center" style="width: 90px; height: 90px;">
                <i class="bi bi-person-fill text-muted" style="font-size: 4rem;"></i>
            </div>
        </div>
        
        <!-- Member Public Details -->
        <div class="col-md-7 col-12 text-center text-md-start">
            <h5 class="fw-bold text-navy-custom mb-1 english-text"><?php echo sanitize($display_name); ?></h5>
            <p class="text-muted small font-hindi mb-2"><i class="bi bi-patch-check-fill text-gold-custom me-1"></i>पंजीकृत सदस्य | Registered Advocate</p>
            
            <div class="d-flex flex-wrap justify-content-center justify-content-md-start gap-2 font-hindi small">
                <span class="badge bg-light text-navy-custom border py-1.5 px-2.5">सदस्यता संख्या: <?php echo sanitize($member['membership_no']); ?></span>
                <span class="badge bg-light text-navy-custom border py-1.5 px-2.5">नामांकन संख्या: <?php echo sanitize($member['enrollment_no']); ?></span>
                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-1.5 px-2.5">स्थिति: सक्रिय (Active)</span>
            </div>
        </div>
        
        <!-- Registration Date -->
        <div class="col-md-3 col-12 text-center text-md-end border-start-md">
            <span class="d-block text-muted font-hindi small">सदस्यता तिथि (Member Since)</span>
            <strong class="text-navy-custom font-size-xs english-text"><?php echo date('d-m-Y', strtotime($member['member_since'])); ?></strong>
        </div>
    </div>
</div>

<!-- Expiry & Application Alerts -->
<?php if ($card && date('Y-m-d') > $card['valid_until']): ?>
    <div class="alert alert-danger border-0 font-hindi mb-3 shadow-xs p-3">
        <i class="bi bi-x-octagon-fill me-2"></i><strong>आपका ID Card समाप्त हो गया है (Expired)!</strong> कृपया तुरंत नवीनीकरण के लिए <a href="id-card.php" class="fw-bold text-danger decoration-underline">आईडी कार्ड पटल</a> पर जाएं।
    </div>
<?php elseif (!$card): ?>
    <div class="alert alert-warning border-0 font-hindi mb-3 shadow-xs p-3">
        <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>डिजिटल ID Card आवश्यक सूचना:</strong> आपने अभी तक पहचान पत्र के लिए आवेदन नहीं किया है। कृपया तुरंत <a href="id-card.php" class="fw-bold text-navy-custom decoration-underline">आवेदन प्रस्तुत करें</a>।
    </div>
<?php endif; ?>

<!-- Digital Services Cards Grid -->
<h5 class="text-navy-custom font-hindi fw-bold mb-3 border-bottom pb-2"><i class="bi bi-cpu text-gold-custom me-2"></i>डिजिटल सेवाएं (Services)</h5>
<div class="row g-3">
    <!-- Digital ID Card (Available placeholder) -->
    <div class="col-md-6 col-12">
        <a href="<?php echo SITE_URL; ?>/member/id-card.php" class="btn btn-outline-navy w-100 p-3 text-start d-flex justify-content-between align-items-center card-custom border">
            <div>
                <h6 class="fw-bold mb-1 font-hindi text-navy-custom"><i class="bi bi-card-image text-gold-dark me-2"></i>डिजिटल ID Card</h6>
                <span class="small text-muted font-hindi">पहचान पत्र सत्यापन एवं डाउनलोड</span>
            </div>
            <i class="bi bi-arrow-right-short fs-4"></i>
        </a>
    </div>

    <!-- Wakalatnama Card -->
    <div class="col-md-6 col-12">
        <?php 
        $waka_eligible = ($member && ($member['membership_status'] ?? '') === 'active' && ($user['status'] ?? '') === 'active');
        ?>
        <a href="<?php echo SITE_URL; ?>/member/wakalatnama/index.php" class="btn btn-outline-navy w-100 p-3 text-start d-flex justify-content-between align-items-center card-custom border">
            <div>
                <h6 class="fw-bold mb-1 font-hindi text-navy-custom"><i class="bi bi-file-earmark-text text-gold-dark me-2"></i>डिजिटल वकालतनामा (Wakalatnama)</h6>
                <span class="small font-hindi <?php echo $waka_eligible ? 'text-success fw-bold' : 'text-danger fw-bold'; ?>">
                    <?php echo $waka_eligible ? 'डाउनलोड उपलब्ध (Download Available)' : 'डाउनलोड अनुपलब्ध (Not Available)'; ?>
                </span>
            </div>
            <i class="bi bi-arrow-right-short fs-4"></i>
        </a>
    </div>

    <!-- Bar Fee (Available) -->
    <div class="col-md-6 col-12">
        <?php 
        $outstanding = $fee_summary['total_outstanding'];
        $overdue = $fee_summary['overdue_amount'];
        $fee_label = 'Paid (पूर्ण भुगतान)';
        $fee_class = 'text-success fw-bold';
        
        if ($outstanding > 0) {
            if ($overdue > 0) {
                $fee_label = 'Overdue (अवधिपार बकाया): ₹' . number_format($outstanding, 2);
                $fee_class = 'text-danger fw-bold';
            } else {
                $fee_label = 'Due (बकाया शुल्क): ₹' . number_format($outstanding, 2);
                $fee_class = 'text-warning fw-bold';
            }
        }
        ?>
        <a href="<?php echo SITE_URL; ?>/member/fees/index.php" class="btn btn-outline-navy w-100 p-3 text-start d-flex justify-content-between align-items-center card-custom border">
            <div>
                <h6 class="fw-bold mb-1 font-hindi text-navy-custom"><i class="bi bi-currency-rupee text-gold-dark me-2"></i>अधिवक्ता संघ शुल्क (Bar Fee Dues)</h6>
                <span class="small font-hindi <?php echo $fee_class; ?>">
                    <?php echo $fee_label; ?>
                </span>
            </div>
            <i class="bi bi-arrow-right-short fs-4"></i>
        </a>
    </div>

    <!-- Chamber (Live Integration) -->
    <div class="col-md-6 col-12">
        <?php 
        if ($chamber_summary['has_chamber']) {
            $ch_url = SITE_URL . '/member/chamber/index.php';
            $ch_label = 'Chamber: ' . $chamber_summary['chamber_no'] . ' - बकाया: ₹' . number_format($chamber_summary['outstanding_rent'], 2);
            $ch_class = ($chamber_summary['outstanding_rent'] > 0) ? 'text-danger fw-bold' : 'text-success fw-bold';
        } else {
            $ch_url = SITE_URL . '/member/chamber/apply.php';
            $ch_label = 'कोई कक्ष आवंटित नहीं है (Apply Now)';
            $ch_class = 'text-muted';
        }
        ?>
        <a href="<?php echo $ch_url; ?>" class="btn btn-outline-navy w-100 p-3 text-start d-flex justify-content-between align-items-center card-custom border">
            <div>
                <h6 class="fw-bold mb-1 font-hindi text-navy-custom"><i class="bi bi-door-closed text-gold-dark me-2"></i>कक्ष / चैंबर किराया (Chamber Rent)</h6>
                <span class="small font-hindi <?php echo $ch_class; ?>">
                    <?php echo $ch_label; ?>
                </span>
            </div>
            <i class="bi bi-arrow-right-short fs-4"></i>
        </a>
    </div>

    <!-- Notices (Available) -->
    <div class="col-md-6 col-12">
        <a href="<?php echo SITE_URL; ?>/member/notices/index.php" class="btn btn-outline-navy w-100 p-3 text-start d-flex justify-content-between align-items-center card-custom border">
            <div>
                <h6 class="fw-bold mb-1 font-hindi text-navy-custom"><i class="bi bi-bell-fill text-gold-dark me-2"></i>संघीय सूचनाएं (Notices)</h6>
                <span class="small font-hindi <?php echo $unread_notices_count > 0 ? 'text-danger fw-bold' : 'text-muted'; ?>">
                    <?php echo $unread_notices_count > 0 ? "$unread_notices_count नई सूचना उपलब्ध" : "कोई नई सूचना नहीं"; ?>
                </span>
            </div>
            <i class="bi bi-arrow-right-short fs-4"></i>
        </a>
    </div>

    <!-- Election Status (Phase 10) -->
    <div class="col-md-6 col-12">
        <?php 
        if ($voter_summary) {
            $el_url = SITE_URL . '/member/election/index.php';
            $el_label = 'वोटर सं: ' . $voter_summary['voter_no'] . ' - पात्रता: ' . $voter_summary['eligibility_status'];
            $el_class = ($voter_summary['eligibility_status'] === 'eligible') ? 'text-success fw-bold' : 'text-warning fw-bold';
        } else {
            $el_url = SITE_URL . '/member/election/index.php';
            $el_label = 'मतदाता सत्यापन सूची (Verify Details)';
            $el_class = 'text-muted';
        }
        ?>
        <a href="<?php echo $el_url; ?>" class="btn btn-outline-navy w-100 p-3 text-start d-flex justify-content-between align-items-center card-custom border">
            <div>
                <h6 class="fw-bold mb-1 font-hindi text-navy-custom"><i class="bi bi-check2-square text-gold-dark me-2"></i>चुनाव व मतदाता सूची (Election Center)</h6>
                <span class="small font-hindi <?php echo $el_class; ?>">
                    <?php echo $el_label; ?>
                </span>
            </div>
            <i class="bi bi-arrow-right-short fs-4"></i>
        </a>
    </div>

    <!-- Profile Detail (Available) -->
    <div class="col-md-6 col-12">
        <a href="<?php echo SITE_URL; ?>/member-profile.php?id=<?php echo sanitize($member['id']); ?>" class="btn btn-outline-navy w-100 p-3 text-start d-flex justify-content-between align-items-center card-custom border">
            <div>
                <h6 class="fw-bold mb-1 font-hindi text-navy-custom"><i class="bi bi-person-bounding-box text-gold-dark me-2"></i>मेरी प्रोफाइल विवरण (My Profile)</h6>
                <span class="small text-muted font-hindi">सार्वजनिक सूचना डेटा शीट</span>
            </div>
            <i class="bi bi-arrow-right-short fs-4"></i>
        </a>
    </div>

    <!-- Association Fund (Available) -->
    <div class="col-md-6 col-12">
        <a href="<?php echo SITE_URL; ?>/member/association-fund.php" class="btn btn-outline-navy w-100 p-3 text-start d-flex justify-content-between align-items-center card-custom border">
            <div>
                <h6 class="fw-bold mb-1 font-hindi text-navy-custom"><i class="bi bi-bank text-gold-dark me-2"></i>संघीय कोष विवरण (Assoc. Fund)</h6>
                <span class="small text-muted font-hindi">प्रकाशित वित्तीय लेखा-जोखा व रिपोर्ट डाउनलोड</span>
            </div>
            <i class="bi bi-arrow-right-short fs-4"></i>
        </a>
    </div>
</div>

<?php 
require_once __DIR__ . '/../includes/dashboard/footer.php';
?>
