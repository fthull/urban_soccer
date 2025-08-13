<?php
session_start();

// Database connection
include "conn.php";
global $conn;

// --- Endpoint: Ambil semua event booking untuk FullCalendar ---
if (isset($_GET['load'])) {
    header('Content-Type: application/json');
    $events = [];
    $result = $conn->query("SELECT id, nama, waktu, status FROM booking ORDER BY waktu ASC, tanggal ASC");

    while ($row = $result->fetch_assoc()) {
        $status = $row['status'] === 'Booked' ? 'Booked' : 'Menunggu';
        $eventColor = '#dc3545';
        if (isset($row['status'])) {
            if ($row['status'] === 'Menunggu') {
                $eventColor = '#ffc107'; // Kuning
            } else if ($row['status'] === 'Booked') {
                $eventColor = '#28a745'; // Hijau
            }
        }
        $events[] = [
            'id' => $row['id'],
            'title' => htmlspecialchars($row['nama']) . ' (' . date('H:i', strtotime($row['waktu'])) . ')',
            'start' => date('Y-m-d\TH:i:s', strtotime($row['waktu'])),
            'color' => $eventColor,
            'extendedProps' => [
                'booking_id' => $row['id']
            ]
        ];
    }

    echo json_encode($events);
    exit;
}

// --- Endpoint: Ambil detail booking berdasarkan booking_id ---
if (isset($_GET['booking_id'])) {
    header('Content-Type: application/json');
    $id = intval($_GET['booking_id']);

    $stmt = $conn->prepare("SELECT id, nama, no_hp, tanggal, waktu FROM booking WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $booking = $result->fetch_assoc();
        echo json_encode(['success' => true, 'booking' => $booking]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Data booking tidak ditemukan']);
    }

    exit;
}

// ==============================================
// BAGIAN UNTUK MENAMPILKAN HALAMAN DASHBOARD
// ==============================================

// Query untuk statistik dashboard
$jumlahMenunggu = 0;
$bookedAll = 0;
$bookedToday = 0;

$stmt_stats = $conn->prepare("SELECT 
    (SELECT COUNT(*) FROM booking WHERE status = 'Menunggu') AS jumlahMenunggu,
    (SELECT COUNT(*) FROM booking WHERE status = 'Booked') AS bookedAll,
    (SELECT COUNT(*) FROM booking WHERE status = 'Booked' AND DATE(tanggal) = CURDATE()) AS bookedToday
");

if ($stmt_stats) {
    $stmt_stats->execute();
    $result_stats = $stmt_stats->get_result();
    if ($data_stats = $result_stats->fetch_assoc()) {
        $jumlahMenunggu = $data_stats['jumlahMenunggu'];
        $bookedAll = $data_stats['bookedAll'];
        $bookedToday = $data_stats['bookedToday'];
    }
    $stmt_stats->close();
}

// Perhitungan total booking
$totalBookings = $jumlahMenunggu + $bookedAll;
//Teks
$result = $conn->query("SELECT teks FROM teks");
$data = $result->fetch_assoc();
$nama = $data ? $data['teks'] : "Admin";
//Deskripsi
$result = $conn->query("SELECT * FROM deskripsi WHERE id=1");
$data = $result->fetch_assoc();
$desc = $data['deskripsi'] ?? 'Waktunya main! Segera booking lapangan untuk timmu';
//Running Teks
$query = mysqli_query($conn, "SELECT runningtext FROM runningtext LIMIT 1");
$running_text= mysqli_fetch_assoc($query);

//Fasilitas
$sql = "SELECT fasilitas1 FROM fasilitas1 WHERE id = 4";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $fasilitas1 = $row['fasilitas1'];
} else {
    $fasilitas1 = "";
}

$sql = "SELECT fasilitas2 FROM fasilitas2 WHERE id = 1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $fasilitas2 = $row['fasilitas2'];
} else {
    $fasilitas2 = "";
}

$sql = "SELECT fasilitas3 FROM fasilitas3 WHERE id = 2";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $fasilitas3 = $row['fasilitas3'];
} else {
    $fasilitas3 = "";
}

$sql = "SELECT fasilitas4 FROM fasilitas4 WHERE id = 1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $fasilitas4 = $row['fasilitas4'];
} else {
    $fasilitas4 = "";
}

$sql = "SELECT fasilitas5 FROM fasilitas5 WHERE id = 1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $fasilitas5 = $row['fasilitas5'];
} else {
    $fasilitas5 = "";
}

