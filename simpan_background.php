<?php
// Koneksi ke database
$conn = new mysqli("localhost", "root", "", "urban_soccer");
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Lokasi folder uploads/
$targetDir = __DIR__ . "/uploads/"; // __DIR__ = folder tempat file PHP ini berada

// Cek jika folder belum ada, buat otomatis
if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true); // 0777 = semua bisa baca/tulis, true = buat subfolder juga kalau perlu
}

if (isset($_FILES['background'])) {
    $fileName = time() . "_" . basename($_FILES['background']['name']);
    $targetFile = $targetDir . $fileName;

    if (move_uploaded_file($_FILES['background']['tmp_name'], $targetFile)) {
        // Simpan path relatif ke database
        $pathRelatif = "uploads/" . $fileName;

        $stmt = $conn->prepare("UPDATE image SET image = ? WHERE id = 1");
        $stmt->bind_param("s", $pathRelatif);
        $stmt->execute();
        $stmt->close();

        echo $pathRelatif; // Dikirim ke JavaScript
    } else {
        http_response_code(500);
        echo "Gagal mengunggah gambar.";
    }
} else {
    http_response_code(400);
    echo "Tidak ada file yang dikirim.";
}
