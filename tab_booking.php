<?php
session_start();
include "conn.php";
include "content_helper.php";

global $conn;

// Deteksi apakah ini request AJAX atau request halaman biasa
$is_ajax_request = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// ==============================================
// BAGIAN AJAX ENDPOINT UNTUK PENGELOLAAN DATA
// ==============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_ajax_request) {
    // Handling POST requests from the table actions
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? '';
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID tidak valid.']);
        exit;
    }

    if ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM booking WHERE id = ?");
        if ($stmt === false) {
            echo json_encode(['success' => false, 'message' => 'Gagal menyiapkan statement: ' . $conn->error]);
            exit;
        }
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        echo json_encode(['success' => $success, 'message' => $success ? 'Booking berhasil dihapus.' : $stmt->error]);
        exit;
    }

    if ($action === 'update_status') {
        $status = $_POST['status'] ?? '';
        // Perhatikan konsistensi status, gunakan 'Booked' dengan huruf kapital
        if (in_array($status, ['Menunggu', 'Booked'])) {
            $stmt = $conn->prepare("UPDATE booking SET status = ? WHERE id = ?");
            if ($stmt === false) {
                echo json_encode(['success' => false, 'message' => 'Gagal menyiapkan statement: ' . $conn->error]);
                exit;
            }
            $stmt->bind_param("si", $status, $id);
            $success = $stmt->execute();
            echo json_encode(['success' => $success, 'message' => $success ? 'Status berhasil diperbarui.' : $stmt->error]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Status tidak valid.']);
        }
        exit;
    }
}

// Bagian untuk menyediakan data events kalender (request GET)
if (isset($_GET['source']) && $_GET['source'] == 'calendar') {
    header('Content-Type: application/json');
    $events = [];
    $sql = "SELECT id, nama, tanggal, waktu, status FROM booking";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $eventColor = '#dc3545';
            if (isset($row['status'])) {
                if ($row['status'] === 'Menunggu') {
                    $eventColor = '#ffc107'; // Kuning
                } else if ($row['status'] === 'Booked') {
                    $eventColor = '#28a745'; // Hijau
                }
            }
            $eventStart = $row['tanggal'] . 'T' . $row['waktu'];
            $events[] = [
                'id' => $row['id'],
                'title' => htmlspecialchars($row['nama']) . ' (' . date('H:i', strtotime($row['waktu'])) . ')',
                'start' => $eventStart,
                'color' => $eventColor
            ];
        }
    }
    echo json_encode($events);
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

// Query untuk detail booking
$bookingDetail = [];
$search_query = $_GET['q'] ?? '';
$is_search_active = !empty($search_query);

if ($is_search_active) {
    // Jika ada kata kunci pencarian, ambil data yang lebih ringkas
    $stmt = $conn->prepare("SELECT id, nama, sewa_sepatu, sewa_rompi, total_harga FROM booking WHERE nama LIKE ? ORDER BY tanggal DESC, waktu DESC");
    $search_param = '%' . $search_query . '%';
    $stmt->bind_param("s", $search_param);
    $stmt->execute();
    $result2 = $stmt->get_result();
} else {
    // Jika tidak ada kata kunci, ambil data normal
    $sql_booking = "SELECT id, nama, no_hp, tanggal, waktu, status, bukti_pembayaran FROM booking ORDER BY tanggal DESC, waktu DESC";
    $result2 = $conn->query($sql_booking);
}

