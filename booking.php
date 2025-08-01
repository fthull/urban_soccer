<?php
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
$result = $conn->query("SELECT nama, waktu FROM booking ORDER BY waktu ASC");
while ($row = $result->fetch_assoc()) {
    $events[] = [
        'title' => 'Booked: ' . ' (' . date('H:i', strtotime($row['waktu'])) . ')',
        'start' => date('c', strtotime($row['waktu'])), // Format ISO 8601
        'allDay' => false
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
  <meta charset="UTF-8" />
  <title>Booking Urban Soccer</title>
  <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
  <style>
    #calendar {
      max-width: 900px;
      margin: 40px auto;
    }
    .modal {
      display: none;
      position: fixed;
      z-index: 999;
      left: 0; top: 0; right: 0; bottom: 0;
      background-color: rgba(0,0,0,0.5);
      align-items: center;
      justify-content: center;
    }
    .modal-content {
      background: white;
      padding: 20px;
      border-radius: 10px;
      width: 300px;
    }
  </style>
</head>
<body>

<div id="calendar"></div>
<button onclick="openChangeModal()" style="margin: 20px auto; display: block;">Change Booking</button>

<!-- Modal Booking -->
<div id="bookingModal" class="modal">
  <div class="modal-content">
    <h4>Booking Urban Soccer</h4>
    <form id="bookingForm">
      <input type="hidden" id="tanggal" name="tanggal" />
      <div><label>Nama:</label><input type="text" name="nama" required></div>
      <div><label>No HP:</label><input type="text" name="no_hp" required></div>
      <div>
        <label>Jam:</label>
        <div>
  <label>Jam:</label>
  <select name="jam" id="jamDropdown" required></select>
</div>

      </div>
      <div style="margin-top: 10px;">
        <button type="submit">Simpan</button>
        <button type="button" onclick="closeModal()">Batal</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Change Booking -->
<div id="changeModal" class="modal">
  <div class="modal-content">
    <h4>Change Booking</h4>
    <form id="changeForm">
      <div><label>Nama:</label><input type="text" name="nama" required></div>
      <div><label>No HP:</label><input type="text" name="no_hp" required></div>
      <div><label>Tanggal Baru:</label><input type="date" name="tanggal" required></div>
      <div>
        <label>Jam Baru:</label>
        <select name="jam" required>
          <?php
          $start = strtotime("07:00");
          $end = strtotime("23:00");
          while ($start <= $end) {
              echo "<option value='" . date("H:i", $start) . "'>" . date("H:i", $start) . "</option>";
              $start = strtotime("+120 minutes", $start);
          }
          ?>
        </select>
      </div>
      <div style="margin-top: 10px;">
        <button type="submit">Update Booking</button>
        <button type="button" onclick="closeChangeModal()">Batal</button>
      </div>
    </form>
  </div>
</div>

<script>
  const bookedEvents = <?= json_encode($events) ?>;

  document.addEventListener("DOMContentLoaded", function () {
    const calendarEl = document.getElementById("calendar");

    const calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: "dayGridMonth",
       displayEventTime: false,
      selectable: true,
      events: bookedEvents,
      dateClick: function(info) {
        document.getElementById("tanggal").value = info.dateStr;
        document.getElementById("bookingModal").style.display = "flex";
      },
      dateClick: function(info) {
  const tanggal = info.dateStr;
  document.getElementById("tanggal").value = tanggal;
  document.getElementById("bookingModal").style.display = "flex";
  loadJamOptions(tanggal); // ✅ ini penting
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

  document.getElementById("bookingForm").addEventListener("submit", function (e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch("", {
      method: "POST",
      body: formData
    })
    .then(response => response.text())
    .then(data => {
      alert(data);
      if (data.includes("Berhasil")) {
        closeModal();
        location.reload();
      }
    })
    .catch(err => {
      alert("Gagal menyimpan booking.");
      console.error(err);
    });
  });

  document.getElementById("changeForm").addEventListener("submit", function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch("?change=1", {
      method: "POST",
      body: formData
    })
    .then(response => response.text())
    .then(data => {
      alert(data);
      if (data.includes("Berhasil")) {
        closeChangeModal();
        location.reload();
      }
    })
    .catch(err => {
      alert("Gagal update booking.");
      console.error(err);
    });
  });

  
function loadJamOptions(tanggal) {
  const dropdown = document.getElementById("jamDropdown");
  dropdown.innerHTML = ""; // Kosongkan dulu

  fetch("?get_booked_times=1&tanggal=" + tanggal)
    .then(res => res.json())
    .then(booked => {
      const semuaJam = generateJamOptions("07:00", "23:00", 120); // 90 = 2 jam
      semuaJam.forEach(jam => {
        if (!booked.includes(jam)) {
          const option = document.createElement("option");
          option.value = jam;
          option.textContent = jam;
          dropdown.appendChild(option);
        }
      });

      if (dropdown.options.length === 0) {
        const option = document.createElement("option");
        option.text = "Tidak ada jam tersedia";
        option.disabled = true;
        dropdown.appendChild(option);
      }
    });
}

// Fungsi buat generate jam 1.5 jam sekali
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


</script>

</body>
</html>
