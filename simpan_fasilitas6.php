<?php
include 'conn.php';
$query = "SELECT * from fasilitas6 ";
if (isset($_POST['fasilitas6'])) {
    $fasilitas6 = mysqli_real_escape_string($conn, $_POST['fasilitas6']);
    
    $query = "UPDATE fasilitas6 SET fasilitas6='$fasilitas6'";
    if (mysqli_query($conn, $query)) {
        echo "Fasilitas berhasil diperbarui!";
    }
}
?>