
<?php
include "conn.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  // Mengambil input dari form bernama username, lalu menghapus spasi di awal dan akhir nilai tersebut.
  $username = trim($_POST['username']);
  $rawPassword = $_POST["password"];

  // $inputRole = $_POST["role"] ?? '';

  if (empty($username) || empty($rawPassword)) {

    $message = "Semua field harus diisi.";
  } else {
    // Enkripsi password
    $password = password_hash($rawPassword, PASSWORD_DEFAULT);

  }
}
    // Cek apakah username sudah ada
    // Kode ini membuat prepared statement untuk mencari apakah username tertentu sudah ada di tabel mencuci.
    $check = $conn->prepare("SELECT id FROM mencuci WHERE username = ?");
    // Ini mengikat nilai dari variabel $username ke placeholder ? yang ada di query SQL yang sebelumnya disiapkan.
    $check->bind_param("s", $username);
    $check->execute();
    // store_result() digunakan untuk menyimpan hasil query dari statement yang dieksekusi ke dalam memori lokal, sehingga bisa diakses nanti — misalnya untuk menghitung jumlah baris hasil dengan num_rows.
    $check->store_result();

  $sql = "INSERT INTO mencuci (username, password) VALUES (?, ? )";
// Menambahkan data username dan password ke tabel mencuci dengan cara yang aman menggunakan Prepared Statement.
    // Ini adalah query SQL yang digunakan untuk menambahkan data baru ke dalam tabel mencuci.
  $sql = "INSERT INTO mencuci (username, password) VALUES (?, ? )";
  // Ini adalah langkah untuk menyiapkan (prepare) perintah SQL sebelum dijalankan, menggunakan Prepared Statement dari MySQLi.
  $sql = "INSERT INTO users (username, password) VALUES (?, ? )";
  $stmt = $conn->prepare($sql);
  // Baris ini mengisi placeholder (?) dalam query dengan nilai yang kamu punya.
  // $username akan menggantikan tanda ? pertama.
// $password akan menggantikan tanda ? kedua.
  $stmt->bind_param("ss", $username, $password);


  // Ini mengecek apakah data username yang diinputkan sudah ada di
    if ($check->num_rows > 0) {
      $message = "Username sudah digunakan. Silakan pilih yang lain.";
    } else {
      //  menambahkan data pengguna baru ke dalam tabel mencuci.
      $sql = "INSERT INTO mencuci (username, password, role) VALUES (?, ?, ?)";
      // Kode ini digunakan untuk menyiapkan (prepare) query SQL yang sebelumnya sudah ditulis di variabel $sql, agar bisa dijalankan dengan prepared statement dari MySQLi.
      $stmt = $conn->prepare($sql);
      // Kode ini berfungsi untuk mengikat data (bind) ke query SQL yang sebelumnya sudah disiapkan dengan prepare().
      $stmt->bind_param("sss", $username, $password, $inputRole);
      // Simpan ke database

      $sql = "INSERT INTO mencuci (username, password ) VALUES (?, ?)";

      $stmt = $conn->prepare($sql);
      $stmt->bind_param("ss", $username, $password);


      // cek apakah eksekusinya berhasil atau tidak.
      if ($stmt->execute()) {
        echo "<script>
          alert('Registrasi berhasil!');
          window.location.href = 'login.php';
        </script>";
        exit();
      } else {
        $message = "Registrasi gagal: " . $stmt->error;
      }

      $stmt->close();
    }

    $check->close();
?>


      



<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Registrasi</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
  <div class="container mt-5">
    <div class="row justify-content-center">
      <div class="col-md-6">
        <div class="card shadow p-4">
          <h3 class="text-center mb-4">Registrasi Akun</h3>

          <?php if (!empty($message)): ?>
            <div class="alert alert-danger"><?= $message ?></div>
          <?php endif; ?>
          
          <form method="POST">
          <div class="mb-3">
    <label class="form-label">Role</label>
    <select class="form-select" name="role" required>
      <option value="">Pilih Role</option>
      <option value="admin">Admin</option>
      <option value="user">User</option>
    </select>
  </div>

            <div class="mb-3">
              <label for="username" class="form-label">Username</label>
              <input type="text" class="form-control" name="username" required>
            </div>
            <div class="mb-3">
              <label for="password" class="form-label">Password</label>
              <input type="password" class="form-control" name="password" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Daftar</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
