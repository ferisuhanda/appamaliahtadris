<?php
require 'config/database.php';
cekLogin();

$KELAS_LIST    = ['1A','1B','2A','2B','3A','3B','3C','4A','4B','5 IPA','5 IPS','6 IPA','6 IPS'];
$MAPEL_LIST    = ['Bahasa Inggris','Bahasa Arab','Hadits','Tafsir','Imla','Khat','Nahwu','Mutholaah','Mahfduzhat','Tarikh Islam','Muhadtsah','Fiqh'];
$KELOMPOK_LIST = [1,2,3,4,5,6,7,8,9];

$MAX_CSV_ROWS = 60;

$msg = '';
$msg_type = 'success';
$mode_edit = false;
$edit_data = [
    'id'            => 0,
    'induk'         => '',
    'siswa'         => '',
    'kelas'         => '',
    'gender'        => '',
    'pelajaran'     => '',
    'kelompok'      => '',
    'kelompok_naqd' => '',
];

// ================== NORMALISASI MAPEL ==================
function normalisasiMapel($mapel, $MAPEL_LIST) {
    $mapel = trim($mapel);
    $typo_map = [
        'Muhtholaah' => 'Mutholaah', 'Mutholaahh' => 'Mutholaah',
        'Mutholah'   => 'Mutholaah', 'Mutholaa'   => 'Mutholaah',
        'Hadist'     => 'Hadits',    'Hadis'      => 'Hadits',
        'Fiqih'      => 'Fiqh',
        'Imlaa'      => 'Imla',
        'Khot'       => 'Khat',
        'Tarikh'     => 'Tarikh Islam',
        'Muhadatsah' => 'Muhadtsah',
        'Mahfudzhat' => 'Mahfduzhat',
    ];
    if (in_array($mapel, $MAPEL_LIST)) return $mapel;
    if (isset($typo_map[$mapel]) && in_array($typo_map[$mapel], $MAPEL_LIST)) {
        return $typo_map[$mapel];
    }
    foreach ($MAPEL_LIST as $m) {
        if (strcasecmp($mapel, $m) === 0) return $m;
    }
    $best = null; $best_dist = 999;
    foreach ($MAPEL_LIST as $m) {
        $dist = levenshtein(strtolower($mapel), strtolower($m));
        if ($dist < $best_dist && $dist <= 2) { $best_dist = $dist; $best = $m; }
    }
    return $best ?: $mapel;
}

