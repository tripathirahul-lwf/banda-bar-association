<?php
/**
 * Multi-Role Login Portal - District Bar Association, Banda
 * Established: 1937
 */

require_once 'config/config.php';
require_once 'includes/auth.php';

// Handle logout action if requested (Requirement 56)
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logoutUser();
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }
    setFlash('success', 'आप सफलतापूर्वक लॉग आउट हो गए हैं। (You have been logged out successfully.)');
    redirect('login.php');
}

// Redirect already logged in user to their dashboard automatically
if (isLoggedIn()) {
    redirectByRole();
}

$pageTitle = 'सदस्य लॉगिन (Member Login)';
require_once 'includes/header.php';
require_once 'includes/navbar.php';

// Validate selected role parameter
$allowed_roles = ['admin', 'president', 'mahasachiv', 'member'];
$selected_role = isset($_GET['role']) ? strtolower(trim($_GET['role'])) : '';

if ($selected_role !== '' && !in_array($selected_role, $allowed_roles)) {
    setFlash('danger', 'अमान्य लॉग इन भूमिका चयनित है। (Invalid Login Role Selected.)');
    redirect('login.php');
}

$csrf_token = generate_csrf_token();
?>

<?php if ($selected_role === ''): ?>
<!-- ========================================================= -->
<!-- 1. ROLE SELECTION LANDING PAGE (PORTAL GATEWAY)           -->
<!-- ========================================================= -->

<!-- Hero Header Strip -->
<div class="bg-navy-custom text-white p-3 p-md-5 rounded-4 mb-4 shadow-sm border-bottom border-gold-custom position-relative overflow-hidden">
    <div class="row align-items-center position-relative" style="z-index: 2;">
        <div class="col-lg-8">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                <span class="badge bg-gold-custom text-navy-custom font-hindi fw-bold px-3 py-1.5 badge-responsive" style="font-size: 0.78rem;">
                    <i class="fa-solid fa-scale-balanced me-1.5"></i>स्थापना: 1937
                </span>
                <span class="badge bg-white bg-opacity-10 text-white font-hindi px-3 py-1.5 border border-light-custom badge-responsive" style="font-size: 0.76rem;">
                    <i class="fa-solid fa-certificate text-gold-custom me-1.5"></i>उत्तर प्रदेश बार काउंसिल सम्बद्ध
                </span>
                <span class="badge bg-white bg-opacity-10 text-white font-hindi px-3 py-1.5 border border-light-custom badge-responsive" style="font-size: 0.76rem;">
                    <i class="fa-solid fa-shield-halved text-gold-custom me-1.5"></i>256-Bit SSL Encrypted
                </span>
            </div>
            <h1 class="h2 mb-2 font-hindi fw-bold text-white hero-heading-responsive">डिजिटल लॉगिन पोर्टल (Official Login Gateway)</h1>
            <p class="mb-0 text-light-custom font-hindi fs-6 opacity-90 hero-subtext-responsive">
                जिला अधिवक्ता संघ, बांदा — अपनी अधिकृत संगठनात्मक भूमिका का चयन कर सुरक्षित डैशबोर्ड में प्रवेश करें
            </p>
        </div>
        <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
            <button type="button" class="btn btn-outline-gold font-hindi fw-semibold px-3 py-2 shadow-sm w-100 w-lg-auto" data-bs-toggle="modal" data-bs-target="#loginHelpModal">
                <i class="fa-solid fa-circle-question me-1.5"></i> लॉगिन सहायता केंद्र
            </button>
        </div>
    </div>
</div>

<!-- Inline Flash Alert if present on Landing page -->
<?php foreach (['danger', 'warning', 'success', 'info'] as $type): ?>
    <?php if (has_flash_message($type)): ?>
        <div class="alert alert-<?php echo $type; ?> alert-dismissible fade show border-0 shadow-sm rounded-3 mb-4 font-hindi" role="alert">
            <div class="d-flex align-items-center gap-2">
                <?php if ($type === 'success'): ?>
                    <i class="fa-solid fa-circle-check text-success fs-5"></i>
                <?php elseif ($type === 'danger'): ?>
                    <i class="fa-solid fa-triangle-exclamation text-danger fs-5"></i>
                <?php elseif ($type === 'warning'): ?>
                    <i class="fa-solid fa-circle-exclamation text-warning fs-5"></i>
                <?php else: ?>
                    <i class="fa-solid fa-circle-info text-info fs-5"></i>
                <?php endif; ?>
                <div><?php echo get_flash_message($type); ?></div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
