<?php
// Koneksi ke database dan helper konten
include "conn.php";
include "content_helper.php";

// Set mode admin jika diakses dari file admin.php
$is_admin_mode = isset($GLOBALS['is_admin_mode']) && $GLOBALS['is_admin_mode'] === true;

// Ambil semua konten dari database
$site_content = getAllContent($conn);
$sql_delete = "DELETE FROM booking WHERE status = 'pending' AND waktu_booking < DATE_SUB(NOW(), INTERVAL ? MINUTE)";
$stmt_delete = $conn->prepare($sql_delete);
$stmt_delete->bind_param("i", $time_limit_minutes);
$stmt_delete->execute();
$stmt_delete->close();
/**
 * Fungsi helper untuk mendapatkan konten dari database atau nilai default.
 * Digunakan untuk mengisi data ke dalam HTML.
 *
 * @param string $key Kunci konten
 * @param string $default Nilai default jika kunci tidak ditemukan
 * @return string Nilai konten
 */
function get_content($key, $default = '') {
    global $site_content;
    // htmlspecialchars() digunakan untuk mencegah XSS
    return isset($site_content[$key]) ? htmlspecialchars($site_content[$key]) : htmlspecialchars($default);
}

// =========================================================================
// HANDLER BARU: UNTUK MENYIMPAN KONTEN YANG DIEDIT OLEH ADMIN
// =========================================================================
if (isset($_POST['action']) && $_POST['action'] === 'save_content') {
    $key = $_POST['key'] ?? '';
    $value = $_POST['value'] ?? '';

    // Bersihkan nilai untuk mencegah SQL Injection
    $clean_value = $conn->real_escape_string($value);
    $clean_key = $conn->real_escape_string($key);

    // SQL UPDATE/INSERT untuk memperbarui data
    // Menggunakan nama tabel 'site_content' sesuai database Anda
    $sql = "INSERT INTO site_content (content_key, content_value) 
             VALUES ('$clean_key', '$clean_value') 
             ON DUPLICATE KEY UPDATE content_value = '$clean_value'";

    if ($conn->query($sql) === TRUE) {
        echo "success"; // Respon ini akan ditangkap oleh JavaScript
    } else {
        echo "error";
    }
    exit;
}

// =========================================================================
// HANDLER UNTUK SUBMIT BOOKING BARU DARI JAVASCRIPT
// =========================================================================
date_default_timezone_set('Asia/Jakarta');
if (isset($_POST['action']) && $_POST['action'] === 'submit_booking') {
    $nama = $_POST['nama'] ?? '';
    $no_hp = $_POST['no_hp'] ?? '';
    $tanggal = $_POST['tanggal'] ?? '';
    $jam = $_POST['jam'] ?? '';

    // Ambil data sewa tambahan dari JSON
    $sewa_items = json_decode($_POST['sewa_items'] ?? '{}', true);
    $sewa_sepatu = $sewa_items['sewaSepatu'] ?? 0;
    $sewa_rompi = $sewa_items['sewaRompi'] ?? 0;
    // Kolom 'sewa_lainnya' dihapus

    if (!empty($nama) && !empty($no_hp) && !empty($tanggal) && !empty($jam)) {
        // Gabungkan tanggal dan jam untuk kolom 'waktu' bertipe datetime
        $waktu_datetime = $tanggal . ' ' . $jam . ':00';
    
        // Cek apakah slot sudah dibooking atau pending
        $cek_stmt = $conn->prepare("SELECT COUNT(*) FROM booking WHERE tanggal = ? AND waktu = ?");
        $cek_stmt->bind_param("ss", $tanggal, $waktu_datetime);
        $cek_stmt->execute();
        $cek_stmt->bind_result($count);
        $cek_stmt->fetch();
        $cek_stmt->close();

        if ($count > 0) {
            echo "Gagal: Slot ini sudah dibooking.";
            exit();
        }
        
        // =========================================================================
        // PERUBAHAN PENTING: Ambil harga dari database, bukan nilai statis
        // =========================================================================
        $harga_sewa_lapangan = floatval(get_content('field_rent_price', '700000'));
        $harga_sewa_sepatu = floatval(get_content('booking_shoes_price', '10000'));
        $harga_sewa_rompi = floatval(get_content('booking_vests_price', '5000'));
        // Variabel harga sewa lainnya dihapus

        $total_harga = $harga_sewa_lapangan + 
                        ($sewa_sepatu * $harga_sewa_sepatu) +
                        ($sewa_rompi * $harga_sewa_rompi);

        // Jika slot tersedia, simpan data booking
        $status = 'pending'; // Gunakan 'pending' sesuai skema database
        
        // PERBAIKAN: Tambahkan 'bukti_pembayaran' dan 'waktu_booking'
        $waktu_booking = date('Y-m-d H:i:s'); // Waktu saat booking dibuat
        $bukti_pembayaran = NULL; // Masih kosong saat booking awal

        // PERBAIKAN: Hapus 'sewa_lainnya' dari query INSERT
        $stmt = $conn->prepare("INSERT INTO booking (nama, no_hp, tanggal, waktu, status, sewa_sepatu, sewa_rompi, total_harga, bukti_pembayaran, waktu_booking) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        // PERBAIKAN: Hapus 'i' dari bind_param
        $stmt->bind_param("sssssiiiss", $nama, $no_hp, $tanggal, $waktu_datetime, $status, $sewa_sepatu, $sewa_rompi, $total_harga, $bukti_pembayaran, $waktu_booking);

        if ($stmt->execute()) {
            echo "Berhasil"; // Respon ini akan ditangkap oleh JavaScript
        } else {
            error_log("SQL Error: " . $stmt->error);
            echo "Gagal: Terjadi kesalahan saat menyimpan data.";
        }
    } else {
        echo "Gagal: Data tidak lengkap.";
    }
    exit();
}

/**
 * Handler untuk permintaan AJAX yang mengambil detail booking harian.
 */
if (isset($_GET['get_daily_bookings']) && isset($_GET['tanggal'])) {
    header('Content-Type: application/json');
    $tanggal = $_GET['tanggal'];
    $daily_bookings = [];

    $all_slots = [
        '06:00', '07:30', '09:00', '10:30', '12:00', '13:30', '15:00', '16:30',
        '18:00', '19:30', '21:00', '22:30'
    ];

    $stmt = $conn->prepare("SELECT TIME_FORMAT(waktu, '%H:%i') AS waktu_only, status FROM booking WHERE tanggal = ?");
    $stmt->bind_param("s", $tanggal);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $booked_data = [];
    while ($row = $result->fetch_assoc()) {
        $booked_data[$row['waktu_only']] = [
            'status' => $row['status']
        ];
    }
    $stmt->close();

    foreach ($all_slots as $slot_start_time) {
        $daily_bookings[$slot_start_time] = [
            'status' => $booked_data[$slot_start_time]['status'] ?? 'available'
        ];
    }
    
    echo json_encode($daily_bookings);
    exit();
}

// =========================================================================
// PERBAIKAN: Handler untuk booking mingguan
// =========================================================================
if (isset($_GET['get_weekly_bookings']) && isset($_GET['start_date'])) {
    header('Content-Type: application/json');
    $start_date = $_GET['start_date'];
    $end_date = date('Y-m-d', strtotime($start_date . ' +6 days'));
    $weekly_bookings = [];

    $stmt = $conn->prepare("SELECT tanggal, TIME_FORMAT(waktu, '%H:%i') AS waktu_only, status FROM booking WHERE tanggal BETWEEN ? AND ?");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $tanggal = $row['tanggal'];
        $time = $row['waktu_only'];
        
        if (!isset($weekly_bookings[$tanggal])) {
            $weekly_bookings[$tanggal] = [];
        }
        $weekly_bookings[$tanggal][$time] = [
            'status' => $row['status']
        ];
    }
    
    echo json_encode($weekly_bookings);
    exit();
}

