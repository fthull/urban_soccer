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
{    global $site_content;
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

    <style>
        :root {
            --usf-green: #1c2531;
            --primary-color: var(--usf-green);
            --light-color: #fff;
        }

        /* Gaya yang sudah ada dari file Anda */
        .carousel {
            scroll-snap-type: x mandatory;
            overflow-x: auto;
            display: flex;
            scroll-behavior: smooth;
        }

        .carousel-item {
            scroll-snap-align: start;
            flex: none;
            width: 100%;
        }

        .carousel::-webkit-scrollbar {
            display: none;
        }

        /* Animasi muncul */
        @keyframes fadeInSlide {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .fade-in-left {
            animation: fadeInSlide 1s ease-out forwards;
        }

        .header-booking {
            background-color: #ffffffff;
            color: #000000ff;}
.fade-in-left {
animation: fadeInSlide 1s ease-out forwards;
}
.header-booking {
            background-color: #1c2531;
            color: #fff;
            text-align: center;
            padding: 3rem 1rem 2rem;
            margin-bottom: 2rem;
        }

        .header-booking h1 {
            font-size: 1.5rem;
            /* Ukuran font diubah menjadi lebih kecil */
            margin: 0;
            font-weight: 700;
            color: #dbe0e9;
        }

        .header-booking h1 span {
            color: #ffd600;
            font-weight: 800;
        }

        .header-booking .price {
            color: #ffd600;
            font-weight: bold;
            margin-top: 1.2rem;
            font-size: 1.3rem;
        }

        .header-booking .note {
            color: #ccd2dc;
            margin-top: 0.5rem;
            font-size: 1rem;
        }

        /* === MAIN CONTAINER from coba.php === */
        .container-booking {
            margin: 2rem auto;
            padding: 2rem;
            background: #353b48;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        }

        h2.booking-title {
            margin-bottom: 0.5rem;
            text-align: center;
            color: #dbe0e9;
            font-size: 1.8rem;
        }

        .date-info {
            text-align: center;
            color: #a7b0bf;
            font-size: 1rem;
            margin-bottom: 2rem;
        }

        .date-picker-container {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 2rem;
        }

        .date-picker-container label {
            margin-right: 10px;
            color: #dbe0e9;
        }

        .date-picker-container input {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: 1px solid #5a6473;
            background-color: #2f343e;
            color: #fff;
            outline: none;
            width: 200px;
        }

        /* Pastikan ini adalah kode untuk kontainer grid */
        /* Atur kontainer grid */
        /* Tambahkan ID #schedule untuk selektor yang lebih spesifik */
        .schedule-grid {
            /* Mengaktifkan tata letak grid dan menimpa gaya flexbox yang mungkin ada */
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;

        /* === SCHEDULE SLOTS from coba.php === */
        .schedule-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }

        @media (max-width: 1024px) {
            .schedule-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .schedule-grid {
                grid-template-columns: 1fr;
            }
        }

        .slot {
            background-color: #2f343e;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: left;
            color: #fff;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
            position: relative;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .slot:hover {
            background-color: #3d434f;
        }

        .slot-time {
            font-size: 1.1rem;
            /* Ukuran font lebih kecil */
            margin-bottom: 0.5rem;
        }

        .slot-time.flex-grow {
            flex-grow: 1;
            /* Biarkan waktu mengisi ruang */
        }


        .slot-status-label {
            font-size: 0.8rem;
            /* Ukuran font lebih kecil */
            color: #a7b0bf;
        }

        .slot-team-label {
            font-size: 0.9rem;
            color: #a7b0bf;
            display: block;
        }

        .slot-info {
            font-size: 0.9rem;
            /* Ukuran font lebih kecil */
            color: #fff;
            margin-bottom: 1rem;
        }

        .slot-btn {
            display: block;
            width: 100%;
            padding: 0.5rem 0.8rem;
            /* Mengubah ukuran padding */
            background-color: #283fa7ff;
            padding: 0.5rem 0.8rem; /* Mengubah ukuran padding */
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .slot.booked .slot-btn {
            background-color: #dc3545;
            cursor: not-allowed;
        }

        .slot.past-time .slot-btn {
            background-color: #6c757d;
            cursor: not-allowed;
        }

        .weekly-schedule-container {
            overflow: auto;
            /* Memungkinkan scrolling */
            max-width: 1100px;
            /* Gunakan max-width agar lebih responsif */
            margin: 0 auto;
            /* Ini yang membuat elemen menjadi di tengah */
        }

        .weekly-schedule-table {
            display: flex;
            flex-direction: column;
            background-color: #2f343e;
            border-radius: 12px;
            overflow: hidden;
            margin-top: 2rem;
            min-width: 800px;
            /* Lebar minimum untuk memastikan scroll */
        }

        .weekly-schedule-header {
            display: grid;
            grid-template-columns: 140px repeat(7, 1fr);
            /* 80px untuk kolom waktu */
            background-color: #1c2531;
            padding: 1rem 0;
            font-weight: bold;
            color: #dbe0e9;
            text-align: center;
            position: sticky;
            /* Membuat header lengket */
            top: 0;
            /* Menempel di bagian atas saat scroll */
            z-index: 10;
        }

        .weekly-schedule-row {
            display: grid;
            grid-template-columns: 140px repeat(7, 1fr);
            /* 80px untuk kolom waktu */
            text-align: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #5a6473;
        }

        .weekly-schedule-row:last-child {
            border-bottom: none;
        }

        .weekly-schedule-cell {
            padding: 1rem 0.5rem;
            color: #a7b0bf;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .weekly-schedule-cell.time-label {
            font-weight: bold;
            background-color: #353b48;
            color: #fff;
            position: sticky;
            /* Membuat kolom waktu lengket */
            left: 0;
            /* Menempel di bagian kiri saat scroll */
            z-index: 5;
        }

        .weekly-schedule-cell.booked {
            background-color: var(--usf-green);
            /* Warna hijau gelap baru */
            color: #fff;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.2s;
        }

        .weekly-schedule-cell.booked:hover {
            background-color: #35448cff;
            /* Warna hover yang lebih cerah */
        }


        /* Styles for the new modals from coba.php */
        /* === MODAL === */
        /* --- Perbaikan Z-Index Modal --- */
        .modal-booking {
            z-index: 1050;
            /* Tingkatkan nilai z-index agar selalu di atas navbar (z-index: 1000) */
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: none;
            align-items: center;
            justify-content: center;
        }

        .modal-content-booking {
            background-color: #2f343e;
            color: #dbe0e9;
            border-radius: 12px;
            padding: 1.5rem;
            /* Padding dikurangi */
            max-width: 400px;
            /* Ukuran kotak dikurangi */
            width: 90%;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
            position: relative;
            border-radius: 16px;
            /* === KODE BARU UNTUK SCROLLING === */
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-content-booking h3 {
            margin-top: 0;
            color: #dbe0e9;
            font-size: 1.5rem;
            /* Ukuran font judul dikurangi */
            font-weight: bold;
            margin-bottom: 0.5rem;
            /* Jarak bawah dikurangi */
        }

        .modal-content-booking p {
            font-size: 1rem;
            /* Ukuran font paragraf dikurangi */
            color: #a7b0bf;
            margin-bottom: 1rem;
            /* Jarak bawah dikurangi */
        }

        .modal-content-booking button {
            margin-top: 1rem;
            padding: 0.6rem 1.2rem;
            /* Ukuran padding tombol dikurangi */
            border: none;
            background-color: #4a90e2;
            color: white;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        }
          
        .weekly-schedule-cell.pending {
            background-color: #a79102ff; /* Warna hijau gelap baru */
            color: #fff;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.2s;
        }
        .weekly-schedule-cell.pending:hover {
            background-color: #958e17ff; /* Warna hover yang lebih cerah */
        }
        
/* Styling untuk Modal Booking */
.modal-booking {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.85);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 1000;
    backdrop-filter: blur(5px);
}

/* Konten di dalam Modal */
.modal-content-booking {
    background-color: #1a1a1a;
    color: #e0e0e0;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
    width: 90%;
    max-width: 600px;
    position: relative;
    font-family: 'Poppins', sans-serif;
    animation: fadeIn 0.3s ease-out;
    border: 1px solid #333;

    max-height: 90vh; /* Tentukan tinggi maksimal 90% dari viewport height */
    overflow-y: auto;   /* Tambahkan scrollbar vertikal jika konten melebihi tinggi maksimal */
}

/* Animasi untuk modal */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Tombol Tutup */
.close-btn {
    position: absolute;
    top: 15px;
    right: 15px;
    font-size: 28px;
    font-weight: bold;
    color: #888;
    background: none;
    border: none;
    cursor: pointer;
    transition: color 0.3s;
}

        .close-btn {
            position: absolute;
            top: 5px;
            /* Posisi diubah menjadi 5px dari atas */
            right: 10px;
            /* Posisi diubah */
            background-color: transparent !important;
            border: none !important;
            color: #dbe0e9;
            font-size: 2rem;
            /* Ukuran tombol close diperbesar */
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close-btn:hover {
            color: #ffd600;
            /* Warna hover disesuaikan */
        }

        /* CSS tambahan untuk memastikan tombol close-btn transparan */
        .modal-content-booking .close-btn {
            background-color: transparent !important;
            border: none !important;
            color: #dbe0e9;
            font-size: 2rem;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        /* New style for detail booking modal */
        #detailBookingModal .modal-content-booking {
            max-width: 300px;
            text-align: center;
            padding: 1.5rem;
            border: 2px solid #ffd600;
            animation: fadeInZoom 0.3s ease-out forwards;
        }

        #detailBookingModal h3 {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            border-bottom: 2px solid #ffd600;
            padding-bottom: 0.5rem;
            display: none;
        }

        #detailBookingModal #bookingDetails {
            text-align: center;
            margin-bottom: 1rem;
            padding-top: 0.5rem;
            font-family: 'Montserrat', sans-serif;
        }

        #detailBookingModal #bookingDetails .detail-item {
            display: flex;
            justify-content: center;
            margin-bottom: 0.6rem;
            font-size: 0.9rem;
            flex-direction: column;
            line-height: 1.5;
        }

        #detailBookingModal #bookingDetails .detail-item strong {
            color: #a7b0bf;
            font-weight: 500;
            font-size: 0.8rem;
        }

        #detailBookingModal #bookingDetails .detail-value {
            font-weight: bold;
            color: #ffd600;
            font-size: 1.1rem;
        }

        #detailBookingModal .confirm-btn {
            background-color: #1f3042ff;
            width: 100%;
            font-weight: 600;
            border-radius: 6px;
            padding: 0.6rem 1rem;
        }

        #detailBookingModal .confirm-btn:hover {
            background-color: #48698cff;
        }

        /* Animasi untuk modal */
        @keyframes fadeInZoom {
            from {
                opacity: 0;
                transform: scale(0.8);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }
.close-btn:hover {
    color: #e0e0e0;
}

/* Judul dan sub-judul */
.modal-content-booking h3 {
    text-align: center;
    margin-bottom: 8px;
    font-size: 2.2rem;
    color: #ffffff;
    font-weight: 700;
}

.modal-content-booking p {
    text-align: center;
    margin-bottom: 25px;
    color: #a0a0a0;
    font-size: 1rem;
}

/* Container Form */
.form-container {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* Grup Form untuk input berpasangan */
.form-group {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

@media (min-width: 640px) {
    .form-group {
        flex-direction: row;
    }
}

.form-group > div {
    flex: 1;
    display: flex;
    flex-direction: column;
}

/* Label Input */
.form-container label {
    margin-bottom: 8px;
    color: #ffffff;
    font-weight: 600;
    font-size: 0.9rem;
}

/* Input dan Textarea */
.form-container input[type="text"],
.form-container input[type="tel"],
.form-container input[type="number"],
.form-container textarea {
    width: 100%;
    padding: 12px 15px;
    border: 1px solid #333;
    border-radius: 8px;
    background-color: #2a2a2a;
    color: #e0e0e0;
    font-size: 1rem;
    transition: border-color 0.3s, box-shadow 0.3s;
}

.form-container input:focus,
.form-container textarea:focus {
    border-color: #ffd600;
    outline: none;
    box-shadow: 0 0 0 2px rgba(255, 214, 0, 0.2);
}

/* Styling untuk input plus/minus */
.input-plus-minus {
    display: flex;
    align-items: center;
    background-color: #2a2a2a;
    border: 1px solid #333;
    border-radius: 8px;
}

.input-plus-minus input {
    border: none;
    text-align: center;
    background-color: transparent;
    padding: 12px 0;
}

.input-plus-minus button {
    background-color: #ffd600;
    color: #1a1a1a;
    border: none;
    font-size: 1.2rem;
    width: 40px;
    height: 40px;
    border-radius: 8px;
    cursor: pointer;
    transition: background-color 0.3s;
}

.input-plus-minus button:hover {
    background-color: #e6c000;
}

        .form-container .confirm-btn:hover {
            background-color: #304055ff;
            ;
        }
.input-plus-minus .btn-minus {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
}

.input-plus-minus .btn-plus {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
}

/* Tombol Aksi */
.button-group {
    display: flex;
    justify-content: flex-end;
    gap: 15px;
    margin-top: 25px;
}

.confirm-btn, .cancel-btn {
    padding: 12px 25px;
    font-size: 1rem;
    font-weight: bold;
    border-radius: 8px;
    cursor: pointer;
    transition: background-color 0.3s, color 0.3s;
    border: none;
}

.confirm-btn {
    background-color: #4CAF50;
    color: white;
}

.confirm-btn:hover {
    background-color: #45a049;
}

.cancel-btn {
    background-color: #555;
    color: white;
}

.cancel-btn:hover {
    background-color: #444;
}

/* Perbaikan untuk tampilan modal konfirmasi akhir */
.confirmation-confirm-btn {
    background-color: #4CAF50;
    color: white;
    padding: 12px 25px;
    font-size: 1rem;
    font-weight: bold;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    transition: background-color 0.3s;
}

.confirmation-confirm-btn:hover {
    background-color: #45a049;
}

.confirmation-cancel-btn {
    background-color: #555;
    color: white;
    padding: 12px 25px;
    font-size: 1rem;
    font-weight: bold;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    transition: background-color 0.3s;
}

.confirmation-cancel-btn:hover {
    background-color: #444;
}
        /* Gaya yang sudah ada dari file Anda */
        .carousel {
            scroll-snap-type: x mandatory;
            overflow-x: auto;
            display: flex;
            scroll-behavior: smooth;
        }

        .carousel-item {
            scroll-snap-align: start;
            flex: none;
            width: 100%;
        }

        .carousel::-webkit-scrollbar {
            display: none;
        }

        /* Animasi muncul */
        @keyframes fadeInSlide {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .fade-in-left {
            animation: fadeInSlide 1s ease-out forwards;
        }

        /* About Section Styles */
        body {
            font-family: "Poppins", sans-serif;
        }

        .background-image {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: -10;
        }

        .text-shadow {
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.7);
        }

        /* Gaya tambahan untuk Swiper.js */
        .swiper-button-next,
        .swiper-button-prev {
            color: var(--primary-color) !important;
            background-color: var(--light-color) !important;
            border-radius: 50%;
            width: 44px !important;
            height: 44px !important;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .swiper-button-next:hover,
        .swiper-button-prev:hover {
            background-color: var(--primary-color) !important;
            color: var(--light-color) !important;
        }

        .swiper-button-next::after,
        .swiper-button-prev::after {
            font-size: 18px !important;
            font-weight: bold;
        }

        .swiper-pagination-bullet {
            background: #ccc !important;
            opacity: 1 !important;
        }

        .swiper-pagination-bullet-active {
            background: var(--primary-color) !important;
        }

        .fc-toolbar-title {
            font-size: 1.5rem !important;

        }

        /* Set tinggi semua baris tanggal menjadi 120px */
        .fc-daygrid-day-frame {
            height: 120px !important;
            min-height: 120px !important;
        }

        /* Area events dengan scroll */
        .fc-daygrid-day-events {
            overflow-y: auto;
            max-height: calc(120px - 30px);
            /* 30px untuk header tanggal */
            margin-right: 2px;
        }

        /* Header tanggal */
        .fc-daygrid-day-top {
            height: 30px;
        }

        /* Abu-abu default jika tidak ada match */

        /* Hilangkan padding yang tidak perlu */
        .fc-daygrid-day {
            padding: 0 !important;
        }

        /* Pastikan sel tanggal memiliki tinggi yang konsisten */
        .fc-daygrid-day {
            height: 120px !important;
        }

        .fc-button-primary {
            background-color: var(--usf-green) !important;
            border-color: var(--usf-green) !important;
            color: black !important;
        }

        .fc-button-primary:hover {
            background-color: #c0db00 !important;
            border-color: #c0db00 !important;
        }

        .fc-daygrid-day.fc-day-today {
            background-color: rgba(0, 214, 200, 0.2) !important;
        }

        #calendar-container {
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        /* Footer Styles */
        input:focus {
            color: var(--usf-green);
        }

        .hover-highlight li:hover,
        .hover-highlight p:hover {
            color: #b2daedff;
            cursor: pointer;
        }

        .social-icon {
            font-size: 1.75rem;
        }

        /* === GAYA UNTUK HOME DAN NAVBAR === */

        /* GAYA NAVBAR UTAMA */
        .header {
            width: 90%;
            padding: 10px 0;
            z-index: 1000;
            /* Memastikan navbar selalu di atas konten lain */
            transition: background-color 0.3s ease;
        }

 /* Navbar utama */
.navbar-menu {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1rem;
  border-radius: 20px;
            background-color: #353b48;
}

/* Kontainer navigasi */
.nav {
  display: flex;
  align-items: center;
  gap: 1.5rem;
}

/* Aturan untuk logo */
.nav img {
  width: 100px; /* Ukuran default desktop */
  height: auto;
  transition: transform 0.3s ease; /* Efek halus saat hover */
}

/* Style link navigasi */
.nav-link {
  padding: 0.5rem 1rem;
  text-decoration: none;
  color: #333;
  font-weight: 500;
  border-radius: 4px;
}

.nav-link:hover {
  background-color: #e9ecef;
/* Header tanggal */
.fc-daygrid-day-top {
height: 30px;
}

/* Event item styling */
.fc-event {
font-size: 12px;
padding: 2px 4px;
margin-bottom: 2px;
white-space: normal;
word-break: break-word;
}

/* Warna layanan custom */
.fc-event-cuci-eksterior {
background-color: #00ff88ff;
border-color: #ffffffff;
}

/* Biru */
.fc-event-cuci-interior {
background-color: #f4ff2cff;
border-color: #ffffffff;
}

/* Hijau */
.fc-event-detailing {
background-color: #d5beffff;
border-color: #ffffffff;
}

/* Oranye */
.fc-event-cuci-mobil {
background-color: #ffffffff;
border-color: #ffffffff;
}

/* Merah */
.fc-event-salon-mobil-kaca {
background-color: #ffb080ff;
border-color: #ffffffff;
}

/* Ungu */
.fc-event-perbaiki-mesin {
background-color: #ffa6c2ff;
border-color: #ffffffff;
}

/* Coklat */
.fc-event-default {
background-color: #ff7676ff;
border-color: #ffffffff;
}
}
/* Abu-abu default jika tidak ada match */

/* Hilangkan padding yang tidak perlu */
.fc-daygrid-day {
padding: 0 !important;
}

/* Pastikan sel tanggal memiliki tinggi yang konsisten */
.fc-daygrid-day {
height: 120px !important;
}
.fc-button-primary {
background-color: var(--usf-green) !important;
border-color: var(--usf-green) !important;
color: black !important;
}
.fc-button-primary:hover {
background-color: #c0db00 !important;
border-color: #c0db00 !important;
}
.fc-daygrid-day.fc-day-today {
background-color: rgba(179, 214, 0, 0.2) !important;
}
#calendar-container {
border-radius: 10px;
box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

/* Footer Styles */
input:focus {
color: var(--usf-green);
}

.hover-highlight li:hover,
.hover-highlight p:hover {
color: var(--usf-green);
cursor: pointer;
}

.social-icon {
font-size: 1.75rem;
}

/* === GAYA UNTUK HOME DAN NAVBAR === */

/* Navbar */
.header {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 1000;
    background-color: #0d1117; /* Warna latar belakang navbar tetap */
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    transition: none;
}

.header-inner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 1.5rem;
    max-width: 1200px;
    margin: auto;
}

.logo img {
    height: 50px;
    filter: none; /* Logo tetap berwarna asli */
}

.nav-link.active {
  background-color: #0d6efd;
  color: white;
}

.nav-link.disabled {
  color: #6c757d;
  pointer-events: none;
}

/* Responsive untuk mobile */
@media (max-width: 768px) {
    
  .navbar-menu {
    flex-direction: column;
    padding: 0.5rem;
  }
  
  .nav {
.navbar-menu .nav-link::after {
    content: '';
    position: absolute;
    width: 0;
    height: 2px;
    background-color: var(--usf-green);
    bottom: -5px;
    left: 0;
    transition: width 0.3s ease;
}

.navbar-menu .nav-link:hover::after {
    width: 100%;
    flex-wrap: wrap;
    justify-content: center;
    gap: 0.75rem;
  }
  
  .nav img {
    width: 80px; /* Logo lebih kecil di mobile */
    margin-bottom: 0.5rem;
  }
  
  .nav-link {
    padding: 0.25rem 0.5rem;
    font-size: 0.9rem;
  }
}

/* Tambahan untuk mode admin */
.admin-editable-image {
  cursor: pointer;
  outline: 2px dashed #0d6efd;
}

.hidden-file-input {
  display: none;
}
html {
  overflow-x: hidden; /* Mencegah scroll horizontal */
}}

        /* Base styles untuk hero section */
.hero-section {
  background-size: cover;
  background-position: center;
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  text-align: center;
  color: white;
  position: relative;
}

/* Responsive font sizes */
.hero-title {
  font-size: 4rem; /* Ukuran default desktop */
  font-weight: 800;
  margin-bottom: 1rem;
  line-height: 1.2;
}

.hero-subtitle {
  font-size: 1.5rem;
  margin-bottom: 2rem;
    font-family: 'Montserrat', sans-serif;
    font-size: 1.5rem;
    margin-bottom: 2rem;
}

.btn-book {
  font-size: 1.2rem;
  padding: 0.8rem 2rem;
}

/* Mobile responsive adjustments */
@media (max-width: 768px) {
  .hero-title {
    font-size: 2.5rem; /* 37.5% lebih kecil dari desktop */
    line-height: 1.1;
  }
  
  .hero-subtitle {
    font-size: 1.1rem; /* 27% lebih kecil dari desktop */
  }
  
  .btn-book {
    font-size: 1rem;
    padding: 0.6rem 1.5rem;
  }
.btn-book:hover {
    background-color: #55a858;
    transform: translateY(-2px);
}}

/* Extra small devices (phones, 576px and down) */
@media (max-width: 576px) {
  .hero-title {
    font-size: 2rem; /* 50% lebih kecil dari desktop */
    margin-bottom: 0.8rem;
  }
  
  .hero-subtitle {
    font-size: 1rem;
    margin-bottom: 1.5rem;
  }
  
  .btn-book {
    font-size: 0.9rem;
  }
}

/* Penyesuaian untuk mode admin */
.admin-editable-text {
  min-height: 1em;
  outline: none;
}

@media (max-width: 768px) {
  .admin-editable-text {
    min-height: 1.2em; /* Lebih mudah diklik di mobile */
  }
}
        /* Gaya tambahan untuk Swiper.js */
        .swiper-button-next,
        .swiper-button-prev {
            color: var(--primary-color) !important;
            background-color: var(--light-color) !important;
            border-radius: 50%;
            width: 44px !important;
            height: 44px !important;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }

        .swiper-button-next:hover,
        .swiper-button-prev:hover {
            background-color: var(--primary-color) !important;
            color: var(--light-color) !important;
        }

        .swiper-button-next::after,
        .swiper-button-prev::after {
            font-size: 18px !important;
            font-weight: bold;
        }

        .swiper-pagination-bullet {
            background: #ccc !important;
            opacity: 1 !important;
        }

        .swiper-pagination-bullet-active {
            background: var(--primary-color) !important;
        }

        /* CSS BARU UNTUK GALERI */
        .myGallerySwiper {
            max-width: 1000px;
            margin: 0 auto;
            position: relative;
            padding-bottom: 40px;
        }

        .myGallerySwiper .swiper-slide {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 0;
            box-sizing: border-box;
        }

        .myGallerySwiper .grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            grid-template-rows: repeat(2, 1fr);
            gap: 16px;
            height: 100%;
        }

        .myGallerySwiper .grid img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            aspect-ratio: 16 / 9;
            border-radius: 12px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.5s ease, box-shadow 0.5s ease;
        }

        .myGallerySwiper .grid img:hover {
            transform: scale(1.03);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }

        /* Ensure horizontal layout */
        .grid.grid-cols-4 {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .gallery-image {
            width: 100%;
            height: 200px;
            /* Fixed height for consistency */
            object-fit: cover;
        }


        /* Base styles untuk section */
.bg-gradient-to-r {
  padding: 2rem 1rem !important; /* Padding lebih kecil di mobile */
}

/* Responsive text scaling */
.text-lg {
  font-size: 1rem; /* Default 1.125rem */
}

.text-2xl {
  font-size: 1.25rem; /* Default 1.5rem */
}

.text-5xl {
  font-size: 2rem; /* Default 3rem */
}

/* Responsive layout untuk fitur list */
@media (max-width: 768px) {
  
  .border-r {
    border-right: none !important;
    border-bottom: 1px solid #4b5563;
    padding: 0 0 1.5rem 0 !important;
    margin-bottom: 1.5rem;
  }
  
  .px-12 {
    padding-left: 1rem !important;
    padding-right: 1rem !important;
  }
}


@media (max-width: 768px) {
  .w-full.md\:w-1\/3 {
    width: 100% !important;
    margin-bottom: 1.5rem;
  }
  
  /* Text overlay lebih kecil */
  .absolute.bottom-0 {
    padding: 0.5rem !important;
  }
}

/* Penyesuaian khusus untuk teks yang editable di mobile */
.admin-editable-text {
  min-height: 1em; /* Pastikan tetap bisa diklik */
}

/* Icon location lebih kecil */
.fa-map-marker-alt {
  font-size: 2rem !important;
}

/* Padding heading program */
.px-8 {
  padding-left: 1rem !important;
  padding-right: 1rem !important;
}

.my-6 {
  margin-top: 1rem !important;
  margin-bottom: 1rem !important;
}

/* Base Footer Styles */
footer {
  padding-top: 2rem;
  padding-bottom: 0;
}

/* Container Responsive */
.max-w-7xl {
  padding-left: 1rem !important;
  padding-right: 1rem !important;
}

/* Layout Adjustments for Mobile */
@media (max-width: 768px) {
  .flex-col.md\:flex-row {
    flex-direction: column;
    gap: 2rem;
    align-items: center;
  }
  
  .md\:gap-24 {
    gap: 2rem;
  }
  
  .md\:justify-center {
    justify-content: center;
  }
  
  .text-center.md\:text-left {
    text-align: center !important;
  }
}

/* Logo Responsive */
.h-40 {
  height: 30vw !important;
  max-height: 120px;
  min-height: 80px;
  margin-bottom: 1rem !important;
}

/* Text Sizing for Mobile */
@media (max-width: 768px) {
  .text-lg {
    font-size: 0.9rem;
  }
  
  .text-xl {
    font-size: 1.1rem;
  }
  
  address p, 
  .hover-highlight li {
    font-size: 0.9rem;
  }
}

/* Columns Layout */
@media (max-width: 768px) {
  .md\:flex-row {
    flex-direction: column;
    gap: 2rem;
  }
  
  .max-w-md {
    max-width: 100% !important;
  }
}

/* Social Icons */
.social-icon {
  font-size: 1.2rem;
}

@media (max-width: 768px) {
  .flex.space-x-6 {
    justify-content: center;
    margin-top: 1.5rem;
  }
}

/* Copyright Section */
.py-4 {
  padding-top: 1rem !important;
  padding-bottom: 1rem !important;
  font-size: 0.8rem;
}

/* Hover Effects (Desktop only) */
@media (hover: hover) {
  .hover-highlight li:hover,
  .social-icon:hover {
    color: #5fa140;
    cursor: pointer;
  }
}

/* Admin Mode Adjustments */
.admin-editable-image {
  min-width: 100px;
}

@media (max-width: 768px) {
  .admin-editable-text {
    min-height: 1.2em; /* Better touch target */
  }
}

/* Base Booking Section Styles */
.booking-section {
  padding: 2rem 0;
}

/* Responsive Font Sizes */
.text-5xl {
  font-size: 3rem; /* Desktop */
}

.text-3xl {
  font-size: 1.75rem; /* Desktop */
}

.text-xl {
  font-size: 1.25rem; /* Desktop */
}

/* Mobile Adjustments */
@media (max-width: 768px) {
  /* Font Sizes */
  .text-5xl {
    font-size: 2rem; /* Smaller on mobile */
  }
  
  .text-3xl {
    font-size: 1.5rem;
  }
  
  .text-xl {
    font-size: 1.1rem;
  }
  
  /* Two-column layout for booking form */
  .form-group {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
  }
  
  /* Adjust modal sizes */
  .modal-content-booking {
    width: 90%;
    max-width: 100%;
  }
  
  /* Schedule grid adjustments */
  .schedule-grid {
    grid-template-columns: repeat(2, 1fr) !important; /* 2 columns */
  }
  
  /* Weekly schedule adjustments */
  .weekly-schedule-container {
    overflow-x: auto;
  }
  
  .weekly-schedule-table {
    min-width: 700px;
  }
}

/* Extra Small Devices */
@media (max-width: 576px) {
  .text-5xl {
    font-size: 1.75rem;
  }
  
  .form-group {
    grid-template-columns: 1fr; /* Single column on very small screens */
    gap: 0.5rem;
  }
  
  /* Button adjustments */
  .btn-primary-custom {
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
  }
  
  /* Modal content adjustments */
  .modal-content-booking {
    padding: 1rem;
  }
}

/* Form Styling */
.form-container {
  width: 100%;
  max-width: 800px;
  margin: 0 auto;
}

.form-group {
  margin-bottom: 1rem;
}

.form-group div {
  margin-bottom: 0.5rem;
}

/* Button Styling */
.btn-primary-custom {
  background-color: #4CAF50;
  color: white;
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 1rem;
  transition: background-color 0.3s;
}

.btn-primary-custom:hover {
  background-color: #45a049;
}

/* Modal Responsiveness */
@media (max-width: 768px) {
  .modal-content-booking.max-w-lg {
    width: 90%;
  }
  
  #finalBookingDetails {
    padding: 1rem;
  }
  
  .confirmation-confirm-btn,
  .confirmation-cancel-btn {
    padding: 0.5rem 1rem;
    font-size: 0.9rem;
  }
}

/* Schedule Grid */
.schedule-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr); /* Default 4 columns */
  gap: 0.5rem;
  padding: 0 1rem;
}

