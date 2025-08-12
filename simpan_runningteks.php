<?php
include 'conn.php';
$query = "SELECT * from runningtext where id=1";
if (isset($_POST['runningtext'])) {
    $running_text = mysqli_real_escape_string($conn, $_POST['runningtext']);
    
    $query = "UPDATE runningtext SET runningtext='$running_text' WHERE id=1";
    if (mysqli_query($conn, $query)) {
        echo "Running text berhasil diperbarui!";
    }
}
?>