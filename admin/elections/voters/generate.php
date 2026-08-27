<?php
/**
 * Voter List Snapshot Generator
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'मतदाता सूची जनरेट करें (Generate Voters Snapshot)';
require_once __DIR__ . '/../../../includes/dashboard/header.php';

// Enforce permissions
requireRole(['admin', 'mahasachiv']);

$election_id = isset($_GET['election_id']) ? intval($_GET['election_id']) : 0;
$db = Database::getConnection();
$election = null;
$error = '';
$success = '';

if ($db && $election_id > 0) {
    try {
        $stmt = $db->prepare("SELECT * FROM elections WHERE id = ?");
        $stmt->execute([$election_id]);
        $election = $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Failed loading election: " . $e->getMessage());
    }
}

if (!$election) {
    echo '<div class="alert alert-danger font-hindi">त्रुटि: चुनाव विवरण नहीं मिला।</div>';
    require_once __DIR__ . '/../../../includes/dashboard/footer.php';
    exit;
}

// Block if voter list is already finalized/published
if ($election['status'] === 'voter_list_finalized' || $election['status'] === 'archived' || $election['status'] === 'completed') {
    $error = 'इस चुनाव की मतदाता सूची पूर्व में ही फाइनल (Finalized) की जा चुकी है, अतः पुनः जनरेशन लॉक है।';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    if (!verifyCSRF()) {
        $error = 'सुरक्षा सत्यापन विफल रहा।';
    } else {
        $source = sanitize($_POST['voter_source'] ?? 'active');
        $initial_status = sanitize($_POST['initial_status'] ?? 'pending_review');

        try {
            $db->beginTransaction();

            // Fetch source members
            $sql = "SELECT id, full_name, membership_no, enrollment_no, chamber_no FROM members WHERE 1=1";
            if ($source === 'active') {
                $sql .= " AND membership_status = 'active'";
            }
            
            $members = $db->query($sql)->fetchAll();

            if (empty($members)) {
                throw new Exception('मतदाता जनरेशन के लिए कोई सदस्य नहीं मिला।');
            }

            $generated_count = 0;
            $skipped_count = 0;

            $ins = $db->prepare("
                INSERT INTO election_voters 
                (election_id, member_id, eligibility_status, added_by, member_name, membership_no, enrollment_no, chamber_no, finalized) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0)
                ON DUPLICATE KEY UPDATE 
                    member_name = VALUES(member_name),
                    membership_no = VALUES(membership_no),
                    enrollment_no = VALUES(enrollment_no),
                    chamber_no = VALUES(chamber_no)
            ");

            foreach ($members as $m) {
                // Execute
                $ins->execute([
                    $election_id, 
                    $m['id'], 
                    $initial_status, 
                    $_SESSION['user_id'],
                    $m['full_name'],
                    $m['membership_no'],
                    $m['enrollment_no'],
                    $m['chamber_no']
                ]);
                $generated_count++;
            }

            // Audit log
            $audit = $db->prepare("INSERT INTO election_history (election_id, action, module, record_id, remarks, performed_by) VALUES (?, 'Voter List Generated', 'voters', ?, ?, ?)");
            $audit->execute([$election_id, $election_id, "प्रारंभिक मतदाता सूची जनरेट की गई। कुल रिकॉर्ड: $generated_count", $_SESSION['user_id']]);

            $db->commit();
            $_SESSION['flash_success'] = "मतदाता सूची सफलतापूर्वक जनरेट की गई। कुल प्रविष्टियां: $generated_count";
            
            header("Location: ../view.php?id=" . $election_id . "&tab=voters");
            exit;

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error = 'मतदाता सूची जनरेशन में त्रुटि: ' . $e->getMessage();
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">मतदाता सूची जनरेट करें (Generate Voters Snapshot)</h4>
    <a href="../view.php?id=<?php echo $election_id; ?>&tab=voters" class="btn btn-xs btn-outline-navy"><i class="bi bi-arrow-left me-1"></i>चुनाव प्रबंधन</a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 font-hindi small"><?php echo $error; ?></div>
<?php endif; ?>

<div class="row g-4 font-hindi small text-navy-custom">
    <!-- Left Column: Generator Form -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-navy-custom text-white py-2">
                <h6 class="mb-0 fw-bold"><i class="bi bi-gear-fill text-gold-custom me-2"></i>जनरेशन पैरामीटर (Generation Rules)</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <?php insertCSRF(); ?>

                    <div class="mb-3">
                        <label class="form-label fw-bold">चयनित चुनाव:</label>
                        <input type="text" class="form-control form-control-sm bg-light fw-bold" readonly value="<?php echo e($election['title']); ?> (<?php echo e($election['election_code']); ?>)">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">1. मतदाता स्रोत फ़िल्टर (Source Members)</label>
                        <select name="voter_source" class="form-select form-select-sm">
                            <option value="active">केवल सक्रिय पंजीकृत सदस्य (Active Members Only)</option>
                            <option value="all">सभी सदस्य रिकॉर्ड (All Database Members)</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">2. प्रारंभिक पात्रता स्थिति (Initial Status)</label>
                        <select name="initial_status" class="form-select form-select-sm">
                            <option value="pending_review">Pending Review (समीक्षा लंबित - अनुशंसित)</option>
                            <option value="eligible">Eligible (योग्य घोषित)</option>
                        </select>
                    </div>

                    <div class="alert alert-warning border-0 p-2 mb-3" style="font-size:0.7rem;">
                        <i class="bi bi-info-circle me-1"></i><strong>सुरक्षा चेतावनी:</strong> यदि मतदाता सूची पहले से जनरेट की गई थी, तो पुनः जनरेट करने पर मतदाताओं की नाम, सदस्यता क्रमांक, नामांकन क्रमांक और चैम्बर विवरणों को वर्तमान सदस्य मास्टर के अनुसार ओवरराइट कर दिया जाएगा। पुरानी पात्रता टिप्पणियां सुरक्षित रहेंगी।
                    </div>

                    <?php if (empty($error)): ?>
                        <div class="text-end">
                            <button type="submit" class="btn btn-navy btn-sm px-4 py-2"><i class="bi bi-check-circle-fill me-1"></i>मतदाता सूची जनरेट करें</button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- Right Column: Info Guide -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-light py-2">
                <h6 class="mb-0 fw-bold text-navy-custom"><i class="bi bi-info-circle-fill me-2"></i>मतदाता सूची स्नैपशॉट क्या है?</h6>
            </div>
            <div class="card-body">
                <p class="leading-relaxed">
                    जिला अधिवक्ता संघ, बांदा के नियमानुसार, चुनाव घोषित होने के बाद मतदाता सूची को अंतिम रूप (Finalize) दिया जाता है।
                </p>
                <p class="leading-relaxed text-muted">
                    एक बार जब सूची जनरेट होती है, तब प्रत्येक मतदाता के रिकॉर्ड (नाम, सदस्यता क्रमांक, नामांकन और चैम्बर) का **स्नैपशॉट** सुरक्षित कर लिया जाता है। 
                </p>
                <p class="leading-relaxed text-muted">
                    इसके बाद यदि मुख्य सदस्य सूची में कोई बदलाव किया जाता है, तो भी पूर्व चुनाव के पुरालेख (Archive) में दर्ज मतदाताओं के मूल विवरण सुरक्षित रहते हैं और कभी परिवर्तित नहीं होते।
                </p>
            </div>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../../../includes/dashboard/footer.php';
?>