// Handler untuk mengubah status booking (contoh untuk admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    header('Content-Type: application/json');
    $id = $_POST['id'] ?? null;
    $status_baru = $_POST['status'] ?? null;

    if ($id && $status_baru) {
        $stmt = $conn->prepare("UPDATE booking SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status_baru, $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'ID atau status tidak valid.']);
    }
    exit();
}

// Handler untuk mengubah booking (contoh untuk admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['change'])) {
    $nama = $_POST['nama'] ?? '';
    $no_hp = $_POST['no_hp'] ?? '';
    $tanggal = $_POST['tanggal'] ?? '';
    $jam = $_POST['jam'] ?? '';

    $cek = $conn->prepare("SELECT id FROM booking WHERE nama = ? AND no_hp = ?");
    $cek->bind_param("ss", $nama, $no_hp);
    $cek->execute();
    $cek->bind_result($booking_id);
    $cek->fetch();
    $cek->close();

    if (!$booking_id) {
        echo "Booking tidak ditemukan!";
        exit;
    }

    $waktu_baru = $tanggal . ' ' . $jam . ':00';

    $cekWaktu = $conn->prepare("SELECT COUNT(*) FROM booking WHERE waktu = ? AND id != ?");
    $cekWaktu->bind_param("si", $waktu_baru, $booking_id);
    $cekWaktu->execute();
    $cekWaktu->bind_result($count);
    $cekWaktu->fetch();
    $cekWaktu->close();

    if ($count > 0) {
        echo "Waktu tersebut sudah dibooking!";
        exit;
    }

    $update = $conn->prepare("UPDATE booking SET tanggal = ?, waktu = ? WHERE id = ?");
    $update->bind_param("ssi", $tanggal, $waktu_baru, $booking_id);
    if ($update->execute()) {
        echo "Berhasil update booking!";
    } else {
        echo "Gagal update booking.";
    }
    $update->close();
    exit;
}

