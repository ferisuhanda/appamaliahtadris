<?php
require 'config/database.php';
cekLogin();

// ================== KONFIGURASI ==================
$KELAS_LIST    = ['1A','1B','2A','2B','3A','3B','3C','4A','4B','5 IPA','5 IPS','6 IPA','6 IPS'];
$KELOMPOK_LIST = [1,2,3,4,5,6,7,8,9];
$KRITERIA_LIST = ['Supervisor 1','Supervisor 2','Assessor'];
$NILAI_OPTIONS = [4,5,6,7,8,9];

// ================== PROSES SIMPAN NILAI ==================
$msg = ''; $msg_type = 'success';

if (isset($_POST['simpan_nilai'])) {
    $kelas_tp  = $_POST['kelas_tp'] ?? '';
    $kelompok  = (int)($_POST['kelompok'] ?? 0);
    $kriteria  = $_POST['kriteria'] ?? '';

    if (!in_array($kelas_tp, $KELAS_LIST) ||
        !in_array($kelompok, $KELOMPOK_LIST) ||
        !in_array($kriteria, $KRITERIA_LIST)) {
        $msg = "❌ Data header tidak valid!";
        $msg_type = 'error';
    } else {
        $santri_ids  = $_POST['santri_id'] ?? [];
        $penilai_arr = $_POST['penilai']   ?? [];
        $n_arr       = $_POST['n']         ?? [];

        if (!is_array($santri_ids) || count($santri_ids) === 0) {
            $msg = "❌ Tidak ada data siswa untuk disimpan.";
            $msg_type = 'error';
        } else {
            $tanggal = date('Y-m-d');
            $sql = "INSERT INTO nilai
                    (santri_id,kelas_tp,kelompok,kriteria,penilai,n1,n2,n3,n4,n5,n6,hasil,tanggal)
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
                    ON DUPLICATE KEY UPDATE
                      penilai=VALUES(penilai),
                      n1=VALUES(n1), n2=VALUES(n2), n3=VALUES(n3),
                      n4=VALUES(n4), n5=VALUES(n5), n6=VALUES(n6),
                      hasil=VALUES(hasil), tanggal=VALUES(tanggal)";
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                $msg = "Prepare gagal: " . $conn->error;
                $msg_type = 'error';
            } else {
                $sukses = 0; $gagal = 0;
                foreach ($santri_ids as $idx => $santri_id) {
                    $santri_id = (int)$santri_id;
                    $penilai   = trim($penilai_arr[$idx] ?? '');

                    if ($santri_id <= 0 || $penilai === '') { $gagal++; continue; }

                    $n = [];
                    $total = 0; $count = 0;
                    for ($i = 1; $i <= 6; $i++) {
                        $val = (int)($n_arr[$idx][$i] ?? 0);
                        if (!in_array($val, $NILAI_OPTIONS)) $val = 0;
                        $n[$i] = $val;
                        if ($val > 0) { $total += $val; $count++; }
                    }
                    $hasil = ($count === 6) ? round($total / 6, 2) : 0;

                    $stmt->bind_param("isisiiiiiiids",
                        $santri_id, $kelas_tp, $kelompok, $kriteria, $penilai,
                        $n[1],$n[2],$n[3],$n[4],$n[5],$n[6], $hasil, $tanggal);
                    if ($stmt->execute()) $sukses++; else $gagal++;
                }
                $stmt->close();
                $msg = "✅ Selesai! Berhasil: <b>$sukses</b>, Gagal: <b>$gagal</b>";
                if ($gagal > 0) $msg_type = 'error';
            }
        }
    }
}

// ================== FILTER ==================
$filter_kriteria = $_GET['filter_kriteria'] ?? 'Supervisor 1';
$filter_kelompok = $_GET['filter_kelompok'] ?? '1';
$filter_sup      = $_GET['filter_sup'] ?? '';

if (!in_array($filter_kriteria, $KRITERIA_LIST)) $filter_kriteria = 'Supervisor 1';
if ($filter_kelompok !== '' && !in_array((int)$filter_kelompok, $KELOMPOK_LIST)) $filter_kelompok = '1';

// ================== BUILD WHERE (HANYA UNTUK tp) ==================
$w = [];

if ($filter_kelompok !== '') {
    $w[] = "tp.kelompok=" . (int)$filter_kelompok;
}
if ($filter_sup) {
    $s = $conn->real_escape_string($filter_sup);
    $w[] = "(tp.supervisor1='$s' OR tp.supervisor2='$s')";
}

