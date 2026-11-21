<?php
defined('INDEX_AUTH') OR die('Direct access is not allowed!');
global $dbs, $sysconf;

// Pastikan tabel dan kolom sudah tersedia
$dbs->query("CREATE TABLE IF NOT EXISTS `plugin_kiosk_media` (
  `media_id` INT(11) NOT NULL AUTO_INCREMENT,
  `media_title` VARCHAR(255) NOT NULL,
  `media_type` ENUM('foto','video') NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `transition_style` VARCHAR(50) NOT NULL DEFAULT 'fade',
  `transition_duration` INT(11) NOT NULL DEFAULT 5,
  `order_num` INT(11) NOT NULL DEFAULT 0,
  `bg_template` VARCHAR(50) NOT NULL DEFAULT 'default',
  `created_date` DATETIME NOT NULL,
  PRIMARY KEY (`media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$dbs->query("CREATE TABLE IF NOT EXISTS `plugin_kiosk_setting` (
  `setting_key` VARCHAR(50) NOT NULL PRIMARY KEY,
  `setting_value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$col_check4 = $dbs->query("SHOW COLUMNS FROM `plugin_kiosk_media` LIKE 'bg_template'");
if ($col_check4 && $col_check4->num_rows == 0) {
    $dbs->query("ALTER TABLE `plugin_kiosk_media` ADD COLUMN `bg_template` VARCHAR(50) NOT NULL DEFAULT 'default'");
}

$col_check5 = $dbs->query("SHOW COLUMNS FROM `plugin_kiosk_media` LIKE 'display_mode'");
if ($col_check5 && $col_check5->num_rows == 0) {
    $dbs->query("ALTER TABLE `plugin_kiosk_media` ADD COLUMN `display_mode` VARCHAR(50) NOT NULL DEFAULT 'default'");
}

// Ambil Pengaturan Latar Belakang & Tata Letak Layar Global
$bg_settings = [];
$q_set = $dbs->query("SELECT setting_key, setting_value FROM plugin_kiosk_setting");
if ($q_set && $q_set->num_rows > 0) {
    while ($s = $q_set->fetch_assoc()) {
        $bg_settings[$s['setting_key']] = $s['setting_value'];
    }
}
$global_bg_tpl       = $bg_settings['bg_template'] ?? 'orbs';
$global_bg_file      = $bg_settings['bg_custom_file'] ?? '';
$global_bg_blur      = (int)($bg_settings['bg_blur'] ?? 25);
$global_bg_bright    = (float)($bg_settings['bg_brightness'] ?? 0.65);
$global_display_mode = $bg_settings['media_display_mode'] ?? 'fullscreen'; // 'fullscreen' (edge-to-edge) atau 'framed' (bingkai melayang)
$bg_upload_url       = SWB . 'files/kiosk_media/backgrounds/';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Kiosk Display - <?php echo htmlspecialchars($sysconf['library_name']); ?></title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html, body {
            width: 100vw;
            height: 100vh;
            overflow: hidden;
            background-color: #000000;
            color: #ffffff;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            cursor: none;
            user-select: none;
            -webkit-user-select: none;
        }

        body.show-cursor {
            cursor: default;
        }

        /* Container Viewport Layar Penuh */
        .kiosk-viewport {
            position: relative;
            width: 100vw;
            height: 100vh;
            background: #000000;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        /* Elemen Slide (Foto / Video) */
        .kiosk-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            display: none;
            align-items: center;
            justify-content: center;
            background: #000000;
            overflow: hidden;
        }

        .kiosk-slide.active-slide {
            display: flex;
            z-index: 2;
        }

        /* Layer Media Foreground di Tengah (Responsif untuk Portrait & Landscape) */
        .kiosk-media-wrapper {
            position: relative;
            z-index: 10;
            width: 100vw;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
        }

        .kiosk-fg-media {
            object-fit: contain;
            outline: none;
            border: none;
            background: transparent;
            transition: max-width 0.3s ease, max-height 0.3s ease, border-radius 0.3s ease;
        }

        /* ======================================================== */
        /* MODE 1: MEMENUHI LAYAR PENUH (EDGE-TO-EDGE)              */
        /* Sesuai Permintaan User: Video & foto memenuhi layar      */
        /* dan tetap proporsional tanpa terpotong.                  */
        /* ======================================================== */
        .kiosk-slide.display-fullscreen .kiosk-fg-media {
            width: 100vw !important;
            height: 100vh !important;
            max-width: 100vw !important;
            max-height: 100vh !important;
            object-fit: contain !important;
            border-radius: 0px !important;
            box-shadow: none !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        /* ======================================================== */
        /* MODE 2: BINGKAI MELAYANG DI TENGAH (FRAMED CENTER CARD)  */
        /* Video & foto berada di tengah dengan sela tepi, sudut   */
        /* melengkung halus, dan bayangan mengambang di atas latar. */
        /* ======================================================== */
        .kiosk-slide.display-framed .kiosk-fg-media {
            max-width: 94vw !important;
            max-height: 94vh !important;
            width: auto !important;
            height: auto !important;
            object-fit: contain !important;
            border-radius: 16px !important;
            box-shadow: 0 16px 50px rgba(0, 0, 0, 0.85), 0 0 1px rgba(255, 255, 255, 0.15) !important;
            margin: auto !important;
        }

        /* ======================================================== */
        /* TEMPLATE LATAR BELAKANG (BERGERAK & STATIS)              */
        /* ======================================================== */

        /* 1. Template: Bola-Bola Mengambang (Floating Glowing Orbs) */
        .kiosk-bg-orbs {
            position: absolute;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: #080d1a;
            overflow: hidden;
            z-index: 1;
        }
        .kiosk-orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(65px);
            opacity: 0.65;
            animation-iteration-count: infinite;
            animation-direction: alternate;
            animation-timing-function: ease-in-out;
        }
        .kiosk-orb-1 {
            width: 520px;
            height: 520px;
            background: radial-gradient(circle, #0284c7 0%, rgba(2, 132, 199, 0) 70%);
            top: -100px;
            left: -100px;
            animation: orbAnim1 18s infinite alternate;
        }
        .kiosk-orb-2 {
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, #8b5cf6 0%, rgba(139, 92, 246, 0) 70%);
            bottom: -150px;
            right: -100px;
            animation: orbAnim2 22s infinite alternate;
        }
        .kiosk-orb-3 {
            width: 420px;
            height: 420px;
            background: radial-gradient(circle, #06b6d4 0%, rgba(6, 182, 212, 0) 70%);
            top: 35%;
            right: 15%;
            animation: orbAnim3 16s infinite alternate;
        }
        .kiosk-orb-4 {
            width: 480px;
            height: 480px;
            background: radial-gradient(circle, #ec4899 0%, rgba(236, 72, 153, 0) 70%);
            bottom: 20%;
            left: 20%;
            animation: orbAnim4 20s infinite alternate;
        }
        .kiosk-orb-5 {
            width: 380px;
            height: 380px;
            background: radial-gradient(circle, #f59e0b 0%, rgba(245, 158, 11, 0) 70%);
            top: 60%;
            left: 45%;
            animation: orbAnim5 24s infinite alternate;
        }
        @keyframes orbAnim1 {
            0% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(260px, 180px) scale(1.15); }
            100% { transform: translate(100px, 320px) scale(0.9); }
        }
        @keyframes orbAnim2 {
            0% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(-220px, -160px) scale(0.85); }
            100% { transform: translate(-360px, -80px) scale(1.2); }
        }
        @keyframes orbAnim3 {
            0% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(-180px, 120px) scale(1.25); }
            100% { transform: translate(140px, -160px) scale(0.95); }
        }
        @keyframes orbAnim4 {
            0% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(160px, -180px) scale(1.1); }
            100% { transform: translate(-120px, 100px) scale(0.8); }
        }
        @keyframes orbAnim5 {
            0% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(-140px, -80px) scale(1.25); }
            100% { transform: translate(180px, 90px) scale(0.9); }
        }

        /* 2. Template: Ambient Blur Cerdas (Dynamic Media Backdrop) */
        .kiosk-bg-ambient {
            position: absolute;
            top: -5%;
            left: -5%;
            width: 110vw;
            height: 110vh;
            overflow: hidden;
            z-index: 1;
            filter: blur(<?php echo $global_bg_blur; ?>px) brightness(<?php echo $global_bg_bright; ?>) saturate(1.4);
            transform: scale(1.1);
        }
        .kiosk-bg-ambient img,
        .kiosk-bg-ambient video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* 3. Template: Gelombang Aurora (Animated Gradient Wave) */
        .kiosk-bg-gradient {
            position: absolute;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: linear-gradient(-45deg, #091326, #162447, #1f4068, #1a1a2e, #0e1e38);
            background-size: 400% 400%;
            animation: auroraWave 14s ease infinite;
            z-index: 1;
        }
        @keyframes auroraWave {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* 4. Template: Bintang & Partikel Mengambang (Floating Particles) */
        .kiosk-bg-particles {
            position: absolute;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: radial-gradient(ellipse at bottom, #1b2735 0%, #090a0f 100%);
            overflow: hidden;
            z-index: 1;
        }
        .kiosk-particle {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.7);
            box-shadow: 0 0 10px rgba(56, 189, 248, 0.8);
            animation: particleFloat 12s infinite linear;
        }

        /* 5. Template: Statis Hitam Pekat Minimalis */
        .kiosk-bg-black {
            position: absolute;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: #000000;
            z-index: 1;
        }

        /* 6. Template: Statis Gradasi Midnight Navy */
        .kiosk-bg-navy {
            position: absolute;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: radial-gradient(circle at center, #172554 0%, #030712 100%);
            z-index: 1;
        }

        /* 7. Template: Kustom File Upload */
        .kiosk-bg-custom {
            position: absolute;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            overflow: hidden;
            z-index: 1;
            background-size: cover;
            background-position: center;
        }
        .kiosk-bg-custom video {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .kiosk-bg-custom-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
        }

        /* ======================================================== */
        /* KEYFRAMES GAYA TRANSISI FULLSCREEN                       */
        /* ======================================================== */
        @keyframes animFade {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes animSlideLeft {
            from { opacity: 0; transform: translateX(100vw); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes animSlideRight {
            from { opacity: 0; transform: translateX(-100vw); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes animZoomIn {
            from { opacity: 0; transform: scale(0.82); }
            to { opacity: 1; transform: scale(1); }
        }
        @keyframes animZoomOut {
            from { opacity: 0; transform: scale(1.18); }
            to { opacity: 1; transform: scale(1); }
        }
        @keyframes animFlip {
            from { opacity: 0; transform: perspective(1200px) rotateY(90deg); }
            to { opacity: 1; transform: perspective(1200px) rotateY(0); }
        }
        @keyframes animBounce {
            0% { opacity: 0; transform: scale(0.65); }
            60% { opacity: 1; transform: scale(1.04); }
            100% { opacity: 1; transform: scale(1); }
        }

        .trans-fade .kiosk-media-wrapper { animation: animFade 0.9s ease-in-out; }
        .trans-slide-left .kiosk-media-wrapper { animation: animSlideLeft 0.85s cubic-bezier(0.16, 1, 0.3, 1); }
        .trans-slide-right .kiosk-media-wrapper { animation: animSlideRight 0.85s cubic-bezier(0.16, 1, 0.3, 1); }
        .trans-zoom-in .kiosk-media-wrapper { animation: animZoomIn 0.85s cubic-bezier(0.16, 1, 0.3, 1); }
        .trans-zoom-out .kiosk-media-wrapper { animation: animZoomOut 0.85s cubic-bezier(0.16, 1, 0.3, 1); }
        .trans-flip .kiosk-media-wrapper { animation: animFlip 0.95s cubic-bezier(0.16, 1, 0.3, 1); }
        .trans-bounce .kiosk-media-wrapper { animation: animBounce 0.9s cubic-bezier(0.175, 0.885, 0.32, 1.275); }

        .kiosk-audio-toast {
            position: fixed;
            bottom: 25px;
            right: 25px;
            background: rgba(15, 23, 42, 0.8);
            color: #ffffff;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            backdrop-filter: blur(6px);
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
            z-index: 999;
        }

        .kiosk-empty {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100vw;
            height: 100vh;
            color: #475569;
            font-size: 1.1rem;
            text-align: center;
        }
    </style>
</head>

<body>

    <div class="kiosk-viewport" id="kioskViewport">
        <?php
        $q_media = $dbs->query("SELECT * FROM plugin_kiosk_media ORDER BY order_num ASC, media_id ASC");
        
        if ($q_media && $q_media->num_rows > 0) {
            $idx = 0;
            while ($m = $q_media->fetch_assoc()) {
                $file_url = SWB . 'files/kiosk_media/' . htmlspecialchars($m['file_path']);
                $type = $m['media_type'];
                $trans_style = !empty($m['transition_style']) ? htmlspecialchars($m['transition_style']) : 'fade';
                $trans_dur   = !empty($m['transition_duration']) ? (int)$m['transition_duration'] : 5;
                $active_class = ($idx === 0) ? 'active-slide trans-' . $trans_style : '';

                // Tentukan template latar belakang untuk slide ini
                $slide_bg_tpl = (!empty($m['bg_template']) && $m['bg_template'] !== 'default') ? $m['bg_template'] : $global_bg_tpl;

                // Tentukan mode tata letak layar (Fullscreen Edge-to-Edge vs Bingkai Melayang)
                $slide_disp_mode = (!empty($m['display_mode']) && $m['display_mode'] !== 'default') ? $m['display_mode'] : $global_display_mode;
                if (!in_array($slide_disp_mode, ['fullscreen', 'framed'])) $slide_disp_mode = 'fullscreen';
                $disp_class = 'display-' . $slide_disp_mode;
                ?>
                <div class="kiosk-slide <?php echo $active_class . ' ' . $disp_class; ?>" 
                     data-index="<?php echo $idx; ?>"
                     data-type="<?php echo $type; ?>" 
                     data-transition="<?php echo $trans_style; ?>" 
                     data-duration="<?php echo $trans_dur; ?>"
                     data-bg="<?php echo $slide_bg_tpl; ?>"
                     data-display="<?php echo $slide_disp_mode; ?>">

                    <!-- 1. LAYER LATAR BELAKANG (BERGERAK / STATIS / CUSTOM) -->
                    <?php if ($slide_bg_tpl === 'ambient'): ?>
                        <!-- Ambient Blur Cerdas: Mengambil dan mem-blur media yang sedang tayang -->
                        <div class="kiosk-bg-ambient">
                            <?php if ($type === 'foto'): ?>
                                <img src="<?php echo $file_url; ?>" alt="">
                            <?php else: ?>
                                <video src="<?php echo $file_url; ?>" muted loop autoplay playsinline></video>
                            <?php endif; ?>
                        </div>

                    <?php elseif ($slide_bg_tpl === 'gradient'): ?>
                        <!-- Gelombang Aurora Gradasi Bergerak -->
                        <div class="kiosk-bg-gradient"></div>

                    <?php elseif ($slide_bg_tpl === 'particles'): ?>
                        <!-- Partikel Bintang Mengambang -->
                        <div class="kiosk-bg-particles">
                            <?php for ($p = 0; $p < 25; $p++): 
                                $top = rand(5, 95);
                                $left = rand(5, 95);
                                $size = rand(3, 8);
                                $delay = rand(0, 10);
                                $dur = rand(8, 16);
                            ?>
                                <div class="kiosk-particle" style="top:<?php echo $top;?>%; left:<?php echo $left;?>%; width:<?php echo $size;?>px; height:<?php echo $size;?>px; animation-delay:<?php echo $delay;?>s; animation-duration:<?php echo $dur;?>s;"></div>
                            <?php endfor; ?>
                        </div>

                    <?php elseif ($slide_bg_tpl === 'navy'): ?>
                        <!-- Gradasi Midnight Navy Statis -->
                        <div class="kiosk-bg-navy"></div>

                    <?php elseif ($slide_bg_tpl === 'black'): ?>
                        <!-- Hitam Pekat Minimalis -->
                        <div class="kiosk-bg-black"></div>

                    <?php elseif ($slide_bg_tpl === 'custom' && !empty($global_bg_file) && file_exists(SB . 'files/kiosk_media/backgrounds/' . $global_bg_file)): ?>
                        <!-- Template Kustom Hasil Upload (Foto / Video MP4) -->
                        <?php 
                        $bg_custom_ext = strtolower(pathinfo($global_bg_file, PATHINFO_EXTENSION));
                        $bg_custom_url = $bg_upload_url . htmlspecialchars($global_bg_file);
                        ?>
                        <div class="kiosk-bg-custom" <?php echo ($bg_custom_ext !== 'mp4') ? "style=\"background-image: url('{$bg_custom_url}');\"" : ""; ?>>
                            <?php if ($bg_custom_ext === 'mp4'): ?>
                                <video src="<?php echo $bg_custom_url; ?>" autoplay muted loop playsinline></video>
                            <?php endif; ?>
                            <div class="kiosk-bg-custom-overlay"></div>
                        </div>

                    <?php else: ?>
                        <!-- Default: Bola-Bola Mengambang Bergerak (Floating Orbs) -->
                        <div class="kiosk-bg-orbs">
                            <div class="kiosk-orb kiosk-orb-1"></div>
                            <div class="kiosk-orb kiosk-orb-2"></div>
                            <div class="kiosk-orb kiosk-orb-3"></div>
                            <div class="kiosk-orb kiosk-orb-4"></div>
                            <div class="kiosk-orb kiosk-orb-5"></div>
                        </div>
                    <?php endif; ?>

                    <!-- 2. LAYER MEDIA FOREGROUND DI TENGAH (FOTO / VIDEO) -->
                    <div class="kiosk-media-wrapper">
                        <?php if ($type === 'foto'): ?>
                            <img src="<?php echo $file_url; ?>" alt="" class="kiosk-fg-media" loading="eager">
                        <?php else: ?>
                            <video src="<?php echo $file_url; ?>" class="kiosk-fg-media" muted playsinline preload="auto"></video>
                        <?php endif; ?>
                    </div>

                </div>
                <?php
                $idx++;
            }
        } else {
            echo '<div class="kiosk-empty">Belum ada foto atau video aktif di Kiosk. Silakan upload melalui Admin SLiMS.</div>';
        }
        ?>
    </div>

    <!-- Toast Indikator Audio (Mute / Unmute) -->
    <div class="kiosk-audio-toast" id="audioToast">🔊 Suara: Nyala</div>

    <script type="text/javascript">
    (function() {
        const slides = document.querySelectorAll('.kiosk-slide');
        const audioToast = document.getElementById('audioToast');
        let currentIndex = -1;
        let slideTimer = null;
        let isMuted = true;

        // Cegah layar monitor / TV mati / tidur (Screen Wake Lock API)
        if ('wakeLock' in navigator) {
            try {
                navigator.wakeLock.request('screen').catch(() => {});
            } catch (err) {}
        }

        // Tampilkan kursor saat mouse digerakkan, sembunyikan otomatis setelah 2 detik
        let cursorTimeout;
        document.addEventListener('mousemove', function() {
            document.body.classList.add('show-cursor');
            clearTimeout(cursorTimeout);
            cursorTimeout = setTimeout(function() {
                document.body.classList.remove('show-cursor');
            }, 2000);
        });

        // Klik layar untuk masuk ke Native Fullscreen browser & toggle audio
        document.addEventListener('click', function() {
            if (!document.fullscreenElement && !document.webkitFullscreenElement) {
                if (document.documentElement.requestFullscreen) {
                    document.documentElement.requestFullscreen().catch(() => {});
                } else if (document.documentElement.webkitRequestFullscreen) {
                    document.documentElement.webkitRequestFullscreen();
                }
            }

            isMuted = !isMuted;
            document.querySelectorAll('.kiosk-fg-media').forEach(v => {
                if (v.tagName.toLowerCase() === 'video') v.muted = isMuted;
            });
            
            if (audioToast) {
                audioToast.textContent = isMuted ? '🔇 Suara: Mati' : '🔊 Suara: Nyala';
                audioToast.style.opacity = '1';
                setTimeout(() => { audioToast.style.opacity = '0'; }, 1500);
            }
        });

        // Kontrol Keyboard Cerdas di Monitor / TV:
        // 'L' atau 'T' = Toggle Tata Letak (Penuh Layar Edge-to-Edge vs Bingkai Melayang di Tengah)
        // 'M' = Toggle Audio Suara Video
        // 'F' = Toggle Mode Layar Penuh Fullscreen
        // Panah Kanan / Spasi = Lanjut ke Slide Berikutnya
        // Panah Kiri = Kembali ke Slide Sebelumnya
        document.addEventListener('keydown', function(e) {
            const key = e.key.toLowerCase();
            if (key === 'l' || key === 't') {
                let isNowFramed = false;
                slides.forEach(slide => {
                    if (slide.classList.contains('display-framed')) {
                        slide.classList.remove('display-framed');
                        slide.classList.add('display-fullscreen');
                        isNowFramed = false;
                    } else {
                        slide.classList.remove('display-fullscreen');
                        slide.classList.add('display-framed');
                        isNowFramed = true;
                    }
                });

                if (audioToast) {
                    audioToast.textContent = isNowFramed ? '🖼️ Tata Letak: Bingkai Melayang di Tengah' : '🖥️ Tata Letak: Memenuhi Layar Penuh (Edge-to-Edge)';
                    audioToast.style.opacity = '1';
                    setTimeout(() => { audioToast.style.opacity = '0'; }, 2000);
                }
            } else if (key === 'm') {
                isMuted = !isMuted;
                document.querySelectorAll('.kiosk-fg-media').forEach(v => {
                    if (v.tagName.toLowerCase() === 'video') v.muted = isMuted;
                });
                if (audioToast) {
                    audioToast.textContent = isMuted ? '🔇 Suara: Mati' : '🔊 Suara: Nyala';
                    audioToast.style.opacity = '1';
                    setTimeout(() => { audioToast.style.opacity = '0'; }, 1500);
                }
            } else if (key === 'f') {
                if (!document.fullscreenElement && !document.webkitFullscreenElement) {
                    if (document.documentElement.requestFullscreen) {
                        document.documentElement.requestFullscreen().catch(() => {});
                    } else if (document.documentElement.webkitRequestFullscreen) {
                        document.documentElement.webkitRequestFullscreen();
                    }
                } else {
                    if (document.exitFullscreen) {
                        document.exitFullscreen().catch(() => {});
                    }
                }
            } else if (key === 'arrowright' || key === ' ') {
                showSlide(currentIndex + 1);
            } else if (key === 'arrowleft') {
                showSlide(currentIndex - 1);
            }
        });

        // Fungsi berpindah slide secara mulus
        function showSlide(index) {
            if (slides.length === 0) return;

            if (index >= slides.length) index = 0;
            if (index < 0) index = 0;

            slides.forEach(slide => {
                slide.classList.remove('active-slide');
                slide.className = slide.className.replace(/\btrans-\S+/g, '').trim();
                
                const vid = slide.querySelector('video.kiosk-fg-media');
                if (vid) {
                    vid.pause();
                    vid.currentTime = 0;
                    vid.onended = null;
                }
            });

            currentIndex = index;
            const currentSlide = slides[currentIndex];
            const mediaType = currentSlide.getAttribute('data-type');
            const transStyle = currentSlide.getAttribute('data-transition') || 'fade';
            const duration = parseInt(currentSlide.getAttribute('data-duration')) || 5;

            currentSlide.classList.add('active-slide', 'trans-' + transStyle);

            if (slideTimer) {
                clearTimeout(slideTimer);
                slideTimer = null;
            }

            if (mediaType === 'video') {
                const video = currentSlide.querySelector('video.kiosk-fg-media');
                if (video) {
                    video.currentTime = 0;
                    video.muted = isMuted;

                    const playPromise = video.play();
                    if (playPromise !== undefined) {
                        playPromise.catch(() => {
                            video.muted = true;
                            video.play().catch(() => {});
                        });
                    }

                    // Saat video selesai diputar -> pindah ke slide berikutnya
                    video.onended = function() {
                        nextSlide();
                    };

                    video.onloadedmetadata = function() {
                        const vidDur = Math.ceil(video.duration);
                        if (vidDur && !isNaN(vidDur) && vidDur > 0) {
                            slideTimer = setTimeout(nextSlide, (vidDur + 2) * 1000);
                        }
                    };
                } else {
                    slideTimer = setTimeout(nextSlide, duration * 1000);
                }
            } else {
                slideTimer = setTimeout(nextSlide, duration * 1000);
            }
        }

        function nextSlide() {
            showSlide(currentIndex + 1);
        }

        if (slides.length > 0) {
            showSlide(0);
        }
    })();
    </script>

</body>

</html>
<?php
exit;
?>
