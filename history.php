<?php
// ====================================================================
// Kode PHP untuk mengambil data histori dan statistik
// ====================================================================
session_start();
include "conn.php";
global $conn;

// Ambil waktu saat ini
$now = date('Y-m-d H:i:s');

// Query untuk statistik riwayat booking (waktu booking sudah lewat)
$totalRiwayat = 0;
$bookedSelesai = 0;
$bookedMenunggu = 0;
$bookedDitolak = 0;

// Menghitung total riwayat booking (yang sudah lewat waktunya)
$query_total = "SELECT COUNT(*) AS totalRiwayat FROM booking WHERE waktu < CURDATE()";
$stmt_total = $conn->prepare($query_total);
if ($stmt_total) {
    $stmt_total->execute();
    $result_total = $stmt_total->get_result();
    $data_total = $result_total->fetch_assoc();
    $totalRiwayat = $data_total['totalRiwayat'];
    $stmt_total->close();
}

// Menghitung booking yang sudah Selesai (sudah lewat waktunya)
$query_selesai = "SELECT COUNT(*) AS bookedSelesai FROM booking WHERE waktu < CURDATE() AND status = 'Booked'";
$stmt_selesai = $conn->prepare($query_selesai);
if ($stmt_selesai) {
    $stmt_selesai->execute();
    $result_selesai = $stmt_selesai->get_result();
    $data_selesai = $result_selesai->fetch_assoc();
    $bookedSelesai = $data_selesai['bookedSelesai'];
    $stmt_selesai->close();
}

// Menghitung booking yang masih Menunggu (tapi sudah lewat waktunya)
$query_menunggu = "SELECT COUNT(*) AS bookedMenunggu FROM booking WHERE waktu < CURDATE() AND status = 'Menunggu'";
$stmt_menunggu = $conn->prepare($query_menunggu);
if ($stmt_menunggu) {
    $stmt_menunggu->execute();
    $result_menunggu = $stmt_menunggu->get_result();
    $data_menunggu = $result_menunggu->fetch_assoc();
    $bookedMenunggu = $data_menunggu['bookedMenunggu'];
    $stmt_menunggu->close();
}

// Menghitung booking yang Ditolak (sudah lewat waktunya)
$query_ditolak = "SELECT COUNT(*) AS bookedDitolak FROM booking WHERE waktu < CURDATE() AND status = 'Ditolak'";
$stmt_ditolak = $conn->prepare($query_ditolak);
if ($stmt_ditolak) {
    $stmt_ditolak->execute();
    $result_ditolak = $stmt_ditolak->get_result();
    $data_ditolak = $result_ditolak->fetch_assoc();
    $bookedDitolak = $data_ditolak['bookedDitolak'];
    $stmt_ditolak->close();
}

// Query untuk menampilkan data histori dalam tabel
$query = "SELECT * FROM booking WHERE waktu < CURDATE() ORDER BY waktu DESC";
$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin | Histori</title>
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
        .status-select { font-weight: bold; color: #ffffff; background-color: #3a3f4b; border: 1px solid #666; }
        .status-selesai { color: #28d17c; background-color: #1e2f1e; border-color: #28d17c; }
        .status-menunggu { color: #f7c948; background-color: #3a2f1e; border-color: #f7c948; }
        body { color: #f1f1f1; }
        table.table { background-color: #2d2d3a; color: #ffffff; border-color: #444; }
        thead.table-secondary { background-color: #3a3a4a !important; color: #ffffff !important; }
        table tbody tr:nth-child(odd) { background-color: #2c2f38; }
        table tbody tr:nth-child(even) { background-color: #2a2d36; }
        table td, table th { border: 1px solid #555; }
        select.status-select { background-color: #3a3a4a; color: #fff; border: 1px solid #666; padding: 3px; border-radius: 4px; }
        .btn-danger { background-color: #e74c3c; border: none; color: #fff; }
        .btn-danger:hover { background-color: #c0392b; }
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
            <span class="brand-text font-weight-light"><br></span>
        </a>
        <div class="sidebar">
            <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                <div class="info"><a href="#" class="d-block"></a></div>
            </div>
            
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    <li class="nav-item"><a href="admin.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Dashboard</p></a></li>
                    <li class="nav-item"><a href="tab_booking.php" class="nav-link"><i class="nav-icon fas fa-th"></i><p>Booking</p></a></li>
                    <li class="nav-item"><a href="history.php" class="nav-link active"><i class="nav-icon fas fa-chart-pie"></i><p>History</p></a></li>
                    <li class="nav-item"><a href="manage_content.php" class="nav-link"><i class="nav-icon fas fa-desktop"></i><p>Manage Website</p></a></li>
                    <li class="nav-item"><a href="logout.php" class="nav-link"><i class="nav-icon fas fa-sign-out-alt"></i><p>Logout</p></a></li>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">Histori</h1>
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
                                <h4><b>Process</b></h4>
                                <h3><?= $bookedMenunggu ?></h3>
                            </div>
                            <br>
                            <div class="icon">
                                <i class="ion ion-loop"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h4><b>Finish</b></h4>
                                <h3><?= $bookedSelesai ?></h3>
                            </div>
                            <br>
                            <div class="icon">
                                <i class="ion ion-checkmark-round"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h4><b>Total Riwayat</b></h4>
                                <h3><?= $totalRiwayat ?></h3>
                            </div>
                            <br>
                            <div class="icon">
                                <i class="ion ion-ios-list"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="card bg-dark">
                            <div class="card-header">
                                <h3 class="card-title">Tabel Histori Booking</h3>
                            </div>
                            <div class="card-body">
                                <div style="max-height: 500px; overflow-y: auto;">
                                    <table class="table table-bordered table-striped">
                                        <thead class="table-secondary">
                                            <tr>
                                                <th>No</th>
                                                <th>Nama</th>
                                                <th>No HP</th>
                                                <th>Jam</th>
                                                <th>Tanggal</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $no = 1;
                                            while ($row = $result->fetch_assoc()):
                                            ?>
                                            <tr>
                                                <td><?= $no++ ?></td>
                                                <td><?= htmlspecialchars($row['nama']) ?></td>
                                                <td><?= htmlspecialchars($row['no_hp']) ?></td>
                                                <td><?= date('H:i', strtotime($row['waktu'])) ?></td>
                                                <td><?= date('d-m-Y', strtotime($row['tanggal'])) ?></td>
                                                <td><?= htmlspecialchars($row['status']) ?></td>

                                            </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
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
        <div class="float-right d-none d-sm-inline-block"><b>Version</b> 3.1.0</div>
    </footer>
    <aside class="control-sidebar control-sidebar-dark"></aside>
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
    window.addEventListener('load', function () {
        const preloader = document.querySelector('.preloader');
        if (preloader) { preloader.style.display = 'none'; }
    });
</script>
</body>
</html>