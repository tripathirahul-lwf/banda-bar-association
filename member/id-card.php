<?php
/**
 * Member Digital ID Card View & Actions
 * District Bar Association, Banda
 */

$pageTitle = 'मेरा डिजिटल ID Card (My ID Card)';
require_once __DIR__ . '/../includes/dashboard/header.php';

// Enforce member role
requireRole('member');

// Include local QR Code library
require_once __DIR__ . '/../libraries/phpqrcode/qrlib.php';

$user = currentUser();
$member_id = $user['member_id'] ?? 0;
$card = null;
$member = null;

$db = Database::getConnection();

if ($db && $member_id > 0) {
    try {
        // Fetch member master info
        $m_stmt = $db->prepare("SELECT * FROM members WHERE id = ?");
        $m_stmt->execute([$member_id]);
        $member = $m_stmt->fetch();
        
        // Fetch current active/issued card
        $c_stmt = $db->prepare("
            SELECT * FROM id_cards 
            WHERE member_id = ? AND status IN ('issued', 'ready', 'generated', 'approved') 
            ORDER BY id DESC LIMIT 1
        ");
        $c_stmt->execute([$member_id]);
        $card = $c_stmt->fetch();
    } catch (PDOException $e) {
        error_log("Failed loading member ID card view: " . $e->getMessage());
    }
}

// Check expiry alerts if card exists
$is_expired = false;
$expiring_soon = false;
if ($card) {
    $current_date = date('Y-m-d');
    if ($current_date > $card['valid_until']) {
        $is_expired = true;
    } else {
        $warning_days = defined('ID_CARD_EXPIRY_WARNING_DAYS') ? ID_CARD_EXPIRY_WARNING_DAYS : 30;
        $expiry_threshold = date('Y-m-d', strtotime("+$warning_days days"));
        if ($card['valid_until'] <= $expiry_threshold) {
            $expiring_soon = true;
        }
    }
    
    // Generate QR Code cached image (PNG if GD is enabled, SVG vector fallback otherwise)
    $has_gd = extension_loaded('gd');
    $qr_filename = 'qr_' . $card['qr_token'] . ($has_gd ? '.png' : '.svg');
    $qr_dir = __DIR__ . '/../uploads/qrcodes/';
    $qr_filepath = $qr_dir . $qr_filename;
    $qr_webpath = SITE_URL . '/uploads/qrcodes/' . $qr_filename;

    if (!is_dir($qr_dir)) {
        mkdir($qr_dir, 0755, true);
    }

    if (!file_exists($qr_filepath)) {
        $verify_url = SITE_URL . '/verify-id-card.php?token=' . $card['qr_token'];
        if ($has_gd) {
            QRcode::png($verify_url, $qr_filepath, QR_ECLEVEL_L, 3, 1);
        } else {
            QRcode::svg($verify_url, $qr_filepath, QR_ECLEVEL_L, 3, 1);
        }
    }
}
?>

<!-- Load ID Card CSS -->
<link rel="stylesheet" href="../assets/css/id-card.css">

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 no-print font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">मेरा डिजिटल पहचान पत्र (My Digital ID)</h4>
    <div class="d-flex gap-2">
        <a href="id-card/history.php" class="btn btn-xs btn-outline-navy fw-semibold"><i class="fa-solid fa-clock-rotate-left me-1"></i>आवेदन इतिहास (History)</a>
        <?php if ($card): ?>
            <button class="btn btn-xs btn-navy fw-semibold" onclick="window.print();"><i class="fa-solid fa-print me-1 text-gold-custom"></i>प्रिंट करें (Print Card)</button>
        <?php endif; ?>
    </div>
</div>

<?php if (!$card): ?>
    <!-- NO CARD EXISTS STATE -->
    <div class="card p-5 text-center border-0 shadow-sm rounded-3 font-hindi no-print">
        <i class="fa-solid fa-id-card display-3 text-muted mb-3 d-block"></i>
        <h4 class="fw-bold text-navy-custom mb-2">आपका डिजिटल ID Card अभी जारी नहीं हुआ है।</h4>
        <p class="text-muted small mb-4">यदि आपने पहले से आवेदन नहीं किया है, तो नीचे दिए बटन पर क्लिक कर नया आईडी कार्ड अनुरोध सबमिट करें।</p>
        
        <div class="d-inline-block">
            <a href="id-card/apply.php?type=new" class="btn btn-navy px-4 py-2 fw-semibold">
                <i class="fa-solid fa-circle-plus text-gold-custom me-2"></i> ID Card के लिए आवेदन करें
            </a>
        </div>
    </div>
<?php else: ?>
    
    <!-- Expiry warning alerts -->
    <?php if ($is_expired): ?>
        <div class="alert alert-danger border-0 font-hindi mb-3 shadow-xs no-print">
            <i class="fa-solid fa-circle-xmark me-2"></i><strong>आपका ID Card समाप्त हो गया है (Expired)!</strong> कृपया तुरंत नवीनीकरण (Renewal) के लिए आवेदन करें।
        </div>
    <?php elseif ($expiring_soon): ?>
        <div class="alert alert-warning border-0 font-hindi mb-3 shadow-xs no-print">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><strong>आपका ID Card शीघ्र समाप्त होने वाला है!</strong> यह तिथि <?php echo date('d-m-Y', strtotime($card['valid_until'])); ?> को समाप्त हो जाएगा।
        </div>
    <?php endif; ?>

    <div class="row g-4 justify-content-center">
        <!-- FRONT SIDE OF CARD -->
        <div class="col-md-6 col-12 d-flex justify-content-center">
            <div class="id-card-wrapper id-card-print-target">
                <div class="id-card-container">
                    <!-- Header -->
                    <div class="id-card-header">
                        <img src="../assets/images/logo.png" alt="Logo" class="id-card-logo" onerror="this.src='../includes/dashboard/logo-placeholder.png'">
                        <div>
                            <h5 class="id-card-title-hi">जिला अधिवक्ता संघ, बांदा</h5>
                            <h6 class="id-card-title-en">DISTRICT BAR ASSOCIATION, BANDA (U.P.)</h6>
                            <span class="id-card-estd">Estd. 1937 | Registered Advocate Credential</span>
                        </div>
                    </div>
                    
                    <!-- Body -->
                    <div class="id-card-body">
                        <!-- Photo left -->
                        <div class="id-card-left">
                            <img src="../uploads/photos/<?php echo e($member['photo']); ?>" alt="Photo" class="id-card-photo" onerror="this.src='../uploads/photos/default_advocate.png'">
                            <span class="id-card-category-badge"><?php echo e($member['membership_category']); ?></span>
                        </div>
                        
                        <!-- Details right -->
                        <div class="id-card-right">
                            <div class="id-card-row">
                                <span class="id-card-label">NAME:</span>
                                <span class="id-card-value d-block english-text"><?php echo e($member['full_name']); ?></span>
                            </div>
                            <div class="id-card-row">
                                <span class="id-card-label">MEMB NO:</span>
                                <span class="id-card-value english-text"><?php echo e($member['membership_no']); ?></span>
                            </div>
                            <div class="id-card-row">
                                <span class="id-card-label">ENROLL NO:</span>
                                <span class="id-card-value english-text"><?php echo e($member['enrollment_no']); ?></span>
                            </div>
                            <div class="id-card-row">
                                <span class="id-card-label">VALIDITY:</span>
                                <span class="id-card-value text-success font-size-xs english-text">
                                    <?php echo date('M Y', strtotime($card['valid_from'])); ?> - <?php echo date('M Y', strtotime($card['valid_until'])); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Footer -->
                    <div class="id-card-footer">
                        <!-- QR Code -->
                        <div class="id-card-qr-box">
                            <img src="<?php echo $qr_webpath; ?>" alt="QR Verification">
                        </div>
                        
                        <!-- Authorized sign -->
                        <div class="id-card-signature-box">
                            <div class="id-card-signature-placeholder">
                                <!-- Place for Secretary signature stamp -->
                            </div>
                            <span>MAHASACHIV SIGN</span>
                        </div>
                    </div>
                </div>
                <div class="text-center mt-2 no-print">
                    <span class="badge bg-light text-navy-custom border">कार्ड अग्रभाग (Front Side View)</span>
                </div>
            </div>
        </div>

        <!-- BACK SIDE OF CARD -->
        <div class="col-md-6 col-12 d-flex justify-content-center">
            <div class="id-card-wrapper id-card-print-target">
                <div class="id-card-container back-side">
                    <!-- Title -->
                    <div class="id-card-back-title font-hindi">सदस्यता नियम व शर्तें (DBA Rules)</div>
                    
                    <!-- Terms -->
                    <div class="id-card-back-terms font-hindi">
                        1. यह कार्ड जिला अधिवक्ता संघ, बांदा की संपत्ति है।<br>
                        2. न्यायालय परिसर एवं आधिकारिक कार्यक्रमों में इसे धारण करना अनिवार्य है।<br>
                        3. कार्ड खो जाने या चोरी होने पर तुरंत बार कार्यालय को सूचित करें।<br>
                        4. इस कार्ड का दुरुपयोग दंडनीय विधिक अनुशासनात्मक कार्रवाई के अधीन है।
                    </div>
                    
                    <!-- Meta -->
                    <div class="id-card-back-meta font-hindi small text-navy-custom border-top pt-2 mb-2">
                        <div class="row">
                            <div class="col-6">
                                <span class="text-muted" style="font-size: 0.6rem;">रक्त समूह (Blood):</span>
                                <strong class="d-block"><?php echo e($member['blood_group'] ?: 'N/A'); ?></strong>
                            </div>
                            <div class="col-6 text-end">
                                <span class="text-muted" style="font-size: 0.6rem;">सदस्य सिंस (Since):</span>
                                <strong class="d-block english-text"><?php echo date('Y', strtotime($member['member_since'])); ?></strong>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Office Address -->
                    <div class="id-card-back-address font-hindi">
                        <strong>कार्यालय:</strong> जिला अधिवक्ता संघ भवन, कलेक्ट्रेट परिसर, बांदा (U.P.) - 210001
                        <span class="d-block english-text" style="font-size: 0.5rem; margin-top:2px;">Verify at: <?php echo SITE_URL; ?>/verify-id-card.php</span>
                    </div>
                </div>
                <div class="text-center mt-2 no-print">
                    <span class="badge bg-light text-navy-custom border">कार्ड पृष्ठभाग (Back Side View)</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick action links below card (NO PRINT) -->
    <div class="row justify-content-center g-3 mt-4 mb-5 no-print font-hindi small text-center">
        <div class="col-md-8 d-flex flex-wrap justify-content-center gap-2">
            <a href="id-card/apply.php?type=renewal&card_id=<?php echo $card['id']; ?>" class="btn btn-navy py-2 px-3 <?php echo (!$is_expired && !$expiring_soon) ? 'disabled' : ''; ?>">
                <i class="fa-solid fa-rotate me-1 text-gold-custom"></i> नवीनीकरण अनुरोध (Renew Card)
            </a>
            <a href="id-card/apply.php?type=duplicate&card_id=<?php echo $card['id']; ?>" class="btn btn-navy py-2 px-3">
                <i class="fa-solid fa-file-circle-plus me-1 text-gold-custom"></i> डुप्लीकेट कार्ड (Duplicate ID)
            </a>
            <a href="id-card/apply.php?type=lost&card_id=<?php echo $card['id']; ?>" class="btn btn-danger py-2 px-3">
                <i class="fa-solid fa-triangle-exclamation me-1"></i> खोया कार्ड रिपोर्ट (Report Lost)
            </a>
        </div>
    </div>
<?php endif; ?>

<?php 
require_once __DIR__ . '/../includes/dashboard/footer.php';
?>
