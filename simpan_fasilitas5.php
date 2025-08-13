<?php
include 'conn.php';
$query = "SELECT * from fasilitas5 ";
if (isset($_POST['fasilitas5'])) {
    $fasilitas5 = mysqli_real_escape_string($conn, $_POST['fasilitas5']);
    
    $query = "UPDATE fasilitas5 SET fasilitas5s='$fasilitas5'";
    if (mysqli_query($conn, $query)) {
        echo "Fasilitas berhasil diperbarui!";
    }
}
?>