<?php
include 'conn.php';
$query = "SELECT * from fasilitas1 ";
if (isset($_POST['fasilitas1'])) {
    $fasilitas1 = mysqli_real_escape_string($conn, $_POST['fasilitas1']);
    
    $query = "UPDATE fasilitas1 SET fasilitas1='$fasilitas1'";
    if (mysqli_query($conn, $query)) {
        echo "Fasilitas berhasil diperbarui!";
    }
}
?>