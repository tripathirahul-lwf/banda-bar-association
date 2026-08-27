<?php
/**
 * Room / Chamber Management - Public Information Page
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'कक्ष/चैंबर आवंटन एवं किराया प्रबंधन (Chamber Services)';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10 text-center">
            <div class="bg-white p-5 rounded-3 shadow-sm border border-light font-hindi">
                <!-- Golden Icon Header -->
                <div class="logo-placeholder bg-navy-custom text-gold-custom d-inline-flex align-items-center justify-content-center rounded-circle mb-4" style="width: 80px; height: 80px;">
                    <i class="bi bi-door-closed display-5"></i>
                </div>
                
                <h2 class="text-navy-custom fw-bold mb-2">कक्ष/चैंबर किराया सेवाएं (Chamber Allotment Services)</h2>
                <p class="text-muted small english-text mb-4">Official chamber licensing, rent schedules, and occupancy directory panel.</p>
                
                <hr class="border-gold-custom mx-auto my-3" style="width: 80px; height: 2px; opacity: 1;">

                <div class="my-4 text-start text-dark-custom">
                    <p class="fs-6 leading-relaxed">
                        जिला अधिवक्ता संघ, बांदा अपने पंजीकृत एवं सक्रिय सदस्य अधिवक्ताओं के व्यावसायिक सुगमता हेतु दीवानी न्यायालय परिसर में चैंबरों (Rooms/Chambers) का आवंटन एवं रख-रखाव संचालित करता है।
                    </p>
                    
                    <h5 class="fw-bold text-navy-custom mt-4 mb-3 border-bottom pb-2">
                        <i class="bi bi-info-circle-fill text-gold-dark me-2"></i>आवंटन नियम एवं प्रमुख विशेषताएं
                    </h5>
                    
                    <ul class="list-group list-group-flush mb-4 small">
                        <li class="list-group-item bg-transparent px-0 py-2">
                            <i class="bi bi-check-circle-fill text-success me-2"></i><strong>आवंटन पात्रता:</strong> केवल बार एसोसिएशन के <strong>सक्रिय (Active)</strong> सदस्य अधिवक्ता ही आवेदन कर सकते हैं।
                        </li>
                        <li class="list-group-item bg-transparent px-0 py-2">
                            <i class="bi bi-check-circle-fill text-success me-2"></i><strong>सुरक्षा जमा (Security Deposit):</strong> प्रत्येक चैंबर आवंटन से पूर्व निर्धारित जमानत राशि जमा करना अनिवार्य है।
                        </li>
                        <li class="list-group-item bg-transparent px-0 py-2">
                            <i class="bi bi-check-circle-fill text-success me-2"></i><strong>मासिक किराया (Monthly Rent):</strong> प्रत्येक ब्लॉक एवं तल के अनुसार निर्धारित मासिक लाइसेंस शुल्क का समय पर भुगतान अपेक्षित है।
                        </li>
                        <li class="list-group-item bg-transparent px-0 py-2">
                            <i class="bi bi-check-circle-fill text-success me-2"></i><strong>साझा आवंटन (Shared Occupancy):</strong> संघ नियमानुसार कुछ चैंबरों में साझा आधार (Shared Capacity) पर भी आवंटन उपलब्ध है।
                        </li>
                    </ul>

                    <div class="alert alert-warning border-0 rounded-3 py-3 font-hindi shadow-xs">
                        <h6 class="fw-bold mb-1"><i class="bi bi-shield-lock-fill me-2"></i>अधिवक्ता पोर्टल लॉगिन अनिवार्य</h6>
                        <span class="small text-muted d-block mt-1">
                            गोपनीयता सुरक्षा मानकों के अंतर्गत चैंबरों की वित्तीय विवरणी, बकाया किराया, भुगतान इतिहास एवं नए आवंटन हेतु ऑनलाइन आवेदन प्रक्रिया केवल अधिकृत सदस्य लॉगिन के उपरांत ही उपलब्ध है।
                        </span>
                    </div>
                </div>

                <div class="d-flex justify-content-center gap-3">
                    <a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-navy fw-semibold px-4 py-2 font-hindi shadow-xs">
                        <i class="bi bi-box-arrow-in-right me-2"></i>पोर्टल में लॉगिन करें (Member Login)
                    </a>
                    <a href="<?php echo SITE_URL; ?>/contact.php" class="btn btn-outline-navy fw-semibold px-4 py-2 font-hindi">
                        अतिरिक्त पूछताछ
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
require_once 'includes/footer.php';
?>
