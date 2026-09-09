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
    <div class="ticker-container py-2.5 px-3 mb-4 shadow-sm font-hindi small d-flex align-items-center gap-3">
        <span class="badge bg-danger text-white px-2.5 py-1.5 fw-bold text-uppercase pulse-badge d-inline-flex align-items-center gap-1.5" style="letter-spacing: 0.5px; font-size: 0.75rem;">
            <i class="fa-solid fa-bullhorn"></i> महत्वपूर्ण सूचना
        </span>
        <div class="text-navy-custom flex-grow-1 text-truncate fw-semibold">
            <a href="notice.php?slug=<?php echo urlencode($ticker_notice['slug']); ?>" class="text-navy-custom text-decoration-none">
                <?php echo e($ticker_notice['title']); ?>
            </a>
        </div>
        <a href="notice.php?slug=<?php echo urlencode($ticker_notice['slug']); ?>" class="btn btn-sm btn-navy py-1 px-3 fw-semibold rounded-pill text-nowrap" style="font-size: 0.78rem;">
            पढ़ें <i class="fa-solid fa-arrow-right ms-1"></i>
        </a>
    </div>
<?php endif; ?>

<!-- Active Election Section (Phase 10) -->
<?php if ($active_election && getSetting('election_section_visibility', '1') === '1'): ?>
    <div class="alert alert-dark border-0 rounded-3 mb-4 shadow-sm p-3 font-hindi small text-navy-custom d-flex flex-wrap align-items-center justify-content-between gap-3 border-start border-4 border-warning" style="background: #fcf8e3 !important;">
        <div class="d-flex align-items-center gap-3">
            <span class="badge bg-gold-custom text-navy-custom px-2.5 py-1.5 fw-bold text-uppercase"><i class="fa-solid fa-square-poll-vertical me-1"></i>संघ चुनाव</span>
            <div>
                <strong class="d-block text-navy-custom fs-6"><?php echo e($active_election['title']); ?></strong>
                <span class="text-muted" style="font-size:0.78rem;">मतदान तिथि: <?php echo date('d-m-Y', strtotime($active_election['election_date'])); ?> | वर्तमान स्थिति: <strong class="text-danger"><?php echo e(ucfirst(str_replace('_', ' ', $active_election['status']))); ?></strong></span>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="election.php" class="btn btn-sm btn-navy py-1 px-3 fw-semibold">विवरण (Details)</a>
            <a href="election.php" class="btn btn-sm btn-outline-navy py-1 px-3 fw-semibold">मतदाता खोजें</a>
        </div>
    </div>
<?php endif; ?>

