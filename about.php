<?php
/**
 * About Us Page - District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'परिचय (About Us)';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
?>

<!-- About Section Banner -->
<div class="bg-navy-custom text-white p-4 rounded-3 mb-4 shadow-sm border-bottom border-gold-custom">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1 class="h3 mb-1 font-hindi fw-bold text-gold-custom"><?php echo e(getSetting('association_name_hi', 'जिला अधिवक्ता संघ, बांदा')); ?></h1>
            <p class="mb-0 text-light-custom english-text text-uppercase tracking-wider small"><?php echo e(getSetting('association_name_en', 'District Bar Association, Banda')); ?> | Estd. <?php echo e(getSetting('established_year', '1937')); ?></p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <span class="badge bg-gold-custom text-navy-custom font-hindi px-3 py-2 fs-6">स्थापना वर्ष <?php echo e(getSetting('established_year', '१९३७')); ?></span>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Main Content Left -->
    <div class="col-lg-8">
        <div class="bg-white p-4 rounded-3 shadow-sm border border-light h-100 font-hindi text-navy-custom">
            <h3 class="fw-bold mb-3 border-bottom border-gold-custom pb-2">हमारी गौरवमयी यात्रा (About Us)</h3>
            <p class="text-dark-custom mb-3" style="line-height: 1.8; text-align: justify; white-space: pre-wrap;"><?php 
                echo e(getSetting('about_association', 'जिला अधिवक्ता संघ, बांदा उत्तर प्रदेश के सबसे प्राचीन और सम्मानित बार संघों में से एक है।')); 
            ?></p>

            <h3 class="fw-bold mt-4 mb-3 border-bottom border-gold-custom pb-2">स्थापना एवं इतिहास (History of DBA)</h3>
            <p class="text-dark-custom mb-3" style="line-height: 1.8; text-align: justify; white-space: pre-wrap;"><?php 
                echo e(getSetting('history', 'बांदा जिले के न्यायिक इतिहास में अधिवक्ताओं के इस संगठन की स्थापना वर्ष 1937 में की गई थी।')); 
            ?></p>

            <h3 class="fw-bold mt-4 mb-3 border-bottom border-gold-custom pb-2">लक्ष्य एवं विजन (Mission Statement)</h3>
            <p class="text-dark-custom mb-3" style="line-height: 1.8; text-align: justify; white-space: pre-wrap;"><?php 
                echo e(getSetting('mission', 'हमारा उद्देश्य अधिवक्ता हितों का संरक्षण एवं न्यायिक मूल्यों का कड़ाई से अनुपालन है।')); 
            ?></p>
        </div>
    </div>

    <!-- Side Content Right -->
    <div class="col-lg-4">
        <div class="bg-white p-4 rounded-3 shadow-sm border border-light mb-4 font-hindi small">
            <h4 class="text-navy-custom fw-bold mb-3 border-bottom border-gold-custom pb-2">महत्वपूर्ण जानकारी</h4>
            <table class="table table-sm table-borderless text-navy-custom">
                <tbody>
                    <tr>
                        <td class="fw-bold" style="width: 40%;">संस्था का नाम:</td>
                        <td><?php echo e(getSetting('association_name_hi', 'जिला अधिवक्ता संघ, बांदा')); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">स्थापना वर्ष:</td>
                        <td><?php echo e(getSetting('established_year', '1937')); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">स्थान:</td>
                        <td><?php echo e(getSetting('address', 'जनपद न्यायालय परिसर, बांदा')); ?></td>
                    </tr>
                    <tr>
                        <td class="fw-bold">अधिकारिक ईमेल:</td>
                        <td class="english-text"><?php echo e(getSetting('official_email', 'info@dbabanda.in')); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="bg-navy-custom text-white p-4 rounded-3 shadow-sm text-center">
            <i class="bi bi-telephone-outbound-fill text-gold-custom display-4 mb-3 d-block"></i>
            <h5 class="fw-bold font-hindi">सहायता एवं संपर्क</h5>
            <p class="small text-light-custom font-hindi mb-3">किसी भी सदस्यता अथवा विधिक जानकारी के लिए हमारे संपर्क केंद्र पर संपर्क करें।</p>
            <a href="<?php echo SITE_URL; ?>/contact.php" class="btn btn-gold btn-sm w-100 fw-bold py-2">
                संपर्क पृष्ठ पर जाएं (Contact Us)
            </a>
        </div>
    </div>
</div>

<?php 
require_once 'includes/footer.php';
?>