// ================== SIMPAN / UPDATE (MANUAL) ==================
if (isset($_POST['simpan'])) {
    $id            = (int)($_POST['id'] ?? 0);
    $induk         = trim($_POST['induk'] ?? '');
    $siswa         = trim($_POST['siswa'] ?? '');
    $kelas         = $_POST['kelas'] ?? '';
    $gender        = $_POST['gender'] ?? '';
    $pelajaran     = $_POST['pelajaran'] ?? '';
    $kelompok      = (int)($_POST['kelompok'] ?? 0);
    $kelompok_naqd = (int)($_POST['kelompok_naqd'] ?? 0);

    if ($induk === '' || $siswa === '') {
        $msg = "❌ Induk dan Nama Siswa wajib diisi!";
        $msg_type = 'error';
    } elseif (!in_array($kelas, $KELAS_LIST)) {
        $msg = "❌ Kelas tidak valid!";
        $msg_type = 'error';
    } elseif (!in_array($gender, ['L','P'])) {
        $msg = "❌ Gender tidak valid!";
        $msg_type = 'error';
    } elseif (!in_array($pelajaran, $MAPEL_LIST)) {
        $msg = "❌ Mata pelajaran tidak valid!";
        $msg_type = 'error';
    } elseif (!in_array($kelompok, $KELOMPOK_LIST)) {
        $msg = "❌ Kelompok tidak valid!";
        $msg_type = 'error';
    } elseif (!in_array($kelompok_naqd, $KELOMPOK_LIST)) {
        $msg = "❌ Kelompok Naqd tidak valid!";
        $msg_type = 'error';
    } else {
        if ($id > 0) {
            // UPDATE
            $stmt = $conn->prepare("UPDATE santri SET 
                pelajaran=?, kelompok=?, kelompok_naqd=? 
                WHERE id=?");
            if ($stmt) {
                $stmt->bind_param("siii", $pelajaran, $kelompok, $kelompok_naqd, $id);
                if ($stmt->execute()) {
                    $msg = "✅ Data santri <b>" . htmlspecialchars($siswa) . "</b> berhasil diupdate!";
                } else {
                    $msg = "❌ Gagal update: " . $conn->error;
                    $msg_type = 'error';
                }
                $stmt->close();
            } else {
                $msg = "❌ Prepare gagal: " . $conn->error;
                $msg_type = 'error';
            }
        } else {
            // INSERT
            $stmt = $conn->prepare("INSERT INTO santri 
                (induk, siswa, kelas, gender, pelajaran, kelompok, kelompok_naqd) 
                VALUES (?,?,?,?,?,?,?)");
            if ($stmt) {
                $stmt->bind_param("sssssii", $induk, $siswa, $kelas, $gender, $pelajaran, $kelompok, $kelompok_naqd);
                if ($stmt->execute()) {
                    $msg = "✅ Data santri <b>" . htmlspecialchars($siswa) . "</b> berhasil disimpan!";
                } else {
                    if ($conn->errno == 1062) {
                        $msg = "❌ Gagal: Induk <b>" . htmlspecialchars($induk) . "</b> sudah terdaftar!";
                    } else {
                        $msg = "❌ Gagal menyimpan: " . $conn->error;
                    }
                    $msg_type = 'error';
                }
                $stmt->close();
            } else {
                $msg = "❌ Prepare gagal: " . $conn->error;
                $msg_type = 'error';
            }
        }
    }

    if ($msg_type === 'success') {
        header("Location: santri.php?msg=" . urlencode($msg) . "&type=$msg_type");
        exit;
    }
}

// ================== UPLOAD CSV ==================
if (isset($_POST['upload_csv'])) {
    if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] != 0) {
        $msg = "❌ Gagal upload file CSV. Kode error: " . ($_FILES['csv_file']['error'] ?? 'N/A');
        $msg_type = 'error';
    } else {
        $fp = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if (!$fp) {
            $msg = "❌ Gagal membuka file CSV!";
            $msg_type = 'error';
        } else {
            $rows = [];
            $row_num = 0;
            $baris_kosong = 0;

            while (($line = fgetcsv($fp, 5000, ",")) !== FALSE) {
                $row_num++;
                if ($row_num == 1) {
                    $first_cell = strtolower(trim($line[0] ?? ''));
                    if (in_array($first_cell, ['induk','no','id','nomor'])) {
                        continue;
                    }
                }
                if (empty(array_filter($line, function($v){ return trim($v) !== ''; }))) {
                    $baris_kosong++;
                    continue;
                }
                $rows[] = $line;
            }
            fclose($fp);

            $total = count($rows);

            if ($total == 0) {
                $msg = "❌ File CSV kosong atau format tidak sesuai!";
                $msg_type = 'error';
            } elseif ($total > $MAX_CSV_ROWS) {
                $msg = "❌ Upload ditolak! File berisi <b>$total baris</b>, "
                     . "melebihi batas maksimal <b>$MAX_CSV_ROWS siswa</b> per upload.";
                $msg_type = 'error';
            } else {
                $berhasil = 0; $duplikat = 0; $gagal = 0;
                $kelas_invalid = 0; $mapel_invalid = 0;
                $kelompok_invalid = 0; $kelompok_naqd_invalid = 0;
                $gender_invalid = 0; $field_kosong = 0;

                $stmt = $conn->prepare("INSERT IGNORE INTO santri 
                    (induk, siswa, kelas, gender, pelajaran, kelompok, kelompok_naqd) 
                    VALUES (?,?,?,?,?,?,?)");

                if ($stmt) {
                    foreach ($rows as $line) {
                        if (count($line) < 7) { $gagal++; continue; }

                        $induk         = trim($line[0]);
                        $siswa         = preg_replace('/\s+/', ' ', trim($line[1]));
                        $kelas         = strtoupper(trim($line[2]));
                        $gender        = strtoupper(trim($line[3]));
                        $pelajaran     = normalisasiMapel(trim($line[4]), $MAPEL_LIST);
                        $kelompok      = (int)trim($line[5]);
                        $kelompok_naqd = (int)trim($line[6]);

                        if ($induk === '' || $siswa === '') { $field_kosong++; continue; }

                        if (!in_array($kelas, $KELAS_LIST))         { $kelas_invalid++;         continue; }
                        if (!in_array($gender, ['L','P']))          { $gender_invalid++;        continue; }
                        if (!in_array($pelajaran, $MAPEL_LIST))     { $mapel_invalid++;         continue; }
                        if (!in_array($kelompok, $KELOMPOK_LIST))   { $kelompok_invalid++;      continue; }
                        if (!in_array($kelompok_naqd, $KELOMPOK_LIST)) { $kelompok_naqd_invalid++; continue; }

                        $stmt->bind_param("sssssii",
                            $induk, $siswa, $kelas, $gender, $pelajaran, $kelompok, $kelompok_naqd);
                        if ($stmt->execute()) {
                            if ($stmt->affected_rows > 0) $berhasil++;
                            else $duplikat++;
                        } else {
                            $gagal++;
                        }
                    }
                    $stmt->close();
                } else {
                    $msg = "❌ Prepare gagal: " . $conn->error;
                    $msg_type = 'error';
                }

                if ($msg_type == 'success') {
                    $msg = "✅ Import CSV selesai!<br>
                            • <b>Berhasil:</b> $berhasil<br>
                            • <b>Duplikat:</b> $duplikat<br>
                            • <b>Field kosong:</b> $field_kosong<br>
                            • <b>Kelas tidak valid:</b> $kelas_invalid<br>
                            • <b>Gender tidak valid:</b> $gender_invalid<br>
                            • <b>Mapel tidak valid:</b> $mapel_invalid<br>
                            • <b>Kelompok tidak valid:</b> $kelompok_invalid<br>
                            • <b>Kelompok Naqd tidak valid:</b> $kelompok_naqd_invalid<br>
                            • <b>Gagal:</b> $gagal<br>
                            • <b>Baris kosong (skip):</b> $baris_kosong<br>
                            • <b>Total diproses:</b> $total / maks $MAX_CSV_ROWS";
                    $msg_type = ($berhasil > 0) ? 'success' : 'error';
                }
            }
        }
    }
}

// ================== HAPUS ==================
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    if ($id > 0) {
        if ($conn->query("DELETE FROM santri WHERE id=$id")) {
            $msg = "✅ Data santri berhasil dihapus.";
        } else {
            $msg = "❌ Gagal menghapus: " . $conn->error;
            $msg_type = 'error';
        }
    }
    header("Location: santri.php?msg=" . urlencode($msg) . "&type=$msg_type");
    exit;
}

