<?php
defined('INDEX_AUTH') OR die('Direct access is not allowed!');
global $dbs, $sysconf;

// Pastikan kolom-kolom transisi dan urutan sudah tersedia di database
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

$col_check3 = $dbs->query("SHOW COLUMNS FROM `plugin_kiosk_media` LIKE 'order_num'");
if ($col_check3 && $col_check3->num_rows == 0) {
    $dbs->query("ALTER TABLE `plugin_kiosk_media` ADD COLUMN `order_num` INT(11) NOT NULL DEFAULT 0");
    $dbs->query("UPDATE `plugin_kiosk_media` SET `order_num` = `media_id` WHERE `order_num` = 0");
}
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
            cursor: none; /* Sembunyikan kursor agar bersih seperti TV / Digital Signage */
            user-select: none;
            -webkit-user-select: none;
        }

        /* Tampilkan kursor sejenak jika mouse digerakkan */
        body.show-cursor {
            cursor: default;
        }

        /* Container Fullscreen Layar Penuh */
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

        /* Media Foto & Video Responsif Menyesuaikan Resolusi Monitor/TV */
        .kiosk-slide img {
            width: 100vw;
            height: 100vh;
            object-fit: contain; /* Menjaga rasio foto tetap sempurna tanpa terpotong / gepeng */
            background: #000000;
            pointer-events: none;
        }

        .kiosk-slide video {
            width: 100vw;
            height: 100vh;
            object-fit: contain; /* Menjaga rasio video tetap proporsional */
            background: #000000;
            outline: none;
            border: none;
        }

        /* ========================================= */
        /* KEYFRAMES GAYA TRANSISI FULLSCREEN        */
        /* ========================================= */
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

        /* Class Animasi */
        .trans-fade { animation: animFade 0.9s ease-in-out; }
        .trans-slide-left { animation: animSlideLeft 0.85s cubic-bezier(0.16, 1, 0.3, 1); }
        .trans-slide-right { animation: animSlideRight 0.85s cubic-bezier(0.16, 1, 0.3, 1); }
        .trans-zoom-in { animation: animZoomIn 0.85s cubic-bezier(0.16, 1, 0.3, 1); }
        .trans-zoom-out { animation: animZoomOut 0.85s cubic-bezier(0.16, 1, 0.3, 1); }
        .trans-flip { animation: animFlip 0.95s cubic-bezier(0.16, 1, 0.3, 1); }
        .trans-bounce { animation: animBounce 0.9s cubic-bezier(0.175, 0.885, 0.32, 1.275); }

        /* Status audio jika pengguna menyalakan suara */
        .kiosk-audio-toast {
            position: fixed;
            bottom: 25px;
            right: 25px;
            background: rgba(15, 23, 42, 0.75);
            color: #ffffff;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            backdrop-filter: blur(5px);
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
            z-index: 999;
        }

        /* Notifikasi jika belum ada media */
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
        // Ambil SEMUA media (baik foto maupun video) secara berurutan sesuai urutan yang diatur user
        $q_media = $dbs->query("SELECT * FROM plugin_kiosk_media ORDER BY order_num ASC, media_id ASC");
        
        if ($q_media && $q_media->num_rows > 0) {
            $idx = 0;
            while ($m = $q_media->fetch_assoc()) {
                $file_url = SWB . 'files/kiosk_media/' . htmlspecialchars($m['file_path']);
                $type = $m['media_type']; // 'foto' atau 'video'
                $trans_style = !empty($m['transition_style']) ? htmlspecialchars($m['transition_style']) : 'fade';
                $trans_dur   = !empty($m['transition_duration']) ? (int)$m['transition_duration'] : 5;
                $active_class = ($idx === 0) ? 'active-slide trans-' . $trans_style : '';
                ?>
                <div class="kiosk-slide <?php echo $active_class; ?>" 
                     data-index="<?php echo $idx; ?>"
                     data-type="<?php echo $type; ?>" 
                     data-transition="<?php echo $trans_style; ?>" 
                     data-duration="<?php echo $trans_dur; ?>">

                    <?php if ($type === 'foto'): ?>
                        <img src="<?php echo $file_url; ?>" alt="" loading="eager">
                    <?php else: ?>
                        <!-- Video diputar otomatis (muted & playsinline agar kompatibel semua browser & TV) -->
                        <video src="<?php echo $file_url; ?>" muted playsinline preload="auto"></video>
                    <?php endif; ?>

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

        // Cegah layar monitor / TV mati / tidur (Screen Wake Lock API jika didukung)
        if ('wakeLock' in navigator) {
            try {
                navigator.wakeLock.request('screen').catch(() => {});
            } catch (err) {}
        }

        // Tampilkan kursor saat mouse digerakkan, lalu sembunyikan otomatis setelah 2 detik
        let cursorTimeout;
        document.addEventListener('mousemove', function() {
            document.body.classList.add('show-cursor');
            clearTimeout(cursorTimeout);
            cursorTimeout = setTimeout(function() {
                document.body.classList.remove('show-cursor');
            }, 2000);
        });

        // Klik layar untuk masuk ke Native Fullscreen browser & toggle audio video
        document.addEventListener('click', function() {
            // Masuk mode fullscreen jika belum
            if (!document.fullscreenElement && !document.webkitFullscreenElement) {
                if (document.documentElement.requestFullscreen) {
                    document.documentElement.requestFullscreen().catch(() => {});
                } else if (document.documentElement.webkitRequestFullscreen) {
                    document.documentElement.webkitRequestFullscreen();
                }
            }

            // Toggle suara jika ada video
            isMuted = !isMuted;
            document.querySelectorAll('video').forEach(v => v.muted = isMuted);
            
            if (audioToast) {
                audioToast.textContent = isMuted ? '🔇 Suara: Mati' : '🔊 Suara: Nyala';
                audioToast.style.opacity = '1';
                setTimeout(() => { audioToast.style.opacity = '0'; }, 1500);
            }
        });

        // Fungsi berpindah slide secara mulus berurutan
        function showSlide(index) {
            if (slides.length === 0) return;

            // Loop kembali ke awal jika sudah di akhir
            if (index >= slides.length) index = 0;
            if (index < 0) index = 0;

            // Hentikan dan reset slide sebelumnya
            slides.forEach(slide => {
                slide.classList.remove('active-slide');
                slide.className = slide.className.replace(/\btrans-\S+/g, '').trim();
                
                const vid = slide.querySelector('video');
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

            // Tampilkan slide dengan gaya transisi yang diatur
            currentSlide.classList.add('active-slide', 'trans-' + transStyle);

            // Bersihkan timer sebelumnya
            if (slideTimer) {
                clearTimeout(slideTimer);
                slideTimer = null;
            }

            if (mediaType === 'video') {
                const video = currentSlide.querySelector('video');
                if (video) {
                    video.currentTime = 0;
                    video.muted = isMuted;

                    const playPromise = video.play();
                    if (playPromise !== undefined) {
                        playPromise.catch(() => {
                            // Jika browser menolak pemutaran dengan suara, paksa mute agar tetap berputar
                            video.muted = true;
                            video.play().catch(() => {});
                        });
                    }

                    // Saat video selesai diputar -> pindah ke media berikutnya
                    video.onended = function() {
                        nextSlide();
                    };

                    // Fallback timer pengaman jika event onended terlewat / video macet
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
                // Tipe Foto: jalankan sesuai durasi detik yang diatur user
                slideTimer = setTimeout(nextSlide, duration * 1000);
            }
        }

        function nextSlide() {
            showSlide(currentIndex + 1);
        }

        // Jalankan slide pertama saat halaman selesai dimuat
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
