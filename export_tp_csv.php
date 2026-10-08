<?php
require 'config/database.php';
cekLogin();

$KELAS_LIST    = ['1A','1B','2A','2B','3A','3B','3C','4A','4B','5 IPA','5 IPS','6 IPA','6 IPS'];
$KELOMPOK_LIST = [1,2,3,4,5,6,7,8,9];

// ================== FILTER ==================
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

// ================== QUERY ==================
$data = $conn->query("SELECT tp.*, s.siswa, s.kelas, s.induk
                      FROM teaching_practice tp
                      JOIN santri s ON s.id=tp.santri_id
                      $where_sql
                      ORDER BY tp.tanggal DESC, tp.kelas_tp, tp.kelompok, s.siswa");
if (!$data) die("Query gagal: " . $conn->error);

// ================== HEADER CSV ==================
$filename = "Teaching_Practice_" . date('Ymd_His') . ".csv";

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');

// BOM UTF-8 agar Excel baca huruf dengan benar
fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

// ================== HEADER INFO ==================
fputcsv($out, ['YAYASAN PONDOK PESANTREN DAAR EL-QOLAM']);
fputcsv($out, ['Data Teaching Practice - Tahun Pelajaran 2026-2027']);
fputcsv($out, ['Filter: Kelas TP=' . ($filter_kelas ?: 'Semua') .
               ' | Kelompok=' . ($filter_kelompok ?: 'Semua') .
               ' | Tanggal=' . ($filter_tgl ?: 'Semua')]);
fputcsv($out, ['Dicetak: ' . date('d/m/Y H:i:s')]);
fputcsv($out, []); // baris kosong

// ================== HEADER TABEL ==================
fputcsv($out, [
    'No', 'Induk', 'Siswa', 'Kelas Siswa', 'Kelas TP', 'Kelompok',
    'Mapel', 'Judul', 'Supervisor 1', 'Supervisor 2', 'Assessor', 'Tanggal'
]);

// ================== ISI DATA ==================
$i = 1;
while ($d = $data->fetch_assoc()) {
    fputcsv($out, [
        $i++,
        $d['induk'],
        $d['siswa'],
        $d['kelas'],
        $d['kelas_tp'],
        $d['kelompok'],
        $d['mata_pelajaran'],
        $d['judul'],
        $d['supervisor1'] ?? '-',
        $d['supervisor2'] ?? '-',
        $d['assessor'] ?? '-',
        date('d/m/Y', strtotime($d['tanggal']))
    ]);
}

// ================== FOOTER ==================
fputcsv($out, []);
fputcsv($out, ['', '', 'TOTAL:', ($i - 1) . ' data']);

fclose($out);
exit;