// ================== MODE EDIT ==================
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $q = $conn->query("SELECT * FROM santri WHERE id=$id");
    if ($q && $q->num_rows > 0) {
        $edit_data = $q->fetch_assoc();
        $mode_edit = true;
    }
}

// ================== PESAN DARI URL ==================
if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
    $msg_type = $_GET['type'] ?? 'success';
}

// ================== FILTER ==================
$filter_kelas         = $_GET['filter_kelas'] ?? '';
$filter_kelompok      = $_GET['filter_kelompok'] ?? '';
$filter_kelompok_naqd = $_GET['filter_kelompok_naqd'] ?? '';

$where_arr = [];
if ($filter_kelas && in_array($filter_kelas, $KELAS_LIST)) {
    $where_arr[] = "kelas='" . $conn->real_escape_string($filter_kelas) . "'";
}
if ($filter_kelompok && in_array((int)$filter_kelompok, $KELOMPOK_LIST)) {
    $where_arr[] = "kelompok=" . (int)$filter_kelompok;
}
if ($filter_kelompok_naqd && in_array((int)$filter_kelompok_naqd, $KELOMPOK_LIST)) {
    $where_arr[] = "kelompok_naqd=" . (int)$filter_kelompok_naqd;
}
$where = $where_arr ? "WHERE " . implode(" AND ", $where_arr) : '';

// ================== QUERY COUNT ==================
$total_santri = 0;
$q_count = $conn->query("SELECT COUNT(*) AS t FROM santri $where");
if ($q_count) {
    $row_count = $q_count->fetch_assoc();
    $total_santri = $row_count['t'] ?? 0;
}

