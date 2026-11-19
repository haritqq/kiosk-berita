<?php
defined('INDEX_AUTH') OR die('Direct access is not allowed!');
global $dbs, $sysconf;

// Pastikan tabel database sudah ada
$dbs->query("CREATE TABLE IF NOT EXISTS `plugin_kiosk_media` (
  `media_id` INT(11) NOT NULL AUTO_INCREMENT,
  `media_title` VARCHAR(255) NOT NULL,
  `media_type` ENUM('foto','video') NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `created_date` DATETIME NOT NULL,
  PRIMARY KEY (`media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

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

// Proses Hapus Media
if (isset($_GET['del']) && is_numeric($_GET['del'])) {
    $id_del = (int) $_GET['del'];
    $q_file = $dbs->query("SELECT file_path FROM plugin_kiosk_media WHERE media_id = $id_del");
    if ($q_file && $q_file->num_rows > 0) {
        $d_file = $q_file->fetch_assoc();
        if (!empty($d_file['file_path']) && file_exists($upload_dir . $d_file['file_path'])) {
            @unlink($upload_dir . $d_file['file_path']);
        }
        $dbs->query("DELETE FROM plugin_kiosk_media WHERE media_id = $id_del");
        $pesan = "<div class='successBox'>Media berhasil dihapus!</div>";
    }
}

// Proses Upload Media Baru
if (isset($_POST['upload'])) {
    // Cek jika ukuran file melebihi post_max_size server (PHP otomatis mengosongkan $_POST dan $_FILES)
    if (empty($_FILES) && empty($_POST['media_title']) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
        $pesan = "<div class='errorBox'>Ukuran file terlalu besar! Melebihi batas maksimal server (post_max_size: " . ini_get('post_max_size') . ").</div>";
    } else {
        $title = isset($_POST['media_title']) ? $dbs->escape_string(trim($_POST['media_title'])) : '';
        $type = isset($_POST['media_type']) ? $dbs->escape_string(trim($_POST['media_type'])) : 'foto';

        if (!empty($_FILES['media_file']['name']) && !empty($title)) {
            if ($_FILES['media_file']['error'] !== UPLOAD_ERR_OK) {
                $err_msg = 'Terjadi kesalahan saat mengunggah file (Kode error: ' . $_FILES['media_file']['error'] . ').';
                if ($_FILES['media_file']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['media_file']['error'] === UPLOAD_ERR_FORM_SIZE) {
                    $err_msg = 'Ukuran file melebihi batas upload maksimal server (' . ini_get('upload_max_filesize') . ')!';
                } elseif ($_FILES['media_file']['error'] === UPLOAD_ERR_PARTIAL) {
                    $err_msg = 'File hanya terunggah sebagian. Silakan coba lagi.';
                }
                $pesan = "<div class='errorBox'>{$err_msg}</div>";
            } else {
                $ext = strtolower(pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION));
                $clean_filename = preg_replace("/[^a-zA-Z0-9\._-]/", "_", basename($_FILES['media_file']['name']));
                $file_name = time() . '_' . $clean_filename;
                $target_file = $upload_dir . $file_name;

                $valid_foto = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                $valid_video = ['mp4', 'webm', 'ogg'];

                $allowed = ($type === 'foto') ? $valid_foto : $valid_video;

                if (in_array($ext, $allowed)) {
                    if (move_uploaded_file($_FILES['media_file']['tmp_name'], $target_file)) {
                        $now = date('Y-m-d H:i:s');
                        $sql = "INSERT INTO plugin_kiosk_media (media_title, media_type, file_path, created_date) 
                                VALUES ('$title', '$type', '$file_name', '$now')";

                        if ($dbs->query($sql)) {
                            $pesan = "<div class='successBox'>Media berhasil diunggah dan disimpan ke database!</div>";
                        } else {
                            @unlink($target_file);
                            $pesan = "<div class='errorBox'>Gagal menyimpan ke database: " . $dbs->error . "</div>";
                        }
                    } else {
                        $pesan = "<div class='errorBox'>Gagal memindahkan file upload ke folder server. Pastikan folder 'files/kiosk_media/' dapat ditulis (writable).</div>";
                    }
                } else {
                    $pesan = "<div class='errorBox'>Format file (.{$ext}) tidak didukung untuk kategori {$type}! Format yang diperbolehkan: " . implode(', ', $allowed) . "</div>";
                }
            }
        } else {
            $pesan = "<div class='errorBox'>Judul kegiatan dan file media wajib diisi!</div>";
        }
    }

    // Jika form disubmit melalui iframe fallback (kioskUploadFrame)
    if (isset($_POST['target_iframe']) && $_POST['target_iframe'] === '1') {
        echo '<script type="text/javascript">';
        if (strpos($pesan, 'successBox') !== false) {
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
?>

<!-- Cegah halaman terbuka langsung secara fullscreen di luar layout SLiMS Admin -->
<script type="text/javascript">
    if (window.self === window.top && $('#mainMenu').length === 0 && $('.s-sidebar').length === 0 && $('#sidepan').length === 0) {
        window.location.href = 'index.php?mod=system';
    }
</script>

<div class="menuBox">
    <div class="menuBoxInner systemIcon">
        <div class="per_title">
            <h2>Kelola Galeri & Video Kiosk</h2>
        </div>
    </div>
</div>

<div id="kioskAlertArea">
    <?php echo $pesan; ?>
</div>

<div class="infoBox">
    Akses tampilan Kiosk publik untuk layar sentuh di:
    <a href="<?php echo SWB; ?>index.php?p=kiosk-berita"
        target="_blank"><b><?php echo SWB; ?>index.php?p=kiosk-berita</b></a>
</div>

<!-- Form Upload Media Baru -->
<div style="background: #f8fafc; padding: 20px; border: 1px solid #cbd5e1; margin-bottom: 25px; border-radius: 8px;">
    <h3 style="margin-top: 0; color: #1e293b; font-size: 1.25rem; font-weight: 600; margin-bottom: 15px;">
        Upload Foto / Video Baru
    </h3>

    <form id="kioskUploadForm" method="POST" action="<?php echo htmlspecialchars($base_url); ?>"
        enctype="multipart/form-data" target="kioskUploadFrame">
        <input type="hidden" name="upload" value="1">
        <input type="hidden" name="target_iframe" id="kiosk_target_iframe" value="1">

        <div style="margin-bottom: 15px;">
            <label style="font-weight: 600; display: block; margin-bottom: 5px;">Judul Kegiatan <span
                    style="color:red;">*</span>:</label>
            <input type="text" name="media_title" id="kiosk_media_title" class="form-control"
                style="width: 100%; max-width: 500px; padding: 8px; border: 1px solid #ccc; border-radius: 4px;"
                required placeholder="Contoh: Kunjungan Belajar SMA 1">
        </div>

        <div style="margin-bottom: 15px;">
            <label style="font-weight: 600; display: block; margin-bottom: 5px;">Jenis Media <span
                    style="color:red;">*</span>:</label>
            <select name="media_type" id="kiosk_media_type" class="form-control"
                style="width: auto; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                <option value="foto">Foto Kegiatan (JPG / PNG / WEBP)</option>
                <option value="video">Video Kegiatan (MP4 / WEBM)</option>
            </select>
        </div>

        <div style="margin-bottom: 15px;">
            <label style="font-weight: 600; display: block; margin-bottom: 5px;">Pilih File <span
                    style="color:red;">*</span>:</label>
            <input type="file" name="media_file" id="kiosk_media_file"
                accept=".jpg,.jpeg,.png,.webp,.gif,.mp4,.webm,.ogg" required>
            <div style="font-size: 12px; color: #64748b; margin-top: 4px;">
                Maksimal ukuran file: <b><?php echo ini_get('upload_max_filesize'); ?></b> (Batas POST server:
                <b><?php echo ini_get('post_max_size'); ?></b>)
            </div>
        </div>

        <!-- Progress Bar Upload -->
        <div id="kioskProgressWrapper" style="display: none; margin-bottom: 15px; max-width: 500px;">
            <div style="font-size: 13px; font-weight: 600; margin-bottom: 4px; color: #0284c7;" id="kioskProgressLabel">
                Mengunggah file...</div>
            <div style="width: 100%; background-color: #e2e8f0; border-radius: 6px; overflow: hidden; height: 20px;">
                <div id="kioskProgressBar"
                    style="width: 0%; height: 100%; background-color: #0284c7; color: white; text-align: center; font-size: 12px; line-height: 20px; font-weight: bold; transition: width 0.2s;">
                    0%</div>
            </div>
        </div>

        <div>
            <button type="submit" id="kioskUploadBtn" class="s-btn btn btn-primary" style="padding: 8px 20px;">
                Upload Media
            </button>
        </div>
    </form>

    <!-- Hidden iframe untuk fallback submission target -->
    <iframe name="kioskUploadFrame" id="kioskUploadFrame" style="display: none; width: 0; height: 0;"></iframe>
</div>

<!-- Daftar Media yang Tersimpan -->
<h3 style="color: #1e293b; font-size: 1.25rem; font-weight: 600; margin-bottom: 15px;">Daftar Media Aktif di Kiosk</h3>
<table class="s-table table dataList" style="width: 100%; border-collapse: collapse; background: #fff;">
    <thead>
        <tr style="background: #f1f5f9;">
            <th style="padding: 10px; border: 1px solid #cbd5e1; width: 50px; text-align: center;">No</th>
            <th style="padding: 10px; border: 1px solid #cbd5e1;">Judul Kegiatan</th>
            <th style="padding: 10px; border: 1px solid #cbd5e1; width: 100px; text-align: center;">Tipe</th>
            <th style="padding: 10px; border: 1px solid #cbd5e1; width: 160px; text-align: center;">Preview</th>
            <th style="padding: 10px; border: 1px solid #cbd5e1; width: 100px; text-align: center;">Aksi</th>
        </tr>
    </thead>
    <tbody>
        <?php
        $no = 1;
        $q = $dbs->query("SELECT * FROM plugin_kiosk_media ORDER BY media_id DESC");
        if ($q && $q->num_rows > 0) {
            while ($row = $q->fetch_assoc()) {
                $file_url = SWB . 'files/kiosk_media/' . htmlspecialchars($row['file_path']);
                $badge_bg = ($row['media_type'] === 'foto') ? '#0284c7' : '#7c3aed';
                echo "<tr>";
                echo "<td style='padding: 10px; border: 1px solid #cbd5e1; text-align:center;'>{$no}</td>";
                echo "<td style='padding: 10px; border: 1px solid #cbd5e1; font-weight: 500;'>" . htmlspecialchars($row['media_title']) . "</td>";
                echo "<td style='padding: 10px; border: 1px solid #cbd5e1; text-align:center;'>
                        <span style='background: {$badge_bg}; color: #fff; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: uppercase;'>
                            " . htmlspecialchars($row['media_type']) . "
                        </span>
                      </td>";
                echo "<td style='padding: 10px; border: 1px solid #cbd5e1; text-align:center;'>";
                if ($row['media_type'] === 'foto') {
                    echo "<a href='{$file_url}' target='_blank' title='Lihat foto ukuran penuh'>
                            <img src='{$file_url}' style='height: 55px; max-width: 120px; border-radius: 4px; object-fit: cover; border: 1px solid #ddd;'>
                          </a>";
                } else {
                    echo "<video src='{$file_url}' style='height: 55px; max-width: 120px; border-radius: 4px; background: #000;' controls preload='metadata'></video>";
                }
                echo "</td>";
                $del_url = $base_url . '&del=' . (int) $row['media_id'];
                echo "<td style='padding: 10px; border: 1px solid #cbd5e1; text-align:center;'>
                        <a href='{$del_url}' class='kiosk-del-link btn btn-sm btn-danger notAJAX' style='background: #dc2626; color: #fff; padding: 4px 10px; border-radius: 4px; text-decoration: none; font-size: 12px; font-weight: bold;'>
                            Hapus
                        </a>
                      </td>";
                echo "</tr>";
                $no++;
            }
        } else {
            echo "<tr><td colspan='5' style='text-align: center; padding: 20px; color: #64748b;'>Belum ada media yang diunggah.</td></tr>";
        }
        ?>
    </tbody>
</table>

<script type="text/javascript">
    (function ($) {
        // Event listener AJAX untuk submit form upload
        $(document).off('submit', '#kioskUploadForm').on('submit', '#kioskUploadForm', function (e) {
            e.preventDefault();

            var form = this;
            var formData = new FormData(form);
            // Tandai bahwa ini dikirim via AJAX (bukan iframe fallback)
            formData.set('target_iframe', '0');
            formData.set('upload', '1');

            var submitBtn = $(form).find('#kioskUploadBtn');
            var originalBtnHtml = submitBtn.html();
            submitBtn.prop('disabled', true).text('Mengunggah...');

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
                xhr: function () {
                    var xhr = new window.XMLHttpRequest();
                    if (xhr.upload) {
                        xhr.upload.addEventListener('progress', function (evt) {
                            if (evt.lengthComputable) {
                                var percent = Math.round((evt.loaded / evt.total) * 100);
                                $('#kioskProgressBar').css('width', percent + '%').text(percent + '%');
                                if (percent >= 100) {
                                    $('#kioskProgressLabel').text('Menyimpan ke database...');
                                }
                            }
                        }, false);
                    }
                    return xhr;
                },
                success: function (response) {
                    // Render hasil respon ke dalam kontainer utama SLiMS (#mainContent)
                    if ($('#mainContent').length > 0) {
                        $('#mainContent').html(response);
                    } else {
                        $('body').html(response);
                    }
                    if (typeof toastr !== 'undefined') {
                        toastr.success('Media berhasil diunggah!');
                    }
                },
                error: function (xhr, status, error) {
                    submitBtn.prop('disabled', false).html(originalBtnHtml);
                    $('#kioskProgressWrapper').hide();
                    var errMsg = xhr.responseText ? xhr.responseText : error;
                    $('#kioskAlertArea').html('<div class="errorBox">Gagal upload media: ' + errMsg + '</div>');
                    if (typeof toastr !== 'undefined') {
                        toastr.error('Upload gagal: ' + error);
                    }
                }
            });
        });

        // Event listener AJAX untuk tombol Hapus media
        $(document).off('click', '.kiosk-del-link').on('click', '.kiosk-del-link', function (e) {
            e.preventDefault();
            if (!confirm('Yakin ingin menghapus media ini?')) {
                return false;
            }
            var delUrl = $(this).attr('href');
            var loadContainer = $('#mainContent');
            if (loadContainer.length > 0 && typeof $.fn.simbioAJAX === 'function') {
                loadContainer.simbioAJAX(delUrl);
            } else {
                $.get(delUrl, function (data) {
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