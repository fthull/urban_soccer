<?php
header('Content-Type: application/json');
include '../conn.php';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
  die(json_encode(['success' => false, 'message' => 'Koneksi gagal']));
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

  case 'load':
    $result = $conn->query("
  SELECT b.id, b.nama, b.id_service, s.name as nama_layanan, b.waktu, b.tanggal, b.status
  FROM booking b
  JOIN services s ON b.id_service = s.id
");

$events = [];

while ($row = $result->fetch_assoc()) {
  $events[] = [
    'id' => $row['id'],
    'start' => $row['tanggal'] . 'T' . date('H:i:s', strtotime($row['waktu'])),
    'extendedProps' => [
      'nama' => $row['nama'],
      'id_service' => $row['id_service'],
      'layanan' => $row['nama_layanan'],
      'waktu' => $row['waktu'],
      'status' => $row['status']
    ]
  ];
}


    echo json_encode($events);
    break;

  case 'add':
    $nama       = $_POST['nama'] ?? '';
    $id_service = $_POST['id_service'] ?? ''; // ID layanan
    $tanggal    = $_POST['tanggal'] ?? '';
    $jam        = $_POST['jam'] ?? '';

    if (!$nama || !$id_service || !$tanggal || !$jam) {
      echo json_encode(["success" => false, "message" => "Data tidak lengkap"]);
      exit;
    }

    $waktu = $tanggal . ' ' . $jam . ':00';
    $status = 'menunggu';

    $stmt = $conn->prepare("INSERT INTO booking (nama, id_service, waktu, tanggal, status) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt) {
      echo json_encode(["success" => false, "message" => "Prepare gagal: " . $conn->error]);
      exit;
    }

    $stmt->bind_param("sisss", $nama, $id_service, $waktu, $tanggal, $status);
    $success = $stmt->execute();

    echo json_encode(["success" => $success]);
    break;

  case 'update':
    $id         = $_POST['id'] ?? '';
    $nama       = $_POST['nama'] ?? '';
    $id_service = $_POST['id_service'] ?? '';
    $tanggal    = $_POST['tanggal'] ?? '';
    $jam        = $_POST['jam'] ?? '';

    if (!$id || !$nama || !$id_service || !$tanggal || !$jam) {
      echo json_encode(["success" => false, "message" => "Data tidak lengkap"]);
      exit;
    }

    $waktu = $tanggal . ' ' . $jam . ':00';

    $stmt = $conn->prepare("UPDATE booking SET nama = ?, id_service = ?, waktu = ?, tanggal = ? WHERE id = ?");
    if (!$stmt) {
      echo json_encode(["success" => false, "message" => "Prepare gagal: " . $conn->error]);
      exit;
    }

    $stmt->bind_param("sissi", $nama, $id_service, $waktu, $tanggal, $id);
    $success = $stmt->execute();

    echo json_encode(["success" => $success]);
    break;

  case 'delete':
    $id = $_POST['id'] ?? 0;

    if ($id) {
      $stmt = $conn->prepare("DELETE FROM booking WHERE id = ?");
      $stmt->bind_param("i", $id);
      $success = $stmt->execute();
      echo json_encode(["success" => $success]);
    } else {
      echo json_encode(["success" => false, "message" => "ID tidak valid"]);
    }
    break;

  case 'status':
    $id     = $_POST['id'] ?? '';
    $status = $_POST['status'] ?? '';

    if (!$id || !$status) {
      echo json_encode(['success' => false, 'message' => 'Data tidak lengkap']);
      exit;
    }

    $stmt = $conn->prepare("UPDATE booking SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $status, $id);
    $success = $stmt->execute();

    echo json_encode(["success" => $success]);
    break;

  default:
    echo json_encode(["success" => false, "message" => "Aksi tidak dikenali"]);
    break;
}

$conn->close();
?>
