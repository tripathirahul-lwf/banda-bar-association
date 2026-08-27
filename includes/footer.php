<?php
/**
 * Global footer file for District Bar Association, Banda
 */

// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit("Direct access forbidden.");
}
?>
    </div> <!-- / .container -->
</main>

<footer class="footer mt-auto py-5 bg-navy-custom text-white border-top border-gold-custom">
    <div class="container">
        <div class="row g-4">
            <!-- Left Info Block -->
            <div class="col-lg-4 col-md-12">
                <div class="d-flex align-items-center mb-3">
                    <?php 
                    $custom_logo = getSetting('logo', '');
                    if (!empty($custom_logo)): 
                    ?>
                        <img src="<?php echo SITE_URL; ?>/<?php echo $custom_logo; ?>" class="me-2" style="max-height: 40px;" onerror="this.style.display='none'">
                    <?php else: ?>
                        <div class="logo-placeholder bg-white text-navy-custom d-flex align-items-center justify-content-center rounded-circle me-2" style="width: 40px; height: 40px;">
                            <i class="bi bi-shield-shaded fs-5"></i>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h5 class="mb-0 fw-bold hindi-text text-gold-custom" style="font-size: 1.1rem;"><?php echo e(getSetting('association_name_hi', 'जिला अधिवक्ता संघ, बांदा')); ?></h5>
                        <small class="text-uppercase tracking-wider english-text text-light-custom" style="font-size: 0.75rem;"><?php echo e(getSetting('association_name_en', 'District Bar Association, Banda')); ?></small>
                    </div>
                </div>
                <p class="small text-muted-custom mb-3 font-hindi">
                    <?php echo e(getSetting('address', 'जनपद न्यायालय परिसर, बांदा')); ?>,<br>
                    पिन कोड - <?php echo e(getSetting('pincode', '२१०००१')); ?>
                </p>
                <p class="small text-muted-custom mb-0 font-hindi">
                    <span class="d-block"><i class="bi bi-award-fill text-gold-custom me-1"></i>स्थापना वर्ष: <?php echo e(getSetting('established_year', '1937')); ?></span>
                </p>
            </div>

            <!-- Quick Links -->
            <div class="col-lg-3 col-md-4 col-6 font-hindi">
                <h6 class="text-uppercase fw-bold text-gold-custom mb-3" style="font-size: 0.85rem; letter-spacing: 1px;">त्वरित लिंक्स (Quick Links)</h6>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/index.php" class="footer-link text-decoration-none small">मुख्य पृष्ठ (Home)</a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/about.php" class="footer-link text-decoration-none small">परिचय (About Us)</a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/office-bearers.php" class="footer-link text-decoration-none small">पदाधिकारी (Bearers)</a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/members.php" class="footer-link text-decoration-none small">सदस्य डायरेक्टरी (Members)</a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/notices.php" class="footer-link text-decoration-none small">नवीनतम सूचनाएं (Notices)</a></li>
                    <li class="mb-0"><a href="<?php echo SITE_URL; ?>/contact.php" class="footer-link text-decoration-none small">संपर्क करें (Contact)</a></li>
                </ul>
            </div>

            <!-- Member Services (digital modules) -->
            <div class="col-lg-3 col-md-4 col-6 font-hindi">
                <h6 class="text-uppercase fw-bold text-gold-custom mb-3" style="font-size: 0.85rem; letter-spacing: 1px;">डिजिटल सेवाएं (Services)</h6>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/id-card.php" class="footer-link text-decoration-none small">डिजिटल ID Card</a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/wakalatnama.php" class="footer-link text-decoration-none small">Wakalatnama (वकालतनामा)</a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/association-fund.php" class="footer-link text-decoration-none small">संघीय कोष (Assoc. Fund)</a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/advocate-fee.php" class="footer-link text-decoration-none small">अधिवक्ता शुल्क (Bar Fee)</a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/rooms.php" class="footer-link text-decoration-none small">कक्ष/रूम किराया (Room Rent)</a></li>
                    <li class="mb-0"><a href="<?php echo SITE_URL; ?>/election.php" class="footer-link text-decoration-none small">चुनाव परिणाम (Election)</a></li>
                </ul>
            </div>

            <!-- Portal Status/Legal -->
            <div class="col-lg-2 col-md-4 font-hindi">
                <h6 class="text-uppercase fw-bold text-gold-custom mb-3" style="font-size: 0.85rem; letter-spacing: 1px;">विधिक (Legal)</h6>
                <ul class="list-unstyled mb-3">
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/privacy-policy.php" class="footer-link text-decoration-none small">गोपनीयता नीति (Privacy)</a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/terms.php" class="footer-link text-decoration-none small">नियम व शर्तें (Terms)</a></li>
                    <li class="mb-0"><a href="<?php echo SITE_URL; ?>/disclaimer.php" class="footer-link text-decoration-none small">अस्वीकरण (Disclaimer)</a></li>
                </ul>
                <div class="border border-gold-custom p-2 rounded text-center">
                    <span class="d-block font-size-xs text-gold-custom fw-semibold mb-1">प्रशासनिक लॉगिन</span>
                    <a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-gold btn-xs w-100 fw-bold">Login Portal</a>
                </div>
            </div>
        </div>

        <hr class="my-4 border-gold-custom opacity-25">

        <div class="row align-items-center">
            <div class="col-md-6 text-center text-md-start mb-2 mb-md-0 font-hindi">
                <p class="small text-muted-custom mb-0">
                    &copy; <?php echo date('Y'); ?> <?php echo e(getSetting('footer_text', 'जिला अधिवक्ता संघ, बांदा। सर्वाधिकार सुरक्षित।')); ?>
                </p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <p class="small text-muted-custom mb-0 english-text">
                    Designed & developed under the judicial standards of Banda Bar.
                </p>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 Bundle JS CDN (includes Popper) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>

<!-- Custom App JS -->
<script src="<?php echo SITE_URL; ?>/assets/js/app.js"></script>
</body>
</html>
