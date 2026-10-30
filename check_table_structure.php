<?php
require_once 'config.php';

try {
    // Check the structure of purchase_returns table
    $sql = "DESCRIBE purchase_returns";
    $result = $conn->query($sql);
    
    if ($result) {
        echo "Structure of purchase_returns table:\n";
        echo "Field\t\tType\t\tNull\tKey\tDefault\tExtra\n";
        echo "-----\t\t----\t\t----\t---\t-------\t-----\n";
        while ($row = $result->fetch_assoc()) {
            echo $row['Field'] . "\t\t" . $row['Type'] . "\t\t" . $row['Null'] . "\t" . $row['Key'] . "\t" . $row['Default'] . "\t" . $row['Extra'] . "\n";
        }
    } else {
        echo "Error describing table: " . $conn->error . "\n";
    }
    
    // Also check if the table exists
    $sql = "SHOW TABLES LIKE 'purchase_returns'";
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        echo "\nTable 'purchase_returns' exists.\n";
    } else {
        echo "\nTable 'purchase_returns' does not exist.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

$conn->close();
?>
