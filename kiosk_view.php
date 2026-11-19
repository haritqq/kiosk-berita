<?php
defined('INDEX_AUTH') OR die('Direct access is not allowed!');
global $dbs;
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kiosk Galeri & Video - <?php echo $sysconf['library_name']; ?></title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #0f172a;
            color: #fff;
            margin: 0;
            padding: 20px;
            overflow-x: hidden;
        }

        header {
            text-align: center;
            margin-bottom: 20px;
        }

        header h1 {
            margin: 0;
            font-size: 2.5rem;
            color: #38bdf8;
        }

        header p {
            color: #94a3b8;
            font-size: 1.2rem;
        }

        .nav-tabs {
            text-align: center;
            margin-bottom: 20px;
        }

        .tab-btn {
            background: #334155;
            color: #fff;
            border: none;
            padding: 12px 30px;
            font-size: 1.1rem;
            border-radius: 30px;
            cursor: pointer;
            margin: 0 10px;
            font-weight: bold;
            transition: 0.3s;
        }

        .tab-btn.active {
            background: #0284c7;
        }

        .section-content {
            display: none;
        }

        .section-content.active {
            display: block;
        }

        /* Slideshow / Carousel Styling untuk Kiosk */
        .slideshow-container {
            max-width: 900px;
            position: relative;
            margin: auto;
            background: #1e293b;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            text-align: center;
        }

        .slide {
            display: none;
        }

        .slide.active-slide {
            display: block;
            animation: fadeIn 1s ease-in-out;
        }

        .slide img {
            width: 100%;
            height: 480px;
            object-fit: contain;
            border-radius: 8px;
            background: #000;
        }

        .slide-caption {
            font-size: 1.4rem;
            margin-top: 15px;
            font-weight: bold;
            color: #f1f5f9;
        }

        @keyframes fadeIn {
            from {
                opacity: 0.4;
            }

            to {
                opacity: 1;
            }
        }

        /* Container Video */
        .video-container {
            max-width: 800px;
            margin: 0 auto;
            background: #1e293b;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }

        .video-container video {
            width: 100%;
            max-height: 480px;
            border-radius: 8px;
        }

        .video-container h3 {
            margin-top: 15px;
            font-size: 1.3rem;
        }

        .back-btn {
            position: fixed;
            bottom: 20px;
            left: 20px;
            background: #475569;
            color: #fff;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            z-index: 99;
        }
    </style>
</head>

<body>

    <header>
        <h1>Anjungan Informasi Kegiatan</h1>
        <p><?php echo $sysconf['library_name']; ?></p>
    </header>

    <div class="nav-tabs">
        <button class="tab-btn active" onclick="switchTab('foto')">📸 Slideshow Galeri Foto</button>
        <button class="tab-btn" onclick="switchTab('video')">🎬 Pemutar Video Kegiatan</button>
    </div>

    <!-- Bagian Slideshow Foto -->
    <div id="tab-foto" class="section-content active">
        <div class="slideshow-container">
            <?php
            $q_foto = $dbs->query("SELECT * FROM plugin_kiosk_media WHERE media_type = 'foto' ORDER BY media_id DESC");
            if ($q_foto && $q_foto->num_rows > 0) {
                $idx = 0;
                while ($f = $q_foto->fetch_assoc()) {
                    $img_url = SWB . 'files/kiosk_media/' . $f['file_path'];
                    $active_class = ($idx === 0) ? 'active-slide' : '';
                    echo '<div class="slide ' . $active_class . '">';
                    echo '<img src="' . $img_url . '" alt="' . $f['media_title'] . '">';
                    echo '<div class="slide-caption">' . $f['media_title'] . '</div>';
                    echo '</div>';
                    $idx++;
                }
            } else {
                echo '<p style="color: #94a3b8; padding: 40px; font-size: 1.2rem;">Belum ada foto kegiatan yang diunggah oleh pustakawan.</p>';
            }
            ?>
        </div>
    </div>

    <!-- Bagian Video -->
    <div id="tab-video" class="section-content">
        <?php
        $q_video = $dbs->query("SELECT * FROM plugin_kiosk_media WHERE media_type = 'video' ORDER BY media_id DESC");
        if ($q_video && $q_video->num_rows > 0) {
            while ($v = $q_video->fetch_assoc()) {
                $vid_url = SWB . 'files/kiosk_media/' . $v['file_path'];
                echo '<div class="video-container" style="margin-bottom: 20px;">';
                echo '<video controls autoplay muted loop>';
                echo '<source src="' . $vid_url . '" type="video/mp4">';
                echo 'Browser Anda tidak mendukung pemutar video.';
                echo '</video>';
                echo '<h3>' . $v['media_title'] . '</h3>';
                echo '</div>';
            }
        } else {
            echo '<p style="text-align:center; color: #94a3b8; font-size: 1.2rem;">Belum ada video kegiatan yang diunggah.</p>';
        }
        ?>
    </div>

    <a href="<?php echo SWB; ?>index.php" class="back-btn">← Kembali ke Beranda OPAC</a>

    <script>
        function switchTab(type) {
            document.querySelectorAll('.section-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));

            if (type === 'foto') {
                document.getElementById('tab-foto').classList.add('active');
                document.querySelectorAll('.tab-btn')[0].classList.add('active');
            } else {
                document.getElementById('tab-video').classList.add('active');
                document.querySelectorAll('.tab-btn')[1].classList.add('active');
            }
        }

        // Script Otomatis Slideshow Foto setiap 4 Detik
        let slideIndex = 0;
        function showSlides() {
            let slides = document.getElementsByClassName("slide");
            if (slides.length === 0) return;

            for (let i = 0; i < slides.length; i++) {
                slides[i].classList.remove("active-slide");
            }

            slideIndex++;
            if (slideIndex > slides.length) { slideIndex = 1; }

            slides[slideIndex - 1].classList.add("active-slide");
            setTimeout(showSlides, 4000); // Ganti slide setiap 4 detik
        }

        // Jalankan slideshow saat halaman dimuat
        if (document.getElementsByClassName("slide").length > 0) {
            setTimeout(showSlides, 4000);
        }
    </script>

</body>

</html>
<?php
exit;
?>