@media (max-width: 992px) {
  .schedule-grid {
    grid-template-columns: repeat(3, 1fr); /* 3 columns on tablet */
  }
}

@media (max-width: 768px) {
  .schedule-grid {
    grid-template-columns: repeat(2, 1fr); /* 2 columns on mobile */
  }
}

@media (max-width: 480px) {
  .schedule-grid {
    grid-template-columns: 1fr; /* 1 column on very small screens */
  }
}
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.4);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background-color: #fff;
            padding: 20px;
            border: 1px solid #888;
            width: 90%;
            max-width: 500px;
            border-radius: 8px;
            position: relative;
        }

        @media (max-width: 768px) {
            .fc-daygrid-day-frame {
                height: 80px !important;
                min-height: 80px !important;
            }

            .fc-daygrid-day-events {
                max-height: calc(80px - 20px);
            }

            .fc-toolbar-title {
                font-size: 1.2rem !important;
            }

            .fc-event {
                font-size: 10px;
                /* Ukuran font diperkecil untuk event */
                padding: 1px 2px;
            }
        }

        .btn-primary-custom {
            background-color: var(--usf-green);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }

        .btn-primary-custom:hover {
            background-color: #557aa8ff;
        }

        .swal2-popup {
            background-color: #2f343e !important;
            color: #dbe0e9 !important;
            border-radius: 12px !important;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2) !important;
            padding: 1.5rem !important;
        }

        .swal2-title {
            color: #dbe0e9 !important;
            font-weight: bold !important;
            font-size: 1.5rem !important;
        }

        .swal2-html-container {
            color: #a7b0bf !important;
            font-size: 1rem !important;
            margin: 1rem 0 !important;
        }

        .swal2-actions {
            margin-top: 1.5rem !important;
            gap: 1rem;
        }

        .swal2-confirm.swal2-styled,
        .swal2-cancel.swal2-styled {
            font-weight: bold !important;
            padding: 0.6rem 1.2rem !important;
            border-radius: 8px !important;
            border: none !important;
        }

        .swal2-confirm.swal2-styled {
            background-color: #4a90e2 !important;
            color: white !important;
        }

        .swal2-confirm.swal2-styled:hover {
            background-color: #5d9ee5 !important;
        }

        .swal2-cancel.swal2-styled {
            background-color: #6c757d !important;
            color: white !important;
        }

        .swal2-cancel.swal2-styled:hover {
            background-color: #5a6268 !important;
        }

        .swal2-close {
            top: 0.5rem !important;
            right: 0.5rem !important;
            color: #dbe0e9 !important;
            font-size: 2rem !important;
            transition: color 0.3s ease !important;
        }

        .swal2-close:hover {
            color: #ffd600 !important;
        }

        /* Tambahan CSS untuk tombol Batal dan Pesan Sekarang di konfirmasi akhir */
        .confirmation-cancel-btn {
            background-color: #dc3545;
            /* Merah */
            color: white;
            font-weight: bold;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            transition: background-color 0.3s ease;
        }

        .confirmation-cancel-btn:hover {
            background-color: #c82333;
            /* Merah lebih gelap */
        }

        .confirmation-confirm-btn {
            background-color: #28a745;
            /* Hijau */
            color: white;
            font-weight: bold;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            transition: background-color 0.3s ease;
        }

        .confirmation-confirm-btn:hover {
            background-color: #218838; /* Hijau lebih gelap */
        }

        /* CSS BARU UNTUK MODAL VIDEO */
        .modal-header-video {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
        }

        .modal-header-video h3 {
            margin-bottom: 0;
            text-align: left;
            flex-grow: 1;
        }

        .modal-header-video .close-btn {
            position: static;
            font-size: 2rem;
        }

        /* === Gaya untuk Admin Edit Inline === */
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

        .hidden-file-input {
            display: none;
        }
    </style>
