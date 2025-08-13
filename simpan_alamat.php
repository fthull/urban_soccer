<?php
// Koneksi ke database
$conn = new mysqli("localhost", "root", "", "urban_soccer");

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Ambil data dari POST
if (isset($_POST['alamat'])) {
    $alamat = $conn->real_escape_string($_POST['alamat']);

    // Update data alamat di tabel
    $sql = "UPDATE alamat SET alamat = '$alamat' WHERE id = 1";

    if ($conn->query($sql) === TRUE) {
        echo "Alamat berhasil disimpan.";
    } else {
        echo "Gagal menyimpan alamat: " . $conn->error;
    }
} else {
    echo "Data alamat tidak diterima.";
}

$conn->close();
?>