if ($result2) {
    while ($row = $result2->fetch_assoc()) {
        $bookingDetail[] = $row;
    }
} else {
    die("Error mengambil data booking: " . $conn->error);
}
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
    <style>
        .status-select { 
            font-weight: bold; 
            color: #ffffff; 
            background-color: #3a3f4b; 
            border: 1px solid #666; 
            padding: 3px; 
            border-radius: 4px; 
        }
        /* Perbaikan CSS untuk status `booked` agar konsisten */
        .status-select.booked { 
            color: #28d17c; 
            background-color: #1e2f1e; 
            border-color: #28d17c; 
        }
        .status-select.menunggu { 
            color: #f7c948; 
            background-color: #3a2f1e; 
            border-color: #f7c948; 
        }
        body { color: #f1f1f1; }
        table.table { background-color: #2d2d3a; color: #ffffff; border-color: #444; }
        thead.table-secondary { background-color: #3a3a4a !important; color: #ffffff !important; }
        table tbody tr:nth-child(odd) { background-color: #2c2f38; }
        table tbody tr:nth-child(even) { background-color: #2a2d36; }
        table td, table th { border: 1px solid #555; }
        .btn-danger { background-color: #e74c3c; border: none; color: #fff; }
        .btn-danger:hover { background-color: #c0392b; }
        .export-section { text-align: center; margin-top: 20px; }
        .export-section .btn { font-weight: bold; }
        .bukti-link { color: #5bc0de; text-decoration: none; }
        .bukti-link:hover { text-decoration: underline; }
        .table td img {
            max-width: 100px;
            height: auto;
            display: block;
            margin: auto;
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
                <div class="info"><a href="#" class="d-block"></a></div>
            </div>
            <div class="form-inline">
                <div class="input-group" data-widget="sidebar-search">
                    <input class="form-control form-control-sidebar" type="search" placeholder="Search" aria-label="Search">
                    <div class="input-group-append"><button class="btn btn-sidebar"><i class="fas fa-search fa-fw"></i></button></div>
                </div>
            </div>
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    <li class="nav-item"><a href="admin.php" class="nav-link"><i class="far fa-circle nav-icon"></i><p>Dashboard</p></a></li>
                    <li class="nav-item"><a href="tab_booking.php" class="nav-link active"><i class="nav-icon fas fa-th"></i><p>Booking</p></a></li>
                    <li class="nav-item"><a href="history.php" class="nav-link"><i class="nav-icon fas fa-chart-pie"></i><p>Histori</p></a></li>
                    <li class="nav-item"><a href="manage_content.php" class="nav-link"><i class="nav-icon fas fa-desktop"></i><p>Kelola Website</p></a></li>
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
                        <h1 class="m-0">Booking</h1>
                    </div>
                    <div class="col-sm-6">
                        <ol class="breadcrumb float-sm-right">
                            <li class="breadcrumb-item"><a href="#">Home</a></li>
                            <li class="breadcrumb-item active">Booking</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-lg-3 col-6"><div class="small-box bg-warning"><div class="inner"><h4><b>Menunggu</b></h4><h3><?=$jumlahMenunggu?></h3></div><br><div class="icon"><i class="ion ion-loop"></i></div></div></div>
                    <div class="col-lg-3 col-6"><div class="small-box bg-success"><div class="inner"><h4><b>Booked All</b></h4><h3><?=$bookedAll?></h3></div><br><div class="icon"><i class="ion ion-calendar"></i></div></div></div>
                    <div class="col-lg-3 col-6"><div class="small-box bg-info"><div class="inner"><h4><b>Booked Today</b></h4><h3><?=$bookedToday?></h3></div><br><div class="icon"><i class="ion ion-checkmark-round"></i></div></div></div>
                    <div class="col-lg-3 col-6"><div class="small-box bg-danger"><div class="inner"><h4><b>Total Booking</b></h4><h3><?=$totalBookings?></h3></div><br><div class="icon"><i class="ion ion-ios-list"></i></div></div></div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <div class="form-group">
                            <input type="text" class="form-control" id="searchInput" placeholder="Cari berdasarkan nama..." value="<?= htmlspecialchars($search_query) ?>">
                        </div>
                    </div>
                </div>
                <div style="max-height: 500px; overflow-y: auto;">
                    <table class="table table-bordered table-striped">
                        <thead class="table-secondary" style="position: sticky; top: 0; z-index: 1;">
                            <tr>
                                <th>No.</th>
                                <th>Nama</th>
                                <?php if (!$is_search_active): ?>
                                <th>No HP</th>
                                <th>Jam</th>
                                <th>Tanggal</th>
                                <th>Bukti Pembayaran</th>
                                <th>Status</th>
                                <?php else: ?>
                                <th>Sewa Sepatu</th>
                                <th>Sewa Rompi</th>
                                <th>Total Harga</th>
                                <?php endif; ?>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $no = 1;
                            if (!empty($bookingDetail)):
                                foreach ($bookingDetail as $row):
                            ?>
                            <tr id="row-<?= $row['id'] ?>">
                                <td><?= $no++ ?></td>
                                <td><?= htmlspecialchars($row['nama']) ?></td>
                                <?php if (!$is_search_active): ?>
                                <td><?= htmlspecialchars($row['no_hp']) ?></td>
                                <td><?= date('H:i', strtotime($row['waktu'])) ?></td>
                                <td><?= date('d-m-Y', strtotime($row['tanggal'])) ?></td>
                                <td>
                                    <?php if (!empty($row['bukti_pembayaran'])): ?>
                                        <a href="uploads/<?= htmlspecialchars($row['bukti_pembayaran']) ?>" target="_blank">
                                            <img src="uploads/<?= htmlspecialchars($row['bukti_pembayaran']) ?>" alt="Bukti Pembayaran" style="width:100px; height:auto; display:block; margin:auto;">
                                        </a>
                                    <?php else: ?>
                                        Tidak Ada Bukti
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <select class="status-select <?= strtolower($row['status']) ?>" onchange="ubahStatus(<?= htmlspecialchars($row['id']) ?>, this.value)">
                                        <option value="Menunggu" <?= ($row['status'] == 'Menunggu') ? 'selected' : '' ?>>Menunggu</option>
                                        <option value="Booked" <?= ($row['status'] == 'Booked') ? 'selected' : '' ?>>Booked</option>
                                    </select>
                                </td>
                                <?php else: ?>
                                <td><?= htmlspecialchars($row['sewa_sepatu']) ?></td>
                                <td><?= htmlspecialchars($row['sewa_rompi']) ?></td>
                                <td>Rp <?= number_format(htmlspecialchars($row['total_harga']), 0, ',', '.') ?></td>
                                <?php endif; ?>
                                <td>
                                    <button class="btn btn-sm btn-danger" onclick="hapusBooking(<?= htmlspecialchars($row['id']) ?>)">Hapus</button>
                                </td>
                            </tr>
                            <?php
                                endforeach;
                            else:
                            ?>
                                <tr><td colspan="<?= $is_search_active ? '5' : '8' ?>" class="text-center">Tidak ada data booking yang ditemukan.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="export-section fw-bold">
                    <h3>Export Data Booking</h3>
                    <form action="export.php" method="post">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-file-excel"></i> <b>Export to Excel</b>
                        </button>
                    </form>
                </div>
            </div>
        </section>
    </div><br>
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
    // Fungsi JavaScript untuk menghapus booking
    function hapusBooking(id) {
        if (confirm("Yakin ingin menghapus data booking ini dari database?")) {
            fetch('tab_booking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'delete', id: id })
            }).then(res => res.json()).then(data => {
                if (data.success) {
                    alert('Data booking berhasil dihapus.');
                    window.location.reload();
                } else { alert('Gagal menghapus data booking: ' + (data.message || 'Unknown error.')); }
            }).catch(err => { console.error('Error:', err); alert('Terjadi kesalahan saat menghapus.'); });
        }
    }

    // Fungsi JavaScript untuk mengubah status booking
    function ubahStatus(id, status) {
        if (confirm("Yakin ingin mengubah status booking ini menjadi '" + status + "'?")) {
            fetch('tab_booking.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'update_status', id: id, status: status })
            }).then(res => res.json()).then(data => {
                if (data.success) {
                    alert("Status berhasil diperbarui.");
                    location.reload();
                } else { alert("Gagal memperbarui status: " + (data.message || 'Unknown error')); }
                
            }).catch(err => { console.error("Error:", err); alert("Terjadi kesalahan saat memperbarui status."); });
        }
    }

    // Event listener untuk input pencarian
    document.getElementById('searchInput').addEventListener('input', function() {
        const query = this.value;
        const currentUrl = new URL(window.location.href);
        
        if (query) {
            currentUrl.searchParams.set('q', query);
        } else {
            currentUrl.searchParams.delete('q');
        }
        window.location.href = currentUrl.toString();
    });

    // Menghilangkan preloader saat halaman selesai dimuat
    window.addEventListener('load', function () {
        const preloader = document.querySelector('.preloader');
        if (preloader) { preloader.style.display = 'none'; }
    });
</script>
</body>
</html>