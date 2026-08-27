<?php
/**
 * Member Portal Election Information Desk
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'अधिवक्ता संघ चुनाव पटल (Election Center)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce member session
requireRole('member');

$db = Database::getConnection();
$error = '';
$success = '';

// Retrieve Member ID
$member_id = $_SESSION['member_id'] ?? 0;

// Fetch latest active election
$election = null;
if ($db) {
    try {
        $election = $db->query("
            SELECT * FROM elections 
            WHERE status != 'archived' AND visibility IN ('public', 'members_only')
            ORDER BY id DESC LIMIT 1
        ")->fetch();
    } catch (PDOException $e) {
        error_log("Failed query member active election: " . $e->getMessage());
    }
}

// Fetch member's personal voter status
$voter_record = null;
if ($db && $election && $member_id > 0) {
    try {
        $v_stmt = $db->prepare("SELECT * FROM election_voters WHERE election_id = ? AND member_id = ?");
        $v_stmt->execute([$election['id'], $member_id]);
        $voter_record = $v_stmt->fetch();
    } catch (PDOException $e) {}
}

// Fetch candidates list grouped by post
$candidates = [];
$posts = [];
$results = [];
$documents = [];
if ($db && $election) {
    try {
        $posts = $db->query("SELECT * FROM election_posts WHERE election_id = {$election['id']} ORDER BY display_order ASC")->fetchAll();
        
        // Only show candidates if status is candidate_finalized or later stage
        $stages_show_candidates = ['candidate_finalized', 'voter_list_finalized', 'polling_scheduled', 'polling_completed', 'counting', 'result_declared', 'completed'];
        if (in_split_array($election['status'], $stages_show_candidates)) {
            $candidates = $db->query("
                SELECT c.*, p.post_name_hindi 
                FROM election_candidates c
                JOIN election_posts p ON p.id = c.post_id
                WHERE c.election_id = {$election['id']} AND c.status = 'final_candidate'
                ORDER BY p.display_order ASC, c.ballot_order ASC
            ")->fetchAll();
        }

        // Fetch documents
        $documents = $db->query("SELECT * FROM election_documents WHERE election_id = {$election['id']} AND visibility IN ('public', 'members_only') ORDER BY id DESC")->fetchAll();

        // Fetch results if declared
        if (in_split_array($election['status'], ['result_declared', 'completed'])) {
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

function in_split_array($val, $arr) {
    return in_array($val, $arr);
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">चुनाव सूचना एवं सेवा पटल (Election Information Portal)</h4>
</div>

<?php if (!$election): ?>
    <div class="alert alert-info py-4 text-center font-hindi border-0 rounded shadow-xs">
        <i class="bi bi-info-circle fs-3 d-block mb-2 text-primary"></i>
        <strong>वर्तमान में कोई सक्रिय चुनाव घोषित नहीं है।</strong>
    </div>
<?php else: ?>
    <!-- 1. Voter eligibility status widget -->
    <div class="card border-0 shadow-sm mb-4 font-hindi small text-navy-custom">
        <div class="card-header bg-navy-custom text-white py-2">
            <h6 class="mb-0 fw-bold"><i class="bi bi-person-check text-gold-custom me-2"></i>मेरी मतदाता स्थिति (My Voter Status)</h6>
        </div>
        <div class="card-body">
            <?php if ($voter_record): ?>
                <?php if ($voter_record['eligibility_status'] === 'eligible'): ?>
                    <div class="alert alert-success border-0 rounded p-3 mb-0 d-flex align-items-center gap-3">
                        <i class="bi bi-check-circle-fill display-6 text-success"></i>
                        <div>
                            <h5 class="fw-bold mb-1">आपका नाम मतदाता सूची में सम्मिलित है।</h5>
                            <span><strong>मतदाता संख्या (Voter No):</strong> <span class="badge bg-success fs-6 text-white english-text"><?php echo e($voter_record['voter_no'] ?: '-'); ?></span></span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning border-0 rounded p-3 mb-0 d-flex align-items-center gap-3">
                        <i class="bi bi-hourglass-split display-6 text-warning"></i>
                        <div>
                            <h5 class="fw-bold mb-1">मतदाता सूची में आपके विवरण सत्यापन के अधीन हैं।</h5>
                            <span class="text-muted">कार्यकारिणी चुनाव समिति द्वारा मतदाता पात्रता सत्यापन की जा रही है।</span>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="alert alert-secondary border-0 rounded p-3 mb-0 d-flex align-items-center gap-3">
                    <i class="bi bi-exclamation-triangle display-6 text-secondary"></i>
                    <div>
                        <h5 class="fw-bold mb-1">मतदाता सूची में रिकॉर्ड अप्राप्त।</h5>
                        <span class="text-muted">आपकी सदस्यता पात्रता समीक्षा या सूची में प्रविष्टि प्रक्रियाधीन है।</span>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 2. Results card if results declared -->
    <?php if (!empty($results)): ?>
        <div class="card border-0 shadow-sm mb-4 font-hindi small text-navy-custom border-start border-3 border-success">
            <div class="card-header bg-success text-white py-2">
                <h6 class="mb-0 fw-bold"><i class="bi bi-award-fill text-gold-custom me-2"></i>घोषित चुनाव परिणाम (Official Winners List)</h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach ($results as $res): ?>
                        <div class="col-md-4 col-sm-6">
                            <div class="p-3 border rounded text-center bg-white shadow-xs">
                                <div class="mb-2 mx-auto rounded-circle overflow-hidden bg-light border" style="width: 70px; height: 70px;">
                                    <img src="<?php echo SITE_URL; ?>/uploads/profile/<?php echo $res['photo_snapshot']; ?>" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default_advocate.png'">
                                </div>
                                <h6 class="fw-bold mb-0 text-navy-custom"><?php echo e($res['name_snapshot']); ?></h6>
                                <span class="badge bg-gold-custom text-navy-custom fw-semibold font-hindi mb-2 px-2 py-0.5" style="font-size:0.7rem;"><?php echo e($res['post_name_hindi']); ?></span>
                                <div class="text-muted font-size-xs english-text" style="font-size:0.7rem;">
                                    <?php echo $res['votes_received'] !== null ? 'प्राप्त मत: ' . $res['votes_received'] : 'निर्विरोध (Unopposed)'; ?>
                                </div>
                                <span class="badge bg-success font-size-xs mt-2">विजयी (Elected)</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4 font-hindi small text-navy-custom">
        <!-- 3. Left Column: Details and documents -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-navy-custom text-white py-2">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-info-circle text-gold-custom me-2"></i>सामान्य चुनाव विवरण (Elections Overview)</h6>
                </div>
                <div class="card-body">
                    <div class="row g-2 mb-3">
                        <div class="col-5 text-secondary">चुनाव शीर्षक:</div>
                        <div class="col-7 fw-bold"><?php echo e($election['title']); ?></div>
                        <div class="col-5 text-secondary">मतदान तिथि:</div>
                        <div class="col-7 fw-bold english-text"><?php echo date('d-m-Y', strtotime($election['election_date'])); ?></div>
                        <div class="col-5 text-secondary">मतदान स्थल:</div>
                        <div class="col-7"><?php echo e($election['venue'] ?: '-'); ?></div>
                        <div class="col-5 text-secondary">मतदान समय:</div>
                        <div class="col-7 english-text"><?php echo e($election['voting_start_time'] ?: '-'); ?> से <?php echo e($election['voting_end_time'] ?: '-'); ?></div>
                    </div>
                    <hr class="my-2">
                    <p class="text-muted leading-relaxed mb-0"><?php echo e($election['description'] ?: 'कोई अन्य विवरण उपलब्ध नहीं।'); ?></p>
                </div>
            </div>

            <!-- Documents -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light py-2">
                    <h6 class="mb-0 fw-bold text-navy-custom"><i class="bi bi-file-earmark-pdf me-2"></i>अपलोड किए गए प्रपत्र / परिपत्र (Documents)</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0" style="font-size:0.75rem;">
                            <thead>
                                <tr class="table-light">
                                    <th>दस्तावेज शीर्षक</th>
                                    <th>श्रेणी</th>
                                    <th class="text-end">डाउनलोड</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($documents)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-2 text-muted">कोई प्रपत्र उपलब्ध नहीं है।</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($documents as $doc): ?>
                                        <tr>
                                            <td><strong><?php echo e($doc['title']); ?></strong></td>
                                            <td><?php echo e($doc['document_type']); ?></td>
                                            <td class="text-end">
                                                <a href="<?php echo SITE_URL; ?>/<?php echo $doc['file_path']; ?>" target="_blank" class="btn btn-xs btn-outline-navy py-0.5">डाउनलोड</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Right Column: Timeline Schedule & Candidate list -->
        <div class="col-lg-6">
            <!-- Timeline Display -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light py-2">
                    <h6 class="mb-0 fw-bold text-navy-custom"><i class="bi bi-clock me-2"></i>चुनाव कार्यक्रम (Schedule Timeline)</h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled position-relative border-start border-2 ps-3 py-1 ms-2 mb-0" style="font-size:0.72rem;">
                        <?php 
                        $timeline = [
                            'नामांकन प्रारंभ' => $election['nomination_start'] ? date('d-m-Y H:i', strtotime($election['nomination_start'])) : 'Date To Be Announced',
                            'नामांकन समाप्ति' => $election['nomination_end'] ? date('d-m-Y H:i', strtotime($election['nomination_end'])) : 'Date To Be Announced',
                            'नामांकन पत्रों की जांच' => $election['scrutiny_date'] ? date('d-m-Y', strtotime($election['scrutiny_date'])) : 'Date To Be Announced',
                            'नाम वापसी की सीमा' => $election['withdrawal_deadline'] ? date('d-m-Y H:i', strtotime($election['withdrawal_deadline'])) : 'Date To Be Announced',
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

            <!-- Grouped candidates by post -->
            <?php if (!empty($candidates)): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-navy-custom text-white py-2">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-person-badge text-gold-custom me-2"></i>अंतिम प्रत्याशी सूची (Final Candidates)</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size:0.75rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th>फोटो</th>
                                        <th>प्रत्याशी का नाम</th>
                                        <th>चुनाव पद (Post)</th>
                                        <th class="text-center">मतपत्र क्रमांक</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($candidates as $cand): ?>
                                        <tr>
                                            <td>
                                                <img src="<?php echo SITE_URL; ?>/uploads/profile/<?php echo $cand['photo_snapshot']; ?>" class="rounded" style="width: 28px; height: 28px; object-fit: cover;" onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default_advocate.png'">
                                            </td>
                                            <td>
                                                <strong><?php echo e($cand['name_snapshot']); ?></strong><br>
                                                <span class="text-muted" style="font-size:0.7rem;">Code: <?php echo e($cand['membership_no_snapshot']); ?></span>
                                            </td>
                                            <td><strong><?php echo e($cand['post_name_hindi']); ?></strong></td>
                                            <td class="text-center fw-bold text-success fs-6 english-text"><?php echo $cand['ballot_order'] ? '#' . $cand['ballot_order'] : '-'; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