$sql = "SELECT fasilitas6 FROM fasilitas6 WHERE id = 1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $fasilitas6 = $row['fasilitas6'];
} else {
    $fasilitas6 = "";
}

$sql = "SELECT alamat FROM alamat WHERE id = 1";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $alamat = $row['alamat'];
} else {
    $alamat = "";
}


$conn->close();



?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin | Dashboard</title>

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

    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

    <style>
        /* Your existing CSS styles */
        .status-select {
            font-weight: bold;
            color: #ffffff;
            background-color: #3a3f4b;
            border: 1px solid #666;
        }

        /* Status Selesai */
        .status-selesai {
            color: #28d17c;
            /* hijau terang */
            background-color: #1e2f1e;
            /* hijau gelap */
            border-color: #28d17c;
        }

        /* Status Menunggu */
        .status-menunggu {
            color: #f7c948;
            /* kuning terang */
            background-color: #3a2f1e;
            /* coklat gelap */
            border-color: #f7c948;
        }

        /* Tinggi tetap untuk kalender */
        #calendar {
            height: 900px;
            overflow: hidden;
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

        /* Event item styling */
        .fc-event {
            font-size: 12px;
            padding: 2px 4px;
            margin-bottom: 2px;
            white-space: normal;
            word-break: break-word;
        }

        /* Hilangkan padding yang tidak perlu */
        .fc-daygrid-day {
            padding: 0 !important;
        }

        /* Pastikan sel tanggal memiliki tinggi yang konsisten */
        .fc-daygrid-day {
            height: 120px !important;
        }

        /* Style untuk detail status di modal */
        #detailStatus {
            padding: 3px 8px;
            border-radius: 4px;
            font-weight: bold;
        }

        .status-selesai {
            color: #28d17c;
            background-color: #1e2f1e;
        }

        .status-menunggu {
            color: #f7c948;
            background-color: #3a2f1e;
        }
    </style>
</head>

<body class="hold-transition sidebar-mini layout-fixed dark-mode">

    <div class="wrapper">
        
        <div class="preloader flex-column justify-content-center align-items-center">
            <img class="animation__wobble" src="AdminLTE-3.1.0/dist/img/logom.png" alt="AdminLTELogo" height="60" width="60">
        </div>
        <aside class="main-sidebar sidebar-dark-primary elevation-4">
            <a href="index3.html" class="brand-link">
                <img src="logom.png" alt="AdminLTE Logo" class="brand-image" style="opacity: .8">
                <span class="brand-text font-weight-light">MGD Soccer Field</span>
            </a>
            
            <div class="sidebar">
                <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                    <div class="info">
                        <a href="#" class="d-block">
                            
                            </a>
                        </div>
                    </div>
                    
                    <div class="form-inline">
                        <div class="input-group" data-widget="sidebar-search">
                            <input class="form-control form-control-sidebar" type="search" placeholder="Search" aria-label="Search">
                            <div class="input-group-append">
                                <button class="btn btn-sidebar">
                                    <i class="fas fa-search fa-fw"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <nav class="mt-2">
                        <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                            <li class="nav-item">
                                <li class="nav-item">
                                    <a href="admin.php" class="nav-link">
                                        <i class="far fa-circle nav-icon"></i>
                                        <p>Dashboard</p>
                                    </a>
                                </li>
                            </li>
                            
                            <li class="nav-item">
                                <a href="tab_booking.php" class="nav-link">
                                    <i class="nav-icon fas fa-th"></i>
                                    <p>
                                        Booking
                                    </p>
                                </a>
                            </li>
                            
                            <li class="nav-item">
                                <a href="history.php" class="nav-link">
                                    <i class="nav-icon fas fa-chart-pie"></i>
                                    <p>
                                        Histori
                                    </p>
                                </a>
                            </li>
                            
                            <li class="nav-item">
                                <a href="update_web.php" class="nav-link active">
                                    <i class="nav-icon fas fa-desktop"></i>
                                    <p>
                                        Web Update
                                    </p>
                                </a>
                            </li>
                            
                            <li class="nav-item">
                                <a href="logout.php" class="nav-link">
                                    <i class="nav-icon fas fa-sign-out-alt"></i>
                                    <p>Logout</p>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </aside>
            
            <div class="content-wrapper">
                <section class="content">
                    <div class="container-fluid">

                    
            <?php
