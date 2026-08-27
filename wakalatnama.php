<?php
/**
 * Public Wakalatnama Landing Portal
 * District Bar Association, Banda
 */

require_once 'config/config.php';
require_once 'includes/functions.php';

// Redirect if already logged in as member
if (isset($_SESSION['auth']) && $_SESSION['auth']['role'] === 'member') {
    redirect(SITE_URL . '/member/wakalatnama/index.php');
}

$pageTitle = 'वकालतनामा (Wakalatnama)';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="row justify-content-center py-5">
    <div class="col-lg-7 col-md-10 text-center">
        <div class="bg-white p-5 rounded-3 shadow-sm border border-light">
            <div class="logo-placeholder bg-navy-custom text-gold-custom d-inline-flex align-items-center justify-content-center rounded-circle mb-4" style="width: 80px; height: 80px;">
                <i class="bi bi-file-earmark-text display-5"></i>
            </div>
            
            <h2 class="text-navy-custom font-hindi fw-bold mb-2">वकालतनामा डाउनलोड पटल (Wakalatnama Portal)</h2>
            <p class="text-muted small english-text mb-4">Official Wakalatnama document center for registered advocates.</p>
            
            <hr class="border-gold-custom mx-auto my-3" style="width: 80px; height: 2px; opacity: 1;">
            
            <!-- Warning / Info Alert -->
            <div class="alert alert-warning border-0 rounded-3 my-4 font-hindi py-3 text-start shadow-xs">
                <h5 class="fw-bold mb-2 text-danger-custom"><i class="bi bi-shield-lock-fill me-2"></i>सुरक्षा सूचना (Security Notice)</h5>
                <p class="mb-0 fs-6 leading-relaxed">
                    वकालतनामा डाउनलोड केवल सक्रिय एवं पंजीकृत बार सदस्यों के लिए उपलब्ध है। (Wakalatnama download is available for active and registered Bar members only.)
                </p>
            </div>
            
            <p class="small text-muted font-hindi leading-relaxed mb-4">
                पंजीकृत सदस्य विलेख डाउनलोड करने के लिए अपने अधिकृत क्रेडेंशियल्स के साथ लॉगिन करें। डाउनलोड की सभी गतिविधियां सुरक्षा ऑडिट लॉग के अंतर्गत दर्ज की जाती हैं।
            </p>
            
            <div class="d-flex justify-content-center gap-3">
                <a href="<?php echo SITE_URL; ?>/index.php" class="btn btn-outline-navy font-hindi fw-semibold px-4 py-2">
                    <i class="bi bi-house-door me-2"></i>मुख्य पृष्ठ
                </a>
                <a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-navy font-hindi fw-semibold px-4 py-2">
                    <i class="bi bi-box-arrow-in-right text-gold-custom me-2"></i>सदस्य लॉगिन (Member Login)
                </a>
            </div>
        </div>
    </div>
</div>

<?php 
require_once 'includes/footer.php';
?>