</head>

<body class="bg-black text-white <?php echo $is_admin_mode ? 'is-admin-mode' : ''; ?>">
    <header id="main-header" class="header fixed-navbar">

        <div class="header-inner">
            <nav class="navbar-menu">
                <nav class="nav">
            <img src="<?php echo get_content('logo_image', 'logom.png'); ?>" alt="Soccer image" class="w-full max-w-[100px]
<?php echo $is_admin_mode ? 'admin-editable-image' : ''; ?>"
                data-key="logo_image">
            <?php if ($is_admin_mode): ?><input type="file" class="hidden-file-input"><?php endif; ?>

  <a class="nav-link" href="#">Beranda</a>
  <a class="nav-link" href="#booking-section">Pesan</a>
  <a class="nav-link" href="#gallery-section">Galeri</a>
  <a class="nav-link" href="#map-section">Kontak</a>
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
            <a href="#booking-section" class="btn btn-book">BOOKING SEKARANG</a>
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
                        style="font-family: 'Poppins', sans-serif;"
                        <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                        data-key="book_heading">
                        <?php echo get_content('book_heading', 'Booking Lapangan'); ?>
                    </h2><br><br>

                    <div id="dailyScheduleContainer" style="max-width: 100%; flex-grow: 1;">

                        <div class="fw-bold text-dark text-center" class="date-info" id="currentDateInfo">Tanggal: 08 August 2025</div><br>
                        <div class="text-center d-flex flex-column items-center gap-2 mb-4">
                            <div class="date-picker-container">
                                <label for="date-input" class="fw-bold text-dark">Pilih Tanggal:</label>
                                <input type="date" id="date-input" value="<?php echo date('Y-m-d'); ?>">
                            </div>