<?php endforeach; ?>

<!-- 4 Unified Institutional Role Cards -->
<div class="row g-4 mb-4">
    <!-- 1. Member Card (Primary Entry Point) -->
    <div class="col-md-6 col-lg-3">
        <div class="login-role-card shadow-sm border border-gold-custom">
            <div class="position-absolute top-0 end-0 mt-2 me-2">
                <span class="badge bg-gold-custom text-navy-custom font-hindi px-2 py-1" style="font-size: 0.68rem; font-weight: 700;">
                    <i class="fa-solid fa-star me-1"></i>मुख्य द्वार
                </span>
            </div>
            <div class="role-icon-box">
                <i class="fa-solid fa-user-tie"></i>
            </div>
            <span class="badge bg-navy-custom text-gold-custom font-hindi mb-2 px-2.5 py-1 align-self-center font-size-xs" style="font-size: 0.72rem;">पंजीकृत अधिवक्ता</span>
            <h5 class="fw-bold font-hindi text-navy-custom mb-1 fs-6">संघ सदस्य (Bar Member)</h5>
            <p class="small text-muted font-hindi mb-2">
                बार संघ के समस्त पंजीकृत अधिवक्ताओं हेतु अधिकृत सदस्य पटल।
            </p>
            <ul class="role-feature-list font-hindi">
                <li><i class="fa-solid fa-circle-check"></i> डिजिटल बार पहचान पत्र (ID Card)</li>
                <li><i class="fa-solid fa-circle-check"></i> आधिकारिक वकालतनामा जनरेटर</li>
                <li><i class="fa-solid fa-circle-check"></i> वार्षिक शुल्क भुगतान व रसीदें</li>
                <li><i class="fa-solid fa-circle-check"></i> चैंबर / कक्ष किराया आवेदन</li>
            </ul>
            <div class="mt-auto">
                <a href="login.php?role=member" class="btn btn-navy w-100 font-hindi fw-bold py-2.5 rounded-3 shadow-sm d-flex align-items-center justify-content-center gap-2">
                    <span>प्रवेश करें</span> <i class="fa-solid fa-arrow-right text-gold-custom small"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- 2. President Card -->
    <div class="col-md-6 col-lg-3">
        <div class="login-role-card shadow-sm border">
            <div class="role-icon-box">
                <i class="fa-solid fa-gavel"></i>
            </div>
            <span class="badge bg-navy-custom text-gold-custom font-hindi mb-2 px-2.5 py-1 align-self-center font-size-xs" style="font-size: 0.72rem;">संवैधानिक प्रमुख</span>
            <h5 class="fw-bold font-hindi text-navy-custom mb-1 fs-6">अध्यक्ष (President)</h5>
            <p class="small text-muted font-hindi mb-2">
                संघ की नीतियों, कल्याणकारी योजनाओं एवं वित्तीय स्वीकृति की निगरानी।
            </p>
            <ul class="role-feature-list font-hindi">
                <li><i class="fa-solid fa-circle-check"></i> संघ नीति व प्रस्ताव अनुमोदन</li>
                <li><i class="fa-solid fa-circle-check"></i> कल्याणकारी कोष व अनुदान स्वीकृति</li>
                <li><i class="fa-solid fa-circle-check"></i> उच्चस्तरीय न्यायिक समन्वय</li>
                <li><i class="fa-solid fa-circle-check"></i> विशेष कार्यकारिणी समीक्षा</li>
            </ul>
            <div class="mt-auto">
                <a href="login.php?role=president" class="btn btn-navy w-100 font-hindi fw-bold py-2.5 rounded-3 shadow-sm d-flex align-items-center justify-content-center gap-2">
                    <span>प्रवेश करें</span> <i class="fa-solid fa-arrow-right text-gold-custom small"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- 3. Secretary Card -->
    <div class="col-md-6 col-lg-3">
        <div class="login-role-card shadow-sm border">
            <div class="role-icon-box">
                <i class="fa-solid fa-pen-nib"></i>
            </div>
            <span class="badge bg-navy-custom text-gold-custom font-hindi mb-2 px-2.5 py-1 align-self-center font-size-xs" style="font-size: 0.72rem;">मुख्य प्रशासनिक</span>
            <h5 class="fw-bold font-hindi text-navy-custom mb-1 fs-6">महासचिव (General Secretary)</h5>
            <p class="small text-muted font-hindi mb-2">
                दैनिक प्रशासनिक संचालन, पत्राचार एवं सदस्यता सत्यापन पटल।
            </p>
            <ul class="role-feature-list font-hindi">
                <li><i class="fa-solid fa-circle-check"></i> दैनिक प्रशासनिक कार्य व पत्राचार</li>
                <li><i class="fa-solid fa-circle-check"></i> नवीन सदस्यता आवेदन समीक्षा</li>
                <li><i class="fa-solid fa-circle-check"></i> वार्षिक चुनाव व मतदाता सूची</li>
                <li><i class="fa-solid fa-circle-check"></i> कक्ष आवंटन व शिकायत निवारण</li>
            </ul>
            <div class="mt-auto">
                <a href="login.php?role=mahasachiv" class="btn btn-navy w-100 font-hindi fw-bold py-2.5 rounded-3 shadow-sm d-flex align-items-center justify-content-center gap-2">
                    <span>प्रवेश करें</span> <i class="fa-solid fa-arrow-right text-gold-custom small"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- 4. Admin Card -->
    <div class="col-md-6 col-lg-3">
        <div class="login-role-card shadow-sm border">
            <div class="role-icon-box">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <span class="badge bg-navy-custom text-gold-custom font-hindi mb-2 px-2.5 py-1 align-self-center font-size-xs" style="font-size: 0.72rem;">सिस्टम प्रशासन</span>
            <h5 class="fw-bold font-hindi text-navy-custom mb-1 fs-6">प्रशासक (Administrator)</h5>
            <p class="small text-muted font-hindi mb-2">
                पोर्टल सुरक्षा, डेटाबेस प्रबंधन एवं तकनीकी कॉन्फ़िगरेशन।
            </p>
            <ul class="role-feature-list font-hindi">
                <li><i class="fa-solid fa-circle-check"></i> वेबसाइट व डेटाबेस प्रबंधन</li>
                <li><i class="fa-solid fa-circle-check"></i> यूजर रोल्स व एक्सेस नियंत्रण</li>
                <li><i class="fa-solid fa-circle-check"></i> सुरक्षा ऑडिट लॉग्स व बैकअप</li>
                <li><i class="fa-solid fa-circle-check"></i> सिस्टम सेटिंग्स व कॉन्फ़िगरेशन</li>
            </ul>
            <div class="mt-auto">
                <a href="login.php?role=admin" class="btn btn-navy w-100 font-hindi fw-bold py-2.5 rounded-3 shadow-sm d-flex align-items-center justify-content-center gap-2">
                    <span>प्रवेश करें</span> <i class="fa-solid fa-arrow-right text-gold-custom small"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Institutional Helpline & Support Strip -->
