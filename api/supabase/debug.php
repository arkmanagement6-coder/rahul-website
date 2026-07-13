<?php
// api/supabase/debug.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>SMMPayNow PHP Environment Debug</h3>";
echo "PHP Version: " . phpversion() . "<br>";
echo "PDO Drivers: " . implode(', ', PDO::getAvailableDrivers()) . "<br><br>";

try {
    require_once 'config.php';
    echo "Config loaded successfully!<br>";
    $pdo = getDbConnection();
    echo "Database connected successfully!<br>";
    
    // Test a simple query
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables in database: " . implode(', ', $tables) . "<br>";
} catch (Throwable $t) {
    echo "<strong>Fatal Error:</strong> " . $t->getMessage() . " in " . $t->getFile() . " on line " . $t->getLine() . "<br>";
} catch (Exception $e) {
    echo "<strong>Exception:</strong> " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine() . "<br>";
}
?>
