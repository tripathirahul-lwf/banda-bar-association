<?php
/**
 * Logged-In User Active Sessions Registry
 * District Bar Association, Banda
 * Established: 1937
 */

$pageTitle = 'सक्रिय सत्र (Active Sessions)';
require_once __DIR__ . '/../includes/dashboard/header.php';

$ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
?>

<div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 font-hindi">
    <h4 class="text-navy-custom fw-bold mb-0"><i class="bi bi-shield-check text-gold-custom me-2"></i>सुरक्षा एवं सक्रिय सत्र (Active Session Settings)</h4>
</div>

<div class="card border-0 shadow-sm font-hindi small text-navy-custom">
    <div class="card-header bg-navy-custom text-white py-2">
        <h6 class="mb-0 fw-bold">वर्तमान लॉगिन विवरण (Current Session Info)</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0" style="font-size:0.78rem;">
                <thead class="table-light">
                    <tr>
                        <th>उपकरण / ब्राउज़र (Device / User Agent)</th>
                        <th>आईपी पता (IP Address)</th>
                        <th>सत्र स्थिति (Status)</th>
                        <th>लॉगिन समय (Session Start)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="english-text">
                            <strong><?php echo e($user_agent); ?></strong>
                        </td>
                        <td class="english-text fw-semibold"><?php echo e($ip); ?></td>
                        <td>
                            <span class="badge bg-success">Current Session (सक्रिय)</span>
                        </td>
                        <td class="english-text">
                            <?php echo date('d-m-Y h:i A', $_SESSION['login_time'] ?? time()); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="alert alert-info border-0 shadow-sm rounded-3 mt-4 font-hindi small text-navy-custom">
    <i class="bi bi-info-circle-fill text-info me-2 fs-5"></i>
    <strong>सुरक्षा संकेत:</strong> अपनी साख सुरक्षित रखने के लिए सार्वजनिक कंप्यूटर या बाहरी नेटवर्क से कार्य समाप्त होने के पश्चात हमेशा <strong>लॉगआउट</strong> अवश्य करें।
</div>

<?php 
require_once __DIR__ . '/../includes/dashboard/footer.php';
?>
