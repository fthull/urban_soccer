<?php
session_start();
include "conn.php";
global $conn;
$active_page = 'kelola-website';
// Set variabel global untuk mengaktifkan mode admin di index.php
$GLOBALS['is_admin_mode'] = true;

// Pastikan direktori 'uploads' ada
if (!is_dir('uploads')) {
    mkdir('uploads', 0777, true);
}

// Endpoint: Hapus konten (GET request)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['key'])) {
    header('Content-Type: application/json');
    $key_name = $_GET['key'];

    if (empty($key_name)) {
        echo json_encode(['success' => false, 'message' => 'Key tidak boleh kosong.']);
        exit;
    }

    // Ambil data lama (untuk menghapus file fisik)
    $stmt = $conn->prepare("SELECT value FROM site_content WHERE content_key = ?");
    $stmt->bind_param("s", $key_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $content = $result->fetch_assoc();

    if ($content) {
        // Hapus file fisik jika ada dan bukan default
        if (!empty($content['value']) && file_exists($content['value']) && strpos($content['value'], 'galeri/gal9.png') === false) {
            unlink($content['value']);
        }

        // Update database jadi default image
        $defaultImage = 'galeri/gal9.png';
        $stmt = $conn->prepare("UPDATE site_content SET value = ? WHERE content_key = ?");
        $stmt->bind_param("ss", $defaultImage, $key_name);

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus data di database.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Data tidak ditemukan.']);
    }

    exit;
}

// --- Endpoint: Update konten (POST request) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_content_inline'])) {
    header('Content-Type: application/json');

    $key_name = $_POST['key'] ?? '';
    $new_content_value = '';

    include "content_helper.php";

    if (isset($_FILES['file'])) {
        $file = $_FILES['file'];
        $uploadDir = 'uploads/';
        $fileName = uniqid() . '_' . basename($file['name']);
        $targetFilePath = $uploadDir . $fileName;

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
        $new_content_value = $_POST['value'] ?? '';
    }

    if (empty($key_name) || (!isset($_POST['value']) && !isset($_FILES['file']))) {
        echo json_encode(['success' => false, 'message' => 'Key atau Value tidak boleh kosong.']);
        exit;
    }

    if (updateContent($conn, $key_name, $new_content_value)) {
        echo json_encode(['success' => true, 'message' => 'Konten berhasil diperbarui.', 'new_value' => $new_content_value]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal memperbarui konten.']);
    }

    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin | Kelola Website</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="AdminLTE-3.1.0/plugins/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css">
    <link rel="stylesheet" href="AdminLTE-3.1.0/plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css">
    <link rel="stylesheet" href="AdminLTE-3.1.0/plugins/icheck-bootstrap/icheck-bootstrap.min.css">
    <link rel="stylesheet" href="AdminLTE-3.1.0/plugins/jqvmap/jqvmap.min.css">
    <link rel="stylesheet" href="AdminLTE-3.1.0/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="AdminLTE-3.1.0/plugins/overlayScrollbars/css/OverlayScrollbars.min.css">
    <link rel="stylesheet" href="AdminLTE-3.1.0/plugins/daterangepicker/daterangepicker.css">
    <link rel="stylesheet" href="AdminLTE-3.1.0/plugins/summernote/summernote-bs4.min.css">
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
            position: relative;
            transition: background-image 0.3s ease;
        }
        
        /* Styles for upload indicators */
        .image-upload-loading, .image-upload-success {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            z-index: 1000;
        }
        .image-upload-success {
            background: rgba(0,200,0,0.7);
        }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed dark-mode">
    <div class="wrapper">
        <?php include 'sidebar.php'; ?>
        </div>
    <div class="content-wrapper">
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <div class="admin-content-wrapper">
                            <?php include 'index.php'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>

