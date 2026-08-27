<?php
/**
 * Terms of Service Placeholder
 */

$pageTitle = 'नियम व शर्तें (Terms of Service)';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="row justify-content-center py-4">
    <div class="col-lg-9 col-md-11">
        <div class="bg-white p-4 p-md-5 rounded-3 shadow-sm border border-light">
            <h3 class="text-navy-custom font-hindi fw-bold border-bottom border-gold-custom pb-2 mb-4">नियम व शर्तें (Terms & Conditions)</h3>
            
            <p class="font-hindi text-dark-custom mb-3" style="line-height: 1.8;">
                इस डिजिटल पोर्टल का उपयोग जिला अधिवक्ता संघ, बांदा (District Bar Association, Banda) द्वारा निर्धारित दिशानिर्देशों के अधीन है। किसी भी अनधिकृत लॉगिन, डेटा स्कैपिंग या वेबसाइट संचालन में व्यवधान उत्पन्न करने वाले कृत्य को विधिक अपराध माना जाएगा।
            </p>
            
            <h5 class="fw-bold font-hindi text-navy-custom mt-4 mb-2">1. सेवा का उपयोग (Use of Services)</h5>
            <p class="small text-muted font-hindi">
                सदस्यता पटल केवल अधिकृत संघ सदस्यों, अध्यक्ष, महासचिव एवं प्रशासकीय दल के उपयोग के लिए है। अपनी लॉगिन आईडी व पासवर्ड की गोपनीयता बनाए रखने का दायित्व स्वयं सदस्य का होगा।
            </p>

            <h5 class="fw-bold font-hindi text-navy-custom mt-4 mb-2">2. भुगतान एवं शुल्क (Fee Policies)</h5>
            <p class="small text-muted font-hindi">
                वकालतनामा अथवा अधिवक्ता वार्षिक शुल्क के ऑनलाइन लेन-देन की रसीदें बैंक सर्वर प्रतिक्रिया पर आधारित होंगी। किसी भी प्रकार के विवादित भुगतान के लिए संघ की कार्यकारिणी का निर्णय अंतिम होगा।
            </p>

            <div class="mt-4 pt-3 border-top border-light text-end">
                <a href="<?php echo SITE_URL; ?>/index.php" class="btn btn-navy font-hindi fw-semibold">मुख्य पृष्ठ पर जाएं</a>
            </div>
        </div>
    </div>
</div>

<?php 
require_once 'includes/footer.php';
?>
