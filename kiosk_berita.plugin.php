<?php
/**
 * Plugin Name: Kiosk Berita, Galeri & Video
 * Plugin URI: https://slims.web.id
 * Description: Modul Kiosk untuk menampilkan galeri foto dan video kegiatan yang dikelola dari Admin SLiMS.
 * Version: 1.0.0
 * Author: Pustakawan SLiMS
 * Author URI: https://slims.web.id
 */

use SLiMS\Plugins;

$plugin = Plugins::getInstance();

// 1. Daftarkan menu di modul System (Admin SLiMS)
$plugin->registerMenu('system', 'Kelola Media Kiosk', __DIR__ . '/admin_media.php');

// 2. Daftarkan halaman publik Kiosk (?p=kiosk-berita)
$plugin->registerMenu('opac', 'kiosk-berita', __DIR__ . '/kiosk_view.php');

// 3. Buat tabel database otomatis saat plugin pertama kali dimuat
global $dbs;
$dbs->query("CREATE TABLE IF NOT EXISTS `plugin_kiosk_media` (
  `media_id` INT(11) NOT NULL AUTO_INCREMENT,
  `media_title` VARCHAR(255) NOT NULL,
  `media_type` ENUM('foto','video') NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `created_date` DATETIME NOT NULL,
  PRIMARY KEY (`media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");