// Handler untuk menghapus booking (contoh untuk admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_booking') {
    header('Content-Type: application/json');
    $id = $_POST['id'] ?? null;
    if ($id) {
        $stmt = $conn->prepare("DELETE FROM booking WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => $stmt->error]);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid.']);
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo get_content('website_title', 'MGD Soccer Magelang'); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&family=Montserrat:wght@400;600;700&family=Saira&family=Lexend+Deca&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-Y3IQOsa3niuvpqakr8NIT3EETRv0G6tFumi3detLTSYuXBc6XxbPEt9sOg6OcLgO" crossorigin="anonymous">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="index.css">

</head>
<style>
    .admin-editable-text {
            <?php if ($is_admin_mode): ?>border: 2px dashed #007bffff;
            padding: 5px;
            display: inline-block;
            cursor: pointer;
            <?php endif; ?>
        }

        .admin-editable-text:hover {
            <?php if ($is_admin_mode): ?>cursor: pointer;
            border-color: #ffc107;
            <?php endif; ?>
        }

        .admin-editable-image {
            <?php if ($is_admin_mode): ?>border: 2px dashed #007bff;
            padding: 5px;
            display: inline-block;
            cursor: pointer;
            <?php endif; ?>
        }

        .admin-editable-image:hover {
            <?php if ($is_admin_mode): ?>cursor: pointer;
            border-color: #ffc107;
            <?php endif; ?>
        }
        </style>
<body class="bg-black text-white <?php echo $is_admin_mode ? 'is-admin-mode' : ''; ?>">
    <header id="main-header" class="header fixed-navbar">

        <div class="header-inner">
            <nav class="navbar-menu">
                <nav class="nav">
                    <!-- <div class="container"> -->
                    <img src="<?php echo get_content('logo_image', 'logom.png'); ?>" alt="Soccer image" class="w-full max-w-[100px]
<?php echo $is_admin_mode ? 'admin-editable-image' : ''; ?>"
                        data-key="logo_image">
                    <?php if ($is_admin_mode): ?><input type="file" class="hidden-file-input"><?php endif; ?>

                     <!-- </div> -->

            <ul class="nav-links">
                <li><a class="nav-link" href="#">Beranda</a></li>
                <li><a class="nav-link" href="#booking-section">Pesan</a></li>
                <li><a class="nav-link" href="#gallery-section">Galeri</a></li>
                <li><a class="nav-link" href="#map-section">Kontak</a></li>
            </ul>

            <div class="hamburger">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </nav>
                <!-- <ul class="nav-links">
                    <li><a href="#" class="nav-link">Beranda</a></li>
                    <li><a href="#booking-section" class="nav-link">Pesan</a></li>
                    <li><a href="#gallery-section" class="nav-link">Galeri</a></li>
                    <li><a href="#map-section" class="nav-link">Kontak</a></li>
                </ul> -->
            </nav>
        </div>
    </header>

    <section

        id="home-section"
        class="hero-section <?php echo $is_admin_mode ? 'admin-editable-image' : ''; ?>"
        data-key="home_bg_image"
        style="background-image: url('<?php echo get_content('home_bg_image', 'bn1.png'); ?>');">

        <div class="hero-content-centered">
            <h1 class="hero-title <?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                data-key="home_title">
                <?php echo get_content('home_title', 'MGD SECCOR MAGELANG'); ?>
            </h1>
            <p class="hero-subtitle <?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                data-key="home_subtitle">
                <?php echo get_content('home_subtitle', 'Play the game you love'); ?>
            </p>
            <a href="#booking-section" class="btn btn-book">PESAN SEKARANG</a>
        </div>

    </section>

<section id="booking-section" class="booking-section py-5" style="background-color: #ffffffff;">
    <div class="container-fluid">
        <div class="row justify-content-center" data-aos="fade-up">
            <div class="col-12"> <h2 class="text-5xl font-bold text-center text-gray-800
<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
data-aos="fade-down"
style="font-family: 'Montserrat', sans-serif;"
<?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
data-key="book_heading">
<?php echo get_content('book_heading', 'Booking'); ?>
</h2><br><br>

                <div id="dailyScheduleContainer" style="max-width: 100%; flex-grow: 1;">
                    <p class="date-info text-dark fw-bold" id="currentDateInfo">Tanggal: 08 August 2025</p>
                    <div class="text-center d-flex flex-column align-items-center gap-2 mb-4">
                        <div class="date-picker-container fw-bold w-100"> <input type="date" id="date-input" value="<?php echo date('Y-m-d'); ?>" class="form-control"> </div>
                    </div>
                    <div class="schedule-grid" id="schedule"></div>
                </div>

                <div class="d-flex flex-column flex-sm-row justify-content-center my-4 gap-2"> <button id="toggleWeeklyBtn" class="btn btn-primary-custom w-100 w-sm-auto">Tampilkan Jadwal Mingguan</button>
                    <a href="pembayaran.php" id="toggleWeeklyBtn" class="btn btn-primary-custom w-100 w-sm-auto">Informasi Pembayaran</a>
                </div>

                <div id="weeklyScheduleContainer" style="display: none;">
                    <h2 class="booking-title fw-bold text-dark">Jadwal Mingguan</h2>
                    <div class="weekly-schedule-container overflow-auto">
                        <div class="weekly-schedule-table">
                            <div class="weekly-schedule-header">
                                <div class="weekly-header-item">Waktu</div>
                                <div class="weekly-header-item">Senin</div>
                                <div class="weekly-header-item">Selasa</div>
                                <div class="weekly-header-item">Rabu</div>
                                <div class="weekly-header-item">Kamis</div>
                                <div class="weekly-header-item">Jumat</div>
                                <div class="weekly-header-item">Sabtu</div>
                                <div class="weekly-header-item">Minggu</div>
                            </div>
                            <div id="weekly-schedule-body">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal-booking" id="detailBookingModal">
        <div class="modal-content-booking">
            </div>
    </div>
    
    <div class="modal-booking" id="formModal">
        <div class="modal-content-booking">
            <div class="form-container">
                <div class="row"> <div class="col-sm-6 mb-3"> <div class="form-group">
                            <label for="fullName">...</label>
                            <input type="text" id="fullName" placeholder="Nama Lengkap" required class="form-control">
                        </div>
                    </div>
                    <div class="col-sm-6 mb-3"> <div class="form-group">
                            <label for="phone">...</label>
                            <input type="tel" id="phone" placeholder="Nomor Telepon" required class="form-control">
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-sm-6 mb-3">
                        <div class="form-group">
                            <label for="sewaSepatu">...</label>
                            <div class="input-group"> <button type="button" class="btn btn-outline-secondary btn-minus" data-item="sewaSepatu">-</button>
                                <input type="number" id="sewaSepatu" class="form-control text-center sewa-item" data-price="30000" value="0" min="0" readonly />
                                <button type="button" class="btn btn-outline-secondary btn-plus" data-item="sewaSepatu">+</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 mb-3">
                        <div class="form-group">
                            <label for="sewaRompi">...</label>
                            <div class="input-group">
                                <button type="button" class="btn btn-outline-secondary btn-minus" data-item="sewaRompi">-</button>
                                <input type="number" id="sewaRompi" class="form-control text-center sewa-item" data-price="20000" value="0" min="0" readonly />
                                <button type="button" class="btn btn-outline-secondary btn-plus" data-item="sewaRompi">+</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="form-group">
                            <label for="notes">...</label>
                            <textarea id="notes" rows="3" placeholder="Misal: Siapkan air mineral" class="form-control"></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="d-flex justify-content-between button-group mt-4">
                <button class="btn btn-secondary cancel-btn" onclick="closeModal('formModal')">Batal</button>
                <button class="btn btn-primary confirm-btn" onclick="showFinalConfirmation()">Booking Sekarang</button>
            </div>
        </div>
    </div>
    
    <div class="modal-booking" id="finalConfirmationModal">
        <div class="modal-content-booking max-w-lg">
            </div>
    </div>
</section>
   <section class="about-section py-5">
  <div class="min-h-screen flex items-center px-6 py-12 relative">
    <div class="max-w-7xl w-full flex flex-col md:flex-row items-center md:items-start gap-8 md:gap-16">

      <div class="text-white max-w-xl px-4 md:px-12">
        <h1 class="font-bold mb-6 text-shadow text-4xl md:text-6xl
          <?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
          data-aos="fade-up" data-aos-delay="100" data-aos-duration="1000"
          style="font-family: 'Poppins', sans-serif;"
          <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
          data-key="abou_head">
          <?php echo get_content('abou_head', 'MGD Soccer Field'); ?>
        </h1>

        <p class="mb-6 text-shadow text-xl md:text-2xl <?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
          data-aos="fade-up" data-aos-delay="300" data-aos-duration="1000"
          style="font-family: 'Poppins', sans-serif;"
          <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
          data-key="about_text1">
          <?php echo get_content('about_text1', 'MGD Soccer Field Magelang hadir sebagai wadah bagi setiap komunitas pecinta sepak bola yang ingin merasakan sensasi bermain dengan kualitas terbaik dan suasana menyenangkan.'); ?>
        </p>

        <p class="text-shadow text-xl md:text-2xl <?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
          data-aos="fade-up" data-aos-delay="500" data-aos-duration="1000"
          style="font-family: 'Poppins', sans-serif;"
          <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
          data-key="about_text2">
          <?php echo get_content('about_text2', 'Kami yakin bahwa sepak bola bukan hanya tentang mencetak gol, tapi juga tentang menjaga kebersamaan, tawa, dan semangat sportifitas.'); ?>
        </p>
      </div>

      <!-- Image -->
      <div class="w-full md:w-auto flex justify-center" data-aos="fade-left" data-aos-duration="1600">
        <img src="<?php echo get_content('about_image_path', 'min.jpg'); ?>" 
          alt="Soccer image" 
          class="w-64 sm:w-72 md:w-80 lg:w-[380px] xl:w-[420px] rounded-lg shadow-lg <?php echo $is_admin_mode ? 'admin-editable-image' : ''; ?>"
          data-key="about_image_path">
        <?php if ($is_admin_mode): ?>
          <input type="file" class="hidden-file-input">
        <?php endif; ?>
      </div>

    </div>
  </div>
</section>

    <section id="gallery-section" class="gallery-section py-5 bg-white text-black">
        <div class="container mx-auto px-4">
            <h2 class="text-5xl font-bold text-center text-gray-800
<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                data-aos="fade-down"
                style="font-family: 'Poppins', sans-serif;"
                <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                data-key="gallery_heading">
                <?php echo get_content('gallery_heading', 'Galeri MGD Soccer Field'); ?>
            </h2>
            <marquee behavior="scroll" direction="left" scrollamount="5" class="my-4" style="font-size: 1.1rem; color: #333;">
                <span class="<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                    data-key="gallery_marquee_text"
                    <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>>
                    <?php echo get_content('gallery_marquee_text', 'Lebih dari sekadar lapangan, Urban Soccer Field adalah ruang untuk mencipta kenangan. Galeri ini memperlihatkan momen kebersamaan, kerja tim, dan semangat sportivitas dari para pecinta bola.'); ?>
                </span>
            </marquee>

            <div class="swiper myGallerySwiper max-w-4xl mx-auto">
                <div class="swiper-wrapper">
                    <div class="swiper-slide">
                        <div class="grid grid-cols-2 grid-rows-2 gap-4" id="galleryGrid">
                            <!-- Gambar-gambar yang sudah ada -->
                            <div style="position: relative;">
                                <img src="<?php echo get_content('gallery_image1_path', 'galeri/gal1.png'); ?>"
                                    alt="Gallery Image 1"
                                    class="gallery-image w-full h-auto object-cover shadow-md rounded-xl <?php echo $is_admin_mode ? 'admin-editable-image' : ''; ?>"
                                    data-key="gallery_image1_path">
                            </div>
                            <div style="position: relative;">
                                <img src="<?php echo get_content('gallery_image2_path', 'galeri/gal2.png'); ?>" alt="Gallery Image 2"
                                    class="gallery-image w-full h-auto object-cover shadow-md rounded-xl <?php echo $is_admin_mode ? 'admin-editable-image' : ''; ?>"
                                    data-key="gallery_image2_path">
                                <?php if ($is_admin_mode): ?>
                                    <input type="file" class="hidden-file-input" data-key="gallery_image2_path">
                                <?php endif; ?>
                            </div>
                            <div style="position: relative;">
                                <img src="<?php echo get_content('gallery_image3_path', 'galeri/gal3.png'); ?>" alt="Gallery Image 3"
                                    class="gallery-image w-full h-auto object-cover shadow-md rounded-xl <?php echo $is_admin_mode ? 'admin-editable-image' : ''; ?>"
                                    data-key="gallery_image3_path">
                                <?php if ($is_admin_mode): ?>
                                    <input type="file" class="hidden-file-input" data-key="gallery_image3_path">
                                <?php endif; ?>
                            </div>
                            <div style="position: relative;">
                                <img src="<?php echo get_content('gallery_image4_path', 'galeri/gal4.png'); ?>" alt="Gallery Image 4"
                                    class="gallery-image w-full h-auto object-cover shadow-md rounded-xl <?php echo $is_admin_mode ? 'admin-editable-image' : ''; ?>"
                                    data-key="gallery_image4_path">
                                <?php if ($is_admin_mode): ?>
                                    <input type="file" class="hidden-file-input" data-key="gallery_image4_path">
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>

                    <div class="swiper-slide">
                        <div class="grid grid-cols-2 grid-rows-2 gap-4">

                            <div style="position: relative;">
                                <img src="<?php echo get_content('gallery_image5_path', 'galeri/gal5.png'); ?>" alt="Gallery Image 5" class="gallery-image w-full h-auto object-cover shadow-md rounded-xl
<?php echo $is_admin_mode ? 'admin-editable-image' : ''; ?>"
                                    data-key="gallery_image5_path">
                                <?php if ($is_admin_mode): ?><input type="file" class="hidden-file-input"><?php endif; ?>
                            </div>
                            <div style="position: relative;">
                                <img src="<?php echo get_content('gallery_image6_path', 'galeri/gal6.png'); ?>" alt="Gallery Image 6" class="gallery-image w-full h-auto object-cover shadow-md rounded-xl
<?php echo $is_admin_mode ? 'admin-editable-image' : ''; ?>"
                                    data-key="gallery_image6_path">
                                <?php if ($is_admin_mode): ?><input type="file" class="hidden-file-input"><?php endif; ?>
                            </div>
                            <div style="position: relative;">
                                <img src="<?php echo get_content('gallery_image7_path', 'galeri/gal7.png'); ?>" alt="Gallery Image 7" class="gallery-image w-full h-auto object-cover shadow-md rounded-xl
<?php echo $is_admin_mode ? 'admin-editable-image' : ''; ?>"
                                    data-key="gallery_image7_path">
                                <?php if ($is_admin_mode): ?><input type="file" class="hidden-file-input"><?php endif; ?>
                            </div>
                            <div style="position: relative;">
                                <img src="<?php echo get_content('gallery_image8_path', 'galeri/gal8.png'); ?>" alt="Gallery Image 8" class="gallery-image w-full h-auto object-cover shadow-md rounded-xl
<?php echo $is_admin_mode ? 'admin-editable-image' : ''; ?>"
                                    data-key="gallery_image8_path">
                                <?php if ($is_admin_mode): ?><input type="file" class="hidden-file-input"><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="swiper-slide">
                        <div class="grid grid-cols-2 grid-rows-2 gap-4">

                            <div style="position: relative;">
                                <img src="<?php echo get_content('gallery_image9_path', 'galeri/gal9.png'); ?>" alt="Gallery Image 9" class="gallery-image w-full h-auto object-cover shadow-md rounded-xl
<?php echo $is_admin_mode ? 'admin-editable-image' : ''; ?>"
                                    data-key="gallery_image9_path">
                                <?php if ($is_admin_mode): ?><input type="file" class="hidden-file-input"><?php endif; ?>
                            </div>
                            <div style="position: relative;">
                                <img src="<?php echo get_content('gallery_image10_path', 'galeri/gal10.png'); ?>" alt="Gallery Image 10" class="gallery-image w-full h-auto object-cover shadow-md rounded-xl
<?php echo $is_admin_mode ? 'admin-editable-image' : ''; ?>"
                                    data-key="gallery_image10_path">
                                <?php if ($is_admin_mode): ?><input type="file" class="hidden-file-input"><?php endif; ?>
                            </div>
                            <div style="position: relative;">
                                <img src="<?php echo get_content('gallery_image11_path', 'galeri/gal11.png'); ?>" alt="Gallery Image 11" class="gallery-image w-full h-auto object-cover shadow-md rounded-xl
<?php echo $is_admin_mode ? 'admin-editable-image' : ''; ?>"
                                    data-key="gallery_image11_path">
                                <?php if ($is_admin_mode): ?><input type="file" class="hidden-file-input"><?php endif; ?>
                            </div>
                            <div style="position: relative;">
                                <img src="<?php echo get_content('gallery_image12_path', 'galeri/gal12.png'); ?>" alt="Gallery Image 12" class="gallery-image w-full h-auto object-cover shadow-md rounded-xl
<?php echo $is_admin_mode ? 'admin-editable-image' : ''; ?>"
                                    data-key="gallery_image12_path">
                                <?php if ($is_admin_mode): ?><input type="file" class="hidden-file-input"><?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="swiper-slide">
                        <div class="grid grid-cols-2 grid-rows-2 gap-4">

                            <div style="position: relative;">
                                <img src="<?php echo get_content('gallery_image13_path', 'galeri/gal13.png'); ?>" alt="Gallery Image 13" class="gallery-image w-full h-auto object-cover shadow-md rounded-xl
<?php echo $is_admin_mode ? 'admin-editable-image' : ''; ?>"
                                    data-key="gallery_image13_path">
                                <?php if ($is_admin_mode): ?><input type="file" class="hidden-file-input"><?php endif; ?>
                            </div>
                            <div style="position: relative;">
                                <img src="<?php echo get_content('gallery_image14_path', 'galeri/gal14.png'); ?>" alt="Gallery Image 14" class="gallery-image w-full h-auto object-cover shadow-md rounded-xl
<?php echo $is_admin_mode ? 'admin-editable-image' : ''; ?>"
                                    data-key="gallery_image14_path">
                                <?php if ($is_admin_mode): ?><input type="file" class="hidden-file-input"><?php endif; ?>
                            </div>
                            <div style="position: relative;">
                                <img src="<?php echo get_content('gallery_image15_path', 'galeri/gal15.png'); ?>" alt="Gallery Image 15" class="gallery-image w-full h-auto object-cover shadow-md rounded-xl
<?php echo $is_admin_mode ? 'admin-editable-image' : ''; ?>"
                                    data-key="gallery_image15_path">
                                <?php if ($is_admin_mode): ?><input type="file" class="hidden-file-input"><?php endif; ?>
                            </div>
                            <div style="position: relative;">
                                <img src="<?php echo get_content('gallery_image16_path', 'galeri/gal16.png'); ?>" alt="Gallery Image 16" class="gallery-image w-full h-auto object-cover shadow-md rounded-xl
<?php echo $is_admin_mode ? 'admin-editable-image' : ''; ?>"
                                    data-key="gallery_image16_path">
                                <?php if ($is_admin_mode): ?><input type="file" class="hidden-file-input"><?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
                <div class="swiper-pagination"></div>
            </div>
        </div>
        </div>
    </section>

<section class="bg-gradient-to-r from-[#121212] px-4 py-10">
    <div class="max-w-7xl mx-auto text-white">

        <div class="flex justify-center my-6">
            <h2 class="text-white text-4xl sm:text-5xl font-bold text-center
                <?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                data-aos="fade-down"
                style="font-family: 'Montserrat', sans-serif;"
                <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                data-key="program_heading">
                <?php echo get_content('program_heading', 'Program Spesial MGD'); ?>
            </h2>
        </div>

        <div class="flex flex-col md:flex-row justify-center items-center md:items-start gap-10 text-center md:text-left">
            <div class="flex-1 px-4 md:border-r border-gray-600">
                <ul class="list-disc list-inside space-y-3 text-left">
                    <li class="text-lg font-semibold
                        <?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                        data-key="feature_list1_text"
                        <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>>
                        <?php echo get_content('feature_list1_text', 'Lapangan ukuran 55 x 22 m'); ?>
                    </li>
                    <li class="text-lg font-semibold
                        <?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                        data-key="feature_list2_text"
                        <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>>
                        <?php echo get_content('feature_list2_text', 'Lampu penerangan'); ?>
                    </li>
                    <li class="text-lg font-semibold
                        <?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                        data-key="feature_list3_text"
                        <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>>
                        <?php echo get_content('feature_list3_text', 'Rumput sintetis Fifa Standar'); ?>
                    </li>
                </ul>
            </div>
            
            <div class="flex-1 px-4 md:border-r border-gray-600">
                <ul class="list-disc list-inside space-y-3 text-left">
                    <li class="text-lg font-semibold
                        <?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                        data-key="feature_list4_text"
                        <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>>
                        <?php echo get_content('feature_list4_text', 'Kamar Mandi'); ?>
                    </li>
                    <li class="text-lg font-semibold
                        <?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                        data-key="feature_list5_text"
                        <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>>
                        <?php echo get_content('feature_list5_text', 'Cafe and Mushola'); ?>
                    </li>
                    <li class="text-lg font-semibold
                        <?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                        data-key="feature_list6_text"
                        <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>>
                        <?php echo get_content('feature_list6_text', 'Parkir'); ?>
                    </li>
                </ul>
            </div>

            <div class="flex-1 flex flex-col items-center justify-center px-4">
                <i class="fas fa-map-marker-alt text-4xl mb-2 text-white"></i>
                <p class="text-lg font-semibold
                    <?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                    data-key="location_address1"
                    <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>>
                    <?php echo get_content('location_address1', 'Jl. Soekarno Hatta No.5'); ?>
                </p>
                <p class="text-lg font-semibold
                    <?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                    data-key="location_address2"
                    <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>>
                    <?php echo get_content('location_address2', 'Magelang'); ?>
                </p>
            </div>
        </div>

        <div class="flex flex-col md:flex-row gap-6 my-10 justify-center items-center">
            <div class="relative w-full sm:w-1/2 md:w-1/3">
                <div style="position: relative;">
                    <img src="<?php echo get_content('program1_image_path', 'pemain.png'); ?>" alt="Private Event" class="w-full h-auto object-cover rounded-lg
                        <?php echo $is_admin_mode ? 'admin-editable-image' : ''; ?>"
                        data-key="program1_image_path">
                    <?php if ($is_admin_mode): ?><input type="file" class="hidden-file-input"><?php endif; ?>
                </div>
                <div class="absolute inset-x-0 bottom-0 p-2 bg-black/70 rounded-b-lg">
                    <p class="text-white text-xl md:text-2xl font-bold text-center
                        <?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                        data-key="program1_title"
                        <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>>
                        <?php echo get_content('program1_title', 'Reward Pemain Terbaik'); ?>
                    </p>
                </div>
            </div>
            <div class="relative w-full sm:w-1/2 md:w-1/3">
                <div style="position: relative;">
                    <img src="<?php echo get_content('program2_image_path', 'pelajar.png'); ?>" alt="Rent a Field" class="w-full h-auto object-cover rounded-lg
                        <?php echo $is_admin_mode ? 'admin-editable-image' : ''; ?>"
                        data-key="program2_image_path">
                    <?php if ($is_admin_mode): ?><input type="file" class="hidden-file-input"><?php endif; ?>
                </div>
                <div class="absolute inset-x-0 bottom-0 p-2 bg-black/70 rounded-b-lg">
                    <p class="text-white text-xl md:text-2xl font-bold text-center
                        <?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                        data-key="program2_title"
                        <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>>
                        <?php echo get_content('program2_title', 'Diskon Khusus Pelajar'); ?>
                    </p>
                </div>
            </div>
            <div class="relative w-full sm:w-1/2 md:w-1/3">
                <div style="position: relative;">
                    <img src="<?php echo get_content('program3_image_path', 'sewa.png'); ?>" alt="Open Play" class="w-full h-auto object-cover rounded-lg
                        <?php echo $is_admin_mode ? 'admin-editable-image' : ''; ?>"
                        data-key="program3_image_path">
                    <?php if ($is_admin_mode): ?><input type="file" class="hidden-file-input"><?php endif; ?>
                </div>
                <div class="absolute inset-x-0 bottom-0 p-2 bg-black/70 rounded-b-lg">
                    <p class="text-white text-xl md:text-2xl font-bold text-center
                        <?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                        data-key="program3_title"
                        <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>>
                        <?php echo get_content('program3_title', 'Sewa Sepatu Gratis'); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>
            
    <section id="map-section" class="map-section py-5 bg-white text-black">
        <h4 class="text-center section-title mb-4
<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
            data-key="map_title"
            <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>>
            <?php echo get_content('map_title', 'Lokasi Kami'); ?>
        </h4>
        <div class="d-flex justify-content-center mt-4">
            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3955.6662217098583!2d110.2217516748534!3d-7.502051574001483!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e7a8f1c869c6d39%3A0x7ea03e5884a0b39b!2sMGD%20Mini%20Soccer%20Magelang!5e0!3m2!1sid!2sid!4v1754552705608!5m2!1sid!2sid" width="80%" height="360" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
    </section>

    <footer class="relative overflow-hidden mt-16 bg-black">
        <div class="relative max-w-7xl mx-auto px-6 py-16 flex flex-col items-center md:flex-row md:justify-center md:items-start gap-12 md:gap-24">
            <div class="flex flex-col items-center md:items-start max-w-md md:max-w-none text-center md:text-left">
                <div style="position: relative;">
                    <img
                        alt="USF Urban Soccer Field logo"
                        class="h-40 mb-4 <?php echo $is_admin_mode ? 'admin-editable-image' : ''; ?>"
                        data-key="footer_logo_path"
                        src="<?php echo htmlspecialchars(get_content('footer_logo_path', 'logom.png')); ?>">
                    <?php if ($is_admin_mode): ?><input type="file" class="hidden-file-input" data-key="footer_logo_path" accept="image/*" /><?php endif; ?>
                </div>
            </div>


            <div class="flex flex-col md:flex-row md:gap-24 text-lg text-center md:text-left">
                <div>
                    <h3 class="text-[#ffffff] font-extrabold text-xl mb-4"
                        <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                        data-key="footer_title"
                        class="<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>">
                        <?php echo htmlspecialchars(get_content('footer_title', 'Site')); ?>
                    </h3>
                    <ul class="space-y-2 font-normal hover-highlight">
                        <li <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?> data-key="footer_list_1" class="<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"><?php echo htmlspecialchars(get_content('footer_list_1', 'Booking Lapangan')); ?></li>
                        <li <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?> data-key="footer_list_2" class="<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"><?php echo htmlspecialchars(get_content('footer_list_2', 'Galeri MGD Soccer Field')); ?></li>
                        <li <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?> data-key="footer_list_3" class="<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"><?php echo htmlspecialchars(get_content('footer_list_3', 'Program Spesial MGD')); ?></li>
                        <li <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?> data-key="footer_list_4" class="<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"><?php echo htmlspecialchars(get_content('footer_list_4', 'Lokasi MGD Soccer')); ?></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-[#ffffff] font-extrabold text-xl mb-4"
                        <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                        data-key="footer_contact_title"
                        class="<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>">
                        <?php echo htmlspecialchars(get_content('footer_contact_title', 'Contact')); ?>
                    </h3>
                    <address class="not-italic space-y-3 font-normal hover-highlight">
                        <p <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?> data-key="footer_address" class="<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"><?php echo htmlspecialchars(get_content('footer_address', 'Jl. Soekarno Hatta No.5, Magelang')); ?></p>
                        <p <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?> data-key="footer_phone" class="<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"><?php echo htmlspecialchars(get_content('footer_phone', '+62 811 2653 988')); ?></p>
                        <p <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?> data-key="footer_company" class="<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"><?php echo htmlspecialchars(get_content('footer_company', 'MGD Soccer Field Magelang')); ?></p>
                    </address>
                    <ul class="flex space-x-6 mt-4 ms-auto text-white">
                        <li>
                            <a aria-label="YouTube" class="hover:text-[#5fa140ff)] social-icon"
                                href="<?php echo htmlspecialchars(get_content('footer_yt_link', 'https://www.youtube.com/@urbAnsoccerfield')); ?>"
                                <?php echo $is_admin_mode ? 'data-key="footer_yt_link" data-link="true" class="admin-editable-text"' : ''; ?>>
                                <i class="fab fa-youtube"></i>
                            </a>
                        </li>
                        <li>
                            <a aria-label="Instagram" class="hover:text-[#5fa140ff)] social-icon"
                                href="<?php echo htmlspecialchars(get_content('footer_ig_link', 'https://www.instagram.com/mgdsoccerfield/')); ?>"
                                <?php echo $is_admin_mode ? 'data-key="footer_ig_link" data-link="true" class="admin-editable-text"' : ''; ?>>
                                <i class="fab fa-instagram"></i>
                            </a>
                        </li>
                        <li>
                            <a aria-label="WhatsApp"
                                class="hover:text-[#5fa140ff)]"
                                href="<?php echo htmlspecialchars(get_content('footer_wa_link', 'https://wa.me/628112653988?text=Halo%20Urban%20Soccer%20Field%2C%20saya%20mau%20booking%20lapangan.')); ?>"
                                target="_blank" rel="noopener noreferrer"
                                <?php echo $is_admin_mode ? 'data-key="footer_wa_link" data-link="true" class="admin-editable-text"' : ''; ?>>
                                <i class="fab fa-whatsapp social-icon"></i>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="text-center text-gray-300 text-sm py-4 border-t border-gray-800 font-normal">
            <a class="text-[#7dbafbff] hover:underline text-white">&copy;Wabi Teknologi Indonesia</a>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>
<script>
AOS.init();
let selectedDate = null;
let selectedTime = null;
let activeDay = "<?php echo date('Y-m-d'); ?>";

// =========================================================================
// DOM Content Loaded Event Listener
// =========================================================================

document.addEventListener("DOMContentLoaded", function() {
    console.log("DOM content loaded. Initializing scripts...");

    if (typeof AOS !== 'undefined') {
        AOS.init();
    }

    const myGallerySwiper = new Swiper(".myGallerySwiper", {
        loop: true,
        spaceBetween: 30,
        autoplay: {
            delay: 4000,
            disableOnInteraction: false,
        },
        pagination: {
            el: ".swiper-pagination",
            clickable: true,
        },
        navigation: {
            nextEl: ".swiper-button-next",
            prevEl: ".swiper-button-prev",
        },
    });

    const formModal = document.getElementById('formModal');
    const detailBookingModal = document.getElementById('detailBookingModal');
    const finalConfirmationModal = document.getElementById('finalConfirmationModal');
    const videoModal = document.getElementById('videoModal');
    const tutorialBtn = document.getElementById('toggleTutorialBtn');
    const tutorialVideo = document.getElementById('tutorialVideo');
    const weeklyScheduleContainer = document.getElementById('weeklyScheduleContainer');
    const toggleWeeklyBtn = document.getElementById('toggleWeeklyBtn');
    const dailyScheduleContainer = document.getElementById('dailyScheduleContainer'); 
    const dateInput = document.getElementById('date-input');
    const scheduleContainer = document.getElementById('schedule');
    let isWeeklyVisible = false;

    // Admin editable content functionality
    const editableElements = document.querySelectorAll('.admin-editable-text');
    if (editableElements) {
        editableElements.forEach(element => {
            element.addEventListener('blur', function() {
                const key = this.dataset.key;
                const value = this.textContent.trim();
                saveContent(key, value);
            });
        });
    }

    if (tutorialBtn) {
        tutorialBtn.addEventListener('click', () => {
            openModal('videoModal');
        });
    }

    if (toggleWeeklyBtn && weeklyScheduleContainer) {
        toggleWeeklyBtn.addEventListener('click', () => {
            isWeeklyVisible = !isWeeklyVisible;
            if (isWeeklyVisible) {
                // Baris ini dihapus agar jadwal harian tetap terlihat
                // dailyScheduleContainer.style.display = 'none'; 
                weeklyScheduleContainer.style.display = 'block';
                toggleWeeklyBtn.textContent = 'Sembunyikan Jadwal Mingguan';
                fetchWeeklyBookings(activeDay);
            } else {
                weeklyScheduleContainer.style.display = 'none';
                // Baris ini juga tidak diperlukan karena jadwal harian tidak disembunyikan
                // dailyScheduleContainer.style.display = 'block'; 
                toggleWeeklyBtn.textContent = 'Tampilkan Jadwal Mingguan';
                fetchDailyBookings(activeDay);
            }
        });
    }

    if (dateInput) {
        dateInput.addEventListener('change', function() {
            activeDay = this.value;
            fetchDailyBookings(activeDay);
            if (isWeeklyVisible) {
                fetchWeeklyBookings(activeDay);
            }
        });
    }

    if (scheduleContainer) {
        scheduleContainer.addEventListener('click', function(event) {
            const clickedButton = event.target.closest('.booking-button');
            if (clickedButton && !clickedButton.disabled) {
                const slotElement = event.target.closest('.slot');
                if (slotElement) {
                    const date = slotElement.dataset.date;
                    const time = slotElement.dataset.time;

                    document.querySelectorAll('.slot').forEach(s => s.classList.remove('selected'));
                    slotElement.classList.add('selected');

                    showBookingForm(time, date);
                }
            }
        });
    }
    
    const plusMinusButtons = document.querySelectorAll('.btn-plus, .btn-minus');
    if (plusMinusButtons) {
        plusMinusButtons.forEach(button => {
            button.addEventListener('click', function() {
                const item = this.dataset.item;
                const input = document.getElementById(item);
                let value = parseInt(input.value);
                
                if (this.classList.contains('btn-plus')) {
                    value++;
                } else if (this.classList.contains('btn-minus') && value > 0) {
                    value--;
                }
                input.value = value;
                updateTotalBooking();
            });
        });
    }
    
    const priceElements = document.querySelectorAll('[data-key="field_rent_price"], [data-key="booking_shoes_price"], [data-key="booking_vests_price"]');
    if (priceElements) {
        priceElements.forEach(element => {
            element.addEventListener('blur', function() {
                updateTotalBooking();
            });
        });
    }

    fetchDailyBookings(activeDay);

    // =========================================================================
    // Polling Functionality
    // =========================================================================
    setInterval(() => {
        if (isWeeklyVisible) {
            fetchWeeklyBookings(activeDay);
        } else {
            fetchDailyBookings(activeDay);
        }
    }, 15000); // Polling setiap 15 detik

    window.openModal = openModal;
    window.closeModal = closeModal;
    window.showBookingForm = showBookingForm;
    window.showFinalConfirmation = showFinalConfirmation;
    window.submitBooking = submitBooking;
    window.showDetailBooking = showDetailBooking;
    window.fetchDailyBookings = fetchDailyBookings;
    window.fetchWeeklyBookings = fetchWeeklyBookings;
});

// =========================================================================
// Utility & Modal Functions
// =========================================================================

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        const tutorialVideo = document.getElementById('tutorialVideo');
        if (modalId === 'videoModal' && tutorialVideo) {
            tutorialVideo.play();
        }
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        const tutorialVideo = document.getElementById('tutorialVideo');
        if (modalId === 'videoModal' && tutorialVideo) {
            tutorialVideo.pause();
            tutorialVideo.currentTime = 0;
        }
    }
}

function getDayName(dateString) {
    const date = new Date(dateString);
    const dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    return dayNames[date.getDay()];
}

function showBookingForm(time, date) {
    Swal.fire({
        title: 'Yakin mau pesan?',
        html: `Anda akan memesan lapangan pada tanggal <strong>${date}</strong> pukul <strong>${time}</strong>.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, pesan sekarang!',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            selectedDate = date;
            selectedTime = time;

            document.getElementById('fullName').value = '';
            document.getElementById('phone').value = '';
            
            document.querySelectorAll('.sewa-item').forEach(input => input.value = 0);
            
            updateTotalBooking();
            document.getElementById('bookingSummary').style.display = 'block';

            openModal('formModal');
        }
    });
}

function updateTotalBooking() {
    const fieldPriceElement = document.querySelector('[data-key="field_rent_price"]');
    let hargaSewaLapangan = 0;
    if (fieldPriceElement) {
        const fieldPriceText = fieldPriceElement.textContent.replace(/[^\d]/g, '');
        hargaSewaLapangan = parseFloat(fieldPriceText) || 0;
    }

    let total = hargaSewaLapangan;
    const sewaTambahanSummary = document.getElementById('sewaTambahanSummary');
    if (sewaTambahanSummary) {
        sewaTambahanSummary.innerHTML = '';
    }
    
    document.querySelectorAll('.sewa-item').forEach(input => {
        let itemPrice = 0;
        let itemName;

        if (input.id === 'sewaSepatu') {
            const sepatuPriceElement = document.querySelector('[data-key="booking_shoes_price"]');
            if (sepatuPriceElement) {
                const sepatuPriceText = sepatuPriceElement.textContent.replace(/[^\d]/g, '');
                itemPrice = parseFloat(sepatuPriceText) || 0;
            }
            const sepatuLabelElement = document.querySelector('[data-key="booking_shoes_label_text"]');
            if (sepatuLabelElement) {
                itemName = sepatuLabelElement.textContent;
            }
        } else if (input.id === 'sewaRompi') {
            const rompiPriceElement = document.querySelector('[data-key="booking_vests_price"]');
            if (rompiPriceElement) {
                const rompiPriceText = rompiPriceElement.textContent.replace(/[^\d]/g, '');
                itemPrice = parseFloat(rompiPriceText) || 0;
            }
            const rompiLabelElement = document.querySelector('[data-key="booking_vests_label_text"]');
            if (rompiLabelElement) {
                itemName = rompiLabelElement.textContent;
            }
        }
        
        const itemCount = parseInt(input.value);
        const itemTotal = itemPrice * itemCount;
        
        if (itemCount > 0) {
            if (itemName) {
                sewaTambahanSummary.innerHTML += `
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-[#a7b0bf]">${itemName} (${itemCount}x)</span>
                        <span class="text-white font-bold">Rp${formatRupiah(itemTotal)}</span>
                    </div>
                `;
                total += itemTotal;
            }
        }
    });

    const biayaSewaElement = document.getElementById('biayaSewaLapangan');
    if (biayaSewaElement) {
        biayaSewaElement.textContent = 'Rp' + formatRupiah(hargaSewaLapangan);
    }
    const totalBayarElement = document.getElementById('totalBayar');
    if (totalBayarElement) {
        totalBayarElement.textContent = 'Rp' + formatRupiah(total);
    }
}

function showFinalConfirmation() {
    const fullName = document.getElementById('fullName').value;
    const phone = document.getElementById('phone').value;

    if (!fullName || !phone) {
        Swal.fire({
            icon: 'error',
            title: 'Opps...',
            text: 'Nama dan Nomor Telepon harus diisi!'
        });
        return;
    }
    
    const fieldPriceElement = document.querySelector('[data-key="field_rent_price"]');
    let hargaSewaLapangan = 0;
    if (fieldPriceElement) {
        const fieldPriceText = fieldPriceElement.textContent.replace(/[^\d]/g, '');
        hargaSewaLapangan = parseFloat(fieldPriceText) || 0;
    }

    document.getElementById('confirmNama').textContent = fullName;
    document.getElementById('confirmPhone').textContent = phone;
    document.getElementById('confirmDate').textContent = selectedDate;
    document.getElementById('confirmTime').textContent = selectedTime;
    
    let total = hargaSewaLapangan;
    document.getElementById('confirmBiayaSewaLapangan').textContent = 'Rp' + formatRupiah(hargaSewaLapangan);
    const confirmSewaTambahan = document.getElementById('confirmSewaTambahan');
    if (confirmSewaTambahan) {
        confirmSewaTambahan.innerHTML = '';
    }

    document.querySelectorAll('.sewa-item').forEach(input => {
        let itemPrice = 0;
        let itemName;

        if (input.id === 'sewaSepatu') {
            const sepatuPriceElement = document.querySelector('[data-key="booking_shoes_price"]');
            if (sepatuPriceElement) {
                const sepatuPriceText = sepatuPriceElement.textContent.replace(/[^\d]/g, '');
                itemPrice = parseFloat(sepatuPriceText) || 0;
            }
            const sepatuLabelElement = document.querySelector('[data-key="booking_shoes_label_text"]');
            if (sepatuLabelElement) {
                itemName = sepatuLabelElement.textContent;
            }
        } else if (input.id === 'sewaRompi') {
            const rompiPriceElement = document.querySelector('[data-key="booking_vests_price"]');
            if (rompiPriceElement) {
                const rompiPriceText = rompiPriceElement.textContent.replace(/[^\d]/g, '');
                itemPrice = parseFloat(rompiPriceText) || 0;
            }
            const rompiLabelElement = document.querySelector('[data-key="booking_vests_label_text"]');
            if (rompiLabelElement) {
                itemName = rompiLabelElement.textContent;
            }
        }
        
        const itemCount = parseInt(input.value);
        const itemTotal = itemPrice * itemCount;
        
        if (itemCount > 0) {
            if (itemName) {
                confirmSewaTambahan.innerHTML += `
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-gray-400">${itemName} (${itemCount}x):</span>
                        <span class="text-white font-bold">Rp${formatRupiah(itemTotal)}</span>
                    </div>
                `;
                total += itemTotal;
            }
        }
    });
    
    document.getElementById('confirmTotalBayar').textContent = 'Rp' + formatRupiah(total);
    
    closeModal('formModal');
    openModal('finalConfirmationModal');
}

function submitBooking() {
    const nama = document.getElementById('fullName').value;
    const no_hp = document.getElementById('phone').value;
    
    if (!nama || !no_hp || !selectedDate || !selectedTime) {
        Swal.fire({
            icon: 'error',
            title: 'Gagal',
            text: 'Silakan lengkapi semua data yang diperlukan.'
        });
        return;
    }

    const sewaItems = {};
    document.querySelectorAll('.sewa-item').forEach(input => {
        sewaItems[input.id] = parseInt(input.value);
    });

    fetch('index.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=submit_booking&nama=${encodeURIComponent(nama)}&no_hp=${encodeURIComponent(no_hp)}&tanggal=${encodeURIComponent(selectedDate)}&jam=${encodeURIComponent(selectedTime)}&sewa_items=${JSON.stringify(sewaItems)}`
    })
    .then(response => response.text())
    .then(data => {
        if (data.includes("Berhasil")) {
            Swal.fire({
                title: 'Booking Berhasil!',
                html: 'Permintaan booking Anda telah berhasil dikirim. Silakan selesaikan pembayaran untuk mengkonfirmasi pesanan.',
                icon: 'success',
                showConfirmButton: true,
                confirmButtonText: 'Lanjutkan ke Pembayaran',
                allowOutsideClick: false,
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'pembayaran.php';
                }
            });

            closeModal('finalConfirmationModal');
            fetchDailyBookings(activeDay);
            const weeklyScheduleContainer = document.getElementById('weeklyScheduleContainer');
            if (weeklyScheduleContainer && weeklyScheduleContainer.style.display !== 'none') {
                fetchWeeklyBookings(activeDay);
            }
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Booking Gagal',
                text: data
            });
        }
    })
    .catch(error => {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Terjadi kesalahan saat menghubungi server.'
        });
    });
}

