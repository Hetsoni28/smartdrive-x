<?php
include '../includes/db_connect.php';

// Check columns in cars table
$result = mysqli_query($conn, "DESCRIBE cars");
$columns = [];
while ($row = mysqli_fetch_assoc($result)) {
    $columns[] = $row;
}
echo "CARS TABLE:\n";
print_r($columns);

$alter_result = mysqli_query($conn, "ALTER TABLE cars MODIFY image TEXT");
if ($alter_result) {
    echo "Successfully modified image column to TEXT.\n";
} else {
    echo "Error modifying image column: " . mysqli_error($conn) . "\n";
}
