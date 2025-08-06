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
    <title>URBAN SOCCER FIELD</title>

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

        /* Calendar Styles */
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
                        <button class="bg-usf-green text-black px-4 py-2 rounded-md font-semibold" type="submit">Simpan</button>
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
                        <button class="bg-usf-green text-black px-4 py-2 rounded-md font-semibold" type="submit">Update Booking</button>
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
        <marquee behavior="scroll" direction="left" scrollamount="5" class="my-4" style="font-size: 1.1rem; color: #333;">
            Lebih dari sekadar lapangan, Urban Soccer Field adalah ruang untuk mencipta kenangan. Galeri ini memperlihatkan momen kebersamaan, kerja tim, dan semangat sportivitas dari para pecinta bola.
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
                    <li class="text-lg font-semibold">Lapangan ukuran 55 x 22 m</li>
                    <li class="text-lg font-semibold">Lampu penerangan</li>
                    <li class="text-lg font-semibold">Rumput sintetis Fifa Standar</li>
                </ul>
            </div>
            <div class="flex-1 border-r border-gray-600 px-12">
                <ul class="list-disc list-inside space-y-3 text-left">
                    <li class="text-lg font-semibold">Kamar Mandi</li>
                    <li class="text-lg font-semibold">Cafe and Mushola</li>
                    <li class="text-lg font-semibold">Parkir</li>
                </ul>
            </div>
            <div class="flex-1 flex flex-col items-center justify-center text-center px-12">
                <i class="fas fa-map-marker-alt text-4xl mb-2 text-white"></i>
                <p class="text-lg font-semibold">Jl. Soekarno Hatta No.5</p>
                <p class="text-lg font-semibold">Magelang</p>
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
<script>
    AOS.init();

    // JS dari file navbar/hero
    const sliderWrapper = document.querySelector('.banner-slideshow .slides');
    const slideItems = document.querySelectorAll('.banner-slideshow .slide');
    const totalSlides = slideItems.length;
    let currentIndex = 0;

    function updateSlider() {
        if (slideItems.length > 0) {
            const slideWidth = sliderWrapper.parentElement.offsetWidth;
            sliderWrapper.style.transform = `translateX(${-currentIndex * slideWidth}px)`;
        }
    }

    function slide() {
        currentIndex++;
        if (currentIndex >= totalSlides) {
            currentIndex = 0;
        }
        updateSlider();
    }

    window.addEventListener('resize', updateSlider);
    updateSlider();
    setInterval(slide, 5000);

    const header = document.querySelector('.header');
    let lastScrollY = window.scrollY;

    window.addEventListener('scroll', () => {
        if (window.scrollY > 100) {
            header.classList.add('hidden');
        } else {
            header.classList.remove('hidden');
        }
    });

    // JS dari file booking
    document.addEventListener("DOMContentLoaded", function () {
        const calendarEl = document.getElementById("calendar");
        const bookedEvents = <?php echo json_encode($events); ?>;

        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: "dayGridMonth",
            displayEventTime: false,
            selectable: true,
            events: bookedEvents,
            height: 'auto',
            dateClick: function(info) {
                const tanggal = info.dateStr;
                document.getElementById("tanggal").value = tanggal;
                document.getElementById("bookingModal").style.display = "flex";
                loadJamOptions(tanggal);
            },
            headerToolbar: {
                left: 'prev',
                center: 'title',
                right: 'next'
            },
            dayCellContent: function(info) {
                return info.dayNumberText;
            }
        });

        calendar.render();
    });

    function closeModal() {
        document.getElementById("bookingModal").style.display = "none";
    }

    function openChangeModal() {
        document.getElementById("changeModal").style.display = "flex";
    }

    function closeChangeModal() {
        document.getElementById("changeModal").style.display = "none";
    }

    function loadJamOptions(tanggal) {
  const dropdown = document.getElementById("jamDropdown");
  dropdown.innerHTML = ""; // Kosongkan dulu

  fetch("?get_booked_times=1&tanggal=" + tanggal)
    .then(res => res.json())
    .then(booked => {
      // Sort booked times just in case they're not in order
      booked.sort();
      
      const semuaJam = generateJamOptions("07:00", "24:00", 120); // 120 minutes = 2 hours
      const availableSlots = [];
      
      // Generate available time slots with their end times
      for (let i = 0; i < semuaJam.length; i++) {
        const startTime = semuaJam[i];
        
        // Skip if this time is booked
        if (booked.includes(startTime)) continue;
        
        // Calculate end time (10 minutes before next slot or closing time)
        let endTime;
        if (i < semuaJam.length - 1) {
          // Parse next time to calculate 10 minutes before it
          const nextTime = semuaJam[i+1];
          const [nextH, nextM] = nextTime.split(":").map(Number);
          const nextDate = new Date(0, 0, 0, nextH, nextM);
          nextDate.setMinutes(nextDate.getMinutes() - 10); // 10 minutes before
          
          endTime = 
            String(nextDate.getHours()).padStart(2, '0') + ":" + 
            String(nextDate.getMinutes()).padStart(2, '0');
        } else {
          // For the last slot, end time is 10 minutes before closing (23:00)
          endTime = "00:50";
        }
        
        availableSlots.push({
          start: startTime,
          end: endTime
        });
      }

      // Populate dropdown with formatted options
      availableSlots.forEach(slot => {
        const option = document.createElement("option");
        option.value = slot.start;
        option.textContent = `${slot.start} - ${slot.end}`;
        dropdown.appendChild(option);
      });

      if (dropdown.options.length === 0) {
        const option = document.createElement("option");
        option.text = "Tidak ada jam tersedia";
        option.disabled = true;
        dropdown.appendChild(option);
      }
    });}
  const dropdown = document.getElementById("jamDropdownChange");
  dropdown.innerHTML = ""; // Kosongkan dulu

  fetch("?get_booked_times=1&tanggal=" + tanggal)
    .then(res => res.json())
    .then(booked => {
      // Sort booked times just in case they're not in order
      booked.sort();
      
      const semuaJam = generateJamOptions("07:00", "24:00", 120); // 120 minutes = 2 hours
      const availableSlots = [];
      
      // Generate available time slots with their end times
      for (let i = 0; i < semuaJam.length; i++) {
        const startTime = semuaJam[i];
        
        // Skip if this time is booked
        if (booked.includes(startTime)) continue;
        
        // Calculate end time (10 minutes before next slot or closing time)
        let endTime;
        if (i < semuaJam.length - 1) {
          // Parse next time to calculate 10 minutes before it
          const nextTime = semuaJam[i+1];
          const [nextH, nextM] = nextTime.split(":").map(Number);
          const nextDate = new Date(0, 0, 0, nextH, nextM);
          nextDate.setMinutes(nextDate.getMinutes() - 10); // 10 minutes before
          
          endTime = 
            String(nextDate.getHours()).padStart(2, '0') + ":" + 
            String(nextDate.getMinutes()).padStart(2, '0');
        } else {
          // For the last slot, end time is 10 minutes before closing (23:00)
          endTime = "00:50";
        }
        
        availableSlots.push({
          start: startTime,
          end: endTime
        });
      }

      // Populate dropdown with formatted options
      availableSlots.forEach(slot => {
        const option = document.createElement("option");
        option.value = slot.start;
        option.textContent = `${slot.start} - ${slot.end}`;
        dropdown.appendChild(option);
      });

      if (dropdown.options.length === 0) {
        const option = document.createElement("option");
        option.text = "Tidak ada jam tersedia";
        option.disabled = true;
        dropdown.appendChild(option);
      }
    });


