<?php
/**
 * Public Safe ID Card Verification Portal (Mobile Optimized)
 * District Bar Association, Banda
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$card_number = isset($_POST['card_number']) ? trim($_POST['card_number']) : '';
$membership_no = isset($_POST['membership_no']) ? trim($_POST['membership_no']) : '';

$card = null;
$error_message = '';
$verified = false;

$db = Database::getConnection();

// Rate Limiting Check (Requirement 43)
if (!checkRateLimit('id_verification', 20, 60)) {
    $error_message = 'अत्यधिक सत्यापन अनुरोध। कृपया कुछ समय बाद पुनः प्रयास करें। (Too many verification requests. Please try again later.)';
}

if ($db && empty($error_message)) {
    try {
        if (!empty($token)) {
            // 1. QR Code Token verification lookup
            $stmt = $db->prepare("
                SELECT c.*, m.full_name, m.membership_no, m.enrollment_no, m.membership_status, m.photo 
                FROM id_cards c 
                JOIN members m ON m.id = c.member_id 
                WHERE c.qr_token = ?
            ");
            $stmt->execute([$token]);
            $card = $stmt->fetch();
            
            if (!$card) {
                $error_message = 'अमान्य सत्यापन टोकन (Invalid QR Token).';
            }
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // 2. Manual lookup
            if (!empty($card_number)) {
                $stmt = $db->prepare("
                    SELECT c.*, m.full_name, m.membership_no, m.enrollment_no, m.membership_status, m.photo 
                    FROM id_cards c 
                    JOIN members m ON m.id = c.member_id 
                    WHERE c.card_number = ?
                ");
                $stmt->execute([$card_number]);
                $card = $stmt->fetch();
                if (!$card) $error_message = 'कार्ड नंबर रिकॉर्ड में नहीं मिला।';
            } elseif (!empty($membership_no)) {
                $stmt = $db->prepare("
                    SELECT c.*, m.full_name, m.membership_no, m.enrollment_no, m.membership_status, m.photo 
                    FROM id_cards c 
                    JOIN members m ON m.id = c.member_id 
                    WHERE m.membership_no = ? AND c.status IN ('issued', 'ready')
                    ORDER BY c.id DESC LIMIT 1
                ");
                $stmt->execute([$membership_no]);
                $card = $stmt->fetch();
                if (!$card) $error_message = 'इस सदस्यता संख्या के लिए कोई सक्रिय कार्ड नहीं मिला।';
            } else {
                $error_message = 'कृपया कार्ड नंबर या सदस्यता संख्या दर्ज करें।';
            }
        }

        // Apply business validation rules
        if ($card && empty($error_message)) {
            $is_status_valid = in_array($card['status'], ['issued', 'ready']);
            $is_member_active = ($card['membership_status'] === 'active');
            $current_date = date('Y-m-d');
            $is_date_valid = ($current_date >= $card['valid_from'] && $current_date <= $card['valid_until']);

            if ($is_status_valid && $is_member_active && $is_date_valid) {
                $verified = true;
            } else {
                // Formulate public-safe error reasons without leaking detailed info
                if (!$is_status_valid) {
                    $error_message = 'यह कार्ड निरस्त या निलंबित है (Cancelled/Suspended Card).';
                } elseif (!$is_member_active) {
                    $error_message = 'अधिवक्ता सदस्यता निष्क्रिय है (Member Inactive/Suspended).';
                } else {
                    $error_message = 'यह कार्ड समाप्त हो चुका है (Expired Card).';
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Verification error: " . $e->getMessage());
        $error_message = 'सिस्टम एरर। कृपया पुनः प्रयास करें।';
    }
}
?>
<!DOCTYPE html>
<html lang="hi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>आईडी कार्ड सत्यापन (ID Card Verification) - जिला अधिवक्ता संघ, बांदा</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <style>
        body {
            background-color: #F5F7FB;
            font-family: Arial, Helvetica, sans-serif;
            color: #102A43;
        }
        .navbar-custom {
            background-color: #102A43;
            border-bottom: 3px solid #D9B44A;
        }
        .verified-card {
            border: 2px solid #198754;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(25, 135, 84, 0.15);
        }
        .invalid-card {
            border: 2px solid #dc3545;
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.15);
        }
        .verification-badge {
            font-size: 1.1rem;
            font-weight: bold;
            padding: 8px 16px;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
    </style>
</head>
<body>

    <!-- Header bar -->
    <nav class="navbar navbar-dark navbar-custom py-2 shadow-sm">
        <div class="container justify-content-center text-center">
            <div>
                <span class="navbar-brand mb-0 h5 font-hindi fw-bold text-white d-block">जिला अधिवक्ता संघ, बांदा (स्थापित 1937)</span>
                <small class="text-white text-opacity-75 english-text text-uppercase tracking-wider font-size-xs" style="font-size: 0.7rem;">District Bar Association, Banda</small>
            </div>
        </div>
    </nav>

    <div class="container py-4" style="max-width: 600px;">
        
        <?php if (!empty($token) || $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <!-- Verification result -->
            <?php if ($verified && $card): ?>
                <!-- SUCCESS VALID CARD -->
                <div class="verified-card p-4 text-center mb-4">
                    <div class="mb-3">
                        <span class="verification-badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">
                            <i class="bi bi-patch-check-fill"></i> वैध आईडी कार्ड (VALID CARD)
                        </span>
                    </div>

                    <!-- Photo -->
                    <div class="mx-auto rounded-circle overflow-hidden bg-light border border-success border-2 mb-3 d-flex align-items-center justify-content-center" style="width: 100px; height: 100px;">
                        <?php if (!empty($card['photo']) && $card['photo'] !== 'default_advocate.png'): ?>
                            <img src="uploads/photos/<?php echo e($card['photo']); ?>" alt="Advocate Photo" style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                            <i class="bi bi-person-fill text-muted" style="font-size: 4.5rem;"></i>
                        <?php endif; ?>
                    </div>

                    <h4 class="fw-bold text-navy-custom mb-1 english-text"><?php echo e($card['full_name']); ?></h4>
                    <p class="text-muted small font-hindi mb-3">पंजीकृत सदस्य अधिवक्ता | DBA Banda</p>
                    
                    <hr class="my-3 border-success border-opacity-25">

                    <!-- Safe Metadata Fields -->
                    <div class="row g-2 text-start font-hindi small text-dark-custom">
                        <div class="col-6 border-bottom border-light pb-2">
                            <span class="text-muted d-block" style="font-size: 0.7rem;">सदस्यता संख्या:</span>
                            <strong class="english-text"><?php echo e($card['membership_no']); ?></strong>
                        </div>
                        <div class="col-6 border-bottom border-light pb-2">
                            <span class="text-muted d-block" style="font-size: 0.7rem;">पंजीकरण संख्या (Bar Council):</span>
                            <strong class="english-text"><?php echo e($card['enrollment_no']); ?></strong>
                        </div>
                        <div class="col-6 border-bottom border-light pb-2">
                            <span class="text-muted d-block" style="font-size: 0.7rem;">कार्ड नंबर (Card Number):</span>
                            <strong class="english-text"><?php echo e($card['card_number']); ?></strong>
                        </div>
                        <div class="col-6 border-bottom border-light pb-2">
                            <span class="text-muted d-block" style="font-size: 0.7rem;">वैधता अवधि:</span>
                            <strong class="english-text"><?php echo date('d-m-Y', strtotime($card['valid_from'])); ?> से <?php echo date('d-m-Y', strtotime($card['valid_until'])); ?></strong>
                        </div>
                    </div>

                    <div class="alert alert-success border-0 small mt-4 mb-0 font-hindi">
                        <i class="bi bi-shield-fill-check me-1"></i>
                        यह आईडी कार्ड जिला अधिवक्ता संघ, बांदा के आधिकारिक डेटाबेस रिकॉर्ड से सत्यापित है।
                    </div>
                </div>
            <?php else: ?>
                <!-- INVALID OR EXPIRED CARD -->
                <div class="invalid-card p-4 text-center mb-4">
                    <div class="mb-3">
                        <span class="verification-badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">
                            <i class="bi bi-x-octagon-fill"></i> अमान्य कार्ड (INVALID CARD)
                        </span>
                    </div>

                    <h5 class="fw-bold text-danger font-hindi mb-3">यह ID Card जिला अधिवक्ता संघ, बांदा के रिकॉर्ड में मान्य नहीं है।</h5>
                    
                    <?php if (!empty($error_message)): ?>
                        <div class="p-3 bg-light rounded text-muted font-hindi small mb-3">
                            <strong>कारण:</strong> <?php echo e($error_message); ?>
                        </div>
                    <?php endif; ?>

                    <p class="text-muted small font-hindi mb-0">सुरक्षा नियमों के अनुसार अमान्य, निरस्त या एक्सपायर्ड कार्ड धारकों के विवरण प्रदर्शित नहीं किए जा सकते।</p>
                </div>
            <?php endif; ?>
            
            <div class="text-center font-hindi">
                <a href="verify-id-card.php" class="btn btn-navy btn-sm"><i class="bi bi-search me-1"></i>अन्य कार्ड सत्यापित करें</a>
            </div>
            
        <?php else: ?>
            <!-- Manual verification form -->
            <div class="bg-white p-4 rounded-3 shadow-sm border border-light font-hindi small">
                <h5 class="text-navy-custom fw-bold border-bottom pb-2 mb-3"><i class="bi bi-shield-lock-fill text-gold-custom me-2"></i>मैनुअल सत्यापन (Manual Card Verification)</h5>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger border-0"><?php echo e($error_message); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="verify-id-card.php">
                    <div class="mb-3">
                        <label for="card_number" class="form-label text-secondary fw-semibold">कार्ड नंबर दर्ज करें (ID Card Number)</label>
                        <input type="text" class="form-control english-text" id="card_number" name="card_number" placeholder="e.g. DBA-ID-2026-0001">
                        <div class="form-text text-muted" style="font-size: 0.7rem;">कार्ड के अग्रभाग पर लिखित कोड।</div>
                    </div>
                    
                    <div class="text-center my-3 text-muted">
                        <span>— अथवा / OR —</span>
                    </div>
                    
                    <div class="mb-3">
                        <label for="membership_no" class="form-label text-secondary fw-semibold">सदस्यता संख्या दर्ज करें (Membership No)</label>
                        <input type="text" class="form-control english-text" id="membership_no" name="membership_no" placeholder="e.g. DBA-001">
                        <div class="form-text text-muted" style="font-size: 0.7rem;">अधिवक्ता की बार एसोसिएशन कोड संख्या।</div>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-navy py-2 fw-semibold">
                            <i class="bi bi-search text-gold-custom me-2"></i>सत्यापन जांचें (Verify ID)
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
        
    </div>

</body>
</html>
