<?php
include 'conn.php';
$query = "SELECT * from fasilitas4 ";
if (isset($_POST['fasilitas4'])) {
    $fasilitas4 = mysqli_real_escape_string($conn, $_POST['fasilitas4']);
    
    $query = "UPDATE fasilitas4 SET fasilitas4='$fasilitas4'";
    if (mysqli_query($conn, $query)) {
        echo "Fasilitas berhasil diperbarui!";
    }
}
?>