// Bagian PHP dari file booking
include 'conn.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_GET['change'])) {
    $nama = $_POST['nama'] ?? '';
    $no_hp = $_POST['no_hp'] ?? '';
    $tanggal = $_POST['tanggal'] ?? '';
    $jam = $_POST['jam'] ?? '';
    
    $waktu_penuh = $tanggal . ' ' . $jam . ':00';
    
    // Cek bentrok
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
    
    $stmt = $conn->prepare("INSERT INTO booking (nama, no_hp, tanggal, waktu) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $nama, $no_hp, $tanggal, $waktu_penuh);
    
    if ($stmt->execute()) {
        echo "Berhasil";
    } else {
        echo "Gagal";
    }
    
    exit;
}

// Data kalender
$events = [];
$result = $conn->query("SELECT nama, waktu, status FROM booking where waktu >= now() ORDER BY waktu ASC");

$events = [];
while ($row = $result->fetch_assoc()) {
    $status = $row['status'] === 'Booked' ? 'Booked' : 'Process';
    $color = $status === 'Booked' ? '#28a745' : '#ffc107';
    
    $events[] = [
        'title' => $status . ': ' . ' (' . date('H:i', strtotime($row['waktu'])) . ')',
        'start' => date('c', strtotime($row['waktu'])), // Format ISO 8601
        'allDay' => false,
        'color' => $color
    ];
}


if (isset($_GET['get_booked_times']) && isset($_GET['tanggal'])) {
    $tanggal = $_GET['tanggal'];
    $booked = [];
    
    $stmt = $conn->prepare("SELECT waktu FROM booking WHERE DATE(waktu) = ?");
    $stmt->bind_param("s", $tanggal);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $booked[] = date('H:i', strtotime($row['waktu']));
    }
    
    echo json_encode($booked);
    exit;
}

