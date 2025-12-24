<?php
// Include the configuration file
require_once 'config.php';

// Test query to fetch companies
try {
    $sql = "SELECT * FROM companies LIMIT 5";
    $stmt = $pdo->query($sql);
    
    if($stmt->rowCount() > 0){
        echo "<h2>Database Connection Successful!</h2>";
        echo "<h3>Sample Companies:</h3>";
        echo "<ul>";
        while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
            echo "<li>ID: " . $row['company_id'] . " - " . htmlspecialchars($row['company_name']) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "Connection successful but no companies found in the database.";
    }
} catch(PDOException $e) {
    die("ERROR: Could not execute query. " . $e->getMessage());
}

// Close connection
closeConnection();
?>