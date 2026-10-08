<?php
require 'config/database.php';
cekLogin();

$KELAS_LIST    = ['1A','1B','2A','2B','3A','3B','3C','4A','4B','5 IPA','5 IPS','6 IPA','6 IPS'];
$KELOMPOK_LIST = [1,2,3,4,5,6,7,8,9];

// ================== SUB LAPORAN ==================
$sub = $_GET['sub'] ?? '1';
if (!in_array($sub, ['1','2'])) $sub = '1';

$is_naqd = ($sub === '2');

// Konfigurasi sesuai sub
if ($is_naqd) {
    $TABLE       = 'nilai_naqd';
    $TITLE       = 'Laporan Nilai Naqd';
    $SUBTITLE    = 'Rekap Nilai Naqd per Kelompok';
    // Naqd hanya Supervisor 1 & 2
    $KRITERIA_TAMPIL = ['Supervisor 1','Supervisor 2'];
} else {
    $TABLE       = 'nilai';
    $TITLE       = 'Laporan Nilai Supervisor';
    $SUBTITLE    = 'Rekap Nilai Supervisor per Kelompok';
    // Reguler: Supervisor 1, Supervisor 2, Assessor
    $KRITERIA_TAMPIL = ['Supervisor 1','Supervisor 2','Assessor'];
}

// ================== FILTER ==================
$filter_kelompok = $_GET['filter_kelompok'] ?? '';
$filter_kelas    = $_GET['filter_kelas']    ?? '';

// ================== BUILD WHERE ==================
$w = [];
if ($filter_kelompok && in_array((int)$filter_kelompok, $KELOMPOK_LIST)) {
    $w[] = "s.kelompok = " . (int)$filter_kelompok;
}
if ($filter_kelas && in_array($filter_kelas, $KELAS_LIST)) {
    $w[] = "s.kelas = '" . $conn->real_escape_string($filter_kelas) . "'";
}
$where_sql = $w ? "WHERE " . implode(" AND ", $w) : "";

// ================== QUERY UTAMA ==================
// Ambil semua siswa + semua kriteria (rata-rata hasil) dalam satu query
$sql = "
    SELECT s.id AS santri_id, s.induk, s.siswa, s.kelas, s.kelompok,
           ka.supervisor1 AS sup1_kelompok, ka.supervisor2 AS sup2_kelompok,
           n.kriteria, n.hasil
    FROM santri s
    LEFT JOIN kelompok_amaliah ka ON ka.kelompok = s.kelompok
    LEFT JOIN `$TABLE` n ON n.santri_id = s.id
                          AND n.kriteria IN ('Supervisor 1','Supervisor 2','Assessor')
    $where_sql
    ORDER BY s.kelompok, s.kelas, s.siswa
";
$data = $conn->query($sql);
if (!$data) die("Query gagal: " . $conn->error);

// ================== SUSUN DATA ==================
// Struktur: $rows[santri_id] = [ siswa, kelas, kelompok, sup1_kelompok, sup2_kelompok, nilai => [kriteria => hasil] ]
$rows = [];
while ($r = $data->fetch_assoc()) {
    $sid = $r['santri_id'];
    if (!isset($rows[$sid])) {
        $rows[$sid] = [
            'induk'         => $r['induk'],
            'siswa'         => $r['siswa'],
            'kelas'         => $r['kelas'],
            'kelompok'      => $r['kelompok'],
            'sup1_kelompok' => $r['sup1_kelompok'],
            'sup2_kelompok' => $r['sup2_kelompok'],
            'nilai'         => [],
        ];
    }
    if ($r['kriteria'] !== null) {
        $rows[$sid]['nilai'][$r['kriteria']] = (float)$r['hasil'];
    }
}

// ================== GROUPING PER KELOMPOK ==================
$groups = [];
foreach ($rows as $sid => $r) {
    $kelompok = $r['kelompok'] ?? '-';
    $groups[$kelompok][] = $r;
}

