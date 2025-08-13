<?php
session_start();
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("location: login.php");
    exit;
}
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

    $stmt = $conn->prepare("SELECT id, nama, no_hp, tanggal, waktu, status FROM booking WHERE id = ?");
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
            display: flex;
            justify-content: flex-end;
            padding-right: 5px;
        }
        
        /* Mengubah warna teks tanggal, nama hari, dan judul event menjadi putih dan tebal */
        .fc-daygrid-day-number,
        .fc-col-header-cell-cushion,
        .fc-event-title,
        .fc-event-time {
            color: #ffffff !important; /* Warna putih */
            font-weight: bold !important; /* Dicetak tebal */
        }
        
        /* Menghilangkan elemen waktu yang terpisah, seperti "9a" */
        .fc-event-time {
            display: none !important;
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
        <img class="animation__wobble" src="AdminLTE-3.1.0/dist/img/AdminLTELogo.png" alt="AdminLTELogo" height="60" width="60">
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
                            <a href="admin.php" class="nav-link active">
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
                            <a href="manage_content.php" class="nav-link">
                                <i class="nav-icon fas fa-desktop"></i>
                                <p>
                                    Kelola Website
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
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Dashboard</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="#">Home</a></li>
                            <li class="breadcrumb-item active">Dashboard</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
            <section class="content">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-warning">
                                <div class="inner">
                                    <h4><b>Menunggu</b></h4>
                                    <h3><?=$jumlahMenunggu?></h3>
                                </div><br>
                                <div class="icon"><i class="ion ion-loop"></i></div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-success">
                                <div class="inner">
                                    <h4><b>Booked All</b></h4>
                                    <h3><?=$bookedAll?></h3>
                                </div><br>
                                <div class="icon"><i class="ion ion-calendar"></i></div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-info">
                                <div class="inner">
                                    <h4><b>Booked Today</b></h4>
                                    <h3><?=$bookedToday?></h3>
                                </div><br>
                                <div class="icon"><i class="ion ion-checkmark-round"></i></div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-6">
                            <div class="small-box bg-danger">
                                <div class="inner">
                                    <h4><b>Total Booking</b></h4>
                                    <h3><?=$totalBookings?></h3>
                                </div><br>
                                <div class="icon"><i class="ion ion-ios-list"></i></div>
                            </div>
                        </div>
                    </div>

                    <div id="calendar"></div>

                    <div class="modal fade" id="bookingModal" tabindex="-1" role="dialog" aria-labelledby="bookingModalLabel" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="bookingModalLabel">Detail Booking</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>

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
                                    <strong>Status:</strong> ${booking.status}<br>
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