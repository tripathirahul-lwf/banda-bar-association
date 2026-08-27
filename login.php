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

<!-- Banner -->
<div class="bg-navy-custom text-white p-4 rounded-3 mb-4 shadow-sm border-bottom border-gold-custom">
    <div class="row align-items-center">
        <div class="col-md-8">
            <h1 class="h3 mb-1 font-hindi fw-bold text-gold-custom">डिजिटल लॉगिन पोर्टल (Login Portal)</h1>
            <p class="mb-0 text-light-custom english-text text-uppercase tracking-wider small">जिला अधिवक्ता संघ, बांदा की सुरक्षित ऑनलाइन प्रणालियों में प्रवेश करें</p>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <span class="badge bg-gold-custom text-navy-custom font-hindi px-3 py-2 fs-6">Estd. 1937</span>
        </div>
    </div>
</div>

<?php if ($selected_role === ''): ?>
    <!-- 1. ROLE SELECTION LANDING PAGE -->
    <div class="text-center mb-4">
        <span class="badge bg-gold-custom text-navy-custom font-hindi mb-2 px-3 py-1 fw-semibold">लॉगिन भूमिका चुनें (Choose Role)</span>
        <h3 class="text-navy-custom font-hindi fw-bold">अधिवक्ता संघ प्रबंधन एवं सेवा पोर्टल</h3>
        <hr class="border-gold-custom mx-auto" style="width: 80px; height: 3px; opacity: 1;">
    </div>
    
    <div class="row g-4 mb-5">
        <!-- Admin Card -->
        <div class="col-md-6 col-lg-3">
            <div class="card-custom h-100 p-4 text-center d-flex flex-column border-top border-4 border-danger">
                <div class="text-danger mb-3">
                    <i class="bi bi-shield-lock-fill display-4"></i>
                </div>
                <h5 class="fw-bold font-hindi text-navy-custom mb-2">प्रशासक (Admin)</h5>
                <p class="small text-muted font-hindi px-2 mb-4">
                    वेबसाइट प्रबन्धन, नोटिस जारी करने, सदस्यों के पंजीकरण तथा डेटाबेस संचालन हेतु पूर्ण नियंत्रण।
                </p>
                <div class="mt-auto">
                    <a href="login.php?role=admin" class="btn btn-navy w-100 font-hindi fw-semibold py-2">
                        प्रवेश करें <i class="bi bi-box-arrow-in-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- President Card -->
        <div class="col-md-6 col-lg-3">
            <div class="card-custom h-100 p-4 text-center d-flex flex-column border-top border-4 border-warning">
                <div class="text-warning mb-3">
                    <i class="bi bi-person-fill-check display-4"></i>
                </div>
                <h5 class="fw-bold font-hindi text-navy-custom mb-2">अध्यक्ष (President)</h5>
                <p class="small text-muted font-hindi px-2 mb-4">
                    संघ की कल्याणकारी योजनाओं, कोष आवंटन, कक्षों के आवंटन प्रस्तावों तथा वित्तीय स्वीकृति की निगरानी।
                </p>
                <div class="mt-auto">
                    <a href="login.php?role=president" class="btn btn-navy w-100 font-hindi fw-semibold py-2">
                        प्रवेश करें <i class="bi bi-box-arrow-in-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Mahasachiv Card -->
        <div class="col-md-6 col-lg-3">
            <div class="card-custom h-100 p-4 text-center d-flex flex-column border-top border-4 border-success">
                <div class="text-success mb-3">
                    <i class="bi bi-person-workspace display-4"></i>
                </div>
                <h5 class="fw-bold font-hindi text-navy-custom mb-2">महासचिव (Mahasachiv)</h5>
                <p class="small text-muted font-hindi px-2 mb-4">
                    दैनिक प्रशासनिक कार्यों, सदस्यता आवेदनों, चुनाव नामांकन समीक्षा एवं शिकायत निवारण पटल का प्रबन्धन।
                </p>
                <div class="mt-auto">
                    <a href="login.php?role=mahasachiv" class="btn btn-navy w-100 font-hindi fw-semibold py-2">
                        प्रवेश करें <i class="bi bi-box-arrow-in-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Bar Member Card -->
        <div class="col-md-6 col-lg-3">
            <div class="card-custom h-100 p-4 text-center d-flex flex-column border-top border-4 border-primary">
                <div class="text-primary mb-3">
                    <i class="bi bi-file-earmark-person display-4"></i>
                </div>
                <h5 class="fw-bold font-hindi text-navy-custom mb-2">संघ सदस्य (Bar Member)</h5>
                <p class="small text-muted font-hindi px-2 mb-4">
                    डिजिटल ID Card डाउनलोड करने, Wakalatnama प्राप्त करने, संघ वार्षिक शुल्क भुगतान एवं कक्ष किराए हेतु अनुरोध।
                </p>
                <div class="mt-auto">
                    <a href="login.php?role=member" class="btn btn-navy w-100 font-hindi fw-semibold py-2">
                        प्रवेश करें <i class="bi bi-box-arrow-in-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- 2. DEDICATED LOGIN FORM FOR THE SELECTED ROLE -->
    <div class="mb-4">
        <a href="login.php" class="btn btn-outline-navy btn-sm font-hindi fw-semibold">
            <i class="bi bi-arrow-left me-1"></i>भूमिका चयन पृष्ठ पर वापस जाएं (Change Role)
        </a>
    </div>

    <div class="row justify-content-center mb-5">
        <div class="col-lg-5 col-md-8 col-12">
            <div class="bg-white p-4 p-md-5 rounded-3 shadow-sm border border-light" style="border-top: 4px solid #D9B44A !important;">
                
                <div class="text-center mb-4">
                    <div class="logo-placeholder bg-navy-custom text-gold-custom d-inline-flex align-items-center justify-content-center rounded-circle mb-3" style="width: 60px; height: 60px;">
                        <i class="bi bi-shield-lock fs-3"></i>
                    </div>
                    <h3 class="fw-bold font-hindi text-navy-custom mb-1">सुरक्षित लॉगिन</h3>
                    <span class="badge bg-gold-custom text-navy-custom px-3 py-1 font-hindi text-uppercase fw-bold">
                        <?php 
                        if ($selected_role === 'admin') echo 'प्रशासक लॉगिन (Admin)';
                        elseif ($selected_role === 'president') echo 'अध्यक्ष लॉगिन (President)';
                        elseif ($selected_role === 'mahasachiv') echo 'महासचिव लॉगिन (General Secretary)';
                        elseif ($selected_role === 'member') echo 'संघ सदस्य लॉगिन (Advocate Member)';
                        ?>
                    </span>
                </div>

                <!-- SandBox Credentials Box (Safe for user local review) -->
                <div class="alert alert-info border-0 rounded-3 mb-4 font-hindi small">
                    <span class="fw-bold d-block text-navy-custom mb-1"><i class="bi bi-info-circle-fill me-1"></i>परीक्षण साख (Sandbox Credentials):</span>
                    <table class="table table-sm table-borderless mb-0 font-size-xs">
                        <tbody>
                            <tr>
                                <td>आईडी (Identifier):</td>
                                <td class="fw-bold english-text">
                                    <?php 
                                    if ($selected_role === 'admin') echo 'admin (username)';
                                    elseif ($selected_role === 'president') echo 'president (username)';
                                    elseif ($selected_role === 'mahasachiv') echo 'mahasachiv (username)';
                                    elseif ($selected_role === 'member') echo 'DBA-003 (Membership) OR 9876543210 (Mobile)';
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <td>पासवर्ड (Password):</td>
                                <td class="fw-bold english-text">
                                    <?php 
                                    if ($selected_role === 'admin') echo 'admin123';
                                    elseif ($selected_role === 'president') echo 'president123';
                                    elseif ($selected_role === 'mahasachiv') echo 'mahasachiv123';
                                    elseif ($selected_role === 'member') echo 'member123';
                                    ?>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <small class="text-muted d-block mt-2">Development seed only — change credentials before production.</small>
                </div>

                <form id="loginForm" method="POST" action="actions/login-action.php" novalidate>
                    <!-- CSRF Token & Role fields -->
                    <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrf_token); ?>">
                    <input type="hidden" name="role" value="<?php echo sanitize($selected_role); ?>">
                    
                    <!-- Identifier -->
                    <div class="mb-3">
                        <label for="identifier" class="form-label font-hindi small fw-semibold text-secondary">
                            <?php 
                            if ($selected_role === 'admin') echo 'यूज़रनेम या ईमेल (Username / Email)';
                            elseif ($selected_role === 'president') echo 'यूज़रनेम / मोबाइल / ईमेल (Username / Mobile / Email)';
                            elseif ($selected_role === 'mahasachiv') echo 'यूज़रनेम / मोबाइल / ईमेल (Username / Mobile / Email)';
                            elseif ($selected_role === 'member') echo 'सदस्यता संख्या या मोबाइल नंबर (Membership No / Mobile)';
                            ?>
                        </label>
                        <div class="input-group shadow-xs">
                            <span class="input-group-text bg-white border-end-0 text-muted" style="border-color: #ced4da;"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control border-start-0" id="identifier" name="identifier" placeholder="Enter details" style="border-color: #ced4da; box-shadow: none;">
                        </div>
                    </div>

                    <!-- Password -->
                    <div class="mb-4">
                        <label for="password" class="form-label font-hindi small fw-semibold text-secondary">पासवर्ड (Password)</label>
                        <div class="input-group shadow-xs">
                            <span class="input-group-text bg-white border-end-0 text-muted" style="border-color: #ced4da;"><i class="bi bi-key"></i></span>
                            <input type="password" class="form-control border-start-0 border-end-0" id="password" name="password" placeholder="Enter password" style="border-color: #ced4da; box-shadow: none;">
                            <span class="input-group-text bg-white border-start-0 text-muted" id="togglePasswordBtn" style="border-color: #ced4da; cursor: pointer;">
                                <i class="bi bi-eye-slash" id="togglePasswordIcon"></i>
                            </span>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-navy w-100 font-hindi fw-semibold py-2">
                        लॉगिन करें (Secure Sign-In) <i class="bi bi-box-arrow-in-right ms-2 text-gold-custom"></i>
                    </button>
                </form>

            </div>
        </div>
    </div>
<?php endif; ?>

<?php 
require_once 'includes/footer.php';
?>
