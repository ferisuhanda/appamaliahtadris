<?php
require 'config/database.php';
cekLogin();

$KELOMPOK_LIST = [1,2,3,4,5,6,7,8,9];

$msg = ''; $msg_type = 'success';
$mode_edit = false;
$edit_data = [
    'id'          => 0,
    'kelompok'    => '',
    'supervisor1' => '',
    'supervisor2' => '',
];

// ================== AMBIL DATA SUPERVISOR ==================
$sup1_options = [];
$q1 = $conn->query("SELECT nama_guru FROM guru WHERE kriteria='Supervisor 1' ORDER BY nama_guru");
if ($q1) while ($s = $q1->fetch_assoc()) $sup1_options[] = $s['nama_guru'];

$sup2_options = [];
$q2 = $conn->query("SELECT nama_guru FROM guru WHERE kriteria='Supervisor 2' ORDER BY nama_guru");
if ($q2) while ($s = $q2->fetch_assoc()) $sup2_options[] = $s['nama_guru'];

// ================== SIMPAN / UPDATE ==================
if (isset($_POST['simpan'])) {
    $id          = (int)($_POST['id'] ?? 0);
    $kelompok    = (int)($_POST['kelompok'] ?? 0);
    $supervisor1 = trim($_POST['supervisor1'] ?? '');
    $supervisor2 = trim($_POST['supervisor2'] ?? '');

    // Validasi
    if (!in_array($kelompok, $KELOMPOK_LIST) ||
        !in_array($supervisor1, $sup1_options) ||
        !in_array($supervisor2, $sup2_options)) {
        $msg = "❌ Data tidak valid! Periksa kembali input Anda.";
        $msg_type = 'error';
    } else {
        if ($id > 0) {
            // UPDATE
            $stmt = $conn->prepare("UPDATE kelompok_amaliah 
                SET kelompok=?, supervisor1=?, supervisor2=? 
                WHERE id=?");
            $stmt->bind_param("issi", $kelompok, $supervisor1, $supervisor2, $id);
            if ($stmt->execute()) {
                $msg = "✅ Data kelompok <b>Kel. $kelompok</b> berhasil diupdate.";
            } else {
                $msg = "❌ Gagal update: " . $conn->error;
                $msg_type = 'error';
            }
            $stmt->close();
        } else {
            // INSERT
            $stmt = $conn->prepare("INSERT INTO kelompok_amaliah 
                (kelompok, supervisor1, supervisor2) 
                VALUES (?,?,?)");
            $stmt->bind_param("iss", $kelompok, $supervisor1, $supervisor2);
            if ($stmt->execute()) {
                $msg = "✅ Data kelompok <b>Kel. $kelompok</b> berhasil disimpan.";
            } else {
                $msg = "❌ Gagal simpan: " . $conn->error;
                $msg_type = 'error';
            }
            $stmt->close();
        }
    }
    header("Location: kelompok_amaliah.php?msg=" . urlencode($msg) . "&type=$msg_type");
    exit;
}

// ================== HAPUS ==================
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    if ($id > 0) {
        if ($conn->query("DELETE FROM kelompok_amaliah WHERE id=$id")) {
            $msg = "✅ Data kelompok berhasil dihapus.";
        } else {
            $msg = "❌ Gagal menghapus: " . $conn->error;
            $msg_type = 'error';
        }
    }
    header("Location: kelompok_amaliah.php?msg=" . urlencode($msg) . "&type=$msg_type");
    exit;
}

// ================== MODE EDIT ==================
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $q = $conn->query("SELECT * FROM kelompok_amaliah WHERE id=$id");
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

// ================== QUERY DATA ==================
$data = $conn->query("SELECT * FROM kelompok_amaliah ORDER BY kelompok");
if (!$data) die("Query gagal: " . $conn->error);

// Hitung total
$total = $conn->query("SELECT COUNT(*) AS t FROM kelompok_amaliah");
$total_row = $total ? ($total->fetch_assoc()['t'] ?? 0) : 0;
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Kelompok Amaliah</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
    .counter-badge { display:inline-block; background:#1e3c72; color:#fff;
        padding:4px 12px; border-radius:20px; font-size:13px; margin-left:8px; }

    .alert-success { background:#e8f8ee; color:#219150; padding:12px 16px;
        border-radius:6px; margin-bottom:15px; border-left:4px solid #27ae60; }
    .alert-error { background:#fdecea; color:#c0392b; padding:12px 16px;
        border-radius:6px; margin-bottom:15px; border-left:4px solid #e74c3c; }

    .badge-kelompok { background:#16a085; color:#fff; padding:4px 12px;
        border-radius:12px; font-size:13px; font-weight:bold; }
    .badge-sup { background:#27ae60; color:#fff; padding:3px 10px;
        border-radius:10px; font-size:12px; }
    .badge-sup2 { background:#2980b9; color:#fff; padding:3px 10px;
        border-radius:10px; font-size:12px; }

    .mode-edit-box {
        background:#fff8e1; border-left:4px solid #f39c12;
        padding:12px 16px; border-radius:6px; margin-bottom:15px;
        font-size:14px; color:#7a5a00;
    }

    /* ============ AKSI BUTTON ============ */
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
        gap: 5px;
        padding: 6px 12px;
        border-radius: 6px;
        font-size: 12.5px;
        font-weight: 600;
        text-decoration: none;
        color: #fff;
        border: none;
        cursor: pointer;
        transition: .15s;
        line-height: 1;
        white-space: nowrap;
    }
    .btn-edit {
        background: #f39c12;
    }
    .btn-edit:hover { background: #e67e22; }
    .btn-hapus {
        background: #e74c3c;
    }
    .btn-hapus:hover { background: #c0392b; }

    /* Kolom aksi di tengah */
    table td.aksi-cell {
        text-align: center;
        vertical-align: middle;
    }

    /* Hover baris */
    table tbody tr {
        transition: background .15s;
    }
    table tbody tr:hover {
        background: #f9fbff;
    }

    @media print {
        .navbar, .btn-print, .no-print, .form-card, .info-box {
            display:none !important;
        }
        table { width: 100%; border-collapse: collapse; }
        table th, table td { border: 1px solid #000; padding: 6px; font-size: 12px; }
    }
</style>
</head>
<body>
<?php include 'partials/navbar.php'; ?>
<div class="container">

    <h2>👥 Kelompok Amaliah
        <span class="counter-badge">Total: <?= $total_row ?> kelompok</span>
    </h2>

    <?php if(!empty($msg)): ?>
        <div class="alert-<?= $msg_type ?>"><?= $msg ?></div>
    <?php endif; ?>

    <?php if ($mode_edit): ?>
        <div class="mode-edit-box">
            ✏️ <b>Mode Edit:</b> Mengubah data kelompok
            <b>Kel. <?= htmlspecialchars($edit_data['kelompok']) ?></b>.
            <a href="kelompok_amaliah.php" style="float:right;color:#7a5a00;">✖ Batal Edit</a>
        </div>
    <?php endif; ?>

    <!-- ============ FORM INPUT ============ -->
    <div class="form-card">
        <h3><?= $mode_edit ? '✏️ Edit Kelompok Amaliah' : '📝 Form Kelompok Amaliah' ?></h3>
        <form method="post">
            <input type="hidden" name="id" value="<?= (int)$edit_data['id'] ?>">

            <div class="grid-3">
                <div>
                    <label>Kelompok</label>
                    <select name="kelompok" required>
                        <option value="">-- Pilih Kelompok --</option>
                        <?php foreach($KELOMPOK_LIST as $kl): ?>
                            <option value="<?=$kl?>"
                                <?=($edit_data['kelompok']==$kl)?'selected':''?>>
                                Kelompok <?=$kl?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Supervisor 1</label>
                    <select name="supervisor1" required>
                        <option value="">-- Pilih Supervisor 1 --</option>
                        <?php foreach($sup1_options as $s): ?>
                            <option value="<?=htmlspecialchars($s)?>"
                                <?=($edit_data['supervisor1']==$s)?'selected':''?>>
                                <?=htmlspecialchars($s)?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label>Supervisor 2</label>
                    <select name="supervisor2" required>
                        <option value="">-- Pilih Supervisor 2 --</option>
                        <?php foreach($sup2_options as $s): ?>
                            <option value="<?=htmlspecialchars($s)?>"
                                <?=($edit_data['supervisor2']==$s)?'selected':''?>>
                                <?=htmlspecialchars($s)?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <button type="submit" name="simpan">
                <?= $mode_edit ? '💾 Update' : '💾 Simpan' ?>
            </button>
            <?php if ($mode_edit): ?>
                <a href="kelompok_amaliah.php"
                   style="margin-left:10px;padding:11px 24px;background:#95a5a6;
                          color:#fff;text-decoration:none;border-radius:6px;
                          display:inline-block;font-size:15px;">Batal</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- ============ DATAGRID ============ -->
    <h3>📋 Data Kelompok Amaliah</h3>
    <table>
        <thead>
            <tr>
                <th style="width:50px; text-align:center;">No</th>
                <th style="text-align:center;">Kelompok</th>
                <th style="text-align:center;">Supervisor 1</th>
                <th style="text-align:center;">Supervisor 2</th>
                <th style="width:180px; text-align:center;">Aksi</th>
            </tr>
        </thead>
        <tbody>
        <?php if ($data->num_rows > 0): ?>
            <?php $i=1; while($d=$data->fetch_assoc()): ?>
            <tr>
                <td style="text-align:center;"><?=$i++?></td>
                <td style="text-align:center;">
                    <span class="badge-kelompok">Kel. <?=htmlspecialchars($d['kelompok'])?></span>
                </td>
                <td style="text-align:center;">
                    <span class="badge-sup"><?=htmlspecialchars($d['supervisor1'] ?? '-')?></span>
                </td>
                <td style="text-align:center;">
                    <span class="badge-sup2"><?=htmlspecialchars($d['supervisor2'] ?? '-')?></span>
                </td>
                <td class="aksi-cell">
                    <div class="aksi-wrap">
                        <a href="?edit=<?=$d['id']?>" class="btn-aksi btn-edit" title="Edit data">
                            ✏️ Edit
                        </a>
                        <a href="?hapus=<?=$d['id']?>"
                           onclick="return confirm('Hapus data Kelompok <?=htmlspecialchars($d['kelompok'])?>?')"
                           class="btn-aksi btn-hapus" title="Hapus data">
                            🗑 Hapus
                        </a>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
        <?php else: ?>
            <tr>
                <td colspan="5" style="text-align:center;color:#999;font-style:italic;padding:20px;">
                    Belum ada data kelompok amaliah.
                </td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>

</div>
</body>
</html>