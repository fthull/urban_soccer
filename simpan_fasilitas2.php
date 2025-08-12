<?php
include 'conn.php';
$query = "SELECT * from fasilitas2 ";
if (isset($_POST['fasilitas2'])) {
    $fasilitas2 = mysqli_real_escape_string($conn, $_POST['fasilitas2']);
    
    $query = "UPDATE fasilitas2 SET fasilitas2='$fasilitas2'";
    if (mysqli_query($conn, $query)) {
        echo "Fasilitas berhasil diperbarui!";
    }
}
?>