<section id="home-section" class="hero-section admin-editable-image"
    style="background-image: url('<?php echo get_content('home_bg_image', 'CZX.jpg'); ?>');"
    data-key="home_bg_image">
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
        <a href="#booking-section" class="btn btn-book">BOOKING SEKARANG</a>
    </div>
</section>
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
            <div class="form-group">
                <div>
                    <label for="notes" class="<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                           <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                           data-key="booking_notes_label">
                           <?php echo get_content('booking_notes_label', 'Catatan Tambahan:'); ?>
                    </label>
                    <textarea id="notes" rows="3" placeholder="Misal: Siapkan air mineral"></textarea>
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
            <div class="max-w-7xl w-full flex flex-col md:flex-row items-center md:items-start gap-10 md:gap-20">
                <div class="text-white max-w-xl pl-4 md:pl-12">
                    <h1 class="text-[50.56px] leading-[80px] font-normal mb-6 text-shadow fw-bold
                    <?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                        data-aos="fade-up" data-aos-delay="100" data-aos-duration="1000"
                        style="font-family: 'Poppins', sans-serif; color: #ffffffff;"
                        <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                        data-key="abou_head">
                        <?php echo get_content('abou_head', 'MGD Soccer Field'); ?>

                    </h1>
                    <p class="text-[27px] leading-[36px] font-normal text-white mb-6 text-shadow
