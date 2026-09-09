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
            <!-- Left Info Block: Brand & Contact Information -->
            <div class="col-lg-4 col-md-12">
                <div class="d-flex align-items-center mb-3">
                    <img src="<?php echo SITE_URL; ?>/assets/images/logo.png" alt="DBA Banda Logo" class="me-2.5 rounded-circle shadow-sm" style="width: 48px; height: 48px; object-fit: cover; border: 2px solid var(--gold-accent);" onerror="this.src='<?php echo SITE_URL; ?>/assets/images/logo.svg'">
                    <div>
                        <h5 class="mb-0 fw-bold hindi-text text-gold-custom" style="font-size: 1.15rem;"><?php echo e(getSetting('association_name_hi', 'जिला अधिवक्ता संघ, बांदा')); ?></h5>
                        <small class="text-uppercase tracking-wider english-text text-light-custom" style="font-size: 0.72rem; letter-spacing: 0.5px;"><?php echo e(getSetting('association_name_en', 'District Bar Association, Banda')); ?></small>
                    </div>
                </div>

                <div class="mb-3">
                    <span class="badge bg-gold-custom text-navy-custom fw-bold px-2.5 py-1 mb-2" style="font-size: 0.72rem;">
                        <i class="fa-solid fa-scale-balanced me-1"></i>स्थापना वर्ष: <?php echo e(getSetting('established_year', '1937')); ?>
                    </span>
                </div>

                <!-- Contact & Location details -->
                <div class="footer-contact-details mb-2">
                    <div class="footer-contact-item">
                        <i class="fa-solid fa-location-dot"></i>
                        <span><?php echo e(getSetting('address', 'जनपद न्यायालय परिसर, बांदा')); ?>, पिन कोड - <?php echo e(getSetting('pincode', '२१०००१')); ?></span>
                    </div>
                    <?php if ($contact_num = getSetting('contact_number')): ?>
                    <div class="footer-contact-item">
                        <i class="fa-solid fa-phone"></i>
                        <span>हेल्पलाइन: <a href="tel:<?php echo e($contact_num); ?>" class="text-decoration-none text-light-custom" style="transition: color 0.2s;" onmouseover="this.style.color='#D9B44A'" onmouseout="this.style.color='#E2E8F0'"><?php echo e($contact_num); ?></a></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($official_mail = getSetting('official_email')): ?>
                    <div class="footer-contact-item">
                        <i class="fa-solid fa-envelope"></i>
                        <span>ईमेल: <a href="mailto:<?php echo e($official_mail); ?>" class="text-decoration-none text-light-custom" style="transition: color 0.2s;" onmouseover="this.style.color='#D9B44A'" onmouseout="this.style.color='#E2E8F0'"><?php echo e($official_mail); ?></a></span>
                    </div>
                    <?php endif; ?>
                    <?php if ($timing = getSetting('office_timing')): ?>
                    <div class="footer-contact-item">
                        <i class="fa-regular fa-clock"></i>
                        <span>कार्यालय समय: <?php echo e($timing); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="col-lg-3 col-md-4 col-6 font-hindi">
                <h6 class="text-uppercase fw-bold text-gold-custom mb-3 d-flex align-items-center gap-1.5" style="font-size: 0.88rem; letter-spacing: 0.8px;">
                    <i class="fa-solid fa-compass text-gold-custom"></i> त्वरित लिंक्स (Quick Links)
                </h6>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/index.php" class="footer-link"><i class="fa-solid fa-chevron-right"></i> मुख्य पृष्ठ (Home)</a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/about.php" class="footer-link"><i class="fa-solid fa-chevron-right"></i> परिचय (About Us)</a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/office-bearers.php" class="footer-link"><i class="fa-solid fa-chevron-right"></i> पदाधिकारी (Bearers)</a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/members.php" class="footer-link"><i class="fa-solid fa-chevron-right"></i> सदस्य डायरेक्टरी (Members)</a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/notices.php" class="footer-link"><i class="fa-solid fa-chevron-right"></i> नवीनतम सूचनाएं (Notices)</a></li>
                    <li class="mb-0"><a href="<?php echo SITE_URL; ?>/contact.php" class="footer-link"><i class="fa-solid fa-chevron-right"></i> संपर्क करें (Contact)</a></li>
                </ul>
            </div>

            <!-- Member Services (digital modules) -->
            <div class="col-lg-3 col-md-4 col-6 font-hindi">
                <h6 class="text-uppercase fw-bold text-gold-custom mb-3 d-flex align-items-center gap-1.5" style="font-size: 0.88rem; letter-spacing: 0.8px;">
                    <i class="fa-solid fa-laptop-file text-gold-custom"></i> डिजिटल सेवाएं (Services)
                </h6>
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/id-card.php" class="footer-link"><i class="fa-solid fa-chevron-right"></i> डिजिटल ID Card</a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/wakalatnama.php" class="footer-link"><i class="fa-solid fa-chevron-right"></i> वकालतनामा (Wakalatnama)</a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/association-fund.php" class="footer-link"><i class="fa-solid fa-chevron-right"></i> संघीय कोष (Assoc. Fund)</a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/advocate-fee.php" class="footer-link"><i class="fa-solid fa-chevron-right"></i> अधिवक्ता शुल्क (Bar Fee)</a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/rooms.php" class="footer-link"><i class="fa-solid fa-chevron-right"></i> कक्ष/रूम किराया (Room Rent)</a></li>
                    <li class="mb-0"><a href="<?php echo SITE_URL; ?>/election.php" class="footer-link"><i class="fa-solid fa-chevron-right"></i> चुनाव परिणाम (Election)</a></li>
                </ul>
            </div>

            <!-- Portal Status/Legal -->
            <div class="col-lg-2 col-md-4 font-hindi">
                <h6 class="text-uppercase fw-bold text-gold-custom mb-3 d-flex align-items-center gap-1.5" style="font-size: 0.88rem; letter-spacing: 0.8px;">
                    <i class="fa-solid fa-scale-unbalanced-flip text-gold-custom"></i> विधिक (Legal)
                </h6>
                <ul class="list-unstyled mb-3">
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/privacy-policy.php" class="footer-link"><i class="fa-solid fa-chevron-right"></i> गोपनीयता नीति (Privacy)</a></li>
                    <li class="mb-2"><a href="<?php echo SITE_URL; ?>/terms.php" class="footer-link"><i class="fa-solid fa-chevron-right"></i> नियम व शर्तें (Terms)</a></li>
                    <li class="mb-0"><a href="<?php echo SITE_URL; ?>/disclaimer.php" class="footer-link"><i class="fa-solid fa-chevron-right"></i> अस्वीकरण (Disclaimer)</a></li>
                </ul>
                <div class="footer-portal-card text-center">
                    <span class="d-block text-gold-custom fw-bold mb-1" style="font-size: 0.78rem;">
                        <i class="fa-solid fa-shield-halved me-1"></i>अधिकृत लॉगिन
                    </span>
                    <small class="d-block text-muted-custom mb-2" style="font-size: 0.7rem;">अधिवक्ता एवं प्रशासनिक</small>
                    <a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-gold btn-sm w-100 fw-bold d-flex align-items-center justify-content-center gap-1.5 shadow-sm">
                        <i class="fa-solid fa-right-to-bracket"></i> Login Portal
                    </a>
                </div>
            </div>
        </div>

        <hr class="my-4 border-gold-custom opacity-25">

        <div class="row align-items-center g-3">
            <div class="col-md-6 text-center text-md-start font-hindi">
                <?php
                $raw_footer = getSetting('footer_text', 'जिला अधिवक्ता संघ, बांदा। सर्वाधिकार सुरक्षित।');
                // Remove pre-existing year or duplicate copyright marks from setting string
                $clean_footer = preg_replace('/^(\s*©\s*\d{4}\s*|\s*&copy;\s*\d{4}\s*|\s*©\s*|\s*&copy;\s*)/u', '', $raw_footer);
                if (empty(trim($clean_footer))) {
                    $clean_footer = 'जिला अधिवक्ता संघ, बांदा। सर्वाधिकार सुरक्षित।';
                }
                ?>
                <p class="small text-muted-custom mb-0">
                    &copy; <?php echo date('Y'); ?> <?php echo e(trim($clean_footer)); ?>
                </p>
            </div>
            <div class="col-md-6 text-center text-md-end d-flex align-items-center justify-content-center justify-content-md-end gap-3">
                <span class="small text-muted-custom english-text">
                    Designed & developed under the judicial standards of Banda Bar.
                </span>
                <a href="#" onclick="window.scrollTo({top: 0, behavior: 'smooth'}); return false;" class="btn-scroll-top shadow-sm flex-shrink-0" title="पृष्ठ के शीर्ष पर जाएं (Back to top)">
                    <i class="fa-solid fa-arrow-up"></i>
                </a>
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
