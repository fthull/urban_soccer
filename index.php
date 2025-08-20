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
function get_content($key, $default = '')
{
    global $site_content;
    // htmlspecialchars() digunakan untuk mencegah XSS
    return isset($site_content[$key]) ? htmlspecialchars($site_content[$key]) : htmlspecialchars($default);
}

// Logika untuk menambahkan booking baru
date_default_timezone_set('Asia/Jakarta');
$is_manage_content_page = strpos($_SERVER['REQUEST_URI'], 'manage_content') !== false;

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
        '06:00',
        '07:30',
        '09:00',
        '10:30',
        '12:00',
        '13:30',
        '15:00',
        '16:30',
        '18:00',
        '19:30',
        '21:00',
        '22:30'
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

    <input type="file" id="heroImageInput" class="hidden-file-input">
    <section id="booking-section" class="booking-section py-5" style="background-color: #ffffffff;">
        <div class="container-fluid">
            <div class="row justify-content-center" data-aos="fade-up">
                <div class="col-lg-12 col-md-12">

                    <h2 class="text-5xl font-bold text-center text-gray-800
<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                        data-aos="fade-down"
                        style="font-family: 'Montserrat', sans-serif;"
                        <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                        data-key="book_heading">
                        <?php echo get_content('book_heading', 'Booking'); ?>
                    </h2><br><br>

                    <div id="dailyScheduleContainer" style="max-width: 100%; flex-grow: 1;">

                        <p class="date-info text-dark fw-bold" id="currentDateInfo">Tanggal: 08 August 2025</p>
                        <div class="text-center d-flex flex-column items-center gap-2 mb-4">
                            <div class="date-picker-container fw-bold">
                                <input type="date" id="date-input" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="schedule-grid" id="schedule"></div>
                        </div>


                        <div class="d-flex justify-content-center my-4 gap-2">
                            <button id="toggleWeeklyBtn" class="btn btn-primary-custom">Tampilkan Jadwal Mingguan</button>
                            <button id="toggleTutorialBtn" class="btn btn-primary-custom">Tutorial Pemesanan</button>
                        </div>
                        <div id="weeklyScheduleContainer" style="display: none;">
                            <h2 class="booking-title fw-bold text-dark">Jadwal Mingguan</h2>
                            <div class="weekly-schedule-container">
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
                    <button class="close-btn" onclick="closeModal('detailBookingModal')">×</button>
                    <div id="bookingDetails">
                        <div class="modal-icon">
                            <i class="fas fa-info-circle text-[60px] text-[#4a90e2] mb-4"></i>
                        </div>
                        <h3 class="text-3xl font-bold mb-4 <?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                            <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                            data-key="booking_detail_title">
                            <?php echo get_content('booking_detail_title', 'Detail Booking'); ?>
                        </h3>
                        <div class="detail-item">
                            <strong class="<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                                <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                                data-key="booking_name_label_modal">
                                <?php echo get_content('booking_name_label_modal', 'Nama:'); ?>
                            </strong>
                            <span id="detailNama" class="detail-value"></span>
                        </div>
                        <div class="detail-item">
                            <strong class="<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                                <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                                data-key="booking_time_label_modal">
                                <?php echo get_content('booking_time_label_modal', 'Waktu:'); ?>
                            </strong>
                            <span id="detailWaktu" class="detail-value"></span>
                        </div>
                        <div class="detail-item">
                            <strong class="<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                                <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                                data-key="booking_date_label_modal">
                                <?php echo get_content('booking_date_label_modal', 'Tanggal:'); ?>
                            </strong>
                            <span id="detailTanggal" class="detail-value"></span>
                        </div>
                    </div>
                    <button class="confirm-btn <?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                        <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                        data-key="booking_ok_button">
                        <?php echo get_content('booking_ok_button', 'OK'); ?>
                    </button>
                </div>
            </div>

            <div class="modal-booking" id="formModal">
                <div class="modal-content-booking">
                    <button class="close-btn" onclick="closeModal('formModal')">×</button>
                    <h3 class="<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                        <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                        data-key="booking_form_title">
                        <?php echo get_content('booking_form_title', 'Detail Booking'); ?>
                    </h3>
                    <p class="<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                        <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                        data-key="booking_form_subtitle">
                        <?php echo get_content('booking_form_subtitle', 'Isi data untuk konfirmasi pemesanan.'); ?>
                    </p>
                    <div id="bookingSummary" class="text-left mb-4 p-4 rounded-lg" style="background-color: #1c2531; display: none;">
                        <h4 class="text-xl font-bold mb-2 text-[#ffd600] <?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                            <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                            data-key="booking_summary_title">
                            <?php echo get_content('booking_summary_title', 'Rincian Biaya'); ?>
                        </h4>
                        <hr class="border-[#5a6473] mb-2" />
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-[#a7b0bf] <?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                                <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                                data-key="booking_field_rent">
                                <?php echo get_content('booking_field_rent', 'Biaya Sewa Lapangan'); ?>
                            </span>
                            <span id="biayaSewaLapangan"
                                class="text-white font-bold <?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                                <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                                data-key="field_rent_price">
                                <?php echo get_content('field_rent_price', 'Rp700.000'); ?>
                            </span>
                        </div>
                        <div id="sewaTambahanSummary"></div>
                        <hr class="border-[#5a6473] my-2" />
                        <div class="flex justify-between items-center">
                            <span class="text-xl font-bold <?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                                <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                                data-key="booking_total_title">
                                <?php echo get_content('booking_total_title', 'Total Bayar'); ?>
                            </span>
                            <span id="totalBayar" class="text-xl font-bold text-[#ffd600]">Rp0</span>
                        </div>
                    </div>
                    <div class="form-container">
                        <div class="form-group">
                            <div>
                                <label for="fullName" class="<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                                    <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                                    data-key="booking_name_label">
                                    <?php echo get_content('booking_name_label', 'Nama :'); ?>
                                </label>
                                <input type="text" id="fullName" placeholder="Nama Lengkap" required />
                            </div>
                            <div>
                                <label for="phone" class="<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                                    <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                                    data-key="booking_phone_label">
                                    <?php echo get_content('booking_phone_label', 'Nomor :'); ?>
                                </label>
                                <input type="tel" id="phone" placeholder="Nomor Telepon" required />
                            </div>
                        </div>
                        <div class="form-group">
                            <div>
                                <label for="sewaSepatu">
                                    <span class="<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                                        <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                                        data-key="booking_shoes_label_text">
                                        <?php echo get_content('booking_shoes_label_text', 'Sewa Sepatu'); ?>
                                    </span>
                                    <span class="<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                                        <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                                        data-key="booking_shoes_price">
                                        <?php echo get_content('booking_shoes_price', 'Rp30.000'); ?>
                                    </span>/pasang:
                                </label>
                                <div class="input-plus-minus">
                                    <button type="button" class="btn-minus" data-item="sewaSepatu">-</button>
                                    <input type="number" id="sewaSepatu" class="sewa-item" data-price="30000" value="0" min="0" readonly />
                                    <button type="button" class="btn-plus" data-item="sewaSepatu">+</button>
                                </div>
                            </div>
                            <div>
                                <label for="sewaRompi">
                                    <span class="<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                                        <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                                        data-key="booking_vests_label_text">
                                        <?php echo get_content('booking_vests_label_text', 'Sewa Rompi'); ?>
                                    </span>
                                    <span class="<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                                        <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                                        data-key="booking_vests_price">
                                        <?php echo get_content('booking_vests_price', 'Rp20.000'); ?>
                                    </span>/biji:
                                </label>
                                <div class="input-plus-minus">
                                    <button type="button" class="btn-minus" data-item="sewaRompi">-</button>
                                    <input type="number" id="sewaRompi" class="sewa-item" data-price="20000" value="0" min="0" readonly />
                                    <button type="button" class="btn-plus" data-item="sewaRompi">+</button>
                                </div>
                            </div>
                        </div>
                        <div class="button-group">
                            <button class="cancel-btn <?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                                <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                                data-key="booking_cancel_button" onclick="closeModal('formModal')">
                                <?php echo get_content('booking_cancel_button', 'Batal'); ?>
                            </button>
                            <button class="confirm-btn <?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                                <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                                data-key="booking_confirm_button" onclick="showFinalConfirmation()">
                                <?php echo get_content('booking_confirm_button', 'Booking Sekarang'); ?>
                            </button>
                        </div>
                    </div>
                </div>
    </section>
    <section class="about-section py-5">
  <div class="min-h-screen flex items-center px-6 py-12 relative">
    <div class="max-w-7xl w-full flex flex-col md:flex-row items-center md:items-start gap-8 md:gap-16">
      
      <!-- Text -->
      <div class="text-white max-w-xl px-4 md:px-12">
        <h1 class="font-bold mb-6 text-shadow
          <?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
          data-aos="fade-up" data-aos-delay="100" data-aos-duration="1000"
          style="font-family: 'Poppins', sans-serif;"
          <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
          data-key="abou_head">

          <?php echo get_content('abou_head', 'MGD Soccer Field'); ?>
        </h1>

        <p class="mb-6 text-shadow <?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
          data-aos="fade-up" data-aos-delay="300" data-aos-duration="1000"
          style="font-family: 'Poppins', sans-serif;"
          <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
          data-key="about_text1">

          <?php echo get_content('about_text1', 'MGD Soccer Field Magelang hadir sebagai wadah bagi setiap komunitas pecinta sepak bola yang ingin merasakan sensasi bermain dengan kualitas terbaik dan suasana menyenangkan.'); ?>
        </p>

        <p class="text-shadow <?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
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

     <section class="about-section py-5">
                <div class="flex justify-start px-8 my-6 justify-content-center">
                <h2 class="text-white text-5xl font-bold text-center
<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                    data-aos="fade-down"
                    style="font-family: 'Montserrat', sans-serif;"
                    <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                    data-key="program_heading">
                    <?php echo get_content('program_heading', 'Program Spesial MGD'); ?>
                </h2>
            </div>
    </section>

    <section class="bg-gradient-to-r from-[#121212] px-6 py-10">
        <div class="max-w-7xl mx-auto text-white">
            <div class="flex flex-col md:flex-row justify-between text-center md:text-left items-center md:items-start gap-10">
                <div class="flex-1 border-r border-gray-600 px-12">
                    <ul class="list-disc list-inside space-y-3 text-left">
                        <li class="text-lg font-semibold
<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                            data-key="feature_list1_text"
                            <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>>
                            <?php echo get_content('feature_list1_text', 'Lapangan ukuran 55 x 22 m'); ?>
                        </li>
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
                <div class="flex-1 border-r border-gray-600 px-12">
                    <ul class="list-disc list-inside space-y-3 text-left">
                        <li class="text-lg font-semibold
<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                            data-key="feature_list4_text"
                            <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>>
                            <?php echo get_content('feature_list4_text', 'Kamar Mandi'); ?>
                        </li>
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
                <div class="flex-1 flex flex-col items-center justify-center text-center px-12">
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
        </div>
        </div>
        <div class="modal-booking" id="finalConfirmationModal">
            <div class="modal-content-booking max-w-lg">
                <button class="close-btn" onclick="closeModal('finalConfirmationModal')">×</button>
                <div class="modal-icon">
                    <i class="fas fa-check-circle text-6xl text-green-500 mb-4"></i>
                </div>
                <h3 class="text-3xl font-bold mb-2">Pesanan Anda sudah benar?</h3>
                <p class="text-lg text-gray-300 mb-4">Pastikan data yang Anda isi sudah benar sebelum melanjutkan.</p>
                <div id="finalBookingDetails" class="text-left bg-[#1c2531] p-4 rounded-lg mb-4">
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-gray-400">Nama:</span>
                        <span id="confirmNama" class="text-white font-bold"></span>
                    </div>
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-gray-400">No. Telepon:</span>
                        <span id="confirmPhone" class="text-white font-bold"></span>
                    </div>
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-gray-400">Tanggal:</span>
                        <span id="confirmDate" class="text-white font-bold"></span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-gray-400">Waktu:</span>
                        <span id="confirmTime" class="text-white font-bold"></span>
                    </div>
                    <hr class="border-[#5a6473] my-2" />
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-gray-400">Biaya Sewa Lapangan:</span>
                        <span id="confirmBiayaSewaLapangan" class="text-white font-bold"></span>
                    </div>
                    <div id="confirmSewaTambahan" class="text-white"></div>
                    <hr class="border-[#5a6473] my-2" />
                    <div class="flex justify-between items-center">
                        <span class="text-xl font-bold">Total Bayar:</span>
                        <span id="confirmTotalBayar" class="text-xl font-bold text-[#ffd600]"></span>
                    </div>
                </div>
                <div class="flex justify-center gap-4 mt-4">
                    <button class="confirmation-cancel-btn" onclick="closeModal('finalConfirmationModal')">Batal</button>
                    <button class="confirmation-confirm-btn" onclick="submitBooking()">Ya, Pesanan sudah benar</button>
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
        document.addEventListener('DOMContentLoaded', function() {
            const hamburger = document.querySelector('.hamburger');
            const navLinks = document.querySelector('.nav-links');
            
            hamburger.addEventListener('click', function() {
                hamburger.classList.toggle('active');
                navLinks.classList.toggle('active');
            });
            
            // Menutup menu ketika link diklik (opsional)
            const navItems = document.querySelectorAll('.nav-link');
            navItems.forEach(item => {
                item.addEventListener('click', function() {
                    hamburger.classList.remove('active');
                    navLinks.classList.remove('active');
                });
            });
        });
    </script>
    <script>
        // Initialize AOS animation library
        AOS.init();

        let selectedDate = null;
        let selectedTime = null;
        let activeDay = "<?php echo date('Y-m-d'); ?>";

        document.addEventListener("DOMContentLoaded", function() {
            // Initialize Swiper for gallery
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

            // Date input change handler
            const dateInput = document.getElementById('date-input');
            if (dateInput) {
                dateInput.addEventListener('change', function() {
                    activeDay = this.value;
                    fetchDailyBookings(activeDay);
                    if (document.getElementById('weeklyScheduleContainer').style.display !== 'none') {
                        fetchWeeklyBookings(activeDay);
                    }
                });
            }

            // Toggle weekly schedule
            const toggleWeeklyBtn = document.getElementById('toggleWeeklyBtn');
            if (toggleWeeklyBtn) {
                toggleWeeklyBtn.addEventListener('click', function() {
                    const weeklyContainer = document.getElementById('weeklyScheduleContainer');
                    const isVisible = weeklyContainer.style.display !== 'none';

                    if (isVisible) {
                        weeklyContainer.style.display = 'none';
                        this.textContent = 'Tampilkan Jadwal Mingguan';
                    } else {
                        weeklyContainer.style.display = 'block';
                        this.textContent = 'Sembunyikan Jadwal Mingguan';
                        fetchWeeklyBookings(activeDay);
                    }
                });
            }

            // Initial load
            fetchDailyBookings(activeDay);
        });

        function fetchDailyBookings(date) {
            const scheduleContainer = document.getElementById('schedule');
            const dateInfo = document.getElementById('currentDateInfo');

            if (!scheduleContainer || !dateInfo) return;

            dateInfo.textContent = `Tanggal: ${formatDate(date)}`;
            scheduleContainer.innerHTML = '<div class="text-white text-center col-span-full">Sedang memuat...</div>';

            fetch(`index.php?get_daily_bookings=1&tanggal=${date}`)
                .then(response => response.json())
                .then(data => {
                    scheduleContainer.innerHTML = '';
                    const now = new Date();
                    const timeSlots = ['06:00', '07:30', '09:00', '10:30', '12:00', '13:30', '15:00', '16:30', '18:00', '19:30', '21:00', '22:30'];

                    timeSlots.forEach(time => {
                        const bookingData = data[time] || {};
                        const status = bookingData.status || 'available';
                        const slot = document.createElement('div');
                        slot.dataset.time = time;
                        slot.dataset.date = date;

                        const slotDate = new Date(`${date}T${time}:00`);
                        const isPast = slotDate < now;

                        let statusText, buttonText, buttonClass, isClickable;

                        switch (status.toLowerCase()) {
                            case 'booked':
                                statusText = 'Sudah Dibooking';
                                buttonText = 'Sudah Dibooking';
                                buttonClass = 'bg-red-500';
                                isClickable = false;
                                break;
                            case 'menunggu':
                                statusText = 'Menunggu Pembayaran';
                                buttonText = 'Menunggu Pembayaran';
                                buttonClass = 'bg-yellow-500';
                                isClickable = false;
                                break;
                            default:
                                statusText = isPast ? 'Waktu sudah lewat' : 'Tersedia';
                                buttonText = isPast ? '-' : 'Pesan Sekarang';
                                buttonClass = isPast ? 'bg-gray-500' : 'bg-green-500';
                                isClickable = !isPast;
                        }

                        const endTime = new Date(slotDate.getTime() + 90 * 60000).toLocaleTimeString('id-ID', {
                            hour: '2-digit',
                            minute: '2-digit'
                        });

                        slot.innerHTML = `
                            <div class="text-white text-lg font-bold mb-2">${time.substring(0,5)} - ${endTime}</div>
                            <div class="text-gray-400 text-sm mb-4">${statusText}</div>
                            <button class="booking-button text-white font-bold py-2 px-4 rounded-lg hover:brightness-90 transition-colors duration-300 ${buttonClass} ${isClickable ? '' : 'opacity-70 cursor-not-allowed'}" ${isClickable ? '' : 'disabled'}>
                                ${buttonText}
                            </button>
                        `;

                        slot.classList.add('slot', 'p-4', 'rounded-lg', 'flex', 'flex-col', 'justify-center', 'items-center');

                        if (isClickable) {
                            slot.addEventListener('click', () => showBookingForm(time, date));
                        }

                        scheduleContainer.appendChild(slot);
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                    scheduleContainer.innerHTML = '<div class="text-red-500 text-center col-span-full">Gagal memuat jadwal</div>';
                });
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            const options = {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            };
            return date.toLocaleDateString('id-ID', options);
        }

        function showBookingForm(time, date) {
            selectedTime = time;
            selectedDate = date;

            Swal.fire({
                title: 'Konfirmasi Booking',
                html: `Anda akan memesan lapangan pada:<br><strong>${formatDate(date)}</strong><br>Pukul <strong>${time}</strong>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Lanjutkan',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Open booking form modal
                    openModal('formModal');
                }
            });
        }

        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }

        // Make functions available globally
        window.openModal = openModal;
        window.closeModal = closeModal;
        window.showBookingForm = showBookingForm;
    </script>
</body>

</html>