<!-- 1. Institutional Hero Section -->
<div class="p-4 p-lg-5 mb-4 text-white rounded-3 hero-banner shadow-sm">
    <div class="container-fluid py-2 text-center text-lg-start">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <span class="badge bg-gold-custom text-navy-custom mb-3 px-3 py-2 fw-bold text-uppercase tracking-wider shadow-sm d-inline-flex align-items-center gap-1.5" style="border: 1px solid rgba(255,255,255,0.3); font-size: 0.8rem;">
                    <i class="fa-solid fa-shield-halved text-navy-custom"></i> आधिकारिक वेब पोर्टल (Official Web Portal)
                </span>
                <h1 class="display-5 fw-bold text-white mb-3 hindi-text" style="line-height: 1.25;">
                    <?php echo e(getSetting('hero_heading', 'जिला अधिवक्ता संघ, बांदा में आपका स्वागत है')); ?>
                </h1>
                <p class="col-lg-11 fs-5 text-light-custom font-hindi mb-4" style="line-height: 1.7; font-weight: 300;">
                    <?php echo e(getSetting('hero_description', '1937 से न्याय, सत्य और अधिवक्ता एकता का प्रतीक। जिला अधिवक्ता संघ, बांदा की आधिकारिक डिजिटल व्यवस्था। सदस्य सेवाएँ, सूचनाएँ, डिजिटल ID Card, Wakalatnama, शुल्क एवं कक्ष प्रबंधन एक ही स्थान पर।')); ?>
                </p>
                <div class="d-flex flex-wrap justify-content-center justify-content-lg-start gap-3">
                    <a href="<?php echo SITE_URL; ?>/members.php" class="btn btn-gold btn-lg px-4 py-2.5 fw-bold shadow-sm d-inline-flex align-items-center gap-2 rounded-3">
                        <i class="fa-solid fa-users-viewfinder"></i> <?php echo e(getSetting('primary_cta', 'सदस्यता खोजें')); ?>
                    </a>
                    <a href="<?php echo SITE_URL; ?>/notices.php" class="btn btn-outline-light btn-lg px-4 py-2.5 fw-semibold d-inline-flex align-items-center gap-2 rounded-3" style="border-width: 1.5px;">
                        <i class="fa-solid fa-bell text-gold-custom"></i> <?php echo e(getSetting('secondary_cta', 'महत्वपूर्ण सूचनाएं')); ?>
                    </a>
                </div>
            </div>
            <div class="col-lg-4 d-none d-lg-block text-center position-relative">
                <div class="p-4 crest-card text-center text-white position-relative" style="max-width: 320px; margin: 0 auto;">
                    <div class="mb-3 mx-auto rounded-circle overflow-hidden shadow-lg p-1 bg-white bg-opacity-10 d-inline-block" style="border: 2px solid var(--gold-accent);">
                        <img src="<?php echo SITE_URL; ?>/assets/images/logo.png" alt="DBA Banda Seal" style="width: 115px; height: 115px; object-fit: cover;" class="rounded-circle" onerror="this.src='<?php echo SITE_URL; ?>/assets/images/logo.svg'">
                    </div>
                    <h5 class="fw-bold text-gold-custom font-hindi mb-1 fs-5">विधिक व्यवस्था एवं एकता</h5>
                    <p class="small text-light-custom mb-2 font-hindi" style="line-height: 1.5;">न्याय व सत्य के प्रति निष्ठा और अधिवक्ता कल्याण का संकल्प।</p>
                    <hr class="border-gold-custom my-2.5 opacity-50">
                    <div class="d-flex justify-content-between align-items-center text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 1px;">
                        <span class="text-gold-custom"><i class="fa-solid fa-calendar-check me-1"></i>SINCE 1937</span>
                        <span class="text-white-50">• BANDA (U.P.)</span>
                    </div>
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
                <i class="fa-solid fa-landmark-dome fs-1 text-gold-dark"></i>
            </div>
            <h3 class="fw-bold text-navy-custom mb-1 font-hindi"><?php echo e(getSetting('established_year', '१९३७')); ?></h3>
            <p class="text-muted small mb-0 fw-semibold">स्थापना वर्ष (Estd. Year)</p>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card-custom p-4 text-center h-100 card-stat">
            <div class="text-navy-custom mb-2">
                <i class="fa-solid fa-id-card-clip fs-1 text-gold-dark"></i>
            </div>
            <h3 class="fw-bold text-navy-custom mb-1 font-hindi">Digital ID</h3>
            <p class="text-muted small mb-0 fw-semibold">QR Verification सहित</p>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card-custom p-4 text-center h-100 card-stat">
            <div class="text-navy-custom mb-2">
                <i class="fa-solid fa-file-contract fs-1 text-gold-dark"></i>
            </div>
            <h3 class="fw-bold text-navy-custom mb-1 font-hindi">Wakalatnama</h3>
            <p class="text-muted small mb-0 fw-semibold">अधिकृत डिजिटल प्रारूप</p>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card-custom p-4 text-center h-100 card-stat">
            <div class="text-navy-custom mb-2">
                <i class="fa-solid fa-users-gear fs-1 text-gold-dark"></i>
            </div>
            <h3 class="fw-bold text-navy-custom mb-1 font-hindi">4 Role Portals</h3>
            <p class="text-muted small mb-0 fw-semibold">Admin • Pres • Secy • Member</p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Core Digital Services Showcase -->