<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                        data-aos="fade-up" data-aos-delay="300" data-aos-duration="1000"
                        style="font-family: 'Poppins', sans-serif;"
                        <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                        data-key="about_text1">
                        <?php echo get_content('about_text1', 'MGD Soccer Field Magelang hadir sebagai wadah bagi setiap komunitas pecinta sepak bola yang ingin merasakan sensasi bermain dengan kualitas terbaik dan suasana menyenangkan.'); ?>
                    </p>

                    <p class="text-[27px] leading-[36px] font-normal text-white text-shadow
<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                        data-aos="fade-up" data-aos-delay="500" data-aos-duration="1000"
                        style="font-family: 'Poppins', sans-serif;"
                        <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                        data-key="about_text2">
                        <?php echo get_content('about_text2', 'Kami yakin bahwa sepak bola bukan hanya tentang mencetak gol, tapi juga tentang menjaga kebersamaan, tawa, dan semangat sportifitas.'); ?>
                    </p>
                </div>

                <div class="w-full md:w-auto" data-aos="fade-left" data-aos-duration="1600" style="position: relative;">
                    <img src="<?php echo get_content('about_image_path', 'min.jpg'); ?>" alt="Soccer image" class="w-full max-w-[430px]
<?php echo $is_admin_mode ? 'admin-editable-image' : ''; ?>"
                        data-key="about_image_path">
                    <?php if ($is_admin_mode): ?><input type="file" class="hidden-file-input"><?php endif; ?>
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
                </div>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
                <div class="swiper-pagination"></div>
            </div>
        </div>
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
 <section class="about-section py-5">
    <div class="min-h-screen flex items-center px-6 py-12 relative" >
   <div class="max-w-7xl w-full flex flex-col md:flex-row items-center md:items-start gap-10 md:gap-20">
    <div class="text-white max-w-xl pl-4 md:pl-12">
     <h1 class="text-[50.56px] leading-[80px] font-normal mb-6 text-shadow fw-bold"
    data-aos="fade-up" data-aos-delay="100" data-aos-duration="1000"
    style="font-family: 'Saira', sans-serif;"
    <?php echo $is_admin_mode ? 'contenteditable="true" class="admin-editable-text"' : ''; ?>
    data-key="about_full_heading">
    <span style="color: #5fa140ff;">
        <?php echo get_content('ab_head', 'MGD Soccer Field'); ?>
    </span>
    <span class="text-white">
        <?php echo get_content('about_heading1', 'Magelang'); ?>
    </span>
