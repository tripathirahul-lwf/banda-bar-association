<?php
/**
 * Public Election Portal
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'अधिवक्ता संघ चुनाव पोर्टल (Election Portal)';
require_once 'includes/header.php';
require_once 'includes/navbar.php';
require_once 'config/database.php';

$db = Database::getConnection();
$error = '';

// Retrieve latest active public election
$election = null;
if ($db) {
    try {
        $election = $db->query("
            SELECT * FROM elections 
            WHERE status != 'archived' AND visibility = 'public'
            ORDER BY id DESC LIMIT 1
        ")->fetch();
    } catch (PDOException $e) {
        error_log("Failed loading public active election: " . $e->getMessage());
    }
}

// -----------------------------
// PUBLIC VOTER SEARCH PROCESSOR
// -----------------------------
$search_query = sanitize(trim($_GET['search'] ?? ''));
$searched_voters = [];

if ($db && $election && !empty($search_query)) {
    try {
        $v_stmt = $db->prepare("
            SELECT voter_no, member_name, membership_no, enrollment_no 
            FROM election_voters 
            WHERE election_id = ? AND eligibility_status = 'eligible'
              AND (member_name LIKE ? OR membership_no LIKE ? OR voter_no LIKE ?)
            ORDER BY CAST(voter_no AS UNSIGNED) ASC, voter_no ASC, id ASC
        ");
        $sp = "%$search_query%";
        $v_stmt->execute([$election['id'], $sp, $sp, $sp]);
        $searched_voters = $v_stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed searching public voter list: " . $e->getMessage());
    }
}

// Retrieve details for public active election
$posts = [];
$candidates = [];
$documents = [];
$results = [];
$notices = [];

if ($db && $election) {
    try {
        $posts = $db->query("SELECT * FROM election_posts WHERE election_id = {$election['id']} ORDER BY display_order ASC")->fetchAll();
        
        // Show candidates if status is candidate_finalized or later stage
        $stages_show_candidates = ['candidate_finalized', 'voter_list_finalized', 'polling_scheduled', 'polling_completed', 'counting', 'result_declared', 'completed'];
        if (in_array($election['status'], $stages_show_candidates)) {
            $candidates = $db->query("
                SELECT c.*, p.post_name_hindi 
                FROM election_candidates c
                JOIN election_posts p ON p.id = c.post_id
                WHERE c.election_id = {$election['id']} AND c.status = 'final_candidate'
                ORDER BY p.display_order ASC, c.ballot_order ASC
            ")->fetchAll();
        }

        // Fetch public documents
        $documents = $db->query("SELECT * FROM election_documents WHERE election_id = {$election['id']} AND visibility = 'public' ORDER BY id DESC")->fetchAll();

        // Fetch election notices
        $notices = $db->query("SELECT * FROM notices WHERE election_id = {$election['id']} AND status = 'published' ORDER BY id DESC")->fetchAll();

        // Fetch results if declared
        if (in_array($election['status'], ['result_declared', 'completed'])) {
            $results = $db->query("
                SELECT r.*, c.name_snapshot, c.membership_no_snapshot, c.photo_snapshot, p.post_name_hindi 
                FROM election_results r
                JOIN election_candidates c ON c.id = r.candidate_id
                JOIN election_posts p ON p.id = r.post_id
                WHERE r.election_id = {$election['id']} AND r.result_status IN ('elected', 'unopposed')
                ORDER BY p.display_order ASC
            ")->fetchAll();
        }
    } catch (PDOException $e) {}
}
?>

<div class="container py-5 text-navy-custom font-hindi small">
    
    <!-- Header Hero Banner -->
    <div class="bg-navy-custom text-white p-4 p-md-5 rounded-3 mb-4 shadow-sm text-center">
        <span class="badge bg-gold-custom text-navy-custom mb-3 px-3 py-1.5 fw-bold text-uppercase tracking-wider">
            <i class="bi bi-bank me-1"></i> जिला अधिवक्ता संघ, बांदा चुनाव पटल (Election Desk)
        </span>
        <h2 class="fw-bold fs-2 text-white font-hindi">अधिवक्ता संघ आम चुनाव (General Elections)</h2>
        <p class="text-light-custom english-text mb-0">Official voter snap schedules, candidate directory, and certified declarations board.</p>
    </div>

    <?php if (!$election): ?>
        <div class="text-center py-5 bg-white border rounded shadow-sm text-muted">
            <i class="bi bi-check2-square display-4 d-block mb-3 text-secondary"></i>
            <h5 class="fw-bold mb-2">वर्तमान में कोई सक्रिय चुनाव घोषित नहीं है।</h5>
            <p class="mb-4 text-muted">पिछले चुनावों के परिणामों व अभिलेखों की समीक्षा हेतु पुरालेख में देखें।</p>
            <a href="elections/archive.php" class="btn btn-navy fw-bold px-4 py-2"><i class="bi bi-archive me-2"></i>चुनाव पुरालेख (View Archive)</a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            
            <!-- Left Panel: General info, schedule & Voter search -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-navy-custom text-white py-2">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-info-circle text-gold-custom me-2"></i>वर्तमान चुनाव की जानकारी (Election Status)</h6>
                    </div>
                    <div class="card-body">
                        <h5 class="fw-bold text-navy-custom mb-3"><?php echo e($election['title']); ?> (Code: <?php echo e($election['election_code']); ?>)</h5>
                        <div class="row g-2 fs-6 mb-3">
                            <div class="col-5 text-secondary">मतदान की तिथि:</div>
                            <div class="col-7 fw-bold english-text"><?php echo date('d-m-Y', strtotime($election['election_date'])); ?></div>
                            <div class="col-5 text-secondary">मतदान का स्थान:</div>
                            <div class="col-7 fw-semibold"><?php echo e($election['venue'] ?: 'सभागार भवन, बांदा'); ?></div>
                            <div class="col-5 text-secondary">वर्तमान स्थिति स्टेज:</div>
                            <div class="col-7"><span class="badge bg-danger text-uppercase font-size-xs"><?php echo e(ucfirst(str_replace('_', ' ', $election['status']))); ?></span></div>
                        </div>
                        <hr class="my-2">
                        <p class="text-muted mb-0 leading-relaxed"><?php echo e($election['description'] ?: 'चुनाव सम्बन्धी कोई अतिरिक्त विवरण दर्ज नहीं है।'); ?></p>
                    </div>
                </div>

                <!-- Public Voter List Search -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-light py-2">
                        <h6 class="mb-0 fw-bold text-navy-custom"><i class="bi bi-people-fill text-gold-dark me-2"></i>सार्वजनिक मतदाता सूची खोजें (Voter List Search)</h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" action="" class="row g-2 mb-3">
                            <div class="col-md-9">
                                <input type="text" name="search" class="form-control form-control-sm" placeholder="अधिवक्ता का नाम / सदस्य कोड / वोटर नम्बर दर्ज करें..." value="<?php echo e($search_query); ?>" required>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-navy btn-sm w-100"><i class="bi bi-search me-1"></i>खोजें</button>
                            </div>
                        </form>

                        <?php if (!empty($search_query)): ?>
                            <div class="table-responsive border rounded bg-white">
                                <table class="table table-sm table-hover align-middle mb-0" style="font-size: 0.72rem;">
                                    <thead class="table-light">
                                        <tr>
                                            <th>मतदाता सं</th>
                                            <th>अधिवक्ता नाम</th>
                                            <th>सदस्यता सं (DBA Code)</th>
                                            <th>नामांकन क्रमांक</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($searched_voters)): ?>
                                            <tr>
                                                <td colspan="4" class="text-center py-2 text-danger fw-bold">खोजे गए मानदंडों के अनुसार कोई पात्र मतदाता नहीं मिला।</td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach ($searched_voters as $sv): ?>
                                                <tr>
                                                    <td class="english-text fw-bold text-navy-custom"><?php echo e($sv['voter_no'] ?: '-'); ?></td>
                                                    <td><strong><?php echo e($sv['member_name']); ?></strong></td>
                                                    <td class="english-text"><?php echo e($sv['membership_no']); ?></td>
                                                    <td class="english-text"><?php echo e($sv['enrollment_no']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Election notices bulletins -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-navy-custom text-white py-2">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-bell-fill text-gold-custom me-2"></i>चुनाव प्रेस विज्ञप्ति एवं सूचनाएं (Notices)</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <?php if (empty($notices)): ?>
                                <div class="text-center py-4 text-muted">कोई भी सूचना प्रकाशित नहीं है।</div>
                            <?php else: ?>
                                <?php foreach ($notices as $nt): ?>
                                    <a href="notice.php?slug=<?php echo urlencode($nt['slug']); ?>" class="list-group-item list-group-item-action p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="badge bg-danger text-uppercase" style="font-size:0.65rem;"><?php echo e($nt['priority']); ?></span>
                                            <small class="text-muted english-text"><?php echo date('d-m-Y', strtotime($nt['publish_at'])); ?></small>
                                        </div>
                                        <strong class="text-navy-custom"><?php echo e($nt['title']); ?></strong>
                                        <p class="text-muted small text-truncate mb-0 mt-1"><?php echo e($nt['short_description']); ?></p>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Panel: Winners List, Timeline, Candidates, Documents -->
            <div class="col-lg-5">
                
                <!-- Election Winners Results when declared -->
                <?php if (!empty($results)): ?>
                    <div class="card border-0 shadow-sm mb-4 border-start border-3 border-success">
                        <div class="card-header bg-success text-white py-2">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-award-fill text-gold-custom me-2"></i>घोषित विजयी पदाधिकारी (Elected Officers)</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-2">
                                <?php foreach ($results as $res): ?>
                                    <div class="col-6 text-center">
                                        <div class="p-2 border rounded bg-white shadow-xs">
                                            <div class="mb-1 mx-auto rounded-circle overflow-hidden bg-light border" style="width: 55px; height: 55px;">
                                                <img src="<?php echo SITE_URL; ?>/uploads/profile/<?php echo $res['photo_snapshot']; ?>" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default_advocate.png'">
                                            </div>
                                            <h6 class="fw-bold mb-0 text-navy-custom" style="font-size:0.75rem;"><?php echo e($res['name_snapshot']); ?></h6>
                                            <span class="badge bg-gold-custom text-navy-custom font-hindi px-2 py-0.5" style="font-size:0.65rem;"><?php echo e($res['post_name_hindi']); ?></span>
                                            <small class="d-block text-muted english-text mt-1" style="font-size:0.65rem;">
                                                <?php echo $res['votes_received'] !== null ? 'मत: ' . $res['votes_received'] : 'निर्विरोध'; ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Election Schedule Timeline -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-light py-2">
                        <h6 class="mb-0 fw-bold text-navy-custom"><i class="bi bi-clock-history me-2"></i>चुनाव कार्यक्रम सारणी (Timeline)</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled position-relative border-start border-2 ps-3 py-1 ms-2 mb-0" style="font-size:0.7rem;">
                            <?php 
                            $timeline = [
                                'नामांकन प्रपत्र प्रारंभ' => $election['nomination_start'] ? date('d-m-Y H:i', strtotime($election['nomination_start'])) : 'Date To Be Announced',
                                'नामांकन अंतिम तिथि' => $election['nomination_end'] ? date('d-m-Y H:i', strtotime($election['nomination_end'])) : 'Date To Be Announced',
                                'नामांकन पत्रों की जांच' => $election['scrutiny_date'] ? date('d-m-Y', strtotime($election['scrutiny_date'])) : 'Date To Be Announced',
                                'नाम वापसी की सीमा' => $election['withdrawal_deadline'] ? date('d-m-Y H:i', strtotime($election['withdrawal_deadline'])) : 'Date To Be Announced',
                                'अंतिम प्रत्याशी प्रकाशन' => $election['final_candidate_date'] ? date('d-m-Y', strtotime($election['final_candidate_date'])) : 'Date To Be Announced',
                                'मतदान तिथि (Polling)' => date('d-m-Y', strtotime($election['election_date'])),
                            ];

                            foreach ($timeline as $label => $val):
                                $color = ($val === 'Date To Be Announced') ? 'text-danger' : 'text-success fw-bold';
                            ?>
                                <li class="mb-2 position-relative">
                                    <div class="position-absolute bg-navy-custom rounded-circle" style="width: 8px; height: 8px; left: -19px; top: 4px;"></div>
                                    <span class="text-secondary"><?php echo $label; ?>:</span>
                                    <span class="<?php echo $color; ?> english-text ms-1"><?php echo $val; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <!-- Final Candidates List -->
                <?php if (!empty($candidates)): ?>
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-navy-custom text-white py-2">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-person-badge text-gold-custom me-2"></i>अंतिम प्रत्याशी सूची (Final Candidates)</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" style="font-size:0.7rem;">
                                    <thead class="table-light">
                                        <tr>
                                            <th>फोटो</th>
                                            <th>प्रत्याशी का नाम</th>
                                            <th>चुनाव पद</th>
                                            <th class="text-center">मतपत्र क्रमांक</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($candidates as $cand): ?>
                                            <tr>
                                                <td>
                                                    <img src="<?php echo SITE_URL; ?>/uploads/profile/<?php echo $cand['photo_snapshot']; ?>" class="rounded" style="width: 24px; height: 24px; object-fit: cover;" onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default_advocate.png'">
                                                </td>
                                                <td><strong><?php echo e($cand['name_snapshot']); ?></strong></td>
                                                <td><?php echo e($cand['post_name_hindi']); ?></td>
                                                <td class="text-center text-success fw-bold"><?php echo $cand['ballot_order'] ? '#' . $cand['ballot_order'] : '-'; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Public Published Documents -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-light py-2">
                        <h6 class="mb-0 fw-bold text-navy-custom"><i class="bi bi-file-earmark-pdf me-2"></i>चुनाव प्रलेख / सूचना पत्र (Documents)</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush" style="font-size:0.75rem;">
                            <?php if (empty($documents)): ?>
                                <div class="text-center py-3 text-muted">कोई परिपत्र उपलब्ध नहीं है।</div>
                            <?php else: ?>
                                <?php foreach ($documents as $dc): ?>
                                    <a href="<?php echo SITE_URL; ?>/<?php echo $dc['file_path']; ?>" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 px-3">
                                        <span><i class="bi bi-file-pdf text-danger me-1"></i><?php echo e($dc['title']); ?></span>
                                        <i class="bi bi-download"></i>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Archive Redirection Link -->
                <div class="text-center mt-3">
                    <a href="elections/archive.php" class="btn btn-outline-navy btn-sm"><i class="bi bi-archive me-1"></i>विगत चुनाव पुरालेख देखें (Previous Elections)</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php 
require_once 'includes/footer.php';
?>
