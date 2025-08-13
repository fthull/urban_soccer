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
        // Jika tidak ada file, ambil dari POST data (untuk teks)
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
        .admin-editable-text, .admin-editable-image, .admin-editable-link {
            border: 2px dashed #00fff2ff;
            padding: 5px;
            display: inline-block;
            cursor: pointer;
            transition: border-color 0.2s ease;
        }
        .admin-editable-text:hover, .admin-editable-image:hover, .admin-editable-link:hover {
            border-color: #ffc107;
        }
        .hidden-file-input { display: none; }
        .admin-editable-image {
            width: 100%;
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
    
   <<script src="AdminLTE-3.1.0/plugins/jquery/jquery.min.js"></script>
<script src="AdminLTE-3.1.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script>
    $(document).ready(function() {
        // --- Logika untuk Edit Teks ---
        $(document).on('blur', '.admin-editable-text', function() {
            var element = $(this);
            var key = element.data('key');
            var newContent = element.text().trim();
            
            if (key) {
                $.ajax({
                    url: '<?php echo isset($_SESSION['admin']) ? 'manage_content.php' : ''; ?>',
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
                    }
                });
            }
        });

        // --- Logika untuk Edit Gambar Latar Belakang ---
        $(document).on('click', '.admin-editable-image', function(e) {
            e.preventDefault(); 
            // Hindari klik pada teks di dalam elemen gambar
            if ($(e.target).is('h1, p, a, button')) {
                return;
            }
            $('#heroImageInput').trigger('click');
        });

        // Tangani perubahan file pada input file yang sudah ada
        $('#heroImageInput').on('change', function() {
            var file = this.files[0];
            var element = $('.admin-editable-image');
            var key = element.data('key');

            if (file && key) {
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
                            // Perbarui background-image dengan URL baru
                            element.css('background-image', 'url("' + response.new_value + '")');
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
            }
        });
    });
</script>
</body>
</html>