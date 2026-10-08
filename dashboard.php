<?php
require 'config/database.php';
cekLogin();

$hari = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu',
         'Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];
$hari_ini = $hari[date('l')];
$tanggal  = date('d F Y');
$jam      = date('H:i:s');

// ================== QUERY AMAN ==================
$supervisors = $conn->query("SELECT nama_guru, kriteria FROM guru 
                             WHERE kriteria IN ('Supervisor 1','Supervisor 2','Assessor') 
                             ORDER BY kriteria");
if (!$supervisors) $supervisors = false;

$total_siswa = 0;
$q = $conn->query("SELECT COUNT(*) AS t FROM santri");
if ($q) $total_siswa = $q->fetch_assoc()['t'] ?? 0;

$total_tp = 0;
$q = $conn->query("SELECT COUNT(*) AS t FROM teaching_practice");
if ($q) $total_tp = $q->fetch_assoc()['t'] ?? 0;

// Siswa aktif = yang punya teaching practice
$siswa_aktif = $conn->query("SELECT s.*, tp.tanggal FROM santri s
                             INNER JOIN teaching_practice tp ON s.id=tp.santri_id
                             ORDER BY tp.tanggal DESC LIMIT 5");
if (!$siswa_aktif) $siswa_aktif = false;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Dashboard</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<?php include 'partials/navbar.php'; ?>
<div class="container">
    <div class="header-dash">
        <img src="assets/images/logo-sm.png" class="logo-xs" alt="Logo">
        <h2>Dashboard</h2>
    </div>

    <div class="clock-card">
        <div class="day"><?= $hari_ini ?></div>
        <div class="date"><?= $tanggal ?></div>
        <div class="clock" id="clock"><?= $jam ?></div>
    </div>

    <div class="stats">
        <div class="stat-card"><h3><?= $total_siswa ?></h3><p>Siswa Aktif</p></div>
        <div class="stat-card"><h3><?= $total_tp ?></h3><p>Teaching Practice</p></div>
    </div>

    <div class="grid-2">
        <div class="card">
            <h3>Supervisor</h3>
            <ul>
                <?php if ($supervisors && $supervisors->num_rows > 0): ?>
                    <?php while($s = $supervisors->fetch_assoc()): ?>
                    <li><b><?= htmlspecialchars($s['kriteria']) ?>:</b>
                        <?= htmlspecialchars($s['nama_guru']) ?></li>
                    <?php endwhile; ?>
                <?php else: ?>
                    <li style="color:#999;">Belum ada data supervisor.</li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="card">
            <h3>Siswa Aktif Terbaru</h3>
            <table>
                <tr><th>Induk</th><th>Nama</th><th>Kelas</th></tr>
                <?php if ($siswa_aktif && $siswa_aktif->num_rows > 0): ?>
                    <?php while($s = $siswa_aktif->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($s['induk']) ?></td>
                        <td><?= htmlspecialchars($s['siswa']) ?></td>
                        <td><?= htmlspecialchars($s['kelas']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="3" style="text-align:center;color:#999;">Belum ada data.</td></tr>
                <?php endif; ?>
            </table>
        </div>
    </div>
</div>
<script>
setInterval(()=>{
    const d = new Date();
    document.getElementById('clock').innerText = d.toLocaleTimeString('id-ID');
},1000);
</script>
</body>
</html>