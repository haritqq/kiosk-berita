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
            max-width: 960px;
            position: relative;
            margin: auto;
            background: #1e293b;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.6);
            text-align: center;
            overflow: hidden;
        }

        .slide {
            display: none;
        }

        .slide.active-slide {
            display: block;
        }

        .slide img {
            width: 100%;
            height: 500px;
            object-fit: contain;
            border-radius: 12px;
            background: #000;
        }

        .slide-caption {
            font-size: 1.5rem;
            margin-top: 18px;
            font-weight: bold;
            color: #f8fafc;
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
        }

        /* Keyframes Animasi Transisi */
        @keyframes animFade {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes animSlideLeft {
            from { opacity: 0; transform: translateX(100px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes animSlideRight {
            from { opacity: 0; transform: translateX(-100px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes animZoomIn {
            from { opacity: 0; transform: scale(0.85); }
            to { opacity: 1; transform: scale(1); }
        }
        @keyframes animZoomOut {
            from { opacity: 0; transform: scale(1.15); }
            to { opacity: 1; transform: scale(1); }
        }
        @keyframes animFlip {
            from { opacity: 0; transform: perspective(800px) rotateY(45deg); }
            to { opacity: 1; transform: perspective(800px) rotateY(0); }
        }
        @keyframes animBounce {
            0% { opacity: 0; transform: scale(0.7); }
            60% { opacity: 1; transform: scale(1.05); }
            100% { opacity: 1; transform: scale(1); }
        }

        .trans-fade { animation: animFade 0.8s ease-in-out; }
        .trans-slide-left { animation: animSlideLeft 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
        .trans-slide-right { animation: animSlideRight 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
        .trans-zoom-in { animation: animZoomIn 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
        .trans-zoom-out { animation: animZoomOut 0.8s cubic-bezier(0.16, 1, 0.3, 1); }
        .trans-flip { animation: animFlip 0.9s cubic-bezier(0.16, 1, 0.3, 1); }
        .trans-bounce { animation: animBounce 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275); }

        /* Container Video */
        .video-container {
            max-width: 860px;
            margin: 0 auto;
            background: #1e293b;
            padding: 24px;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
        }

        .video-container video {
            width: 100%;
            max-height: 500px;
            border-radius: 12px;
            background: #000;
        }

        .video-container h3 {
            margin-top: 15px;
            font-size: 1.4rem;
            color: #f8fafc;
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
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            transition: 0.2s;
        }
        .back-btn:hover {
            background: #334155;
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
                    $img_url = SWB . 'files/kiosk_media/' . htmlspecialchars($f['file_path']);
                    $trans_style = !empty($f['transition_style']) ? htmlspecialchars($f['transition_style']) : 'fade';
                    $trans_dur   = !empty($f['transition_duration']) ? (int)$f['transition_duration'] : 5;
                    $active_class = ($idx === 0) ? 'active-slide trans-' . $trans_style : '';
                    
                    echo '<div class="slide ' . $active_class . '" data-transition="' . $trans_style . '" data-duration="' . $trans_dur . '">';
                    echo '<img src="' . $img_url . '" alt="' . htmlspecialchars($f['media_title']) . '">';
                    echo '<div class="slide-caption">' . htmlspecialchars($f['media_title']) . '</div>';
                    echo '</div>';
                    $idx++;
                }
            } else {
                echo '<p style="color: #94a3b8; padding: 50px 20px; font-size: 1.2rem;">Belum ada foto kegiatan yang diunggah oleh pustakawan.</p>';
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
                $vid_url = SWB . 'files/kiosk_media/' . htmlspecialchars($v['file_path']);
                echo '<div class="video-container" style="margin-bottom: 25px;">';
                echo '<video controls autoplay muted loop>';
                echo '<source src="' . $vid_url . '" type="video/mp4">';
                echo 'Browser Anda tidak mendukung pemutar video.';
                echo '</video>';
                echo '<h3>' . htmlspecialchars($v['media_title']) . '</h3>';
                echo '</div>';
            }
        } else {
            echo '<p style="text-align:center; color: #94a3b8; font-size: 1.2rem; padding: 40px;">Belum ada video kegiatan yang diunggah.</p>';
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

        // Script Otomatis Slideshow Foto dengan Waktu & Transisi Sesuai Database
        let slideIndex = 0;
        let slideTimer = null;

        function showSlides() {
            let slides = document.getElementsByClassName("slide");
            if (slides.length === 0) return;

            for (let i = 0; i < slides.length; i++) {
                slides[i].className = slides[i].className.replace(/\btrans-\S+/g, '').replace('active-slide', '').trim();
            }

            slideIndex++;
            if (slideIndex > slides.length) { slideIndex = 1; }

            let currentSlide = slides[slideIndex - 1];
            let trans = currentSlide.getAttribute('data-transition') || 'fade';
            let duration = parseInt(currentSlide.getAttribute('data-duration')) || 5;

            currentSlide.classList.add('active-slide', 'trans-' + trans);

            if (slideTimer) clearTimeout(slideTimer);
            slideTimer = setTimeout(showSlides, duration * 1000);
        }

        // Inisialisasi awal durasi slide pertama
        let initialSlides = document.getElementsByClassName("slide");
        if (initialSlides.length > 0) {
            let firstDuration = parseInt(initialSlides[0].getAttribute('data-duration')) || 5;
            slideTimer = setTimeout(showSlides, firstDuration * 1000);
        }
    </script>

</body>

</html>
<?php
exit;
?>
