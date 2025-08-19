<?php
include "conn.php";

$message = "";
$sweet_alert_script = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['nama']) && isset($_POST['no_hp'])) {
        $nama = $_POST['nama'];
        $no_hp = $_POST['no_hp'];

        // Periksa apakah data dengan nama dan no_hp yang sama sudah ada di tabel booking
        $check_sql = "SELECT id FROM booking WHERE nama = ? AND no_hp = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $nama, $no_hp);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        // Validasi: Periksa apakah ada baris yang cocok
        if ($check_result->num_rows > 0) {
            // Jika nama dan no_hp cocok, lanjutkan proses unggah bukti pembayaran
            $row = $check_result->fetch_assoc();
            $id_to_update = $row['id'];
            
            if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] == 0) {
                $file_name = $_FILES['bukti_pembayaran']['name'];
                $file_tmp = $_FILES['bukti_pembayaran']['tmp_name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $new_file_name = uniqid() . '.' . $file_ext;
                $upload_dir = "uploads/";

                if (move_uploaded_file($file_tmp, $upload_dir . $new_file_name)) {
                    // Lakukan UPDATE dengan bukti pembayaran baru dan ubah status menjadi 'confirmed'
                    $update_sql = "UPDATE booking SET bukti_pembayaran = ?, status = 'confirmed' WHERE id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("si", $new_file_name, $id_to_update);

                    if ($update_stmt->execute()) {
                        $sweet_alert_script = "
                            <script>
                                Swal.fire({
                                  title: 'Berhasil!',
                                  text: '✅ Pembayaran berhasil dikirim! Silakan tunggu konfirmasi dari admin.',
                                  icon: 'success',
                                  confirmButtonText: 'OK'
                                }).then((result) => {
                                  if (result.isConfirmed) {
                                    window.location.href = 'index.php';
                                  }
                                });
                            </script>
                        ";
                    } else {
                        $message = "<div class='alert alert-danger'>❌ Gagal memperbarui data.</div>";
                    }
                    $update_stmt->close();
                } else {
                    $message = "<div class='alert alert-danger'>❌ Gagal mengunggah file.</div>";
                }
            } else {
                $message = "<div class='alert alert-danger'>❌ Silakan unggah file bukti pembayaran.</div>";
            }
        } else {
            // Jika nama dan no_hp tidak cocok dengan data booking yang ada
            $message = "<div class='alert alert-danger'>❌ Nama atau Nomor HP tidak terdaftar. Pastikan data yang Anda masukkan sesuai dengan data booking.</div>";
        }

        $check_stmt->close();
    } else {
        $message = "<div class='alert alert-danger'>❌ Data nama atau nomor HP tidak lengkap.</div>";
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfirmasi Pembayaran</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            display: flex;
            justify-content: center;
            padding: 50px 0;
            margin: 0;
        }
        .main-container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 100%;
        }
        h2, h3 {
            text-align: center;
            color: #333;
            margin-bottom: 20px;
        }
        h3 {
            text-align: left;
            margin-top: 0;
        }
        .payment-info {
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .bank-details {
            border: 1px solid #007bff;
            border-radius: 8px;
            padding: 15px;
            background-color: #e6f2ff;
            text-align: center;
            margin: 20px 0;
        }
        .bank-details h4 {
            margin: 0 0 10px 0;
            color: #0056b3;
        }
        .bank-details p {
            margin: 0;
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .bank-details .copy-icon {
            font-size: 16px;
            margin-left: 10px;
            cursor: pointer;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn-submit {
            background-color: #28a745;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            margin-top: 20px;
        }
        .btn-submit:hover {
            background-color: #218838;
        }
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* --- Gaya untuk header Konfirmasi Pembayaran --- */
        .header-box {
            background-color: #0056b3;
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
            font-weight: bold;
        }
        .header-box h2 {
            color: white;
            margin: 0;
        }

        /* --- Gaya baru untuk input file --- */
        .file-upload-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }
        .file-upload-input {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }
        .file-upload-label {
            background-color: #f0f0f0;
            border: 1px solid #ccc;
            border-radius: 4px;
            padding: 10px;
            display: flex;
            align-items: center;
            cursor: pointer;
            justify-content: space-between;
        }
        .file-upload-label:hover {
            background-color: #e9e9e9;
        }
        .file-upload-label .file-name {
            color: #555;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
            flex-grow: 1;
        }
        .file-upload-label .upload-button {
            background-color: #007bff;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <div class="header-box">
            <h2>Konfirmasi Pembayaran</h2>
        </div>
        <h3>Cara Pembayaran</h3>
        <div class="payment-info">
            <ol>
                <li>Lakukan transfer sebesar **Rp 150.000** ke rekening di bawah ini.</li>
                <li>Setelah transfer berhasil, unggah bukti pembayaran melalui formulir di bawah.</li>
                <li>Pastikan Nama dan Nomor Telepon yang Anda masukkan sesuai dengan data booking.</li>
            </ol>
        </div>
        <div class="bank-details">
            <h4>**Transfer Bank BCA**</h4>
            <p>
                1228354545
                <span class="copy-icon" onclick="copyText('1228354545')">📋</span>
            </p>
            <p style="font-size: 16px;">a/n Enggede Mini Soccer</p>
        </div>
        <hr style="border: 0; border-top: 1px solid #eee; margin: 30px 0;"> <h2>Konfirmasi Pembayaran</h2>
        <div id="countdown-timer" style="text-align: center; font-weight: bold; margin-bottom: 20px;"></div>
        <?php echo $message; ?>
        <p>Unggah bukti pembayaran dan verifikasi data Anda untuk menyelesaikan proses booking.</p>
        <form action="pembayaran.php" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="nama">Nama Lengkap</label>
                <input type="text" id="nama" name="nama" required>
            </div>
            <div class="form-group">
                <label for="no_hp">Nomor HP</label>
                <input type="text" id="no_hp" name="no_hp" required>
            </div>
            <div class="form-group">
                <label for="bukti_pembayaran">Unggah Bukti Pembayaran (JPG/PNG)</label>
                <div class="file-upload-wrapper">
                    <input type="file" id="bukti_pembayaran" name="bukti_pembayaran" class="file-upload-input" accept="image/jpeg, image/png" required>
                    <label for="bukti_pembayaran" class="file-upload-label">
                        <span class="file-name">Belum ada file dipilih</span>
                        <span class="upload-button">Pilih File</span>
                    </label>
                </div>
            </div>
            <button type="submit" class="btn-submit">Verifikasi & Unggah</button>
        </form>
    </div>
    
   <script>
    const timeoutInMinutes = 60;
    const timerElement = document.getElementById('countdown-timer');
    const fileInput = document.getElementById('bukti_pembayaran');
    const fileNameSpan = document.querySelector('.file-name');
    let endTime;
    let timerInterval;

    function updateTimer() {
        const now = new Date().getTime();
        const distance = endTime - now;

        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);

        const formattedMinutes = String(minutes).padStart(2, '0');
        const formattedSeconds = String(seconds).padStart(2, '0');
        
        if (distance > 0) {
            timerElement.textContent = `Waktu tersisa: ${formattedMinutes}:${formattedSeconds}`;
        } else {
            timerElement.textContent = "Waktu Habis";
        }
        
        if (distance < 0) {
            clearInterval(timerInterval);
            alert('Waktu Anda telah habis. Silakan coba lagi.');
            sessionStorage.removeItem('timerEndTime');
            window.location.href = 'index.php';
        }
    }

    function startTimer() {
        // Hapus waktu yang tersimpan sebelumnya untuk memastikan timer selalu mulai dari awal
        sessionStorage.removeItem('timerEndTime');

        // Buat waktu baru dan simpan di sessionStorage
        endTime = new Date().getTime() + timeoutInMinutes * 60 * 1000;
        sessionStorage.setItem('timerEndTime', endTime);

        // Mulai timer
        timerInterval = setInterval(updateTimer, 1000);
    }

    fileInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) {
            fileNameSpan.textContent = file.name;
        } else {
            fileNameSpan.textContent = 'Belum ada file dipilih';
        }
    });
    
    // Panggil fungsi startTimer saat halaman dimuat
    window.onload = startTimer;

    function copyText(text) {
        navigator.clipboard.writeText(text).then(() => {
            alert('Nomor rekening berhasil disalin!');
        }).catch(err => {
            console.error('Gagal menyalin teks: ', err);
        });
    }
</script>
    <?php echo $sweet_alert_script; ?>
</body>
</html>