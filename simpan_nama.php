<?php
include 'conn.php';
$query = "SELECT * from teks where id=10";
if (isset($_POST['teks'])) {
    $nama = mysqli_real_escape_string($conn, $_POST['teks']);
    
    $query = "UPDATE teks SET teks='$nama' WHERE id=10";
    if (mysqli_query($conn, $query)) {
        echo "Judul berhasil diperbarui!";
    }
}
?>