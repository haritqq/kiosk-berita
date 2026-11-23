<?php
/**
 * Plugin Name: Kiosk Berita, Galeri & Video
 * Plugin URI: https://github.com/haritqq/kiosk-berita
 * Description: Modul Kiosk untuk menampilkan galeri foto dan video kegiatan yang dikelola dari Admin SLiMS.
 * Version: 1.2.0
 * Author: Muhammad Nur Haritsah
 * Author URI: https://github.com/haritqq/
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
  `transition_style` VARCHAR(50) NOT NULL DEFAULT 'fade',
  `transition_duration` INT(11) NOT NULL DEFAULT 5,
  `order_num` INT(11) NOT NULL DEFAULT 0,
  `bg_template` VARCHAR(50) NOT NULL DEFAULT 'default',
  `display_mode` VARCHAR(50) NOT NULL DEFAULT 'default',
  `created_date` DATETIME NOT NULL,
  PRIMARY KEY (`media_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Update tabel jika kolom display_mode belum ada
$col_disp = $dbs->query("SHOW COLUMNS FROM `plugin_kiosk_media` LIKE 'display_mode'");
if ($col_disp && $col_disp->num_rows == 0) {
    $dbs->query("ALTER TABLE `plugin_kiosk_media` ADD COLUMN `display_mode` VARCHAR(50) NOT NULL DEFAULT 'default'");
}

$dbs->query("CREATE TABLE IF NOT EXISTS `plugin_kiosk_setting` (
  `setting_key` VARCHAR(50) NOT NULL PRIMARY KEY,
  `setting_value` TEXT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Inisialisasi default setting media_display_mode jika belum ada
$chk_disp = $dbs->query("SELECT setting_value FROM plugin_kiosk_setting WHERE setting_key = 'media_display_mode'");
if ($chk_disp && $chk_disp->num_rows == 0) {
    $dbs->query("INSERT INTO plugin_kiosk_setting (setting_key, setting_value) VALUES ('media_display_mode', 'fullscreen')");
}