// ================== QUERY DATA ==================
$data = $conn->query("SELECT * FROM santri $where ORDER BY kelas, kelompok, siswa");
if (!$data) die("Query data gagal: " . $conn->error);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Form Santri</title>
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
    .info-box { background:#eaf4ff; border-left:4px solid #3498db; padding:12px 16px;
        border-radius:6px; margin-bottom:15px; font-size:14px; color:#2c3e50; }
    .counter-badge { display:inline-block; background:#1e3c72; color:#fff;
        padding:4px 12px; border-radius:20px; font-size:13px; margin-left:8px; }
    .alert-success, .alert-error { padding:12px 16px; border-radius:6px;
        margin-bottom:15px; font-size:14px; line-height:1.6; }
    .alert-success { background:#e8f8ee; color:#219150; border-left:4px solid #27ae60; }
    .alert-error   { background:#fdecea; color:#c0392b; border-left:4px solid #e74c3c; }

    .filter-bar { background:#fff; padding:15px 20px; border-radius:10px;
        box-shadow:0 3px 12px rgba(0,0,0,.08); margin-bottom:15px;
        display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
    .filter-bar select { padding:8px 12px; border:1px solid #ccc; border-radius:6px; }
    .filter-bar button { padding:8px 20px; background:#1e3c72; color:#fff;
        border:none; border-radius:6px; cursor:pointer; }
    .btn-reset { background:#95a5a6 !important; color:#fff;
        padding:8px 16px; border-radius:6px; text-decoration:none; font-size:14px; }

    .badge-pelajaran { background:#2980b9; color:#fff; padding:3px 9px;
        border-radius:10px; font-size:11px; }
    .badge-kelompok { background:#16a085; color:#fff; padding:3px 10px;
        border-radius:12px; font-size:12px; font-weight:bold; }
    .badge-naqd { background:#9b59b6; color:#fff; padding:3px 10px;
        border-radius:12px; font-size:12px; font-weight:bold; }

    .max-info { background:#fff8e1; border-left:4px solid #f39c12;
        padding:8px 12px; border-radius:6px; margin-top:8px; font-size:12px;
        color:#7a5a00; }

    .mode-edit-box {
        background:#fff8e1; border-left:4px solid #f39c12;
        padding:12px 16px; border-radius:6px; margin-bottom:15px;
        font-size:14px; color:#7a5a00;
    }

    /* ============ AKSI ICON ============ */
    .aksi-wrap {
        display: flex; gap: 6px; justify-content: center;
        align-items: center; flex-wrap: nowrap;
    }
    .btn-aksi {
        display: inline-flex; align-items: center; justify-content: center;
        width: 34px; height: 34px; border-radius: 6px;
        font-size: 14px; text-decoration: none; color: #fff;
        border: none; cursor: pointer; transition: .15s; line-height: 1;
    }
    .btn-edit { background: #f39c12; }
    .btn-edit:hover { background: #e67e22; transform: scale(1.05); }
    .btn-hapus { background: #e74c3c; }
    .btn-hapus:hover { background: #c0392b; transform: scale(1.05); }
    table td.aksi-cell { text-align: center; vertical-align: middle; }

    .csv-contoh {
        background: #f4f6f9; border-left: 3px solid #27ae60;
        padding: 10px 14px; border-radius: 6px; margin-top: 10px;
        font-size: 12px; line-height: 1.7; color: #333;
    }
    .csv-contoh code {
        background: #fff; padding: 2px 6px; border-radius: 3px;
        color: #c0392b; font-family: monospace;
    }
</style>
</head>
<body>
<?php include 'partials/navbar.php'; ?>
<div class="container">

<h2>Form Santri
    <span class="counter-badge">Total: <?= $total_santri ?> siswa</span>
</h2>

<?php if(!empty($msg)): ?>
    <div class="alert-<?= $msg_type ?>"><?= $msg ?></div>
<?php endif; ?>

<?php if ($mode_edit): ?>
    <div class="mode-edit-box">
        ✏️ <b>Mode Edit:</b> Mengubah data santri
        <b><?= htmlspecialchars($edit_data['siswa']) ?></b>.
        <a href="santri.php" style="float:right;color:#7a5a00;">✖ Batal Edit</a>
    </div>
<?php endif; ?>

<div class="grid-2">

    <!-- ============ INPUT MANUAL ============ -->
    <div class="form-card">
        <h3><?= $mode_edit ? '✏️ Edit Santri' : '📝 Input Manual' ?></h3>
        <form method="post">
            <input type="hidden" name="id" value="<?= (int)$edit_data['id'] ?>">

            <label>Induk</label>
            <input type="text" name="induk" required maxlength="30"
                   value="<?=htmlspecialchars($edit_data['induk'])?>"
                   <?= $mode_edit ? 'readonly style="background:#f5f5f5;"' : '' ?>>

            <label>Nama Siswa</label>
            <input type="text" name="siswa" required maxlength="100"
                   value="<?=htmlspecialchars($edit_data['siswa'])?>"
                   <?= $mode_edit ? 'readonly style="background:#f5f5f5;"' : '' ?>>

            <label>Kelas</label>
            <select name="kelas" required <?= $mode_edit ? 'disabled' : '' ?>>
                <option value="">-- Pilih Kelas --</option>
                <?php foreach($KELAS_LIST as $k): ?>
                    <option value="<?=$k?>" <?=($edit_data['kelas']==$k)?'selected':''?>><?=$k?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($mode_edit): ?>
                <input type="hidden" name="kelas" value="<?=htmlspecialchars($edit_data['kelas'])?>">
            <?php endif; ?>

            <label>Gender</label>
            <select name="gender" required <?= $mode_edit ? 'disabled' : '' ?>>
                <option value="L" <?=($edit_data['gender']=='L')?'selected':''?>>Laki-laki</option>
                <option value="P" <?=($edit_data['gender']=='P')?'selected':''?>>Perempuan</option>
            </select>
            <?php if ($mode_edit): ?>
                <input type="hidden" name="gender" value="<?=htmlspecialchars($edit_data['gender'])?>">
            <?php endif; ?>

            <label>Mata Pelajaran</label>
            <select name="pelajaran" required>
                <option value="">-- Pilih Mata Pelajaran --</option>
                <?php foreach($MAPEL_LIST as $m): ?>
                    <option value="<?=htmlspecialchars($m)?>"
                        <?=($edit_data['pelajaran']==$m)?'selected':''?>>
                        <?=htmlspecialchars($m)?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Kelompok</label>
            <select name="kelompok" required>
                <option value="">-- Pilih Kelompok --</option>
                <?php foreach($KELOMPOK_LIST as $kl): ?>
                    <option value="<?=$kl?>" <?=($edit_data['kelompok']==$kl)?'selected':''?>>
                        Kelompok <?=$kl?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Kelompok Naqd</label>
            <select name="kelompok_naqd" required>
                <option value="">-- Pilih Kelompok Naqd --</option>
                <?php foreach($KELOMPOK_LIST as $kl): ?>
                    <option value="<?=$kl?>" <?=($edit_data['kelompok_naqd']==$kl)?'selected':''?>>
                        Kelompok Naqd <?=$kl?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" name="simpan">
                <?= $mode_edit ? '💾 Update' : 'Simpan' ?>
            </button>
            <?php if ($mode_edit): ?>
                <a href="santri.php"
                   style="margin-left:10px;padding:11px 24px;background:#95a5a6;
                          color:#fff;text-decoration:none;border-radius:6px;
                          display:inline-block;font-size:15px;">Batal</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ============ INPUT CSV ============ -->
    <div class="form-card">
        <h3>📤 Input Otomatis (CSV)</h3>
        <div class="info-box">
            <b>Format CSV:</b> <code>induk,siswa,kelas,gender,pelajaran,kelompok,kelompok_naqd</code><br>
            <b>Kelas valid:</b> <?= implode(', ', $KELAS_LIST) ?><br>
            <b>Mapel valid:</b> <?= implode(', ', $MAPEL_LIST) ?><br>
            <b>Kelompok valid:</b> <?= implode(', ', $KELOMPOK_LIST) ?><br>
            <b>Kelompok Naqd valid:</b> <?= implode(', ', $KELOMPOK_LIST) ?><br>
            <b>Gender valid:</b> L, P
        </div>

        <div class="max-info">
            ⚠️ <b>Batas upload:</b> Maksimal <b><?= $MAX_CSV_ROWS ?> siswa</b> per file CSV.
        </div>

        <form method="post" enctype="multipart/form-data" style="margin-top:12px;">
            <label>Pilih File CSV</label>
            <input type="file" name="csv_file" accept=".csv" required>
            <button type="submit" name="upload_csv">Upload CSV</button>
        </form>

        <div class="csv-contoh">
            💡 <b>Contoh:</b><br>
            <code>induk,siswa,kelas,gender,pelajaran,kelompok,kelompok_naqd</code><br>
            <code>2024001,Ahmad Fauzi,1A,L,Bahasa Arab,1,1</code><br>
            <code>2024002,Siti Aminah,2B,P,Bahasa Inggris,2,2</code><br>
            <code>2024003,Muhammad Rizki,3A,L,Nahwu,3,3</code><br>
            <code>2024004,Fatimah Az-Zahra,5 IPA,P,Hadits,4,4</code>
        </div>
    </div>

</div>

<!-- ============ FILTER ============ -->
<div class="filter-bar">
    <b>🔍 Filter:</b>
    <form method="get" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
        <select name="filter_kelas">
            <option value="">-- Semua Kelas --</option>
            <?php foreach($KELAS_LIST as $k): ?>
                <option value="<?=$k?>" <?=($filter_kelas==$k)?'selected':''?>><?=$k?></option>
            <?php endforeach; ?>
        </select>

        <select name="filter_kelompok">
            <option value="">-- Semua Kelompok --</option>
            <?php foreach($KELOMPOK_LIST as $kl): ?>
                <option value="<?=$kl?>" <?=($filter_kelompok==$kl)?'selected':''?>>Kelompok <?=$kl?></option>
            <?php endforeach; ?>
        </select>

        <select name="filter_kelompok_naqd">
            <option value="">-- Semua Kelompok Naqd --</option>
            <?php foreach($KELOMPOK_LIST as $kl): ?>
                <option value="<?=$kl?>" <?=($filter_kelompok_naqd==$kl)?'selected':''?>>Naqd <?=$kl?></option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Tampilkan</button>
        <?php if($filter_kelas || $filter_kelompok || $filter_kelompok_naqd): ?>
            <a href="santri.php" class="btn-reset">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- ============ DATAGRID ============ -->
<h3>Daftar Santri <?= $filter_kelas ? "— Kelas $filter_kelas" : '' ?></h3>
<table>
    <thead>
        <tr>
            <th>No</th>
            <th>Induk</th>
            <th>Nama</th>
            <th>Kelas</th>
            <th>Gender</th>
            <th>Pelajaran</th>
            <th>Kelompok</th>
            <th>Kelompok Naqd</th>
            <th style="width:100px;">Aksi</th>
        </tr>
    </thead>
    <tbody>
    <?php if ($data && $data->num_rows > 0): ?>
        <?php $i=1; while($s=$data->fetch_assoc()): ?>
        <tr>
            <td><?=$i++?></td>
            <td><?=htmlspecialchars($s['induk'])?></td>
            <td><?=htmlspecialchars($s['siswa'])?></td>
            <td><b><?=htmlspecialchars($s['kelas'])?></b></td>
            <td><?=$s['gender']=='L'?'Laki-laki':'Perempuan'?></td>
            <td><span class="badge-pelajaran"><?=htmlspecialchars($s['pelajaran'] ?? '-')?></span></td>
            <td><span class="badge-kelompok">Kel. <?=htmlspecialchars($s['kelompok'] ?? '-')?></span></td>
            <td>
                <span class="badge-naqd">
                    Naqd <?=htmlspecialchars($s['kelompok_naqd'] ?? '-')?>
                </span>
            </td>
            <td class="aksi-cell">
                <div class="aksi-wrap">
                    <a href="?edit=<?=$s['id']?>" class="btn-aksi btn-edit" title="Edit data">
                        <i class="fa-solid fa-pen"></i>
                    </a>
                    <a href="?hapus=<?=$s['id']?>"
                       onclick="return confirm('Hapus data <?=htmlspecialchars($s['siswa'])?>?')"
                       class="btn-aksi btn-hapus" title="Hapus data">
                        <i class="fa-solid fa-trash"></i>
                    </a>
                </div>
            </td>
        </tr>
        <?php endwhile; ?>
    <?php else: ?>
        <tr><td colspan="9" style="text-align:center;">Belum ada data santri.</td></tr>
    <?php endif; ?>
    </tbody>
</table>

</div>
</body>
</html>