$where_sql = $w ? "WHERE " . implode(" AND ", $w) : "";

// ================== QUERY DATA (FIX: kriteria di ON clause) ==================
$sql_data = "
    SELECT tp.id AS tp_id, tp.santri_id, tp.kelas_tp, tp.kelompok,
           tp.supervisor1, tp.supervisor2, tp.assessor, tp.tanggal,
           s.induk, s.siswa, s.kelas,
           n.id AS nilai_id, n.penilai,
           n.n1,n.n2,n.n3,n.n4,n.n5,n.n6, n.hasil
    FROM teaching_practice tp
    JOIN santri s ON s.id = tp.santri_id
    LEFT JOIN nilai n ON n.santri_id = tp.santri_id
                     AND n.kelas_tp = tp.kelas_tp
                     AND n.kelompok = tp.kelompok
                     AND n.kriteria = '" . $conn->real_escape_string($filter_kriteria) . "'
    $where_sql
    ORDER BY tp.kelas_tp, tp.kelompok, s.siswa
";
$data = $conn->query($sql_data);
if (!$data) die("Query gagal: " . $conn->error);

// ================== DROPDOWN PENILAI (GURU) ==================
$list_penilai = $conn->query("SELECT nama_guru FROM guru WHERE kriteria='"
    . $conn->real_escape_string($filter_kriteria) . "' ORDER BY nama_guru");
if (!$list_penilai) $list_penilai = false;

$penilai_options = [];
if ($list_penilai) {
    while ($p = $list_penilai->fetch_assoc()) $penilai_options[] = $p['nama_guru'];
}

