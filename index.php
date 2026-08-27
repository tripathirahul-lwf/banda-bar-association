<?php
/**
 * Homepage - District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'मुख्य पृष्ठ (Home)';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
require_once 'config/database.php';

// Try to fetch latest active notices from database, otherwise fallback to mock data
$db = Database::getConnection();
$latest_notices = [];
$ticker_notice = null;
$active_election = null;

if ($db) {
    try {
        // Query active public election (Phase 10)
        $elec_stmt = $db->query("
            SELECT * FROM elections 
            WHERE status NOT IN ('completed', 'archived', 'draft', 'cancelled') AND visibility = 'public' 
            ORDER BY id DESC LIMIT 1
        ");
        $active_election = $elec_stmt->fetch();
        $stmt = $db->query("
            SELECT * FROM notices 
            WHERE status = 'published' AND publish_at <= CURRENT_TIMESTAMP() AND (expire_at IS NULL OR expire_at >= CURRENT_TIMESTAMP()) 
            ORDER BY 
                CASE priority 
                    WHEN 'urgent' THEN 1 
                    WHEN 'important' THEN 2 
                    ELSE 3 
                END, 
                published_at DESC, id DESC 
            LIMIT 3
        ");
        $latest_notices = $stmt->fetchAll();

        // Fetch latest ticker notice (urgent or important only)
        $ticker_stmt = $db->query("
            SELECT title, slug FROM notices 
            WHERE status = 'published' AND priority IN ('important', 'urgent') AND publish_at <= CURRENT_TIMESTAMP() AND (expire_at IS NULL OR expire_at >= CURRENT_TIMESTAMP())
            ORDER BY published_at DESC LIMIT 1
        ");
        $ticker_notice = $ticker_stmt->fetch();
    } catch (PDOException $e) {
        error_log("Failed to fetch notices for home: " . $e->getMessage());
    }
}

// Fallback notices if database is not set up yet
if (empty($latest_notices)) {
    $latest_notices = [
        [
            'id' => 1,
            'title' => 'अधिवक्ता कार्यकारिणी बैठक के संबंध में आवश्यक सूचना',
            'category' => 'Meeting Notice',
            'published_at' => '2026-08-20',
            'description' => 'संघ के सभी सम्मानित सदस्यों को सूचित किया जाता है कि दिनांक 25 अगस्त 2026 को दोपहर 1:00 बजे सभागार में कार्यकारिणी बैठक आहूत की गई है।',
            'priority' => 'High'
        ],
        [
            'id' => 2,
            'title' => 'बार काउंसिल उत्तर प्रदेश द्वारा सदस्यता सत्यापन अभियान',
            'category' => 'Important Notice',
            'published_at' => '2026-08-18',
            'description' => 'सभी सदस्य अपने सीओपी (COP) विवरण एवं पंजीकरण प्रपत्र कार्यालय में ३१ अगस्त तक जमा करें जिससे सत्यापन प्रक्रिया समय पर पूर्ण हो सके।',
            'priority' => 'Normal'
        ],
        [
            'id' => 3,
            'title' => 'शोक संदेश: वरिष्ठ अधिवक्ता श्री राम सजीवन गुप्ता जी का निधन',
            'category' => 'Condolence Notice',
            'published_at' => '2026-08-15',
            'description' => 'हमारे संघ के वरिष्ठ सदस्य श्री राम सजीवन गुप्ता जी का दिनांक 15 अगस्त को दुःखद निधन हो गया है। उनके सम्मान में बार एसोसिएशन शोक सभा आयोजित करेगी।',
            'priority' => 'High',
            'deceased_name' => 'स्व० राम सजीवन गुप्ता',
            'deceased_photo' => 'default_advocate.png'
        ]
    ];
}

// Fetch active office bearers for home page (Phase 11)
$active_term = null;
$home_bearers = [];
$president_message_bearer = null;

if ($db) {
    try {
        $t_stmt = $db->query("SELECT * FROM office_bearer_terms WHERE status = 'active' LIMIT 1");
        $active_term = $t_stmt->fetch();

        if ($active_term) {
            $b_stmt = $db->prepare("
                SELECT ob.*, p.position_name, p.position_name_hindi, p.code AS position_code
                FROM office_bearers ob
                JOIN office_bearer_positions p ON p.id = ob.position_id
                WHERE ob.term_id = ? AND ob.status = 'active' AND p.code IN ('PRESIDENT', 'MAHASACHIV', 'VICE_PRESIDENT', 'TREASURER')
                ORDER BY p.display_order ASC, ob.display_order ASC, ob.id ASC
            ");
            $b_stmt->execute([$active_term['id']]);
            $home_bearers = $b_stmt->fetchAll();
            
            // Find President's message
            foreach ($home_bearers as $hb) {
                if ($hb['position_code'] === 'PRESIDENT' && !empty($hb['message'])) {
                    $president_message_bearer = $hb;
                }
            }
        }
    } catch (PDOException $e) {
        error_log("Failed to load home bearers: " . $e->getMessage());
    }
}
?>

<!-- Latest Important Notice Ticker -->
<?php if ($ticker_notice): ?>
    <div class="alert alert-warning border-0 rounded-3 mb-4 shadow-sm py-2 px-3 font-hindi small d-flex align-items-center gap-2">
        <span class="badge bg-danger text-white px-2 py-1 fw-bold text-uppercase"><i class="bi bi-exclamation-circle-fill me-1"></i>महत्वपूर्ण सूचना</span>
        <div class="text-navy-custom flex-grow-1 text-truncate">
            <a href="notice.php?slug=<?php echo urlencode($ticker_notice['slug']); ?>" class="text-navy-custom fw-semibold text-decoration-underline">
                <?php echo e($ticker_notice['title']); ?>
            </a>
        </div>
        <a href="notice.php?slug=<?php echo urlencode($ticker_notice['slug']); ?>" class="btn btn-xs btn-navy py-0.5">पढ़ें</a>
    </div>
<?php endif; ?>

<!-- Active Election Section (Phase 10) -->
<?php if ($active_election && getSetting('election_section_visibility', '1') === '1'): ?>
    <div class="alert alert-dark border-0 rounded-3 mb-4 shadow-sm p-3 font-hindi small text-navy-custom d-flex flex-wrap align-items-center justify-content-between gap-3 border-start border-3 border-warning" style="background: #fcf8e3 !important;">
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-gold-custom text-navy-custom px-2 py-1 fw-bold text-uppercase"><i class="bi bi-check2-square me-1"></i>संघ चुनाव</span>
            <div>
                <strong class="d-block text-navy-custom fs-6"><?php echo e($active_election['title']); ?></strong>
                <span class="text-muted" style="font-size:0.75rem;">मतदान तिथि: <?php echo date('d-m-Y', strtotime($active_election['election_date'])); ?> | वर्तमान स्थिति स्टेज: <strong class="text-danger"><?php echo e(ucfirst(str_replace('_', ' ', $active_election['status']))); ?></strong></span>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="election.php" class="btn btn-xs btn-navy py-1 px-3 fw-semibold">विवरण (Details)</a>
            <a href="election.php" class="btn btn-xs btn-outline-navy py-1 px-3 fw-semibold">मतदाता खोजें</a>
        </div>
    </div>
<?php endif; ?>

<!-- 1. Institutional Hero Section -->
<div class="p-5 mb-4 text-white rounded-3 hero-banner shadow-sm">
    <div class="container-fluid py-4 text-center text-lg-start">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <span class="badge bg-gold-custom text-navy-custom mb-3 px-3 py-2 fw-bold text-uppercase tracking-wider">
                    <i class="bi bi-shield-fill-check me-1"></i> आधिकारिक वेब पोर्टल (Official Web Portal)
                </span>
                <h1 class="display-5 fw-bold text-white mb-3 hindi-text"><?php echo e(getSetting('hero_heading', 'न्याय, एकता और अधिवक्ता हित')); ?></h1>
                <p class="col-lg-11 fs-5 text-light-custom font-hindi mb-4" style="line-height: 1.7; font-weight: 300;">
                    <?php echo e(getSetting('hero_description', 'जिला अधिवक्ता संघ, बांदा की आधिकारिक डिजिटल व्यवस्था। सदस्य सेवाएँ, सूचनाएँ, डिजिटल ID Card, Wakalatnama, शुल्क, कक्ष/रूम प्रबंधन और संघीय जानकारी एक ही स्थान पर।')); ?>
                </p>
                <div class="d-flex flex-wrap justify-content-center justify-content-lg-start gap-3">
                    <a href="<?php echo SITE_URL; ?>/notices.php" class="btn btn-gold btn-lg px-4 py-3 fw-semibold">
                        <i class="bi bi-bell-fill me-2"></i><?php echo e(getSetting('primary_cta', 'नवीनतम सूचनाएं / Notices')); ?>
                    </a>
                    <a href="<?php echo SITE_URL; ?>/login.php" class="btn btn-outline-light btn-lg px-4 py-3 fw-semibold">
                        <i class="bi bi-person-lock me-2"></i><?php echo e(getSetting('secondary_cta', 'सदस्य लॉगिन / Login')); ?>
                    </a>
                </div>
            </div>
            <div class="col-lg-4 d-none d-lg-block text-center position-relative">
                <!-- Large Decorative Emblem Icon (Requirement 53 - Subtle background styling and pointer-events disabled) -->
                <i class="bi bi-bank text-white" style="font-size: 15rem; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); opacity: 0.05; z-index: 1; pointer-events: none;"></i>
                <div class="p-4 border border-gold-custom border-2 rounded-3 text-center bg-white bg-opacity-10 backdrop-blur" style="max-width: 320px; margin: 0 auto; position: relative; z-index: 2;">
                    <i class="bi bi-briefcase-fill text-gold-custom display-4 mb-2"></i>
                    <h5 class="fw-bold text-gold-custom font-hindi mb-1">विधिक व्यवस्था</h5>
                    <p class="small text-light-custom mb-0 font-hindi">न्याय व सत्य के प्रति निष्ठा और अधिवक्ता कल्याण का संकल्प।</p>
                    <hr class="border-gold-custom my-3">
                    <div class="small english-text text-uppercase fw-semibold tracking-wider text-light">Since <?php echo e(getSetting('established_year', '1937')); ?> | Banda</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 2. Institutional Stats Section -->
<?php if (getSetting('association_statistics_visibility', '1') === '1'): ?>
<div class="row g-3 mb-5">
    <div class="col-md-3 col-sm-6">
        <div class="card-custom p-4 text-center h-100 card-stat">
            <div class="text-navy-custom mb-2">
                <i class="bi bi-calendar3 fs-1"></i>
            </div>
            <h3 class="fw-bold text-navy-custom mb-1 font-hindi"><?php echo e(getSetting('established_year', '१९३७')); ?></h3>
            <p class="text-muted small mb-0 fw-semibold">स्थापना वर्ष (Estd. Year)</p>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card-custom p-4 text-center h-100 card-stat">
            <div class="text-navy-custom mb-2">
                <i class="bi bi-qr-code-scan fs-1"></i>
            </div>
            <h3 class="fw-bold text-navy-custom mb-1 font-hindi">Digital ID</h3>
            <p class="text-muted small mb-0 fw-semibold">QR Verification सहित</p>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card-custom p-4 text-center h-100 card-stat">
            <div class="text-navy-custom mb-2">
                <i class="bi bi-file-earmark-check fs-1"></i>
            </div>
            <h3 class="fw-bold text-navy-custom mb-1 font-hindi">Wakalatnama</h3>
            <p class="text-muted small mb-0 fw-semibold">केवल Registered Members</p>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card-custom p-4 text-center h-100 card-stat">
            <div class="text-navy-custom mb-2">
                <i class="bi bi-shield-lock-fill fs-1"></i>
            </div>
            <h3 class="fw-bold text-navy-custom mb-1 font-hindi">4 Logins</h3>
            <p class="text-muted small mb-0 fw-semibold">Admin • Pres • Secy • Member</p>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-4 mb-5">
    <!-- 3. About Section Preview -->
    <div class="col-lg-7">
        <div class="bg-white p-4 p-md-5 rounded-3 shadow-sm border border-light h-100">
            <span class="text-gold-dark fw-bold text-uppercase tracking-wider small d-block mb-2 font-hindi">
                <i class="bi bi-journal-bookmark-fill me-1"></i> संघ की गौरवशाली यात्रा
            </span>
            <h2 class="mb-3 text-navy-custom font-hindi fw-bold"><?php echo e(getSetting('association_name_hi', 'जिला अधिवक्ता संघ, बांदा')); ?> (परिचय)</h2>
            <hr class="border-gold-custom my-3" style="width: 80px; height: 3px; opacity: 1;">
            
            <p class="text-dark-custom mb-4 font-hindi lead fs-6" style="line-height: 1.8; white-space: pre-wrap;"><?php 
                echo e(getSetting('about_preview', 'जिला अधिवक्ता संघ, बांदा — स्थापना वर्ष 1937। इस पोर्टल का उद्देश्य संघ की सूचनाओं, सदस्य सेवाओं और प्रशासनिक कार्यों को व्यवस्थित एवं डिजिटल बनाना है।')); 
            ?></p>
            
            <a href="<?php echo SITE_URL; ?>/about.php" class="btn btn-navy px-4 py-2">
                विस्तृत जानकारी पढ़ें (Read More) <i class="bi bi-arrow-right-short ms-1"></i>
            </a>
        </div>
    </div>

    <!-- 4. Notice Board Preview -->
    <div class="col-lg-5">
        <div class="bg-white p-4 rounded-3 shadow-sm border border-light h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0 text-navy-custom font-hindi fw-bold">
                    <i class="bi bi-chat-square-text-fill text-gold-custom me-2"></i>सूचना पट्ट (Notice Board)
                </h4>
                <a href="<?php echo SITE_URL; ?>/notices.php" class="btn btn-xs btn-outline-navy fw-semibold">सभी देखें</a>
            </div>
            <hr class="border-gold-custom my-2">
            
            <div class="d-flex flex-column gap-3 mt-3">
                <?php foreach ($latest_notices as $notice): ?>
                    <?php 
                    $isCondolence = ($notice['category'] === 'Condolence Notice');
                    
                    $priorityBadgeClass = 'bg-secondary text-white';
                    if ($notice['priority'] === 'urgent') {
                        $priorityBadgeClass = 'bg-danger text-white';
                    } elseif ($notice['priority'] === 'important') {
                        $priorityBadgeClass = 'bg-warning text-dark';
                    }

                    $catIcon = 'bi-bell';
                    if ($notice['category'] === 'Meeting Notice') $catIcon = 'bi-people';
                    if ($notice['category'] === 'Important Notice') $catIcon = 'bi-exclamation-octagon';
                    if ($isCondolence) $catIcon = 'bi-flower1';
                    ?>
                    
                    <div class="p-3 rounded border <?php echo $isCondolence ? 'border-dark bg-light' : 'border-light bg-light-custom'; ?> position-relative">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge <?php echo $priorityBadgeClass; ?> font-size-xs px-2 py-1">
                                <?php echo sanitize($notice['priority']); ?>
                            </span>
                            <small class="text-muted font-hindi"><i class="bi bi-calendar3 me-1"></i><?php echo date('d-m-Y', strtotime($notice['published_at'] ?: $notice['created_at'])); ?></small>
                        </div>
                        <h6 class="fw-bold mb-1 font-hindi text-navy-custom <?php echo $isCondolence ? 'text-decoration-underline' : ''; ?>">
                            <i class="bi <?php echo $catIcon; ?> text-gold-custom me-1"></i><?php echo sanitize($notice['title']); ?>
                        </h6>
                        <p class="small text-muted mb-2 text-truncate font-hindi">
                            <?php echo sanitize($notice['short_description'] ?: $notice['description']); ?>
                        </p>
                        <div class="text-end">
                            <a href="notice.php?slug=<?php echo urlencode($notice['slug']); ?>" class="text-navy-custom small fw-semibold text-decoration-none">
                                पूर्ण विवरण पढ़ें (Details) <i class="bi bi-chevron-right small"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- President's Message homepage widget (Phase 11) -->
<?php if ($president_message_bearer && getSetting('president_message_visibility', '1') === '1'): ?>
    <div class="card border-0 shadow-sm mb-5 font-hindi text-navy-custom">
        <div class="card-header bg-navy-custom text-white py-2">
            <h6 class="mb-0 fw-bold"><i class="bi bi-chat-left-quote text-gold-custom me-2"></i>अध्यक्ष की कलम से (President's Message)</h6>
        </div>
        <div class="card-body p-4 bg-light bg-opacity-50">
            <div class="row align-items-center g-4">
                <div class="col-md-3 text-center">
                    <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $president_message_bearer['photo_snapshot'] ?: 'profile/default_advocate.png'; ?>" class="rounded border border-gold-custom border-2 mb-2" style="width: 100px; height: 100px; object-fit: cover;" onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default_advocate.png'">
                    <h6 class="fw-bold mb-0 text-navy-custom"><?php echo e($president_message_bearer['display_name_snapshot']); ?></h6>
                    <span class="badge bg-gold-custom text-navy-custom font-size-xs px-2 py-0.5" style="font-size: 0.68rem;"><?php echo e($active_term['title']); ?></span>
                </div>
                <div class="col-md-9 border-start border-light-custom">
                    <p class="mb-3 lead fs-6" style="line-height:1.7; font-style: italic;">
                        "<?php echo e($president_message_bearer['message']); ?>"
                    </p>
                    <a href="office-bearers.php" class="btn btn-navy btn-xs py-1 px-3">विस्तृत संदेश पढ़ें</a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- 5. Current Office Bearers Preview Section -->
<?php if (getSetting('office_bearers_visibility', '1') === '1'): ?>
<div class="mb-5 font-hindi small text-navy-custom">
    <div class="text-center mb-4">
        <span class="badge bg-gold-custom text-navy-custom mb-2 px-3 py-1 font-hindi fw-semibold">
            <?php echo $active_term ? e($active_term['title']) : 'सत्र विवरण'; ?>
        </span>
        <h2 class="text-navy-custom font-hindi fw-bold">मुख्य संघ पदाधिकारी (Office Bearers)</h2>
        <p class="text-muted small">संघ के प्रशासनिक एवं विधिक हितों के संरक्षण हेतु कार्यरत मुख्य कार्यकारिणी सदस्य</p>
        <hr class="border-gold-custom mx-auto" style="width: 100px; height: 3px; opacity: 1;">
    </div>
    
    <div class="row g-4">
        <?php if (empty($home_bearers)): ?>
            <div class="col-12 text-center text-muted py-4">वर्तमान कार्यकारिणी विवरण शीघ्र उपलब्ध होगा।</div>
        <?php else: ?>
            <?php foreach ($home_bearers as $bearer): ?>
                <div class="col-lg-3 col-md-6">
                    <div class="card-custom h-100 card-bearer text-center p-3">
                        <div class="mb-3 mx-auto rounded-circle overflow-hidden bg-light d-flex align-items-center justify-content-center border border-gold-custom border-2" style="width: 120px; height: 120px;">
                            <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $bearer['photo_snapshot'] ?: 'profile/default_advocate.png'; ?>" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default_advocate.png'">
                        </div>
                        <h5 class="fw-bold mb-1 text-navy-custom small-heading english-text fs-6"><?php echo e($bearer['display_name_snapshot']); ?></h5>
                        <p class="badge bg-gold-custom text-navy-custom fw-semibold font-hindi mb-2 px-3 py-1 font-size-xs" style="font-size: 0.72rem;"><?php echo e($bearer['designation_override'] ?: $bearer['position_name_hindi']); ?></p>
                        <div class="border-top border-light pt-2 mt-auto">
                            <small class="text-muted font-hindi fw-semibold"><i class="bi bi-clock me-1"></i>कार्यकाल: <?php echo e($active_term['title']); ?></small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div class="text-center mt-4">
        <a href="<?php echo SITE_URL; ?>/office-bearers.php" class="btn btn-outline-navy px-4">
            सभी कार्यकारिणी सदस्यों को देखें (View All Bearers) <i class="bi bi-people-fill ms-2"></i>
        </a>
    </div>
</div>
<?php endif; ?>

<?php 
require_once 'includes/footer.php';
?>
