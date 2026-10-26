<?php
/**
 * auth/generate_hash.php
 * -----------------------------------------------------------
 * Jalankan file ini SEKALI lewat browser untuk membuat hash
 * bcrypt yang valid untuk password admin default, lalu
 * salin hasilnya ke kolom `password` pada tabel `users`
 * di phpMyAdmin. Setelah selesai, HAPUS file ini dari server.
 * -----------------------------------------------------------
 */

$password = 'admin123'; // ganti sesuai keinginan sebelum dijalankan
$hash = password_hash($password, PASSWORD_BCRYPT);

echo "Password  : " . htmlspecialchars($password) . "<br>";
echo "Hash bcrypt: " . htmlspecialchars($hash) . "<br><br>";
echo "Jalankan query berikut di phpMyAdmin:<br>";
echo "<code>UPDATE users SET password = '" . htmlspecialchars($hash) . "' WHERE username = 'admin';</code>";
