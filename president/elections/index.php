<?php
/**
 * Presidential Election Approvals & Monitoring Portal
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'अध्यक्ष चुनाव समीक्षा पटल (President Election Desk)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce President role
requireRole('president');

$db = Database::getConnection();
$error = '';
$success = '';

// Handle Presidential Results Approval Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'approve_results') {
    if (!verifyCSRF()) {
        $error = 'सुरक्षा सत्यापन विफल रहा।';
    } else {
        $elec_id = intval($_POST['election_id'] ?? 0);
        if ($elec_id > 0) {
            try {
                $db->beginTransaction();

                // Update election status
                $up = $db->prepare("UPDATE elections SET status = 'result_declared', updated_at = NOW() WHERE id = ?");
                $up->execute([$elec_id]);

                // Audit log
                $audit = $db->prepare("INSERT INTO election_history (election_id, action, module, record_id, remarks, performed_by) VALUES (?, 'Result Declared', 'results', ?, 'राष्ट्रपति द्वारा चुनाव परिणामों की पुष्टि एवं प्रकाशन स्वीकृत।', ?)");
                $audit->execute([$elec_id, $elec_id, $_SESSION['user_id']]);

                $db->commit();
                $success = 'चुनाव परिणाम सफलतापूर्वक स्वीकृत एवं सार्वजनिक रूप से प्रकाशित कर दिए गए हैं।';
            } catch (PDOException $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = 'अनुमोदन करने में असमर्थ: ' . $e->getMessage();
            }
        }
    }
}

// Fetch active latest election details
$election = null;
if ($db) {
    try {
        $election = $db->query("
            SELECT e.*, 
                   (SELECT COUNT(*) FROM election_voters WHERE election_id = e.id AND eligibility_status = 'eligible') AS eligible_voters_count,
                   (SELECT COUNT(*) FROM election_candidates WHERE election_id = e.id) AS candidates_count
            FROM elections e
            WHERE e.status != 'archived'
            ORDER BY e.id DESC LIMIT 1
        ")->fetch();
    } catch (PDOException $e) {
        error_log("Failed query president desk active election: " . $e->getMessage());
    }
}

// Group candidates if election exists
$candidates = [];
$posts = [];
$results = [];
if ($db && $election) {
    try {
        $posts = $db->query("SELECT * FROM election_posts WHERE election_id = {$election['id']} ORDER BY display_order ASC")->fetchAll();
        $candidates = $db->query("
            SELECT c.*, p.post_name_hindi 
            FROM election_candidates c
            JOIN election_posts p ON p.id = c.post_id
            WHERE c.election_id = {$election['id']}
            ORDER BY p.display_order ASC, c.ballot_order ASC, c.id ASC
        ")->fetchAll();
        
        $results = $db->query("
            SELECT r.*, c.name_snapshot, c.membership_no_snapshot, p.post_name_hindi 
            FROM election_results r
            JOIN election_candidates c ON c.id = r.candidate_id
            JOIN election_posts p ON p.id = r.post_id
            WHERE r.election_id = {$election['id']}
        ")->fetchAll();
    } catch (PDOException $e) {}
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">अध्यक्ष चुनाव समीक्षा पटल (President Election Dashboard)</h4>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 font-hindi small"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success py-2 font-hindi small"><?php echo $success; ?></div>
<?php endif; ?>

<?php if (!$election): ?>
    <div class="alert alert-info py-3 font-hindi text-center border-0 rounded">
        <strong>वर्तमान में कोई सक्रिय चुनाव घोषित नहीं है।</strong>
    </div>
<?php else: ?>
    <!-- High-level stats -->
    <div class="row g-3 mb-4 font-hindi small text-navy-custom">
        <div class="col-md-4 col-12">
            <div class="card p-3 border-0 bg-light text-center shadow-xs">
                <span class="text-secondary font-size-xs d-block">सक्रिय चुनाव</span>
                <strong class="fs-6 d-block my-1"><?php echo e($election['title']); ?></strong>
            </div>
        </div>
        <div class="col-md-2 col-4">
            <div class="card p-3 border-0 bg-light text-center shadow-xs">
                <span class="text-secondary font-size-xs d-block text-success">योग्य मतदाता (Voters)</span>
                <strong class="fs-4 d-block text-success english-text my-1"><?php echo $election['eligible_voters_count']; ?></strong>
            </div>
        </div>
        <div class="col-md-2 col-4">
            <div class="card p-3 border-0 bg-light text-center shadow-xs">
                <span class="text-secondary font-size-xs d-block text-danger">नामांकित प्रत्याशी</span>
                <strong class="fs-4 d-block text-danger english-text my-1"><?php echo $election['candidates_count']; ?></strong>
            </div>
        </div>
        <div class="col-md-4 col-4">
            <div class="card p-3 border-0 bg-light text-center shadow-xs">
                <span class="text-secondary font-size-xs d-block">चुनाव चरण (Current Stage)</span>
                <strong class="fs-6 text-uppercase text-danger d-block my-1"><?php echo e(ucfirst(str_replace('_', ' ', $election['status']))); ?></strong>
            </div>
        </div>
    </div>

    <!-- Results Approval Block if Result Pending Approval config is enabled -->
    <?php if ($election['status'] !== 'result_declared' && !empty($results)): ?>
        <div class="card border-0 shadow-sm border-start border-3 border-warning mb-4 font-hindi small text-navy-custom">
            <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="bi bi-shield-check text-gold-dark me-2"></i>परिणाम अनुमोदन पटल (Results Awaiting Executive Approval)</h6>
                <form method="POST" action="" onsubmit="return confirm('क्या आप वास्तव में इन चुनाव परिणामों को प्रमाणित कर घोषित करना चाहते हैं?');">
                    <?php insertCSRF(); ?>
                    <input type="hidden" name="action" value="approve_results">
                    <input type="hidden" name="election_id" value="<?php echo $election['id']; ?>">
                    <button type="submit" class="btn btn-xs btn-success"><i class="bi bi-check-circle-fill me-1"></i>परिणाम स्वीकृत करें (Approve Results)</button>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0" style="font-size:0.75rem;">
                        <thead>
                            <tr class="table-light">
                                <th>पद (Post)</th>
                                <th>प्रत्याशी का नाम</th>
                                <th>DBA Code</th>
                                <th class="text-center">प्राप्त मत (Votes)</th>
                                <th>परिणाम स्थिति</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($results as $res): ?>
                                <tr>
                                    <td><strong><?php echo e($res['post_name_hindi']); ?></strong></td>
                                    <td><strong><?php echo e($res['name_snapshot']); ?></strong></td>
                                    <td class="english-text"><?php echo e($res['membership_no_snapshot']); ?></td>
                                    <td class="english-text text-center fw-bold text-success"><?php echo $res['votes_received'] !== null ? $res['votes_received'] : 'निर्विरोध (Unopposed)'; ?></td>
                                    <td>
                                        <?php 
                                        $c = ($res['result_status'] === 'elected' || $res['result_status'] === 'unopposed') ? 'bg-success' : 'bg-secondary';
                                        ?>
                                        <span class="badge <?php echo $c; ?> font-size-xs"><?php echo e(ucfirst($res['result_status'])); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-4 font-hindi small text-navy-custom">
        <!-- Left Column: Election Details & Schedule -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-navy-custom text-white py-2">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-info-circle text-gold-custom me-2"></i>सामान्य चुनाव कार्यक्रम ब्यौरा (Schedules)</h6>
                </div>
                <div class="card-body">
                    <div class="row g-2 mb-3">
                        <div class="col-6 text-secondary">मतदान तिथि:</div>
                        <div class="col-6 fw-bold english-text"><?php echo date('d-m-Y', strtotime($election['election_date'])); ?></div>
                        <div class="col-6 text-secondary">मतदान स्थल:</div>
                        <div class="col-6 fw-bold"><?php echo e($election['venue'] ?: '-'); ?></div>
                        <div class="col-6 text-secondary">प्रारंभ समय:</div>
                        <div class="col-6 english-text"><?php echo e($election['voting_start_time'] ?: '-'); ?></div>
                        <div class="col-6 text-secondary">समाप्ति समय:</div>
                        <div class="col-6 english-text"><?php echo e($election['voting_end_time'] ?: '-'); ?></div>
                    </div>
                    <hr class="my-2">
                    <p class="text-muted leading-relaxed mb-0"><?php echo e($election['description'] ?: 'कोई अतिरिक्त विवरण दर्ज नहीं है।'); ?></p>
                </div>
            </div>
        </div>

        <!-- Right Column: Candidate Nominee Cards -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-navy-custom text-white py-2">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-person-badge text-gold-custom me-2"></i>उम्मीदवारों की नामांकित सूची (Nominees Registry)</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="font-size:0.75rem;">
                            <thead class="table-light">
                                <tr>
                                    <th>नाम</th>
                                    <th>पद</th>
                                    <th>नामांकन क्रमांक</th>
                                    <th>जांच स्थिति</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($candidates)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-3 text-muted">कोई नामांकित प्रत्याशी नहीं है।</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($candidates as $cand): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo e($cand['name_snapshot']); ?></strong><br>
                                                <span class="text-muted" style="font-size:0.7rem;">Code: <?php echo e($cand['membership_no_snapshot']); ?></span>
                                            </td>
                                            <td><?php echo e($cand['post_name_hindi']); ?></td>
                                            <td class="english-text"><?php echo e($cand['nomination_no']); ?></td>
                                            <td>
                                                <?php 
                                                $co = ($cand['status'] === 'accepted' || $cand['status'] === 'final_candidate') ? 'bg-success' : 'bg-warning text-dark';
                                                ?>
                                                <span class="badge <?php echo $co; ?> font-size-xs"><?php echo e(ucfirst($cand['status'])); ?></span>
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
    </div>
<?php endif; ?>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
