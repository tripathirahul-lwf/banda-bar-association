<?php
/**
 * Detailed Election Master Control & Management Workspace
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'चुनाव संचालन समीक्षा पटल (Election Workspace)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce permissions
requireRole(['admin', 'mahasachiv']);

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$active_tab = sanitize($_GET['tab'] ?? 'overview');
$db = Database::getConnection();

$election = null;
$error = '';
$success = '';

if ($db && $id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM elections WHERE id = ?");
        $stmt->execute([$id]);
        $election = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Failed loading election details: " . $e->getMessage());
    }
}

if (!$election) {
    echo '<div class="alert alert-danger font-hindi">त्रुटि: चुनाव विवरण नहीं मिला।</div>';
    require_once __DIR__ . '/../../includes/dashboard/footer.php';
    exit;
}

// -----------------------------
// POST FORMS PROCESSING SECTION
// -----------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF()) {
        $error = 'सुरक्षा सत्यापन विफल रहा।';
    } else {
        $action = sanitize($_POST['action'] ?? '');
        
        try {
            $db->beginTransaction();

            if ($action === 'update_status') {
                $new_status = sanitize($_POST['status'] ?? '');
                
                $stage_orders = [
                    'draft' => 1, 'announced' => 2, 'nomination_open' => 3, 'scrutiny' => 4, 'withdrawal' => 5,
                    'candidate_finalized' => 6, 'voter_list_finalized' => 7, 'polling_scheduled' => 8,
                    'polling_completed' => 9, 'counting' => 10, 'result_declared' => 11, 'completed' => 12, 'cancelled' => 13
                ];
                
                $current_status = $election['status'];
                $is_backward = isset($stage_orders[$new_status], $stage_orders[$current_status]) && ($stage_orders[$new_status] < $stage_orders[$current_status]);
                $is_protected_stage = in_array($current_status, ['candidate_finalized', 'voter_list_finalized', 'result_declared', 'completed']);
                
                if ($is_backward && $is_protected_stage) {
                    $reopen_reason = sanitize(trim($_POST['reopen_reason'] ?? ''));
                    if (empty($reopen_reason)) {
                        throw new Exception("Reopening Reason is required to go back from finalized stages.");
                    }
                    $remarks = "Reopened stage from $current_status to $new_status. Reason: " . $reopen_reason;
                } else {
                    $remarks = "Stage changed.";
                }
                
                $up = $db->prepare("UPDATE elections SET status = ?, updated_at = NOW() WHERE id = ?");
                $up->execute([$new_status, $id]);

                $audit = $db->prepare("INSERT INTO election_history (election_id, action, module, record_id, old_value, new_value, remarks, performed_by) VALUES (?, 'Stage Changed', 'elections', ?, ?, ?, ?, ?)");
                $audit->execute([$id, $id, $current_status, $new_status, $remarks, $_SESSION['user_id']]);
                
                // Central Audit Log (Requirement 58)
                logAudit('elections', 'Stage Changed', 'elections', $id, $remarks, ['status' => $current_status], ['status' => $new_status]);

                $db->commit();
                $_SESSION['flash_success'] = 'चुनाव की स्थिति सफलतापूर्वक अपडेट की गई।';
                header("Location: view.php?id=$id&tab=overview");
                exit;

            } elseif ($action === 'update_schedule') {
                $title = sanitize(trim($_POST['title'] ?? ''));
                $date = sanitize(trim($_POST['election_date'] ?? ''));
                $venue = sanitize(trim($_POST['venue'] ?? ''));
                $visibility = sanitize($_POST['visibility'] ?? 'private');

                $nom_start = !empty($_POST['nomination_start']) ? sanitize($_POST['nomination_start']) : null;
                $nom_end = !empty($_POST['nomination_end']) ? sanitize($_POST['nomination_end']) : null;
                $scrutiny = !empty($_POST['scrutiny_date']) ? sanitize($_POST['scrutiny_date']) : null;
                $withdraw = !empty($_POST['withdrawal_deadline']) ? sanitize($_POST['withdrawal_deadline']) : null;
                $final_cand = !empty($_POST['final_candidate_date']) ? sanitize($_POST['final_candidate_date']) : null;
                $v_start = !empty($_POST['voting_start_time']) ? sanitize($_POST['voting_start_time']) : null;
                $v_end = !empty($_POST['voting_end_time']) ? sanitize($_POST['voting_end_time']) : null;
                $counting = !empty($_POST['counting_date']) ? sanitize($_POST['counting_date']) : null;
                $result = !empty($_POST['result_date']) ? sanitize($_POST['result_date']) : null;

                $up = $db->prepare("
                    UPDATE elections 
                    SET title = ?, election_date = ?, venue = ?, visibility = ?,
                        nomination_start = ?, nomination_end = ?, scrutiny_date = ?, 
                        withdrawal_deadline = ?, final_candidate_date = ?, voting_start_time = ?, 
                        voting_end_time = ?, counting_date = ?, result_date = ? 
                    WHERE id = ?
                ");
                $up->execute([
                    $title, $date, $venue, $visibility,
                    $nom_start, $nom_end, $scrutiny, $withdraw,
                    $final_cand, $v_start, $v_end, $counting, $result, $id
                ]);

                $audit = $db->prepare("INSERT INTO election_history (election_id, action, module, record_id, remarks, performed_by) VALUES (?, 'Schedule Updated', 'elections', ?, 'चुनाव कार्यक्रम विवरण संशोधित किए गए।', ?)");
                $audit->execute([$id, $id, $_SESSION['user_id']]);

                $db->commit();
                $_SESSION['flash_success'] = 'चुनाव कार्यक्रम सफलतापूर्वक संशोधित किया गया।';
                header("Location: view.php?id=$id&tab=overview");
                exit;

            } elseif ($action === 'add_post') {
                $post_name = sanitize(trim($_POST['post_name'] ?? ''));
                $post_name_hindi = sanitize(trim($_POST['post_name_hindi'] ?? ''));
                $seats = intval($_POST['number_of_seats'] ?? 1);
                $order = intval($_POST['display_order'] ?? 0);

                if (empty($post_name)) {
                    throw new Exception('कृपया पद का अंग्रेजी नाम अवश्य भरें।');
                }

                $ins = $db->prepare("INSERT INTO election_posts (election_id, post_name, post_name_hindi, number_of_seats, display_order) VALUES (?, ?, ?, ?, ?)");
                $ins->execute([$id, $post_name, $post_name_hindi, $seats, $order]);

                $db->commit();
                $_SESSION['flash_success'] = 'सफलतापूर्वक नया पद जोड़ा गया।';
                header("Location: view.php?id=$id&tab=posts");
                exit;

            } elseif ($action === 'update_voter') {
                $voter_id = intval($_POST['voter_id'] ?? 0);
                $eligibility = sanitize($_POST['eligibility_status'] ?? 'pending_review');
                $reason = sanitize(trim($_POST['eligibility_reason'] ?? ''));
                $voter_no = sanitize(trim($_POST['voter_no'] ?? ''));

                if ($voter_id <= 0) {
                    throw new Exception('अमान्य मतदाता क्रमांक।');
                }

                // Check finalized
                $voter_stmt = $db->prepare("SELECT finalized FROM election_voters WHERE id = ?");
                $voter_stmt->execute([$voter_id]);
                if ($voter_stmt->fetchColumn() > 0) {
                    throw new Exception('मतदाता सूची फाइनल होने के उपरांत व्यक्तिगत संशोधन लॉक है।');
                }

                $up = $db->prepare("UPDATE election_voters SET eligibility_status = ?, eligibility_reason = ?, voter_no = ? WHERE id = ?");
                $up->execute([$eligibility, $reason, $voter_no, $voter_id]);

                $db->commit();
                $_SESSION['flash_success'] = 'मतदाता की पात्रता अद्यतन की गई।';
                header("Location: view.php?id=$id&tab=voters");
                exit;

            } elseif ($action === 'auto_number_voters') {
                // Fetch all eligible voters sorted by membership no or name
                $stmt = $db->prepare("SELECT id FROM election_voters WHERE election_id = ? AND eligibility_status = 'eligible' ORDER BY membership_no ASC");
                $stmt->execute([$id]);
                $voters_list = $stmt->fetchAll();

                $prefix = sanitize(trim($_POST['prefix'] ?? ''));
                $start = intval($_POST['start_no'] ?? 1);

                $up = $db->prepare("UPDATE election_voters SET voter_no = ? WHERE id = ?");
                foreach ($voters_list as $v) {
                    $num_str = $prefix . sprintf("%03d", $start++);
                    $up->execute([$num_str, $v['id']]);
                }

                $audit = $db->prepare("INSERT INTO election_history (election_id, action, module, record_id, remarks, performed_by) VALUES (?, 'Voters AutoNumbered', 'voters', ?, 'पात्र मतदाताओं को क्रमांक आबंटित किए गए।', ?)");
                $audit->execute([$id, $id, $_SESSION['user_id']]);

                $db->commit();
                $_SESSION['flash_success'] = 'पात्र मतदाताओं को स्वतः क्रमिक संख्या आबंटित कर दी गई है।';
                header("Location: view.php?id=$id&tab=voters");
                exit;

            } elseif ($action === 'finalize_voters') {
                // Verify pending reviews
                $pend_stmt = $db->prepare("SELECT COUNT(*) FROM election_voters WHERE election_id = ? AND eligibility_status = 'pending_review'");
                $pend_stmt->execute([$id]);
                $pending_count = $pend_stmt->fetchColumn();

                if ($pending_count > 0 && empty($_POST['override_pending'])) {
                    throw new Exception("समीक्षा लंबित (Pending Review) $pending_count प्रविष्टियां शेष हैं। पहले समीक्षा पूर्ण करें।");
                }

                // Finalize Voter list snapshot lock
                $up = $db->prepare("UPDATE election_voters SET finalized = 1 WHERE election_id = ?");
                $up->execute([$id]);

                // Update election status
                $el_up = $db->prepare("UPDATE elections SET status = 'voter_list_finalized' WHERE id = ?");
                $el_up->execute([$id]);

                $audit = $db->prepare("INSERT INTO election_history (election_id, action, module, record_id, remarks, performed_by) VALUES (?, 'Voter List Finalized', 'voters', ?, 'मतदाता सूची अंतिम रूप से फ्रीज एवं लॉक की गई।', ?)");
                $audit->execute([$id, $id, $_SESSION['user_id']]);

                $db->commit();
                $_SESSION['flash_success'] = 'मतदाता सूची सफलतापूर्वक फाइनल एवं लॉक कर दी गई है।';
                header("Location: view.php?id=$id&tab=voters");
                exit;

            } elseif ($action === 'add_candidate') {
                $post_id = intval($_POST['post_id'] ?? 0);
                $member_id = intval($_POST['member_id'] ?? 0);
                $nom_no = sanitize(trim($_POST['nomination_no'] ?? ''));
                $nom_date = !empty($_POST['nomination_date']) ? sanitize($_POST['nomination_date']) : null;
                $proposer = !empty($_POST['proposer_member_id']) ? intval($_POST['proposer_member_id']) : null;
                $seconder = !empty($_POST['seconder_member_id']) ? intval($_POST['seconder_member_id']) : null;
                $remarks = sanitize(trim($_POST['remarks'] ?? ''));

                if ($post_id <= 0 || $member_id <= 0) {
                    throw new Exception('कृपया पद एवं नामांकित सदस्य अवश्य चुनें।');
                }

                // Verify member exist and fetch snapshot data
                $m_stmt = $db->prepare("SELECT * FROM members WHERE id = ?");
                $m_stmt->execute([$member_id]);
                $m = $m_stmt->fetch();
                if (!$m) {
                    throw new Exception('नामांकित सदस्य डेटाबेस में नहीं मिला।');
                }

                // Insert Candidate
                $ins = $db->prepare("
                    INSERT INTO election_candidates 
                    (election_id, post_id, member_id, nomination_no, nomination_date, status, 
                     name_snapshot, membership_no_snapshot, enrollment_no_snapshot, photo_snapshot,
                     proposer_member_id, seconder_member_id, remarks)
                    VALUES (?, ?, ?, ?, ?, 'nominated', ?, ?, ?, ?, ?, ?, ?)
                ");
                $ins->execute([
                    $id, $post_id, $member_id, $nom_no, $nom_date,
                    $m['full_name'], $m['membership_no'], $m['enrollment_no'], $m['photo'] ?: 'default_advocate.png',
                    $proposer, $seconder, $remarks
                ]);

                $db->commit();
                $_SESSION['flash_success'] = 'प्रत्याशी नामांकन सफलतापूर्वक पंजीकृत किया गया।';
                header("Location: view.php?id=$id&tab=candidates");
                exit;

            } elseif ($action === 'scrutiny_candidate') {
                $cand_id = intval($_POST['candidate_id'] ?? 0);
                $status = sanitize($_POST['status'] ?? 'under_scrutiny');
                $remarks = sanitize(trim($_POST['remarks'] ?? ''));

                if ($cand_id <= 0) {
                    throw new Exception('अमान्य प्रत्याशी आईडी।');
                }

                $up = $db->prepare("UPDATE election_candidates SET status = ?, remarks = ? WHERE id = ?");
                $up->execute([$status, $remarks, $cand_id]);

                $db->commit();
                $_SESSION['flash_success'] = 'नामांकन जांच परिणाम सफलतापूर्वक सहेजा गया।';
                header("Location: view.php?id=$id&tab=candidates");
                exit;

            } elseif ($action === 'withdraw_candidate') {
                $cand_id = intval($_POST['candidate_id'] ?? 0);
                $w_date = sanitize(trim($_POST['withdrawal_date'] ?? ''));
                $reason = sanitize(trim($_POST['remarks'] ?? ''));

                if ($cand_id <= 0 || empty($w_date)) {
                    throw new Exception('अमान्य प्रत्याशी या नाम वापसी तिथि।');
                }

                $up = $db->prepare("UPDATE election_candidates SET status = 'withdrawn', remarks = ? WHERE id = ?");
                $up->execute(["नाम वापसी: $reason ($w_date)", $cand_id]);

                $db->commit();
                $_SESSION['flash_success'] = 'प्रत्याशी नाम वापसी सफलतापूर्वक दर्ज की गई।';
                header("Location: view.php?id=$id&tab=candidates");
                exit;

            } elseif ($action === 'finalize_candidates') {
                // Update candidates status to final_candidate if accepted
                $up = $db->prepare("UPDATE election_candidates SET status = 'final_candidate' WHERE election_id = ? AND status = 'accepted'");
                $up->execute([$id]);

                // Update election status
                $el_up = $db->prepare("UPDATE elections SET status = 'candidate_finalized' WHERE id = ?");
                $el_up->execute([$id]);

                // Auto order ballot order
                $stmt = $db->prepare("SELECT id FROM election_candidates WHERE election_id = ? AND status = 'final_candidate' ORDER BY id ASC");
                $stmt->execute([$id]);
                $cands = $stmt->fetchAll();
                
                $order_up = $db->prepare("UPDATE election_candidates SET ballot_order = ? WHERE id = ?");
                $bo = 1;
                foreach ($cands as $c) {
                    $order_up->execute([$bo++, $c['id']]);
                }

                $audit = $db->prepare("INSERT INTO election_history (election_id, action, module, record_id, remarks, performed_by) VALUES (?, 'Candidate List Finalized', 'candidates', ?, 'अंतिम प्रत्याशी सूची फ्रिज की गई।', ?)");
                $audit->execute([$id, $id, $_SESSION['user_id']]);

                $db->commit();
                $_SESSION['flash_success'] = 'अंतिम प्रत्याशी सूची सफलतापूर्वक फाइनल एवं मतपत्र प्राथमिकता नियत कर दी गई है।';
                header("Location: view.php?id=$id&tab=candidates");
                exit;

            } elseif ($action === 'save_results') {
                $results_array = $_POST['results'] ?? [];
                
                $ins = $db->prepare("
                    INSERT INTO election_results 
                    (election_id, post_id, candidate_id, votes_received, result_status, declared_by, declared_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE 
                        votes_received = VALUES(votes_received),
                        result_status = VALUES(result_status),
                        declared_by = VALUES(declared_by),
                        declared_at = VALUES(declared_at)
                ");

                foreach ($results_array as $cand_id => $val) {
                    $post_id = intval($val['post_id']);
                    $votes = ($val['votes'] !== '') ? intval($val['votes']) : null;
                    $status = sanitize($val['status']);

                    // Verify candidate belongs to the post and election
                    $check = $db->prepare("SELECT COUNT(*) FROM election_candidates WHERE id = ? AND election_id = ? AND post_id = ?");
                    $check->execute([$cand_id, $id, $post_id]);
                    if ($check->fetchColumn() == 0) {
                        throw new Exception('अवैध प्रत्याशी विवरण मिला। सत्यापन विफल।');
                    }

                    $ins->execute([$id, $post_id, $cand_id, $votes, $status, $_SESSION['user_id']]);
                }

                // If result require approval config is disabled, auto declare election status as result_declared
                if (!ELECTION_RESULT_REQUIRE_APPROVAL) {
                    $el_up = $db->prepare("UPDATE elections SET status = 'result_declared' WHERE id = ?");
                    $el_up->execute([$id]);
                }

                $audit = $db->prepare("INSERT INTO election_history (election_id, action, module, record_id, remarks, performed_by) VALUES (?, 'Result Entered', 'results', ?, 'चुनाव परिणाम दर्ज किए गए।', ?)");
                $audit->execute([$id, $id, $_SESSION['user_id']]);

                $db->commit();
                $_SESSION['flash_success'] = 'चुनाव परिणाम सफलतापूर्वक सहेज लिए गए हैं।';
                header("Location: view.php?id=$id&tab=results");
                exit;

            } elseif ($action === 'upload_document') {
                $title = sanitize(trim($_POST['doc_title'] ?? ''));
                $doc_type = sanitize($_POST['document_type'] ?? 'Other');
                $visibility = sanitize($_POST['doc_visibility'] ?? 'public');

                if (empty($title) || empty($_FILES['doc_file']['name'])) {
                    throw new Exception('कृपया प्रलेख शीर्षक एवं फाइल अवश्य संलग्न करें।');
                }

                // Upload logic
                $target_dir = __DIR__ . '/../../uploads/elections/';
                if (!is_dir($target_dir)) {
                    mkdir($target_dir, 0777, true);
                }

                $file_name = time() . '_' . basename($_FILES['doc_file']['name']);
                $target_file = $target_dir . $file_name;

                if (move_uploaded_file($_FILES['doc_file']['tmp_name'], $target_file)) {
                    $file_path = 'uploads/elections/' . $file_name;

                    $ins = $db->prepare("INSERT INTO election_documents (election_id, title, document_type, file_path, visibility, status, uploaded_by) VALUES (?, ?, ?, ?, ?, 'published', ?)");
                    $ins->execute([$id, $title, $doc_type, $file_path, $visibility, $_SESSION['user_id']]);

                    $db->commit();
                    $_SESSION['flash_success'] = 'दस्तावेज सफलतापूर्वक अपलोड एवं प्रकाशित किया गया।';
                    header("Location: view.php?id=$id&tab=documents");
                    exit;
                } else {
                    throw new Exception('सर्वर पर दस्तावेज फाइल अपलोड करने में विफलता।');
                }
            }

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = 'कार्रवाई करने में विफलता: ' . $e->getMessage();
        }
    }
}

// -----------------------------
// VIEW DATA RETRIEVAL SECTION
// -----------------------------
// Session Flash message retrieve
if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

// Fetch all members list for nominee dropdowns
$members_list = [];
if ($db) {
    try {
        $members_list = $db->query("SELECT id, full_name, membership_no, enrollment_no FROM members WHERE membership_status = 'active' ORDER BY full_name ASC")->fetchAll();
    } catch (PDOException $e) {}
}

// 1. Fetch Posts
$posts = [];
if ($db) {
    $posts = $db->query("SELECT * FROM election_posts WHERE election_id = $id ORDER BY display_order ASC")->fetchAll();
}

// 2. Fetch Voters with filters
$voters = [];
$search = sanitize($_GET['search'] ?? '');
$v_filter = sanitize($_GET['eligibility'] ?? '');
if ($db) {
    $sql = "SELECT * FROM election_voters WHERE election_id = $id";
    $p = [];
    if (!empty($search)) {
        $sql .= " AND (member_name LIKE ? OR membership_no LIKE ? OR enrollment_no LIKE ? OR voter_no LIKE ?)";
        $sp = "%$search%";
        $p[] = $sp; $p[] = $sp; $p[] = $sp; $p[] = $sp;
    }
    if (!empty($v_filter)) {
        $sql .= " AND eligibility_status = ?";
        $p[] = $v_filter;
    }
    $sql .= " ORDER BY CAST(voter_no AS UNSIGNED) ASC, voter_no ASC, id ASC";
    $v_stmt = $db->prepare($sql);
    $v_stmt->execute($p);
    $voters = $v_stmt->fetchAll();
}

// 3. Fetch Candidates grouped by posts
$candidates = [];
if ($db) {
    $candidates = $db->query("
        SELECT c.*, p.post_name, p.post_name_hindi 
        FROM election_candidates c
        JOIN election_posts p ON p.id = c.post_id
        WHERE c.election_id = $id
        ORDER BY p.display_order ASC, c.ballot_order ASC, c.id ASC
    ")->fetchAll();
}

// 4. Fetch Documents
$documents = [];
if ($db) {
    $documents = $db->query("SELECT * FROM election_documents WHERE election_id = $id ORDER BY id DESC")->fetchAll();
}

// 5. Fetch Results
$results = [];
if ($db) {
    $res_stmt = $db->query("
        SELECT r.*, c.name_snapshot, c.membership_no_snapshot, p.post_name, p.post_name_hindi 
        FROM election_results r
        JOIN election_candidates c ON c.id = r.candidate_id
        JOIN election_posts p ON p.id = r.post_id
        WHERE r.election_id = $id
    ");
    $results = $res_stmt->fetchAll();
}

// 6. Fetch History logs
$history = [];
if ($db) {
    $history = $db->query("
        SELECT h.*, u.username 
        FROM election_history h
        JOIN users u ON u.id = h.performed_by
        WHERE h.election_id = $id
        ORDER BY h.id DESC
    ")->fetchAll();
}

// Phase 10 Notices
$notices = [];
if ($db) {
    $notices = $db->query("SELECT * FROM notices WHERE election_id = $id ORDER BY id DESC")->fetchAll();
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">चुनाव प्रबंधन समीक्षा पटल (Election Console)</h4>
    <div class="d-flex gap-2">
        <a href="index.php" class="btn btn-xs btn-outline-navy"><i class="bi bi-arrow-left me-1"></i>चुनाव सूची</a>
    </div>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 font-hindi small"><?php echo $error; ?></div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success py-2 font-hindi small"><?php echo $success; ?></div>
<?php endif; ?>

<!-- Header Stage Panel -->
<div class="card border-0 shadow-sm mb-4 font-hindi small bg-light">
    <div class="card-body p-3 d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h5 class="fw-bold text-navy-custom mb-1"><?php echo e($election['title']); ?> (Code: <?php echo e($election['election_code']); ?>)</h5>
            <span class="text-muted"><i class="bi bi-calendar-check me-1"></i>वर्ष: <?php echo e($election['election_year']); ?> | मतदान तिथि: <?php echo date('d-m-Y', strtotime($election['election_date'])); ?></span>
        </div>
        
        <!-- Status Changer Form -->
        <form method="POST" action="" class="d-flex align-items-center gap-2 mt-2 mt-md-0">
            <?php insertCSRF(); ?>
            <input type="hidden" name="action" value="update_status">
            <label class="fw-bold mb-0 text-navy-custom">चुनाव वर्तमान स्टेज:</label>
            <select name="status" id="electionStatusSelect" class="form-select form-select-sm fw-bold border-navy">
                <?php 
                $stages = [
                    'draft' => 'Draft (प्रारूप)',
                    'announced' => 'Announced (घोषित)',
                    'nomination_open' => 'Nomination Open (नामांकन शुरू)',
                    'scrutiny' => 'Nomination Scrutiny (जांच चरण)',
                    'withdrawal' => 'Withdrawal Open (नाम वापसी)',
                    'candidate_finalized' => 'Candidate Finalized',
                    'voter_list_finalized' => 'Voter List Finalized',
                    'polling_scheduled' => 'Polling Scheduled',
                    'polling_completed' => 'Polling Completed',
                    'counting' => 'Counting (गणना)',
                    'result_declared' => 'Result Declared',
                    'completed' => 'Completed',
                    'cancelled' => 'Cancelled'
                ];
                foreach ($stages as $st => $lbl) {
                    $sel = ($election['status'] === $st) ? 'selected' : '';
                    echo "<option value=\"$st\" $sel>$lbl</option>";
                }
                ?>
            </select>
        </form>
    </div>
</div>

<!-- Tabs Navigation -->
<div class="bg-white rounded border border-light p-1 shadow-xs mb-4 font-hindi small">
    <ul class="nav nav-tabs nav-fill" id="electionTabs">
        <li class="nav-item">
            <a class="nav-link <?php echo ($active_tab === 'overview') ? 'active fw-bold text-navy-custom' : 'text-muted'; ?>" href="?id=<?php echo $id; ?>&tab=overview"><i class="bi bi-info-circle me-1"></i>अवलोकन व कार्यक्रम</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($active_tab === 'posts') ? 'active fw-bold text-navy-custom' : 'text-muted'; ?>" href="?id=<?php echo $id; ?>&tab=posts"><i class="bi bi-briefcase me-1"></i>पद विवरण (Posts)</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($active_tab === 'voters') ? 'active fw-bold text-navy-custom' : 'text-muted'; ?>" href="?id=<?php echo $id; ?>&tab=voters"><i class="bi bi-people me-1"></i>मतदाता सूची (Voters)</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($active_tab === 'candidates') ? 'active fw-bold text-navy-custom' : 'text-muted'; ?>" href="?id=<?php echo $id; ?>&tab=candidates"><i class="bi bi-person-badge me-1"></i>प्रत्याशी (Candidates)</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($active_tab === 'notices') ? 'active fw-bold text-navy-custom' : 'text-muted'; ?>" href="?id=<?php echo $id; ?>&tab=notices"><i class="bi bi-bell me-1"></i>चुनाव नोटिस</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($active_tab === 'results') ? 'active fw-bold text-navy-custom' : 'text-muted'; ?>" href="?id=<?php echo $id; ?>&tab=results"><i class="bi bi-check2-square me-1"></i>चुनाव परिणाम (Results)</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($active_tab === 'documents') ? 'active fw-bold text-navy-custom' : 'text-muted'; ?>" href="?id=<?php echo $id; ?>&tab=documents"><i class="bi bi-file-earmark-pdf me-1"></i>दस्तावेज (Docs)</a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?php echo ($active_tab === 'history') ? 'active fw-bold text-navy-custom' : 'text-muted'; ?>" href="?id=<?php echo $id; ?>&tab=history"><i class="bi bi-clock-history me-1"></i>इतिहास (Audit)</a>
        </li>
    </ul>
</div>

<!-- Tabs Contents -->
<div class="tab-content font-hindi small text-navy-custom">
    
    <!-- Tab 1: Overview & Schedule -->
    <?php if ($active_tab === 'overview'): ?>
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-navy-custom text-white py-2">
                        <h6 class="mb-0 fw-bold">चुनाव कार्यक्रम विवरण संपादित करें (Schedule Edit)</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <?php insertCSRF(); ?>
                            <input type="hidden" name="action" value="update_schedule">

                            <div class="mb-3">
                                <label class="form-label fw-bold">चुनाव का शीर्षक (Title)</label>
                                <input type="text" name="title" class="form-control form-control-sm" required value="<?php echo e($election['title']); ?>">
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-bold">मतदान तिथि (Polling Date)</label>
                                    <input type="date" name="election_date" class="form-control form-control-sm" required value="<?php echo e($election['election_date']); ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold">मतदान स्थल (Venue)</label>
                                    <input type="text" name="venue" class="form-control form-control-sm" value="<?php echo e($election['venue']); ?>">
                                </div>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-bold">नामांकन प्रपत्र प्रारंभ</label>
                                    <input type="datetime-local" name="nomination_start" class="form-control form-control-sm" value="<?php echo $election['nomination_start'] ? date('Y-m-d\TH:i', strtotime($election['nomination_start'])) : ''; ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold">नामांकन प्रपत्र अंतिम तिथि</label>
                                    <input type="datetime-local" name="nomination_end" class="form-control form-control-sm" value="<?php echo $election['nomination_end'] ? date('Y-m-d\TH:i', strtotime($election['nomination_end'])) : ''; ?>">
                                </div>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-4">
                                    <label class="form-label fw-bold">जांच तिथि</label>
                                    <input type="date" name="scrutiny_date" class="form-control form-control-sm" value="<?php echo e($election['scrutiny_date']); ?>">
                                </div>
                                <div class="col-4">
                                    <label class="form-label fw-bold">नाम वापसी अंतिम तिथि</label>
                                    <input type="datetime-local" name="withdrawal_deadline" class="form-control form-control-sm" value="<?php echo $election['withdrawal_deadline'] ? date('Y-m-d\TH:i', strtotime($election['withdrawal_deadline'])) : ''; ?>">
                                </div>
                                <div class="col-4">
                                    <label class="form-label fw-bold">अंतिम प्रत्याशी प्रकाशन</label>
                                    <input type="date" name="final_candidate_date" class="form-control form-control-sm" value="<?php echo e($election['final_candidate_date']); ?>">
                                </div>
                            </div>

                            <div class="row g-2 mb-3">
                                <div class="col-3">
                                    <label class="form-label fw-bold">मतदान प्रारंभ</label>
                                    <input type="time" name="voting_start_time" class="form-control form-control-sm" value="<?php echo e($election['voting_start_time']); ?>">
                                </div>
                                <div class="col-3">
                                    <label class="form-label fw-bold">मतदान समाप्त</label>
                                    <input type="time" name="voting_end_time" class="form-control form-control-sm" value="<?php echo e($election['voting_end_time']); ?>">
                                </div>
                                <div class="col-3">
                                    <label class="form-label fw-bold">मतगणना तिथि</label>
                                    <input type="date" name="counting_date" class="form-control form-control-sm" value="<?php echo e($election['counting_date']); ?>">
                                </div>
                                <div class="col-3">
                                    <label class="form-label fw-bold">परिणाम तिथि</label>
                                    <input type="date" name="result_date" class="form-control form-control-sm" value="<?php echo e($election['result_date']); ?>">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">दृश्यता (Visibility)</label>
                                <select name="visibility" class="form-select form-select-sm">
                                    <option value="private" <?php echo ($election['visibility'] === 'private') ? 'selected' : ''; ?>>Private</option>
                                    <option value="members_only" <?php echo ($election['visibility'] === 'members_only') ? 'selected' : ''; ?>>Members Only</option>
                                    <option value="public" <?php echo ($election['visibility'] === 'public') ? 'selected' : ''; ?>>Public</option>
                                </select>
                            </div>

                            <div class="text-end">
                                <button type="submit" class="btn btn-navy btn-sm px-4">सहेजें (Save Changes)</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right Column: Visual Timeline -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light py-2">
                        <h6 class="mb-0 fw-bold text-navy-custom"><i class="bi bi-clock me-2"></i>घोषित कार्यक्रम सारणी (Timeline)</h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled position-relative border-start border-2 ps-3 py-2 ms-2">
                            <?php 
                            $timeline = [
                                'नामंकन प्रपत्र प्रारंभ' => $election['nomination_start'] ? date('d-m-Y H:i', strtotime($election['nomination_start'])) : 'Date To Be Announced',
                                'नामांकन प्रपत्र अंतिम तिथि' => $election['nomination_end'] ? date('d-m-Y H:i', strtotime($election['nomination_end'])) : 'Date To Be Announced',
                                'नामांकन पत्रों की जांच' => $election['scrutiny_date'] ? date('d-m-Y', strtotime($election['scrutiny_date'])) : 'Date To Be Announced',
                                'नाम वापसी की सीमा' => $election['withdrawal_deadline'] ? date('d-m-Y H:i', strtotime($election['withdrawal_deadline'])) : 'Date To Be Announced',
                                'अंतिम प्रत्याशी सूची' => $election['final_candidate_date'] ? date('d-m-Y', strtotime($election['final_candidate_date'])) : 'Date To Be Announced',
                                'मतदान तिथि (Polling)' => date('d-m-Y', strtotime($election['election_date'])),
                                'मतगणना एवं परिणाम' => $election['counting_date'] ? date('d-m-Y', strtotime($election['counting_date'])) : 'Date To Be Announced',
                            ];

                            foreach ($timeline as $label => $val):
                                $color = ($val === 'Date To Be Announced') ? 'text-danger' : 'text-success fw-bold';
                            ?>
                                <li class="mb-3 position-relative">
                                    <div class="position-absolute bg-navy-custom rounded-circle" style="width: 10px; height: 10px; left: -20px; top: 6px;"></div>
                                    <strong class="d-block text-secondary"><?php echo $label; ?></strong>
                                    <span class="<?php echo $color; ?> english-text"><?php echo $val; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tab 2: Posts Configuration -->
    <?php if ($active_tab === 'posts'): ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-navy-custom text-white py-2">
                        <h6 class="mb-0 fw-bold">सम्बद्ध पदों की सूची (Constitutional Posts)</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>प्रदर्शित क्रम (Order)</th>
                                        <th>पद का नाम (अंग्रेजी)</th>
                                        <th>पद का नाम (हिन्दी)</th>
                                        <th>सीटों की संख्या</th>
                                        <th>स्थिति</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($posts)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-3 text-muted">कोई पद परिभाषित नहीं है।</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($posts as $p): ?>
                                            <tr>
                                                <td class="english-text text-center fw-bold">#<?php echo $p['display_order']; ?></td>
                                                <td class="fw-bold text-navy-custom"><?php echo e($p['post_name']); ?></td>
                                                <td><?php echo e($p['post_name_hindi']); ?></td>
                                                <td class="english-text text-center"><?php echo $p['number_of_seats']; ?></td>
                                                <td><span class="badge bg-success font-size-xs"><?php echo e($p['status']); ?></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add Post Form -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light py-2">
                        <h6 class="mb-0 fw-bold text-navy-custom"><i class="bi bi-plus-circle me-1"></i>नया पद जोड़ें (Add Seat)</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <?php insertCSRF(); ?>
                            <input type="hidden" name="action" value="add_post">

                            <div class="mb-2">
                                <label class="form-label fw-bold">पद नाम (English) *</label>
                                <input type="text" name="post_name" class="form-control form-control-sm" required placeholder="उदा. President, Treasurer">
                            </div>

                            <div class="mb-2">
                                <label class="form-label fw-bold">पद नाम (हिन्दी)</label>
                                <input type="text" name="post_name_hindi" class="form-control form-control-sm" placeholder="उदा. अध्यक्ष, कोषाध्यक्ष">
                            </div>

                            <div class="mb-2">
                                <label class="form-label fw-bold">सीटों की संख्या *</label>
                                <input type="number" name="number_of_seats" class="form-control form-control-sm" required value="1" min="1">
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">प्रदर्शित वरीयता क्रम *</label>
                                <input type="number" name="display_order" class="form-control form-control-sm" required value="1">
                            </div>

                            <button type="submit" class="btn btn-gold btn-sm w-100 text-navy-custom fw-bold"><i class="bi bi-check-circle-fill me-1"></i>सहेजें</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tab 3: Voter List Management -->
    <?php if ($active_tab === 'voters'): ?>
        <!-- Buttons and search toolbar -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div class="d-flex gap-1.5 no-print">
                <a href="voters/generate.php?election_id=<?php echo $id; ?>" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-arrow-repeat me-1"></i>मतदाता स्नैपशॉट जनरेट करें</a>
                <a href="print-voters.php?election_id=<?php echo $id; ?>" target="_blank" class="btn btn-xs btn-outline-navy fw-semibold"><i class="bi bi-printer me-1"></i>A4 प्रिंट</a>
            </div>

            <!-- Voters Finalize lock trigger -->
            <?php if ($election['status'] !== 'voter_list_finalized'): ?>
                <form method="POST" action="" class="d-inline" onsubmit="return confirm('क्या आप वास्तव में मतदाता सूची फाइनल और लॉक करना चाहते हैं? इसके बाद सामान्य संसोधन लॉक हो जाएंगे।');">
                    <?php insertCSRF(); ?>
                    <input type="hidden" name="action" value="finalize_voters">
                    <button type="submit" class="btn btn-xs btn-success"><i class="bi bi-lock-fill me-1"></i>मतदाता सूची फाइनल करें (Finalize Voters)</button>
                </form>
            <?php else: ?>
                <span class="badge bg-success py-1.5 px-3"><i class="bi bi-check-circle-fill me-1"></i>मतदाता सूची लॉक है (Voters Locked)</span>
            <?php endif; ?>
        </div>

        <div class="row g-3 mb-4 no-print">
            <div class="col-md-12">
                <div class="card border-0 shadow-xs p-3 bg-light border border-light">
                    <form method="GET" action="" class="row g-2">
                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                        <input type="hidden" name="tab" value="voters">
                        <div class="col-md-5">
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="नाम/सदस्यता सं/वोटर सं खोजें..." value="<?php echo e($search); ?>">
                        </div>
                        <div class="col-md-4">
                            <select name="eligibility" class="form-select form-select-sm">
                                <option value="">-- सभी पात्रता --</option>
                                <option value="eligible" <?php echo ($v_filter === 'eligible') ? 'selected' : ''; ?>>Eligible (योग्य)</option>
                                <option value="not_eligible" <?php echo ($v_filter === 'not_eligible') ? 'selected' : ''; ?>>Not Eligible (अयोग्य)</option>
                                <option value="pending_review" <?php echo ($v_filter === 'pending_review') ? 'selected' : ''; ?>>Pending Review (समीक्षा लंबित)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-navy btn-sm w-100">खोजें</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Voters Auto-Numbering Panel -->
        <?php if ($election['status'] !== 'voter_list_finalized'): ?>
            <div class="card border-0 shadow-sm mb-4 bg-light border-start border-primary border-3 no-print">
                <div class="card-body p-3">
                    <form method="POST" action="" class="row g-2 align-items-end">
                        <?php insertCSRF(); ?>
                        <input type="hidden" name="action" value="auto_number_voters">
                        <div class="col-md-12 mb-1">
                            <strong class="text-navy-custom"><i class="bi bi-hash me-1"></i>मतदाता संख्या जनरेशन (Voter Number Generator)</strong>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-0 small text-muted">वरीयता प्रीफिक्स (उदा. V- या DBA-)</label>
                            <input type="text" name="prefix" class="form-control form-control-sm" placeholder="उदा. V-">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label mb-0 small text-muted">प्रारंभिक संख्या (Start No)</label>
                            <input type="number" name="start_no" class="form-control form-control-sm" value="1" min="1" required>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-magic me-1"></i>क्रमानुसार वोटर नं भरें</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Voter List Registry Table -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-navy-custom text-white py-2">
                <h6 class="mb-0 fw-bold">मतदाता सूची विवरणी (Voter snapshot)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 450px;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>मतदाता सं</th>
                                <th>अधिवक्ता का नाम (Snapshot)</th>
                                <th>सदस्यता सं</th>
                                <th>पंजीकरण क्रमांक</th>
                                <th>चैम्बर नं</th>
                                <th>पात्रता (Eligibility)</th>
                                <th>अस्वीकृति टिप्पणी</th>
                                <?php if ($election['status'] !== 'voter_list_finalized'): ?>
                                    <th class="text-end">संशोधन</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($voters)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">कोई मतदाता प्रविष्टि नहीं मिली। मतदाता सूची जनरेट करने के लिए ऊपर जनरेट बटन का उपयोग करें।</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($voters as $v): ?>
                                    <tr>
                                        <td class="english-text fw-bold text-navy-custom"><?php echo e($v['voter_no'] ?: '-'); ?></td>
                                        <td><strong><?php echo e($v['member_name']); ?></strong></td>
                                        <td class="english-text"><?php echo e($v['membership_no']); ?></td>
                                        <td class="english-text"><?php echo e($v['enrollment_no']); ?></td>
                                        <td class="english-text"><?php echo $v['chamber_no'] ? 'Chamber ' . e($v['chamber_no']) : 'N/A'; ?></td>
                                        <td>
                                            <?php 
                                            $col = ($v['eligibility_status'] === 'eligible') ? 'bg-success' : (($v['eligibility_status'] === 'not_eligible') ? 'bg-danger' : 'bg-warning text-dark');
                                            ?>
                                            <span class="badge <?php echo $col; ?> font-size-xs"><?php echo e($v['eligibility_status']); ?></span>
                                        </td>
                                        <td><span class="text-danger small"><?php echo e($v['eligibility_reason']); ?></span></td>
                                        
                                        <?php if ($election['status'] !== 'voter_list_finalized'): ?>
                                            <td class="text-end">
                                                <button class="btn btn-xs btn-outline-navy py-0.5" data-bs-toggle="modal" data-bs-target="#voterModal<?php echo $v['id']; ?>">बदलें</button>
                                            </td>

                                            <!-- Voter Edit Modal -->
                                            <div class="modal fade" id="voterModal<?php echo $v['id']; ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-navy-custom text-white py-2">
                                                            <h6 class="modal-title fw-bold">मतदाता पात्रता संशोधन</h6>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body text-start">
                                                            <form method="POST" action="">
                                                                <?php insertCSRF(); ?>
                                                                <input type="hidden" name="action" value="update_voter">
                                                                <input type="hidden" name="voter_id" value="<?php echo $v['id']; ?>">

                                                                <div class="mb-3">
                                                                    <label class="form-label fw-bold">मतदाता संख्या (Voter No)</label>
                                                                    <input type="text" name="voter_no" class="form-control form-control-sm english-text fw-bold" value="<?php echo e($v['voter_no']); ?>">
                                                                </div>

                                                                <div class="mb-3">
                                                                    <label class="form-label fw-bold">पात्रता स्थिति (Status) *</label>
                                                                    <select name="eligibility_status" class="form-select form-select-sm" required>
                                                                        <option value="eligible" <?php echo ($v['eligibility_status'] === 'eligible') ? 'selected' : ''; ?>>Eligible (योग्य)</option>
                                                                        <option value="not_eligible" <?php echo ($v['eligibility_status'] === 'not_eligible') ? 'selected' : ''; ?>>Not Eligible (अयोग्य)</option>
                                                                        <option value="pending_review" <?php echo ($v['eligibility_status'] === 'pending_review') ? 'selected' : ''; ?>>Pending Review (लंबित)</option>
                                                                    </select>
                                                                </div>

                                                                <div class="mb-3">
                                                                    <label class="form-label fw-bold">अस्वीकृति का कारण (Remarks - Required if Not Eligible)</label>
                                                                    <textarea name="eligibility_reason" class="form-control form-control-sm" rows="2"><?php echo e($v['eligibility_reason']); ?></textarea>
                                                                </div>

                                                                <div class="text-end">
                                                                    <button type="button" class="btn btn-xs btn-outline-secondary" data-bs-dismiss="modal">बंद करें</button>
                                                                    <button type="submit" class="btn btn-xs btn-navy px-3">अपडेट करें</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tab 4: Candidates Nominee Desk -->
    <?php if ($active_tab === 'candidates'): ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold border-bottom pb-1 text-navy-custom mb-0"><i class="bi bi-person-badge-fill me-1"></i>नामांकित प्रत्याशियों की समीक्षा सूची (Nominees)</h6>
            
            <?php if ($election['status'] !== 'candidate_finalized'): ?>
                <form method="POST" action="" class="d-inline" onsubmit="return confirm('क्या आप प्रत्याशी सूची फाइनल करना चाहते हैं? इसके बाद नए नामांकन पत्र दर्ज नहीं होंगे।');">
                    <?php insertCSRF(); ?>
                    <input type="hidden" name="action" value="finalize_candidates">
                    <button type="submit" class="btn btn-xs btn-success"><i class="bi bg-lock me-1"></i>प्रत्याशी सूची फाइनल करें (Finalize Candidates)</button>
                </form>
            <?php else: ?>
                <span class="badge bg-success py-1.5 px-3"><i class="bi bi-check-circle-fill me-1"></i>प्रत्याशी सूची लॉक है (Candidates Finalized)</span>
            <?php endif; ?>
        </div>

        <div class="row g-4">
            <!-- Nominees list grid -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>प्राथमिकता</th>
                                        <th>फोटो</th>
                                        <th>प्रत्याशी का नाम</th>
                                        <th>चुनाव पद (Post)</th>
                                        <th>नामांकन सं.</th>
                                        <th>स्थिति</th>
                                        <th class="text-end">कार्यवाही</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($candidates)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">कोई भी नामांकन दर्ज नहीं मिला। नामांकन फॉर्म भरने के लिए दाएं पैनल का उपयोग करें।</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($candidates as $c): ?>
                                            <tr>
                                                <td class="english-text text-center fw-bold"><?php echo $c['ballot_order'] ? '#' . $c['ballot_order'] : '-'; ?></td>
                                                <td>
                                                    <img src="<?php echo SITE_URL; ?>/uploads/profile/<?php echo $c['photo_snapshot']; ?>" class="rounded" style="width: 32px; height: 32px; object-fit: cover;" onerror="this.src='<?php echo SITE_URL; ?>/assets/images/default_advocate.png'">
                                                </td>
                                                <td>
                                                    <strong><?php echo e($c['name_snapshot']); ?></strong><br>
                                                    <span class="text-muted font-size-xs" style="font-size:0.7rem;">Code: <?php echo e($c['membership_no_snapshot']); ?></span>
                                                </td>
                                                <td>
                                                    <strong><?php echo e($c['post_name_hindi']); ?></strong><br>
                                                    <span class="text-muted font-size-xs" style="font-size:0.7rem;"><?php echo e($c['post_name']); ?></span>
                                                </td>
                                                <td class="english-text"><?php echo e($c['nomination_no'] ?: '-'); ?></td>
                                                <td>
                                                    <?php 
                                                    $co = ($c['status'] === 'accepted' || $c['status'] === 'final_candidate') ? 'bg-success' : (($c['status'] === 'rejected' || $c['status'] === 'withdrawn') ? 'bg-danger' : 'bg-warning text-dark');
                                                    ?>
                                                    <span class="badge <?php echo $co; ?> font-size-xs"><?php echo e(ucfirst($c['status'])); ?></span>
                                                </td>
                                                <td class="text-end">
                                                    <div class="d-flex justify-content-end gap-1">
                                                        <button class="btn btn-xs btn-outline-navy py-0.5" data-bs-toggle="modal" data-bs-target="#scrutinyModal<?php echo $c['id']; ?>">जांच</button>
                                                        <button class="btn btn-xs btn-outline-danger py-0.5" data-bs-toggle="modal" data-bs-target="#withdrawModal<?php echo $c['id']; ?>">नाम वापसी</button>
                                                    </div>
                                                </td>
                                            </tr>

                                            <!-- Scrutiny Modal -->
                                            <div class="modal fade" id="scrutinyModal<?php echo $c['id']; ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-navy-custom text-white py-2">
                                                            <h6 class="modal-title fw-bold">नामांकन प्रपत्र जांच (Scrutiny)</h6>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body text-start">
                                                            <form method="POST" action="">
                                                                <?php insertCSRF(); ?>
                                                                <input type="hidden" name="action" value="scrutiny_candidate">
                                                                <input type="hidden" name="candidate_id" value="<?php echo $c['id']; ?>">

                                                                <div class="mb-3">
                                                                    <label class="form-label fw-bold">जांच स्थिति (Scrutiny Outcome) *</label>
                                                                    <select name="status" class="form-select form-select-sm" required>
                                                                        <option value="under_scrutiny" <?php echo ($c['status'] === 'under_scrutiny') ? 'selected' : ''; ?>>Under Scrutiny (जांच जारी)</option>
                                                                        <option value="accepted" <?php echo ($c['status'] === 'accepted') ? 'selected' : ''; ?>>Accepted (स्वीकृत)</option>
                                                                        <option value="rejected" <?php echo ($c['status'] === 'rejected') ? 'selected' : ''; ?>>Rejected (अस्वीकृत)</option>
                                                                    </select>
                                                                </div>

                                                                <div class="mb-3">
                                                                    <label class="form-label fw-bold">अस्वीकृति या जांच टिप्पणी (Remarks)</label>
                                                                    <textarea name="remarks" class="form-control form-control-sm" rows="2"><?php echo e($c['remarks']); ?></textarea>
                                                                </div>

                                                                <div class="text-end">
                                                                    <button type="button" class="btn btn-xs btn-outline-secondary" data-bs-dismiss="modal">बंद करें</button>
                                                                    <button type="submit" class="btn btn-xs btn-navy px-3">अपडेट करें</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Withdrawal Modal -->
                                            <div class="modal fade" id="withdrawModal<?php echo $c['id']; ?>" tabindex="-1" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-danger text-white py-2">
                                                            <h6 class="modal-title fw-bold">नामांकन वापसी (Nominee Withdrawal)</h6>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body text-start">
                                                            <form method="POST" action="">
                                                                <?php insertCSRF(); ?>
                                                                <input type="hidden" name="action" value="withdraw_candidate">
                                                                <input type="hidden" name="candidate_id" value="<?php echo $c['id']; ?>">

                                                                <div class="mb-3">
                                                                    <label class="form-label fw-bold">नाम वापसी की तिथि *</label>
                                                                    <input type="date" name="withdrawal_date" class="form-control form-control-sm" required value="<?php echo date('Y-m-d'); ?>">
                                                                </div>

                                                                <div class="mb-3">
                                                                    <label class="form-label fw-bold">कारण / विशेष टिप्पणी</label>
                                                                    <textarea name="remarks" class="form-control form-control-sm" rows="2" placeholder="स्वैच्छिक त्यागपत्र, स्वास्थ्य कारण आदि..."></textarea>
                                                                </div>

                                                                <div class="text-end">
                                                                    <button type="button" class="btn btn-xs btn-outline-secondary" data-bs-dismiss="modal">बंद करें</button>
                                                                    <button type="submit" class="btn btn-xs btn-danger px-3">नाम वापस लें</button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Candidate Nomination Register Form -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light py-2">
                        <h6 class="mb-0 fw-bold text-navy-custom"><i class="bi bi-plus-circle me-1"></i>नामांकन प्रविष्टि फॉर्म (Nomination Form)</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <?php insertCSRF(); ?>
                            <input type="hidden" name="action" value="add_candidate">

                            <div class="mb-2">
                                <label class="form-label fw-bold">चुनाव पद (Post) *</label>
                                <select name="post_id" class="form-select form-select-sm" required>
                                    <option value="">-- पद चुनें --</option>
                                    <?php foreach ($posts as $p): ?>
                                        <option value="<?php echo $p['id']; ?>"><?php echo e($p['post_name_hindi']); ?> (<?php echo e($p['post_name']); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-2">
                                <label class="form-label fw-bold">नामांकित अधिवक्ता सदस्य *</label>
                                <select name="member_id" class="form-select form-select-sm" required>
                                    <option value="">-- प्रत्याशी चुनें --</option>
                                    <?php foreach ($members_list as $m): ?>
                                        <option value="<?php echo $m['id']; ?>"><?php echo e($m['full_name']); ?> (<?php echo e($m['membership_no']); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label fw-bold">नामांकन पत्र सं.</label>
                                    <input type="text" name="nomination_no" class="form-control form-control-sm" placeholder="उदा. NOM-01">
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold">नामांकन तिथि</label>
                                    <input type="date" name="nomination_date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>

                            <div class="mb-2">
                                <label class="form-label fw-bold">प्रस्तावक सदस्य (Proposer - Optional)</label>
                                <select name="proposer_member_id" class="form-select form-select-sm">
                                    <option value="">-- प्रस्तावक सदस्य --</option>
                                    <?php foreach ($members_list as $m): ?>
                                        <option value="<?php echo $m['id']; ?>"><?php echo e($m['full_name']); ?> (<?php echo e($m['membership_no']); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-2">
                                <label class="form-label fw-bold">समर्थक सदस्य (Seconder - Optional)</label>
                                <select name="seconder_member_id" class="form-select form-select-sm">
                                    <option value="">-- समर्थक सदस्य --</option>
                                    <?php foreach ($members_list as $m): ?>
                                        <option value="<?php echo $m['id']; ?>"><?php echo e($m['full_name']); ?> (<?php echo e($m['membership_no']); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">टिप्पणी</label>
                                <textarea name="remarks" class="form-control form-control-sm" rows="1" placeholder="नामांकन शुल्क ब्यौरा या अन्य सूचना..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-navy btn-sm w-100 fw-bold"><i class="bi bi-check-circle-fill me-1"></i>नामांकन सहेजें</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tab 5: Notices -->
    <?php if ($active_tab === 'notices'): ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0 text-navy-custom">इस चुनाव से सम्बद्ध सूचनाएं (Election Bulletins)</h6>
            <a href="../notices/create.php?election_id=<?php echo $id; ?>" class="btn btn-xs btn-gold text-navy-custom fw-bold"><i class="bi bi-plus-circle me-1"></i>नया चुनाव नोटिस लिखें</a>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>नोटीस क्रमांक</th>
                                <th>शीर्षक</th>
                                <th>श्रेणी</th>
                                <th>प्राथमिकता</th>
                                <th>दिनांक</th>
                                <th>स्थिति</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($notices)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-3 text-muted">कोई भी नोटिस सम्बद्ध नहीं है। नया नोटिस जोड़ने के लिए ऊपर बटन दबाएं।</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($notices as $n): ?>
                                    <tr>
                                        <td class="english-text"><?php echo e($n['notice_no']); ?></td>
                                        <td><strong><?php echo e($n['title']); ?></strong></td>
                                        <td><?php echo e($n['category']); ?></td>
                                        <td><span class="badge bg-light text-dark border"><?php echo e($n['priority']); ?></span></td>
                                        <td class="english-text"><?php echo date('d-m-Y', strtotime($n['publish_at'])); ?></td>
                                        <td><span class="badge bg-success font-size-xs"><?php echo e($n['status']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tab 6: Results Management -->
    <?php if ($active_tab === 'results'): ?>
        <!-- Votes count entries grouped by post -->
        <form method="POST" action="">
            <?php insertCSRF(); ?>
            <input type="hidden" name="action" value="save_results">

            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-bold mb-0 text-navy-custom"><i class="bi bi-award-fill text-gold-dark me-2"></i>प्रत्याशी मत प्रविष्टि एवं परिणाम घोषित करें (Result Declaration Desk)</h6>
                <button type="submit" class="btn btn-xs btn-success"><i class="bi bi-check2-all me-1"></i>परिणाम सहेजें एवं घोषित करें</button>
            </div>

            <div class="row g-4">
                <?php 
                $final_candidates = array_filter($candidates, function($c) {
                    return $c['status'] === 'final_candidate';
                });

                if (empty($final_candidates)):
                ?>
                    <div class="col-12">
                        <div class="alert alert-warning py-3 text-center">
                            परिणाम दर्ज करने से पूर्व प्रत्याशी सूची को फाइनल एवं मतपत्र वरीयता क्रमांक नियत करना अनिवार्य है।
                        </div>
                    </div>
                <?php else: ?>
                    <?php 
                    // Group final candidates by post
                    $grouped_candidates = [];
                    foreach ($final_candidates as $fc) {
                        $grouped_candidates[$fc['post_id']][] = $fc;
                    }

                    foreach ($posts as $post):
                        if (!isset($grouped_candidates[$post['id']])) continue;
                    ?>
                        <div class="col-md-6 col-12">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-navy-custom text-white py-2">
                                    <h6 class="mb-0 fw-bold"><?php echo e($post['post_name_hindi']); ?> ( सीटों की संख्या: <?php echo $post['number_of_seats']; ?> )</h6>
                                </div>
                                <div class="card-body p-0">
                                    <table class="table table-sm table-hover align-middle mb-0" style="font-size:0.75rem;">
                                        <thead>
                                            <tr class="table-light">
                                                <th>क्रम</th>
                                                <th>प्रत्याशी का नाम</th>
                                                <th>प्राप्त मत (Votes)</th>
                                                <th>चुनाव परिणाम स्थिति (Status)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($grouped_candidates[$post['id']] as $cand): 
                                                // Find existing values
                                                $exists = array_filter($results, function($r) use ($cand) {
                                                    return $r['candidate_id'] == $cand['id'];
                                                });
                                                $row_val = !empty($exists) ? array_shift($exists) : null;
                                                $votes = $row_val ? $row_val['votes_received'] : '';
                                                $res_status = $row_val ? $row_val['result_status'] : 'pending';
                                            ?>
                                                <tr>
                                                    <td class="english-text text-center fw-bold">#<?php echo $cand['ballot_order']; ?></td>
                                                    <td><strong><?php echo e($cand['name_snapshot']); ?></strong></td>
                                                    <td>
                                                        <input type="hidden" name="results[<?php echo $cand['id']; ?>][post_id]" value="<?php echo $post['id']; ?>">
                                                        <input type="number" name="results[<?php echo $cand['id']; ?>][votes]" class="form-control form-control-xs px-1 text-center english-text" style="width:70px; font-size:0.75rem;" value="<?php echo $votes; ?>">
                                                    </td>
                                                    <td>
                                                        <select name="results[<?php echo $cand['id']; ?>][status]" class="form-select form-select-xs" style="font-size:0.75rem;">
                                                            <option value="pending" <?php echo ($res_status === 'pending') ? 'selected' : ''; ?>>Pending (लंबित)</option>
                                                            <option value="elected" <?php echo ($res_status === 'elected') ? 'selected' : ''; ?>>Elected (विजयी)</option>
                                                            <option value="unopposed" <?php echo ($res_status === 'unopposed') ? 'selected' : ''; ?>>Elected Unopposed (निर्विरोध)</option>
                                                            <option value="runner_up" <?php echo ($res_status === 'runner_up') ? 'selected' : ''; ?>>Runner up</option>
                                                            <option value="not_elected" <?php echo ($res_status === 'not_elected') ? 'selected' : ''; ?>>Not Elected</option>
                                                            <option value="tied" <?php echo ($res_status === 'tied') ? 'selected' : ''; ?>>Tied (टाई)</option>
                                                        </select>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </form>
    <?php endif; ?>

    <!-- Tab 7: Documents Upload -->
    <?php if ($active_tab === 'documents'): ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-navy-custom text-white py-2">
                        <h6 class="mb-0 fw-bold">अपलोड किए गए प्रपत्र / परिपत्र (Documents List)</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>शीर्षक (Title)</th>
                                        <th>दस्तावेज श्रेणी</th>
                                        <th>दृश्यता (Visibility)</th>
                                        <th>अपलोड तिथि</th>
                                        <th class="text-end">डाउनलोड</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($documents)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-3 text-muted">कोई भी प्रलेख अपलोड नहीं है। नया दस्तावेज जोड़ने के लिए दाएं पैनल का उपयोग करें।</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($documents as $doc): ?>
                                            <tr>
                                                <td><strong><?php echo e($doc['title']); ?></strong></td>
                                                <td><?php echo e($doc['document_type']); ?></td>
                                                <td><span class="badge bg-light text-dark border font-size-xs"><?php echo e($doc['visibility']); ?></span></td>
                                                <td class="english-text"><?php echo date('d-m-Y', strtotime($doc['created_at'])); ?></td>
                                                <td class="text-end">
                                                    <a href="<?php echo SITE_URL; ?>/<?php echo $doc['file_path']; ?>" target="_blank" class="btn btn-xs btn-outline-navy py-0.5"><i class="bi bi-download"></i> डाउनलोड</a>
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

            <!-- Upload document Form -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light py-2">
                        <h6 class="mb-0 fw-bold text-navy-custom"><i class="bi bi-upload me-1"></i>दस्तावेज अपलोड करें (Upload PDF)</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" enctype="multipart/form-data">
                            <?php insertCSRF(); ?>
                            <input type="hidden" name="action" value="upload_document">

                            <div class="mb-2">
                                <label class="form-label fw-bold">दस्तावेज का शीर्षक *</label>
                                <input type="text" name="doc_title" class="form-control form-control-sm" required placeholder="उदा. मुख्य चुनाव अधिसूचना कार्यक्रम">
                            </div>

                            <div class="mb-2">
                                <label class="form-label fw-bold">दस्तावेज प्रकार *</label>
                                <select name="document_type" class="form-select form-select-sm" required>
                                    <option value="Election Programme">Election Programme (चुनाव कार्यक्रम)</option>
                                    <option value="Election Rules">Election Rules (चुनाव नियम नियमावली)</option>
                                    <option value="Nomination Form">Nomination Form (नामांकन पत्र प्रारूप)</option>
                                    <option value="Preliminary Voter List PDF">Preliminary Voter List PDF</option>
                                    <option value="Final Voter List PDF">Final Voter List PDF</option>
                                    <option value="Final Candidate List PDF">Final Candidate List PDF</option>
                                    <option value="Result Sheet">Result Sheet (परिणाम पत्र)</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div class="mb-2">
                                <label class="form-label fw-bold">दस्तावेज फाइल (PDF Only) *</label>
                                <input type="file" name="doc_file" class="form-control form-control-sm" required accept=".pdf">
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">दृश्यता (Visibility) *</label>
                                <select name="doc_visibility" class="form-select form-select-sm">
                                    <option value="public">Public (सार्वजनिक)</option>
                                    <option value="members_only">Members Only (केवल लॉगिन सदस्य)</option>
                                    <option value="private">Private</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-navy btn-sm w-100 fw-bold"><i class="bi bi-cloud-upload-fill me-1"></i>दस्तावेज प्रकाशित करें</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tab 8: Audit Logs History -->
    <?php if ($active_tab === 'history'): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-navy-custom text-white py-2">
                <h6 class="mb-0 fw-bold">चुनाव संचालन इतिहास बही (Audit Logs Register)</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="font-size:0.75rem;">
                        <thead class="table-light">
                            <tr>
                                <th>क्र.सं</th>
                                <th>प्रक्रिया / गतिविधि (Action)</th>
                                <th>सम्बद्ध मॉड्यूल</th>
                                <th>पुराना मान</th>
                                <th>नया मान</th>
                                <th>टिप्पणी</th>
                                <th>अधिकृत अधिकारी</th>
                                <th>दिनांक व समय</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($history)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-3 text-muted">कोई इतिहास दर्ज नहीं मिला।</td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $sr = 1;
                                foreach ($history as $h): 
                                ?>
                                    <tr>
                                        <td class="text-center"><?php echo $sr++; ?></td>
                                        <td><strong><?php echo e($h['action']); ?></strong></td>
                                        <td><?php echo e($h['module']); ?></td>
                                        <td><span class="text-muted english-text"><?php echo e($h['old_value'] ?: '-'); ?></span></td>
                                        <td><span class="text-success english-text"><?php echo e($h['new_value'] ?: '-'); ?></span></td>
                                        <td><?php echo e($h['remarks']); ?></td>
                                        <td><span class="badge bg-light text-navy-custom border"><?php echo e($h['username']); ?></span></td>
                                        <td class="english-text"><?php echo date('d-m-Y H:i:s', strtotime($h['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<script>
document.getElementById('electionStatusSelect').addEventListener('change', function(e) {
    const current = "<?php echo $election['status']; ?>";
    const selected = this.value;
    
    const stageOrders = {
        'draft': 1, 'announced': 2, 'nomination_open': 3, 'scrutiny': 4, 'withdrawal': 5,
        'candidate_finalized': 6, 'voter_list_finalized': 7, 'polling_scheduled': 8,
        'polling_completed': 9, 'counting': 10, 'result_declared': 11, 'completed': 12, 'cancelled': 13
    };
    
    const currentOrder = stageOrders[current] || 0;
    const selectedOrder = stageOrders[selected] || 0;
    const protectedStages = ['candidate_finalized', 'voter_list_finalized', 'result_declared', 'completed'];
    
    if (selectedOrder < currentOrder && protectedStages.includes(current)) {
        const reason = prompt("WARNING: You are reverting from a finalized/completed election stage. Please enter a valid Reopening Reason (Min 5 chars):");
        if (!reason || reason.trim().length < 5) {
            alert("Error: Reopening reason is required to revert stage. Action cancelled.");
            this.value = current;
            e.preventDefault();
            return false;
        }
        
        let reasonInput = document.getElementById('reopenReasonInput');
        if (!reasonInput) {
            reasonInput = document.createElement('input');
            reasonInput.type = 'hidden';
            reasonInput.name = 'reopen_reason';
            reasonInput.id = 'reopenReasonInput';
            this.form.appendChild(reasonInput);
        }
        reasonInput.value = reason;
    }
    
    this.form.submit();
});
</script>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
