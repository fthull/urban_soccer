<?php
session_start();
// Menghapus seluruh data sesi (session) yang sedang aktif untuk pengguna tersebut
session_destroy();
header("Location: login.php");
exit();
