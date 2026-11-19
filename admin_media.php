<?php
defined('INDEX_AUTH') OR die('Direct access is not allowed!');
global $dbs, $sysconf;

// Pastikan tabel database sudah ada & kolom transisi dan urutan tersedia
$dbs->query("CREATE TABLE IF NOT EXISTS `plugin_kiosk_media` (
  `media_id` INT(11) NOT NULL AUTO_INCREMENT,
  `media_title` VARCHAR(255) NOT NULL,
  `media_type` ENUM('foto','video') NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `transition_style` VARCHAR(50) NOT NULL DEFAULT 'fade',
  `transition_duration` INT(11) NOT NULL DEFAULT 5,
  `order_num` INT(11) NOT NULL DEFAULT 0,
  `created_date` DATETIME NOT NULL,
  PRIMARY KEY (`media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Update tabel jika kolom belum ada
$col_check = $dbs->query("SHOW COLUMNS FROM `plugin_kiosk_media` LIKE 'transition_style'");
if ($col_check && $col_check->num_rows == 0) {
    $dbs->query("ALTER TABLE `plugin_kiosk_media` ADD COLUMN `transition_style` VARCHAR(50) NOT NULL DEFAULT 'fade'");
}
$col_check2 = $dbs->query("SHOW COLUMNS FROM `plugin_kiosk_media` LIKE 'transition_duration'");
if ($col_check2 && $col_check2->num_rows == 0) {
    $dbs->query("ALTER TABLE `plugin_kiosk_media` ADD COLUMN `transition_duration` INT(11) NOT NULL DEFAULT 5");
}
$col_check3 = $dbs->query("SHOW COLUMNS FROM `plugin_kiosk_media` LIKE 'order_num'");
if ($col_check3 && $col_check3->num_rows == 0) {
    $dbs->query("ALTER TABLE `plugin_kiosk_media` ADD COLUMN `order_num` INT(11) NOT NULL DEFAULT 0");
    $dbs->query("UPDATE `plugin_kiosk_media` SET `order_num` = `media_id` WHERE `order_num` = 0");
}

// Path direktori penyimpanan file upload
$upload_dir = SB . 'files/kiosk_media/';
if (!is_dir($upload_dir)) {
    @mkdir($upload_dir, 0755, true);
}

// Bersihkan parameter 'del' dari query string agar tidak menumpuk saat Hapus atau Upload
$get_params = $_GET;
unset($get_params['del']);
$base_url = $_SERVER['PHP_SELF'] . '?' . http_build_query($get_params);

$pesan = '';

// 1. Proses Hapus Media
if (isset($_GET['del']) && is_numeric($_GET['del'])) {
    $id_del = (int)$_GET['del'];
    $q_file = $dbs->query("SELECT file_path FROM plugin_kiosk_media WHERE media_id = $id_del");
    if ($q_file && $q_file->num_rows > 0) {
        $d_file = $q_file->fetch_assoc();
        if (!empty($d_file['file_path']) && file_exists($upload_dir . $d_file['file_path'])) {
            @unlink($upload_dir . $d_file['file_path']);
        }
        $dbs->query("DELETE FROM plugin_kiosk_media WHERE media_id = $id_del");
        $pesan = "<div class='kiosk-alert kiosk-alert-success'>✨ Media berhasil dihapus dari Kiosk!</div>";
    }
}

// 2. Proses Perbarui Transisi & Urutan Tayang (Update Settings)
if (isset($_POST['update_transition'])) {
    $media_id = (int)$_POST['edit_media_id'];
    $edit_style = isset($_POST['edit_style']) ? $dbs->escape_string(trim($_POST['edit_style'])) : 'fade';
    $edit_duration = isset($_POST['edit_duration']) ? (int)$_POST['edit_duration'] : 5;
    if ($edit_duration < 2 || $edit_duration > 60) $edit_duration = 5;
    $edit_order = isset($_POST['edit_order']) ? (int)$_POST['edit_order'] : 1;

    $dbs->query("UPDATE plugin_kiosk_media SET transition_style = '$edit_style', transition_duration = $edit_duration, order_num = $edit_order WHERE media_id = $media_id");
    $pesan = "<div class='kiosk-alert kiosk-alert-success'>✅ Pengaturan transisi dan urutan tayang berhasil diperbarui!</div>";
}

// 3. Proses Upload Media Baru
if (isset($_POST['upload'])) {
    if (empty($_FILES) && empty($_POST['media_title']) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $pesan = "<div class='kiosk-alert kiosk-alert-error'>❌ Ukuran file terlalu besar! Melebihi batas server (post_max_size: " . ini_get('post_max_size') . ").</div>";
    } else {
        $title = isset($_POST['media_title']) ? $dbs->escape_string(trim($_POST['media_title'])) : '';
        $type  = isset($_POST['media_type']) ? $dbs->escape_string(trim($_POST['media_type'])) : 'foto';
        
        $transition_style = isset($_POST['transition_style']) ? $dbs->escape_string(trim($_POST['transition_style'])) : 'fade';
        $allowed_styles = ['fade', 'slide-left', 'slide-right', 'zoom-in', 'zoom-out', 'flip', 'bounce'];
        if (!in_array($transition_style, $allowed_styles)) {
            $transition_style = 'fade';
        }

        $transition_duration = isset($_POST['transition_duration']) ? (int)$_POST['transition_duration'] : 5;
        if ($transition_duration < 2 || $transition_duration > 60) {
            $transition_duration = 5;
        }

        // Tentukan urutan berikutnya
        $q_max = $dbs->query("SELECT MAX(order_num) as max_order FROM plugin_kiosk_media");
        $d_max = $q_max ? $q_max->fetch_assoc() : null;
        $next_order = ($d_max && isset($d_max['max_order'])) ? ((int)$d_max['max_order'] + 1) : 1;

        if (!empty($_FILES['media_file']['name']) && !empty($title)) {
            if ($_FILES['media_file']['error'] !== UPLOAD_ERR_OK) {
                $err_msg = 'Terjadi kesalahan saat mengunggah file (Kode: ' . $_FILES['media_file']['error'] . ').';
                if ($_FILES['media_file']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['media_file']['error'] === UPLOAD_ERR_FORM_SIZE) {
                    $err_msg = 'Ukuran file melebihi batas upload maksimal server (' . ini_get('upload_max_filesize') . ')!';
                } elseif ($_FILES['media_file']['error'] === UPLOAD_ERR_PARTIAL) {
                    $err_msg = 'File hanya terunggah sebagian. Silakan coba kembali.';
                }
                $pesan = "<div class='kiosk-alert kiosk-alert-error'>❌ {$err_msg}</div>";
            } else {
                $ext = strtolower(pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION));
                $clean_filename = preg_replace("/[^a-zA-Z0-9\._-]/", "_", basename($_FILES['media_file']['name']));
                $file_name = time() . '_' . $clean_filename;
                $target_file = $upload_dir . $file_name;
                
                $valid_foto  = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                $valid_video = ['mp4', 'webm', 'ogg'];
                
                $allowed = ($type === 'foto') ? $valid_foto : $valid_video;
                
                if (in_array($ext, $allowed)) {
                    if (move_uploaded_file($_FILES['media_file']['tmp_name'], $target_file)) {
                        $now = date('Y-m-d H:i:s');
                        $sql = "INSERT INTO plugin_kiosk_media (media_title, media_type, file_path, transition_style, transition_duration, order_num, created_date) 
                                VALUES ('$title', '$type', '$file_name', '$transition_style', $transition_duration, $next_order, '$now')";
                        
                        if ($dbs->query($sql)) {
                            $pesan = "<div class='kiosk-alert kiosk-alert-success'>🎉 Media baru berhasil diunggah dengan nomor urutan #{$next_order} (Transisi: {$transition_style}, {$transition_duration} detik)!</div>";
                        } else {
                            @unlink($target_file);
                            $pesan = "<div class='kiosk-alert kiosk-alert-error'>❌ Gagal menyimpan ke database: " . $dbs->error . "</div>";
                        }
                    } else {
                        $pesan = "<div class='kiosk-alert kiosk-alert-error'>❌ Gagal memindahkan file upload ke folder server. Pastikan folder 'files/kiosk_media/' writable.</div>";
                    }
                } else {
                    $pesan = "<div class='kiosk-alert kiosk-alert-error'>❌ Format file (.{$ext}) tidak didukung untuk kategori {$type}! Format yang didukung: " . implode(', ', $allowed) . "</div>";
                }
            }
        } else {
            $pesan = "<div class='kiosk-alert kiosk-alert-error'>❌ Judul kegiatan dan file media wajib diisi!</div>";
        }
    }

    // Jika form disubmit melalui iframe fallback (kioskUploadFrame)
    if (isset($_POST['target_iframe']) && $_POST['target_iframe'] === '1') {
        echo '<script type="text/javascript">';
        if (strpos($pesan, 'kiosk-alert-success') !== false) {
            echo 'if (typeof top.toastr !== "undefined") { top.toastr.success("Media berhasil diunggah!", "Sukses"); }';
        } else {
            $clean_err = addslashes(strip_tags($pesan));
            echo 'if (typeof top.toastr !== "undefined") { top.toastr.error("' . $clean_err . '", "Gagal"); } else { alert("' . $clean_err . '"); }';
        }
        echo 'if (top.$("#mainContent").length > 0 && typeof top.$.fn.simbioAJAX === "function") {
                top.$("#mainContent").simbioAJAX("' . $base_url . '");
              } else {
                top.location.href = "' . $base_url . '";
              }';
        echo '</script>';
        exit;
    }
}

// Hitung data statistik media
$q_stats = $dbs->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN media_type = 'foto' THEN 1 ELSE 0 END) as total_foto,
    SUM(CASE WHEN media_type = 'video' THEN 1 ELSE 0 END) as total_video
    FROM plugin_kiosk_media");
$stats = $q_stats ? $q_stats->fetch_assoc() : ['total' => 0, 'total_foto' => 0, 'total_video' => 0];
$total_media = (int)($stats['total'] ?? 0);
$total_foto  = (int)($stats['total_foto'] ?? 0);
$total_video = (int)($stats['total_video'] ?? 0);

// Helper nama gaya transisi
function get_trans_label($style) {
    $labels = [
        'fade'        => '✨ Memudar Halus (Fade)',
        'slide-left'  => '➡️ Geser ke Kiri (Slide Left)',
        'slide-right' => '⬅️ Geser ke Kanan (Slide Right)',
        'zoom-in'     => '🔍 Zoom In (Membesar)',
        'zoom-out'    => '🔎 Zoom Out (Mengecil)',
        'flip'        => '🔄 Membalik 3D (Flip)',
        'bounce'      => '⚡ Memantul (Bounce)',
    ];
    return $labels[$style] ?? '✨ Memudar Halus (Fade)';
}
?>

<!-- Cegah halaman terbuka fullscreen di luar layout SLiMS Admin -->
<script type="text/javascript">
if (window.self === window.top && $('#mainMenu').length === 0 && $('.s-sidebar').length === 0 && $('#sidepan').length === 0) {
    window.location.href = 'index.php?mod=system';
}
</script>

<!-- STYLE KHUSUS KIOSK MEDIA (MODERN CARD UI & FRIENDLY CONTROLS) -->
<style>
.kiosk-container {
    padding: 10px 5px 30px 5px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
    color: #1e293b;
}

/* Header & Banner */
.kiosk-header {
    background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
    border-radius: 14px;
    padding: 24px;
    color: #ffffff;
    box-shadow: 0 4px 15px rgba(2, 132, 199, 0.2);
    margin-bottom: 25px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
}
.kiosk-header h2 {
    margin: 0 0 6px 0;
    font-size: 1.6rem;
    font-weight: 700;
    color: #ffffff;
}
.kiosk-header p {
    margin: 0;
    font-size: 0.95rem;
    color: #e0f2fe;
}
.kiosk-header-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.kiosk-btn-hero {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #ffffff;
    color: #0369a1;
    font-weight: 600;
    font-size: 0.9rem;
    padding: 10px 18px;
    border-radius: 8px;
    text-decoration: none;
    box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
}
.kiosk-btn-hero:hover {
    background: #f0f9ff;
    color: #0284c7;
    transform: translateY(-1px);
    text-decoration: none;
}
.kiosk-btn-hero-dark {
    background: rgba(15, 23, 42, 0.4);
    color: #ffffff;
    border: 1px solid rgba(255,255,255,0.25);
}
.kiosk-btn-hero-dark:hover {
    background: rgba(15, 23, 42, 0.6);
    color: #ffffff;
}

/* Alert Boxes */
.kiosk-alert {
    padding: 14px 18px;
    border-radius: 10px;
    font-size: 0.95rem;
    font-weight: 500;
    margin-bottom: 20px;
}
.kiosk-alert-success {
    background: #f0fdf4;
    border: 1px solid #86efac;
    color: #166534;
}
.kiosk-alert-error {
    background: #fef2f2;
    border: 1px solid #fca5a5;
    color: #991b1b;
}

/* Stat Cards Bar */
.kiosk-stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}
.kiosk-stat-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 18px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.03);
    display: flex;
    align-items: center;
    gap: 15px;
}
.kiosk-stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}
.kiosk-stat-total { background: #e0f2fe; color: #0284c7; }
.kiosk-stat-foto { background: #fef3c7; color: #d97706; }
.kiosk-stat-video { background: #f3e8ff; color: #9333ea; }
.kiosk-stat-number {
    font-size: 1.5rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.2;
}
.kiosk-stat-label {
    font-size: 0.85rem;
    color: #64748b;
    font-weight: 500;
}

/* Card Form Container */
.kiosk-form-card {
    background: #ffffff;
    border: 1px solid #cbd5e1;
    border-radius: 14px;
    padding: 24px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.04);
    margin-bottom: 35px;
}
.kiosk-form-title {
    margin: 0 0 4px 0;
    font-size: 1.25rem;
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    gap: 8px;
}
.kiosk-form-subtitle {
    margin: 0 0 20px 0;
    font-size: 0.88rem;
    color: #64748b;
}

/* Template Presets Grid */
.kiosk-template-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 12px;
    margin-top: 8px;
    margin-bottom: 15px;
}
.kiosk-template-btn {
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px;
    background: #f8fafc;
    text-align: left;
    cursor: pointer;
    transition: all 0.2s ease;
    user-select: none;
}
.kiosk-template-btn:hover {
    border-color: #38bdf8;
    background: #f0f9ff;
    transform: translateY(-2px);
}
.kiosk-template-btn.active {
    border-color: #0284c7;
    background: #e0f2fe;
    box-shadow: 0 2px 8px rgba(2, 132, 199, 0.2);
}
.kiosk-tpl-name {
    font-size: 0.9rem;
    font-weight: 700;
    color: #0f172a;
    margin-bottom: 3px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.kiosk-tpl-desc {
    font-size: 0.78rem;
    color: #64748b;
}

/* Duration Quick Select Pills */
.kiosk-duration-pills {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 6px;
}
.kiosk-dur-pill {
    padding: 6px 14px;
    border-radius: 20px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    font-size: 0.85rem;
    font-weight: 600;
    color: #334155;
    cursor: pointer;
    transition: all 0.15s ease;
}
.kiosk-dur-pill:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
}
.kiosk-dur-pill.active {
    background: #0284c7;
    border-color: #0284c7;
    color: #ffffff;
}

/* Live Animation Preview Box */
.kiosk-preview-box {
    background: #0f172a;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    color: #fff;
    margin-top: 15px;
    overflow: hidden;
    height: 120px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    position: relative;
}
.kiosk-preview-badge {
    position: absolute;
    top: 8px;
    left: 10px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    background: rgba(255,255,255,0.15);
    padding: 2px 8px;
    border-radius: 4px;
    color: #38bdf8;
}
.kiosk-preview-element {
    font-size: 1.1rem;
    font-weight: 700;
    color: #38bdf8;
    background: rgba(255,255,255,0.1);
    padding: 8px 24px;
    border-radius: 6px;
    border: 1px dashed rgba(56, 189, 248, 0.4);
    display: inline-block;
}

/* Dropzone Styling */
.kiosk-dropzone {
    border: 2px dashed #94a3b8;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    background: #f8fafc;
    cursor: pointer;
    transition: all 0.2s ease;
}
.kiosk-dropzone:hover {
    border-color: #0284c7;
    background: #f0f9ff;
}
.kiosk-dropzone-icon {
    font-size: 2rem;
    color: #0284c7;
    margin-bottom: 6px;
}
.kiosk-dropzone-text {
    font-size: 0.95rem;
    font-weight: 600;
    color: #334155;
    margin-bottom: 2px;
}
.kiosk-dropzone-sub {
    font-size: 0.8rem;
    color: #64748b;
}

/* Card Gallery Section (NO TABLE) */
.kiosk-gallery-header {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    gap: 15px;
}
.kiosk-gallery-title {
    font-size: 1.35rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0;
}
.kiosk-filter-bar {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.kiosk-filter-btn {
    padding: 7px 16px;
    border-radius: 20px;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    font-size: 0.85rem;
    font-weight: 600;
    color: #475569;
    cursor: pointer;
    transition: all 0.2s ease;
}
.kiosk-filter-btn:hover {
    background: #f1f5f9;
}
.kiosk-filter-btn.active {
    background: #0f172a;
    border-color: #0f172a;
    color: #ffffff;
}

/* Card Grid Layout */
.kiosk-media-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(290px, 1fr));
    gap: 22px;
}
.kiosk-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 4px 10px rgba(0,0,0,0.03);
    transition: all 0.25s ease;
    display: flex;
    flex-direction: column;
}
.kiosk-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 22px rgba(0,0,0,0.08);
    border-color: #cbd5e1;
}

/* Card Media Preview */
.kiosk-card-media {
    position: relative;
    width: 100%;
    height: 190px;
    background: #0f172a;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}
.kiosk-card-media img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}
.kiosk-card:hover .kiosk-card-media img {
    transform: scale(1.04);
}
.kiosk-card-media video {
    width: 100%;
    height: 100%;
    object-fit: contain;
    background: #000;
}
.kiosk-card-badge-type {
    position: absolute;
    top: 10px;
    left: 10px;
    font-size: 11px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 6px;
    text-transform: uppercase;
    color: #fff;
    box-shadow: 0 2px 6px rgba(0,0,0,0.3);
    z-index: 2;
}
.kiosk-badge-foto { background: #0284c7; }
.kiosk-badge-video { background: #9333ea; }

.kiosk-card-badge-duration {
    position: absolute;
    top: 10px;
    right: 10px;
    font-size: 11px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 6px;
    background: rgba(15, 23, 42, 0.85);
    color: #f8fafc;
    backdrop-filter: blur(4px);
    z-index: 2;
}

.kiosk-card-badge-order {
    position: absolute;
    bottom: 10px;
    left: 10px;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 6px;
    background: rgba(15, 23, 42, 0.8);
    color: #38bdf8;
    backdrop-filter: blur(4px);
    z-index: 2;
}

/* Card Body */
.kiosk-card-body {
    padding: 16px 18px;
    flex: 1;
    display: flex;
    flex-direction: column;
}
.kiosk-card-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: #0f172a;
    margin: 0 0 10px 0;
    line-height: 1.4;
    word-break: break-word;
}
.kiosk-card-meta {
    font-size: 0.82rem;
    color: #64748b;
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.kiosk-trans-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    background: #f1f5f9;
    color: #334155;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.8rem;
    font-weight: 600;
    margin-top: 6px;
    margin-bottom: 12px;
    border: 1px solid #e2e8f0;
}

/* Card Actions */
.kiosk-card-actions {
    border-top: 1px solid #f1f5f9;
    padding: 12px 18px;
    background: #f8fafc;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
}
.kiosk-btn-action {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 7px 12px;
    border-radius: 6px;
    font-size: 0.82rem;
    font-weight: 600;
    text-decoration: none;
    border: 1px solid #cbd5e1;
    background: #ffffff;
    color: #334155;
    cursor: pointer;
    transition: all 0.15s ease;
}
.kiosk-btn-action:hover {
    background: #e2e8f0;
    color: #0f172a;
    text-decoration: none;
}
.kiosk-btn-danger {
    color: #dc2626;
    border-color: #fecaca;
    background: #fff;
}
.kiosk-btn-danger:hover {
    background: #fee2e2;
    border-color: #f87171;
    color: #b91c1c;
}
.kiosk-btn-primary-sm {
    background: #0284c7;
    border-color: #0284c7;
    color: #ffffff;
}
.kiosk-btn-primary-sm:hover {
    background: #0369a1;
    color: #ffffff;
}

/* Modal Styling untuk Edit Transisi Cepat */
.kiosk-modal-backdrop {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.6);
    z-index: 99999;
    backdrop-filter: blur(3px);
    justify-content: center;
    align-items: center;
}
.kiosk-modal-content {
    background: #ffffff;
    border-radius: 14px;
    width: 90%;
    max-width: 480px;
    padding: 24px;
    box-shadow: 0 15px 35px rgba(0,0,0,0.25);
    animation: modalPop 0.25s ease-out;
}
@keyframes modalPop {
    from { opacity: 0; transform: scale(0.92); }
    to { opacity: 1; transform: scale(1); }
}

/* Empty State */
.kiosk-empty-state {
    text-align: center;
    padding: 60px 20px;
    background: #ffffff;
    border: 2px dashed #cbd5e1;
    border-radius: 16px;
    color: #64748b;
}
.kiosk-empty-icon {
    font-size: 3.5rem;
    color: #94a3b8;
    margin-bottom: 15px;
}
</style>

<div class="kiosk-container">

    <!-- Header Hero Kiosk -->
    <div class="kiosk-header">
        <div>
            <h2>Kelola Galeri & Video Kiosk</h2>
            <p>Atur foto dan video kegiatan perpustakaan beserta gaya dan durasi transisinya untuk layar TV Kiosk Fullscreen.</p>
        </div>
        <div class="kiosk-header-actions">
            <a href="<?php echo SWB; ?>index.php?p=kiosk-berita" target="_blank" class="kiosk-btn-hero" title="Buka tampilan fullscreen di monitor / TV">
                📺 Buka Kiosk TV Fullscreen
            </a>
            <button type="button" onclick="$('#kiosk_media_title').focus(); $('html, body').animate({scrollTop: $('#kioskUploadFormCard').offset().top - 20}, 400);" class="kiosk-btn-hero kiosk-btn-hero-dark">
                ➕ Unggah Media Baru
            </button>
        </div>
    </div>

    <!-- Alert Area -->
    <div id="kioskAlertArea">
        <?php echo $pesan; ?>
    </div>

    <!-- Bar Kartu Statistik Ringkas -->
    <div class="kiosk-stats-row">
        <div class="kiosk-stat-card">
            <div class="kiosk-stat-icon kiosk-stat-total">📊</div>
            <div>
                <div class="kiosk-stat-number"><?php echo $total_media; ?></div>
                <div class="kiosk-stat-label">Total Playlist Media</div>
            </div>
        </div>
        <div class="kiosk-stat-card">
            <div class="kiosk-stat-icon kiosk-stat-foto">📸</div>
            <div>
                <div class="kiosk-stat-number"><?php echo $total_foto; ?></div>
                <div class="kiosk-stat-label">Foto Tayang</div>
            </div>
        </div>
        <div class="kiosk-stat-card">
            <div class="kiosk-stat-icon kiosk-stat-video">🎬</div>
            <div>
                <div class="kiosk-stat-number"><?php echo $total_video; ?></div>
                <div class="kiosk-stat-label">Video Tayang</div>
            </div>
        </div>
    </div>

    <!-- KARTU FORM UPLOAD & PENGATURAN TRANSISI -->
    <div class="kiosk-form-card" id="kioskUploadFormCard">
        <h3 class="kiosk-form-title">
            <span>✨</span> Form Unggah Media & Template Transisi
        </h3>
        <p class="kiosk-form-subtitle">
            Pilih file, tentukan template atau gaya transisi favorit, dan atur waktu tayang setiap media saat ditampilkan berurutan di layar TV Kiosk.
        </p>

        <form id="kioskUploadForm" method="POST" action="<?php echo htmlspecialchars($base_url); ?>" enctype="multipart/form-data" target="kioskUploadFrame">
            <input type="hidden" name="upload" value="1">
            <input type="hidden" name="target_iframe" id="kiosk_target_iframe" value="1">

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 20px;">
                <!-- Kolom Kiri: Judul, Tipe & File -->
                <div>
                    <div style="margin-bottom: 16px;">
                        <label style="font-weight: 600; font-size: 0.92rem; display: block; margin-bottom: 6px;">
                            Judul Kegiatan <span style="color:red;">*</span>
                        </label>
                        <input type="text" name="media_title" id="kiosk_media_title" class="form-control" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.95rem;" required placeholder="Contoh: Kunjungan Literasi Siswa SMPN 1">
                    </div>

                    <div style="margin-bottom: 16px;">
                        <label style="font-weight: 600; font-size: 0.92rem; display: block; margin-bottom: 6px;">
                            Jenis Media <span style="color:red;">*</span>
                        </label>
                        <div style="display: flex; gap: 12px;">
                            <label style="flex: 1; border: 2px solid #0284c7; background: #f0f9ff; padding: 10px; border-radius: 8px; cursor: pointer; text-align: center; font-weight: 600; color: #0369a1;" id="lbl_type_foto">
                                <input type="radio" name="media_type" value="foto" checked onchange="updateMediaTypeUI('foto')" style="margin-right: 6px;">
                                📸 Foto Kegiatan
                            </label>
                            <label style="flex: 1; border: 2px solid #e2e8f0; background: #ffffff; padding: 10px; border-radius: 8px; cursor: pointer; text-align: center; font-weight: 600; color: #475569;" id="lbl_type_video">
                                <input type="radio" name="media_type" value="video" onchange="updateMediaTypeUI('video')" style="margin-right: 6px;">
                                🎬 Video Kegiatan
                            </label>
                        </div>
                    </div>

                    <div style="margin-bottom: 16px;">
                        <label style="font-weight: 600; font-size: 0.92rem; display: block; margin-bottom: 6px;">
                            Pilih File Media <span style="color:red;">*</span>
                        </label>
                        <div class="kiosk-dropzone" onclick="$('#kiosk_media_file').click();">
                            <div class="kiosk-dropzone-icon">📁</div>
                            <div class="kiosk-dropzone-text" id="kiosk_filename_label">Klik di sini untuk memilih file</div>
                            <div class="kiosk-dropzone-sub">
                                Foto: JPG/PNG/WEBP | Video: MP4/WEBM (Maks: <b><?php echo ini_get('upload_max_filesize'); ?></b>)
                            </div>
                        </div>
                        <input type="file" name="media_file" id="kiosk_media_file" accept=".jpg,.jpeg,.png,.webp,.gif,.mp4,.webm,.ogg" style="display: none;" required onchange="handleFileSelected(this)">
                    </div>
                </div>

                <!-- Kolom Kanan: Pilihan Template & Pengaturan Transisi -->
                <div>
                    <!-- Template Transisi Siap Pakai -->
                    <label style="font-weight: 600; font-size: 0.92rem; display: block; margin-bottom: 4px;">
                        🎨 Template Transisi Siap Pakai (1-Klik):
                    </label>
                    <div class="kiosk-template-grid">
                        <div class="kiosk-template-btn active" onclick="applyTemplate('fade', 5, this)">
                            <div class="kiosk-tpl-name">🌟 Standar Elegan</div>
                            <div class="kiosk-tpl-desc">Fade In • 5 Detik</div>
                        </div>
                        <div class="kiosk-template-btn" onclick="applyTemplate('slide-left', 6, this)">
                            <div class="kiosk-tpl-name">➡️ Geser Carousel</div>
                            <div class="kiosk-tpl-desc">Slide Kiri • 6 Detik</div>
                        </div>
                        <div class="kiosk-template-btn" onclick="applyTemplate('zoom-in', 5, this)">
                            <div class="kiosk-tpl-name">🔍 Zoom Fokus</div>
                            <div class="kiosk-tpl-desc">Zoom In • 5 Detik</div>
                        </div>
                        <div class="kiosk-template-btn" onclick="applyTemplate('flip', 7, this)">
                            <div class="kiosk-tpl-name">🔄 3D Flip Modern</div>
                            <div class="kiosk-tpl-desc">Flip 3D • 7 Detik</div>
                        </div>
                        <div class="kiosk-template-btn" onclick="applyTemplate('bounce', 4, this)">
                            <div class="kiosk-tpl-name">⚡ Ceria Memantul</div>
                            <div class="kiosk-tpl-desc">Bounce • 4 Detik</div>
                        </div>
                        <div class="kiosk-template-btn" onclick="applyTemplate('fade', 10, this)">
                            <div class="kiosk-tpl-name">☕ Santai Sinematik</div>
                            <div class="kiosk-tpl-desc">Fade • 10 Detik</div>
                        </div>
                    </div>

                    <!-- Pilihan Gaya Manual & Durasi -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                        <div>
                            <label style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 4px;">
                                Gaya Transisi:
                            </label>
                            <select name="transition_style" id="kiosk_trans_style" class="form-control" style="width: 100%; padding: 8px; border-radius: 8px; border: 1px solid #cbd5e1;" onchange="previewSelectedTransition()">
                                <option value="fade">✨ Memudar Halus (Fade)</option>
                                <option value="slide-left">➡️ Geser Kiri (Slide Left)</option>
                                <option value="slide-right">⬅️ Geser Kanan (Slide Right)</option>
                                <option value="zoom-in">🔍 Zoom In (Membesar)</option>
                                <option value="zoom-out">🔎 Zoom Out (Mengecil)</option>
                                <option value="flip">🔄 Membalik 3D (Flip)</option>
                                <option value="bounce">⚡ Memantul (Bounce)</option>
                            </select>
                        </div>
                        <div>
                            <label style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 4px;">
                                Waktu Tayang (Detik):
                            </label>
                            <input type="number" name="transition_duration" id="kiosk_trans_duration" min="2" max="60" value="5" class="form-control" style="width: 100%; padding: 8px; border-radius: 8px; border: 1px solid #cbd5e1;" onchange="updateDurationUI(this.value)">
                        </div>
                    </div>

                    <!-- Pills Cepat Durasi -->
                    <div style="margin-bottom: 14px;">
                        <span style="font-size: 0.8rem; color: #64748b; font-weight: 600;">Pilihan Cepat Waktu:</span>
                        <div class="kiosk-duration-pills">
                            <span class="kiosk-dur-pill" onclick="setDurationQuick(3)">3 Detik</span>
                            <span class="kiosk-dur-pill active" onclick="setDurationQuick(5)">5 Detik (Ideal)</span>
                            <span class="kiosk-dur-pill" onclick="setDurationQuick(7)">7 Detik</span>
                            <span class="kiosk-dur-pill" onclick="setDurationQuick(10)">10 Detik</span>
                            <span class="kiosk-dur-pill" onclick="setDurationQuick(15)">15 Detik</span>
                        </div>
                    </div>

                    <!-- Live Animasi Preview Box -->
                    <div class="kiosk-preview-box">
                        <div class="kiosk-preview-badge">Demo Animasi Transisi</div>
                        <div id="kioskPreviewDemo" class="kiosk-preview-element trans-fade">
                            Foto Kegiatan Perpustakaan
                        </div>
                        <div style="font-size: 11px; color: #94a3b8; margin-top: 8px;" id="kioskPreviewCaption">
                            Gaya: Memudar Halus (Fade) • Tayang: 5 Detik
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progress Bar Upload -->
            <div id="kioskProgressWrapper" style="display: none; margin-bottom: 18px;">
                <div style="font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #0284c7;" id="kioskProgressLabel">
                    Mengunggah file ke server...
                </div>
                <div style="width: 100%; background-color: #e2e8f0; border-radius: 10px; overflow: hidden; height: 22px;">
                    <div id="kioskProgressBar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #0284c7, #38bdf8); color: white; text-align: center; font-size: 12px; line-height: 22px; font-weight: bold; transition: width 0.2s;">0%</div>
                </div>
            </div>

            <!-- Tombol Submit -->
            <div>
                <button type="submit" id="kioskUploadBtn" class="s-btn btn btn-primary" style="padding: 10px 28px; font-size: 0.95rem; font-weight: 600; border-radius: 8px;">
                    🚀 Upload Media ke Kiosk
                </button>
            </div>
        </form>

        <!-- Hidden iframe fallback -->
        <iframe name="kioskUploadFrame" id="kioskUploadFrame" style="display: none; width: 0; height: 0;"></iframe>
    </div>

    <!-- BAGIAN DAFTAR MEDIA AKTIF (CARD GRID - TANPA TABEL) -->
    <div class="kiosk-gallery-header">
        <div>
            <h3 class="kiosk-gallery-title">🖼️ Galeri Media Aktif di Kiosk</h3>
            <span style="font-size: 0.88rem; color: #64748b;">Media di bawah ini langsung berputar secara berurutan di layar TV Kiosk Fullscreen.</span>
        </div>

        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <!-- Filter Tipe -->
            <div class="kiosk-filter-bar">
                <button type="button" class="kiosk-filter-btn active" onclick="filterMediaGrid('all', this)">Semua (<?php echo $total_media; ?>)</button>
                <button type="button" class="kiosk-filter-btn" onclick="filterMediaGrid('foto', this)">📸 Foto (<?php echo $total_foto; ?>)</button>
                <button type="button" class="kiosk-filter-btn" onclick="filterMediaGrid('video', this)">🎬 Video (<?php echo $total_video; ?>)</button>
            </div>

            <!-- Pencarian Cepat -->
            <input type="text" id="kioskSearchInput" placeholder="Cari judul..." style="padding: 6px 14px; border: 1px solid #cbd5e1; border-radius: 20px; font-size: 0.85rem; width: 170px;" onkeyup="searchMediaCards(this.value)">
        </div>
    </div>

    <!-- Grid Kartu Media -->
    <div class="kiosk-media-grid" id="kioskCardsContainer">
        <?php
        // Ambil media berurutan berdasarkan order_num ASC, media_id ASC
        $q_media = $dbs->query("SELECT * FROM plugin_kiosk_media ORDER BY order_num ASC, media_id ASC");
        if ($q_media && $q_media->num_rows > 0) {
            while ($row = $q_media->fetch_assoc()) {
                $file_url = SWB . 'files/kiosk_media/' . htmlspecialchars($row['file_path']);
                $media_type = $row['media_type'];
                $style = !empty($row['transition_style']) ? $row['transition_style'] : 'fade';
                $dur   = !empty($row['transition_duration']) ? (int)$row['transition_duration'] : 5;
                $order = !empty($row['order_num']) ? (int)$row['order_num'] : (int)$row['media_id'];
                $del_url = $base_url . '&del=' . (int)$row['media_id'];
                $badge_class = ($media_type === 'foto') ? 'kiosk-badge-foto' : 'kiosk-badge-video';
                $badge_text  = ($media_type === 'foto') ? '📸 FOTO' : '🎬 VIDEO';
                $formatted_date = date('d M Y, H:i', strtotime($row['created_date']));
        ?>
            <div class="kiosk-card" data-type="<?php echo $media_type; ?>" data-title="<?php echo strtolower(htmlspecialchars($row['media_title'])); ?>" id="card-media-<?php echo $row['media_id']; ?>">
                
                <!-- Preview Visual -->
                <div class="kiosk-card-media">
                    <span class="kiosk-card-badge-type <?php echo $badge_class; ?>">
                        <?php echo $badge_text; ?>
                    </span>
                    <span class="kiosk-card-badge-duration">
                        ⏱️ <?php echo $dur; ?> Detik
                    </span>
                    <span class="kiosk-card-badge-order">
                        🔢 Urutan: #<?php echo $order; ?>
                    </span>

                    <?php if ($media_type === 'foto'): ?>
                        <a href="<?php echo $file_url; ?>" target="_blank" title="Klik untuk perbesar">
                            <img src="<?php echo $file_url; ?>" alt="<?php echo htmlspecialchars($row['media_title']); ?>" loading="lazy">
                        </a>
                    <?php else: ?>
                        <video src="<?php echo $file_url; ?>" controls preload="metadata"></video>
                    <?php endif; ?>
                </div>

                <!-- Body Kartu -->
                <div class="kiosk-card-body">
                    <h4 class="kiosk-card-title"><?php echo htmlspecialchars($row['media_title']); ?></h4>
                    
                    <div class="kiosk-card-meta">
                        <span>📅 Diunggah: <?php echo $formatted_date; ?></span>
                    </div>

                    <div>
                        <span class="kiosk-trans-pill">
                            <?php echo get_trans_label($style); ?>
                        </span>
                    </div>
                </div>

                <!-- Aksi Kartu -->
                <div class="kiosk-card-actions">
                    <button type="button" class="kiosk-btn-action kiosk-btn-primary-sm" onclick="openEditModal(<?php echo $row['media_id']; ?>, '<?php echo addslashes(htmlspecialchars($row['media_title'])); ?>', '<?php echo $style; ?>', <?php echo $dur; ?>, <?php echo $order; ?>)">
                        ⚙️ Atur Transisi
                    </button>
                    <a href="<?php echo $file_url; ?>" target="_blank" class="kiosk-btn-action" title="Lihat ukuran asli">
                        🔍 Buka
                    </a>
                    <a href="<?php echo $del_url; ?>" class="kiosk-btn-action kiosk-btn-danger kiosk-del-link notAJAX" title="Hapus media ini">
                        🗑️ Hapus
                    </a>
                </div>

            </div>
        <?php
            }
        } else {
        ?>
            <!-- Tampilan Kosong (Empty State) -->
            <div style="grid-column: 1 / -1;">
                <div class="kiosk-empty-state">
                    <div class="kiosk-empty-icon">🖼️</div>
                    <h4 style="font-size: 1.25rem; font-weight: 700; color: #334155; margin-bottom: 6px;">Belum Ada Media di Kiosk</h4>
                    <p style="margin: 0 0 15px 0;">Silakan unggah foto atau video kegiatan perpustakaan pertama Anda menggunakan form di atas.</p>
                    <button type="button" onclick="$('#kiosk_media_title').focus();" class="kiosk-btn-hero" style="background: #0284c7; color: #fff;">
                        ➕ Mulai Upload Sekarang
                    </button>
                </div>
            </div>
        <?php } ?>
    </div>

</div>

<!-- MODAL POPUP CEPAT UNTUK EDIT GAYA, WAKTU & URUTAN TRANSISI -->
<div class="kiosk-modal-backdrop" id="kioskEditModal">
    <div class="kiosk-modal-content">
        <h4 style="margin: 0 0 4px 0; font-size: 1.2rem; font-weight: 700; color: #0f172a;">⚙️ Atur Gaya, Waktu & Urutan Tayang</h4>
        <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 16px;" id="modalMediaTitleLabel">Ubah pengaturan tayang media</p>

        <form id="kioskEditForm" method="POST" action="<?php echo htmlspecialchars($base_url); ?>">
            <input type="hidden" name="update_transition" value="1">
            <input type="hidden" name="edit_media_id" id="modalMediaId">

            <div style="margin-bottom: 14px;">
                <label style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 5px;">Nomor Urutan Putar di Layar TV:</label>
                <input type="number" name="edit_order" id="modalMediaOrder" min="1" max="999" class="form-control" style="width: 100%; padding: 8px; border-radius: 8px; border: 1px solid #cbd5e1;">
                <span style="font-size: 0.78rem; color: #64748b; margin-top: 3px; display: block;">Media diputar berurutan dari nomor terkecil ke terbesar (#1, #2, dst).</span>
            </div>

            <div style="margin-bottom: 14px;">
                <label style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 5px;">Gaya Transisi Animasi:</label>
                <select name="edit_style" id="modalTransStyle" class="form-control" style="width: 100%; padding: 8px; border-radius: 8px; border: 1px solid #cbd5e1;">
                    <option value="fade">✨ Memudar Halus (Fade)</option>
                    <option value="slide-left">➡️ Geser Kiri (Slide Left)</option>
                    <option value="slide-right">⬅️ Geser Kanan (Slide Right)</option>
                    <option value="zoom-in">🔍 Zoom In (Membesar)</option>
                    <option value="zoom-out">🔎 Zoom Out (Mengecil)</option>
                    <option value="flip">🔄 Membalik 3D (Flip)</option>
                    <option value="bounce">⚡ Memantul (Bounce)</option>
                </select>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="font-weight: 600; font-size: 0.88rem; display: block; margin-bottom: 5px;">Waktu Tayang per Slide (Detik):</label>
                <input type="number" name="edit_duration" id="modalTransDuration" min="2" max="60" class="form-control" style="width: 100%; padding: 8px; border-radius: 8px; border: 1px solid #cbd5e1;">
                <span style="font-size: 0.78rem; color: #64748b; margin-top: 4px; display: block;">Untuk foto, tentukan durasi tampil di layar (misal: 5 - 10 detik).</span>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="kiosk-btn-action" onclick="closeEditModal()">Batal</button>
                <button type="submit" class="kiosk-btn-hero" style="background: #0284c7; color: #fff; padding: 8px 18px;">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- SCRIPT LOGIKA AJAX & INTERAKSI UI -->
<script type="text/javascript">
(function($) {

    // 1. Terapkan Preset Template 1-Klik
    window.applyTemplate = function(style, dur, el) {
        $('.kiosk-template-btn').removeClass('active');
        $(el).addClass('active');

        $('#kiosk_trans_style').val(style);
        $('#kiosk_trans_duration').val(dur);

        updateDurationPills(dur);
        previewSelectedTransition();
    };

    // 2. Set Durasi Cepat dari Pills
    window.setDurationQuick = function(sec) {
        $('#kiosk_trans_duration').val(sec);
        updateDurationPills(sec);
        previewSelectedTransition();
    };

    window.updateDurationUI = function(sec) {
        updateDurationPills(parseInt(sec));
        previewSelectedTransition();
    };

    function updateDurationPills(sec) {
        $('.kiosk-dur-pill').removeClass('active');
        $('.kiosk-dur-pill').each(function() {
            if ($(this).text().indexOf(sec + ' Detik') !== -1) {
                $(this).addClass('active');
            }
        });
    }

    // 3. Update UI Tipe Media
    window.updateMediaTypeUI = function(type) {
        if (type === 'foto') {
            $('#lbl_type_foto').css({'border-color': '#0284c7', 'background': '#f0f9ff', 'color': '#0369a1'});
            $('#lbl_type_video').css({'border-color': '#e2e8f0', 'background': '#ffffff', 'color': '#475569'});
            $('#kiosk_media_file').attr('accept', '.jpg,.jpeg,.png,.webp,.gif');
        } else {
            $('#lbl_type_video').css({'border-color': '#9333ea', 'background': '#faf5ff', 'color': '#7e22ce'});
            $('#lbl_type_foto').css({'border-color': '#e2e8f0', 'background': '#ffffff', 'color': '#475569'});
            $('#kiosk_media_file').attr('accept', '.mp4,.webm,.ogg');
        }
    };

    // 4. File Dropzone Selection Handler
    window.handleFileSelected = function(input) {
        if (input.files && input.files[0]) {
            var file = input.files[0];
            var sizeMb = (file.size / (1024 * 1024)).toFixed(2);
            $('#kiosk_filename_label').html('<b>' + file.name + '</b> (' + sizeMb + ' MB)');
        }
    };

    // 5. Live Interactive Preview Transisi
    window.previewSelectedTransition = function() {
        var style = $('#kiosk_trans_style').val() || 'fade';
        var dur   = $('#kiosk_trans_duration').val() || 5;
        var styleName = $('#kiosk_trans_style option:selected').text();

        var demo = $('#kioskPreviewDemo');
        demo.removeClass('trans-fade trans-slide-left trans-slide-right trans-zoom-in trans-zoom-out trans-flip trans-bounce');
        
        void demo[0].offsetWidth;

        demo.addClass('trans-' + style);
        $('#kioskPreviewCaption').text('Gaya: ' + styleName + ' • Tayang: ' + dur + ' Detik');
    };

    // 6. Filter Grid Media (Semua, Foto, Video)
    window.filterMediaGrid = function(type, btn) {
        $('.kiosk-filter-btn').removeClass('active');
        $(btn).addClass('active');

        if (type === 'all') {
            $('.kiosk-card').fadeIn(150);
        } else {
            $('.kiosk-card').each(function() {
                if ($(this).data('type') === type) {
                    $(this).fadeIn(150);
                } else {
                    $(this).fadeOut(150);
                }
            });
        }
    };

    // 7. Live Search Media Cards
    window.searchMediaCards = function(keyword) {
        var term = keyword.toLowerCase().trim();
        $('.kiosk-card').each(function() {
            var title = $(this).data('title') || '';
            if (title.indexOf(term) !== -1) {
                $(this).fadeIn(150);
            } else {
                $(this).fadeOut(150);
            }
        });
    };

    // 8. Modal Edit Transisi & Urutan
    window.openEditModal = function(id, title, style, dur, order) {
        $('#modalMediaId').val(id);
        $('#modalMediaTitleLabel').text('Ubah pengaturan untuk: "' + title + '"');
        $('#modalTransStyle').val(style);
        $('#modalTransDuration').val(dur);
        $('#modalMediaOrder').val(order || 1);
        $('#kioskEditModal').css('display', 'flex');
    };

    window.closeEditModal = function() {
        $('#kioskEditModal').hide();
    };

    $('#kioskEditModal').on('click', function(e) {
        if (e.target === this) {
            closeEditModal();
        }
    });

    // 9. AJAX Form Edit Transisi & Urutan
    $(document).off('submit', '#kioskEditForm').on('submit', '#kioskEditForm', function(e) {
        e.preventDefault();
        var form = this;
        $.ajax({
            url: $(form).attr('action'),
            type: 'POST',
            data: $(form).serialize(),
            success: function(response) {
                closeEditModal();
                if ($('#mainContent').length > 0) {
                    $('#mainContent').html(response);
                } else {
                    $('body').html(response);
                }
                if (typeof toastr !== 'undefined') {
                    toastr.success('Pengaturan transisi & urutan berhasil disimpan!');
                }
            },
            error: function(xhr, status, error) {
                alert('Gagal menyimpan: ' + error);
            }
        });
    });

    // 10. AJAX Upload Media (No Fullscreen, Real-time Progress Bar)
    $(document).off('submit', '#kioskUploadForm').on('submit', '#kioskUploadForm', function(e) {
        e.preventDefault();
        
        var form = this;
        var formData = new FormData(form);
        formData.set('target_iframe', '0');
        formData.set('upload', '1');

        var submitBtn = $(form).find('#kioskUploadBtn');
        var originalBtnHtml = submitBtn.html();
        submitBtn.prop('disabled', true).text('⏳ Sedang Mengunggah...');
        
        $('#kioskProgressWrapper').show();
        $('#kioskProgressBar').css('width', '0%').text('0%');
        $('#kioskProgressLabel').text('Mengunggah file ke server...');
        $('#kioskAlertArea').html('');

        $.ajax({
            url: $(form).attr('action'),
            type: 'POST',
            data: formData,
            contentType: false,
            processData: false,
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                if (xhr.upload) {
                    xhr.upload.addEventListener('progress', function(evt) {
                        if (evt.lengthComputable) {
                            var percent = Math.round((evt.loaded / evt.total) * 100);
                            $('#kioskProgressBar').css('width', percent + '%').text(percent + '%');
                            if (percent >= 100) {
                                $('#kioskProgressLabel').text('Menyimpan ke database & memproses media...');
                            }
                        }
                    }, false);
                }
                return xhr;
            },
            success: function(response) {
                if ($('#mainContent').length > 0) {
                    $('#mainContent').html(response);
                } else {
                    $('body').html(response);
                }
                if (typeof toastr !== 'undefined') {
                    toastr.success('Media berhasil diunggah!');
                }
            },
            error: function(xhr, status, error) {
                submitBtn.prop('disabled', false).html(originalBtnHtml);
                $('#kioskProgressWrapper').hide();
                var errMsg = xhr.responseText ? xhr.responseText : error;
                $('#kioskAlertArea').html('<div class="kiosk-alert kiosk-alert-error">❌ Gagal upload media: ' + errMsg + '</div>');
                if (typeof toastr !== 'undefined') {
                    toastr.error('Upload gagal: ' + error);
                }
            }
        });
    });

    // 11. AJAX Hapus Media
    $(document).off('click', '.kiosk-del-link').on('click', '.kiosk-del-link', function(e) {
        e.preventDefault();
        if (!confirm('Yakin ingin menghapus media ini dari Kiosk?')) {
            return false;
        }
        var delUrl = $(this).attr('href');
        var loadContainer = $('#mainContent');
        if (loadContainer.length > 0 && typeof $.fn.simbioAJAX === 'function') {
            loadContainer.simbioAJAX(delUrl);
        } else {
            $.get(delUrl, function(data) {
                if ($('#mainContent').length > 0) {
                    $('#mainContent').html(data);
                } else {
                    $('body').html(data);
                }
                if (typeof toastr !== 'undefined') {
                    toastr.success('Media berhasil dihapus!');
                }
            });
        }
    });

})(jQuery);
</script>
