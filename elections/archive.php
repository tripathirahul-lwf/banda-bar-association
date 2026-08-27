<?php
/**
 * Previous Elections Archive Browser Portal
 * District Bar Association, Banda
 * Established: 1937
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();
$archived_elections = [];

if ($db) {
    try {
        // Fetch archived/completed elections
        $archived_elections = $db->query("
            SELECT * FROM elections 
            WHERE status IN ('archived', 'completed')
            ORDER BY election_year DESC, id DESC
        ")->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed query archived list: " . $e->getMessage());
    }
}
?>

<div class="container py-5 text-navy-custom font-hindi small">
    
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
        <h4 class="text-navy-custom fw-bold mb-0">विगत चुनाव पुरालेख (Previous Elections Archive)</h4>
        <a href="<?php echo SITE_URL; ?>/election.php" class="btn btn-xs btn-outline-navy"><i class="bi bi-arrow-left me-1"></i>सक्रिय चुनाव</a>
    </div>

    <?php if (empty($archived_elections)): ?>
        <div class="text-center py-5 bg-white border rounded shadow-sm text-muted">
            <i class="bi bi-archive display-4 d-block mb-2 text-secondary"></i>
            <h5 class="fw-bold">पुरालेख में कोई पूर्व चुनाव रिकॉर्ड सुरक्षित नहीं है।</h5>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($archived_elections as $elec): 
                // Fetch results and documents for this archived election
                $results = [];
                $docs = [];
                try {
                    $results = $db->query("
                        SELECT r.*, c.name_snapshot, c.membership_no_snapshot, p.post_name_hindi 
                        FROM election_results r
                        JOIN election_candidates c ON c.id = r.candidate_id
                        JOIN election_posts p ON p.id = r.post_id
                        WHERE r.election_id = {$elec['id']} AND r.result_status IN ('elected', 'unopposed')
                        ORDER BY p.display_order ASC
                    ")->fetchAll();
                    
                    $docs = $db->query("
                        SELECT * FROM election_documents 
                        WHERE election_id = {$elec['id']} AND visibility = 'public'
                        ORDER BY id DESC
                    ")->fetchAll();
                } catch (PDOException $e) {}
            ?>
                <div class="col-md-6 col-12">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-header bg-navy-custom text-white py-2">
                            <h6 class="mb-0 fw-bold">वर्ष: <?php echo e($elec['election_year']); ?> - <?php echo e($elec['title']); ?> (<?php echo e($elec['election_code']); ?>)</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-2 mb-3 font-size-xs text-muted" style="font-size:0.75rem;">
                                <div class="col-6"><strong>मतदान तिथि:</strong> <?php echo date('d-m-Y', strtotime($elec['election_date'])); ?></div>
                                <div class="col-6"><strong>स्थान:</strong> <?php echo e($elec['venue']); ?></div>
                            </div>
                            
                            <h6 class="fw-bold border-bottom pb-1 mb-2 text-success">विजयी कार्यकारिणी सूची (Elected Bearers):</h6>
                            <?php if (empty($results)): ?>
                                <p class="text-muted italic" style="font-size:0.7rem;">विजयी प्रत्याशियों की सूची अनुपलब्ध है।</p>
                            <?php else: ?>
                                <ul class="list-unstyled mb-3 ps-2" style="font-size:0.7rem; line-height:1.4;">
                                    <?php foreach ($results as $res): ?>
                                        <li>
                                            <i class="bi bi-award-fill text-gold-dark me-1"></i>
                                            <strong><?php echo e($res['post_name_hindi']); ?>:</strong> <?php echo e($res['name_snapshot']); ?> (<?php echo e($res['membership_no_snapshot']); ?>) 
                                            <?php if ($res['votes_received'] !== null): ?>
                                                - <span class="english-text text-success"><?php echo $res['votes_received']; ?> मत</span>
                                            <?php else: ?>
                                                - <span class="badge bg-light text-navy-custom border">निर्विरोध</span>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>

                            <h6 class="fw-bold border-bottom pb-1 mb-2 text-navy-custom">पुरालेख प्रलेख (Archived Documents):</h6>
                            <?php if (empty($docs)): ?>
                                <p class="text-muted italic" style="font-size:0.7rem;">कोई प्रलेख संलग्न नहीं मिला।</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush" style="font-size:0.7rem;">
                                    <?php foreach ($docs as $d): ?>
                                        <a href="<?php echo SITE_URL; ?>/<?php echo $d['file_path']; ?>" target="_blank" class="list-group-item list-group-item-action py-1 px-2 d-flex justify-content-between align-items-center">
                                            <span><i class="bi bi-file-pdf text-danger me-1"></i><?php echo e($d['title']); ?></span>
                                            <i class="bi bi-download"></i>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php 
require_once __DIR__ . '/../includes/footer.php';
?>
