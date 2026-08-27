<?php
/**
 * Create New Election Declaration Form
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'नया चुनाव घोषित करें (Declare Election)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce permissions
requireRole(['admin', 'mahasachiv']);

$db = Database::getConnection();
$error = '';
$success = '';

// Default values
$election_year = intval(date('Y'));
$election_code = "DBA-ELECTION-$election_year";
$title = "वार्षिक कार्यकारिणी चुनाव $election_year-" . substr($election_year + 1, 2);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRF()) {
        $error = 'सुरक्षा सत्यापन विफल रहा।';
    } else {
        $title = sanitize(trim($_POST['title'] ?? ''));
        $code = sanitize(trim($_POST['election_code'] ?? ''));
        $year = intval($_POST['election_year'] ?? 0);
        $date = sanitize(trim($_POST['election_date'] ?? ''));
        $desc = sanitize(trim($_POST['description'] ?? ''));
        $venue = sanitize(trim($_POST['venue'] ?? ''));
        $vis = sanitize($_POST['visibility'] ?? 'private');

        // Optional timeline datetime inputs
        $nom_start = !empty($_POST['nomination_start']) ? sanitize($_POST['nomination_start']) : null;
        $nom_end = !empty($_POST['nomination_end']) ? sanitize($_POST['nomination_end']) : null;
        $scrutiny = !empty($_POST['scrutiny_date']) ? sanitize($_POST['scrutiny_date']) : null;
        $withdraw = !empty($_POST['withdrawal_deadline']) ? sanitize($_POST['withdrawal_deadline']) : null;
        $final_cand = !empty($_POST['final_candidate_date']) ? sanitize($_POST['final_candidate_date']) : null;
        $v_start = !empty($_POST['voting_start_time']) ? sanitize($_POST['voting_start_time']) : null;
        $v_end = !empty($_POST['voting_end_time']) ? sanitize($_POST['voting_end_time']) : null;
        $counting = !empty($_POST['counting_date']) ? sanitize($_POST['counting_date']) : null;
        $result = !empty($_POST['result_date']) ? sanitize($_POST['result_date']) : null;

        if (empty($title) || empty($code) || $year <= 0 || empty($date)) {
            $error = 'कृपया चुनाव शीर्षक, कोड, वर्ष एवं मतदान की तिथि अनिवार्य रूप से भरें।';
        } else {
            try {
                $db->beginTransaction();

                // Check duplicate code
                $check = $db->prepare("SELECT COUNT(*) FROM elections WHERE election_code = ?");
                $check->execute([$code]);
                if ($check->fetchColumn() > 0) {
                    throw new Exception("चुनाव कोड '$code' पहले से डेटाबेस में मौजूद है। कृपया दूसरा कोड दर्ज करें।");
                }

                // Insert Election
                $ins = $db->prepare("
                    INSERT INTO elections 
                    (election_code, title, description, election_year, election_date, 
                     nomination_start, nomination_end, scrutiny_date, withdrawal_deadline, 
                     final_candidate_date, voting_start_time, voting_end_time, counting_date, 
                     result_date, venue, status, visibility, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', ?, ?)
                ");
                $ins->execute([
                    $code, $title, $desc, $year, $date,
                    $nom_start, $nom_end, $scrutiny, $withdraw,
                    $final_cand, $v_start, $v_end, $counting,
                    $result, $venue, $vis, $_SESSION['user_id']
                ]);
                $new_id = $db->lastInsertId();

                // Add default constitutional posts for DBA Banda election
                $default_posts = [
                    ['post_name' => 'President', 'post_name_hindi' => 'अध्यक्ष', 'seats' => 1, 'order' => 1],
                    ['post_name' => 'Vice President', 'post_name_hindi' => 'उपाध्यक्ष', 'seats' => 1, 'order' => 2],
                    ['post_name' => 'Mahasachiv', 'post_name_hindi' => 'महासचिव', 'seats' => 1, 'order' => 3],
                    ['post_name' => 'Treasurer', 'post_name_hindi' => 'कोषाध्यक्ष', 'seats' => 1, 'order' => 4],
                    ['post_name' => 'Executive Member', 'post_name_hindi' => 'कार्यकारिणी सदस्य', 'seats' => 5, 'order' => 5],
                ];

                $post_ins = $db->prepare("INSERT INTO election_posts (election_id, post_name, post_name_hindi, number_of_seats, display_order) VALUES (?, ?, ?, ?, ?)");
                foreach ($default_posts as $dp) {
                    $post_ins->execute([$new_id, $dp['post_name'], $dp['post_name_hindi'], $dp['seats'], $dp['order']]);
                }

                // Audit log
                $audit = $db->prepare("INSERT INTO election_history (election_id, action, module, record_id, remarks, performed_by) VALUES (?, 'Election Created', 'elections', ?, 'नया चुनाव घोषित किया गया एवं प्राथमिक पद निर्मित किए गए।', ?)");
                $audit->execute([$new_id, $new_id, $_SESSION['user_id']]);

                $db->commit();
                $success = 'नया चुनाव सफलतापूर्वक घोषित कर दिया गया है।';
                
                header("Location: index.php");
                exit;

            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $error = 'घोषणा विफलता: ' . $e->getMessage();
            }
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0">नया चुनाव घोषित करें (Election Declaration Form)</h4>
    <a href="index.php" class="btn btn-xs btn-outline-navy"><i class="bi bi-arrow-left me-1"></i>वापस जाएं</a>
</div>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 font-hindi small"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm font-hindi small text-navy-custom">
    <div class="card-header bg-navy-custom text-white py-2">
        <h6 class="mb-0 fw-bold"><i class="bi bi-plus-circle text-gold-custom me-2"></i>चुनाव प्रविष्टि विवरण (Election Info)</h6>
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <?php insertCSRF(); ?>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">1. चुनाव का शीर्षक (Election Title) <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control form-control-sm" required value="<?php echo e($title); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">2. चुनाव वर्ष (Year) <span class="text-danger">*</span></label>
                    <input type="number" name="election_year" class="form-control form-control-sm english-text" required value="<?php echo $election_year; ?>" onchange="updateCodeAndTitle(this.value);">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">3. विशिष्ट चुनाव कोड (Election Code) <span class="text-danger">*</span></label>
                    <input type="text" name="election_code" id="election_code" class="form-control form-control-sm english-text fw-bold" required value="<?php echo e($election_code); ?>">
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">4. मतदान की तिथि (Polling Date) <span class="text-danger">*</span></label>
                    <input type="date" name="election_date" class="form-control form-control-sm" required value="<?php echo date('Y-m-d', strtotime('+3 weeks')); ?>">
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-bold">5. मतदान स्थल (Venue)</label>
                    <input type="text" name="venue" class="form-control form-control-sm" value="बार संघ सभागार भवन, बांदा" placeholder="उदा. संघ पुस्तकालय कक्ष या सभागार...">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">6. चुनाव सम्बन्धी संक्षिप्त विवरण (Description)</label>
                <textarea name="description" class="form-control form-control-sm" rows="2" placeholder="वोटरों हेतु कोई विशेष निर्देश या चुनाव का परिचय..."></textarea>
            </div>

            <h6 class="fw-bold border-bottom pb-1 my-3 text-gold-dark"><i class="bi bi-clock-history me-1"></i>चुनाव कार्यक्रम सारणी (Timeline Schedules - Optional)</h6>
            
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">नामांकन प्रपत्र प्रारंभ तिथि व समय</label>
                    <input type="datetime-local" name="nomination_start" class="form-control form-control-sm">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">नामांकन प्रपत्र जमा करने की अंतिम तिथि व समय</label>
                    <input type="datetime-local" name="nomination_end" class="form-control form-control-sm">
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold">नामांकन पत्रों की जांच तिथि (Scrutiny)</label>
                    <input type="date" name="scrutiny_date" class="form-control form-control-sm">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">नाम वापसी की अंतिम तिथि व समय</label>
                    <input type="datetime-local" name="withdrawal_deadline" class="form-control form-control-sm">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">अंतिम प्रत्याशी सूची प्रकाशन तिथि</label>
                    <input type="date" name="final_candidate_date" class="form-control form-control-sm">
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <label class="form-label fw-bold">मतदान प्रारंभ समय</label>
                    <input type="time" name="voting_start_time" class="form-control form-control-sm" value="09:00">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">मतदान समाप्ति समय</label>
                    <input type="time" name="voting_end_time" class="form-control form-control-sm" value="16:00">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">मतगणना की तिथि (Counting)</label>
                    <input type="date" name="counting_date" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">चुनाव परिणाम तिथि (Result)</label>
                    <input type="date" name="result_date" class="form-control form-control-sm">
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold">7. चुनाव की दृश्यता (Visibility) <span class="text-danger">*</span></label>
                <select name="visibility" class="form-select form-select-sm" required>
                    <option value="private">Private (केवल अधिकृत चुनाव स्टाफ देख सकेगा)</option>
                    <option value="members_only">Members Only (लॉगिन करने के बाद सदस्य देख सकेंगे)</option>
                    <option value="public">Public (वेबसाइट पर सार्वजनिक रूप से दिखेगा)</option>
                </select>
            </div>

            <div class="alert alert-secondary border-0 p-2 mb-3" style="font-size:0.7rem;">
                <i class="bi bi-info-circle me-1"></i><strong>नोट:</strong> जो तिथियां निर्धारित नहीं हुई हैं उन्हें रिक्त छोड़ दें। संघ नियमानुसार उन्हें बाद में अपडेट किया जा सकता है।
            </div>

            <div class="text-end">
                <button type="submit" class="btn btn-navy btn-sm px-4 py-2"><i class="bi bi-check-circle-fill me-1"></i>चुनाव घोषित करें (Save Draft)</button>
            </div>
        </form>
    </div>
</div>

<script>
function updateCodeAndTitle(year) {
    if(year > 0) {
        document.getElementById('election_code').value = "DBA-ELECTION-" + year;
    }
}
</script>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
