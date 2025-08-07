<?php
// Kode koneksi database Anda
$servername = "localhost";
$username_db = "root";
$password_db = "";
$dbname = "urban_soccer";

$conn = new mysqli($servername, $username_db, $password_db, $dbname);

if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}