<?php
require 'config/database.php';
cekLogin();

$KELAS_LIST    = ['1A','1B','2A','2B','3A','3B','3C','4A','4B','5 IPA','5 IPS','6 IPA','6 IPS'];
$MAPEL_LIST    = ['Bahasa Inggris','Bahasa Arab','Hadits','Tafsir','Imla','Khat','Nahwu','Mutholaah','Mahfduzhat','Tarikh Islam','Muhadtsah','Fiqh'];
$KELOMPOK_LIST = [1,2,3,4,5,6,7,8,9];

$msg = '';
$msg_type = 'success';
$mode_edit = false;
$edit_data = [
    'id'           => 0,
    'santri_id'    => '',
    'kelas_tp'     => '',
    'kelompok'     => '',
    'mata_pelajaran' => '',
    'judul'        => '',
    'supervisor1'  => '',
    'supervisor2'  => '',
    'assessor'     => '',
    'tanggal'      => date('Y-m-d'),
];

// ================== SIMPAN / UPDATE ==================
if (isset($_POST['simpan'])) {
    $id           = (int)($_POST['id'] ?? 0);
    $kelas_tp     = $_POST['kelas_tp'] ?? '';
    $kelompok     = (int)($_POST['kelompok'] ?? 0);
    $mapel        = $_POST['mata_pelajaran'] ?? '';
    $supervisor1  = trim($_POST['supervisor1'] ?? '');
    $supervisor2  = trim($_POST['supervisor2'] ?? '');

    // Validasi
    if (!in_array($kelas_tp, $KELAS_LIST)) {
        $msg = "❌ Kelas Teaching Practice tidak valid!";
        $msg_type = 'error';
    } elseif (!in_array($kelompok, $KELOMPOK_LIST)) {
        $msg = "❌ Kelompok tidak valid!";
        $msg_type = 'error';
    } elseif (!in_array($mapel, $MAPEL_LIST)) {
        $msg = "❌ Mata pelajaran tidak valid!";
        $msg_type = 'error';
    } elseif ($supervisor1 === '' || $supervisor2 === '') {
        $msg = "❌ Supervisor 1 & Supervisor 2 wajib diisi!";
        $msg_type = 'error';
    } else {
        if ($id > 0) {
            // ===== UPDATE =====
            $stmt = $conn->prepare("UPDATE teaching_practice SET 
                kelas_tp=?, kelompok=?, mata_pelajaran=?, judul=?,
                supervisor1=?, supervisor2=?, assessor=?, tanggal=?
                WHERE id=?");
            if (!$stmt) {
                $msg = "❌ Prepare gagal: " . $conn->error;
                $msg_type = 'error';
            } else {
                $assessor = $_POST['assessor'] ?? '';
                $stmt->bind_param("sissssssi",
                    $kelas_tp,
                    $kelompok,
                    $mapel,
                    $_POST['judul'],
                    $supervisor1,
                    $supervisor2,
                    $assessor,
                    $_POST['tanggal'],
                    $id
                );
                if ($stmt->execute()) {
                    $msg = "✅ Data teaching practice berhasil diupdate!";
                } else {
                    $msg = "❌ Gagal update: " . $stmt->error;
                    $msg_type = 'error';
                }
                $stmt->close();
            }
        } else {
            // ===== INSERT =====
            $stmt = $conn->prepare("INSERT INTO teaching_practice 
                (santri_id, kelas_tp, kelompok, mata_pelajaran, judul, 
                 supervisor1, supervisor2, assessor, tanggal) 
                VALUES (?,?,?,?,?,?,?,?,?)");
            if (!$stmt) {
                $msg = "❌ Prepare gagal: " . $conn->error;
                $msg_type = 'error';
            } else {
                $assessor = $_POST['assessor'] ?? '';
                $stmt->bind_param("isissssss",
                    $_POST['santri_id'],
                    $kelas_tp,
                    $kelompok,
                    $mapel,
                    $_POST['judul'],
                    $supervisor1,
                    $supervisor2,
                    $assessor,
                    $_POST['tanggal']
                );
                if ($stmt->execute()) {
                    $msg = "✅ Data teaching practice berhasil disimpan!";
                } else {
                    $msg = "❌ Gagal simpan: " . $stmt->error;
                    $msg_type = 'error';
                }
                $stmt->close();
            }
        }
    }

    if ($msg_type === 'success') {
        header("Location: teaching_practice.php?msg=" . urlencode($msg) . "&type=$msg_type");
        exit;
    }
}

// ================== HAPUS ==================
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    if ($id > 0) {
        if ($conn->query("DELETE FROM teaching_practice WHERE id=$id")) {
            $msg = "✅ Data berhasil dihapus.";
        } else {
            $msg = "❌ Gagal menghapus: " . $conn->error;
            $msg_type = 'error';
        }
    }
    header("Location: teaching_practice.php?msg=" . urlencode($msg) . "&type=$msg_type");
    exit;
}

// ================== MODE EDIT ==================
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $q = $conn->query("SELECT * FROM teaching_practice WHERE id=$id");
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
$filter_kelas    = $_GET['filter_kelas'] ?? '';
$filter_tgl      = $_GET['filter_tgl'] ?? '';
$filter_kelompok = $_GET['filter_kelompok'] ?? '';

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

// ================== QUERY SANTRI ==================
$santri = $conn->query("
    SELECT s.id, s.induk, s.siswa, s.kelas, s.pelajaran, s.kelompok,
           ka.supervisor1 AS sup_kelompok1, ka.supervisor2 AS sup_kelompok2
    FROM santri s
    LEFT JOIN kelompok_amaliah ka ON ka.kelompok = s.kelompok
    ORDER BY s.kelas, s.kelompok, s.siswa
");
if (!$santri) $santri = false;

// ================== QUERY GURU ==================
$sup1 = $conn->query("SELECT nama_guru FROM guru WHERE kriteria='Supervisor 1' ORDER BY nama_guru");
if (!$sup1) $sup1 = false;
$sup1_arr = [];
if ($sup1) while ($s = $sup1->fetch_assoc()) $sup1_arr[] = $s['nama_guru'];

$sup2 = $conn->query("SELECT nama_guru FROM guru WHERE kriteria='Supervisor 2' ORDER BY nama_guru");
if (!$sup2) $sup2 = false;
$sup2_arr = [];
if ($sup2) while ($s = $sup2->fetch_assoc()) $sup2_arr[] = $s['nama_guru'];

$ass = $conn->query("SELECT nama_guru FROM guru WHERE kriteria='Assessor' ORDER BY nama_guru");
if (!$ass) $ass = false;
$ass_arr = [];
if ($ass) while ($s = $ass->fetch_assoc()) $ass_arr[] = $s['nama_guru'];

// ================== QUERY DATA ==================
$data = $conn->query("SELECT tp.*, s.siswa, s.kelas, s.induk
                      FROM teaching_practice tp
                      JOIN santri s ON s.id=tp.santri_id
                      $where_sql
                      ORDER BY tp.tanggal DESC, tp.kelas_tp, tp.kelompok, s.siswa");
if (!$data) die("Query gagal: " . $conn->error);

// Simpan data untuk export XLS
$data_export = [];
while ($row = $data->fetch_assoc()) $data_export[] = $row;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Teaching Practice</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
    .filter-bar { background:#fff; padding:15px 20px; border-radius:10px;
        box-shadow:0 3px 12px rgba(0,0,0,.08); margin-bottom:15px;
        display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
    .filter-bar label { font-weight:600; color:#444; font-size:14px; }
    .filter-bar select, .filter-bar input { padding:8px 12px;
        border:1px solid #ccc; border-radius:6px; }
    .filter-bar button { padding:8px 20px; background:#1e3c72; color:#fff;
        border:none; border-radius:6px; cursor:pointer; }
    .btn-reset { background:#95a5a6 !important; color:#fff;
        padding:8px 16px; border-radius:6px; text-decoration:none; font-size:14px; }
    .badge-kelas { background:#1e3c72; color:#fff; padding:3px 4px;
        border-radius:12px; font-size:12px; font-weight:bold; }
    .badge-kelas-tp { background:#e67e22; color:#fff; padding:3px 9px;
        border-radius:12px; font-size:12px; font-weight:bold; }
    .badge-kelompok { background:#16a085; color:#fff; padding:3px 10px;
        border-radius:12px; font-size:12px; font-weight:bold; }
    .badge-mapel { background:#2980b9; color:#fff; padding:3px 9px;
        border-radius:10px; font-size:11px; }
    .badge-sup { background:#27ae60; color:#fff; padding:3px 8px;
        border-radius:10px; font-size:11px; display:inline-block;
        margin-bottom:2px; }
    .badge-sup2 { background:#2980b9; color:#fff; padding:3px 8px;
        border-radius:10px; font-size:11px; display:inline-block;
        margin-bottom:2px; }
    .badge-assessor { background:#8e44ad; color:#fff; padding:3px 8px;
        border-radius:10px; font-size:11px; }
    .alert-success { background:#e8f8ee; color:#219150; padding:12px 16px;
        border-radius:6px; margin-bottom:15px; border-left:4px solid #27ae60; }
    .alert-error { background:#fdecea; color:#c0392b; padding:12px 16px;
        border-radius:6px; margin-bottom:15px; border-left:4px solid #e74c3c; }

    .autofill-box {
        background:#e8f5e9; border-left:4px solid #27ae60;
        padding:10px 14px; border-radius:6px; margin-top:10px;
        font-size:13px; color:#1e5c2e;
    }
    .autofill-box b { color:#155724; }

    .field-filled {
        background:#f0fdf4 !important;
        border-color:#27ae60 !important;
        transition:.2s;
    }

    .mode-edit-box {
        background:#fff8e1; border-left:4px solid #f39c12;
        padding:12px 16px; border-radius:6px; margin-bottom:15px;
        font-size:14px; color:#7a5a00;
    }
.btn-csv {
    background: #27ae60;
}
.btn-csv:hover { background: #1e8449; }

.btn-pdf {
    background: #e74c3c;
}
.btn-pdf:hover { background: #c0392b; }

    /* ============ AKSI BUTTON (ICON) ============ */
    .aksi-wrap {
        display: flex;
        gap: 6px;
        justify-content: center;
        align-items: center;
        flex-wrap: nowrap;
    }
    .btn-aksi {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border-radius: 6px;
        font-size: 15px;
        text-decoration: none;
        color: #fff;
        border: none;
        cursor: pointer;
        transition: .15s;
        line-height: 1;
    }
    .btn-edit {
        background: #f39c12;
    }
    .btn-edit:hover { background: #e67e22; transform: scale(1.05); }
    .btn-hapus {
        background: #e74c3c;
    }
    .btn-hapus:hover { background: #c0392b; transform: scale(1.05); }
    table td.aksi-cell { text-align: center; vertical-align: middle; }

    /* ============ TOOLBAR ============ */
    .toolbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        margin-bottom: 15px;
    }
    .toolbar-left, .toolbar-right {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }
    .btn-download {
        background: #27ae60;
        color: #fff;
        padding: 10px 20px;
        border-radius: 6px;
        text-decoration: none;
        border: none;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: .15s;
    }
    .btn-download:hover { background: #1e8449; transform: translateY(-1px); }
    .btn-download i { font-size: 15px; }
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body>
<?php include 'partials/navbar.php'; ?>
<div class="container">
<h2>Form Teaching Practice</h2>
<?php if(!empty($msg)) echo "<div class='alert-$msg_type'>$msg</div>"; ?>

<?php if ($mode_edit): ?>
    <div class="mode-edit-box">
        ✏️ <b>Mode Edit:</b> Mengubah data Teaching Practice
        <b><?= htmlspecialchars($edit_data['judul']) ?></b>.
        <a href="teaching_practice.php" style="float:right;color:#7a5a00;">✖ Batal Edit</a>
    </div>
<?php endif; ?>

<!-- ============ FORM ============ -->
<form method="post" class="form-card">
    <input type="hidden" name="id" value="<?= (int)$edit_data['id'] ?>">

    <div class="grid-3">

        <!-- Cari Siswa -->
        <div style="grid-column: span 3;">
            <label>🔍 Cari Siswa <?= $mode_edit ? '<small style="color:#888;">(tidak dapat diubah saat edit)</small>' : '' ?></label>
            <select name="santri_id" id="santri_id" <?= $mode_edit ? 'disabled' : 'required' ?>
                    style="width:100%;padding:10px;font-size:14px;">
                <option value="">-- Pilih Siswa (mapel | kelas | kelompok | nama) --</option>
                <?php if ($santri): 
                    // Reset pointer santri
                    $santri->data_seek(0);
                    while($s=$santri->fetch_assoc()): ?>
                    <option value="<?=$s['id']?>"
                            data-kelas="<?=htmlspecialchars($s['kelas'])?>"
                            data-kelompok="<?=htmlspecialchars($s['kelompok'] ?? '')?>"
                            data-pelajaran="<?=htmlspecialchars($s['pelajaran'] ?? '')?>"
                            data-sup1="<?=htmlspecialchars($s['sup_kelompok1'] ?? '')?>"
                            data-sup2="<?=htmlspecialchars($s['sup_kelompok2'] ?? '')?>"
                            <?=($edit_data['santri_id']==$s['id'])?'selected':''?>>
                        <?=htmlspecialchars($s['pelajaran'] ?? '-')?>
                        |
                        <?=htmlspecialchars($s['kelas'])?>
                        |
                        Kel. <?=htmlspecialchars($s['kelompok'] ?? '-')?>
                        |
                        <?=htmlspecialchars($s['siswa'])?>
                    </option>
                <?php endwhile; endif; ?>
            </select>
            <?php if ($mode_edit): ?>
                <input type="hidden" name="santri_id" value="<?= (int)$edit_data['santri_id'] ?>">
            <?php endif; ?>

            <div class="autofill-box" id="autofill-box" style="display:none;">
                ✅ <b>Auto-fill:</b>
                Kelas TP = <b id="af-kelas">-</b> &nbsp;|&nbsp;
                Kelompok = <b id="af-kelompok">-</b> &nbsp;|&nbsp;
                Mapel = <b id="af-mapel">-</b> &nbsp;|&nbsp;
                Sup1 = <b id="af-sup1">-</b> &nbsp;|&nbsp;
                Sup2 = <b id="af-sup2">-</b>
            </div>
        </div>

        <!-- Kelas TP -->
        <div>
            <label>Kelas Teaching Practice</label>
            <select name="kelas_tp" id="kelas_tp" required>
                <option value="">-- Pilih Kelas --</option>
                <?php foreach($KELAS_LIST as $k): ?>
                    <option value="<?=$k?>" <?=($edit_data['kelas_tp']==$k)?'selected':''?>>
                        <?=$k?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Kelompok -->
        <div>
            <label>Kelompok</label>
            <select name="kelompok" id="kelompok" required>
                <option value="">-- Pilih Kelompok --</option>
                <?php foreach($KELOMPOK_LIST as $kl): ?>
                    <option value="<?=$kl?>" <?=($edit_data['kelompok']==$kl)?'selected':''?>>
                        Kelompok <?=$kl?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Mapel -->
        <div>
            <label>Mata Pelajaran</label>
            <select name="mata_pelajaran" id="mata_pelajaran" required>
                <option value="">-- Pilih Mata Pelajaran --</option>
                <?php foreach($MAPEL_LIST as $m): ?>
                    <option value="<?=htmlspecialchars($m)?>"
                        <?=($edit_data['mata_pelajaran']==$m)?'selected':''?>>
                        <?=htmlspecialchars($m)?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Judul -->
        <div>
            <label>Judul</label>
            <input type="text" name="judul" required placeholder="Contoh: Latihan Mubtada"
                   value="<?=htmlspecialchars($edit_data['judul'])?>">
        </div>

        <!-- Supervisor 1 -->
        <div>
            <label>Supervisor 1</label>
            <select name="supervisor1" id="supervisor1" required>
                <option value="">-- Pilih Supervisor 1 --</option>
                <?php foreach($sup1_arr as $s): ?>
                    <option value="<?=htmlspecialchars($s)?>"
                        <?=($edit_data['supervisor1']==$s)?'selected':''?>>
                        <?=htmlspecialchars($s)?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Supervisor 2 -->
        <div>
            <label>Supervisor 2</label>
            <select name="supervisor2" id="supervisor2" required>
                <option value="">-- Pilih Supervisor 2 --</option>
                <?php foreach($sup2_arr as $s): ?>
                    <option value="<?=htmlspecialchars($s)?>"
                        <?=($edit_data['supervisor2']==$s)?'selected':''?>>
                        <?=htmlspecialchars($s)?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Assessor -->
        <div>
            <label>Assessor</label>
            <select name="assessor" required>
                <option value="">-- Pilih --</option>
                <?php foreach($ass_arr as $s): ?>
                    <option value="<?=htmlspecialchars($s)?>"
                        <?=($edit_data['assessor']==$s)?'selected':''?>>
                        <?=htmlspecialchars($s)?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Tanggal -->
        <div>
            <label>Tanggal</label>
            <input type="date" name="tanggal" required
                   value="<?=htmlspecialchars($edit_data['tanggal'] ?: date('Y-m-d'))?>">
        </div>

    </div>

    <button type="submit" name="simpan">
        <?= $mode_edit ? '💾 Update' : '💾 Simpan' ?>
    </button>
    <?php if ($mode_edit): ?>
        <a href="teaching_practice.php"
           style="margin-left:10px;padding:11px 24px;background:#95a5a6;
                  color:#fff;text-decoration:none;border-radius:6px;
                  display:inline-block;font-size:15px;">Batal</a>
    <?php endif; ?>
</form>

<!-- ============ TOOLBAR ============ -->
<div class="toolbar">
    <div class="toolbar-left">
        <a href="export_tp_csv.php?<?=http_build_query([
            'filter_kelas'    => $filter_kelas,
            'filter_kelompok' => $filter_kelompok,
            'filter_tgl'      => $filter_tgl
        ])?>" class="btn-download btn-csv">
            <i class="fa-solid fa-file-csv"></i> Download CSV
        </a>

        <a href="export_tp_pdf.php?<?=http_build_query([
            'filter_kelas'    => $filter_kelas,
            'filter_kelompok' => $filter_kelompok,
            'filter_tgl'      => $filter_tgl
        ])?>" target="_blank" class="btn-download btn-pdf">
            <i class="fa-solid fa-file-pdf"></i> Download PDF
        </a>
    </div>
</div>

<!-- ============ FILTER ============ -->
<div class="filter-bar">
    <b>🔍 Filter:</b>
    <form method="get" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
        <label>Kelas TP:</label>
        <select name="filter_kelas">
            <option value="">-- Semua Kelas TP --</option>
            <?php foreach($KELAS_LIST as $k): ?>
                <option value="<?=$k?>" <?=($filter_kelas==$k)?'selected':''?>><?=$k?></option>
            <?php endforeach; ?>
        </select>

        <label>Kelompok:</label>
        <select name="filter_kelompok">
            <option value="">-- Semua Kelompok --</option>
            <?php foreach($KELOMPOK_LIST as $kl): ?>
                <option value="<?=$kl?>" <?=($filter_kelompok==$kl)?'selected':''?>>Kelompok <?=$kl?></option>
            <?php endforeach; ?>
        </select>

        <label>Tanggal:</label>
        <input type="date" name="filter_tgl" value="<?=htmlspecialchars($filter_tgl)?>">

        <button type="submit">Tampilkan</button>
        <?php if($filter_kelas || $filter_tgl || $filter_kelompok): ?>
            <a href="teaching_practice.php" class="btn-reset">Reset</a>
        <?php endif; ?>
    </form>
</div>

<h3>Data Teaching Practice</h3>
<table>
<tr>
    <th>No</th>
    <th>Induk</th>
    <th>Siswa</th>
    <th>Kelas Siswa</th>
    <th>Kelas TP</th>
    <th>Kelompok</th>
    <th>Mapel</th>
    <th>Judul</th>
    <th>Supervisor 1</th>
    <th>Supervisor 2</th>
    <th>Assessor</th>
    <th>Tanggal</th>
    <th style="width:100px;">Aksi</th>
</tr>
<?php $i=1; foreach ($data_export as $d): ?>
<tr>
    <td><?=$i++?></td>
    <td><?=htmlspecialchars($d['induk'])?></td>
    <td><?=htmlspecialchars($d['siswa'])?></td>
    <td><span class="badge-kelas"><?=htmlspecialchars($d['kelas'])?></span></td>
    <td><span class="badge-kelas-tp"><?=htmlspecialchars($d['kelas_tp'])?></span></td>
    <td><span class="badge-kelompok">Kel. <?=htmlspecialchars($d['kelompok'])?></span></td>
    <td><span class="badge-mapel"><?=htmlspecialchars($d['mata_pelajaran'])?></span></td>
    <td><?=htmlspecialchars($d['judul'])?></td>
    <td><span class="badge-sup"><?=htmlspecialchars($d['supervisor1'] ?? '-')?></span></td>
    <td><span class="badge-sup2"><?=htmlspecialchars($d['supervisor2'] ?? '-')?></span></td>
    <td><span class="badge-assessor"><?=htmlspecialchars($d['assessor'] ?? '-')?></span></td>
    <td><?=date('d/m/Y', strtotime($d['tanggal']))?></td>
    <td class="aksi-cell">
        <div class="aksi-wrap">
            <a href="?edit=<?=$d['id']?>" class="btn-aksi btn-edit" title="Edit data">
                <i class="fa-solid fa-pen"></i>
            </a>
            <a href="?hapus=<?=$d['id']?>"
               onclick="return confirm('Hapus data <?=htmlspecialchars($d['siswa'])?>?')"
               class="btn-aksi btn-hapus" title="Hapus data">
                <i class="fa-solid fa-trash"></i>
            </a>
        </div>
    </td>
</tr>
<?php endforeach; ?>
<?php if($i === 1): ?>
<tr><td colspan="13" style="text-align:center;color:#999;font-style:italic;padding:20px;">
    Belum ada data teaching practice.
</td></tr>
<?php endif; ?>
</table>
</div>

<script>
const selSantri    = document.getElementById('santri_id');
const selKelasTp   = document.getElementById('kelas_tp');
const selKelompok  = document.getElementById('kelompok');
const selMapel     = document.getElementById('mata_pelajaran');
const selSup1      = document.getElementById('supervisor1');
const selSup2      = document.getElementById('supervisor2');
const autofillBox  = document.getElementById('autofill-box');

function setFieldHighlight(el, active) {
    if (!el) return;
    if (active) el.classList.add('field-filled');
    else el.classList.remove('field-filled');
}

if (selSantri && !selSantri.disabled) {
    selSantri.addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];

        if (!this.value) {
            if (selKelasTp) selKelasTp.value = '';
            if (selKelompok) selKelompok.value = '';
            if (selMapel) selMapel.value = '';
            if (selSup1) selSup1.value = '';
            if (selSup2) selSup2.value = '';
            if (autofillBox) autofillBox.style.display = 'none';
            [selKelasTp, selKelompok, selMapel, selSup1, selSup2]
                .forEach(el => setFieldHighlight(el, false));
            return;
        }

        const kelas     = opt.dataset.kelas     || '';
        const kelompok  = opt.dataset.kelompok  || '';
        const pelajaran = opt.dataset.pelajaran || '';
        const sup1      = opt.dataset.sup1      || '';
        const sup2      = opt.dataset.sup2      || '';

        if (kelas && selKelasTp)       selKelasTp.value = kelas;
        if (kelompok && selKelompok)   selKelompok.value = kelompok;
        if (pelajaran && selMapel)     selMapel.value = pelajaran;
        if (sup1 && selSup1)           selSup1.value = sup1;
        if (sup2 && selSup2)           selSup2.value = sup2;

        setFieldHighlight(selKelasTp, !!kelas);
        setFieldHighlight(selKelompok, !!kelompok);
        setFieldHighlight(selMapel, !!pelajaran);
        setFieldHighlight(selSup1, !!sup1);
        setFieldHighlight(selSup2, !!sup2);

        if (document.getElementById('af-kelas'))
            document.getElementById('af-kelas').textContent    = kelas || '-';
        if (document.getElementById('af-kelompok'))
            document.getElementById('af-kelompok').textContent = kelompok || '-';
        if (document.getElementById('af-mapel'))
            document.getElementById('af-mapel').textContent    = pelajaran || '-';
        if (document.getElementById('af-sup1'))
            document.getElementById('af-sup1').textContent     = sup1 || '-';
        if (document.getElementById('af-sup2'))
            document.getElementById('af-sup2').textContent     = sup2 || '-';
        if (autofillBox) autofillBox.style.display = 'block';
    });

    [selKelasTp, selKelompok, selMapel, selSup1, selSup2].forEach(el => {
        if (el) el.addEventListener('change', () => setFieldHighlight(el, false));
    });
}
</script>
</body>
</html>