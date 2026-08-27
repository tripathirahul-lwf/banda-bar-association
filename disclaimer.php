<?php
/**
 * Disclaimer Page
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'अस्वीकरण (Disclaimer)';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="row justify-content-center py-4 font-hindi small text-navy-custom">
    <div class="col-lg-9 col-md-11">
        <div class="bg-white p-4 p-md-5 rounded-3 shadow-sm border border-light">
            <h3 class="text-navy-custom fw-bold border-bottom border-gold-custom pb-2 mb-4">अस्वीकरण (Disclaimer)</h3>
            
            <p class="text-dark-custom mb-3 lead" style="line-height: 1.8;">
                जिला अधिवक्ता संघ, बांदा (District Bar Association, Banda) की आधिकारिक वेबसाइट पर आपका स्वागत है।
            </p>
            
            <h5 class="fw-bold text-navy-custom mt-4 mb-2">1. सूचना की सत्यता (Accuracy of Information)</h5>
            <p class="text-muted mb-3">
                इस वेबसाइट पर दी गई सभी सूचनाएं और डेटा केवल सामान्य जानकारी और प्रशासनिक सुगमता के लिए हैं। यद्यपि हम डेटा की सत्यता और प्रासंगिकता बनाए रखने का पूरा प्रयास करते हैं, फिर भी किसी विधिक संदर्भ अथवा न्यायालयीन कार्यवाही के लिए अधिकारिक और प्रमाणित स्रोतों या संघ के कार्यालय से भौतिक सत्यापन अवश्य करें।
            </p>

            <h5 class="fw-bold text-navy-custom mt-4 mb-2">2. न्यायालयीन सूचनाएं (Official Judicial Information)</h5>
            <p class="text-muted mb-3">
                न्यायालय की दैनिक वाद सूची (Cause List), स्थगन (Adjournments) अथवा अन्य न्यायिक आदेशों के संबंध में अधिकृत रूप से न्यायालय प्रशासन के निर्देशों को ही अंतिम माना जाए। इस पोर्टल पर प्रदर्शित ऐसी सूचनाओं के आधार पर लिए गए किसी निर्णय के लिए संघ जिम्मेदार नहीं होगा।
            </p>

            <h5 class="fw-bold text-navy-custom mt-4 mb-2">3. बाहरी लिंक्स (External Links)</h5>
            <p class="text-muted mb-3">
                इस वेबसाइट में उपलब्ध कराये गए बाहरी लिंक केवल उपयोगकर्ता की सुविधा के लिए हैं। उन साइटों की सामग्री, सुरक्षा और गोपनीयता नीतियों पर हमारा नियंत्रण नहीं है।
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