// =========================================================================
// Booking Fetching Functions
// =========================================================================

function fetchDailyBookings(date) {
    const scheduleContainer = document.getElementById('schedule');
    const dateInfo = document.getElementById('currentDateInfo');
    if (!scheduleContainer || !dateInfo) return;

    dateInfo.textContent = `Tanggal: ${getDayName(date)}, ${new Date(date).toLocaleDateString('id-ID', {day: '2-digit', month: 'long', year: 'numeric'})}`;

    scheduleContainer.innerHTML = `<div class="text-white text-center col-span-full">Sedang memuat...</div>`;

    fetch(`index.php?get_daily_bookings=1&tanggal=${date}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Jaringan tidak responsif atau terjadi kesalahan server');
            }
            return response.json();
        })
        .then(data => {
            scheduleContainer.innerHTML = '';
            const now = new Date();

            const timeSlots = ['06:00', '07:30', '09:00', '10:30', '12:00', '13:30', '15:00', '16:30', '18:00', '19:30', '21:00', '22:30'];
            
            if (Object.keys(data).length === 0) {
                 scheduleContainer.innerHTML = `<div class="text-white text-center col-span-full">Tidak ada jadwal yang tersedia.</div>`;
            } else {
                timeSlots.forEach(time => {
                    const bookingData = data[time];
                    const status = bookingData ? bookingData.status : 'available';
                    const slot = document.createElement('div');
                    slot.dataset.time = time;
                    slot.dataset.date = date;

                    const slotDate = new Date(`${date}T${time}:00`);
                    const isPast = slotDate < now;

                    slot.classList.add('slot', 'p-4', 'bg-[#1e1e1e]', 'rounded-lg', 'flex', 'flex-col', 'justify-center', 'items-center', 'cursor-pointer', 'transition-all', 'duration-300', 'hover:bg-[#2a2a2a]');

                    let slotStatusText = '';
                    let buttonText = '';
                    let isClickable = true;
                    let buttonClass = 'bg-green-500';

                    if (status && status.toLowerCase() === 'booked') {
                        slot.classList.add('booked'); 
                        slotStatusText = 'Sudah Dibooking';
                        buttonText = 'Sudah Dibooking';
                        isClickable = false;
                        buttonClass = 'bg-red-500';
                    } else if (status && status.toLowerCase() === 'menunggu') {
                        slot.classList.add('pending'); 
                        slotStatusText = 'Menunggu Pembayaran';
                        buttonText = 'Menunggu Pembayaran';
                        isClickable = false;
                        buttonClass = 'bg-yellow-500';
                    } else {
                        slot.classList.add('available');
                        slotStatusText = 'Tersedia';
                        buttonText = 'Pesan Sekarang';
                    }

                    if (isPast && status.toLowerCase() === 'available') {
                        slot.classList.add('past-time'); 
                        slotStatusText = 'Waktu sudah lewat';
                        buttonText = 'Waktu sudah lewat';
                        isClickable = false;
                        buttonClass = 'bg-gray-500';
                    }
                    
                    const slotContent = `
                        <div class="text-white text-lg font-bold mb-2">${time.substring(0, 5)} - ${new Date(slotDate.getTime() + 90 * 60000).toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit'})}</div>
                        <div class="text-gray-400 text-sm mb-4">${slotStatusText}</div>
                        <button class="booking-button text-white font-bold py-2 px-4 rounded-lg hover:brightness-90 transition-colors duration-300 ${buttonClass} ${isClickable ? '' : 'opacity-70 cursor-not-allowed'}" ${isClickable ? '' : 'disabled'}>
                            ${buttonText}
                        </button>
                    `;

                    slot.innerHTML = slotContent;
                    scheduleContainer.appendChild(slot);
                });
            }
        })
        .catch(error => {
            console.error('Error fetching daily bookings:', error);
            scheduleContainer.innerHTML = `<div class="text-red-500 text-center col-span-full">Gagal mengambil data jadwal. Silakan coba lagi.</div>`;
        });
}

function fetchWeeklyBookings(date) {
    const weeklyBody = document.getElementById('weekly-schedule-body');
    if (!weeklyBody) return;
    weeklyBody.innerHTML = ''; 

    const startDate = new Date(date);
    const day = startDate.getDay();
    const diff = startDate.getDate() - day + (day === 0 ? -6 : 1);
    startDate.setDate(diff);

    const timeSlots = ['06:00', '07:30', '09:00', '10:30', '12:00', '13:30', '15:00', '16:30', '18:00', '19:30', '21:00', '22:30'];
    const weeklyDates = Array.from({length: 7}, (_, i) => {
        const d = new Date(startDate);
        d.setDate(d.getDate() + i);
        return d.toISOString().split('T')[0];
    });

    fetch(`index.php?get_weekly_bookings=1&start_date=${weeklyDates[0]}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Jaringan tidak responsif atau terjadi kesalahan server');
            }
            return response.json();
        })
        .then(data => {
            const bookings = data || {};

            const tableHeaders = document.querySelectorAll('.weekly-schedule-header .weekly-header-item:not(:first-child)');
            weeklyDates.forEach((d, i) => {
                const dateObj = new Date(d);
                const dayName = getDayName(d);
                const dayNumber = dateObj.getDate();
                if (tableHeaders[i]) {
                    tableHeaders[i].textContent = `${dayName.substring(0, 3)} (${dayNumber})`;
                }
            });

            for (const time of timeSlots) {
                const row = document.createElement('div');
                row.classList.add('weekly-schedule-row');
                
                const timeCell = document.createElement('div');
                timeCell.classList.add('weekly-schedule-cell', 'time-label');
                
                const slotDate = new Date(`1970-01-01T${time}:00`);
                const displayTime = time.substring(0, 5) + ' - ' + new Date(slotDate.getTime() + 90 * 60000).toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit'});
                timeCell.textContent = displayTime;
                row.appendChild(timeCell);
                
                for (let i = 0; i < 7; i++) {
                    const cell = document.createElement('div');
                    cell.classList.add('weekly-schedule-cell');
                    
                    const dayData = bookings[weeklyDates[i]];
                    let bookingStatus = 'available';

                    if (dayData && dayData[time] && dayData[time].status) {
                        bookingStatus = dayData[time].status;
                    }

                    const bookingDate = weeklyDates[i];
                    const now = new Date();
                    const isPast = new Date(`${bookingDate}T${time}:00`) < now;
                    
                    let cellContent = ''; 
                    
                    if (bookingStatus && bookingStatus.toLowerCase() === 'booked') {
                        cellContent = `Booked`;
                        cell.classList.add('booked');
                    } else if (bookingStatus && bookingStatus.toLowerCase() === 'menunggu') {
                        cellContent = `Menunggu`;
                        cell.classList.add('pending');
                    }
                    
                    if (isPast && bookingStatus && bookingStatus.toLowerCase() === 'available') {
                        cellContent = 'Waktu sudah lewat';
                        cell.classList.add('past');
                    }
                    
                    cell.textContent = cellContent;
                    row.appendChild(cell);
                }
                weeklyBody.appendChild(row);
            }
        })
        .catch(error => {
            console.error('Error fetching weekly bookings:', error);
            weeklyBody.innerHTML = `<div class="text-red-500 text-center col-span-full">Gagal mengambil data jadwal mingguan. Silakan coba lagi.</div>`;
        });
}

function showDetailBooking(tanggal, jam, nama) {
    document.getElementById('detailNama').textContent = nama;
    document.getElementById('detailWaktu').textContent = jam;
    document.getElementById('detailTanggal').textContent = tanggal;
    openModal('detailBookingModal');
}

function formatRupiah(number) {
    return new Intl.NumberFormat('id-ID').format(number);
}

function saveContent(key, value) {
    const formData = new FormData();
    formData.append('action', 'save_content');
    formData.append('key', key);
    formData.append('value', value);

    fetch('index.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        if (data.includes("success")) {
            console.log("Konten berhasil disimpan.");
        } else {
            console.error("Gagal menyimpan konten:", data);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}
    </script>
</body>

</html>