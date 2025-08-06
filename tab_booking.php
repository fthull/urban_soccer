<?php
session_start();
include "conn.php";

$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

if ($isPost && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'delete') {
        $id = intval($_POST['id']);
        $stmt = $conn->prepare("DELETE FROM booking WHERE id = ?");
        $stmt->bind_param("i", $id);
        $success = $stmt->execute();
        echo json_encode(['success' => $success]);
        exit;
    }

    if ($action === 'update_status') {
        $id = intval($_POST['id']);
        $status = $_POST['status'];

        if ($id && in_array($status, ['Process', 'Booked'])) {
            $stmt = $conn->prepare("UPDATE booking SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $status, $id);
            $success = $stmt->execute();
            echo json_encode(['success' => $success]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
        }
        exit;
    }
}

if (
    isset($_GET['action']) && $_GET['action'] === 'update_status' &&
    $_SERVER['REQUEST_METHOD'] === 'POST'
) {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $status = isset($_POST['status']) ? $_POST['status'] : '';

    if ($id && in_array($status, ['Process', 'Booked'])) {
        $stmt = $conn->prepare("UPDATE booking SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $id);
        $success = $stmt->execute();

        echo json_encode(['success' => $success]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Data tidak valid']);
    }
    exit;
}



$result2 = $conn->query("SELECT * FROM booking where waktu >= now() ORDER BY waktu ASC");
$bookingDetail = [];

while ($row = $result2->fetch_assoc()) {
    $bookingDetail[] = $row;
}

$sql = "SELECT COUNT(*) AS total FROM booking WHERE status = 'process'";
$result = $conn->query($sql);
$data = $result->fetch_assoc();
$jumlahProcess = $data['total'];


$sql = "SELECT COUNT(*) AS total FROM booking WHERE status = 'booked' and waktu>= now()";
$result = $conn->query($sql);
$data = $result->fetch_assoc();
$booked = $data['total'];


$sql = "SELECT COUNT(*) AS total FROM booking WHERE status = 'booked' AND DATE(tanggal) = CURDATE()";
$result = $conn->query($sql);
$data = $result->fetch_assoc();
$today = $data['total'];


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
        /* Your existing CSS styles */
        .status-select {
            font-weight: bold;
            color: #ffffff;
            background-color: #3a3f4b;
            border: 1px solid #666;
        }

        /* Status Selesai */
        .status-selesai {
            color: #28d17c; /* hijau terang */
            background-color: #1e2f1e; /* hijau gelap */
            border-color: #28d17c;
        }

        /* Status Menunggu */
        .status-menunggu {
            color: #f7c948; /* kuning terang */
            background-color: #3a2f1e; /* coklat gelap */
            border-color: #f7c948;
        }

        /* Override tabel agar cocok dengan dark mode */
        body {
            color: #f1f1f1;
        }

        table.table {
            background-color: #2d2d3a;
            color: #ffffff;
            border-color: #444;
        }

        thead.table-secondary {
            background-color: #3a3a4a !important;
            color: #ffffff !important;
        }

        table tbody tr:nth-child(odd) {
            background-color: #2c2f38;
        }

        table tbody tr:nth-child(even) {
            background-color: #2a2d36;
        }

        table td, table th {
            border: 1px solid #555;
        }

        select.status-select {
            background-color: #3a3a4a;
            color: #fff;
            border: 1px solid #666;
            padding: 3px;
            border-radius: 4px;
        }

        .btn-danger {
            background-color: #e74c3c;
            border: none;
            color: #fff;
        }

        .btn-danger:hover {
            background-color: #c0392b;
        }
    </style>

</head>
<body class="hold-transition sidebar-mini layout-fixed dark-mode">
<div class="wrapper">

    <div class="preloader flex-column justify-content-center align-items-center">
        <img class="animation__shake" src="dist/img/AdminLTELogo.png" alt="AdminLTELogo" height="60" width="60">
    </div>

    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="index3.html" class="brand-link">
                <img src="mgdlogo.png" alt="AdminLTE Logo" class="brand-image" style="opacity: .8">
            <span class="brand-text font-weight-light">MGD Soccer Field</span>
        </a>

        <div class="sidebar">
            <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                <div class="image">
                    <img src="dist/img/user2-160x160.jpg" class="img-circle elevation-2" alt="User Image">
                </div>
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
                        <a href="tab_booking.php" class="nav-link active">
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
                        <a href="kasir.php" class="nav-link">
                            <i class="nav-icon fas fa-desktop"></i>
                            <p>
                                Kasir
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

    <br>
    <br>
    <br>

    <div class="content-wrapper">
        <section class="content">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h2>Process</h2>
                                <h3><?=$jumlahProcess?></h3>
                            </div><br>
                            <div class="icon">
                                <i class="ion ion-loop"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h2>Booked All</h2>
                                <h3><?=$booked?></h3>
                            </div><br>
                            <div class="icon">
                                <i class="ion ion-checkmark-circled"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h2>Booked Today</h2>
                                <h3><?=$today?></h3>
                            </div><br>
                            <div class="icon">
                                <i class="ion ion-star"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-danger">
                            <div class="inner">
                                <h6></h6>
                                <p>?</p>
                            </div>
                            <div class="icon">
                                <i class="ion ion-minus-circled"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="max-height: 500px; overflow-y: auto;">
    <table class="table table-bordered table-striped">
        <thead class="table-secondary" style="position: sticky; top: 0; z-index: 1;">
            <tr>
                <th>No.</th>
                <th>Nama</th>
                <th>No HP</th>
                <th>Jam</th>
                <th>Tanggal</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $counter = 0;
            $no = 1;
            foreach ($bookingDetail as $row):
                $counter++;
                if ($counter > 10) break;
            ?>
            <tr id="row-<?= $row['id'] ?>">
                <td><?= $no++ ?></td>
                <td><?= htmlspecialchars($row['nama']) ?></td>
                <td><?= htmlspecialchars($row['no_hp']) ?></td>
                <td><?= date('H:i', strtotime($row['waktu'])) ?></td>
                <td><?= date('d-m-Y', strtotime($row['tanggal'])) ?></td>
                <td><?= htmlspecialchars($row['status']) ?></td>
                <td>
                    <button class="btn btn-sm btn-danger" onclick="hapusBooking(<?= htmlspecialchars($row['id']) ?>)">Delete</button>
                    <button class="btn btn-sm btn-success" onclick="ubahStatus(<?= htmlspecialchars($row['id']) ?>)">Check Booked</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
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
function hapusBooking(id) {
    if (confirm("Yakin ingin menghapus data booking ini dari database?")) {
        fetch('tab_booking.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'delete', id: id })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                document.getElementById('row-' + id).remove();
                window.location.reload();
            } else {
                alert('Gagal menghapus data booking: ' + (data.message || 'Unknown error.'));
            }
        })
        .catch(err => {
            console.error('Error:', err);
            alert('Terjadi kesalahan saat menghapus.');
        });
    }
}

function ubahStatus(id) {
    if (confirm("Tandai booking ini sebagai 'Booked'?")) {
        fetch('tab_booking.php?action=update_status', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
    },
    body: new URLSearchParams({
        id: id,
        status: 'Booked'
    })
})

        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert("Status berhasil diperbarui.");
                location.reload();
            } else {
                alert("Gagal memperbarui status: " + (data.message || 'Unknown error'));
            }
        })
        .catch(err => {
            console.error("Error:", err);
            alert("Terjadi kesalahan saat memperbarui status.");
        });
    }
}

window.addEventListener('load', function () {
    const preloader = document.querySelector('.preloader');
    if (preloader) {
        preloader.style.display = 'none';
    }
});

$('#btnUbahStatus').click(function() {
    const bookingId = $('#bookingModal').data('booking-id');
    fetch('tab_booking.php?action=update_status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${bookingId}&status=Booked`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Status booking berhasil diperbarui.');
            $('#bookingModal').modal('hide');
            calendar.refetchEvents();
        } else {
            alert('Gagal memperbarui status.');
        }
    });
});
</script>


</body>
</html>