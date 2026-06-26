<?php
$host = 'localhost';
$db   = 'logctic';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
     $conn = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
try {
    $conn->exec('ALTER TABLE transfers DROP INDEX transfer_number');
    echo "Dropped unique index transfer_number\n";
} catch (Exception $e) {
    echo "Error dropping transfer_number: " . $e->getMessage() . "\n";
}
try {
    $conn->exec('ALTER TABLE transfers DROP INDEX idx_transfer_number');
    echo "Dropped index idx_transfer_number\n";
} catch (Exception $e) {
    echo "Error dropping idx_transfer_number: " . $e->getMessage() . "\n";
}
try {
    $conn->exec('ALTER TABLE transfers ADD INDEX idx_transfer_number (transfer_number)');
    echo "Added non-unique index idx_transfer_number\n";
} catch (Exception $e) {
    echo "Error adding idx_transfer_number: " . $e->getMessage() . "\n";
}
?>
