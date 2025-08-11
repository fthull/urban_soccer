<?php
// Pastikan file conn.php ada dan berisi koneksi database
include 'conn.php';

// Atur zona waktu ke Jakarta untuk memastikan konsistensi waktu
date_default_timezone_set('Asia/Jakarta');

/**
 * Handle POST request untuk booking baru.
 * Menerima data nama, no_hp, tanggal, dan jam dari form.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['change'])) {
    $nama = $_POST['nama'] ?? '';
    $no_hp = $_POST['no_hp'] ?? '';
    $tanggal = $_POST['tanggal'] ?? '';
    $jam = $_POST['jam'] ?? '';

    // --- VALIDASI BARU UNTUK TANGGAL DAN WAKTU YANG SUDAH LEWAT ---
    $current_date = date('Y-m-d');
    $current_time = date('H:i');

    if ($tanggal < $current_date) {
        echo "Gagal: Anda tidak bisa memesan untuk tanggal yang sudah lewat.";
        exit;
    }

    // Jika tanggal yang dipesan adalah hari ini, cek apakah jamnya sudah lewat
    if ($tanggal === $current_date && $jam < $current_time) {
        echo "Gagal: Anda tidak bisa memesan untuk jam yang sudah lewat di hari ini.";
        exit;
    }
    // --- AKHIR VALIDASI BARU ---

    // Gabungkan tanggal dan jam menjadi format datetime
    $waktu_penuh = $tanggal . ' ' . $jam . ':00';

    // Cek apakah waktu sudah dibooking
    $cek = $conn->prepare("SELECT COUNT(*) FROM booking WHERE waktu = ?");
    $cek->bind_param("s", $waktu_penuh);
    $cek->execute();
    $cek->bind_result($count);
    $cek->fetch();
    $cek->close();

    if ($count > 0) {
        echo "Waktu sudah dibooking!";
        exit;
    }

    // Insert data booking baru ke database
    $stmt = $conn->prepare("INSERT INTO booking (nama, no_hp, tanggal, waktu) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $nama, $no_hp, $tanggal, $waktu_penuh);

    if ($stmt->execute()) {
        echo "Berhasil";
    } else {
        echo "Gagal";
    }
    $stmt->close();
    exit;
}

/**
 * Handler untuk permintaan AJAX yang mengambil detail booking harian.
 * Mengembalikan nama pemesan dan status untuk setiap slot.
 */
if (isset($_GET['get_daily_bookings']) && isset($_GET['tanggal'])) {
    $tanggal = $_GET['tanggal'];
    $daily_bookings = [];

    // Daftar semua slot waktu
    $all_slots = [
        '06:00', '07:30', '09:00', '10:30', '12:00', '13:30', '15:00', '16:30',
        '18:00', '19:30', '21:00', '22:30'
    ];

    // Ambil data booking dari database
    $stmt = $conn->prepare("SELECT waktu FROM booking WHERE DATE(waktu) = ?");
    $stmt->bind_param("s", $tanggal);
    $stmt->execute();
    $result = $stmt->get_result();

    $booked_data = [];
    while ($row = $result->fetch_assoc()) {
        $booked_data[date('H:i', strtotime($row['waktu']))] = true;
    }

    // Gabungkan data booking dengan semua slot waktu
    foreach ($all_slots as $slot_start_time) {
        $daily_bookings[$slot_start_time] = [
            'booked' => isset($booked_data[$slot_start_time]),
            'nama' => null
        ];
    }

    echo json_encode($daily_bookings);
    exit;
}

/**
 * Handler untuk permintaan AJAX yang mengambil data booking mingguan.
 */
if (isset($_GET['get_weekly_bookings']) && isset($_GET['start_date'])) {
    $start_date = $_GET['start_date'];
    $end_date = date('Y-m-d', strtotime($start_date . ' +6 days'));
    $weekly_bookings = [];

    $stmt = $conn->prepare("SELECT waktu, status FROM booking WHERE DATE(waktu) BETWEEN ? AND ?");
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $dayOfWeek = date('N', strtotime($row['waktu'])); // 1 (Senin) hingga 7 (Minggu)
        $time = date('H:i', strtotime($row['waktu']));
        if (!isset($weekly_bookings[$dayOfWeek])) {
            $weekly_bookings[$dayOfWeek] = [];
        }
        $weekly_bookings[$dayOfWeek][$time] = [
            'booked' => true,
            'status' => $row['status']
        ];
    }

    echo json_encode($weekly_bookings);
    exit;
}