<div class="card border-0 bg-white shadow-sm rounded-3 p-3 p-md-4 mb-5 border border-light-custom">
    <div class="row align-items-center g-3">
        <div class="col-lg-8 text-lg-start font-hindi">
            <div class="d-flex align-items-center gap-2 mb-1">
                <i class="fa-solid fa-headset text-gold-dark fs-5"></i>
                <h6 class="fw-bold text-navy-custom mb-0 fs-6">लॉगिन अथवा सदस्यता संख्या संबंधी सहायता:</h6>
            </div>
            <p class="small text-muted mb-0">
                कार्यालय: जिला अधिवक्ता संघ भवन, जनपद न्यायालय परिसर, बांदा (उ.प्र.) | कार्य समय: 10:00 AM - 05:00 PM (सोमवार - शनिवार)
            </p>
        </div>
        <div class="col-lg-4 text-lg-end d-flex align-items-center justify-content-lg-end gap-2 flex-wrap">
            <button type="button" class="btn btn-outline-navy btn-sm font-hindi fw-semibold px-3 py-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#loginHelpModal">
                <i class="fa-solid fa-circle-question me-1 text-gold-dark"></i> सहायता केंद्र
            </button>
            <a href="tel:05192220000" class="btn btn-gold btn-sm font-hindi fw-bold px-3 py-2 shadow-sm">
                <i class="fa-solid fa-phone me-1"></i> 05192-220000
            </a>
        </div>
    </div>
</div>