</h1>
<p class="text-[27px] leading-[36px] font-normal text-white mb-6 text-shadow
<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
data-aos="fade-up" data-aos-delay="300" data-aos-duration="1000"
style="font-family: 'Lexend Deca', sans-serif;"
<?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
data-key="about_text1">
<?php echo get_content('about_text1', 'MGD Soccer Field Magelang hadir sebagai wadah bagi setiap komunitas pecinta sepak bola yang ingin merasakan sensasi bermain dengan kualitas terbaik dan suasana menyenangkan.'); ?>
</p>

            <div class="flex justify-start px-8 my-6 justify-content-center">
                <h2 class="text-white text-5xl font-bold text-center
<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                    data-aos="fade-down"
                    style="font-family: 'Poppins', sans-serif;"
                    <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
                    data-key="program_heading">
                    <?php echo get_content('program_heading', 'Program Spesial MGD'); ?>
                </h2>
            </div>
<div class="fade-up" data-aos-delay="500" data-aos-duration="1000"
style="font-family: 'Lexend Deca', sans-serif;">
<?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>
data-key="about_text2">
<?php echo get_content('about_text2', 'Kami yakin bahwa sepak bola bukan hanya tentang mencetak gol, tapi juga tentang menjaga kebersamaan, tawa, dan semangat sportifitas.'); ?>
</p>
</div>

            <div class="flex flex-col md:flex-row gap-6 my-10 justify-between">
                <div class="relative w-full md:w-1/3">
                    <div style="position: relative;">
                        <img src="<?php echo get_content('program1_image_path', 'pemain.png'); ?>" alt="Private Event" class="w-full h-auto object-cover rounded-lg
