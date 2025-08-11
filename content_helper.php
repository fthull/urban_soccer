<?php
// content_helper.php

/**
 * Mengambil semua konten dari tabel 'site_content'.
 *
 * @param mysqli $conn Objek koneksi database.
 * @return array Array asosiatif dari konten, dengan content_key sebagai kunci array.
 */
function getAllContent($conn) {
    $content = [];
    $result = $conn->query("SELECT content_key, content_value FROM site_content");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $content[$row['content_key']] = $row['content_value'];
        }
    }
    return $content;
}

/**
 * Memperbarui atau menambahkan konten ke tabel 'site_content' menggunakan satu query.
 *
 * @param mysqli $conn Objek koneksi database.
 * @param string $key Kunci konten (content_key).
 * @param string $value Nilai konten (content_value).
 * @return bool True jika berhasil, false jika gagal.
 */
function updateContent($conn, $key, $value) {
    // Gunakan INSERT ... ON DUPLICATE KEY UPDATE untuk menangani INSERT dan UPDATE dalam satu perintah
    $sql = "INSERT INTO site_content (content_key, content_value) VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE content_value = VALUES(content_value)";
    
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        // Jika gagal menyiapkan query, tampilkan error
        error_log("Gagal menyiapkan statement: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("ss", $key, $value);
    
    return $stmt->execute();
}
?>