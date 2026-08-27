<?php
/**
 * Permissions Matrix and RBAC Helpers
 * District Bar Association, Banda
 */

// Prevent direct access
if (count(get_included_files()) === 1) {
    http_response_code(403);
    exit("Direct access forbidden.");
}

require_once __DIR__ . '/auth.php';

/**
 * Returns the array of permissions mapped to each role.
 * 
 * @return array
 */
function get_role_permissions_matrix() {
    return [
        'admin' => [
            // Admin role has implicit bypass ('all')
            'all'
        ],
        'president' => [
            'dashboard.view',
            'members.view',
            'notices.view',
            'notices.approve',
            'finance.view',
            'election.view'
        ],
        'mahasachiv' => [
            'dashboard.view',
            'members.view',
            'members.manage',
            'notices.manage',
            'office_bearers.manage',
            'id_cards.manage',
            'wakalatnama.manage',
            'rooms.manage'
        ],
        'member' => [
            'dashboard.view',
            'profile.view',
            'profile.update',
            'id_card.view',
            'wakalatnama.view',
            'fees.view',
            'notices.view'
        ]
    ];
}

/**
 * Check if the current authenticated user has a specific permission.
 * 
 * @param string $permission The permission string identifier.
 * @return bool
 */
function hasPermission($permission) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $role = currentRole();
    $matrix = get_role_permissions_matrix();
    
    if (!isset($matrix[$role])) {
        return false;
    }
    
    // Admin bypasses all checks
    if (in_array('all', $matrix[$role])) {
        return true;
    }
    
    return in_array($permission, $matrix[$role]);
}

/**
 * Enforce permission check, redirecting to 403 page if not authorized.
 * 
 * @param string $permission The permission string identifier.
 */
function requirePermission($permission) {
    requireLogin();
    
    if (!hasPermission($permission)) {
        // Log access violation attempt in future if needed
        http_response_code(403);
        redirect(SITE_URL . '/403.php');
    }
}