// Ubah booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['change'])) {
    $nama = $_POST['nama'] ?? '';
    $no_hp = $_POST['no_hp'] ?? '';
    $tanggal = $_POST['tanggal'] ?? '';
    $jam = $_POST['jam'] ?? '';
    $waktu_baru = $tanggal . ' ' . $jam . ':00';
    
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
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" />
        <script src="https://cdn.tailwindcss.com"></script>
        <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet" />
        <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css" />
        
        <link rel="stylesheet" href="style.css">
        
        <style>
            :root {
                --usf-green: #388E3C;
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
                    font-size: 10px; /* Ukuran font diperkecil untuk event */
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
        <div class="banner-slideshow" onclick="editBackground">
            <div class="slides" >
                <div class="slide" style="background-image: url('bn2.png');"></div>
                <div class="slide" style="background-image: url('bn1.png');"></div>
                <div class="slide" style="background-image: url('<?= htmlspecialchars($bg) ?>');"></div>
            </div>
        </div>
            <div class="menu-item" >
        <div class="container hero-content">
            <div class="row justify-content-center text-center">
                <div class="col-lg-8" >
                    
                    <h1 class="display-3 fw-bold mb-3" onclick="changeFile()" id= nama><?= htmlspecialchars($nama) ?></h1>
                    <p class="fs-5 mb-4"><p onclick="editDeskripsi()" id="deskripsi"><?= htmlspecialchars($desc) ?></p></p>
                    <a href="#booking-section" class="btn-book btn btn-lg fw-bold">Book Sekarang</a>
                </div>
            </div>
        </div>
         </div>
    </main>
    
    <section id="booking-section" class="booking-section py-5 bg-white text-black">
        <div class="container">
            <h2 class="section-title text-center mb-5 text-5xl font-bold text-center text-gray-800" data-aos="fade-down" style="font-family: 'Montserrat', sans-serif;">Booking Lapangan</h2>
            <div class="row justify-content-center" data-aos="fade-up">
                <div class="col-lg-9 col-md-10">
                    <div id="calendar-container" class="shadow-lg p-md-4 p-3 rounded-3 bg-white">
                        <div id="calendar"></div>
                    </div>
                    <div class="text-center mt-4">
                        <button onclick="openChangeModal()" class="btn-primary-custom">Ubah Booking</button>
                    </div>
                </div>
            </div>
            <div id="bookingModal" class="modal">
                <div class="modal-content text-center bg-white p-6 rounded-lg shadow-xl">
                    <h4 class="text-2xl font-bold mb-4 text-usf-green">Booking Urban Soccer</h4>
                    <form id="bookingForm" class="space-y-4">
                        <input type="hidden" id="tanggal" name="tanggal" />
                        <div><label class="block text-left font-semibold">Nama:</label><input class="w-full p-2 border rounded-md" type="text" name="nama" required></div>
                        <div><label class="block text-left font-semibold">No HP:</label><input class="w-full p-2 border rounded-md" type="text" name="no_hp" required></div>
                        <div>
                            <label class="block text-left font-semibold">Jam:</label>
                            <select class="w-full p-2 border rounded-md" name="jam" id="jamDropdown" required></select>
                        </div>
                        <div class="mt-4 flex justify-end gap-2">
                            <button class="bg-gray-300 text-black px-4 py-2 rounded-md" type="button" onclick="closeModal()">Batal</button>
                            <button class="bg-usf-green text-white px-4 py-2 rounded-md font-semibold" type="submit">Simpan</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <div id="changeModal" class="modal">
                <div class="modal-content bg-white p-6 rounded-lg shadow-xl">
                    <h4 class="text-2xl font-bold mb-4 text-usf-green">Ubah Booking</h4>
                    <form id="changeForm" class="space-y-4">
                        <div><label class="block text-left font-semibold">Nama:</label><input class="w-full p-2 border rounded-md" type="text" name="nama" required></div>
                        <div><label class="block text-left font-semibold">No HP:</label><input class="w-full p-2 border rounded-md" type="text" name="no_hp" required></div>
                        <div><label class="block text-left font-semibold">Tanggal Baru:</label><input class="w-full p-2 border rounded-md" type="date" name="tanggal" required></div>
                        <div>
                            <label>Jam Baru:</label>
                            <select name="jam" id="jamDropdownChange" required>
                                
                                </select>
                            </div>
                            <div class="mt-4 flex justify-end gap-2">
                                <button class="bg-gray-300 text-black px-4 py-2 rounded-md" type="button" onclick="closeChangeModal()">Batal</button>
                                <button class="bg-usf-green text-white px-4 py-2 rounded-md font-semibold" type="submit">Update Booking</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
        
        <section class="about-section py-5">
            <div class="min-h-screen flex items-center px-6 py-12 relative" >
                <div class="max-w-7xl w-full flex flex-col md:flex-row items-center md:items-start gap-10 md:gap-20">
                    <div class="text-white max-w-xl pl-4 md:pl-12">
                        <h1 class="text-[50.56px] leading-[80px] font-normal mb-6 text-shadow fw-bold"
                        style="font-family: 'Saira', sans-serif; color: #5fa140ff;"
                        data-aos="fade-up" data-aos-delay="100" data-aos-duration="1000">
                        MGD Soccer Field
                        <span class="text-white">Magelang</span>
                    </h1>
                    <p class="text-[27px] leading-[36px] font-normal text-white mb-6 text-shadow"
                    style="font-family: 'Lexend Deca', sans-serif;"
                    data-aos="fade-up" data-aos-delay="300" data-aos-duration="1000">
                    MGD Soccer Field Magelang hadir sebagai wadah bagi setiap komunitas pecinta sepak bola yang ingin merasakan sensasi bermain dengan kualitas terbaik dan suasana menyenangkan.
                </p>
                
                <p class="text-[27px] leading-[36px] font-normal text-white text-shadow"
                style="font-family: 'Lexend Deca', sans-serif;"
                data-aos="fade-up" data-aos-delay="500" data-aos-duration="1000">
                Kami yakin bahwa sepak bola bukan hanya tentang mencetak gol,
                tapi juga tentang menjaga kebersamaan, tawa, dan semangat sportifitas.
            </p>
        </div>
        
        <div class="w-full md:w-auto"
        data-aos="fade-left" data-aos-duration="1600">
        <img src="min.jpg" alt="Soccer image" class="w-full max-w-[430px]" />
    </div>
</div>
</div>
</section>

<section id="gallery-section" class="gallery-section py-5 bg-white text-black">
    <div class="container mx-auto px-4">
        <h2 class="text-5xl font-bold text-center text-gray-800" data-aos="fade-down" style="font-family: 'Montserrat', sans-serif;">Galeri MGD Soccer Field</h2>
        <marquee id="runningtext" 
         behavior="scroll" 
         direction="left" 
         scrollamount="5" 
         class="my-4" 
         style="font-size: 1.1rem; color: #333; cursor: pointer;" 
         onclick="editRunningText()">
    <?php echo htmlspecialchars($running_text['runningtext']); ?>
</marquee>
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

<section class="bg-gradient-to-r from-[#121212]  px-6 py-10">
    <div class="max-w-7xl mx-auto text-white">
        <div class="flex flex-col md:flex-row justify-between text-center md:text-left items-center md:items-start gap-10">
            <div class="flex-1 border-r border-gray-600 px-12">
                <ul class="list-disc list-inside space-y-3 text-left">
                    <li class="text-lg font-semibold"><p onclick="editFasilitas1()" id="fasilitas1"><?= htmlspecialchars($fasilitas1) ?></p></li>
                    <li class="text-lg font-semibold"><p onclick="editFasilitas2()" id="fasilitas2"><?= htmlspecialchars($fasilitas2) ?></p></li>
                    <li class="text-lg font-semibold"><p onclick="editFasilitas3()" id="fasilitas3"><?= htmlspecialchars($fasilitas3) ?></p></li>
                </ul>
            </div>
            <div class="flex-1 border-r border-gray-600 px-12">
                <ul class="list-disc list-inside space-y-3 text-left">
                    <li class="text-lg font-semibold"><p onclick="editFasilitas4()" id="fasilitas4"><?= htmlspecialchars($fasilitas4) ?></p></li>
                    <li class="text-lg font-semibold"><p onclick="editFasilitas5()" id="fasilitas5"><?= htmlspecialchars($fasilitas5) ?></p></li>
                    <li class="text-lg font-semibold"><p onclick="editFasilitas6()" id="fasilitas6"><?= htmlspecialchars($fasilitas6) ?></p></li>
                </ul>
            </div>
            <div class="flex-1 flex flex-col items-center justify-center text-center px-12">
                <i class="fas fa-map-marker-alt text-4xl mb-2 text-white"></i>
                <p class="text-lg font-semibold"><p onclick="editAlamat()" id="alamat"><?= htmlspecialchars($alamat) ?></p>
            </div>
        </div>
        
        <div class="flex justify-start px-8 my-6 justify-content-center">
            <h2 class="text-white text-5xl font-bold text-center" data-aos="fade-down" style="font-family: 'Montserrat', sans-serif;">Program Spesial MGD</h2>
        </div>
        
        <div class="flex flex-col md:flex-row gap-6 my-10 justify-between">
            <div class="relative w-full md:w-1/3">
                <img src="pemain.png" alt="Private Event" class="w-full h-auto object-cover rounded-lg">
                <div class="absolute inset-x-0 bottom-0 p-2 bg-black/70 rounded-b-lg">
                    <p class="text-white text-2xl font-bold text-center">Reward Pemain Terbaik</p>
                </div>
            </div>
            <div class="relative w-full md:w-1/3">
                <img src="pelajar.png" alt="Rent a Field" class="w-full h-auto object-cover rounded-lg">
                <div class="absolute inset-x-0 bottom-0 p-2 bg-black/70 rounded-b-lg">
                    <p class="text-white text-2xl font-bold text-center">Diskon Khusus Pelajar</p>
                </div>
            </div>
            <div class="relative w-full md:w-1/3">
                <img src="sewa.png" alt="Open Play" class="w-full h-auto object-cover rounded-lg">
                <div class="absolute inset-x-0 bottom-0 p-2 bg-black/70 rounded-b-lg">
                    <p class="text-white text-2xl font-bold text-center">Sewa Sepatu Gratis</p>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="map-section" class="map-section py-5 bg-white text-black">
    <h4 class="text-center section-title mb-4">Lokasi Kami</h4>
    <div class="d-flex justify-content-center mt-4">
        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3955.6662217098583!2d110.2217516748534!3d-7.502051574001483!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e7a8f1c869c6d39%3A0x7ea03e5884a0b39b!2sMGD%20Mini%20Soccer%20Magelang!5e0!3m2!1sid!2sid!4v1754453314773!5m2!1sid!2sid" width="80%" height="360" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
        </div>
    </section>
    
    <footer class="relative overflow-hidden mt-16 bg-black">
        <div class="relative max-w-7xl mx-auto px-6 py-16 flex flex-col items-center md:flex-row md:justify-center md:items-start gap-12 md:gap-24">
            <div class="flex flex-col items-center md:items-center max-w-md md:max-w-none text-center">
                <img
                alt="USF Urban Soccer Field logo"
                class="w-63 md:w-72 mx-auto"
                src="logom.png"
                />
            </div>
            
            <div class="flex flex-col md:flex-row md:gap-24 text-lg">
                <div>
                    <h3 class="text-[#5fa140ff] font-extrabold text-xl mb-4">Site</h3>
                    <ul class="space-y-2 font-normal hover-highlight">
                        <li>Booking Lapangan</li>
                        <li>Galeri MGD Soccer Field</li>
                        <li>Program Spesial MGD</li>
                        <li>Lokasi MGD Soccer</li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-[#5fa140ff] font-extrabold text-xl mb-4">Contact</h3>
                    <address class="not-italic space-y-3 font-normal hover-highlight">
                        <p>Jl. Soekarno Hatta No.5, Magelang</p>
                        <p>+62 811 2653 988</p>
                        <p>MGD Soccer Field Magelang</p>
                    </address>
                    <ul class="flex space-x-6 mt-4 text-white">
                        <li>
                            <a aria-label="YouTube" class="hover:text-[#5fa140ff)] social-icon" href="https://www.youtube.com/@urbAnsoccerfield">
                                <i class="fab fa-youtube"></i>
                            </a>
                        </li>
                        <li>
                            <a aria-label="Instagram" class="hover:text-[#5fa140ff)] social-icon" href="https://www.instagram.com/mgdsoccerfield/">
                                <i class="fab fa-instagram"></i>
                            </a>
                        </li>
                        <li>
                            <a aria-label="WhatsApp"
                            class="hover:text-[#5fa140ff]"
                            href="https://wa.me/628112653988?text=Halo%20Urban%20Soccer%20Field%2C%20saya%20mau%20booking%20lapangan."
                            target="_blank" rel="noopener noreferrer">
                            <i class="fab fa-whatsapp social-icon"></i>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</footer>

<div class="text-center text-gray-300 text-sm py-4 border-t border-gray-800 font-normal">
    ©2025 By
    <a class="text-[#5fa140ff] hover:underline" href="#">MGD Soccer Field</a> <span class="text-red-600">❤️</span>
    <a class="text-[#5fa140ff] hover:underline" href="#">Magelang</a>
</div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
<script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</body>
</html>

</div>
                </section>
            </div>









        <footer class="main-footer">
            <strong>Copyright &copy; 2014-2021 <a href="https://adminlte.io">AdminLTE.io</a>.</strong>
            All rights reserved.
            <div class="float-right d-none d-sm-inline-block">
                <b>Version</b> 3.1.0
            </div>
        </footer>

        <aside class="control-sidebar control-sidebar-dark">
        </aside>
    </div>
    <script src="AdminLTE-3.1.0/plugins/jquery/jquery.min.js"></script>
    <script src="AdminLTE-3.1.0/plugins/jquery-ui/jquery-ui.min.js"></script>
    <script>
        $.widget.bridge('uibutton', $.ui.button)
    </script>
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
        window.addEventListener('load', function() {
            const preloader = document.querySelector('.preloader');
            if (preloader) {
                preloader.style.display = 'none';
            }
        });

        const logo = document.getElementById("admin-logo");
    const menu = document.getElementById("admin-menu");

    // Toggle menu saat klik logo
    logo.addEventListener("click", () => {
      menu.style.display = menu.style.display === "none" ? "block" : "none";
    });

    // Fungsi fitur
    function changeFile() {
            Swal.fire({
                title: 'Ganti Nama',
                input: 'text',
                inputLabel: 'Masukkan nama baru',
                inputPlaceholder: 'Nama baru...',
                showCancelButton: true,
                confirmButtonText: 'Simpan',
                cancelButtonText: 'Batal',
                inputValidator: (value) => {
                    if (!value) {
                        return 'Nama tidak boleh kosong!';
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    let namaBaru = result.value;

                    fetch("simpan_nama.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded"
                        },
                        body: "nama=" + encodeURIComponent(namaBaru)
                    })
                    .then(response => response.text())
                    .then(data => {
                        Swal.fire('Berhasil!', data, 'success');
                        document.getElementById("nama").innerText = namaBaru;
                    })
                    .catch(error => {
                        Swal.fire('Error', 'Terjadi kesalahan: ' + error, 'error');
                    });
                }
            });
        }
        

    function editDeskripsi() {
      Swal.fire({
                title: 'Edit Deskripsi',
                html:
'<textarea id="inputDeskripsi" class="swal2-textarea" placeholder="Deskripsi">' + 
                    document.getElementById("deskripsi").innerText + '</textarea>',
                focusConfirm: false,
                showCancelButton: true,
                confirmButtonText: 'Simpan',
                cancelButtonText: 'Batal',
                preConfirm: () => {
                    let deskripsi = document.getElementById('inputDeskripsi').value.trim();
                    if (!deskripsi) {
                        Swal.showValidationMessage('Deskripsi tidak boleh kosong!');
                        return false;
                    }
                    return { deskripsi: deskripsi };
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch("simpan_deskripsi.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/x-www-form-urlencoded" },
                        body: "deskripsi=" + encodeURIComponent(result.value.deskripsi)
                    })
                    .then(res => res.text())
                    .then(msg => {
                        Swal.fire('Berhasil!', msg, 'success');
                        document.getElementById("deskripsi").innerText = result.value.deskripsi;
                    });
                }
            });
        }

    function editRunningText() {
    Swal.fire({
        title: 'Edit Running Text',
        html:
        '<textarea id="inputRunning" class="swal2-textarea">' +
        document.getElementById("runningtext").innerText + '</textarea>',
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Simpan',
        cancelButtonText: 'Batal',
        preConfirm: () => {
            let running = document.getElementById('inputRunning').value.trim();
            if (!running) {
                Swal.showValidationMessage('Running text tidak boleh kosong!');
                return false;
            }
            return { running: running };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            fetch("simpan_runningteks.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "runningtext=" + encodeURIComponent(result.value.running)
            })
            .then(res => res.text())
            .then(msg => {
                Swal.fire('Berhasil!', msg, 'success');
                document.getElementById("runningtext").innerText = result.value.running;
            });
        }
    });
}

    function editFasilitas1() {
    let fasilitasText = document.getElementById("fasilitas1").innerText;

    Swal.fire({
        title: 'Edit Fasilitas',
        html:
        '<textarea id="inputFasilitas" class="swal2-textarea">' +
        fasilitasText + '</textarea>',
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Simpan',
        cancelButtonText: 'Batal',
        preConfirm: () => {
            let fasilitas = document.getElementById('inputFasilitas').value.trim();
            if (!fasilitas) {
                Swal.showValidationMessage('Teks fasilitas tidak boleh kosong!');
                return false;
            }
            return { fasilitas: fasilitas };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            fetch("simpan_fasilitas1.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "fasilitas1" + encodeURIComponent(result.value.fasilitas)
            })
            .then(res => res.text())
            .then(msg => {
                Swal.fire('Berhasil!', msg, 'success');
                document.getElementById("fasilitas1").innerText = result.value.fasilitas;
            });
        }
    });
}


    function editFasilitas2() {
    let fasilitasText = document.getElementById("fasilitas2").innerText;

    Swal.fire({
        title: 'Edit Fasilitas',
        html:
        '<textarea id="inputFasilitas" class="swal2-textarea">' +
        fasilitasText + '</textarea>',
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Simpan',
        cancelButtonText: 'Batal',
        preConfirm: () => {
            let fasilitas = document.getElementById('inputFasilitas').value.trim();
            if (!fasilitas) {
                Swal.showValidationMessage('Teks fasilitas tidak boleh kosong!');
                return false;
            }
            return { fasilitas: fasilitas };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            fetch("simpan_fasilitas2.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "fasilitas1" + encodeURIComponent(result.value.fasilitas)
            })
            .then(res => res.text())
            .then(msg => {
                Swal.fire('Berhasil!', msg, 'success');
                document.getElementById("fasilitas2").innerText = result.value.fasilitas;
            });
        }
    });
}


    function editFasilitas3() {
    let fasilitasText = document.getElementById("fasilitas3").innerText;

    Swal.fire({
        title: 'Edit Fasilitas',
        html:
        '<textarea id="inputFasilitas" class="swal2-textarea">' +
        fasilitasText + '</textarea>',
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Simpan',
        cancelButtonText: 'Batal',
        preConfirm: () => {
            let fasilitas = document.getElementById('inputFasilitas').value.trim();
            if (!fasilitas) {
                Swal.showValidationMessage('Teks fasilitas tidak boleh kosong!');
                return false;
            }
            return { fasilitas: fasilitas };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            fetch("simpan_fasilitas3.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "fasilitas3" + encodeURIComponent(result.value.fasilitas)
            })
            .then(res => res.text())
            .then(msg => {
                Swal.fire('Berhasil!', msg, 'success');
                document.getElementById("fasilitas3").innerText = result.value.fasilitas;
            });
        }
    });
}

    function editFasilitas4() {
    let fasilitasText = document.getElementById("fasilitas4").innerText;

    Swal.fire({
        title: 'Edit Fasilitas',
        html:
        '<textarea id="inputFasilitas" class="swal2-textarea">' +
        fasilitasText + '</textarea>',
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Simpan',
        cancelButtonText: 'Batal',
        preConfirm: () => {
            let fasilitas = document.getElementById('inputFasilitas').value.trim();
            if (!fasilitas) {
                Swal.showValidationMessage('Teks fasilitas tidak boleh kosong!');
                return false;
            }
            return { fasilitas: fasilitas };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            fetch("simpan_fasilitas4.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "fasilitas4" + encodeURIComponent(result.value.fasilitas)
            })
            .then(res => res.text())
            .then(msg => {
                Swal.fire('Berhasil!', msg, 'success');
                document.getElementById("fasilitas4").innerText = result.value.fasilitas;
            });
        }
    });
}

    function editFasilitas5() {
    let fasilitasText = document.getElementById("fasilitas5").innerText;

    Swal.fire({
        title: 'Edit Fasilitas',
        html:
        '<textarea id="inputFasilitas" class="swal2-textarea">' +
        fasilitasText + '</textarea>',
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Simpan',
        cancelButtonText: 'Batal',
        preConfirm: () => {
            let fasilitas = document.getElementById('inputFasilitas').value.trim();
            if (!fasilitas) {
                Swal.showValidationMessage('Teks fasilitas tidak boleh kosong!');
                return false;
            }
            return { fasilitas: fasilitas };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            fetch("simpan_fasilitas5.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "fasilitas5" + encodeURIComponent(result.value.fasilitas)
            })
            .then(res => res.text())
            .then(msg => {
                Swal.fire('Berhasil!', msg, 'success');
                document.getElementById("fasilitas5").innerText = result.value.fasilitas;
            });
        }
    });
}

    function editFasilitas6() {
    let fasilitasText = document.getElementById("fasilitas6").innerText;

    Swal.fire({
        title: 'Edit Fasilitas',
        html:
        '<textarea id="inputFasilitas" class="swal2-textarea">' +
        fasilitasText + '</textarea>',
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Simpan',
        cancelButtonText: 'Batal',
        preConfirm: () => {
            let fasilitas = document.getElementById('inputFasilitas').value.trim();
            if (!fasilitas) {
                Swal.showValidationMessage('Teks fasilitas tidak boleh kosong!');
                return false;
            }
            return { fasilitas: fasilitas };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            fetch("simpan_fasilitas6.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "fasilitas6" + encodeURIComponent(result.value.fasilitas)
            })
            .then(res => res.text())
            .then(msg => {
                Swal.fire('Berhasil!', msg, 'success');
                document.getElementById("fasilitas6").innerText = result.value.fasilitas;
            });
        }
    });
}

    function editAlamat() {
    let alamatText = document.getElementById("alamat").innerHTML.replace(/<br\s*\/?>/g, "\n");

    Swal.fire({
        title: 'Edit Alamat',
        html:
        '<textarea id="inputAlamat" class="swal2-textarea" style="height:80px;">' +
        alamatText + '</textarea>',
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Simpan',
        cancelButtonText: 'Batal',
        preConfirm: () => {
            let alamat = document.getElementById('inputAlamat').value.trim();
            if (!alamat) {
                Swal.showValidationMessage('Alamat tidak boleh kosong!');
                return false;
            }
            return { alamat: alamat };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Di sini kalau mau simpan ke database, kirim via fetch/ajax
            document.getElementById("alamat").innerHTML = result.value.alamat.replace(/\n/g, "<br>");
            Swal.fire('Berhasil!', 'Alamat berhasil diperbarui.', 'success');
        }
    });
}

    function logout() {
      alert("Anda keluar sebagai admin.");
      menu.style.display = "none";
    }

        document.addEventListener("DOMContentLoaded", function() {
            var calendarEl = document.getElementById("calendar");

            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: "dayGridMonth",
                events: "admin.php?load=1",
                dateClick: function(info) {
                    const tanggal = info.dateStr;
                    document.getElementById("tanggal").value = tanggal;
                    if (document.getElementById("bookingModal")) {
                        document.getElementById("bookingModal").style.display = "flex";
                    }
                    if (typeof loadJamOptions === "function") {
                        loadJamOptions(tanggal);
                    }
                },
                eventClick: function(info) {
                    const bookingId = info.event.extendedProps.booking_id;

                    fetch(`admin.php?booking_id=${bookingId}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const booking = data.booking;

                                $('#bookingModal .modal-body').html(`
                                    <strong>Nama:</strong> ${booking.nama}<br>
                                    <strong>No HP:</strong> ${booking.no_hp}<br>
                                    <strong>Tanggal:</strong> ${booking.tanggal.split(' ')[0]}<br>
                                    <strong>Jam:</strong> ${booking.waktu.split(' ')[1].substring(0,5)}<br>
                                `);
                                $('#bookingModalLabel').text('Detail Booking');
                                $('#bookingModal').modal('show');
                            } else {
                                alert('Booking tidak ditemukan.');
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            alert('Gagal memuat data booking.');
                        });
                }

            });

            calendar.render();
        });

        // TUTUP MODAL DETAIL FUNCTION
        function tutupDetailModal() {
            document.getElementById("bookingDetailModal").style.display = "none";
        }
    </script>

</body>

</html>