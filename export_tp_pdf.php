<?php
require 'config/database.php';
cekLogin();

$KELAS_LIST    = ['1A','1B','2A','2B','3A','3B','3C','4A','4B','5 IPA','5 IPS','6 IPA','6 IPS'];
$KELOMPOK_LIST = [1,2,3,4,5,6,7,8,9];

$filter_kelas    = $_GET['filter_kelas'] ?? '';
$filter_kelompok = $_GET['filter_kelompok'] ?? '';
$filter_tgl      = $_GET['filter_tgl'] ?? '';

$where = [];
if ($filter_kelas && in_array($filter_kelas, $KELAS_LIST)) {
    $where[] = "tp.kelas_tp='" . $conn->real_escape_string($filter_kelas) . "'";
}
if ($filter_tgl) {
    $where[] = "tp.tanggal='" . $conn->real_escape_string($filter_tgl) . "'";
}
if ($filter_kelompok && in_array((int)$filter_kelompok, $KELOMPOK_LIST)) {
    $where[] = "tp.kelompok=" . (int)$filter_kelompok;
}
$where_sql = $where ? "WHERE " . implode(" AND ", $where) : '';

$data = $conn->query("SELECT tp.*, s.siswa, s.kelas, s.induk
                      FROM teaching_practice tp
                      JOIN santri s ON s.id=tp.santri_id
                      $where_sql
                      ORDER BY tp.tanggal DESC, tp.kelas_tp, tp.kelompok, s.siswa");
if (!$data) die("Query gagal: " . $conn->error);

$rows = [];
while ($d = $data->fetch_assoc()) $rows[] = $d;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Data Teaching Practice</title>
<style>
    @page {
        size: A4 landscape;
        margin: 12mm;
    }
    * { box-sizing: border-box; }
    body {
        font-family: 'Segoe UI', Arial, sans-serif;
        font-size: 11px;
        color: #222;
        margin: 0;
        padding: 15px;
    }

    /* ============ HEADER ============ */
    .header {
        text-align: center;
        border-bottom: 3px double #1e3c72;
        padding-bottom: 10px;
        margin-bottom: 12px;
    }
    .header img {
        width: 55px;
        height: 55px;
        margin-bottom: 4px;
    }
    .header h1 {
        margin: 3px 0;
        font-size: 16px;
        color: #1e3c72;
        letter-spacing: .5px;
    }
    .header h2 {
        margin: 3px 0;
        font-size: 13px;
        font-weight: normal;
        color: #333;
    }
    .header .tp {
        font-size: 11px;
        color: #666;
        margin-top: 2px;
    }

    /* ============ INFO FILTER ============ */
    .info-filter {
        background: #f0f4f8;
        padding: 6px 12px;
        border-left: 3px solid #1e3c72;
        font-size: 10px;
        margin-bottom: 10px;
        border-radius: 3px;
    }

    /* ============ TABEL ============ */
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 10px;
    }
    thead th {
        background: #1e3c72;
        color: #fff;
        padding: 7px 5px;
        text-align: center;
        border: 1px solid #0f2547;
        font-weight: 600;
    }
    tbody td {
        padding: 5px;
        border: 1px solid #bbb;
        vertical-align: top;
    }
    tbody tr:nth-child(even) { background: #f9fbff; }
    tbody tr:hover { background: #eef5ff; }

    td.center { text-align: center; }
    td.right  { text-align: right; }

    /* ============ FOOTER ============ */
    .footer {
        margin-top: 12px;
        display: flex;
        justify-content: space-between;
        font-size: 10px;
        color: #666;
    }
    .total-box {
        background: #fff8e1;
        border: 1px solid #f39c12;
        padding: 6px 12px;
        border-radius: 4px;
        font-weight: bold;
        color: #7a5a00;
    }

    /* ============ TOMBOL PRINT ============ */
    .no-print {
        text-align: center;
        margin-bottom: 15px;
        padding: 10px;
        background: #eaf4ff;
        border-radius: 6px;
    }
    .btn-print-now {
        background: #e74c3c;
        color: #fff;
        padding: 10px 22px;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: bold;
        cursor: pointer;
        margin-right: 8px;
    }
    .btn-print-now:hover { background: #c0392b; }
    .btn-back {
        background: #95a5a6;
        color: #fff;
        padding: 10px 22px;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
    }
    .btn-back:hover { background: #7f8c8d; }

    @media print {
        .no-print { display: none !important; }
        body { padding: 0; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    }
</style>
</head>
<body>

<!-- ============ TOMBOL PRINT (tidak ikut tercetak) ============ -->
<div class="no-print">
    <button class="btn-print-now" onclick="window.print()">🖨 Cetak / Simpan sebagai PDF</button>
    <a href="teaching_practice.php" class="btn-back">← Kembali</a>
</div>

<!-- ============ HEADER LAPORAN ============ -->
<div class="header">
    <img src="assets/images/logo-sm.png" alt="Logo">
    <h1>YAYASAN PONDOK PESANTREN DAAR EL-QOLAM</h1>
    <h2>Data Teaching Practice</h2>
    <div class="tp">Tahun Pelajaran 2026-2027</div>
</div>

<!-- ============ INFO FILTER ============ -->
<div class="info-filter">
    <b>Filter:</b>
    Kelas TP = <b><?= htmlspecialchars($filter_kelas ?: 'Semua') ?></b> &nbsp;|&nbsp;
    Kelompok = <b><?= htmlspecialchars($filter_kelompok ?: 'Semua') ?></b> &nbsp;|&nbsp;
    Tanggal = <b><?= htmlspecialchars($filter_tgl ?: 'Semua') ?></b> &nbsp;|&nbsp;
    Dicetak: <b><?= date('d/m/Y H:i') ?></b>
</div>

<!-- ============ TABEL DATA ============ -->
<table>
    <thead>
        <tr>
            <th style="width:3%;">No</th>
            <th style="width:7%;">Induk</th>
            <th style="width:16%;">Siswa</th>
            <th style="width:6%;">Kelas</th>
            <th style="width:6%;">Kelas TP</th>
            <th style="width:5%;">Kel.</th>
            <th style="width:9%;">Mapel</th>
            <th style="width:13%;">Judul</th>
            <th style="width:10%;">Sup 1</th>
            <th style="width:10%;">Sup 2</th>
            <th style="width:10%;">Assessor</th>
            <th style="width:5%;">Tanggal</th>
        </tr>
    </thead>
    <tbody>
    <?php if (count($rows) > 0): ?>
        <?php $i = 1; foreach ($rows as $d): ?>
        <tr>
            <td class="center"><?= $i++ ?></td>
            <td class="center"><?= htmlspecialchars($d['induk']) ?></td>
            <td><?= htmlspecialchars($d['siswa']) ?></td>
            <td class="center"><?= htmlspecialchars($d['kelas']) ?></td>
            <td class="center"><?= htmlspecialchars($d['kelas_tp']) ?></td>
            <td class="center"><?= htmlspecialchars($d['kelompok']) ?></td>
            <td><?= htmlspecialchars($d['mata_pelajaran']) ?></td>
            <td><?= htmlspecialchars($d['judul']) ?></td>
            <td><?= htmlspecialchars($d['supervisor1'] ?? '-') ?></td>
            <td><?= htmlspecialchars($d['supervisor2'] ?? '-') ?></td>
            <td><?= htmlspecialchars($d['assessor'] ?? '-') ?></td>
            <td class="center"><?= date('d/m/Y', strtotime($d['tanggal'])) ?></td>
        </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="12" class="center" style="padding:20px;color:#999;font-style:italic;">
                Belum ada data teaching practice.
            </td>
        </tr>
    <?php endif; ?>
    </tbody>
</table>

<!-- ============ FOOTER TOTAL ============ -->
<div class="footer">
    <div class="total-box">
        📊 TOTAL: <?= count($rows) ?> data
    </div>
    <div>
        Dicetak dari <b>App Amaliah Tadris</b> — <?= date('d F Y, H:i') ?> WIB
    </div>
</div>

<script>
// Auto-trigger print saat halaman dibuka
window.addEventListener('load', function() {
    // Beri waktu 500ms agar logo/gambar ter-load
    setTimeout(function() {
        // Uncomment baris berikut jika ingin langsung print otomatis:
        // window.print();
    }, 500);
});
</script>
</body>
</html>