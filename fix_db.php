<?php
require_once 'config/config.php';
try {
    $conn->exec("ALTER TABLE transfers DROP INDEX transfer_number");
    echo "Dropped unique index transfer_number<br>";
} catch (Exception $e) {
    echo "transfer_number index might not exist or error: " . $e->getMessage() . "<br>";
}
try {
    $conn->exec("ALTER TABLE transfers DROP INDEX idx_transfer_number");
    echo "Dropped idx_transfer_number<br>";
} catch (Exception $e) {
    echo "idx_transfer_number might not exist or error: " . $e->getMessage() . "<br>";
}
try {
    $conn->exec("ALTER TABLE transfers ADD INDEX idx_transfer_number (transfer_number)");
    echo "Added non-unique index idx_transfer_number<br>";
} catch (Exception $e) {
    echo "Error adding idx_transfer_number: " . $e->getMessage() . "<br>";
}
echo "Database fix complete.";
?>