<div class="mb-5 font-hindi">
    <div class="d-flex justify-content-between align-items-end mb-4 border-bottom pb-2">
        <div>
            <span class="text-gold-dark fw-bold text-uppercase tracking-wider small d-block mb-1">
                <i class="fa-solid fa-laptop-file me-1"></i> अधिवक्ता डिजिटल सुविधाएं
            </span>
            <h3 class="mb-0 text-navy-custom fw-bold">प्रमुख डिजिटल सेवाएं (Core Digital Services)</h3>
        </div>
        <span class="text-muted small d-none d-md-inline">पंजीकृत अधिवक्ताओं के लिए सुगम डिजिटल व्यवस्था</span>
    </div>
    <div class="row g-4">
        <!-- 1. Digital ID Card -->
        <div class="col-lg-4 col-md-6">
            <a href="<?php echo SITE_URL; ?>/id-card.php" class="text-decoration-none">
                <div class="service-box">
                    <div class="icon-wrap">
                        <i class="fa-solid fa-id-card"></i>
                    </div>
                    <h5 class="fw-bold text-navy-custom mb-2">डिजिटल अधिवक्ता पहचान पत्र</h5>
                    <p class="text-muted small mb-3 flex-grow-1">
                        क्यूआर (QR) कोड युक्त आधिकारिक पहचान पत्र हेतु ऑनलाइन आवेदन, स्थिति जांच व डिजिटल सत्यापन।
                    </p>
                    <div class="text-gold-dark fw-semibold small d-flex align-items-center gap-1">
                        आवेदन / सत्यापन करें <i class="fa-solid fa-arrow-right-long small"></i>
                    </div>
                </div>
            </a>
        </div>

        <!-- 2. Wakalatnama -->
        <div class="col-lg-4 col-md-6">
            <a href="<?php echo SITE_URL; ?>/wakalatnama.php" class="text-decoration-none">
                <div class="service-box">
                    <div class="icon-wrap">
                        <i class="fa-solid fa-file-signature"></i>
                    </div>
                    <h5 class="fw-bold text-navy-custom mb-2">आधिकारिक वकालतनामा</h5>
                    <p class="text-muted small mb-3 flex-grow-1">
                        बार एसोसिएशन द्वारा अधिकृत वकालतनामा प्रारूपों को सदस्य लॉगिन के माध्यम से तुरंत डाउनलोड करें।
                    </p>
                    <div class="text-gold-dark fw-semibold small d-flex align-items-center gap-1">
                        प्रारूप डाउनलोड करें <i class="fa-solid fa-arrow-right-long small"></i>
                    </div>
                </div>
            </a>
        </div>

        <!-- 3. Chambers & Room Rent -->
        <div class="col-lg-4 col-md-6">
            <a href="<?php echo SITE_URL; ?>/rooms.php" class="text-decoration-none">
                <div class="service-box">
                    <div class="icon-wrap">
                        <i class="fa-solid fa-door-open"></i>
                    </div>
                    <h5 class="fw-bold text-navy-custom mb-2">कक्ष/चैंबर प्रबंधन</h5>
                    <p class="text-muted small mb-3 flex-grow-1">
                        बार परिसर में स्थित चैंबर आवंटन, मासिक किराया देयता, रसीदें और रिक्तता स्थिति की जानकारी।
                    </p>
                    <div class="text-gold-dark fw-semibold small d-flex align-items-center gap-1">
                        चैंबर विवरण देखें <i class="fa-solid fa-arrow-right-long small"></i>
                    </div>
                </div>
            </a>
        </div>

        <!-- 4. Bar Elections -->
        <div class="col-lg-4 col-md-6">
            <a href="<?php echo SITE_URL; ?>/election.php" class="text-decoration-none">
                <div class="service-box">
                    <div class="icon-wrap">
                        <i class="fa-solid fa-square-poll-vertical"></i>
                    </div>
                    <h5 class="fw-bold text-navy-custom mb-2">संघ चुनाव व्यवस्था</h5>
                    <p class="text-muted small mb-3 flex-grow-1">
                        वार्षिक चुनाव अधिसूचना, पात्र मतदाता सूची, प्रत्याशी विवरण एवं चुनाव परिणामों का पारदर्शी ब्यौरा।
                    </p>
                    <div class="text-gold-dark fw-semibold small d-flex align-items-center gap-1">
                        चुनाव पोर्टल पर जाएं <i class="fa-solid fa-arrow-right-long small"></i>
                    </div>
                </div>
            </a>
        </div>

        <!-- 5. Association Fund -->
        <div class="col-lg-4 col-md-6">
            <a href="<?php echo SITE_URL; ?>/association-fund.php" class="text-decoration-none">
                <div class="service-box">
                    <div class="icon-wrap">
                        <i class="fa-solid fa-building-columns"></i>
                    </div>
                    <h5 class="fw-bold text-navy-custom mb-2">संघीय कल्याण कोष</h5>
                    <p class="text-muted small mb-3 flex-grow-1">
                        अधिवक्ता कल्याणकारी योजनाएं, वित्तीय पारदर्शिता, सार्वजनिक अंशदान एवं सहयोग विवरण।
                    </p>
                    <div class="text-gold-dark fw-semibold small d-flex align-items-center gap-1">
                        कोष विवरण देखें <i class="fa-solid fa-arrow-right-long small"></i>
                    </div>
                </div>
            </a>
        </div>

        <!-- 6. Bar Fees Management -->
        <div class="col-lg-4 col-md-6">
            <a href="<?php echo SITE_URL; ?>/advocate-fee.php" class="text-decoration-none">
                <div class="service-box">
                    <div class="icon-wrap">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                    <h5 class="fw-bold text-navy-custom mb-2">अधिवक्ता शुल्क (Bar Fee)</h5>
                    <p class="text-muted small mb-3 flex-grow-1">
                        सदस्यता शुल्क, वार्षिक देयता, ऑनलाइन रसीदें एवं खाता स्थिति की तुरंत जांच करें।
                    </p>
                    <div class="text-gold-dark fw-semibold small d-flex align-items-center gap-1">
                        शुल्क स्थिति जांचें <i class="fa-solid fa-arrow-right-long small"></i>
                    </div>
                </div>
            </a>
        </div>
    </div>
