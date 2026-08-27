<?php
/**
 * 403 Forbidden - Access Denied page
 * District Bar Association, Banda
 */

$pageTitle = 'पहुंच वर्जित (403 Access Denied)';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="row justify-content-center py-5">
    <div class="col-lg-6 col-md-10 text-center">
        <div class="bg-white p-5 rounded-3 shadow-sm border border-light">
            <div class="logo-placeholder bg-danger bg-opacity-10 text-danger d-inline-flex align-items-center justify-content-center rounded-circle mb-4" style="width: 80px; height: 80px;">
                <i class="bi bi-shield-slash-fill display-5"></i>
            </div>
            
            <h2 class="text-navy-custom font-hindi fw-bold mb-2">पहुंच वर्जित (403 Access Denied)</h2>
            <h5 class="text-danger font-hindi fw-medium mb-3">आपको इस पेज को देखने की अनुमति नहीं है।</h5>
            
            <p class="text-muted small english-text mb-4">You do not have the required permissions to access this directory or page.</p>
            
            <hr class="border-gold-custom mx-auto my-3" style="width: 80px; height: 2px; opacity: 1;">
            
            <p class="small text-muted font-hindi leading-relaxed mb-4">
                यदि आपको लगता है कि यह कोई तकनीकी त्रुटि है, तो कृपया बार एसोसिएशन के सचिव या तकनीकी टीम से संपर्क करें। सुरक्षा कारणों से इस गतिविधि का ऑडिट लॉग में मिलान कर लिया गया है।
            </p>
            
            <div class="d-flex justify-content-center gap-3">
                <a href="<?php echo SITE_URL; ?>/index.php" class="btn btn-navy font-hindi fw-semibold px-4 py-2">
                    <i class="bi bi-house-door me-2"></i>मुख्य पृष्ठ (Home)
                </a>
                <a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-outline-navy font-hindi fw-semibold px-4 py-2">
                    लॉगिन पटल (Login Panel)
                </a>
            </div>
        </div>
    </div>
</div>

<?php 
require_once 'includes/footer.php';
?>