<script src="AdminLTE-3.1.0/plugins/jquery/jquery.min.js"></script>
<script src="AdminLTE-3.1.0/plugins/jquery-ui/jquery-ui.min.js"></script>
<script>$.widget.bridge('uibutton', $.ui.button)</script>
<script src="AdminLTE-3.1.0/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="AdminLTE-3.1.0/plugins/chart.js/Chart.min.js"></script>
<script src="AdminLTE-3.1.0/plugins/sparklines/sparkline.js"></script>
<script src="AdminLTE-3.1.0/plugins/jqvmap/jquery.vmap.min.js"></script>
<script src="AdminLTE-3.1.0/plugins/jqvmap/maps/jquery.vmap.usa.js"></script>
<script src="AdminLTE-3.1.0/plugins/jquery-knob/jquery.knob.min.js"></script>
<script src="AdminLTE-3.1.0/plugins/moment/moment.min.js"></script>
<script src="AdminLTE-3.1.0/plugins/daterangepicker/daterangepicker.js"></script>
<script src="AdminLTE-3.1.0/plugins/tempusdominus-bootstrap-4/js/tempusdominus-bootstrap-4.min.js"></script>
<script src="AdminLTE-3.1.0/plugins/summernote/summernote-bs4.min.js"></script>
<script src="AdminLTE-3.1.0/plugins/overlayScrollbars/js/jquery.overlayScrollbars.min.js"></script>
<script src="AdminLTE-3.1.0/dist/js/adminlte.js"></script>
<script src="AdminLTE-3.1.0/dist/js/demo.js"></script>
<script src="AdminLTE-3.1.0/dist/js/pages/dashboard.js"></script>
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
                    try {
                        const data = typeof response === 'string' ? JSON.parse(response) : response;
                        if (data.success) {
                            console.log('Konten ' + key + ' berhasil diperbarui.');
                        } else {
                            console.error('Gagal memperbarui ' + key + ': ' + data.message);
                            alert('Gagal memperbarui konten: ' + data.message);
                        }
                    } catch (e) {
                        console.error('Error parsing response:', e);
                        alert('Terjadi kesalahan saat memproses respons server.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    alert('Terjadi kesalahan koneksi. Silakan coba lagi.');
                }
            });
        }
    });

    // --- Enhanced Image Editing Logic ---
    $(document).on('click', '.admin-editable-image', function(e) {
        e.preventDefault();
        
        // Skip if clicking on child elements that shouldn't trigger image edit
        if ($(e.target).is('h1, h2, h3, h4, h5, h6, p, a, button, input, textarea, select')) {
            return;
        }
        
        // Create file input if it doesn't exist
        if (!$('#imageEditInput').length) {
            $('body').append('<input type="file" id="imageEditInput" accept="image/*" style="display: none;">');
        }
        
        // Store reference to the clicked element
        $('#imageEditInput').data('target', $(this));
        $('#imageEditInput').trigger('click');
    });

    // Handle file selection for image editing
    $(document).on('change', '#imageEditInput', function() {
        const file = this.files[0];
        if (!file) return;

        const target = $(this).data('target');
        const key = target.data('key');
        
        // Validate file type
        const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!validTypes.includes(file.type)) {
            alert('Harap pilih file gambar yang valid (JPEG, PNG, GIF, atau WebP).');
            return;
        }
        
        // Validate file size (max 5MB)
        if (file.size > 20 * 1024 * 1024) {
            alert('Ukuran gambar harus kurang dari 20MB.');
            return;
        }

        // Show loading indicator
        target.append('<div class="image-upload-loading">Mengunggah...</div>');

        const formData = new FormData();
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
                $('.image-upload-loading').remove();
                
                try {
                    const data = typeof response === 'string' ? JSON.parse(response) : response;
                    
                    if (data.success) {
                        // Check if target is an img element or background image
                        if (target.is('img')) {
                            // Update img src with cache busting
                            const newUrl = data.new_value + (data.new_value.includes('?') ? '&' : '?') + 't=' + Date.now();
                            target.attr('src', newUrl);
                        } else {
                            // Update background-image with cache busting
                            const newUrl = data.new_value + (data.new_value.includes('?') ? '&' : '?') + 't=' + Date.now();
                            target.css('background-image', `url("${newUrl}")`);
                        }
                        
                        // Show success message temporarily
                        const successMsg = $('<div class="image-upload-success">Gambar berhasil diperbarui!</div>');
                        target.append(successMsg);
                        setTimeout(() => successMsg.fadeOut(500, () => successMsg.remove()), 2000);
                    } else {
                        throw new Error(data.message || 'Terjadi kesalahan yang tidak diketahui');
                    }
                } catch (e) {
                    console.error('Error updating image:', e);
                    alert('Gagal memperbarui gambar: ' + e.message);
                }
            },
            error: function(xhr, status, error) {
                $('.image-upload-loading').remove();
                console.error('AJAX Error:', status, error);
                alert('Terjadi kesalahan server. Silakan coba lagi.');
            },
            complete: function() {
                // Reset file input
                $('#imageEditInput').val('').removeData('target');
            }
        });
    });

    // Handle window load event
    window.addEventListener('load', function() {
        const preloader = document.querySelector('.preloader');
        if (preloader) { 
            preloader.style.display = 'none'; 
        }
    });
});
</script>
</body>
</html>