</div>

<div class="row g-4 mb-5">
    <!-- 3. About Section Preview -->
    <div class="col-lg-7">
        <div class="bg-white p-4 p-md-5 rounded-3 shadow-sm border border-light h-100 font-hindi">
            <span class="text-gold-dark fw-bold text-uppercase tracking-wider small d-block mb-2">
                <i class="fa-solid fa-landmark me-1"></i> संघ की गौरवशाली यात्रा
            </span>
            <h2 class="mb-3 text-navy-custom fw-bold"><?php echo e(getSetting('association_name_hi', 'जिला अधिवक्ता संघ, बांदा')); ?> (परिचय)</h2>
            <hr class="border-gold-custom my-3" style="width: 80px; height: 3px; opacity: 1;">
            
            <p class="text-dark-custom mb-3 lead fs-6" style="line-height: 1.8; white-space: pre-wrap;"><?php 
                echo e(getSetting('about_preview', 'जिला अधिवक्ता संघ, बांदा — स्थापना वर्ष 1937। इस पोर्टल का उद्देश्य संघ की सूचनाओं, सदस्य सेवाओं और प्रशासनिक कार्यों को व्यवस्थित एवं डिजिटल बनाना है।')); 
            ?></p>

            <ul class="list-unstyled mb-4 small text-secondary">
                <li class="mb-2 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-circle-check text-gold-dark"></i> अधिवक्ताओं के विधिक, सामाजिक एवं व्यावसायिक हितों का संरक्षण।
                </li>
                <li class="mb-2 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-circle-check text-gold-dark"></i> न्यायालय परिसर में पुस्तकालय, चैंबर व आधारभूत सुविधाओं का विकास।
                </li>
                <li class="mb-0 d-flex align-items-center gap-2">
                    <i class="fa-solid fa-circle-check text-gold-dark"></i> पारदर्शी डिजिटल व्यवस्था — ID कार्ड, वकालतनामा व ई-शुल्क प्रणाली।
                </li>
            </ul>
            
            <a href="<?php echo SITE_URL; ?>/about.php" class="btn btn-navy px-4 py-2 fw-semibold rounded-pill">
                विस्तृत जानकारी पढ़ें (Read More) <i class="fa-solid fa-arrow-right ms-1"></i>
            </a>
        </div>
    </div>

    <!-- 4. Notice Board Preview -->
    <div class="col-lg-5">
        <div class="bg-white p-4 rounded-3 shadow-sm border border-light h-100 font-hindi">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="mb-0 text-navy-custom fw-bold d-flex align-items-center gap-2">
                    <i class="fa-solid fa-bullhorn text-gold-dark"></i> सूचना पट्ट (Notice Board)
                </h4>
                <a href="<?php echo SITE_URL; ?>/notices.php" class="btn btn-sm btn-outline-navy fw-semibold rounded-pill px-3 py-1" style="font-size: 0.78rem;">सभी देखें</a>
            </div>
            <hr class="border-gold-custom my-2 opacity-50">
            
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

                    $catIcon = 'fa-bell';
                    if ($notice['category'] === 'Meeting Notice') $catIcon = 'fa-users';
                    if ($notice['category'] === 'Important Notice') $catIcon = 'fa-triangle-exclamation';
                    if ($isCondolence) $catIcon = 'fa-ribbon';
                    ?>
                    
                    <div class="p-3 rounded-3 border <?php echo $isCondolence ? 'border-danger border-opacity-25 bg-danger bg-opacity-10' : 'border-light bg-light-custom'; ?> position-relative transition-hover">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge <?php echo $priorityBadgeClass; ?> font-size-xs px-2 py-1 text-uppercase" style="font-size: 0.68rem; letter-spacing: 0.5px;">
                                <?php echo sanitize($notice['priority']); ?>
                            </span>
                            <small class="text-muted"><i class="fa-regular fa-calendar me-1"></i><?php echo date('d-m-Y', strtotime($notice['published_at'] ?: $notice['created_at'])); ?></small>
                        </div>
                        <h6 class="fw-bold mb-1 text-navy-custom" style="font-size: 0.92rem;">
                            <i class="fa-solid <?php echo $catIcon; ?> text-gold-dark me-1.5"></i><?php echo sanitize($notice['title']); ?>
                        </h6>
                        <p class="small text-muted mb-2 text-truncate">
                            <?php echo sanitize($notice['short_description'] ?: $notice['description']); ?>
                        </p>
                        <div class="text-end">
                            <a href="notice.php?slug=<?php echo urlencode($notice['slug']); ?>" class="text-navy-custom small fw-semibold text-decoration-none">
                                पूर्ण विवरण पढ़ें (Details) <i class="fa-solid fa-chevron-right small ms-1"></i>
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
    <div class="card border-0 shadow-sm mb-5 font-hindi overflow-hidden" style="border-radius: 14px; border: 1px solid rgba(16, 42, 67, 0.1) !important;">
        <div class="card-header bg-navy-custom text-white py-3 px-4 d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <span class="rounded-circle bg-gold-custom text-navy-custom d-inline-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                    <i class="fa-solid fa-quote-left fs-6"></i>
                </span>
                <div>
                    <h5 class="mb-0 fw-bold text-white fs-6">अध्यक्ष की कलम से</h5>
                    <small class="text-gold-custom text-uppercase tracking-wider" style="font-size: 0.72rem;">From the President's Desk</small>
                </div>
            </div>
            <span class="badge bg-gold-custom text-navy-custom px-3 py-1.5 fw-semibold d-none d-sm-inline-block">
                <?php echo $active_term ? e($active_term['title']) : 'सत्र २०२४-२५'; ?>
            </span>
        </div>
        <div class="card-body p-4 bg-white">
            <div class="row align-items-center g-4">
                <div class="col-md-3 text-center">
                    <div class="position-relative d-inline-block mb-2">
                        <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $president_message_bearer['photo_snapshot'] ?: 'profile/default_advocate.png'; ?>" class="rounded-circle border border-gold-custom border-3 shadow-sm" style="width: 105px; height: 105px; object-fit: cover;" onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default_advocate.png'" alt="President Photo">
                        <span class="position-absolute bottom-0 end-0 bg-navy-custom text-gold-custom rounded-circle p-1 shadow-sm border border-white" style="width: 26px; height: 26px; display: inline-flex; align-items: center; justify-content: center;" title="President">
                            <i class="fa-solid fa-stamp" style="font-size: 0.7rem;"></i>
                        </span>
                    </div>
                    <h6 class="fw-bold mb-1 text-navy-custom"><?php echo e($president_message_bearer['display_name_snapshot']); ?></h6>
                    <span class="badge bg-gold-custom text-navy-custom px-2.5 py-1 fw-bold mb-1" style="font-size: 0.72rem;">
                        <?php echo e($president_message_bearer['designation_override'] ?: 'अध्यक्ष / President'); ?>
                    </span>
                    <div class="text-muted small mt-1">
                        <i class="fa-solid fa-landmark text-gold-dark me-1"></i>बांदा बार एसोसिएशन
                    </div>
                </div>
                <div class="col-md-9 ps-md-4 border-start border-light-custom">
                    <div class="position-relative">
                        <i class="fa-solid fa-quote-left position-absolute top-0 start-0 text-navy-custom opacity-10" style="font-size: 3rem; transform: translate(-10px, -20px);"></i>
                        <p class="mb-3 text-navy-custom position-relative" style="line-height: 1.85; font-size: 1.02rem; font-style: italic; color: #1e3a5f;">
                            "<?php echo e($president_message_bearer['message']); ?>"
                        </p>
                    </div>
                    <div class="d-flex flex-wrap align-items-center justify-content-between pt-2 border-top border-light-custom">
                        <span class="small text-muted fst-italic">न्याय, स्वाभिमान एवं अधिवक्ता कल्याण के प्रति समर्पित</span>
                        <a href="<?php echo SITE_URL; ?>/office-bearers.php" class="btn btn-navy btn-sm px-3 py-1.5 shadow-sm rounded-2 d-inline-flex align-items-center gap-1.5">
                            <i class="fa-solid fa-envelope-open-text text-gold-custom"></i> विस्तृत संदेश एवं कार्यकारिणी देखें
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- 5. Current Office Bearers Preview Section -->
<?php if (getSetting('office_bearers_visibility', '1') === '1'): ?>
<div class="mb-5 font-hindi text-navy-custom">
    <div class="text-center mb-4">
        <span class="badge bg-gold-custom text-navy-custom mb-2 px-3 py-1.5 font-hindi fw-bold shadow-sm">
            <i class="fa-solid fa-users-viewfinder me-1"></i> <?php echo $active_term ? e($active_term['title']) : 'सत्र विवरण'; ?>
        </span>
        <h2 class="text-navy-custom font-hindi fw-bold">मुख्य संघ पदाधिकारी (Office Bearers)</h2>
        <p class="text-muted small mb-3">संघ के प्रशासनिक एवं विधिक हितों के संरक्षण हेतु कार्यरत मुख्य कार्यकारिणी सदस्य</p>
        <div class="d-flex justify-content-center align-items-center gap-2">
            <span style="height: 2px; width: 40px; background-color: var(--gold-accent);"></span>
            <i class="fa-solid fa-scale-balanced text-gold-dark small"></i>
            <span style="height: 2px; width: 40px; background-color: var(--gold-accent);"></span>
        </div>
    </div>
    
    <div class="row g-4">
        <?php if (empty($home_bearers)): ?>
            <div class="col-12 text-center text-muted py-4 bg-white rounded-3 border">
                <i class="fa-solid fa-circle-info me-1 text-gold-dark"></i> वर्तमान कार्यकारिणी विवरण शीघ्र उपलब्ध होगा।
            </div>
        <?php else: ?>
            <?php foreach ($home_bearers as $bearer): ?>
                <div class="col-lg-3 col-md-6">
                    <div class="card-bearer p-4 text-center h-100 d-flex flex-column">
                        <div class="mb-3 mx-auto rounded-circle overflow-hidden bg-light d-flex align-items-center justify-content-center border border-gold-custom border-2 shadow-sm position-relative" style="width: 120px; height: 120px;">
                            <img src="<?php echo SITE_URL; ?>/uploads/<?php echo $bearer['photo_snapshot'] ?: 'profile/default_advocate.png'; ?>" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default_advocate.png'" alt="<?php echo e($bearer['display_name_snapshot']); ?>">
                        </div>
                        <h5 class="fw-bold mb-1 text-navy-custom fs-6"><?php echo e($bearer['display_name_snapshot']); ?></h5>
                        <div class="mb-3">
                            <span class="badge bg-gold-custom text-navy-custom fw-semibold font-hindi px-3 py-1" style="font-size: 0.72rem; letter-spacing: 0.3px;">
                                <?php echo e($bearer['designation_override'] ?: $bearer['position_name_hindi']); ?>
                            </span>
                        </div>
                        <div class="border-top border-light-custom pt-2 mt-auto">
                            <small class="text-muted font-hindi fw-semibold"><i class="fa-regular fa-clock text-gold-custom me-1"></i>कार्यकाल: <?php echo e($active_term['title']); ?></small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <div class="text-center mt-4 pt-2">
        <a href="<?php echo SITE_URL; ?>/office-bearers.php" class="btn btn-outline-navy px-4 py-2.5 fw-bold shadow-sm d-inline-flex align-items-center gap-2 rounded-3">
            <i class="fa-solid fa-users text-gold-dark"></i> सभी कार्यकारिणी सदस्यों को देखें (View All Bearers)
        </a>
    </div>
</div>
<?php endif; ?>

<?php 
require_once 'includes/footer.php';
?>
