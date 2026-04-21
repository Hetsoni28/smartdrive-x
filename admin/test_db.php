<?php
$conn = mysqli_connect("localhost", "root", "", "smartdrive_db");
$res = mysqli_query($conn, "SHOW COLUMNS FROM bookings");
while($row = mysqli_fetch_assoc($res)) {
    echo $row["Field"] . " - " . $row["Type"] . "\n";
}
?>
