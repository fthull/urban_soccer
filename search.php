<?php
include "conn.php";

$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$is_search_active = !empty($search_query);

if ($is_search_active) {
    $stmt = $conn->prepare("SELECT id, nama, sewa_sepatu, sewa_rompi, total_harga, no_hp, tanggal, waktu, status 
                            FROM booking 
                            WHERE nama LIKE ? AND waktu < CURDATE() 
                            ORDER BY waktu DESC");
    $search_param = '%' . $search_query . '%';
    $stmt->bind_param("s", $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $query = "SELECT * FROM booking WHERE waktu < CURDATE() ORDER BY waktu DESC";
    $result = $conn->query($query);
}

$no = 1;
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>
                <td>".$no++."</td>
                <td>".htmlspecialchars($row['nama'])."</td>
                <td>".htmlspecialchars($row['no_hp'])."</td>
                <td>".date('H:i', strtotime($row['waktu']))."</td>
                <td>".date('d-m-Y', strtotime($row['tanggal']))."</td>
                <td>".htmlspecialchars($row['status'])."</td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='6' class='text-center'>Data tidak ditemukan</td></tr>";
}
