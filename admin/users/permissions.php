<?php
/**
 * Role Permission Mapping Review Sheet
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'रोल परमिशन मैट्रिक्स (Permission Matrix)';
require_once __DIR__ . '/../../includes/dashboard/header.php';

// Enforce admin permission
requireRole(['admin']);

// Define static permissions structure for clear visibility (Requirement 32)
$permissions = [
    'सदस्य मास्टर (Advocates Master)' => [
        'विस्तृत सूची देखना' => ['admin' => true, 'president' => true, 'mahasachiv' => true, 'member' => true],
        'नया सदस्य जोड़ना' => ['admin' => true, 'president' => false, 'mahasachiv' => true, 'member' => false],
        'सदस्य विवरण सुधारना' => ['admin' => true, 'president' => false, 'mahasachiv' => true, 'member' => false],
        'सदस्य प्रोफाइल अपडेट समीक्षा' => ['admin' => true, 'president' => false, 'mahasachiv' => true, 'member' => false],
    ],
    'डिजिटल आईडी कार्ड (Digital ID Cards)' => [
        'कार्ड आवेदन जमा करना' => ['admin' => false, 'president' => false, 'mahasachiv' => false, 'member' => true],
        'आवेदन पत्र समीक्षा करना' => ['admin' => true, 'president' => false, 'mahasachiv' => true, 'member' => false],
        'अंतिम कार्ड स्वीकृत/जारी' => ['admin' => true, 'president' => true, 'mahasachiv' => false, 'member' => false],
    ],
    'वकालतनामा (Wakalatnama Module)' => [
        'वकालतनामा अपलोड करना' => ['admin' => true, 'president' => false, 'mahasachiv' => true, 'member' => false],
        'वकालतनामा क्रय/डाउनलोड' => ['admin' => false, 'president' => false, 'mahasachiv' => false, 'member' => true],
        'सम्पूर्ण डाउनलोड विवरणी रिपोर्ट' => ['admin' => true, 'president' => true, 'mahasachiv' => true, 'member' => false],
    ],
    'सूचना पट्ट (Notices & Announcements)' => [
        'सूचना ड्राफ्ट तैयार करना' => ['admin' => true, 'president' => true, 'mahasachiv' => true, 'member' => false],
        'सूचना स्वीकृत/प्रसारित करना' => ['admin' => true, 'president' => true, 'mahasachiv' => false, 'member' => false],
    ],
    'संघीय वित्तीय कोष (Association Fund)' => [
        'लेन-देन प्रविष्टि दर्ज़ करना' => ['admin' => true, 'president' => false, 'mahasachiv' => true, 'member' => false],
        'लेन-देन समीक्षा एवं स्वीकृत' => ['admin' => false, 'president' => true, 'mahasachiv' => false, 'member' => false],
    ],
    'वार्षिक सदस्यता शुल्क (Bar Fee Dues)' => [
        'शुल्क मांग (Dues Creation)' => ['admin' => true, 'president' => false, 'mahasachiv' => true, 'member' => false],
        'शुल्क भुगतान प्रविष्टि दर्ज' => ['admin' => true, 'president' => false, 'mahasachiv' => true, 'member' => false],
        'स्वयं की भुगतान रसीद डाउनलोड' => ['admin' => false, 'president' => false, 'mahasachiv' => false, 'member' => true],
    ],
    'चैंबर एवं कक्ष आवंटन (Chambers)' => [
        'चैंबर मास्टर आवंटन' => ['admin' => true, 'president' => true, 'mahasachiv' => true, 'member' => false],
        'मासिक किराया रसीद प्रविष्टि' => ['admin' => true, 'president' => false, 'mahasachiv' => true, 'member' => false],
    ],
    'संघ निर्वाचन (Election Management)' => [
        'निर्वाचन कार्यक्रम घोषित करना' => ['admin' => true, 'president' => false, 'mahasachiv' => true, 'member' => false],
        'मतदाता सूची/प्रत्याशी संपादन' => ['admin' => true, 'president' => false, 'mahasachiv' => true, 'member' => false],
        'परिणाम घोषणा व सत्यापन' => ['admin' => true, 'president' => true, 'mahasachiv' => false, 'member' => false],
    ],
];
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0"><i class="bi bi-shield-check text-gold-custom me-2"></i>सुरक्षा भूमिका एवं विशेषाधिकार विवरणी (Role-Permission Matrix)</h4>
    <a href="index.php" class="btn btn-xs btn-outline-navy"><i class="bi bi-arrow-left me-1"></i>खाता प्रबंधन</a>
</div>

<div class="alert alert-info border-0 shadow-sm rounded-3 mb-4 font-hindi small text-navy-custom">
    <i class="bi bi-info-circle-fill text-info me-2 fs-5"></i>
    <strong>सत्यापन रिपोर्ट:</strong> नीचे प्रदर्शित मैट्रिक्स वर्तमान जिला अधिवक्ता संघ बांदा सॉफ्टवेयर के सुरक्षा नियमों को दर्शाता है। परमिशन संपादन प्रशासनिक अधिकार के आधार पर नियंत्रित हैं।
</div>

<div class="card border-0 shadow-sm font-hindi small text-navy-custom">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover table-bordered align-middle mb-0 text-center" style="font-size:0.75rem;">
                <thead class="table-navy text-navy-custom">
                    <tr>
                        <th class="text-start" style="width: 35%;">विशिष्ट मॉड्यूल एवं क्रिया (Permissions)</th>
                        <th style="width: 15%;">प्रशासक (Admin)</th>
                        <th style="width: 15%;">अध्यक्ष (President)</th>
                        <th style="width: 15%;">महासचिव (Mahasachiv)</th>
                        <th style="width: 15%;">अधिवक्ता सदस्य (Member)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($permissions as $module => $actions): ?>
                        <tr class="table-light">
                            <td class="text-start fw-bold text-navy-custom" colspan="5"><?php echo $module; ?></td>
                        </tr>
                        <?php foreach ($actions as $action_name => $roles): ?>
                            <tr>
                                <td class="text-start ps-4"><?php echo $action_name; ?></td>
                                <td>
                                    <?php echo $roles['admin'] ? '<i class="bi bi-check-circle-fill text-success fs-5"></i>' : '<i class="bi bi-x-circle text-muted"></i>'; ?>
                                </td>
                                <td>
                                    <?php echo $roles['president'] ? '<i class="bi bi-check-circle-fill text-success fs-5"></i>' : '<i class="bi bi-x-circle text-muted"></i>'; ?>
                                </td>
                                <td>
                                    <?php echo $roles['mahasachiv'] ? '<i class="bi bi-check-circle-fill text-success fs-5"></i>' : '<i class="bi bi-x-circle text-muted"></i>'; ?>
                                </td>
                                <td>
                                    <?php echo $roles['member'] ? '<i class="bi bi-check-circle-fill text-success fs-5"></i>' : '<i class="bi bi-x-circle text-muted"></i>'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../../includes/dashboard/footer.php';
?>
