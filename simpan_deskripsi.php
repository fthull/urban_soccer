<?php
include 'conn.php';
$query = "SELECT * from deskripsi where id=1";
if (isset($_POST['deskripsi'])) {
    $desc = mysqli_real_escape_string($conn, $_POST['deskripsi']);
    
    $query = "UPDATE deskripsi SET deskripsi='$desc' WHERE id=6";
    if (mysqli_query($conn, $query)) {
        echo "Deskripsi berhasil diperbarui!";
    }
}
?>