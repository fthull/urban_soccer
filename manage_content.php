<?php
session_start();

// Koneksi ke database
include "conn.php";
global $conn;

// Pastikan direktori 'uploads' ada dan dapat ditulis
if (!is_dir('uploads')) {
    mkdir('uploads', 0777, true);
}

// --- Endpoint: Tangani pembaruan konten dari permintaan AJAX (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_content_inline'])) {
    header('Content-Type: application/json');

    $key_name = $_POST['key'] ?? '';
    $new_content_value = '';

    // Sertakan file helper konten untuk fungsi update
    include "content_helper.php";

    // Cek apakah ada file yang diunggah
    if (isset($_FILES['file'])) {
        $file = $_FILES['file'];
        $uploadDir = 'uploads/';
        $fileName = uniqid() . '_' . basename($file['name']); // Menambahkan uniqid() agar nama file unik
        $targetFilePath = $uploadDir . $fileName;

        // Validasi file (contoh sederhana)
        $fileType = pathinfo($targetFilePath, PATHINFO_EXTENSION);
        $allowTypes = array('jpg', 'png', 'jpeg', 'gif');

        if (in_array(strtolower($fileType), $allowTypes)) {
            if (move_uploaded_file($file['tmp_name'], $targetFilePath)) {
                $new_content_value = $targetFilePath;
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal mengunggah file.']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Hanya file JPG, JPEG, PNG, & GIF yang diperbolehkan.']);
            exit;
        }
    } else {
        // Jika tidak ada file, ambil dari POST data (untuk teks atau link)
        $new_content_value = $_POST['value'] ?? '';
    }

    if (empty($key_name) || (!isset($_POST['value']) && !isset($_FILES['file']))) {
        echo json_encode(['success' => false, 'message' => 'Key atau Value tidak boleh kosong.']);
        exit;
    }

    // Gunakan fungsi updateContent untuk memperbarui database
    if (updateContent($conn, $key_name, $new_content_value)) {
        echo json_encode(['success' => true, 'message' => 'Konten berhasil diperbarui.', 'new_value' => $new_content_value]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui konten.']);
    }

    exit;
}

// Set variabel global untuk mengaktifkan mode admin di index.php
$GLOBALS['is_admin_mode'] = true;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin | Kelola Website</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="AdminLTE-3.1.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="AdminLTE-3.1.0/dist/css/adminlte.min.css">
    <style>
        .admin-header {
            background-color: #343a40;
            color: white;
            padding: 10px;
            text-align: center;
            border-bottom: 2px solid #007bff;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 9999;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        body { padding-top: 60px; }
        .admin-content-wrapper { margin-top: 20px; }
        /* Kelas dasar untuk semua elemen yang bisa diedit */
        .admin-editable-text, .admin-editable-image, .admin-editable-link {
            border: 2px dashed #00fff2ff;
            padding: 5px;
            cursor: pointer;
            transition: border-color 0.2s ease;
        }
        .admin-editable-text:hover, .admin-editable-image:hover, .admin-editable-link:hover {
            border-color: #ffc107;
        }
        .hidden-file-input { display: none; }
        
        /* CSS tambahan untuk elemen yang bisa diedit */
        .admin-editable-text, .admin-editable-link {
            display: inline-block;
        }
        .admin-editable-image {
            width: 100%; /* Pastikan gambar mengisi lebar kontainernya */
        }
        /* Penting: Pastikan elemen slideshow punya ukuran */
        .slide {
            height: 500px; /* Atur tinggi sesuai kebutuhan */
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
    </style>
</head>
<body class="hold-transition layout-top-nav dark-mode">
    <div class="wrapper">
        <div class="admin-header">
            <h4>Mode Admin Aktif</h4>
            <small>Klik pada teks atau gambar yang memiliki garis putus-putus untuk mengedit. Perubahan akan tersimpan otomatis.</small>
            <br><br><a href="admin.php" class="btn-book btn btn-lg w-65 h-10 bg-white" style="border-radius: 5px;">
                <h6>Kembali Ke Dashboard</h6>
            </a>
        </div>

        <div class="admin-content-wrapper">
            <?php include 'index.php'; ?>
        </div>
    </div>
    
    <script src="AdminLTE-3.1.0/plugins/jquery/jquery.min.js"></script>
    <script src="AdminLTE-3.1.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Tangani perubahan pada elemen teks
            $(document).on('blur', '[contenteditable="true"]', function() {
                var element = $(this);
                var key = element.data('key');
                var newContent = element.text();
                
                $.ajax({
                    url: 'manage_content.php',
                    type: 'POST',
                    data: {
                        update_content_inline: true,
                        key: key,
                        value: newContent
                    },
                    success: function(response) {
                        if (response.success) {
                            console.log('Konten ' + key + ' berhasil diperbarui.');
                        } else {
                            console.error('Gagal memperbarui ' + key + ': ' + response.message);
                            alert('Gagal memperbarui konten: ' + response.message);
                        }
                    },
                    error: function() {
                        alert('Terjadi kesalahan saat berkomunikasi dengan server.');
                    }
                });
            });

            // Tangani klik pada elemen gambar (termasuk background-image)
            $(document).on('click', '.admin-editable-image', function(e) {
                e.preventDefault(); 
                var fileInput = $(this).closest('div[style*="position: relative;"]').find('.hidden-file-input');
                fileInput.click();
            });

            // Tangani klik pada elemen link (peta)
            $(document).on('click', '.admin-editable-link', function(e) {
                e.preventDefault();
                var element = $(this);
                var key = element.data('key');
                var currentLink = element.attr('href') || prompt('Masukkan URL baru:', element.data('default-link'));

                if (currentLink !== null) {
                    $.ajax({
                        url: 'manage_content.php',
                        type: 'POST',
                        data: {
                            update_content_inline: true,
                            key: key,
                            value: currentLink
                        },
                        success: function(response) {
                            if (response.success) {
                                element.attr('href', response.new_value);
                                console.log('Link ' + key + ' berhasil diperbarui.');
                            } else {
                                console.error('Gagal memperbarui ' + key + ': ' + response.message);
                                alert('Gagal memperbarui link: ' + response.message);
                            }
                        }
                    });
                }
            });


            // Tangani perubahan file pada input file
            $(document).on('change', '.hidden-file-input', function() {
                var fileInput = this;
                var file = fileInput.files[0];
                
                // Cari elemen yang akan diperbarui (baik img atau div)
                var element = $(this).closest('div').find('.admin-editable-image');
                var key = element.data('key');
                
                if (file) {
                    var formData = new FormData();
                    formData.append('update_content_inline', true);
                    formData.append('key', key);
                    formData.append('file', file);
                    
                    $.ajax({
                        url: 'manage_content.php',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(response) {
                            if (response.success) {
                                // Logika untuk membedakan src dan background-image
                                if (element.is('img')) {
                                    // Jika elemen adalah tag <img> (seperti logo)
                                    element.attr('src', response.new_value);
                                } else {
                                    // Jika elemen bukan tag <img> (seperti div dengan background-image)
                                    element.css('background-image', 'url("' + response.new_value + '")');
                                }
                                console.log('Konten ' + key + ' berhasil diperbarui.');
                                // Reload halaman untuk memastikan semua elemen slideshow diperbarui dengan benar
                                location.reload();
                            } else {
                                console.error('Gagal memperbarui ' + key + ': ' + response.message);
                                alert('Gagal memperbarui konten: ' + response.message);
                            }
                        },
                        error: function() {
                            alert('Terjadi kesalahan saat berkomunikasi dengan server.');
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>