<?php
/**
 * Global helper functions for District Bar Association, Banda
 */

// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit("Direct access forbidden.");
}

/**
 * Sanitize user input for safe rendering in HTML.
 *
 * @param string $data The raw input.
 * @return string The sanitized output.
 */
function sanitize($data) {
    if ($data === null) return '';
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize an array recursively.
 * 
 * @param array $arr Input array.
 * @return array Sanitized array.
 */
function sanitize_array($arr) {
    $sanitized = [];
    foreach ($arr as $key => $value) {
        if (is_array($value)) {
            $sanitized[$key] = sanitize_array($value);
        } else {
            $sanitized[$key] = sanitize($value);
        }
    }
    return $sanitized;
}

/**
 * Generate CSRF token and store it in session.
 *
 * @return string
 */
function generate_csrf_token() {
    if (session_status() === PHP_SESSION_NONE) {
        return '';
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify if the submitted CSRF token matches the session token.
 *
 * @param string $token
 * @return bool
 */
function verify_csrf_token($token) {
    if (session_status() === PHP_SESSION_NONE || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Helper to safely redirect and exit.
 *
 * @param string $url Target URL.
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Set a flash message.
 *
 * @param string $type The message type (success, danger, warning, info).
 * @param string $message The message body.
 */
function set_flash_message($type, $message) {
    if (session_status() !== PHP_SESSION_NONE) {
        $_SESSION['flash_messages'][$type] = $message;
    }
}

/**
 * Check if a specific type of flash message exists.
 *
 * @param string $type The message type.
 * @return bool
 */
function has_flash_message($type) {
    return (session_status() !== PHP_SESSION_NONE && isset($_SESSION['flash_messages'][$type]));
}

/**
 * Get and clear a flash message from session.
 *
 * @param string $type The message type.
 * @return string|null
 */
function get_flash_message($type) {
    if (session_status() !== PHP_SESSION_NONE && isset($_SESSION['flash_messages'][$type])) {
        $msg = $_SESSION['flash_messages'][$type];
        unset($_SESSION['flash_messages'][$type]);
        return $msg;
    }
    return null;
}

/**
 * Helper to check if a specific page in navbar is active.
 *
 * @param string $pageName The page file name (e.g. 'index.php').
 * @return string Returns 'active' class string or empty.
 */
function is_page_active($pageName) {
    $current = basename($_SERVER['SCRIPT_NAME']);
    return ($current === $pageName) ? 'active' : '';
}

/**
 * Alias setFlash for Phase 2 compatibility.
 */
function setFlash($type, $message) {
    set_flash_message($type, $message);
}

/**
 * Alias hasFlash for Phase 2 compatibility.
 */
function hasFlash($type) {
    return has_flash_message($type);
}

/**
 * Alias getFlash for Phase 2 compatibility.
 */
function getFlash($type) {
    return get_flash_message($type);
}

/**
 * Generate and output a hidden input field for CSRF tokens.
 */
function csrfField() {
    echo '<input type="hidden" name="csrf_token" value="' . sanitize(generate_csrf_token()) . '">';
}

/**
 * Output escaping helper using htmlspecialchars.
 *
 * @param string|null $value Dynamic content.
 * @return string Escaped string.
 */
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Generate fully qualified URL for the application (Requirement 46)
 */
function url($path = '') {
    return SITE_URL . '/' . ltrim($path, '/');
}

/**
 * Generate a unique sequential ID Card Number for the current year
 * Format: DBA-ID-YYYY-XXXX
 */
function generateIdCardNumber($db) {
    $year = date('Y');
    try {
        $stmt = $db->prepare("SELECT card_number FROM id_cards WHERE card_number LIKE :prefix ORDER BY id DESC LIMIT 1");
        $stmt->execute([':prefix' => "DBA-ID-$year-%"]);
        $last = $stmt->fetchColumn();
        if ($last) {
            $parts = explode('-', $last);
            $seq = intval(end($parts)) + 1;
        } else {
            $seq = 1;
        }
        return sprintf("DBA-ID-%d-%04d", $year, $seq);
    } catch (PDOException $e) {
        error_log("Failed to generate ID card number: " . $e->getMessage());
        return "DBA-ID-$year-" . rand(1000, 9999);
    }
}

/**
 * Identify and expire ID cards whose valid_until date has passed.
 */
function expireOldIdCards($db) {
    try {
        $stmt = $db->prepare("UPDATE id_cards SET status = 'expired' WHERE status IN ('issued', 'ready') AND valid_until < CURRENT_DATE()");
        $stmt->execute();
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Failed to expire old ID cards: " . $e->getMessage());
        return 0;
    }
}

/**
 * Check if a Wakalatnama document is currently active and within effective dates.
 *
 * @param array $doc Document row from db.
 * @return bool True if downloadable, false otherwise.
 */
function isWakalatnamaAvailable($doc) {
    if (!$doc) return false;
    if ($doc['status'] !== 'active') return false;
    
    $current = date('Y-m-d');
    if ($doc['effective_from'] > $current) return false;
    if (!empty($doc['effective_until']) && $doc['effective_until'] < $current) return false;
    
    return true;
}

/**
 * Generate sequential Notice Number for the current year
 */
function generateNoticeNumber($db) {
    $year = date('Y');
    try {
        $stmt = $db->prepare("SELECT notice_no FROM notices WHERE notice_no LIKE :prefix ORDER BY id DESC LIMIT 1");
        $stmt->execute([':prefix' => "DBA/NOTICE/$year/%"]);
        $last = $stmt->fetchColumn();
        if ($last) {
            $parts = explode('/', $last);
            $seq = intval(end($parts)) + 1;
        } else {
            $seq = 1;
        }
        return sprintf("DBA/NOTICE/%d/%04d", $year, $seq);
    } catch (PDOException $e) {
        error_log("Failed to generate notice number: " . $e->getMessage());
        return "DBA/NOTICE/$year/" . rand(1000, 9999);
    }
}

/**
 * Transition scheduled notices to published status
 */
function publishScheduledNotices($db) {
    try {
        $stmt = $db->prepare("
            UPDATE notices 
            SET status = 'published', published_at = NOW() 
            WHERE status = 'scheduled' AND publish_at <= NOW()
        ");
        $stmt->execute();
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Failed to publish scheduled notices: " . $e->getMessage());
        return 0;
    }
}

/**
 * Transition expired published notices to expired status
 */
function expireNotices($db) {
    try {
        $stmt = $db->prepare("
            UPDATE notices 
            SET status = 'expired' 
            WHERE status = 'published' AND expire_at IS NOT NULL AND expire_at <= NOW()
        ");
        $stmt->execute();
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log("Failed to expire notices: " . $e->getMessage());
        return 0;
    }
}

/**
 * Fetch Association Fund Balance metrics
 */
function getAssociationFundSummary($financialYearId, $db) {
    $summary = [
        'opening_balance' => 0.00,
        'total_income' => 0.00,
        'total_expense' => 0.00,
        'current_balance' => 0.00
    ];
    if (!$db || $financialYearId <= 0) return $summary;

    try {
        // Opening balance
        $stmt = $db->prepare("SELECT opening_balance FROM association_fund_accounts WHERE financial_year_id = ? LIMIT 1");
        $stmt->execute([$financialYearId]);
        $op = $stmt->fetchColumn();
        if ($op) {
            $summary['opening_balance'] = floatval($op);
        }

        // Total income
        $stmt = $db->prepare("SELECT SUM(amount) FROM association_fund_transactions WHERE financial_year_id = ? AND transaction_type = 'income' AND status = 'approved'");
        $stmt->execute([$financialYearId]);
        $summary['total_income'] = floatval($stmt->fetchColumn() ?: 0.00);

        // Total expense
        $stmt = $db->prepare("SELECT SUM(amount) FROM association_fund_transactions WHERE financial_year_id = ? AND transaction_type = 'expense' AND status = 'approved'");
        $stmt->execute([$financialYearId]);
        $summary['total_expense'] = floatval($stmt->fetchColumn() ?: 0.00);

        $summary['current_balance'] = $summary['opening_balance'] + $summary['total_income'] - $summary['total_expense'];

    } catch (PDOException $e) {
        error_log("Failed calculating association fund summary: " . $e->getMessage());
    }
    return $summary;
}

/**
 * Generate unique transaction number
 */
function generateFundTransactionNumber($db) {
    // Get active financial year name
    $fy_name = 'FY';
    $fy_id = 0;
    try {
        $fy_stmt = $db->query("SELECT id, name FROM financial_years WHERE status = 'active' LIMIT 1");
        $fy = $fy_stmt->fetch();
        if ($fy) {
            $fy_name = $fy['name'];
            $fy_id = $fy['id'];
        }
    } catch (PDOException $e) {
        error_log("Failed getting active FY name: " . $e->getMessage());
    }

    $year = date('Y');
    try {
        $stmt = $db->prepare("SELECT transaction_no FROM association_fund_transactions WHERE financial_year_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$fy_id]);
        $last = $stmt->fetchColumn();
        if ($last) {
            $parts = explode('/', $last);
            $seq = intval(end($parts)) + 1;
        } else {
            $seq = 1;
        }
        return sprintf("DBA/FUND/%s/%04d", $fy_name, $seq);
    } catch (PDOException $e) {
        error_log("Failed generating fund transaction number: " . $e->getMessage());
        return "DBA/FUND/$fy_name/" . rand(1000, 9999);
    }
}

/**
 * Generate auto sequence for receipt or voucher
 */
function generateReceiptOrVoucherNo($db, $type) {
    $prefix = ($type === 'income') ? 'REC' : 'VOU';
    $year = date('Y');
    try {
        $col = ($type === 'income') ? 'receipt_no' : 'voucher_no';
        $stmt = $db->prepare("SELECT $col FROM association_fund_transactions WHERE transaction_type = ? AND $col LIKE :pattern ORDER BY id DESC LIMIT 1");
        $stmt->execute([$type, ":pattern" => "$prefix-$year-%"]);
        $last = $stmt->fetchColumn();
        if ($last) {
            $parts = explode('-', $last);
            $seq = intval(end($parts)) + 1;
        } else {
            $seq = 1;
        }
        return sprintf("%s-%d-%04d", $prefix, $year, $seq);
    } catch (PDOException $e) {
        error_log("Failed generating receipt or voucher no: " . $e->getMessage());
        return sprintf("%s-%d-%04d", $prefix, $year, rand(1000, 9999));
    }
}

/**
 * Fetch Member Bar Fee Summary metrics
 */
function getMemberFeeSummary($memberId, $db) {
    $summary = [
        'total_due' => 0.00,
        'total_paid' => 0.00,
        'total_outstanding' => 0.00,
        'overdue_amount' => 0.00
    ];
    if (!$db || $memberId <= 0) return $summary;

    try {
        $stmt = $db->prepare("
            SELECT 
                SUM(payable_amount) as total_due,
                SUM(paid_amount) as total_paid,
                SUM(outstanding_amount) as total_outstanding
            FROM member_fee_dues
            WHERE member_id = ? AND status != 'cancelled'
        ");
        $stmt->execute([$memberId]);
        $row = $stmt->fetch();
        if ($row) {
            $summary['total_due'] = floatval($row['total_due'] ?: 0.00);
            $summary['total_paid'] = floatval($row['total_paid'] ?: 0.00);
            $summary['total_outstanding'] = floatval($row['total_outstanding'] ?: 0.00);
        }

        // Overdue amount
        $stmt = $db->prepare("
            SELECT SUM(outstanding_amount) 
            FROM member_fee_dues
            WHERE member_id = ? AND status != 'cancelled' AND due_date < CURRENT_DATE() AND status != 'paid'
        ");
        $stmt->execute([$memberId]);
        $summary['overdue_amount'] = floatval($stmt->fetchColumn() ?: 0.00);
    } catch (PDOException $e) {
        error_log("Failed fetching member fee summary: " . $e->getMessage());
    }
    return $summary;
}

/**
 * Fetch Member Outstanding Fee amount
 */
function getMemberOutstandingFee($memberId, $db) {
    if (!$db || $memberId <= 0) return 0.00;
    try {
        $stmt = $db->prepare("
            SELECT SUM(outstanding_amount) 
            FROM member_fee_dues
            WHERE member_id = ? AND status IN ('pending', 'partially_paid', 'overdue')
        ");
        $stmt->execute([$memberId]);
        return floatval($stmt->fetchColumn() ?: 0.00);
    } catch (PDOException $e) {
        error_log("Failed fetching member outstanding: " . $e->getMessage());
        return 0.00;
    }
}

/**
 * Generate unique Bar Fee Payment Number
 */
function generateBarFeePaymentNumber($db) {
    $fy_name = 'FY';
    try {
        $fy = $db->query("SELECT name FROM financial_years WHERE status = 'active' LIMIT 1")->fetch();
        if ($fy) {
            $fy_name = $fy['name'];
        }
    } catch (PDOException $e) {
        error_log("Failed to resolve active FY: " . $e->getMessage());
    }

    try {
        $stmt = $db->query("SELECT payment_no FROM bar_fee_payments ORDER BY id DESC LIMIT 1");
        $last = $stmt->fetchColumn();
        if ($last) {
            $parts = explode('/', $last);
            $seq = intval(end($parts)) + 1;
        } else {
            $seq = 1;
        }
        return sprintf("DBA/BF/%s/%05d", $fy_name, $seq);
    } catch (PDOException $e) {
        error_log("Failed generating payment number: " . $e->getMessage());
        return "DBA/BF/$fy_name/" . rand(10000, 99999);
    }
}

/**
 * Recalculate Member Fee Due metrics by id
 */
function recalculateMemberFeeDue($dueId, $db) {
    if (!$db || $dueId <= 0) return false;
    try {
        // Find due details
        $stmt = $db->prepare("SELECT original_amount, discount_amount, penalty_amount, due_date FROM member_fee_dues WHERE id = ?");
        $stmt->execute([$dueId]);
        $due = $stmt->fetch();
        if (!$due) return false;

        $payable = floatval($due['original_amount']) - floatval($due['discount_amount']) + floatval($due['penalty_amount']);

        // Sum allocations
        $alloc_stmt = $db->prepare("
            SELECT SUM(a.allocated_amount) 
            FROM bar_fee_payment_allocations a
            JOIN bar_fee_payments p ON p.id = a.payment_id
            WHERE a.due_id = ? AND p.status = 'confirmed'
        ");
        $alloc_stmt->execute([$dueId]);
        $paid = floatval($alloc_stmt->fetchColumn() ?: 0.00);

        $outstanding = $payable - $paid;
        if ($outstanding < 0) $outstanding = 0.00;

        // Resolve status
        $status = 'pending';
        if ($outstanding <= 0) {
            $status = 'paid';
        } elseif ($paid > 0) {
            $status = 'partially_paid';
        } elseif (date('Y-m-d') > $due['due_date']) {
            $status = 'overdue';
        }

        // Update
        $up = $db->prepare("
            UPDATE member_fee_dues 
            SET payable_amount = ?, paid_amount = ?, outstanding_amount = ?, status = ? 
            WHERE id = ?
        ");
        $up->execute([$payable, $paid, $outstanding, $status, $dueId]);
        return true;
    } catch (PDOException $e) {
        error_log("Failed to recalculate due $dueId: " . $e->getMessage());
        return false;
    }
}

/**
 * Fetch Outstanding Chamber Rent for Allotment (Phase 9)
 */
function getChamberOutstandingRent($allotmentId, $db) {
    if (!$db || $allotmentId <= 0) return 0.00;
    try {
        $stmt = $db->prepare("
            SELECT SUM(outstanding_amount) 
            FROM chamber_rent_dues 
            WHERE allotment_id = ? AND status NOT IN ('paid', 'waived', 'cancelled')
        ");
        $stmt->execute([$allotmentId]);
        return floatval($stmt->fetchColumn() ?: 0.00);
    } catch (PDOException $e) {
        error_log("Failed to fetch chamber outstanding rent: " . $e->getMessage());
        return 0.00;
    }
}

/**
 * Fetch Member Chamber Summary (Phase 9)
 */
function getMemberChamberSummary($memberId, $db) {
    $summary = [
        'has_chamber' => false,
        'chamber_no' => 'कोई नहीं (None)',
        'allotment_id' => 0,
        'outstanding_rent' => 0.00,
        'security_deposit' => 0.00,
        'security_deposit_paid' => 0.00,
        'monthly_rent' => 0.00,
        'block_name' => '',
        'floor' => '',
        'allotment_no' => '',
        'allotment_date' => ''
    ];
    if (!$db || $memberId <= 0) return $summary;

    try {
        $stmt = $db->prepare("
            SELECT a.*, c.chamber_no, c.block_name, c.floor 
            FROM chamber_allotments a
            JOIN chambers c ON c.id = a.chamber_id
            WHERE a.member_id = ? AND a.status = 'active'
            ORDER BY a.id DESC LIMIT 1
        ");
        $stmt->execute([$memberId]);
        $row = $stmt->fetch();
        if ($row) {
            $summary['has_chamber'] = true;
            $summary['chamber_no'] = $row['chamber_no'];
            $summary['allotment_id'] = intval($row['id']);
            $summary['monthly_rent'] = floatval($row['monthly_rent']);
            $summary['security_deposit'] = floatval($row['security_deposit']);
            $summary['security_deposit_paid'] = floatval($row['security_deposit_paid']);
            $summary['block_name'] = $row['block_name'];
            $summary['floor'] = $row['floor'];
            $summary['allotment_no'] = $row['allotment_no'];
            $summary['allotment_date'] = $row['allotment_date'];
            
            // Calculate outstanding rent
            $summary['outstanding_rent'] = getChamberOutstandingRent($row['id'], $db);
        }
    } catch (PDOException $e) {
        error_log("Failed fetching member chamber summary: " . $e->getMessage());
    }
    return $summary;
}

/**
 * Generate Unique Chamber Application Number (Phase 9)
 */
function generateChamberApplicationNumber($db) {
    $year = date('Y');
    try {
        $stmt = $db->prepare("SELECT application_no FROM chamber_applications WHERE application_no LIKE :pattern ORDER BY id DESC LIMIT 1");
        $stmt->execute(['pattern' => "DBA/ROOM/$year/%"]);
        $last = $stmt->fetchColumn();
        if ($last) {
            $parts = explode('/', $last);
            $seq = intval(end($parts)) + 1;
        } else {
            $seq = 1;
        }
        return sprintf("DBA/ROOM/%s/%04d", $year, $seq);
    } catch (PDOException $e) {
        error_log("Failed generating chamber application number: " . $e->getMessage());
        return "DBA/ROOM/$year/" . rand(1000, 9999);
    }
}

/**
 * Generate Unique Chamber Allotment Number (Phase 9)
 */
function generateChamberAllotmentNumber($db) {
    $year = date('Y');
    try {
        $stmt = $db->prepare("SELECT allotment_no FROM chamber_allotments WHERE allotment_no LIKE :pattern ORDER BY id DESC LIMIT 1");
        $stmt->execute(['pattern' => "DBA/ALLOT/$year/%"]);
        $last = $stmt->fetchColumn();
        if ($last) {
            $parts = explode('/', $last);
            $seq = intval(end($parts)) + 1;
        } else {
            $seq = 1;
        }
        return sprintf("DBA/ALLOT/%s/%04d", $year, $seq);
    } catch (PDOException $e) {
        error_log("Failed generating chamber allotment number: " . $e->getMessage());
        return "DBA/ALLOT/$year/" . rand(1000, 9999);
    }
}

/**
 * Generate Unique Chamber Rent Payment Receipt Number (Phase 9)
 */
function generateChamberPaymentNumber($db) {
    $year = date('Y');
    try {
        $stmt = $db->query("SELECT payment_no FROM chamber_rent_payments ORDER BY id DESC LIMIT 1");
        $last = $stmt->fetchColumn();
        if ($last) {
            $parts = explode('/', $last);
            $seq = intval(end($parts)) + 1;
        } else {
            $seq = 1;
        }
        return sprintf("DBA/RP/%s/%05d", $year, $seq);
    } catch (PDOException $e) {
        error_log("Failed generating chamber payment number: " . $e->getMessage());
        return "DBA/RP/$year/" . rand(10000, 99999);
    }
}

/**
 * Recalculate Chamber Rent Due details (Phase 9)
 */
function recalculateChamberRentDue($dueId, $db) {
    if (!$db || $dueId <= 0) return false;
    try {
        $stmt = $db->prepare("SELECT allotment_id, rent_amount, penalty_amount, discount_amount, due_date FROM chamber_rent_dues WHERE id = ?");
        $stmt->execute([$dueId]);
        $due = $stmt->fetch();
        if (!$due) return false;

        $payable = floatval($due['rent_amount']) + floatval($due['penalty_amount']) - floatval($due['discount_amount']);
        if ($payable < 0) $payable = 0.00;

        // Sum allocations
        $alloc_stmt = $db->prepare("
            SELECT SUM(a.allocated_amount) 
            FROM chamber_rent_payment_allocations a
            JOIN chamber_rent_payments p ON p.id = a.payment_id
            WHERE a.rent_due_id = ? AND p.status = 'confirmed'
        ");
        $alloc_stmt->execute([$dueId]);
        $paid = floatval($alloc_stmt->fetchColumn() ?: 0.00);

        $outstanding = $payable - $paid;
        if ($outstanding < 0) $outstanding = 0.00;

        // Resolve status
        $status = 'pending';
        if ($outstanding <= 0) {
            $status = 'paid';
        } elseif ($paid > 0) {
            $status = 'partially_paid';
        } elseif (date('Y-m-d') > $due['due_date']) {
            $status = 'overdue';
        }

        $up = $db->prepare("
            UPDATE chamber_rent_dues 
            SET payable_amount = ?, paid_amount = ?, outstanding_amount = ?, status = ? 
            WHERE id = ?
        ");
        $up->execute([$payable, $paid, $outstanding, $status, $dueId]);
        return true;
    } catch (PDOException $e) {
        error_log("Failed to recalculate chamber due $dueId: " . $e->getMessage());
        return false;
    }
}

/**
 * PHASE 12: Helper Functions
 */

/**
 * Get system setting with request-level static cache
 */
function getSetting($key, $default = null) {
    static $settings = null;
    if ($settings === null) {
        $settings = [];
        $db = Database::getConnection();
        if ($db) {
            try {
                $stmt = $db->query("SELECT setting_key, setting_value, value_type FROM system_settings");
                while ($row = $stmt->fetch()) {
                    $val = $row['setting_value'];
                    if ($row['value_type'] === 'boolean') {
                        $val = ($val === '1' || $val === 'true' || $val === true);
                    } elseif ($row['value_type'] === 'integer') {
                        $val = intval($val);
                    }
                    $settings[$row['setting_key']] = $val;
                }
            } catch (PDOException $e) {
                error_log("Failed to load system settings: " . $e->getMessage());
            }
        }
    }
    return array_key_exists($key, $settings) ? $settings[$key] : $default;
}

/**
 * Centralized audit log writer (Phase 12 Centralized Audit)
 */
function logAudit($module, $action, $record_type = null, $record_id = null, $remarks = null, $old_values = null, $new_values = null) {
    $db = Database::getConnection();
    if (!$db) return false;
    try {
        $user_id = $_SESSION['user_id'] ?? null;
        $role = $_SESSION['role'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $old_json = $old_values ? json_encode($old_values, JSON_UNESCAPED_UNICODE) : null;
        $new_json = $new_values ? json_encode($new_values, JSON_UNESCAPED_UNICODE) : null;

        $stmt = $db->prepare("
            INSERT INTO `audit_logs` (user_id, role, module, action, record_type, record_id, old_values, new_values, remarks, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$user_id, $role, $module, $action, $record_type, $record_id, $old_json, $new_json, $remarks, $ip, $ua]);
    } catch (PDOException $e) {
        error_log("Failed to log audit entry: " . $e->getMessage());
        return false;
    }
}

/**
 * Reusable helper to send internal notifications (Requirement 34)
 */
function createNotification($user_id, $member_id, $role_target, $title, $message, $type, $link = null) {
    $db = Database::getConnection();
    if (!$db) return false;
    try {
        $stmt = $db->prepare("
            INSERT INTO `notifications` (user_id, member_id, role_target, title, message, type, link)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$user_id, $member_id, $role_target, $title, $message, $type, $link]);
    } catch (PDOException $e) {
        error_log("Failed to create notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Format Currency to INR (Requirement 45)
 */
function formatCurrency($amount, $precision = true) {
    $formatted = number_format(floatval($amount), $precision ? 2 : 0);
    return '₹' . $formatted;
}

/**
 * Centralized status label/badge resolver (Requirement 46)
 */
function getStatusBadge($status) {
    $status = strtolower(trim($status));
    switch ($status) {
        case 'active':
        case 'published':
        case 'approved':
        case 'confirmed':
        case 'eligible':
        case 'issued':
        case 'elected':
        case 'unopposed':
        case 'read':
        case 'resolved':
            return '<span class="badge bg-success font-size-xs">Active</span>';
        case 'pending':
        case 'pending_approval':
        case 'submitted':
        case 'under_review':
        case 'new':
            return '<span class="badge bg-warning text-dark font-size-xs">Pending</span>';
        case 'inactive':
        case 'expired':
        case 'suspended':
        case 'cancelled':
        case 'rejected':
        case 'not_eligible':
        case 'withdrawn':
        case 'deceased':
            return '<span class="badge bg-danger font-size-xs">' . htmlspecialchars(ucfirst($status)) . '</span>';
        case 'draft':
        case 'scheduled':
            return '<span class="badge bg-secondary font-size-xs">Draft</span>';
        default:
            return '<span class="badge bg-dark font-size-xs">' . htmlspecialchars($status) . '</span>';
    }
}

/**
 * Controlled file upload helper (Requirement 15 & 16)
 */
function storeUploadedFile($fileVar, $category) {
    if (!isset($_FILES[$fileVar]) || $_FILES[$fileVar]['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    $file = $_FILES[$fileVar];
    
    // 1. Size Validation (Requirement 15)
    $maxSize = defined('UPLOAD_MAX_SIZE') ? UPLOAD_MAX_SIZE : 5242880;
    if ($file['size'] > $maxSize) {
        return false;
    }

    // 2. Extension strict check
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
    if (!in_array($ext, $allowedExts)) {
        return false;
    }

    // 3. Server-side MIME verification (Requirement 16)
    $mime = '';
    if (function_exists('mime_content_type')) {
        $mime = mime_content_type($file['tmp_name']);
    } else {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
    }

    $allowedMimes = [
        'jpg'  => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'png'  => ['image/png'],
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document']
    ];

    if (!isset($allowedMimes[$ext]) || !in_array($mime, $allowedMimes[$ext])) {
        return false;
    }

    // 4. Generate clean target path
    $baseDir = __DIR__ . '/../storage/' . $category . '/';
    if (!is_dir($baseDir)) {
        @mkdir($baseDir, 0755, true);
    }
    $filename = uniqid($category . '_', true) . '.' . $ext;
    $target = $baseDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $target)) {
        return 'storage/' . $category . '/' . $filename;
    }
    return false;
}

/**
 * Controlled file deletion helper (Requirement 41)
 */
function deleteStoredFileSafely($path) {
    if (empty($path)) return false;
    $fullPath = __DIR__ . '/../' . ltrim($path, '/');
    if (file_exists($fullPath) && is_file($fullPath)) {
        return unlink($fullPath);
    }
    return false;
}

/**
 * Get Secure filepath (Requirement 41)
 */
function getSecureFilePath($path) {
    return __DIR__ . '/../' . ltrim($path, '/');
}

/**
 * Simple session-based rate limiter (Requirement 43)
 */
function checkRateLimit($key, $max_requests, $period_seconds) {
    if (session_status() === PHP_SESSION_NONE) {
        return true;
    }
    $now = time();
    $sessionKey = 'rate_limit_' . $key;
    if (!isset($_SESSION[$sessionKey])) {
        $_SESSION[$sessionKey] = [
            'requests' => 1,
            'start_time' => $now
        ];
        return true;
    }
    $data = &$_SESSION[$sessionKey];
    $elapsed = $now - $data['start_time'];
    if ($elapsed > $period_seconds) {
        $data['requests'] = 1;
        $data['start_time'] = $now;
        return true;
    }
    $data['requests']++;
    if ($data['requests'] > $max_requests) {
        return false;
    }
    return true;
}

/**
 * Stream Protected Private files (Requirement 41)
 */
function streamProtectedFile($path) {
    $fullPath = getSecureFilePath($path);
    if (!file_exists($fullPath) || !is_file($fullPath)) {
        http_response_code(404);
        exit("फाइल नहीं मिली (File not found).");
    }
    $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
    $mime = 'application/octet-stream';
    if ($ext === 'pdf') $mime = 'application/pdf';
    elseif ($ext === 'jpg' || $ext === 'jpeg') $mime = 'image/jpeg';
    elseif ($ext === 'png') $mime = 'image/png';
    
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($fullPath));
    header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');
    readfile($fullPath);
    exit;
}

/**
 * Output CSRF hidden input field (insertCSRF)
 */
function insertCSRF() {
    echo '<input type="hidden" name="csrf_token" value="' . sanitize(generate_csrf_token()) . '">';
}

/**
 * Verify CSRF token from POST request (verifyCSRF)
 */
function verifyCSRF() {
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    return verify_csrf_token($token);
}
?>






