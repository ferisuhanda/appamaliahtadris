<?php
require 'config/database.php';
cekLogin();

$KELOMPOK_LIST = [1,2,3,4,5,6,7,8,9];
$KRITERIA_LIST = ['Supervisor 1','Supervisor 2','Assessor'];

// ================== SUB LAPORAN ==================
// 1 = Laporan Supervisor (idad)
// 2 = Laporan Naqd
$sub = $_GET['sub'] ?? '1';
if (!in_array($sub, ['1','2'])) $sub = '1';

// Tabel & label sesuai sub
if ($sub === '2') {
    $TABLE       = 'nilai_naqd';
    $TITLE       = 'Laporan Nilai Naqd';
    $SUBTITLE    = 'Nilai Naqd Kelompok Amaliah Tadris';
    $LABEL_KRIT  = 'Naqd';
} else {
    $TABLE       = 'nilai';
    $TITLE       = 'Laporan Supervisor';
    $SUBTITLE    = 'Nilai Kelompok Amaliah Tadris';
    $LABEL_KRIT  = 'Supervisor';
}

// ================== FILTER ==================
$filter_kriteria = $_GET['filter_kriteria'] ?? 'Supervisor 1';
$filter_kelompok = $_GET['filter_kelompok'] ?? '';
$filter_sup      = $_GET['filter_sup'] ?? '';

if (!in_array($filter_kriteria, $KRITERIA_LIST)) $filter_kriteria = 'Supervisor 1';

// ================== BUILD WHERE ==================
$w = [];
$w[] = "n.kriteria = '" . $conn->real_escape_string($filter_kriteria) . "'";

if ($filter_kelompok && in_array((int)$filter_kelompok, $KELOMPOK_LIST)) {
    $w[] = "n.kelompok = " . (int)$filter_kelompok;
}
if ($filter_sup) {
    $s = $conn->real_escape_string($filter_sup);
    $w[] = "n.penilai = '$s'";
}
$where_sql = "WHERE " . implode(" AND ", $w);

// ================== QUERY DATA ==================
$sql = "
    SELECT n.*, s.siswa, s.kelas, s.induk,
           ka.supervisor1 AS kelompok_sup1,
           ka.supervisor2 AS kelompok_sup2
    FROM `$TABLE` n
    JOIN santri s ON s.id = n.santri_id
    LEFT JOIN kelompok_amaliah ka ON ka.kelompok = n.kelompok
    $where_sql
    ORDER BY n.kelompok, s.siswa
";
$data = $conn->query($sql);
if (!$data) die("Query gagal: " . $conn->error);

// ================== GROUPING BY KELOMPOK ==================
$groups = [];
while ($row = $data->fetch_assoc()) {
    $groups[$row['kelompok']][] = $row;
}

