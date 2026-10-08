<?php
require 'config/database.php';
cekLogin();

$data = $conn->query("SELECT * FROM guru ORDER BY kriteria, nama_guru");
if (!$data) {
    die("Query gagal: " . $conn->error);
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Laporan Guru</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include 'partials/navbar.php'; ?>
<div class="container">
<h2>Laporan Data Guru</h2>
<button onclick="window.print()" class="btn-print">🖨 Cetak</button>
<table>
<tr><th>No</th><th>Nama Guru</th><th>Mata Pelajaran</th><th>Kriteria</th></tr>
<?php if ($data->num_rows > 0): ?>
    <?php $i=1; while($g=$data->fetch_assoc()): ?>
    <tr>
        <td><?=$i++?></td>
        <td><?=htmlspecialchars($g['nama_guru'])?></td>
        <td><?=htmlspecialchars($g['mata_pelajaran'])?></td>
        <td><?=htmlspecialchars($g['kriteria'])?></td>
    </tr>
    <?php endwhile; ?>
<?php else: ?>
    <tr><td colspan="4" style="text-align:center;">Belum ada data guru.</td></tr>
<?php endif; ?>
</table>
</div>
</body>
</html>