// Urutkan kelompok
ksort($groups, SORT_NUMERIC);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?=htmlspecialchars($TITLE)?></title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
    .header-lap {
        text-align: center;
        margin-bottom: 20px;
        border-bottom: 3px double #333;
        padding-bottom: 12px;
    }
    .header-lap img { width: 60px; height: 60px; margin-bottom: 6px; }
    .header-lap h2 { margin: 4px 0; font-size: 18px; }
    .header-lap h3 { margin: 4px 0; font-size: 16px; color: #1e3c72; }
    .header-lap h4 { margin: 4px 0; font-size: 14px; color: #555; font-weight: normal; }

    /* ============ SUB LAPORAN TAB ============ */
    .sub-tabs {
        display: flex; gap: 8px; margin-bottom: 18px;
        border-bottom: 2px solid #e0e6ef; flex-wrap: wrap;
    }
    .sub-tab {
        padding: 10px 22px; border-radius: 8px 8px 0 0;
        text-decoration: none; font-size: 14px; font-weight: 600;
        color: #555; background: #f4f6f9;
        border: 1px solid #d5dbe6; border-bottom: none;
        transition: .15s; display: inline-flex; align-items: center; gap: 8px;
    }
    .sub-tab:hover { background: #e8effa; color: #1e3c72; }
    .sub-tab.active { background: #1e3c72; color: #fff; border-color: #1e3c72; }
    .sub-tab.active-naqd { background: #8e44ad; color: #fff; border-color: #8e44ad; }

    .filter-bar { background:#fff; padding:15px 20px; border-radius:10px;
        box-shadow:0 3px 12px rgba(0,0,0,.08); margin-bottom:15px;
        display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
    .filter-bar label { font-weight:600; color:#444; font-size:13px; }
    .filter-bar select { padding:8px 12px; border:1px solid #ccc; border-radius:6px; font-size:13px; }
    .filter-bar button { padding:8px 20px; background:#1e3c72; color:#fff;
        border:none; border-radius:6px; cursor:pointer; }
    .btn-reset { background:#95a5a6 !important; color:#fff;
        padding:8px 16px; border-radius:6px; text-decoration:none;
        font-size:13px; display:inline-block; }
    .btn-print {
        background:#2c3e50; color:#fff; padding:10px 20px;
        border-radius:6px; border:none; cursor:pointer;
        font-size:14px; margin-bottom:15px;
    }
    .btn-print:hover { background:#1a252f; }

    /* Header kelompok */
    .kelompok-box {
        background: linear-gradient(135deg, #1e3c72, #2a5298);
        color: #fff; padding: 12px 20px; border-radius: 8px 8px 0 0;
        margin-top: 22px; display: flex; justify-content: space-between;
        align-items: center; flex-wrap: wrap; gap: 10px;
    }
    .kelompok-box.naqd { background: linear-gradient(135deg, #6c3483, #8e44ad); }
    .kelompok-box .left { font-weight: bold; font-size: 15px; }
    .kelompok-box .right { font-size: 13px; opacity: .9; }
    .kelompok-box .info-line { font-size: 13px; margin-top: 4px; opacity: .95; }

    table.rekap-table {
        width: 100%; background: #fff; border-collapse: collapse;
        border-radius: 0 0 8px 8px; overflow: hidden;
        box-shadow: 0 3px 12px rgba(0,0,0,.08); margin-bottom: 20px;
    }
    table.rekap-table th {
        background: #34495e; color: #fff;
        padding: 10px 8px; font-size: 12.5px;
        text-align: center; border: 1px solid #2c3e50;
    }
    table.rekap-table th.th-naqd {
        background: #6c3483; border-color: #4a235a;
    }
    table.rekap-table td {
        padding: 8px 10px; font-size: 13px;
        border: 1px solid #e5e5e5; vertical-align: middle;
    }
    table.rekap-table td.center { text-align: center; }
    table.rekap-table td.left   { text-align: left; }
    table.rekap-table tr:nth-child(even) { background: #f9fbff; }
    table.rekap-table tr:hover { background: #eef5ff; }

    .hasil-badge {
        display: inline-block; min-width: 52px;
        padding: 5px 10px; border-radius: 20px;
        font-weight: bold; color: #fff;
        background: #3498db; font-size: 13px;
    }
    .hasil-good { background: #27ae60; }
    .hasil-warn { background: #e67e22; }
    .hasil-bad  { background: #e74c3c; }
    .hasil-none { background: #95a5a6; }

    .badge-kelas { background:#1e3c72; color:#fff; padding:3px 9px;
        border-radius:12px; font-size:11px; font-weight:bold; }
    .badge-kelompok { background:#16a085; color:#fff; padding:3px 10px;
        border-radius:12px; font-size:11px; font-weight:bold; }

    .empty-box {
        background:#fdecea; color:#c0392b; padding:20px;
        border-radius:8px; border-left:4px solid #e74c3c;
        text-align:center; font-size:14px;
    }

    @media print {
        .navbar, .btn-print, .filter-bar, .no-print, .sub-tabs { display: none !important; }
        body { background: #fff; }
        .container { max-width: 100%; margin: 0; padding: 0; }
        .kelompok-box {
            background: #1e3c72 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .kelompok-box.naqd { background: #6c3483 !important; }
        table.rekap-table th {
            background: #34495e !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        table.rekap-table th.th-naqd { background: #6c3483 !important; }
        .hasil-badge, .badge-kelas, .badge-kelompok {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        table.rekap-table { box-shadow: none; }
        .kelompok-box { page-break-after: avoid; }
        tr { page-break-inside: avoid; }
    }
</style>
</head>
<body>
<?php include 'partials/navbar.php'; ?>
<div class="container">

    <!-- ============ HEADER LAPORAN ============ -->
    <div class="header-lap">
        <img src="assets/images/logo-sm.png" alt="Logo">
        <h2>Yayasan Pondok Pesantren Daar el-Qolam</h2>
        <h3><?=htmlspecialchars($SUBTITLE)?></h3>
        <h4>Tahun Pelajaran 2026-2027</h4>
    </div>

    <!-- ============ SUB LAPORAN (TAB) ============ -->
    <div class="sub-tabs no-print">
        <a href="?sub=1<?=($filter_kelompok?'&filter_kelompok='.urlencode($filter_kelompok):'')?><?=($filter_kelas?'&filter_kelas='.urlencode($filter_kelas):'')?>"
           class="sub-tab <?=($sub==='1')?'active':''?>">
            📘 1. Nilai Supervisor
        </a>
        <a href="?sub=2<?=($filter_kelompok?'&filter_kelompok='.urlencode($filter_kelompok):'')?><?=($filter_kelas?'&filter_kelas='.urlencode($filter_kelas):'')?>"
           class="sub-tab <?=($sub==='2')?'active-naqd':''?>">
            📕 2. Nilai Naqd
        </a>
    </div>

    <!-- ============ TOMBOL PRINT ============ -->
    <button onclick="window.print()" class="btn-print no-print">🖨 Cetak Laporan</button>

    <!-- ============ FILTER ============ -->
    <div class="filter-bar no-print">
        <b>🔍 Filter:</b>
        <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <input type="hidden" name="sub" value="<?=htmlspecialchars($sub)?>">

            <label>Kelompok:</label>
            <select name="filter_kelompok">
                <option value="">-- Semua Kelompok --</option>
                <?php foreach($KELOMPOK_LIST as $kl): ?>
                    <option value="<?=$kl?>" <?=($filter_kelompok==$kl)?'selected':''?>>
                        Kelompok <?=$kl?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Kelas:</label>
            <select name="filter_kelas">
                <option value="">-- Semua Kelas --</option>
                <?php foreach($KELAS_LIST as $k): ?>
                    <option value="<?=$k?>" <?=($filter_kelas==$k)?'selected':''?>>
                        <?=$k?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Tampilkan</button>

            <?php if($filter_kelompok || $filter_kelas): ?>
                <a href="laporan_siswa.php?sub=<?=htmlspecialchars($sub)?>"
                   class="btn-reset">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ============ INFO SUB LAPORAN ============ -->
    <div class="no-print" style="background:#eaf4ff;border-left:4px solid #3498db;
         padding:10px 15px;border-radius:6px;margin-bottom:15px;font-size:13px;">
        <?php if ($is_naqd): ?>
            📕 <b>Sub Laporan 2: Nilai Naqd</b> — Rata-rata nilai per siswa
            (Supervisor 1, Supervisor 2) untuk kegiatan Naqd.
        <?php else: ?>
            📘 <b>Sub Laporan 1: Nilai Supervisor</b> — Rata-rata nilai per siswa
            (Supervisor 1, Supervisor 2, Assessor) untuk kegiatan Idad.
        <?php endif; ?>
    </div>

    <?php if (empty($groups)): ?>
        <div class="empty-box">
            <b>📭 Belum ada data siswa</b><br>
            untuk filter yang dipilih.<br>
            Silakan input data di menu <b>Santri</b> / <b>Input Nilai</b> terlebih dahulu.
        </div>
    <?php else: ?>

        <?php foreach($groups as $kelompok => $siswa_rows): 
            $sup1 = $siswa_rows[0]['sup1_kelompok'] ?? '-';
            $sup2 = $siswa_rows[0]['sup2_kelompok'] ?? '-';
            $musrif_tampil = $sup1;
            if ($sup1 !== '-' && $sup2 !== '-' && $sup1 !== $sup2) {
                $musrif_tampil = $sup1 . ' & ' . $sup2;
            } elseif ($sup1 === '-') {
                $musrif_tampil = $sup2;
            }
            $total_siswa = count($siswa_rows);
        ?>

        <!-- ============ HEADER KELOMPOK ============ -->
        <div class="kelompok-box <?=$is_naqd?'naqd':''?>">
            <div class="left">
                <div>📁 Kelompok : <?=htmlspecialchars($kelompok)?></div>
                <div class="info-line">🎓 Musrif : <?=htmlspecialchars($musrif_tampil)?></div>
            </div>
            <div class="right">
                <?= $total_siswa ?> siswa &nbsp;|&nbsp;
                <?=htmlspecialchars($is_naqd ? 'Naqd' : 'Supervisor')?>
            </div>
        </div>

        <!-- ============ TABEL REKAP ============ -->
        <table class="rekap-table">
            <thead>
                <tr>
                    <th style="width:40px;">No</th>
                    <th>Nama Siswa</th>
                    <th style="width:90px;">Kelas</th>
                    <?php foreach ($KRITERIA_TAMPIL as $krit): ?>
                        <th style="width:100px;" class="<?=$is_naqd?'th-naqd':''?>">
                            <?=htmlspecialchars($krit)?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php $no=1; foreach($siswa_rows as $r): ?>
                <tr>
                    <td class="center"><?=$no++?></td>
                    <td class="left">
                        <b><?=htmlspecialchars($r['siswa'])?></b><br>
                        <small style="color:#888;"><?=htmlspecialchars($r['induk'])?></small>
                    </td>
                    <td class="center">
                        <span class="badge-kelas"><?=htmlspecialchars($r['kelas'])?></span>
                    </td>
                    <?php foreach ($KRITERIA_TAMPIL as $krit): 
                        $nilai = $r['nilai'][$krit] ?? null;
                        $class = 'hasil-badge';
                        if ($nilai !== null) {
                            if ($nilai >= 8)      $class .= ' hasil-good';
                            elseif ($nilai >= 6)  $class .= ' hasil-warn';
                            elseif ($nilai > 0)   $class .= ' hasil-bad';
                            else                  $class .= ' hasil-none';
                        } else {
                            $class .= ' hasil-none';
                        }
                    ?>
                        <td class="center">
                            <span class="<?=$class?>">
                                <?= $nilai !== null ? number_format($nilai, 2) : '-' ?>
                            </span>
                        </td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php endforeach; ?>

    <?php endif; ?>

</div>
</body>
</html>