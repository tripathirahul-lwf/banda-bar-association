<?php
/**
 * Public ID Card Portal Page
 * District Bar Association, Banda
 */

require_once 'config/config.php';
require_once 'includes/functions.php';

// Redirect if already logged in as member
if (isset($_SESSION['auth']) && $_SESSION['auth']['role'] === 'member') {
    redirect(SITE_URL . '/member/id-card.php');
}

$pageTitle = 'डिजिटल ID Card (Digital ID Card)';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="row justify-content-center py-5">
    <div class="col-lg-7 col-md-10 text-center">
        <div class="bg-white p-5 rounded-3 shadow-sm border border-light">
            <div class="logo-placeholder bg-navy-custom text-gold-custom d-inline-flex align-items-center justify-content-center rounded-circle mb-4" style="width: 80px; height: 80px;">
                <i class="bi bi-card-image display-5"></i>
            </div>
            
            <h2 class="text-navy-custom font-hindi fw-bold mb-2">डिजिटल ID Card (Digital ID Card)</h2>
            <p class="text-muted small english-text mb-4">Official digital identifiers and secure verification for registered advocates of Banda.</p>
            
            <hr class="border-gold-custom mx-auto my-3" style="width: 80px; height: 2px; opacity: 1;">
            
            <div class="p-3 bg-light-custom rounded-3 my-4 font-hindi py-3 text-start">
                <h6 class="fw-bold text-navy-custom mb-2"><i class="bi bi-shield-check text-gold-dark me-2"></i> पहचान पत्र सेवाएं (ID Services)</h6>
                <p class="mb-2 text-muted small leading-relaxed">
                    1. <strong>अधिवक्ताओं के लिए:</strong> अपना डिजिटल आईडी कार्ड देखने, खोए कार्ड की रिपोर्ट करने या नवीनीकरण के लिए अपने सदस्य खाते में लॉगिन करें।
                </p>
                <p class="mb-0 text-muted small leading-relaxed">
                    2. <strong>सार्वजनिक सत्यापन:</strong> किसी भी जारी किए गए बार पहचान पत्र की सत्यता जांचने के लिए सार्वजनिक सत्यापन पटल का उपयोग करें।
                </p>
            </div>
            
            <div class="d-flex flex-wrap justify-content-center gap-3 mt-4">
                <a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-navy font-hindi fw-semibold px-4 py-2">
                    <i class="bi bi-box-arrow-in-right text-gold-custom me-2"></i> सदस्य लॉगिन (Advocate Login)
                </a>
                <a href="<?php echo SITE_URL; ?>/verify-id-card.php" class="btn btn-outline-navy font-hindi fw-semibold px-4 py-2">
                    <i class="bi bi-qr-code-scan me-2"></i>कार्ड सत्यापित करें (Verify Card)
                </a>
            </div>
        </div>
    </div>
</div>

<?php 
require_once 'includes/footer.php';
?>