<?php echo $is_admin_mode ? 'admin-editable-image' : ''; ?>"
                            data-key="program1_image_path">
                        <?php if ($is_admin_mode): ?><input type="file" class="hidden-file-input"><?php endif; ?>
                    </div>
                    <div class="absolute inset-x-0 bottom-0 p-2 bg-black/70 rounded-b-lg">
                        <p class="text-white text-2xl font-bold text-center
<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                            data-key="program1_title"
                            <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>>
                            <?php echo get_content('program1_title', 'Reward Pemain Terbaik'); ?>
                        </p>
                    </div>
                </div>
                <div class="relative w-full md:w-1/3">
                    <div style="position: relative;">
                        <img src="<?php echo get_content('program2_image_path', 'pelajar.png'); ?>" alt="Rent a Field" class="w-full h-auto object-cover rounded-lg
data-aos="fade-down"
style="font-family: 'Montserrat', sans-serif;"
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
<div class="grid grid-cols-2 grid-rows-2 gap-4">
<div style="position: relative;">
<img src="<?php echo get_content('gallery_image1_path', 'galeri/gal1.png'); ?>" alt="Gallery Image 1" class="gallery-image w-full h-auto object-cover shadow-md rounded-xl
>>>>>>> dd5422c7dfd4bd1409ad21e0a5bfeae8ce0f95ce
<?php echo $is_admin_mode ? 'admin-editable-image' : ''; ?>"
                            data-key="program2_image_path">
                        <?php if ($is_admin_mode): ?><input type="file" class="hidden-file-input"><?php endif; ?>
                    </div>
                    <div class="absolute inset-x-0 bottom-0 p-2 bg-black/70 rounded-b-lg">
                        <p class="text-white text-2xl font-bold text-center
<?php echo $is_admin_mode ? 'admin-editable-text' : ''; ?>"
                            data-key="program2_title"
                            <?php echo $is_admin_mode ? 'contenteditable="true"' : ''; ?>>
                            <?php echo get_content('program2_title', 'Diskon Khusus Pelajar'); ?>
                        </p>
                    </div>
                </div>
                <div class="relative w-full md:w-1/3">
                    <div style="position: relative;">
                        <img src="<?php echo get_content('program3_image_path', 'sewa.png'); ?>" alt="Open Play" class="w-full h-auto object-cover rounded-lg
<?php echo $is_admin_mode ? 'admin-editable-image' : ''; ?>"
                            data-key="program3_image_path">
                        <?php if ($is_admin_mode): ?><input type="file" class="hidden-file-input"><?php endif; ?>
                    </div>
                    <div class="absolute inset-x-0 bottom-0 p-2 bg-black/70 rounded-b-lg">
                        <p class="text-white text-2xl font-bold text-center
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
                        
                        switch(status.toLowerCase()) {
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

                        const endTime = new Date(slotDate.getTime() + 90 * 60000).toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit'});
                        
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
            const options = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' };
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