<?php else: ?>
    <!-- ========================================================= -->
    <!-- 2. DEDICATED ROLE LOGIN PAGE (SPLIT 2-COLUMN DESKTOP)      -->
    <!-- ========================================================= -->
    <?php
    // Prepare role presentation metadata
    $role_meta = [
        'member' => [
            'title' => 'संघ सदस्य लॉगिन',
            'icon' => 'fa-solid fa-user-tie',
            'ident_label' => 'सदस्यता संख्या / मोबाइल नंबर',
            'ident_placeholder' => 'उदा. DBA-003 या 9876543210',
            'ident_icon' => 'fa-solid fa-id-card',
            'default_ident' => 'DBA-003',
            'default_pass' => 'member123',
        ],
        'president' => [
            'title' => 'अध्यक्ष लॉगिन',
            'icon' => 'fa-solid fa-gavel',
            'ident_label' => 'यूज़रनेम / मोबाइल / ईमेल',
            'ident_placeholder' => 'president या पंजीकृत विवरण',
            'ident_icon' => 'fa-solid fa-user-shield',
            'default_ident' => 'president',
            'default_pass' => 'president123',
        ],
        'mahasachiv' => [
            'title' => 'महासचिव लॉगिन',
            'icon' => 'fa-solid fa-pen-nib',
            'ident_label' => 'यूज़रनेम / मोबाइल / ईमेल',
            'ident_placeholder' => 'mahasachiv या पंजीकृत विवरण',
            'ident_icon' => 'fa-solid fa-signature',
            'default_ident' => 'mahasachiv',
            'default_pass' => 'mahasachiv123',
        ],
        'admin' => [
            'title' => 'प्रशासक लॉगिन',
            'icon' => 'fa-solid fa-shield-halved',
            'ident_label' => 'यूज़रनेम / ईमेल',
            'ident_placeholder' => 'admin या अधिकृत ईमेल',
            'ident_icon' => 'fa-solid fa-user-gear',
            'default_ident' => 'admin',
            'default_pass' => 'admin123',
        ],
    ];

    $meta = $role_meta[$selected_role] ?? $role_meta['member'];
    ?>

    <!-- Top Action Breadcrumb & SSL Badge -->
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <a href="login.php" class="btn btn-outline-navy btn-sm font-hindi fw-semibold px-3 py-1.5 shadow-xs">
            <i class="fa-solid fa-arrow-left me-1"></i> भूमिका सूची पर वापस जाएं (All Roles)
        </a>
        <span class="badge bg-white text-navy-custom border px-3 py-2 font-hindi shadow-xs">
            <i class="fa-solid fa-lock text-gold-dark me-1.5"></i>सुरक्षित 256-Bit SSL एन्क्रिप्टेड सत्र
        </span>
    </div>

    <!-- 2-Column Split Portal Layout -->
    <div class="row g-4 mb-5 align-items-stretch">
        
        <!-- Left Column: Institutional Brand & Trust Panel (Desktop Showcase) -->
        <div class="col-lg-5 d-none d-lg-block">
            <div class="login-brand-panel">
                <div class="brand-crest-box">
                    <i class="fa-solid fa-scale-balanced"></i>
                </div>
                <span class="badge bg-gold-custom text-navy-custom font-hindi fw-bold px-3 py-1 align-self-start mb-2" style="font-size: 0.72rem;">
                    स्थापना: सन् 1937
                </span>
                <h3 class="fw-bold font-hindi text-white mb-1 fs-4">जिला अधिवक्ता संघ, बांदा</h3>
                <p class="text-white-50 font-hindi small mb-4" style="line-height: 1.6;">
                    जनपद न्यायालय परिसर, बांदा (उत्तर प्रदेश) • उत्तर प्रदेश बार काउंसिल द्वारा विधिवत मान्यता प्राप्त विधिक संस्था।
                </p>

                <div class="brand-trust-item font-hindi">
                    <div class="brand-trust-icon">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <div>
                        <h6 class="text-white fw-bold mb-0.5 fs-6">सुरक्षित एवं अधिकृत सत्र</h6>
                        <p class="text-white-50 small mb-0">256-Bit डेटा सुरक्षा, पासवर्ड एन्क्रिप्शन एवं निरंतर ऑडिट ट्रैकिंग।</p>
                    </div>
                </div>

                <div class="brand-trust-item font-hindi">
                    <div class="brand-trust-icon">
                        <i class="fa-solid fa-id-card"></i>
                    </div>
                    <div>
                        <h6 class="text-white fw-bold mb-0.5 fs-6">एकीकृत डिजिटल विधिक सेवाएं</h6>
                        <p class="text-white-50 small mb-0">बार पहचान पत्र, वकालतनामा जनरेटर, कक्ष किराया एवं कल्याणकारी योजनाएं।</p>
                    </div>
                </div>

                <div class="brand-trust-item font-hindi">
                    <div class="brand-trust-icon">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                    <div>
                        <h6 class="text-white fw-bold mb-0.5 fs-6">24x7 ऑनलाइन उपलब्धता</h6>
                        <p class="text-white-50 small mb-0">सभी पंजीकृत अधिवक्ताओं व पदाधिकारियों हेतु सुगम व निर्बाध एक्सेस।</p>
                    </div>
                </div>

                <div class="mt-auto pt-4 border-top border-white border-opacity-10 font-hindi">
                    <div class="d-flex align-items-center justify-content-between text-white-50 small">
                        <span><i class="fa-solid fa-phone me-1.5 text-gold-custom"></i>05192-220000</span>
                        <span><i class="fa-solid fa-envelope me-1.5 text-gold-custom"></i>info@dbabanda.in</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Focused Sign-In Workspace -->
        <div class="col-lg-7 col-12">
            
            <!-- 1-Click Role Switcher Tabs -->
            <div class="role-nav-segmented mb-3 font-hindi shadow-xs">
                <a href="login.php?role=member" class="role-nav-pill <?php echo $selected_role === 'member' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-user-tie"></i> सदस्य
                </a>
                <a href="login.php?role=president" class="role-nav-pill <?php echo $selected_role === 'president' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-gavel"></i> अध्यक्ष
                </a>
                <a href="login.php?role=mahasachiv" class="role-nav-pill <?php echo $selected_role === 'mahasachiv' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-pen-nib"></i> महासचिव
                </a>
                <a href="login.php?role=admin" class="role-nav-pill <?php echo $selected_role === 'admin' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-shield-halved"></i> एडमिन
                </a>
            </div>

            <!-- The Clean Login Form Card -->
            <div class="login-form-card p-4 p-md-4">
                
                <!-- Role Header in Card -->
                <div class="text-center mb-3">
                    <div class="rounded-circle bg-navy-custom text-gold-custom d-inline-flex align-items-center justify-content-center shadow-sm mb-2 border border-gold-custom border-2" style="width: 56px; height: 56px; font-size: 1.45rem;">
                        <i class="<?php echo $meta['icon']; ?>"></i>
                    </div>
                    <h3 class="fw-bold font-hindi text-navy-custom mb-0 fs-4"><?php echo e($meta['title']); ?></h3>
                </div>

                <!-- Contextual INLINE Flash Messages (Only displayed on error/warning) -->
                <?php foreach (['danger', 'warning', 'success', 'info'] as $type): ?>
                    <?php if (has_flash_message($type)): ?>
                        <div class="alert alert-<?php echo $type; ?> alert-dismissible fade show border-0 shadow-sm rounded-3 mb-3 py-2.5 px-3 font-hindi small" role="alert">
                            <div class="d-flex align-items-center gap-2">
                                <?php if ($type === 'success'): ?>
                                    <i class="fa-solid fa-circle-check text-success fs-5 flex-shrink-0"></i>
                                <?php elseif ($type === 'danger'): ?>
                                    <i class="fa-solid fa-triangle-exclamation text-danger fs-5 flex-shrink-0"></i>
                                <?php elseif ($type === 'warning'): ?>
                                    <i class="fa-solid fa-circle-exclamation text-warning fs-5 flex-shrink-0"></i>
                                <?php else: ?>
                                    <i class="fa-solid fa-circle-info text-info fs-5 flex-shrink-0"></i>
                                <?php endif; ?>
                                <div class="fw-semibold"><?php echo get_flash_message($type); ?></div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <!-- Pure Required Form -->
                <form id="loginForm" method="POST" action="actions/login-action.php" novalidate>
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                    <input type="hidden" name="role" value="<?php echo sanitize($selected_role); ?>">
                    
                    <!-- Identifier -->
                    <div class="mb-3">
                        <label for="identifier" class="form-label font-hindi small fw-semibold text-navy-custom mb-1.5">
                            <?php echo e($meta['ident_label']); ?> <span class="text-danger">*</span>
                        </label>
                        <div class="input-group login-input-group">
                            <span class="input-group-text border-end-0">
                                <i class="<?php echo $meta['ident_icon']; ?> text-navy-custom opacity-75"></i>
                            </span>
                            <input type="text" class="form-control border-start-0 font-hindi ps-1" id="identifier" name="identifier" placeholder="<?php echo e($meta['ident_placeholder']); ?>" autocomplete="username" required autofocus>
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1.5">
                            <label for="password" class="form-label font-hindi small fw-semibold text-navy-custom mb-0">
                                पासवर्ड <span class="text-danger">*</span>
                            </label>
                            <button type="button" 
                                    class="btn btn-sm autofill-btn-hover border border-gold-custom text-navy-custom font-hindi fw-semibold py-0.5 px-2.5 rounded-pill shadow-xs" 
                                    id="btnAutofillPassword" 
                                    onclick="autoFillRoleCredentials()"
                                    title="पासवर्ड स्वतः भरें (Autofill: <?php echo e($meta['default_pass']); ?>)">
                                <i class="fa-solid fa-wand-magic-sparkles text-gold-dark me-1"></i>पासवर्ड ऑटोफ़िल
                            </button>
                        </div>
                        <div class="input-group login-input-group">
                            <span class="input-group-text border-end-0">
                                <i class="fa-solid fa-lock text-navy-custom opacity-75"></i>
                            </span>
                            <input type="password" class="form-control border-start-0 border-end-0 ps-1" id="password" name="password" placeholder="अपना पासवर्ड दर्ज करें" autocomplete="current-password" required>
                            <button type="button" class="input-group-text bg-white border-start-0 text-muted" id="togglePasswordBtn" style="cursor: pointer;" title="पासवर्ड दिखाएं/छिपाएं">
                                <i class="fa-solid fa-eye-slash" id="togglePasswordIcon"></i>
                            </button>
                        </div>
                        
                        <!-- Live Caps Lock Warning Banner (Only shown if Caps Lock is active) -->
                        <div id="capslockAlert" class="alert alert-warning py-1.5 px-3 small font-hindi mt-2 mb-0 d-none rounded-3 border-0 shadow-xs" style="font-size: 0.74rem;">
                            <i class="fa-solid fa-arrow-up-from-bracket me-1 text-danger"></i> <strong>ध्यान दें:</strong> Caps Lock चालू है
                        </div>
                    </div>

                    <!-- Remember Me & Forgot Password in one clean row -->
                    <div class="d-flex align-items-center justify-content-between mb-4 font-hindi">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="rememberMe" name="remember_me">
                            <label class="form-check-label small text-muted" for="rememberMe" style="font-size: 0.82rem;">
                                मुझे याद रखें
                            </label>
                        </div>
                        <a href="#" data-bs-toggle="modal" data-bs-target="#loginHelpModal" class="small text-navy-custom font-hindi text-decoration-none" style="font-size: 0.8rem;">
                            <i class="fa-solid fa-circle-question me-1 text-gold-dark"></i>पासवर्ड भूल गए?
                        </a>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-navy w-100 font-hindi fw-bold py-2.5 rounded-3 shadow-sm d-flex align-items-center justify-content-center gap-2 fs-6">
                        <span>लॉगिन करें</span>
                        <i class="fa-solid fa-arrow-right text-gold-custom"></i>
                    </button>
                </form>

                <script>
                function autoFillRoleCredentials() {
                    const ident = <?php echo json_encode($meta['default_ident'] ?? ''); ?>;
                    const pass = <?php echo json_encode($meta['default_pass'] ?? ''); ?>;
                    const passInput = document.getElementById('password');
                    const identInput = document.getElementById('identifier');
                    const btn = document.getElementById('btnAutofillPassword');
                    
                    if (passInput) {
                        passInput.value = pass;
                        passInput.classList.remove('is-invalid');
                        passInput.classList.add('is-valid');
                        passInput.focus();
                    }
                    
                    if (identInput && (!identInput.value.trim())) {
                        identInput.value = ident;
                        identInput.classList.remove('is-invalid');
                        identInput.classList.add('is-valid');
                    }
                    
                    if (btn) {
                        const origHtml = btn.innerHTML;
                        btn.innerHTML = '<i class="fa-solid fa-circle-check text-success me-1"></i>पासवर्ड भर दिया गया!';
                        btn.style.borderColor = '#198754';
                        btn.style.backgroundColor = '#ecfdf5';
                        
                        setTimeout(() => {
                            btn.innerHTML = origHtml;
                            btn.style.borderColor = '';
                            btn.style.backgroundColor = '';
                            if (passInput) passInput.classList.remove('is-valid');
                            if (identInput) identInput.classList.remove('is-valid');
                        }, 2000);
                    }
                }
                </script>

            </div>
        </div>
    </div>
