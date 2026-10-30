<?php
require_once 'config.php';

echo "=== Setting up Expense Management Tables ===\n\n";

// Create expenses table
echo "1. Creating expenses table...\n";
$sql = "CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    expense_category VARCHAR(100) NOT NULL,
    expense_date DATE NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    description TEXT,
    payment_method VARCHAR(50),
    receipt_number VARCHAR(50),
    vendor_name VARCHAR(255),
    notes TEXT,
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_expense_date (expense_date),
    INDEX idx_category (expense_category),
    INDEX idx_created_by (created_by),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

try {
    if ($conn->query($sql)) {
        echo "   ✓ expenses table created successfully\n";
    } else {
        echo "   ✗ Error creating expenses table: " . $conn->error . "\n";
    }
} catch (Exception $e) {
    echo "   ✗ Exception: " . $e->getMessage() . "\n";
}

// Create expense_categories table
echo "\n2. Creating expense_categories table...\n";
$sql = "CREATE TABLE IF NOT EXISTS expense_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

try {
    if ($conn->query($sql)) {
        echo "   ✓ expense_categories table created successfully\n";
    } else {
        echo "   ✗ Error creating expense_categories table: " . $conn->error . "\n";
    }
} catch (Exception $e) {
    echo "   ✗ Exception: " . $e->getMessage() . "\n";
}

// Insert default expense categories
echo "\n3. Inserting default expense categories...\n";
$categories = [
    ['Office Supplies', 'Stationery, printer ink, paper, etc.'],
    ['Rent', 'Office rent, property lease'],
    ['Utilities', 'Electricity, water, internet, phone bills'],
    ['Salaries', 'Employee salaries and wages'],
    ['Marketing', 'Advertising, promotions, marketing campaigns'],
    ['Travel', 'Business travel, accommodation, meals'],
    ['Insurance', 'Business insurance premiums'],
    ['Maintenance', 'Equipment maintenance, repairs'],
    ['Software', 'Software licenses, subscriptions'],
    ['Training', 'Employee training and development'],
    ['Entertainment', 'Business entertainment, client meetings'],
    ['Bank Charges', 'Bank fees, transaction charges'],
    ['Taxes', 'Business taxes, GST, other taxes'],
    ['Miscellaneous', 'Other business expenses']
];

foreach ($categories as $category) {
    $sql = "INSERT IGNORE INTO expense_categories (category_name, description) VALUES (?, ?)";
    try {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $category[0], $category[1]);
        $stmt->execute();
        $stmt->close();
        echo "   ✓ Category '{$category[0]}' inserted\n";
    } catch (Exception $e) {
        echo "   ✗ Error inserting category '{$category[0]}': " . $e->getMessage() . "\n";
    }
}

// Check table structures
echo "\n4. Verifying table structures:\n";
echo "   expenses table structure:\n";
try {
    $result = $conn->query('DESCRIBE expenses');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo "     {$row['Field']} - {$row['Type']}\n";
        }
    }
} catch (Exception $e) {
    echo "     Error: " . $e->getMessage() . "\n";
}

echo "\n   expense_categories table structure:\n";
try {
    $result = $conn->query('DESCRIBE expense_categories');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo "     {$row['Field']} - {$row['Type']}\n";
        }
    }
} catch (Exception $e) {
    echo "     Error: " . $e->getMessage() . "\n";
}

// Check sample data
echo "\n5. Sample data check:\n";
try {
    $result = $conn->query('SELECT COUNT(*) as count FROM expenses');
    $count = $result->fetch_assoc()['count'];
    echo "   Total expenses: {$count}\n";
    
    $result = $conn->query('SELECT COUNT(*) as count FROM expense_categories');
    $count = $result->fetch_assoc()['count'];
    echo "   Total expense categories: {$count}\n";
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

echo "\n=== Setup Complete ===\n";
echo "You can now access the expense management at: expense_management.php\n";
?>
