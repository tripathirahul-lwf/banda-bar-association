<?php
/**
 * 500 Internal Server Error Page
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'सर्वर त्रुटि (500 Server Error)';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="row justify-content-center py-5 font-hindi small text-navy-custom">
    <div class="col-lg-6 col-md-10 text-center">
        <div class="bg-white p-5 rounded-3 shadow-sm border border-light">
            <div class="logo-placeholder bg-danger bg-opacity-10 text-danger d-inline-flex align-items-center justify-content-center rounded-circle mb-4" style="width: 80px; height: 80px;">
                <i class="bi bi-bug-fill display-5"></i>
            </div>
            
            <h2 class="text-navy-custom fw-bold mb-2">सर्वर त्रुटि (500 Server Error)</h2>
            <h5 class="text-danger fw-medium mb-3">सर्वर पर आंतरिक तकनीकी समस्या उत्पन्न हुई है।</h5>
            
            <p class="text-muted small english-text mb-4">The server encountered an internal error or misconfiguration and was unable to complete your request.</p>
            
            <hr class="border-gold-custom mx-auto my-3" style="width: 80px; height: 2px; opacity: 1;">
            
            <p class="small text-muted leading-relaxed mb-4">
                सुरक्षा कारणों से इस सिस्टम एरर के विवरण को गुप्त रखा गया है। संघ के तकनीकी विभाग को सूचित कर दिया गया है।
            </p>
            
            <div class="d-flex justify-content-center gap-3">
                <a href="<?php echo SITE_URL; ?>/index.php" class="btn btn-navy fw-semibold px-4 py-2">
                    <i class="bi bi-house-door me-2"></i>मुख्य पृष्ठ (Home)
                </a>
            </div>
        </div>
    </div>
</div>

<?php 
require_once 'includes/footer.php';
?>