<?php endif; ?>

<!-- ========================================================= -->
<!-- OFFICIAL LOGIN ASSISTANCE & HELPLINE MODAL                -->
<!-- ========================================================= -->
<div class="modal fade" id="loginHelpModal" tabindex="-1" aria-labelledby="loginHelpModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered font-hindi">
        <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
            <div class="modal-header bg-navy-custom text-white py-3 border-bottom border-gold-custom">
                <h5 class="modal-title fs-6 fw-bold text-white d-flex align-items-center gap-2" id="loginHelpModalLabel">
                    <i class="fa-solid fa-headset text-gold-custom"></i> लॉगिन एवं तकनीकी सहायता केंद्र
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-navy-custom">
                <div class="text-center mb-3">
                    <div class="d-inline-flex p-3 rounded-circle bg-light border border-gold-custom mb-2">
                        <i class="fa-solid fa-scale-balanced fs-2 text-gold-dark"></i>
                    </div>
                    <h6 class="fw-bold mb-1">जिला अधिवक्ता संघ, बांदा</h6>
                    <small class="text-muted">जनपद न्यायालय परिसर, बांदा (उ.प्र.) - २१०००१ | स्थापना: सन् 1937</small>
                </div>

                <div class="alert alert-light border rounded-3 p-3 mb-3 small">
                    <h6 class="fw-bold fs-6 text-navy-custom mb-1.5"><i class="fa-solid fa-circle-question text-gold-dark me-1.5"></i>पासवर्ड अथवा आईडी भूल गए हैं?</h6>
                    <p class="text-muted mb-2">
                        अधिवक्ता साथी अपनी सदस्यता संख्या (जैसे: <code>DBA-003</code>) बार पंजीकरण रसीद अथवा अपने अधिवक्ता परिचय पत्र से प्राप्त कर सकते हैं। इसके अतिरिक्त आप अपने बार में पंजीकृत 10-अंकीय मोबाइल नंबर से भी सीधे लॉगिन कर सकते हैं।
                    </p>
                    <p class="text-muted mb-0">
                        यदि आपका मोबाइल नंबर अपडेट नहीं है अथवा पासवर्ड रीसेट की आवश्यकता है, तो बार कार्यालय में व्यक्तिगत रूप से संपर्क कर तत्काल सहायता प्राप्त करें।
                    </p>
                </div>

                <div class="border rounded-3 p-3 bg-white">
                    <h6 class="fw-bold fs-6 text-navy-custom mb-2"><i class="fa-solid fa-phone-volume text-gold-dark me-1.5"></i>आधिकारिक संपर्क सूत्र:</h6>
                    <ul class="list-unstyled mb-0 small text-muted">
                        <li class="mb-2"><i class="fa-solid fa-phone text-navy-custom me-2"></i><strong>हेल्पलाइन:</strong> <a href="tel:05192220000" class="text-decoration-none text-navy-custom fw-semibold">05192-220000</a></li>
                        <li class="mb-2"><i class="fa-solid fa-envelope text-navy-custom me-2"></i><strong>ईमेल:</strong> <a href="mailto:info@dbabanda.in" class="text-decoration-none text-navy-custom fw-semibold">info@dbabanda.in</a></li>
                        <li class="mb-2"><i class="fa-solid fa-building text-navy-custom me-2"></i><strong>कार्यालय:</strong> कमरा नं. 14, जिला अधिवक्ता संघ भवन, सिविल कोर्ट बांदा</li>
                        <li><i class="fa-regular fa-clock text-navy-custom me-2"></i><strong>कार्यालय समय:</strong> प्रातः 10:00 बजे से सायं 05:00 बजे तक (सोमवार - शनिवार)</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer bg-light py-2.5">
                <button type="button" class="btn btn-navy btn-sm px-4 fw-semibold font-hindi" data-bs-dismiss="modal">ठीक है, समझ गया</button>
            </div>
        </div>
    </div>
</div>

<?php 
require_once 'includes/footer.php';
?>
