<?php
require 'config/database.php';
cekLogin();

// ================== DAFTAR KRITERIA VALID ==================
$KRITERIA_VALID = [
    'Supervisor 1',
    'Supervisor 2',
    'Assessor'
];

// ================== PROSES SIMPAN MANUAL ==================
if (isset($_POST['simpan'])) {
    $nama_guru      = trim($_POST['nama_guru'] ?? '');
    $mata_pelajaran = trim($_POST['mata_pelajaran'] ?? '');
    $kriteria       = trim($_POST['kriteria'] ?? '');

    if ($nama_guru === '' || $mata_pelajaran === '' || !in_array($kriteria, $KRITERIA_VALID)) {
        $_SESSION['pesan'] = "❌ Data tidak valid! Periksa kembali input Anda.";
        header("Location: guru.php");
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO guru (nama_guru, mata_pelajaran, kriteria) VALUES (?,?,?)");
    if ($stmt) {
        $stmt->bind_param("sss", $nama_guru, $mata_pelajaran, $kriteria);
        if ($stmt->execute()) {
            $_SESSION['pesan'] = "✅ Data guru <b>" . htmlspecialchars($nama_guru) . "</b> berhasil disimpan.";
        } else {
            $_SESSION['pesan'] = "❌ Gagal menyimpan: " . $conn->error;
        }
        $stmt->close();
    } else {
        $_SESSION['pesan'] = "❌ Prepare gagal: " . $conn->error;
    }
    header("Location: guru.php");
    exit;
}

// ================== PROSES HAPUS ==================
if (isset($_GET['hapus'])) {
    $id = (int)$_GET['hapus'];
    if ($id > 0) {
        if ($conn->query("DELETE FROM guru WHERE id=$id")) {
            $_SESSION['pesan'] = "✅ Data guru berhasil dihapus.";
        } else {
            $_SESSION['pesan'] = "❌ Gagal menghapus: " . $conn->error;
        }
    }
    header("Location: guru.php");
    exit;
}

// ================== PROSES UPLOAD CSV ==================
if (isset($_POST['upload_csv'])) {
    if (!isset($_FILES['file_csv']) || $_FILES['file_csv']['error'] != 0) {
        $_SESSION['pesan'] = "❌ Gagal upload file CSV. Kode error: " . ($_FILES['file_csv']['error'] ?? 'N/A');
        header("Location: guru.php");
        exit;
    }

    $file = $_FILES['file_csv']['tmp_name'];
    $handle = fopen($file, "r");
    if ($handle === FALSE) {
        $_SESSION['pesan'] = "❌ Gagal membuka file CSV.";
        header("Location: guru.php");
        exit;
    }

    $firstRow = true;
    $inserted = 0;
    $updated  = 0;
    $gagal    = 0;
    $errors   = [];

    $stmt_insert    = $conn->prepare("INSERT INTO guru (nama_guru, mata_pelajaran, kriteria) VALUES (?,?,?)");
    $stmt_insert_id = $conn->prepare("INSERT INTO guru (id, nama_guru, mata_pelajaran, kriteria) VALUES (?,?,?,?)");
    $stmt_update    = $conn->prepare("UPDATE guru SET nama_guru=?, mata_pelajaran=?, kriteria=? WHERE id=?");
    $stmt_cek       = $conn->prepare("SELECT id FROM guru WHERE id=?");

    if (!$stmt_insert || !$stmt_insert_id || !$stmt_update || !$stmt_cek) {
        fclose($handle);
        $_SESSION['pesan'] = "❌ Prepare statement gagal: " . $conn->error;
        header("Location: guru.php");
        exit;
    }

    $baris = 0;
    while (($row = fgetcsv($handle, 10000, ",")) !== FALSE) {
        $baris++;

        if ($firstRow) {
            $firstRow = false;
            if (isset($row[0]) &&
                (stripos($row[0], 'id') !== false ||
                 stripos($row[0], 'guru') !== false ||
                 stripos($row[0], 'nama') !== false)) {
                continue;
            }
        }

        if (empty(array_filter($row))) {
            continue;
        }

        $id             = isset($row[0]) ? trim($row[0]) : '';
        $nama_guru      = isset($row[1]) ? trim($row[1]) : '';
        $mata_pelajaran = isset($row[2]) ? trim($row[2]) : '';
        $kriteria       = isset($row[3]) ? trim($row[3]) : '';

        $kriteria = str_replace(['&amp;', ' & ', '&amp; '], ' & ', $kriteria);
        $kriteria = preg_replace('/\s+/', ' ', $kriteria);
        $kriteria = trim($kriteria);

        if (!in_array($kriteria, $KRITERIA_VALID)) {
            $errors[] = "Baris $baris: kriteria '<b>$kriteria</b>' tidak valid (fallback ke 'Assessor')";
            $kriteria = 'Assessor';
        }

        if ($nama_guru === '' || $mata_pelajaran === '') {
            $gagal++;
            $errors[] = "Baris $baris: nama_guru atau mata_pelajaran kosong";
            continue;
        }

        if ($id !== '' && is_numeric($id) && $id > 0) {
            $id = (int)$id;
            $stmt_cek->bind_param("i", $id);
            $stmt_cek->execute();
            $res_cek = $stmt_cek->get_result();
            if ($res_cek->num_rows == 0) {
                $stmt_insert_id->bind_param("isss", $id, $nama_guru, $mata_pelajaran, $kriteria);
                if ($stmt_insert_id->execute()) $inserted++;
                else { $gagal++; $errors[] = "Baris $baris: " . $stmt_insert_id->error; }
            } else {
                $stmt_update->bind_param("sssi", $nama_guru, $mata_pelajaran, $kriteria, $id);
                if ($stmt_update->execute()) $updated++;
                else { $gagal++; $errors[] = "Baris $baris: " . $stmt_update->error; }
            }
        } else {
            $stmt_insert->bind_param("sss", $nama_guru, $mata_pelajaran, $kriteria);
            if ($stmt_insert->execute()) $inserted++;
            else { $gagal++; $errors[] = "Baris $baris: " . $stmt_insert->error; }
        }
    }

    $stmt_insert->close();
    $stmt_insert_id->close();
    $stmt_update->close();
    $stmt_cek->close();
    fclose($handle);

    $pesan = "✅ Import CSV selesai!<br>"
           . "• Baru: <b>$inserted</b><br>"
           . "• Update: <b>$updated</b><br>"
           . "• Gagal: <b>$gagal</b>";
    if (!empty($errors)) {
        $pesan .= "<br><br>⚠️ <b>Detail:</b><br>" . implode("<br>", array_slice($errors, 0, 20));
        if (count($errors) > 20) {
            $pesan .= "<br>... dan " . (count($errors) - 20) . " error lainnya.";
        }
    }
    $_SESSION['pesan'] = $pesan;
    header("Location: guru.php");
    exit;
}

// ================== AMBIL DATA ==================
$data = $conn->query("SELECT * FROM guru ORDER BY id DESC");
if (!$data) die("Query gagal: " . $conn->error);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Form Guru</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .header-info {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
        }
        .header-info h2, .header-info h3, .header-info h4 {
            margin: 5px 0;
        }
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
        .btn-print {
            background-color: #2c3e50;
            color: #fff;
            padding: 8px 14px;
            border-radius: 5px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-print:hover { background-color: #1a252f; }
        .upload-box {
            background: #f4f6f9;
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px dashed #aaa;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .upload-box input[type="file"] { font-size: 13px; }
        .btn-upload {
            background-color: #27ae60;
            color: #fff;
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
        }
        .btn-upload:hover { background-color: #1e8449; }
        .pesan {
            background: #d4edda;
            color: #155724;
            padding: 12px 16px;
            border-radius: 5px;
            margin-bottom: 15px;
            border: 1px solid #c3e6cb;
            line-height: 1.6;
        }
        .badge-kriteria {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            color: #fff;
        }
        .k-sup1     { background: #27ae60; }
        .k-sup2     { background: #2980b9; }
        .k-assessor { background: #8e44ad; }

        @media print {
            body * { visibility: hidden; }
            #print-area, #print-area * { visibility: visible; }
            #print-area { position: absolute; left: 0; top: 0; width: 100%; }
            .no-print, .no-print * { display: none !important; }
            .header-info { border-bottom: 2px solid #000; }
            table { width: 100%; border-collapse: collapse; }
            table th, table td { border: 1px solid #000; padding: 6px; font-size: 12px; }
            .btn-del, .toolbar, .form-card, .upload-box { display: none !important; }
            .badge-kriteria { background: none !important; color: #000 !important; padding: 0; }
        }
    </style>
</head>
<body>
<?php include 'partials/navbar.php'; ?>

<div class="container">

    <div id="print-area">
        <div class="header-info">
            <h2>Pondok Pesantren Daar el-Qolam 4</h2>
            <h3>Amaliah Tadris</h3>
            <h4>Tahun Pelajaran 2026-2027</h4>
        </div>

        <h3>Daftar Guru</h3>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Guru</th>
                    <th>Mata Pelajaran</th>
                    <th>Kriteria</th>
                    <th class="no-print">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($data->num_rows > 0): ?>
                <?php while($g=$data->fetch_assoc()): ?>
                    <?php
                    $badge_class = 'k-assessor';
                    if ($g['kriteria'] === 'Supervisor 1')         $badge_class = 'k-sup1';
                    elseif ($g['kriteria'] === 'Supervisor 2')     $badge_class = 'k-sup2';
                    elseif ($g['kriteria'] === 'Assessor')         $badge_class = 'k-assessor';
                    ?>
                    <tr>
                        <td><?=htmlspecialchars($g['id'])?></td>
                        <td><?=htmlspecialchars($g['nama_guru'])?></td>
                        <td><?=htmlspecialchars($g['mata_pelajaran'])?></td>
                        <td>
                            <span class="badge-kriteria <?=$badge_class?>">
                                <?=htmlspecialchars($g['kriteria'])?>
                            </span>
                        </td>
                        <td class="no-print">
                            <a href="?hapus=<?=$g['id']?>"
                               onclick="return confirm('Hapus data guru <?=htmlspecialchars($g['nama_guru'])?>?')"
                               class="btn-del">Hapus</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align:center;color:#999;font-style:italic;">
                        Belum ada data guru.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="toolbar no-print">
        <div class="toolbar-left">
            <button class="btn-print" onclick="window.print()">
                <i class="fa-solid fa-print"></i> Print
            </button>
        </div>
        <div class="toolbar-right">
            <form method="post" enctype="multipart/form-data" class="upload-box">
                <label style="font-size:13px; font-weight:bold;">
                    <i class="fa-solid fa-file-csv"></i> Upload CSV:
                </label>
                <input type="file" name="file_csv" accept=".csv" required>
                <button type="submit" name="upload_csv" class="btn-upload">
                    <i class="fa-solid fa-upload"></i> Upload
                </button>
            </form>
        </div>
    </div>

    <?php if (isset($_SESSION['pesan'])): ?>
        <div class="pesan no-print"><?= $_SESSION['pesan']; unset($_SESSION['pesan']); ?></div>
    <?php endif; ?>

    <h2 class="no-print">Form Guru</h2>
    <form method="post" class="form-card no-print">
        <label>Nama Guru</label>
        <input type="text" name="nama_guru" required maxlength="100">

        <label>Mata Pelajaran</label>
        <input type="text" name="mata_pelajaran" required maxlength="100">

        <label>Kriteria</label>
        <select name="kriteria" required>
            <option value="Supervisor 1">Supervisor 1</option>
            <option value="Supervisor 2">Supervisor 2</option>
            <option value="Assessor">Assessor</option>
        </select>

        <button type="submit" name="simpan">Simpan</button>
    </form>

    <div class="no-print" style="margin-top:15px; font-size:13px; color:#555; line-height:1.7;">
        <strong>Format CSV:</strong> <code>[id][guru][mata.pelajaran][kriteria]</code><br>
        <strong>Contoh:</strong><br>
        <code>1, Ahmad Fauzi, Matematika, Supervisor 1</code><br>
        <code>2, Siti Aminah, Bahasa Arab, Supervisor 2</code><br>
        <code>3, Dewi Lestari, Kimia, Assessor</code><br>
        <em>*Baris header otomatis dilewati.</em><br>
        <em>*Jika ID sudah ada → data akan di-<b>update</b>. Jika ID kosong → insert baru.</em><br>
        <em>*Nilai kriteria yang valid:
            <strong>Supervisor 1, Supervisor 2, Assessor</strong>.
            Jika tidak valid → fallback ke <b>Assessor</b>.
        </em>
    </div>

</div>
</body>
</html>