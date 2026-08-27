<?php
/**
 * Placeholder - Advocate Bar Fee Module
 */

$pageTitle = 'अधिवक्ता संघ शुल्क (Bar Fee)';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="row justify-content-center py-5">
    <div class="col-lg-7 col-md-10 text-center">
        <div class="bg-white p-5 rounded-3 shadow-sm border border-light">
            <div class="logo-placeholder bg-navy-custom text-gold-custom d-inline-flex align-items-center justify-content-center rounded-circle mb-4" style="width: 80px; height: 80px;">
                <i class="bi bi-currency-rupee display-5"></i>
            </div>
            
            <h2 class="text-navy-custom font-hindi fw-bold mb-2">अधिवक्ता संघ शुल्क (Advocate Membership Fee)</h2>
            <p class="text-muted small english-text mb-4">Annual registration fees, membership renewals, and online invoice desk.</p>
            
            <hr class="border-gold-custom mx-auto my-3" style="width: 80px; height: 2px; opacity: 1;">
            
            <div class="alert alert-info border-0 rounded-3 my-4 font-hindi py-3">
                <h5 class="fw-bold mb-2"><i class="bi bi-gear-fill text-primary me-2"></i>विकास के अधीन (Under Development)</h5>
                <p class="mb-0 fs-6">
                    इस मॉड्यूल का विकास अगले चरण में किया जाएगा। (This module will be developed in the next phase.)
                </p>
            </div>
            
            <p class="small text-muted font-hindi leading-relaxed mb-4">
                आगामी चरण में, सदस्य अपने वार्षिक अथवा आजीवन संघ सदस्यता शुल्क का सुरक्षित भुगतान गेटवे के माध्यम से ऑनलाइन भुगतान कर सकेंगे तथा तत्काल डिजिटल रसीद डाउनलोड कर सकेंगे।
            </p>
            
            <div class="d-flex justify-content-center gap-3">
                <a href="<?php echo SITE_URL; ?>/index.php" class="btn btn-navy font-hindi fw-semibold px-4 py-2">
                    <i class="bi bi-house-door me-2"></i>मुख्य पृष्ठ
                </a>
                <a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-outline-navy font-hindi fw-semibold px-4 py-2">
                    सदस्य लॉगिन
                </a>
            </div>
        </div>
    </div>
</div>

<?php 
require_once 'includes/footer.php';
?>
