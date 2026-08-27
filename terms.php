<?php
/**
 * Terms and Conditions Page
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'नियम व शर्तें (Terms & Conditions)';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="row justify-content-center py-4 font-hindi small text-navy-custom">
    <div class="col-lg-9 col-md-11">
        <div class="bg-white p-4 p-md-5 rounded-3 shadow-sm border border-light">
            <h3 class="text-navy-custom fw-bold border-bottom border-gold-custom pb-2 mb-4">नियम व शर्तें (Terms & Conditions)</h3>
            
            <p class="text-dark-custom mb-3 lead" style="line-height: 1.8;">
                इस डिजिटल पोर्टल का उपयोग जिला अधिवक्ता संघ, बांदा (District Bar Association, Banda) द्वारा निर्धारित दिशानिर्देशों के अधीन है।
            </p>
            
            <h5 class="fw-bold text-navy-custom mt-4 mb-2">1. सेवा का उपयोग (Use of Services)</h5>
            <p class="text-muted mb-3">
                यह सदस्यता पटल केवल अधिकृत संघ सदस्यों, अध्यक्ष, महासचिव एवं प्रशासकीय दल के विधिक उपयोग के लिए है। अपनी लॉगिन आईडी व पासवर्ड की गोपनीयता बनाए रखने का दायित्व स्वयं सदस्य का होगा।
            </p>

            <h5 class="fw-bold text-navy-custom mt-4 mb-2">2. विधिक दायित्व (Legal Disclaimer)</h5>
            <p class="text-muted mb-3">
                इस पोर्टल पर प्रदर्शित जानकारी मुख्य रूप से प्रशासनिक एवं सूचनात्मक उद्देश्यों के लिए है। किसी भी विधिक संदर्भ के लिए संघ के भौतिक दस्तावेजों को ही अंतिम प्रमाण माना जाएगा।
            </p>

            <h5 class="fw-bold text-navy-custom mt-4 mb-2">3. भुगतान एवं रसीदें (Payments & Receipts)</h5>
            <p class="text-muted mb-3">
                वार्षिक बार शुल्क, चैंबर किराया अथवा अन्य किसी संघीय देयता का भुगतान संबंधित रसीदों के सत्यापन के उपरांत ही मान्य होगा।
            </p>

            <div class="mt-4 pt-3 border-top border-light text-end">
                <a href="<?php echo SITE_URL; ?>/index.php" class="btn btn-navy fw-semibold">मुख्य पृष्ठ पर जाएं</a>
            </div>
        </div>
    </div>
</div>

<?php 
require_once 'includes/footer.php';
?>