// ================== DROPDOWN SUPERVISOR ==================
$sup_options = [];
$q_sup = $conn->query("SELECT DISTINCT nama_guru FROM guru 
                       WHERE kriteria IN ('Supervisor 1','Supervisor 2') 
                       ORDER BY nama_guru");
if ($q_sup) while ($s = $q_sup->fetch_assoc()) $sup_options[] = $s['nama_guru'];

// ================== DROPDOWN PENILAI ==================
$penilai_options = [];
$q_pen = $conn->query("SELECT DISTINCT penilai FROM `$TABLE` 
                       WHERE kriteria='" . $conn->real_escape_string($filter_kriteria) . "'
                       ORDER BY penilai");
if ($q_pen) while ($s = $q_pen->fetch_assoc()) $penilai_options[] = $s['penilai'];
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
    .header-lap img {
        width: 60px; height: 60px; margin-bottom: 6px;
    }
    .header-lap h2 { margin: 4px 0; font-size: 18px; }
    .header-lap h3 { margin: 4px 0; font-size: 16px; color: #1e3c72; }
    .header-lap h4 { margin: 4px 0; font-size: 14px; color: #555; font-weight: normal; }

    /* ============ SUB LAPORAN TAB ============ */
    .sub-tabs {
        display: flex;
        gap: 8px;
        margin-bottom: 18px;
        border-bottom: 2px solid #e0e6ef;
        padding-bottom: 0;
        flex-wrap: wrap;
    }
    .sub-tab {
        padding: 10px 22px;
        border-radius: 8px 8px 0 0;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        color: #555;
        background: #f4f6f9;
        border: 1px solid #d5dbe6;
        border-bottom: none;
        transition: .15s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .sub-tab:hover {
        background: #e8effa;
        color: #1e3c72;
    }
    .sub-tab.active {
        background: #1e3c72;
        color: #fff;
        border-color: #1e3c72;
    }
    .sub-tab.active-naqd {
        background: #8e44ad;
        color: #fff;
        border-color: #8e44ad;
    }
    .sub-tab .icon {
        font-size: 16px;
    }

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
        color: #fff;
        padding: 12px 20px;
        border-radius: 8px 8px 0 0;
        margin-top: 22px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    .kelompok-box.naqd {
        background: linear-gradient(135deg, #6c3483, #8e44ad);
    }
    .kelompok-box .left { font-weight: bold; font-size: 15px; }
    .kelompok-box .right { font-size: 13px; opacity: .9; }
    .kelompok-box .info-line { font-size: 13px; margin-top: 4px; opacity: .95; }

    table.rekap-table {
        width: 100%;
        background: #fff;
        border-collapse: collapse;
        border-radius: 0 0 8px 8px;
        overflow: hidden;
        box-shadow: 0 3px 12px rgba(0,0,0,.08);
        margin-bottom: 20px;
    }
    table.rekap-table th {
        background: #34495e; color: #fff;
        padding: 10px 8px; font-size: 12.5px;
        text-align: center; border: 1px solid #2c3e50;
    }
    table.rekap-table th.th-naqd {
        background: #6c3483;
        border-color: #4a235a;
    }
    table.rekap-table td {
        padding: 8px 10px; font-size: 13px;
        border: 1px solid #e5e5e5; vertical-align: middle;
    }
    table.rekap-table td.center { text-align: center; }
    table.rekap-table td.left   { text-align: left; }
    table.rekap-table tr:nth-child(even) { background: #f9fbff; }
    table.rekap-table tr:hover { background: #eef5ff; }

    .nilai-cell {
        display: inline-block;
        min-width: 22px; padding: 2px 6px;
        background: #eaf4ff; color: #1e3c72;
        border-radius: 4px; font-weight: bold;
        margin: 0 1px; font-size: 12px;
    }
    .nilai-cell-naqd {
        background: #f4ecf7; color: #6c3483;
    }
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

    .empty-box {
        background:#fdecea; color:#c0392b;
        padding:20px; border-radius:8px;
        border-left:4px solid #e74c3c;
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
        .kelompok-box.naqd {
            background: #6c3483 !important;
        }
        table.rekap-table th {
            background: #34495e !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        table.rekap-table th.th-naqd {
            background: #6c3483 !important;
        }
        .hasil-badge, .badge-kelas, .nilai-cell, .nilai-cell-naqd {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        table.rekap-table { box-shadow: none; }
        .kelompok-box { page-break-after: avoid; }
        table.rekap-table { page-break-inside: auto; }
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
        <a href="?sub=1&filter_kriteria=<?=urlencode($filter_kriteria)?><?=($filter_kelompok?'&filter_kelompok='.urlencode($filter_kelompok):'')?><?=($filter_sup?'&filter_sup='.urlencode($filter_sup):'')?>"
           class="sub-tab <?=($sub==='1')?'active':''?>">
            <span class="icon">📘</span> 1. Laporan Supervisor
        </a>
        <a href="?sub=2&filter_kriteria=<?=urlencode($filter_kriteria)?><?=($filter_kelompok?'&filter_kelompok='.urlencode($filter_kelompok):'')?><?=($filter_sup?'&filter_sup='.urlencode($filter_sup):'')?>"
           class="sub-tab <?=($sub==='2')?'active-naqd':''?>">
            <span class="icon">📕</span> 2. Laporan Naqd
        </a>
    </div>

    <!-- ============ TOMBOL PRINT ============ -->
    <button onclick="window.print()" class="btn-print no-print">🖨 Cetak Laporan</button>

    <!-- ============ FILTER ============ -->
    <div class="filter-bar no-print">
        <b>🔍 Filter:</b>
        <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <input type="hidden" name="sub" value="<?=htmlspecialchars($sub)?>">

            <label>Kriteria:</label>
            <select name="filter_kriteria" onchange="this.form.submit()">
                <?php foreach($KRITERIA_LIST as $k): ?>
                    <option value="<?=$k?>" <?=($filter_kriteria==$k)?'selected':''?>>
                        <?=$k?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Kelompok:</label>
            <select name="filter_kelompok">
                <option value="">-- Semua Kelompok --</option>
                <?php foreach($KELOMPOK_LIST as $kl): ?>
                    <option value="<?=$kl?>" <?=($filter_kelompok==$kl)?'selected':''?>>
                        Kelompok <?=$kl?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Musrif:</label>
            <select name="filter_sup">
                <option value="">-- Semua Musrif --</option>
                <?php foreach($penilai_options as $p): ?>
                    <option value="<?=htmlspecialchars($p)?>"
                        <?=($filter_sup==$p)?'selected':''?>>
                        <?=htmlspecialchars($p)?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Tampilkan</button>

            <?php if($filter_kelompok || $filter_sup): ?>
                <a href="laporan_supervisor.php?sub=<?=htmlspecialchars($sub)?>&filter_kriteria=<?=urlencode($filter_kriteria)?>"
                   class="btn-reset">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ============ INFO SUB LAPORAN ============ -->
    <div class="no-print" style="background:#eaf4ff;border-left:4px solid #3498db;
         padding:10px 15px;border-radius:6px;margin-bottom:15px;font-size:13px;">
        <?php if ($sub === '2'): ?>
            📕 <b>Sub Laporan 2: Nilai Naqd</b> — Menampilkan nilai kegiatan Naqd
            (kesungguhan naqd, bahasa naqd, aktif komentar, koperatif, menilai, melaksanakan tugas).
        <?php else: ?>
            📘 <b>Sub Laporan 1: Nilai Supervisor (Idad)</b> — Menampilkan nilai kegiatan Idad
            (penguasaan, penulisan, interaksi, naqd kesalahan, kesungguhan, aktif).
        <?php endif; ?>
    </div>

    <?php if (empty($groups)): ?>
        <div class="empty-box">
            <b>📭 Belum ada data nilai <?=htmlspecialchars($LABEL_KRIT)?></b><br>
            untuk kriteria <b><?=htmlspecialchars($filter_kriteria)?></b>
            <?= $filter_kelompok ? " kelompok <b>$filter_kelompok</b>" : '' ?>.<br>
            Silakan input nilai di menu
            <b><?= $sub === '2' ? 'Input Nilai Naqd' : 'Input Nilai' ?></b> terlebih dahulu.
        </div>
    <?php else: ?>

        <?php foreach($groups as $kelompok => $rows): 
            $musrif_1 = $rows[0]['kelompok_sup1'] ?? '-';
            $musrif_2 = $rows[0]['kelompok_sup2'] ?? '-';
            $musrif_tampil = $musrif_1;
            if ($musrif_1 !== '-' && $musrif_2 !== '-' && $musrif_1 !== $musrif_2) {
                $musrif_tampil = $musrif_1 . ' & ' . $musrif_2;
            } elseif ($musrif_1 === '-') {
                $musrif_tampil = $musrif_2;
            }
            $total_siswa = count($rows);
            $is_naqd = ($sub === '2');
        ?>

        <!-- ============ HEADER KELOMPOK ============ -->
        <div class="kelompok-box <?=$is_naqd?'naqd':''?>">
            <div class="left">
                <div>📁 Kelompok : <?=htmlspecialchars($kelompok)?></div>
                <div class="info-line">🎓 Musrif : <?=htmlspecialchars($musrif_tampil)?></div>
            </div>
            <div class="right">
                <?= $total_siswa ?> siswa &nbsp;|&nbsp;
                Kriteria: <?=htmlspecialchars($filter_kriteria)?>
            </div>
        </div>

        <!-- ============ TABEL REKAP ============ -->
        <table class="rekap-table">
            <thead>
                <tr>
                    <th style="width:40px;">No</th>
                    <th style="width:90px;">Induk</th>
                    <th>Nama Siswa</th>
                    <th style="width:70px;">Kelas</th>
                    <th style="width:60px;" class="<?=$is_naqd?'th-naqd':''?>">a</th>
                    <th style="width:60px;" class="<?=$is_naqd?'th-naqd':''?>">b</th>
                    <th style="width:60px;" class="<?=$is_naqd?'th-naqd':''?>">c</th>
                    <th style="width:60px;" class="<?=$is_naqd?'th-naqd':''?>">d</th>
                    <th style="width:60px;" class="<?=$is_naqd?'th-naqd':''?>">e</th>
                    <th style="width:60px;" class="<?=$is_naqd?'th-naqd':''?>">f</th>
                    <th style="width:90px;" class="<?=$is_naqd?'th-naqd':''?>">Hasil</th>
                </tr>
            </thead>
            <tbody>
            <?php $no=1; foreach($rows as $r): 
                $hasil_val = (float)$r['hasil'];
                $hasil_class = 'hasil-badge';
                if ($hasil_val >= 8)      $hasil_class .= ' hasil-good';
                elseif ($hasil_val >= 6)  $hasil_class .= ' hasil-warn';
                elseif ($hasil_val > 0)   $hasil_class .= ' hasil-bad';
                else                      $hasil_class .= ' hasil-none';

                $cell_class = 'nilai-cell' . ($is_naqd ? ' nilai-cell-naqd' : '');
            ?>
                <tr>
                    <td class="center"><?=$no++?></td>
                    <td class="center"><?=htmlspecialchars($r['induk'])?></td>
                    <td class="left"><b><?=htmlspecialchars($r['siswa'])?></b></td>
                    <td class="center">
                        <span class="badge-kelas"><?=htmlspecialchars($r['kelas'])?></span>
                    </td>
                    <td class="center"><span class="<?=$cell_class?>"><?=(int)$r['n1']?></span></td>
                    <td class="center"><span class="<?=$cell_class?>"><?=(int)$r['n2']?></span></td>
                    <td class="center"><span class="<?=$cell_class?>"><?=(int)$r['n3']?></span></td>
                    <td class="center"><span class="<?=$cell_class?>"><?=(int)$r['n4']?></span></td>
                    <td class="center"><span class="<?=$cell_class?>"><?=(int)$r['n5']?></span></td>
                    <td class="center"><span class="<?=$cell_class?>"><?=(int)$r['n6']?></span></td>
                    <td class="center">
                        <span class="<?=$hasil_class?>">
                            <?= number_format($hasil_val, 2) ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <?php endforeach; ?>

    <?php endif; ?>

</div>
</body>
</html>