// ================== DROPDOWN SUPERVISOR (filter) ==================
$sup_options = [];
$q_sup = $conn->query("SELECT DISTINCT nama_guru FROM guru 
                       WHERE kriteria IN ('Supervisor 1','Supervisor 2') 
                       ORDER BY nama_guru");
if ($q_sup) {
    while ($s = $q_sup->fetch_assoc()) $sup_options[] = $s['nama_guru'];
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Input Nilai</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
    .filter-bar { background:#fff; padding:15px 20px; border-radius:10px;
        box-shadow:0 3px 12px rgba(0,0,0,.08); margin-bottom:15px;
        display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
    .filter-bar label { font-weight:600; color:#444; font-size:13px; }
    .filter-bar select, .filter-bar input[type="date"] {
        padding:8px 12px; border:1px solid #ccc; border-radius:6px;
        font-size:13px; }
    .filter-bar button { padding:8px 20px; background:#1e3c72; color:#fff;
        border:none; border-radius:6px; cursor:pointer; }
    .btn-reset { background:#95a5a6 !important; color:#fff;
        padding:8px 16px; border-radius:6px; text-decoration:none;
        font-size:13px; display:inline-block; }

    .shortcut-bar { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:12px;
        align-items:center; }
    .shortcut-btn {
        background:#eaf4ff; color:#1e3c72; border:1px solid #b8d8f5;
        padding:6px 14px; border-radius:20px; text-decoration:none;
        font-size:13px; font-weight:600; transition:.15s;
    }
    .shortcut-btn:hover { background:#1e3c72; color:#fff; border-color:#1e3c72; }
    .shortcut-btn.active { background:#1e3c72; color:#fff; border-color:#1e3c72; }

    .badge-kelas { background:#1e3c72; color:#fff; padding:3px 9px;
        border-radius:12px; font-size:12px; font-weight:bold; }
    .badge-kelas-tp { background:#e67e22; color:#fff; padding:3px 9px;
        border-radius:12px; font-size:12px; font-weight:bold; }
    .badge-kelompok { background:#16a085; color:#fff; padding:3px 10px;
        border-radius:12px; font-size:12px; font-weight:bold; }
    .badge-sup { background:#27ae60; color:#fff; padding:3px 8px;
        border-radius:10px; font-size:11px; }
    .badge-assessor { background:#8e44ad; color:#fff; padding:3px 8px;
        border-radius:10px; font-size:11px; }
    .badge-tgl { background:#34495e; color:#fff; padding:3px 8px;
        border-radius:10px; font-size:11px; }
    .alert-success { background:#e8f8ee; color:#219150; padding:12px 16px;
        border-radius:6px; margin-bottom:15px; border-left:4px solid #27ae60; }
    .alert-error { background:#fdecea; color:#c0392b; padding:12px 16px;
        border-radius:6px; margin-bottom:15px; border-left:4px solid #e74c3c; }

    .kriteria-box { background:#fff8e1; border-left:4px solid #f39c12;
        padding:12px 18px; border-radius:6px; margin-bottom:15px; font-size:13px; }
    .kriteria-box ol { margin:6px 0 0 20px; line-height:1.7; }

    table.nilai-table th, table.nilai-table td { text-align:center; vertical-align:middle; }
    table.nilai-table td.left { text-align:left; }

    .hasil-badge { display:inline-block; min-width:55px; padding:6px 12px;
        border-radius:20px; font-weight:bold; color:#fff; background:#3498db;
        font-size:14px; }
    .hasil-good { background:#27ae60; }
    .hasil-warn { background:#e67e22; }
    .hasil-bad  { background:#e74c3c; }

    .kelompok-header {
        background:linear-gradient(135deg,#1e3c72,#2a5298);
        color:#fff; padding:10px 18px; border-radius:8px 8px 0 0;
        margin-top:20px; font-weight:bold; display:flex;
        justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;
    }
    .kelompok-header .info { font-size:13px; opacity:.9; font-weight:normal; }

    .btn-save-row {
        background:#27ae60; color:#fff; border:none; padding:8px 14px;
        border-radius:6px; cursor:pointer; font-size:12px; font-weight:bold;
    }
    .btn-save-row:hover { background:#1e8449; }

    .info-filter { background:#eaf4ff; border-left:4px solid #3498db;
        padding:10px 15px; border-radius:6px; margin-bottom:15px;
        font-size:13px; color:#2c3e50; }

    .nilai-grid {
        display:grid;
        grid-template-columns:repeat(3,1fr);
        gap:10px 24px;
        font-size:13px;
        text-align:left;
    }
    .nilai-grid .item {
        display:flex; align-items:center; gap:8px;
    }
    .nilai-grid .item span.label { flex:1; color:#555; }
    .nilai-grid .item select {
        padding:6px 8px; border-radius:6px;
        border:1px solid #ccc; width:70px;
    }

    @media print {
        .navbar, .btn-print, .filter-bar, .kriteria-box, .no-print,
        .shortcut-bar, .info-filter { display:none !important; }
    }
</style>
</head>
<body>
<?php include 'partials/navbar.php'; ?>
<div class="container">

<h2>📝 Input Nilai Kelompok Musrif</h2>

<?php if(!empty($msg)) echo "<div class='alert-$msg_type'>$msg</div>"; ?>

<div class="kriteria-box">
    <b>📌 Keterangan Kriteria Pencapaian (nilai 4–9):</b>
    <ol type="a">
        <li>siswa penguasaan idad dengan baik</li>
        <li>siswa penulisan idad dengan baik</li>
        <li>siswa interaksi dengan guru baik</li>
        <li>siswa naqd kesalahan dengan baik</li>
        <li>siswa kesungguhan pembuatan idad dengan baik</li>
        <li>siswa aktif dalam pembuatan idad</li>
    </ol>
    <p style="margin-top:8px;">
        <b>Hasil = (n1 + n2 + n3 + n4 + n5 + n6) ÷ 6</b>
    </p>
</div>

<!-- ============ SHORTCUT KELOMPOK 1–9 ============ -->
<div class="shortcut-bar">
    <span style="font-size:13px;font-weight:bold;color:#555;">
        👥 Kelompok:
    </span>
    <?php foreach($KELOMPOK_LIST as $kl): ?>
        <a href="?filter_kriteria=<?=urlencode($filter_kriteria)?>&filter_kelompok=<?=$kl?><?=($filter_sup?'&filter_sup='.urlencode($filter_sup):'')?>"
           class="shortcut-btn <?=($filter_kelompok==$kl)?'active':''?>">
            Kel. <?=$kl?>
        </a>
    <?php endforeach; ?>
    <a href="?filter_kriteria=<?=urlencode($filter_kriteria)?>"
       class="shortcut-btn <?=($filter_kelompok==='')?'active':''?>">
        Semua
    </a>
</div>

<!-- ============ SHORTCUT SUPERVISOR ============ -->
<div class="shortcut-bar">
    <span style="font-size:13px;font-weight:bold;color:#555;">
        🎓 Supervisor:
    </span>
    <?php foreach($sup_options as $sup): ?>
        <a href="input_nilai.php?filter_kriteria=<?=urlencode($filter_kriteria)?>&filter_kelompok=<?=urlencode($filter_kelompok)?>&filter_sup=<?=urlencode($sup)?>"
           class="shortcut-btn <?=($filter_sup==$sup)?'active':''?>">
            <?=htmlspecialchars($sup)?>
        </a>
    <?php endforeach; ?>
    <?php if($filter_sup): ?>
        <a href="input_nilai.php?filter_kriteria=<?=urlencode($filter_kriteria)?>&filter_kelompok=<?=urlencode($filter_kelompok)?>"
           class="shortcut-btn">✖ Reset Sup</a>
    <?php endif; ?>
</div>

<!-- ============ FILTER BAR ============ -->
<div class="filter-bar">
    <b>🔍 Filter:</b>
    <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <label>Kriteria:</label>
        <select name="filter_kriteria" onchange="this.form.submit()">
            <?php foreach($KRITERIA_LIST as $k): ?>
                <option value="<?=$k?>" <?=($filter_kriteria==$k)?'selected':''?>>
                    <?=$k?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Kelompok:</label>
        <select name="filter_kelompok" onchange="this.form.submit()">
            <option value="">-- Semua --</option>
            <?php foreach($KELOMPOK_LIST as $kl): ?>
                <option value="<?=$kl?>" <?=($filter_kelompok==$kl)?'selected':''?>>
                    Kel. <?=$kl?>
                </option>
            <?php endforeach; ?>
        </select>

        <label>Supervisor:</label>
        <select name="filter_sup">
            <option value="">-- Semua --</option>
            <?php foreach($sup_options as $sup): ?>
                <option value="<?=htmlspecialchars($sup)?>"
                    <?=($filter_sup==$sup)?'selected':''?>>
                    <?=htmlspecialchars($sup)?>
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Tampilkan</button>

        <?php if($filter_sup): ?>
            <a href="input_nilai.php?filter_kriteria=<?=urlencode($filter_kriteria)?>&filter_kelompok=<?=urlencode($filter_kelompok)?>"
               class="btn-reset">Reset</a>
        <?php endif; ?>
    </form>
</div>

<?php if ($data->num_rows == 0): ?>
    <div class="alert-error">
        Belum ada data Teaching Practice untuk filter yang dipilih.
        Silakan input data di menu <b>Teaching Practice</b> terlebih dahulu,
        atau ubah filter.
    </div>
<?php else: ?>

<?php
// ================== GROUPING ==================
$groups = [];
while ($row = $data->fetch_assoc()) {
    $key = $row['kelas_tp'] . '|' . $row['kelompok'];
    $groups[$key][] = $row;
}
?>

<?php foreach($groups as $key => $rows): 
    list($ktp, $klp) = explode('|', $key);
    $sup1_unik = $rows[0]['supervisor1'] ?? '-';
    $sup2_unik = $rows[0]['supervisor2'] ?? '-';
    $tgl_unik  = isset($rows[0]['tanggal']) ? date('d/m/Y', strtotime($rows[0]['tanggal'])) : '-';
?>
    <div class="kelompok-header">
        <div>
            <span class="badge-kelas-tp">Kelas TP: <?=htmlspecialchars($ktp)?></span>
            <span class="badge-kelompok">Kelompok <?=htmlspecialchars($klp)?></span>
            <span class="badge-sup"><?=htmlspecialchars($filter_kriteria)?></span>
            <span class="badge-sup">Sup1: <?=htmlspecialchars($sup1_unik)?></span>
            <span class="badge-sup">Sup2: <?=htmlspecialchars($sup2_unik)?></span>
            <span class="badge-tgl"><?=$tgl_unik?></span>
        </div>
        <div class="info"><?=count($rows)?> siswa</div>
    </div>

    <form method="post" style="background:#fff;border-radius:0 0 8px 8px;
          box-shadow:0 3px 12px rgba(0,0,0,.08);padding:0 0 12px 0;">
        <input type="hidden" name="kelas_tp" value="<?=htmlspecialchars($ktp)?>">
        <input type="hidden" name="kelompok" value="<?=htmlspecialchars($klp)?>">
        <input type="hidden" name="kriteria" value="<?=htmlspecialchars($filter_kriteria)?>">

        <table class="nilai-table">
            <thead>
                <tr>
                    <th style="width:40px;">No</th>
                    <th style="width:70px;">Kelompok</th>
                    <th class="left">Siswa</th>
                    <th style="width:90px;">Kelas</th>
                    <th style="width:160px;">Supervisor</th>
                    <th>Input Nilai (4–9)</th>
                    <th style="width:90px;">Hasil</th>
                    <th style="width:110px;">Guru</th>
                </tr>
            </thead>
            <tbody>
            <?php $no=1; foreach($rows as $idx => $r): ?>
                <?php
                $cur = [
                    'n1'=>(int)($r['n1']??0), 'n2'=>(int)($r['n2']??0),
                    'n3'=>(int)($r['n3']??0), 'n4'=>(int)($r['n4']??0),
                    'n5'=>(int)($r['n5']??0), 'n6'=>(int)($r['n6']??0),
                ];
                $cur_hasil = $r['hasil'] !== null ? $r['hasil'] : '-';
                $hasil_class = 'hasil-badge';
                if (is_numeric($cur_hasil)) {
                    if ($cur_hasil >= 8) $hasil_class .= ' hasil-good';
                    elseif ($cur_hasil >= 6) $hasil_class .= ' hasil-warn';
                    else $hasil_class .= ' hasil-bad';
                }
                ?>
                <tr>
                    <td><?=$no++?></td>
                    <td><span class="badge-kelompok">Kel. <?=htmlspecialchars($r['kelompok'])?></span></td>
                    <td class="left">
                        <b><?=htmlspecialchars($r['siswa'])?></b><br>
                        <small style="color:#888;"><?=htmlspecialchars($r['induk'])?></small>
                    </td>
                    <td><span class="badge-kelas"><?=htmlspecialchars($r['kelas'])?></span></td>
                    <td>
                        <span class="badge-sup"><?=htmlspecialchars($r['supervisor1'] ?? '-')?></span><br>
                        <span class="badge-sup" style="margin-top:3px;display:inline-block;">
                            <?=htmlspecialchars($r['supervisor2'] ?? '-')?>
                        </span>
                    </td>
                    <td>
                        <div class="nilai-grid">
                            <?php
                            $labels = [1=>'a',2=>'b',3=>'c',4=>'d',5=>'e',6=>'f'];
                            foreach($labels as $nidx=>$lbl): ?>
                                <div class="item">
                                    <span class="label"><?=$lbl?>.</span>
                                    <select name="n[<?=$idx?>][<?=$nidx?>]" class="n-input"
                                        data-row="<?=$r['tp_id']?>">
                                        <option value="0">-</option>
                                        <?php foreach($NILAI_OPTIONS as $opt): ?>
                                            <option value="<?=$opt?>"
                                                <?=($cur["n$nidx"]==$opt)?'selected':''?>>
                                                <?=$opt?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="santri_id[<?=$idx?>]" value="<?=$r['santri_id']?>">
                    </td>
                    <td>
                        <span class="<?=$hasil_class?>" id="hasil_<?=$r['tp_id']?>">
                            <?= is_numeric($cur_hasil) ? number_format($cur_hasil,2) : '-' ?>
                        </span>
                    </td>
                    <td>
                        <select name="penilai[<?=$idx?>]" required
                                style="padding:6px;border-radius:6px;
                                       border:1px solid #ccc;min-width:130px;font-size:12px;">
                            <option value="">-- Pilih --</option>
                            <?php foreach($penilai_options as $p): ?>
                                <option value="<?=htmlspecialchars($p)?>"
                                    <?=($r['penilai']==$p)?'selected':''?>>
                                    <?=htmlspecialchars($p)?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>

        <div style="padding:12px 18px;text-align:right;">
            <button type="submit" name="simpan_nilai" class="btn-save-row"
                    style="padding:10px 24px;font-size:14px;">
                💾 Simpan Nilai Kelompok <?=htmlspecialchars($klp)?>
            </button>
        </div>
    </form>
<?php endforeach; ?>

<?php endif; ?>

</div>

<script>
document.querySelectorAll('.n-input').forEach(sel => {
    sel.addEventListener('change', () => {
        const rowId = sel.dataset.row;
        let total = 0, count = 0;
        document.querySelectorAll(`.n-input[data-row="${rowId}"]`).forEach(s => {
            const v = parseInt(s.value) || 0;
            if (v > 0) { total += v; count++; }
        });
        const hasilEl = document.getElementById('hasil_' + rowId);
        if (!hasilEl) return;
        if (count === 6) {
            const hasil = (total / 6).toFixed(2);
            hasilEl.textContent = hasil;
            hasilEl.className = 'hasil-badge';
            if (hasil >= 8) hasilEl.classList.add('hasil-good');
            else if (hasil >= 6) hasilEl.classList.add('hasil-warn');
            else hasilEl.classList.add('hasil-bad');
        } else {
            hasilEl.textContent = '-';
            hasilEl.className = 'hasil-badge';
        }
    });
});
</script>
</body>
</html>