<?php
/**
 * 404 Not Found Page
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'पृष्ठ नहीं मिला (404 Not Found)';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="row justify-content-center py-5 font-hindi small text-navy-custom">
    <div class="col-lg-6 col-md-10 text-center">
        <div class="bg-white p-5 rounded-3 shadow-sm border border-light">
            <div class="logo-placeholder bg-warning bg-opacity-10 text-warning d-inline-flex align-items-center justify-content-center rounded-circle mb-4" style="width: 80px; height: 80px;">
                <i class="bi bi-exclamation-triangle-fill display-5"></i>
            </div>
            
            <h2 class="text-navy-custom fw-bold mb-2">पृष्ठ नहीं मिला (404 Not Found)</h2>
            <h5 class="text-warning fw-medium mb-3">खोजा गया पृष्ठ अस्तित्व में नहीं है।</h5>
            
            <p class="text-muted small english-text mb-4">The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.</p>
            
            <hr class="border-gold-custom mx-auto my-3" style="width: 80px; height: 2px; opacity: 1;">
            
            <div class="d-flex justify-content-center gap-3">
                <a href="<?php echo SITE_URL; ?>/index.php" class="btn btn-navy fw-semibold px-4 py-2">
                    <i class="bi bi-house-door me-2"></i>मुख्य पृष्ठ (Home)
                </a>
                <a href="<?php echo SITE_URL; ?>/contact.php" class="btn btn-outline-navy fw-semibold px-4 py-2">
                    संपर्क करें (Contact Us)
                </a>
            </div>
        </div>
    </div>
</div>

<?php 
require_once 'includes/footer.php';
?>