/**
 * Handle POST request untuk mengubah booking yang sudah ada.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['change'])) {
    $nama = $_POST['nama'] ?? '';
    $no_hp = $_POST['no_hp'] ?? '';
    $tanggal = $_POST['tanggal'] ?? '';
    $jam = $_POST['jam'] ?? '';
    $waktu_baru = $tanggal . ' ' . $jam . ':00';

    // Cari ID booking berdasarkan nama dan no_hp
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

    // Cek apakah waktu baru sudah dibooking oleh orang lain
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

    // Update data booking
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
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MGD Seccor Magelang</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&family=Montserrat:wght@400;600;700&family=Saira&family=Lexend+Deca&display=swap" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <link rel="stylesheet" href="style.css">

    <style>
        :root {
            --usf-green: #5fa140ff;
            --primary-color: var(--usf-green);
            --light-color: #fff;
        }

        /* === HEADER from coba.php === */
        .header-booking {
            background-color: #1c2531;
            color: #fff;
            text-align: center;
            padding: 3rem 1rem 2rem;
            margin-bottom: 2rem;
        }

        .header-booking h1 {
            font-size: 1.5rem; /* Ukuran font diubah menjadi lebih kecil */
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
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .slot:hover {
            background-color: #3d434f;
        }

        .slot-time {
            font-size: 1.1rem; /* Ukuran font lebih kecil */
            margin-bottom: 0.5rem;
        }

        .slot-time.flex-grow {
            flex-grow: 1; /* Biarkan waktu mengisi ruang */
        }


        .slot-status-label {
            font-size: 0.8rem; /* Ukuran font lebih kecil */
            color: #a7b0bf;
        }

        .slot-team-label {
            font-size: 0.9rem;
            color: #a7b0bf;
            display: block;
        }

        .slot-info {
            font-size: 0.9rem; /* Ukuran font lebih kecil */
            color: #fff;
            margin-bottom: 1rem;
        }

        .slot-btn {
            display: block;
            width: 100%;
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

        /* === NEW WEEKLY SCHEDULE STYLES (UPDATED FOR STICKY HEADER) === */
        .weekly-schedule-container {
            overflow: auto; /* Memungkinkan scrolling */
        }
        .weekly-schedule-table {
            display: flex;
            flex-direction: column;
            background-color: #2f343e;
            border-radius: 12px;
            overflow: hidden;
            margin-top: 2rem;
            min-width: 800px; /* Lebar minimum untuk memastikan scroll */
        }
        .weekly-schedule-header {
            display: grid;
            grid-template-columns: 140px repeat(7, 1fr); /* 80px untuk kolom waktu */
            background-color: #1c2531;
            padding: 1rem 0;
            font-weight: bold;
            color: #dbe0e9;
            text-align: center;
            position: sticky; /* Membuat header lengket */
            top: 0; /* Menempel di bagian atas saat scroll */
            z-index: 10;
        }
        .weekly-schedule-row {
            display: grid;
            grid-template-columns: 140px repeat(7, 1fr); /* 80px untuk kolom waktu */
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
            position: sticky; /* Membuat kolom waktu lengket */
            left: 0; /* Menempel di bagian kiri saat scroll */
            z-index: 5;
        }
        .weekly-schedule-cell.booked {
            background-color: var(--usf-green); /* Warna hijau gelap baru */
            color: #fff;
            cursor: pointer;
            font-weight: bold;
            transition: background-color 0.2s;
        }
        .weekly-schedule-cell.booked:hover {
            background-color: #28a745; /* Warna hover yang lebih cerah */
        }


        /* Styles for the new modals from coba.php */
        /* === MODAL === */
        .modal-booking {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 999;
        }

        .modal-content-booking {
            background-color: #2f343e;
            color: #dbe0e9;
            border-radius: 12px;
            padding: 1.5rem; /* Padding dikurangi */
            max-width: 400px; /* Ukuran kotak dikurangi */
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
            font-size: 1.5rem; /* Ukuran font judul dikurangi */
            font-weight: bold;
            margin-bottom: 0.5rem; /* Jarak bawah dikurangi */
        }

        .modal-content-booking p {
            font-size: 1rem; /* Ukuran font paragraf dikurangi */
            color: #a7b0bf;
            margin-bottom: 1rem; /* Jarak bawah dikurangi */
        }

        .modal-content-booking button {
            margin-top: 1rem;
            padding: 0.6rem 1.2rem; /* Ukuran padding tombol dikurangi */
            border: none;
            background-color: #4a90e2;
            color: white;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .modal-content-booking button:hover {
            background-color: #5d9ee5;
        }

        .close-btn {
            position: absolute;
            top: 5px; /* Posisi diubah menjadi 5px dari atas */
            right: 10px; /* Posisi diubah */
            background-color: transparent !important;
            border: none !important;
            color: #dbe0e9;
            font-size: 2rem; /* Ukuran tombol close diperbesar */
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .close-btn:hover {
            color: #ffd600; /* Warna hover disesuaikan */
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
            background-color: #4a90e2;
            width: 100%;
            font-weight: 600;
            border-radius: 6px;
            padding: 0.6rem 1rem;
        }
        #detailBookingModal .confirm-btn:hover {
            background-color: #5d9ee5;
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

        /* === FORM STYLE === */
        .form-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
            text-align: left;
        }

        .form-container .form-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-container label {
            font-weight: bold;
            color: #dbe0e9;
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-container input,
        .form-container textarea,
        .form-container select {
            padding: 0.8rem;
            font-size: 1rem;
            border: 1px solid #5a6473;
            border-radius: 8px;
            width: 100%;
            box-sizing: border-box;
            background-color: #353b48;
            color: #fff;
        }

        .form-container input:focus,
        .form-container textarea:focus,
        .form-container select:focus {
            border-color: #ffd600;
            outline: none;
        }

        .form-container .button-group {
            grid-column: 1 / -1;
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .form-container button {
            width: 100%;
            padding: 0.8rem;
            font-size: 1rem;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.2s;
            font-weight: bold;
        }

        .form-container .cancel-btn {
            background-color: #6c757d;
            color: white;
        }

        .form-container .cancel-btn:hover {
            background-color: #5a6268;
        }

        .form-container .confirm-btn {
            background-color: var(--usf-green);
            color: white;
        }

        .form-container .confirm-btn:hover {
            background-color: #218838;
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
            text-shadow: 1px 1px 2px rgba(0,0,0,0.7);
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

        /* Navbar & Hero Section */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            background-color: transparent;
            box-shadow: none;
            transition: transform 0.3s ease;
        }

        .header.hidden {
            transform: translateY(-100%);
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
            filter: brightness(0) invert(1);
        }

        .navbar-menu .nav-links {
            list-style: none;
            display: flex;
            margin: 0;
            padding: 0;
            gap: 2rem;
        }

        .navbar-menu .nav-link {
            font-weight: 600;
            color: white;
            position: relative;
            transition: color 0.3s ease;
        }

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
        }

        .hero-section {
            position: relative;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            overflow: hidden;
            text-align: center;
        }

        .hero-content {
            position: relative;
            z-index: 10;
        }

        .btn-book {
            background-color: var(--usf-green);
            color: white;
            border: none;
            padding: 0.75rem 2.5rem;
            border-radius: 50px;
            font-size: 1.25rem;
            font-weight: bold;
            transition: background-color 0.3s ease, transform 0.3s ease;
        }

        .btn-book:hover {
            background-color: #55a858;
            transform: translateY(-2px);
        }

        /* Gaya tambahan untuk Swiper.js */
        .swiper-button-next, .swiper-button-prev {
            color: var(--primary-color) !important;
            background-color: var(--light-color) !important;
            border-radius: 50%;
            width: 44px !important;
            height: 44px !important;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .swiper-button-next:hover, .swiper-button-prev:hover {
            background-color: var(--primary-color) !important;
            color: var(--light-color) !important;
        }

        .swiper-button-next::after, .swiper-button-prev::after {
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
            background-color: rgba(0,0,0,0.4);
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
            background-color: #55a858;
        }

        /* SweetAlert2 Custom Styles */
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
            background-color: #dc3545; /* Merah */
            color: white;
            font-weight: bold;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            transition: background-color 0.3s ease;
        }

        .confirmation-cancel-btn:hover {
            background-color: #c82333; /* Merah lebih gelap */
        }

        .confirmation-confirm-btn {
            background-color: #28a745; /* Hijau */
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
    </style>

</head>
<body class="bg-black text-white">

<header class="header" id="main-header">
    <div class="header-inner container">
        <a href="#" class="logo">
            <img src="logom.png" alt="Logo">
        </a>
        <nav class="navbar-menu">
            <ul class="nav-links">
                <li><a href="#" class="nav-link">Home</a></li>
                <li><a href="#booking-section" class="nav-link">Book</a></li>
                <li><a href="#gallery-section" class="nav-link">Gallery</a></li>
                <li><a href="#map-section" class="nav-link">Contact</a></li>
            </ul>
        </nav>
    </div>
</header>

<main class="hero-section">
    <div class="banner-slideshow">
        <div class="slides">
            <div class="slide" style="background-image: url('bn2.png');"></div>
            <div class="slide" style="background-image: url('bn1.png');"></div>
            <div class="slide" style="background-image: url('galeri/b.jpeg');"></div>
        </div>
    </div>

    <div class="container hero-content">
        <div class="row justify-content-center text-center">
            <div class="col-lg-8">
                <h1 class="display-3 fw-bold mb-3">MGD Soccer Field Magelang</h1>
                <p class="fs-5 mb-4">Waktunya main! Segera booking lapangan untuk timmu</p>
                <a href="#booking-section" class="btn-book btn btn-lg fw-bold">Book Sekarang</a>
            </div>
        </div>
    </div>
</main>

<section id="booking-section" class="booking-section py-5" style="background-color: #353b48;">
    <div class="container-fluid">
        <div class="row justify-content-center" data-aos="fade-up">
            <div class="col-lg-12 col-md-12">
                <div id="calendar-container" class="shadow-lg p-md-4 p-3 rounded-3" style="background-color: #353b48;">
                    <div class="header-booking">
                        <h1>Booking <span>MGD Soccer Field Manggelang</span></h1>
                    </div>

                    <div class="container-booking" id="dailyScheduleContainer" style="max-width: 100%; flex-grow: 1;">
                        <h2 class="booking-title">Jadwal Harian</h2>
                        <p class="date-info" id="currentDateInfo">Tanggal: 08 August 2025</p>
                                            <div class="text-center d-flex flex-column items-center gap-2 mb-4">
                        <div class="date-picker-container">
                            <label for="date-input">Pilih Tanggal:</label>
                            <input type="date" id="date-input" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>
                        <div class="schedule-grid" id="schedule"></div>
                    </div>

                    
                    <div class="d-flex justify-content-center my-4 gap-2">
                        <button id="toggleWeeklyBtn" class="btn btn-primary-custom">Tampilkan Jadwal Mingguan</button>
                        <button id="toggleTutorialBtn" class="btn btn-primary-custom">Tutorial Pemesanan</button>
                    </div>
                    <div class="container-booking mt-4" id="weeklyScheduleContainer" style="display: none;">
                        <h2 class="booking-title">Jadwal Mingguan</h2>
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
                    <h3 class="text-3xl font-bold mb-4">Detail Booking</h3>
                    <div class="detail-item">
                        <strong>Nama:</strong>
                        <span id="detailNama" class="detail-value"></span>
                    </div>
                    <div class="detail-item">
                        <strong>Waktu:</strong>
                        <span id="detailWaktu" class="detail-value"></span>
                    </div>
                    <div class="detail-item">
                        <strong>Tanggal:</strong>
                        <span id="detailTanggal" class="detail-value"></span>
                    </div>
                </div>
                <button class="confirm-btn" onclick="closeModal('detailBookingModal')">OK</button>
            </div>
        </div>
        <div class="modal-booking" id="formModal">
            <div class="modal-content-booking">
                <button class="close-btn" onclick="closeModal('formModal')">×</button>
                <h3>Detail Booking</h3>
                <p>Isi data untuk konfirmasi pemesanan.</p>
                <div id="bookingSummary" class="text-left mb-4 p-4 rounded-lg" style="background-color: #1c2531; display: none;">
                    <h4 class="text-xl font-bold mb-2 text-[#ffd600]">Rincian Biaya</h4>
                    <hr class="border-[#5a6473] mb-2" />
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-[#a7b0bf]">Biaya Sewa</span>
                        <span id="biayaSewa" class="text-white font-bold">Rp0</span>
                    </div>
                    <hr class="border-[#5a6473] my-2" />
                    <div class="flex justify-between items-center">
                        <span class="text-xl font-bold">Total Bayar</span>
                        <span id="totalBayar" class="text-xl font-bold text-[#ffd600]">Rp0</span>
                    </div>
                </div>
                <div class="form-container">
                    <div class="form-group">
                        <div>
                            <label for="fullName">Nama :</label>
                            <input type="text" id="fullName" placeholder="Nama Lengkap" required />
                        </div>
                        <div>
                            <label for="phone">Nomor :</label>
                            <input type="tel" id="phone" placeholder="Nomor Telepon" required />
                        </div>
                    </div>
                    <div class="form-group">
                        <div>
                            <label for="numPlayers">Jumlah Pemain :</label>
                            <input type="number" id="numPlayers" placeholder="Misal: 10" />
                        </div>
                        <div>
                            <label for="teamName">Nama Tim :</label>
                            <input type="text" id="teamName" placeholder="Nama Tim" />
                        </div>
                    </div>
                    <div>
                        <label for="notes">Catatan :</label>
                        <textarea id="notes" rows="3" placeholder="Misal: Siapkan rompi"></textarea>
                    </div>
                    <div class="button-group">
                        <button class="cancel-btn" onclick="closeModal('formModal')">Batal</button>
                        <button class="confirm-btn" onclick="confirmBooking()">Booking Sekarang</button>
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
                <p class="text-lg text-gray-300 mb-4"> Pastikan data yang Anda isi sudah benar sebelum melanjutkan. </p>
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
                </div>
                <div class="flex justify-center gap-4 mt-4">
                    <button class="confirmation-cancel-btn" onclick="closeModal('finalConfirmationModal')">Batal</button>
                    <button class="confirmation-confirm-btn" onclick="submitBooking()">Ya, Pesanan sudah benar</button>
                </div>
            </div>
        </div>
        <div class="modal-booking" id="videoModal">
            <div class="modal-content-booking p-0 rounded-xl" style="max-width: 800px; width: 95%;">
                <div class="modal-header-video">
                    <h3 class="text-3xl font-bold text-center">Tutorial Pemesanan</h3>
                    <button class="close-btn text-white text-4xl leading-none" onclick="closeModal('videoModal')">×</button>
                </div>
                <video id="tutorialVideo" width="100%" height="auto" controls autoplay class="rounded-xl">
                    <source src="vidio.mp4" type="video/mp4"> Browser Anda tidak mendukung tag video.
                </video>
            </div>
        </div>
    </div>
</section>
<section class="about-section py-5">
    <div class="min-h-screen flex items-center px-6 py-12 relative">
        <div class="max-w-7xl w-full flex flex-col md:flex-row items-center md:items-start gap-10 md:gap-20">
            <div class="text-white max-w-xl pl-4 md:pl-12">
                <h1 class="text-[50.56px] leading-[80px] font-normal mb-6 text-shadow fw-bold" style="font-family: 'Saira', sans-serif; color: var(--usf-green);" data-aos="fade-up" data-aos-delay="100" data-aos-duration="1000"> MGD Soccer Field <span class="text-white">Magelang</span> </h1>
                <p class="text-[27px] leading-[36px] font-normal text-white mb-6 text-shadow" style="font-family: 'Lexend Deca', sans-serif;" data-aos="fade-up" data-aos-delay="300" data-aos-duration="1000"> MGD Soccer Field adalah tempat terbaik untuk komunitas pecinta sepak bola di Magelang. </p>
                <p class="text-[27px] leading-[36px] font-normal text-white text-shadow" style="font-family: 'Lexend Deca', sans-serif;" data-aos="fade-up" data-aos-delay="500" data-aos-duration="1000"> Kami hadir untuk menjaga kebersamaan, semangat, dan sportivitas. </p>
            </div>
            <div class="w-full md:w-auto" data-aos="fade-left" data-aos-duration="1600">
                <img src="min.jpg" alt="Soccer image" class="w-full max-w-[430px]" />
            </div>
        </div>
    </div>
</section>
<section id="gallery-section" class="gallery-section py-5 bg-white text-black">
    <div class="container mx-auto px-4">
        <h2 class="text-5xl font-bold text-center text-gray-800" data-aos="fade-down" style="font-family: 'Montserrat', sans-serif;">Galeri MGD Soccer Field</h2>
        <marquee behavior="scroll" direction="left" scrollamount="5" class="my-4" style="font-size: 1.1rem; color: #333;"> Momen kebersamaan, kerja tim, dan semangat sportivitas dari para pecinta bola di MGD Soccer Field. </marquee>
        <div class="swiper myGallerySwiper max-w-4xl mx-auto">
            <div class="swiper-wrapper">
                <div class="swiper-slide">
                    <div class="grid grid-cols-2 grid-rows-2 gap-4">
                        <img src="galeri/gal1.png" alt="Gallery Image 1" class="gallery-image w-full h-auto object-cover shadow-md rounded-xl">
                        <img src="galeri/gal2.png" alt="Gallery Image 2" class="gallery-image w-full h-auto object-cover shadow-md rounded-xl">
                        <img src="galeri/gal3.png" alt="Gallery Image 3" class="gallery-image w-full h-auto object-cover shadow-md rounded-xl">
                        <img src="galeri/gal4.png" alt="Gallery Image 4" class="gallery-image w-full h-auto object-cover shadow-md rounded-xl">
                    </div>
                </div>
                <div class="swiper-slide">
                    <div class="grid grid-cols-2 grid-rows-2 gap-4">
                        <img src="galeri/gal5.png" alt="Gallery Image 5" class="gallery-image w-full h-auto object-cover shadow-md rounded-xl">
                        <img src="galeri/gal6.png" alt="Gallery Image 6" class="gallery-image w-full h-auto object-cover shadow-md rounded-xl">
                        <img src="galeri/gal7.png" alt="Gallery Image 7" class="gallery-image w-full h-auto object-cover shadow-md rounded-xl">
                        <img src="galeri/gal8.png" alt="Gallery Image 8" class="gallery-image w-full h-auto object-cover shadow-md rounded-xl">
                    </div>
                </div>
            </div>
            <div class="swiper-button-next"></div>
            <div class="swiper-button-prev"></div>
            <div class="swiper-pagination"></div>
        </div>
    </div>
</section>
<section class="bg-gradient-to-r from-[#121212] px-6 py-10">
    <div class="max-w-7xl mx-auto text-white">
        <div class="flex flex-col md:flex-row justify-between text-center md:text-left items-center md:items-start space-y-8 md:space-y-0 md:space-x-12">
            <div class="flex-1 w-full" data-aos="fade-up" data-aos-duration="1000">
                <div class="text-3xl font-bold mb-4" style="font-family: 'Poppins', sans-serif;">
                    Tentang Kami
                </div>
                <p class="mb-4 text-gray-300">
                    MGD Soccer Field adalah tempat terbaik untuk komunitas pecinta sepak bola di Magelang. Dengan fasilitas berkualitas tinggi dan rumput sintetis yang terawat, kami menawarkan pengalaman bermain yang tak terlupakan.
                </p>
                <p class="text-gray-300">
                    Kami tidak hanya sekadar lapangan, tetapi juga tempat berkumpulnya para pemain yang memiliki semangat dan sportivitas.
                </p>
            </div>

            <div class="flex-1 w-full text-center" data-aos="fade-up" data-aos-duration="1000" data-aos-delay="200">
                <div class="text-3xl font-bold mb-4" style="font-family: 'Poppins', sans-serif;">
                    Kontak Kami
                </div>
                <ul class="space-y-2 list-none p-0 mx-auto hover-highlight max-w-sm">
                    <li><p class="text-white"><i class="fas fa-map-marker-alt text-lg mr-2" style="color: var(--usf-green);"></i> Salaman, Magelang, Jawa Tengah</p></li>
                    <li><p class="text-white"><i class="fab fa-instagram text-lg mr-2" style="color: var(--usf-green);"></i> <a href="https://instagram.com/mgd_soccerfield" target="_blank" class="text-white no-underline hover:text-white">@mgd_soccerfield</a></p></li>
                    <li><p class="text-white"><i class="fab fa-whatsapp text-lg mr-2" style="color: var(--usf-green);"></i> <a href="https://wa.me/6285741005741" target="_blank" class="text-white no-underline hover:text-white">0857-4100-5741</a></p></li>
                </ul>
            </div>
        </div>
    </div>
</section>
<section id="map-section" class="map-section w-full py-10 bg-[#121212]">
    <div class="container mx-auto px-4">
        <h2 class="text-4xl font-bold text-center mb-6 text-white" style="font-family: 'Montserrat', sans-serif;" data-aos="fade-down" data-aos-duration="1000">Lokasi Kami</h2>
        <div class="relative w-full h-[400px] rounded-2xl overflow-hidden shadow-2xl border-4 border-gray-800" data-aos="fade-up" data-aos-duration="1000" data-aos-delay="300">
            <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d15814.77123910543!2d110.15873970220675!3d-7.469085207907577!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e7a83d3473130c1%3A0x897914620f4c803!2sMGD%20Soccer%20Field%20(Sintetis)!5e0!3m2!1sid!2sid!4v1684992496155!5m2!1sid!2sid"
                width="100%"
                height="100%"
                style="border:0;"
                allowfullscreen=""
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade">
            </iframe>
        </div>
    </div>
</section>
<footer class="bg-black text-gray-400 py-6">
    <div class="container mx-auto text-center px-4">
        <p class="mb-2">© 2025 MGD Soccer Field. All Rights Reserved.</p>
        <p class="text-sm">Made with ❤️ by Tim MGD</p>
    </div>
</footer>

<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script>
    AOS.init();
</script>
<script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        const swiper = new Swiper('.myGallerySwiper', {
            loop: true,
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            autoplay: {
                delay: 5000,
                disableOnInteraction: false,
            },
            effect: 'fade',
            fadeEffect: {
                crossFade: true
            },
        });
    });
</script>

<script>
    let activeDay = "<?php echo date('Y-m-d'); ?>";

    const formModal = document.getElementById('formModal');
    const detailBookingModal = document.getElementById('detailBookingModal');
    const finalConfirmationModal = document.getElementById('finalConfirmationModal');
    const videoModal = document.getElementById('videoModal');
    const tutorialBtn = document.getElementById('toggleTutorialBtn');
    const tutorialVideo = document.getElementById('tutorialVideo');
    const weeklyScheduleContainer = document.getElementById('weeklyScheduleContainer');
    const toggleWeeklyBtn = document.getElementById('toggleWeeklyBtn');

    tutorialBtn.addEventListener('click', () => {
        openModal('videoModal');
    });

    // Menambahkan event listener untuk tombol toggle jadwal mingguan
    toggleWeeklyBtn.addEventListener('click', () => {
        if (weeklyScheduleContainer.style.display === 'none') {
            weeklyScheduleContainer.style.display = 'block';
            toggleWeeklyBtn.textContent = 'Sembunyikan Jadwal Mingguan';
            // Panggil fungsi untuk memuat jadwal mingguan saat ditampilkan
            fetchWeeklyBookings(activeDay);
        } else {
            weeklyScheduleContainer.style.display = 'none';
            toggleWeeklyBtn.textContent = 'Tampilkan Jadwal Mingguan';
        }
    });


    // Fungsi untuk membuka modal
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'flex';
            if (modalId === 'videoModal' && tutorialVideo) {
                tutorialVideo.play();
            }
        }
    }

    // Fungsi untuk menutup modal
    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.style.display = 'none';
            if (modalId === 'videoModal' && tutorialVideo) {
                tutorialVideo.pause();
                tutorialVideo.currentTime = 0; // Mengatur ulang video ke awal
            }
        }
    }

    function showBookingForm(time, date) {
        // --- KODE BARU UNTUK KONFIRMASI DENGAN SWEETALERT2 ---
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
                // Jika pengguna mengklik 'Ya', lanjutkan ke modal formulir booking
                
                // Tampilkan summary booking
                const bookingSummary = document.getElementById('bookingSummary');
                bookingSummary.style.display = 'block';
                document.getElementById('biayaSewa').textContent = 'Rp700.000';
                document.getElementById('totalBayar').textContent = 'Rp700.000';

                openModal('formModal');
            }
        });
    }

    function confirmBooking() {
        // Logika konfirmasi booking sebelum submit
        const fullName = document.getElementById('fullName').value;
        const phone = document.getElementById('phone').value;

        if (fullName === "" || phone === "") {
            Swal.fire({
                icon: 'error',
                title: 'Opps...',
                text: 'Nama dan Nomor Telepon harus diisi!'
            });
            return;
        }

        // Tampilkan data di modal konfirmasi
        document.getElementById('confirmNama').textContent = fullName;
        document.getElementById('confirmPhone').textContent = phone;
        document.getElementById('confirmDate').textContent = activeDay; // Menggunakan tanggal yang aktif
        // Ambil jam yang dipilih dari slot yang terakhir diklik, contoh:
        const selectedSlot = document.querySelector('.slot.selected');
        if (selectedSlot) {
             document.getElementById('confirmTime').textContent = selectedSlot.dataset.time;
        } else {
             document.getElementById('confirmTime').textContent = "Tidak ada waktu terpilih";
        }
       
        closeModal('formModal');
        openModal('finalConfirmationModal');
    }

    function submitBooking() {
        const nama = document.getElementById('fullName').value;
        const no_hp = document.getElementById('phone').value;
        const tanggal = activeDay; // Menggunakan tanggal yang aktif
        const selectedSlot = document.querySelector('.slot.selected');
        const jam = selectedSlot ? selectedSlot.dataset.time : '';
        const jumlahPemain = document.getElementById('numPlayers').value;
        const namaTim = document.getElementById('teamName').value;
        const catatan = document.getElementById('notes').value;

        if (nama === "" || no_hp === "" || tanggal === "" || jam === "") {
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: 'Silakan lengkapi semua data yang diperlukan.'
            });
            return;
        }

        // Kirim data ke server
        fetch('index.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `nama=${encodeURIComponent(nama)}&no_hp=${encodeURIComponent(no_hp)}&tanggal=${encodeURIComponent(tanggal)}&jam=${encodeURIComponent(jam)}&jumlah_pemain=${encodeURIComponent(jumlahPemain)}&nama_tim=${encodeURIComponent(namaTim)}&catatan=${encodeURIComponent(catatan)}`
        })
        .then(response => response.text())
        .then(data => {
            if (data.includes("Berhasil")) {
                Swal.fire({
                    icon: 'success',
                    title: 'Booking Berhasil!',
                    text: 'Booking Anda telah berhasil dikonfirmasi.'
                });
                closeModal('finalConfirmationModal');
                fetchDailyBookings(activeDay); // Refresh jadwal harian
                if(weeklyScheduleContainer.style.display !== 'none') {
                    fetchWeeklyBookings(activeDay); // Refresh jadwal mingguan jika sedang ditampilkan
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

    document.getElementById('date-input').addEventListener('change', function() {
        activeDay = this.value;
        fetchDailyBookings(activeDay);
        if(weeklyScheduleContainer.style.display !== 'none') {
            fetchWeeklyBookings(activeDay);
        }
    });

    function getDayName(dateString) {
        const date = new Date(dateString);
        const dayNames = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        return dayNames[date.getDay()];
    }

    function fetchDailyBookings(date) {
        const scheduleContainer = document.getElementById('schedule');
        const dateInfo = document.getElementById('currentDateInfo');
        dateInfo.textContent = `Tanggal: ${getDayName(date)}, ${new Date(date).toLocaleDateString('id-ID', {day: '2-digit', month: 'long', year: 'numeric'})}`;
        
        fetch(`index.php?get_daily_bookings=1&tanggal=${date}`)
        .then(response => response.json())
        .then(data => {
            scheduleContainer.innerHTML = '';
            const now = new Date();
            const today = now.toISOString().split('T')[0];
            const currentHour = now.getHours();
            const currentMinute = now.getMinutes();

            for (const time in data) {
                const isBooked = data[time].booked;
                const slot = document.createElement('div');
                slot.classList.add('slot');
                slot.dataset.time = time;
                
                const slotDate = new Date(`${date}T${time}:00`);
                const isPast = slotDate < now;
                
                if (isBooked) {
                    slot.classList.add('booked');
                }
                if (isPast) {
                    slot.classList.add('past-time');
                }

                const displayTime = time.substring(0, 5) + ' - ' + new Date(slotDate.getTime() + 90 * 60000).toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit'});

                let slotContent = `
                    <div class="slot-time flex-grow">${displayTime}</div>
                    <div class="slot-info">
                        <span class="slot-status-label">${isBooked ? 'Sudah Dibooking' : 'Tersedia'}</span>
                    </div>
                    <button class="slot-btn" onclick="${isBooked || isPast ? '' : `showBookingForm('${time}', '${date}')`}" ${isBooked || isPast ? 'disabled' : ''}>
                        ${isBooked ? 'Sudah Dibooking' : (isPast ? 'Waktu sudah lewat' : 'Booking')}
                    </button>
                `;

                slot.innerHTML = slotContent;
                
                if (!isBooked && !isPast) {
                    slot.addEventListener('click', () => {
                         document.querySelectorAll('.slot').forEach(s => s.classList.remove('selected'));
                         slot.classList.add('selected');
                         showBookingForm(time, date);
                    });
                }
                
                scheduleContainer.appendChild(slot);
            }
        });
    }

    function fetchWeeklyBookings(date) {
        const weeklyBody = document.getElementById('weekly-schedule-body');
        weeklyBody.innerHTML = '';
        
        // Perbaikan: Logika untuk mendapatkan tanggal Senin
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
            .then(response => response.json())
            .then(data => {
                for (const time of timeSlots) {
                    const row = document.createElement('div');
                    row.classList.add('weekly-schedule-row');
                    const timeCell = document.createElement('div');
                    timeCell.classList.add('weekly-schedule-cell', 'time-label');
                    
                    // --- KODE BARU UNTUK MENAMPILKAN RENTANG WAKTU ---
                    const slotDate = new Date(`1970-01-01T${time}:00`);
                    const displayTime = time.substring(0, 5) + ' - ' + new Date(slotDate.getTime() + 90 * 60000).toLocaleTimeString('id-ID', {hour: '2-digit', minute: '2-digit'});
                    timeCell.textContent = displayTime;
                    // --- AKHIR KODE BARU ---
                    
                    row.appendChild(timeCell);
                    
                    for (let i = 1; i <= 7; i++) {
                        const cell = document.createElement('div');
                        cell.classList.add('weekly-schedule-cell');
                        
                        // Perbaikan: Menggunakan String(i) untuk mengakses kunci data
                        const dayData = data[String(i)];
                        if (dayData && dayData[time]) {
                            cell.classList.add('booked');
                            cell.textContent = 'Booked';
                        } else {
                            cell.textContent = '-';
                        }
                        row.appendChild(cell);
                    }
                    weeklyBody.appendChild(row);
                }
            });
    }

    function showDetailBooking(tanggal, jam, nama) {
        document.getElementById('detailNama').textContent = nama;
        document.getElementById('detailWaktu').textContent = jam;
        document.getElementById('detailTanggal').textContent = tanggal;
        openModal('detailBookingModal');
    }

    // Panggil fungsi untuk pertama kali
    fetchDailyBookings(activeDay);
    
</script>
<script>
    // Hide and show header on scroll
    let lastScrollY = window.scrollY;
    const header = document.getElementById('main-header');

    window.addEventListener('scroll', () => {
        if (window.scrollY > 200) {
            if (window.scrollY > lastScrollY) {
                header.classList.add('hidden');
            } else {
                header.classList.remove('hidden');
            }
        }
        lastScrollY = window.scrollY;
    });

    // Carousel script
    const slidesContainer = document.querySelector('.slides');
    const slides = document.querySelectorAll('.slide');
    let currentSlide = 0;

    function nextSlide() {
        currentSlide = (currentSlide + 1) % slides.length;
        updateCarousel();
    }

    function updateCarousel() {
        slidesContainer.style.transform = `translateX(-${currentSlide * 100}%)`;
    }

    // Set interval for auto-play
    setInterval(nextSlide, 5000);
</script>

</body>
</html>