// Helper function to generate time options (unchanged)
function generateJamOptions(start, end, stepMinutes) {
  const pad = n => n.toString().padStart(2, "0");
  const options = [];

  let [sh, sm] = start.split(":").map(Number);
  const [eh, em] = end.split(":").map(Number);
  let startDate = new Date(0, 0, 0, sh, sm);
  const endDate = new Date(0, 0, 0, eh, em);

  while (startDate <= endDate) {
    options.push(pad(startDate.getHours()) + ":" + pad(startDate.getMinutes()));
    startDate.setMinutes(startDate.getMinutes() + stepMinutes);
  }

  return options;
}
    document.getElementById("bookingForm").addEventListener("submit", function(event) {
        event.preventDefault();
        const formData = new FormData(this);

        fetch('index.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            alert(data);
            closeModal();
            location.reload();
        })
        .catch(error => console.error('Error:', error));
    });

    document.getElementById("changeForm").addEventListener("submit", function(event) {
        event.preventDefault();
        const formData = new FormData(this);

        fetch('index.php?change=1', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            alert(data);
            closeChangeModal();
            location.reload();
        })
        .catch(error => console.error('Error:', error));
    });

    // JS untuk Galeri Swiper (auto slide dengan transisi geser)
    const gallerySwiper = new Swiper('.myGallerySwiper', {
        slidesPerView: 1,
        spaceBetween: 0,
        loop: true,
        autoplay: {
            delay: 5000,
            disableOnInteraction: false,
        },
        speed: 1500,
        pagination: {
            el: '.swiper-pagination',
            clickable: true,
        },
        navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
        }
    });

    // JS untuk Swiper "Our Offer"
    const offerSwiper = new Swiper('.offerSwiper', {
        direction: 'horizontal',
        loop: true,
        autoplay: {
            delay: 4000,
            disableOnInteraction: false,
        },
        pagination: {
            el: '.swiper-pagination',
            clickable: true,
        },
        navigation: {
            nextEl: '.swiper-button-next',
            prevEl: '.swiper-button-prev',
        },
        slidesPerView: 1,
        spaceBetween: 10,
        breakpoints: {
            768: {
                slidesPerView: 3,
                spaceBetween: 20,
            }
        },
    });
</script>
</body>
</html>