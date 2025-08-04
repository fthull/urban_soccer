<?php
include 'conn.php';

// Menangani permintaan POST untuk booking baru
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

// Mengambil data kalender
$events = [];
$result = $conn->query("SELECT nama, waktu FROM booking ORDER BY waktu ASC");
while ($row = $result->fetch_assoc()) {
    $events[] = [
        'title' => 'Booked: ' . ' (' . date('H:i', strtotime($row['waktu'])) . ')',
        'start' => date('c', strtotime($row['waktu'])), // Format ISO 8601
        'allDay' => false
    ];
}

// Menangani permintaan GET untuk mendapatkan jam yang sudah dibooking
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

// Menangani permintaan POST untuk mengubah booking
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Urban Soccer</title>
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f0f2f5; /* Warna background utama, abu-abu muda */
            --card-bg: #ffffff; /* Warna card/container, putih bersih */
            --text-color: #333333; /* Warna teks utama, abu-abu gelap */
            --primary-color: #28a745; /* Warna hijau utama, sesuai website */
            --primary-dark: #1e7e34; /* Warna hijau lebih gelap untuk hover */
            --secondary-color: #6c757d; /* Warna abu-abu sekunder */
            --danger-color: #dc3545; /* Warna merah untuk tombol batal */
            --box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            margin: 0;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .container {
            max-width: 900px;
            width: 100%;
            background-color: var(--card-bg);
            padding: 2.5rem;
            border-radius: 12px;
            box-shadow: var(--box-shadow);
        }

        h1 {
            text-align: center;
            color: var(--primary-color);
            margin-bottom: 2rem;
            font-weight: 700;
        }
        
        #calendar {
            background-color: var(--card-bg);
            padding: 1rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        /* Kalender custom styles */
        .fc-daygrid-day-number {
            color: var(--text-color);
            font-weight: 500;
            font-size: 1.1em;
        }
        .fc .fc-toolbar-title {
            color: var(--text-color);
            font-weight: 700;
        }
        .fc .fc-button {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
            transition: all 0.3s ease;
        }
        .fc .fc-button:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }
        .fc-col-header-cell {
            background-color: #f8f9fa;
            color: var(--secondary-color);
            font-weight: 600;
        }
        .fc-day-other .fc-daygrid-day-number {
            color: #ccc;
        }
        .fc-daygrid-day.fc-day-today {
            background-color: #e9ecef !important;
        }
        .fc-event {
            background-color: var(--secondary-color) !important;
            border-color: var(--secondary-color) !important;
            color: white !important;
            font-size: 0.8em;
            font-weight: 500;
            border-radius: 4px;
            padding: 2px 5px;
        }

        /* Tombol */
        .button-group {
            text-align: center;
            margin-top: 2rem;
            display: flex;
            justify-content: center;
            gap: 1rem;
        }
        button {
            padding: 12px 28px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 1rem;
        }
        .btn-main {
            background-color: var(--primary-color);
            color: white;
        }
        .btn-main:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        .btn-cancel {
            background-color: var(--danger-color);
            color: white;
        }
        .btn-cancel:hover {
            background-color: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0; top: 0; right: 0; bottom: 0;
            background-color: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s;
        }
        .modal.is-visible {
            opacity: 1;
            visibility: visible;
        }
        .modal-content {
            background: var(--card-bg);
            padding: 2.5rem;
            border-radius: 10px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            transform: translateY(-30px);
            transition: transform 0.3s ease;
        }
        .modal.is-visible .modal-content {
            transform: translateY(0);
        }
        .modal-content h4 {
            margin-top: 0;
            margin-bottom: 2rem;
            text-align: center;
            color: var(--primary-color);
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--secondary-color);
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #ced4da;
            background-color: #f8f9fa;
            color: var(--text-color);
            box-sizing: border-box;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.25);
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Booking Urban Soccer</h1>
    <div id="calendar"></div>
    <div class="button-group">
        <button class="btn-main" onclick="openChangeModal()">Ubah Booking</button>
    </div>
</div>

<div id="bookingModal" class="modal">
    <div class="modal-content">
        <h4>Booking Urban Soccer</h4>
        <form id="bookingForm">
            <input type="hidden" id="tanggal" name="tanggal" />
            <div class="form-group">
                <label for="nama">Nama:</label>
                <input type="text" id="nama" name="nama" required>
            </div>
            <div class="form-group">
                <label for="no_hp">No HP:</label>
                <input type="text" id="no_hp" name="no_hp" required>
            </div>
            <div class="form-group">
                <label for="jamDropdown">Jam:</label>
                <select name="jam" id="jamDropdown" required></select>
            </div>
            <div class="button-group">
                <button type="submit" class="btn-main">Simpan</button>
                <button type="button" class="btn-cancel" onclick="closeModal()">Batal</button>
            </div>
        </form>
    </div>
</div>

<div id="changeModal" class="modal">
    <div class="modal-content">
        <h4>Ubah Booking</h4>
        <form id="changeForm">
            <div class="form-group">
                <label for="changeNama">Nama:</label>
                <input type="text" id="changeNama" name="nama" required>
            </div>
            <div class="form-group">
                <label for="changeNoHp">No HP:</label>
                <input type="text" id="changeNoHp" name="no_hp" required>
            </div>
            <div class="form-group">
                <label for="changeTanggal">Tanggal Baru:</label>
                <input type="date" id="changeTanggal" name="tanggal" required>
            </div>
            <div class="form-group">
                <label for="changeJam">Jam Baru:</label>
                <select name="jam" id="changeJam" required>
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
            <div class="button-group">
                <button type="submit" class="btn-main">Update Booking</button>
                <button type="button" class="btn-cancel" onclick="closeChangeModal()">Batal</button>
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
                const tanggal = info.dateStr;
                document.getElementById("tanggal").value = tanggal;
                document.getElementById("bookingModal").classList.add("is-visible");
                loadJamOptions(tanggal);
            }
        });

        calendar.render();
    });

    function closeModal() {
        document.getElementById("bookingModal").classList.remove("is-visible");
    }

    function openChangeModal() {
        document.getElementById("changeModal").classList.add("is-visible");
    }

    function closeChangeModal() {
        document.getElementById("changeModal").classList.remove("is-visible");
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
        dropdown.innerHTML = ""; 

        fetch("?get_booked_times=1&tanggal=" + tanggal)
            .then(res => res.json())
            .then(booked => {
                const semuaJam = generateJamOptions("07:00", "23:00", 120); 
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