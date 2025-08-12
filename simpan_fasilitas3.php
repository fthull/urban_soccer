<?php
include 'conn.php';
$query = "SELECT * from fasilitas3 ";
if (isset($_POST['fasilitas3'])) {
    $fasilitas3 = mysqli_real_escape_string($conn, $_POST['fasilitas3']);
    
    $query = "UPDATE fasilitas3 SET fasilitas3='$fasilitas3'";
    if (mysqli_query($conn, $query)) {
        echo "Fasilitas berhasil diperbarui!";
    }
}
?>