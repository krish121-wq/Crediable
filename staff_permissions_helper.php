<?php
/**
 * Staff Permissions Helper
 * Manages staff user permissions and access control
 */

class StaffPermissions {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Check if a staff user has a specific permission
     * Staff users have access to everything except restricted features
     */
    public function hasPermission($user_id, $permission_name) {
        // Staff users have access to all permissions by default
        // No need to check database - they get everything except restricted features
        return true;
    }
    
    /**
     * Get all permissions for a user
     */
    public function getUserPermissions($user_id) {
        // Staff users have all permissions by default except restricted ones
        return [
            ['permission_name' => 'dashboard_view', 'permission_description' => 'View Dashboard', 'module' => 'dashboard'],
            ['permission_name' => 'products_manage', 'permission_description' => 'Manage Products', 'module' => 'inventory'],
            ['permission_name' => 'sales_manage', 'permission_description' => 'Manage Sales', 'module' => 'sales'],
            ['permission_name' => 'purchase_manage', 'permission_description' => 'Manage Purchases', 'module' => 'purchase'],
            ['permission_name' => 'reports_view', 'permission_description' => 'View Reports', 'module' => 'reports']
        ];
    }
    
    /**
     * Grant permissions to a user
     */
    public function grantPermissions($user_id, $permission_ids, $granted_by) {
        try {
            $this->conn->begin_transaction();
            
            // First, remove existing permissions
            $stmt = $this->conn->prepare("DELETE FROM staff_user_permissions WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            
            // Then add new permissions
            if (!empty($permission_ids)) {
                $stmt = $this->conn->prepare("
                    INSERT INTO staff_user_permissions (user_id, permission_id, granted_by) 
                    VALUES (?, ?, ?)
                ");
                
                foreach ($permission_ids as $permission_id) {
                    $stmt->bind_param("iii", $user_id, $permission_id, $granted_by);
                    $stmt->execute();
                }
            }
            
            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            error_log("Grant permissions error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all available permissions grouped by module
     */
    public function getAllPermissions() {
        // Return all available permissions grouped by module
        $permissions = [
            ['id' => 1, 'permission_name' => 'dashboard_view', 'permission_description' => 'View Dashboard', 'module' => 'dashboard'],
            ['id' => 2, 'permission_name' => 'products_manage', 'permission_description' => 'Manage Products', 'module' => 'inventory'],
            ['id' => 3, 'permission_name' => 'sales_manage', 'permission_description' => 'Manage Sales', 'module' => 'sales'],
            ['id' => 4, 'permission_name' => 'purchase_manage', 'permission_description' => 'Manage Purchases', 'module' => 'purchase'],
            ['id' => 5, 'permission_name' => 'reports_view', 'permission_description' => 'View Reports', 'module' => 'reports']
        ];

        // Group by module
        $grouped = [];
        foreach ($permissions as $perm) {
            $grouped[$perm['module']][] = $perm;
        }

        return $grouped;
    }
    
    /**
     * Check if user can access a specific page
     * Staff users can access all pages except restricted ones
     */
    public function canAccessPage($user_id, $page) {
        // Check if page is restricted for staff
        if ($this->isRestrictedForStaff($page)) {
            return false;
        }

        // Allow access to all other pages
        return true;
    }
    
    /**
     * Get default permissions for new staff users
     */
    public function getDefaultStaffPermissions() {
        return [
            'dashboard_view',
            'products_view',
            'categories_view',
            'inventory_view',
            'sales_view',
            'sales_create',
            'cash_bills_view',
            'cash_bills_create',
            'purchase_view',
            'purchase_return_view',
            'delivery_challan_view',
            'debit_note_view',
            'accounting_view',
            'reports_view'
        ];
    }
    
    /**
     * Get restricted features for staff (features they cannot access)
     */
    public function getRestrictedFeatures() {
        return [
            'salary_management.php',
            'create-user.php' // Add Staff feature
        ];
    }
    
    /**
     * Check if a feature is restricted for staff
     */
    public function isRestrictedForStaff($page) {
        return in_array($page, $this->getRestrictedFeatures());
    }
}

// Global function to check permissions
function checkStaffPermission($permission_name) {
    // Staff users have access to all permissions by default
    // Only admins and staff are allowed, guests are not
    if (!isset($_SESSION['user_type'])) {
        return false; // Not logged in
    }

    return true; // Both admin and staff have access to all features (except restricted ones)
}

// Global function to check page access
function checkPageAccess($page) {
    global $conn;

    if (!isset($_SESSION['user_type'])) {
        return false; // Not logged in
    }

    if ($_SESSION['user_type'] === 'admin') {
        return true; // Admin has all access
    }

    // For staff users, check if page is restricted
    $permissions = new StaffPermissions($conn);
    return !$permissions->isRestrictedForStaff($page);
}
?>
