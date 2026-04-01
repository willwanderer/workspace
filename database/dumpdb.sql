/*
SQLyog Community v13.3.1 (64 bit)
MySQL - 8.0.37 : Database - workspace_db
*********************************************************************
*/

/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
CREATE DATABASE /*!32312 IF NOT EXISTS*/`workspace_db` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;

USE `workspace_db`;

/*Table structure for table `activity_logs` */

DROP TABLE IF EXISTS `activity_logs`;

CREATE TABLE `activity_logs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `action` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int NOT NULL,
  `old_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `new_value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `activity_logs` */

insert  into `activity_logs`(`id`,`user_id`,`action`,`entity_type`,`entity_id`,`old_value`,`new_value`,`ip_address`,`created_at`) values 
(6,2,'created','project',4,NULL,'Pemeriksaan Belanja Bantuan Partai Politik Kab Luwu Timir TA 2025','::1','2026-03-24 13:43:24'),
(7,2,'updated','project',4,NULL,'Pemeriksaan Belanja Bantuan Partai Politik Kab Luwu Timur TA 2025','::1','2026-03-24 14:29:08'),
(8,2,'deleted','project',4,NULL,NULL,'::1','2026-03-24 22:06:14'),
(9,2,'created','project',5,NULL,'Pemeriksaan Belanja Bantuan Partai Politik Kab Luwu Timur TA 2025','::1','2026-03-24 22:09:37'),
(10,2,'deleted','project',5,NULL,NULL,'::1','2026-03-24 22:16:13'),
(11,2,'created','project',6,NULL,'Pemeriksaan Belanja Bantuan Partai Politik Kab Luwu Timur TA 2025','::1','2026-03-24 22:21:01'),
(12,2,'deleted','project',6,NULL,NULL,'::1','2026-03-24 23:02:04'),
(13,2,'created','project',7,NULL,'Pemeriksaan Belanja Bantuan Partai Politik Kab Luwu Timur TA 2025','::1','2026-03-24 23:02:49'),
(14,2,'deleted','project',7,NULL,NULL,'::1','2026-03-24 23:03:58'),
(15,2,'created','project',8,NULL,'Pemeriksaan Belanja Bantuan Partai Politik Kab Luwu Timur TA 2025','::1','2026-03-24 23:06:48'),
(16,2,'deleted','project',8,NULL,NULL,'::1','2026-03-25 01:03:38'),
(17,2,'created','project',9,NULL,'Pemeriksaan Belanja Bantuan Partai Politik Kab Luwu Timur TA 2025','::1','2026-03-25 01:04:47'),
(18,2,'created','task',7,NULL,'Merapikan aplikasi ASTI','::1','2026-03-25 01:36:09'),
(19,2,'created','task',8,NULL,'Buat P2','::1','2026-03-25 01:41:19'),
(20,2,'created','quick_link',9,NULL,'SiSDM','::1','2026-03-26 19:39:23'),
(21,2,'created','quick_link',10,NULL,'Kelolah Tugas','::1','2026-03-26 19:48:29'),
(22,2,'updated','quick_link',9,NULL,'SiSDM','::1','2026-03-26 19:51:11'),
(23,2,'deleted','task',7,NULL,NULL,'::1','2026-03-28 23:18:50'),
(24,2,'deleted','task',8,NULL,NULL,'::1','2026-03-28 23:28:04'),
(25,2,'created','task',9,NULL,'Membuat P2','::1','2026-03-28 23:28:52'),
(26,2,'created','task',10,NULL,'Menginput pada Siap Banparpol','::1','2026-03-28 23:29:32'),
(27,2,'created','task',11,NULL,'Membuat LHP Banparpol','::1','2026-03-28 23:30:07'),
(28,2,'created','project',10,NULL,'2026 - LKPD Jeneponto TA 2025','::1','2026-03-28 23:33:30'),
(29,2,'created','task',12,NULL,'Perhitungan Kekurangan Volume Belanja Modal JIJ','::1','2026-03-28 23:34:28'),
(30,2,'created','task',13,NULL,'Dalami Permasalahan Terkait E-Katalog','::1','2026-03-28 23:35:41'),
(31,2,'created','task',14,NULL,'Perhitungan Kekurangan Volume Belanja Modal Gedung-Bangunan','::1','2026-03-28 23:36:51'),
(32,2,'updated','task',14,NULL,'Perhitungan Kekurangan Volume Belanja Modal Gedung-Bangunan','::1','2026-03-28 23:37:46'),
(33,2,'created','task',15,NULL,'Update Dashboard DRTLHP per Sem 1 2026','::1','2026-03-28 23:38:57'),
(34,2,'updated','task',9,NULL,'Membuat P2','::1','2026-03-28 23:39:19'),
(35,2,'updated','task',10,NULL,'Menginput pada Siap Banparpol','::1','2026-03-28 23:39:32'),
(36,2,'updated','task',11,NULL,'Membuat LHP Banparpol','::1','2026-03-28 23:39:43'),
(37,2,'updated','task',12,NULL,'Perhitungan Kekurangan Volume Belanja Modal JIJ','::1','2026-03-28 23:39:58'),
(38,2,'updated','task',9,NULL,'in_progress','10.60.10.84','2026-03-30 11:56:45'),
(39,2,'updated','task',10,NULL,'in_progress','10.60.10.69','2026-03-31 16:52:50'),
(40,2,'created','task',16,NULL,'Konfirmasi Hasil Uji Lab untuk Bossowa Betton','10.60.10.69','2026-03-31 17:19:04'),
(41,2,'updated','task',10,NULL,'completed','10.60.10.50','2026-04-01 13:37:19');

/*Table structure for table `contact_categories` */

DROP TABLE IF EXISTS `contact_categories`;

CREATE TABLE `contact_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#3b82f6',
  `user_id` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `contact_categories_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `contact_categories` */

insert  into `contact_categories`(`id`,`name`,`color`,`user_id`,`created_at`) values 
(1,'Client','#2563eb',1,'2026-03-24 12:49:54'),
(2,'Partner','#10b981',1,'2026-03-24 12:49:54'),
(3,'Vendor','#f59e0b',1,'2026-03-24 12:49:54'),
(4,'Lead','#8b5cf6',1,'2026-03-24 12:49:54');

/*Table structure for table `contacts` */

DROP TABLE IF EXISTS `contacts`;

CREATE TABLE `contacts` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `website` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tags` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `contacts_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `contacts` */

/*Table structure for table `folders` */

DROP TABLE IF EXISTS `folders`;

CREATE TABLE `folders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `parent_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'project, task, or null for root',
  `parent_id` int DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `folder_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_folder_key` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `parent_type` (`parent_type`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `folders` */

insert  into `folders`(`id`,`user_id`,`parent_type`,`parent_id`,`name`,`folder_key`,`parent_folder_key`,`created_at`) values 
(25,2,'project',9,'Pemeriksaan Belanja Bantuan Partai Politik Kab Luwu Timur TA 2025','proj_9_pemeriks82c4',NULL,'2026-03-25 01:04:47'),
(26,2,'task',7,'Merapikan aplikasi ASTI','task_7_merapika4ff2',NULL,'2026-03-25 01:36:09'),
(33,2,'task',8,'Buat P2','proj_9_pemeriks82c4/task_8_buatp26d08','proj_9_pemeriks82c4','2026-03-25 18:53:32'),
(36,2,'task',9,'Membuat P2','proj_9_pemeriks82c4/task_9_membuatp2831','proj_9_pemeriks82c4','2026-03-28 23:28:52'),
(37,2,'task',10,'Menginput pada Siap Banparpol','proj_9_pemeriks82c4/task_10_menginpu3cc5','proj_9_pemeriks82c4','2026-03-28 23:29:32'),
(38,2,'task',11,'Membuat LHP Banparpol','proj_9_pemeriks82c4/task_11_membuatl40ab','proj_9_pemeriks82c4','2026-03-28 23:30:07'),
(39,2,'project',10,'2026 - LKPD Jeneponto TA 2025','proj_10_2026lkpd11e6',NULL,'2026-03-28 23:33:30'),
(40,2,'task',12,'Perhitungan Kekurangan Volume Belanja Modal JIJ','proj_10_2026lkpd11e6/task_12_perhitunabcd','proj_10_2026lkpd11e6','2026-03-28 23:34:28'),
(41,2,'task',13,'Dalami Permasalahan Terkait E-Katalog','proj_10_2026lkpd11e6/task_13_dalamipea216','proj_10_2026lkpd11e6','2026-03-28 23:35:41'),
(42,2,'task',14,'Perhitungan Kekurangan Volume Belanja Modal Gedung-Bangunan','proj_10_2026lkpd11e6/task_14_perhitun4596','proj_10_2026lkpd11e6','2026-03-28 23:36:51'),
(43,2,'task',15,'Update Dashboard DRTLHP per Sem 1 2026','task_15_updateda6813',NULL,'2026-03-28 23:38:57'),
(44,2,'task',16,'Konfirmasi Hasil Uji Lab untuk Bossowa Betton','proj_10_2026lkpd11e6/task_16_konfirmac47c','proj_10_2026lkpd11e6','2026-03-31 17:19:04');

/*Table structure for table `link_categories` */

DROP TABLE IF EXISTS `link_categories`;

CREATE TABLE `link_categories` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `icon` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'link',
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#3b82f6',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `link_categories_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `link_categories` */

insert  into `link_categories`(`id`,`user_id`,`name`,`icon`,`color`,`created_at`) values 
(1,1,'Work','briefcase','#2563eb','2026-03-24 12:49:54'),
(2,1,'Social','users','#10b981','2026-03-24 12:49:54'),
(3,1,'Tools','tool','#f59e0b','2026-03-24 12:49:54'),
(4,1,'News','newspaper','#ef4444','2026-03-24 12:49:54');

/*Table structure for table `notes` */

DROP TABLE IF EXISTS `notes`;

CREATE TABLE `notes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#fef3c7',
  `is_pinned` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `parent_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `notes` */

insert  into `notes`(`id`,`user_id`,`title`,`content`,`color`,`is_pinned`,`created_at`,`updated_at`,`parent_type`,`parent_id`) values 
(3,2,NULL,'Lakukan Koordinasi dengan pengendali teknis terkait P2','#fef3c7',0,'2026-03-28 23:30:32','2026-03-28 23:30:32','project',9);

/*Table structure for table `notifications` */

DROP TABLE IF EXISTS `notifications`;

CREATE TABLE `notifications` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=155 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `notifications` */

insert  into `notifications`(`id`,`user_id`,`type`,`title`,`message`,`link`,`is_read`,`created_at`) values 
(150,2,'task_upcoming','Tugas Hampir Due!','Tugas \"Konfirmasi Hasil Uji Lab untuk Bossowa Betton\" akan due date pada 02 Apr 2026','index.php?page=task_detail&id=16',0,'2026-04-01 17:19:43'),
(151,2,'task_overdue','Tugas Terlambat!','Tugas \"Membuat P2\" sudah lewat due date sejak 30 Mar 2026 (1 hari terlambat)','index.php?page=task_detail&id=9',0,'2026-04-01 17:19:43'),
(152,2,'task_overdue','Tugas Terlambat!','Tugas \"Membuat LHP Banparpol\" sudah lewat due date sejak 30 Mar 2026 (1 hari terlambat)','index.php?page=task_detail&id=11',0,'2026-04-01 17:19:43'),
(153,2,'task_overdue','Tugas Terlambat!','Tugas \"Perhitungan Kekurangan Volume Belanja Modal JIJ\" sudah lewat due date sejak 31 Mar 2026 (0 hari terlambat)','index.php?page=task_detail&id=12',0,'2026-04-01 17:19:43'),
(154,2,'project_overdue','Proyek Terlambat!','Proyek \"Pemeriksaan Belanja Bantuan Partai Politik Kab Luwu Timur TA 2025\" sudah lewat due date sejak 31 Mar 2026 (1 hari terlambat)','index.php?page=project_detail&id=9',0,'2026-04-01 17:19:43');

/*Table structure for table `organizer_labels` */

DROP TABLE IF EXISTS `organizer_labels`;

CREATE TABLE `organizer_labels` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#3b82f6',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `organizer_labels_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `organizer_labels` */

/*Table structure for table `organizer_notes` */

DROP TABLE IF EXISTS `organizer_notes`;

CREATE TABLE `organizer_notes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#ffffff',
  `is_pinned` tinyint(1) DEFAULT '0',
  `is_archived` tinyint(1) DEFAULT '0',
  `is_trashed` tinyint(1) DEFAULT '0',
  `reminder` datetime DEFAULT NULL,
  `labels` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `is_pinned` (`is_pinned`),
  KEY `is_archived` (`is_archived`),
  KEY `is_trashed` (`is_trashed`),
  KEY `reminder` (`reminder`),
  CONSTRAINT `organizer_notes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `organizer_notes` */

insert  into `organizer_notes`(`id`,`user_id`,`title`,`content`,`color`,`is_pinned`,`is_archived`,`is_trashed`,`reminder`,`labels`,`created_at`,`updated_at`) values 
(1,2,'dfsda','dafsd','#fee2e2',0,0,1,'2026-04-01 15:57:00',NULL,'2026-04-01 15:52:34','2026-04-01 16:00:49'),
(2,2,'Pribadi','Pribadi\r\nNIK : 5308192803930001 \r\nNIK Mei : 3578225105950001\r\n‎NoKK : 5371023008220005\r\nNo BPJStk : 5308192803930001\r\nNo BpjS Kes : 0000149879518\r\nId Pelanggan Listrik kupang : 45019209431\r\nId listrik vms makassar : 45033217493\r\nmalkus9328 Pass :will123456\r\nNo TCash : 201508 0028 3320\r\n\r\n\r\nAkun\r\n‎netflix tspwillybrodus@gmail.com  always!@#4567\r\nArti6768@gmail.com \r\nhttp://waifu2x.udp.jp/\r\nPass phpmyadmin nas : Always!@#456\r\nemail : willy!@#456\r\n000webhost fspsoft : fsp!@#456\r\n575858\r\n11012022wc\r\nWILLYBRODUS28 || Always1234 || 28031993 || RDN rek 04203154521\r\nAlamat Kontrakan Makassar: Jalan Jipang Raya, Blok C/7, Perumahan Villa Megasari, Kec. Rappocini, Kota Makassar, Sulawesi Selatan 90221\r\nAlamat Rumah Kupang : Perumahan Villa Grand Nusa II Nomor B6, Kelurahan Liliba, Kecamatan Oebobo, Kota Kupang, Kode Pos 85111.  -10.169714,123.644530\r\nUB: LD 57 x 76.    Reg 16 1/2 reg 17 1/2\r\nIcloud : Always!@#456\r\nphpmyadmin synology : root Always!@#456\r\ncristinamei95@gmail.com             M@ju5858\r\n\r\nWork\r\nNIP PNS : 199303282020121004 \r\nNIP BPK : 240010281 \r\nNIK Taspen : 201819933595\r\nEmail BPK : malkus.w@bpk.go.id \r\n\r\nNomor Rekening\r\nMy BNI : 0196657074 (malkus28 pass : Willy!@#456 030303)\r\nMy BRI : 207901006865502 (malkuswilly12345 pin: 280393 pas: Always1234)\r\nMantap Meiwi : 2012109060202\r\nMandiri a.n. CRISTINA MEI WULANDARI - 1420013932545\r\nMandiri Taspen a.n. CRISTINA MEI WULANDARI - 2012104671639\r\nBNI Mei : 1752015583   M@ju5858!      999555\r\nBni elen: 0603 611993\r\nKode pas cctv : garasi PAUXFH pintu depan EPUXBB taman samping PJUYJQ modem 085111304626\r\n\r\nEmail\r\nwillwanderer@gmail.com          meiwi!@#456\r\nfspsoftinc@gmail.com\r\ntspwillybrodus@gmail.com\r\nByu.akunkeren@gmail.com.    @qwert123\r\nKoperasi MANTAP cristina      ||  mei123    ||  cif: 00009784\r\n\r\n◦ Pass ig: 5758M@pan\r\n◦ ICloud: M@ju5858\r\n◦ Greatday: 5758M@pan!\r\n◦ Twitter: Maju5858\r\n◦ Pass ig 2: M@ju5858\r\n◦ Gmail: M@ju5858\r\n◦ BPJSTK: Maju5858\r\n◦ Inhealth: M@ju5858\r\n◦ MOST: 4171445 , Pass: 5758M@pan!\r\n◦ Fb: Melati1\r\n◦ Pajak: Maju5858\r\n◦ Efin: 8168726119\r\n◦ JKN Mei: Maju5858\r\n◦ TikTok: M@ju5858\r\n\r\n◦ Nik 3578225105950001\r\n◦ User: Cristinamw95 , Pass: 999555\r\n\r\nREG 5308192803930001#5371023008220005#\r\n\r\nScribd Downloader:\r\n- docdownloader.com\r\n- dlscrib.com \r\n- scribddown.com\r\n','#d1fae5',0,0,0,NULL,NULL,'2026-04-01 15:59:44','2026-04-01 16:05:06'),
(3,2,'sgfd','dfsg','#ffffff',0,0,1,NULL,NULL,'2026-04-01 16:01:00','2026-04-01 16:01:07'),
(4,2,'adsfasdfadf','dafs','#fee2e2',0,0,1,'2026-04-01 16:33:00',NULL,'2026-04-01 16:01:20','2026-04-01 16:37:23');

/*Table structure for table `organizer_todos` */

DROP TABLE IF EXISTS `organizer_todos`;

CREATE TABLE `organizer_todos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `note_id` int NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_completed` tinyint(1) DEFAULT '0',
  `position` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `note_id` (`note_id`),
  KEY `is_completed` (`is_completed`),
  CONSTRAINT `organizer_todos_ibfk_1` FOREIGN KEY (`note_id`) REFERENCES `organizer_notes` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `organizer_todos` */

/*Table structure for table `project_attachments` */

DROP TABLE IF EXISTS `project_attachments`;

CREATE TABLE `project_attachments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `folder_id` int DEFAULT NULL,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int NOT NULL DEFAULT '0',
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uploaded_by` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `folder_id` (`folder_id`),
  KEY `uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `project_attachments` */

/*Table structure for table `project_comments` */

DROP TABLE IF EXISTS `project_comments`;

CREATE TABLE `project_comments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `user_id` int NOT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `project_id` (`project_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `project_comments_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `project_comments` */

/*Table structure for table `project_members` */

DROP TABLE IF EXISTS `project_members`;

CREATE TABLE `project_members` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_id` int NOT NULL,
  `user_id` int NOT NULL,
  `role` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'member',
  `joined_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_project_user` (`project_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `project_members_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `project_members` */

insert  into `project_members`(`id`,`project_id`,`user_id`,`role`,`joined_at`) values 
(6,9,2,'owner','2026-03-25 01:04:47'),
(7,10,2,'owner','2026-03-28 23:33:30');

/*Table structure for table `projects` */

DROP TABLE IF EXISTS `projects`;

CREATE TABLE `projects` (
  `id` int NOT NULL AUTO_INCREMENT,
  `project_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `owner_id` int NOT NULL,
  `status` enum('planning','active','on_hold','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'planning',
  `progress_percentage` int DEFAULT '0',
  `start_date` date DEFAULT NULL,
  `deadline` date DEFAULT NULL,
  `folder_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `owner_id` (`owner_id`),
  CONSTRAINT `projects_ibfk_1` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `projects` */

insert  into `projects`(`id`,`project_code`,`name`,`description`,`owner_id`,`status`,`progress_percentage`,`start_date`,`deadline`,`folder_name`,`created_at`,`updated_at`) values 
(9,'Prjt001','Pemeriksaan Belanja Bantuan Partai Politik Kab Luwu Timur TA 2025','Pemeriksaan Belanja Banparpol Kab Luwu Timur TA 2025',2,'active',0,'2026-03-09','2026-03-31',NULL,'2026-03-25 01:04:47','2026-03-25 01:04:47'),
(10,'Prjt002','2026 - LKPD Jeneponto TA 2025','Pemeriksaan Laporan Keuangan Pemerintah Daerah Kabupaten Jeneponto Tahun Anggaran 2025',2,'active',0,'2026-02-04','2026-05-06',NULL,'2026-03-28 23:33:30','2026-03-28 23:33:30');

/*Table structure for table `quick_links` */

DROP TABLE IF EXISTS `quick_links`;

CREATE TABLE `quick_links` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `title` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `favicon` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `icon_upload` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'general',
  `is_pinned` tinyint(1) DEFAULT '0',
  `is_favorite` tinyint(1) DEFAULT '0',
  `click_count` int DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `quick_links_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `quick_links` */

insert  into `quick_links`(`id`,`user_id`,`title`,`url`,`favicon`,`icon_upload`,`category`,`is_pinned`,`is_favorite`,`click_count`,`created_at`,`updated_at`) values 
(9,2,'SiSDM','https://sisdm.bpk.go.id','https://www.google.com/s2/favicons?domain=sisdm.bpk.go.id&sz=64',NULL,'work',0,0,0,'2026-03-26 19:39:23','2026-03-26 19:51:11'),
(10,2,'Kelolah Tugas','http://m.kelolatugas.bpk.go.id','https://www.google.com/s2/favicons?domain=m.kelolatugas.bpk.go.id&sz=64',NULL,'Work',0,0,0,'2026-03-26 19:48:29','2026-03-26 19:48:29');

/*Table structure for table `status_history` */

DROP TABLE IF EXISTS `status_history`;

CREATE TABLE `status_history` (
  `id` int NOT NULL AUTO_INCREMENT,
  `entity_type` enum('task','project') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'task or project',
  `entity_id` int NOT NULL,
  `old_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `new_status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` int NOT NULL,
  `note` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `entity_type` (`entity_type`),
  KEY `entity_id` (`entity_id`),
  KEY `user_id` (`user_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `status_history` */

insert  into `status_history`(`id`,`entity_type`,`entity_id`,`old_status`,`new_status`,`user_id`,`note`,`created_at`) values 
(1,'task',9,'pending','in_progress',2,'Status diubah dari \'Ditunda\' menjadi \'Sedang Dikerjakan\'. Catatan: Telah dikirimkan ke Pengendali Teknis','2026-03-30 11:56:45'),
(2,'task',10,'pending','in_progress',2,'Status diubah dari \'Ditunda\' menjadi \'Sedang Dikerjakan\'','2026-03-31 16:52:50'),
(3,'task',10,'in_progress','completed',2,'Status diubah dari \'Sedang Dikerjakan\' menjadi \'Selesai\'','2026-04-01 13:37:19');

/*Table structure for table `task_attachments` */

DROP TABLE IF EXISTS `task_attachments`;

CREATE TABLE `task_attachments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `task_id` int NOT NULL,
  `folder_id` int DEFAULT NULL,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_size` int NOT NULL DEFAULT '0',
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `uploaded_by` int NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `task_id` (`task_id`),
  KEY `folder_id` (`folder_id`),
  KEY `uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `task_attachments` */

/*Table structure for table `task_comments` */

DROP TABLE IF EXISTS `task_comments`;

CREATE TABLE `task_comments` (
  `id` int NOT NULL AUTO_INCREMENT,
  `task_id` int NOT NULL,
  `user_id` int NOT NULL,
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `task_id` (`task_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `task_comments_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `task_comments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `task_comments` */

/*Table structure for table `task_reminders` */

DROP TABLE IF EXISTS `task_reminders`;

CREATE TABLE `task_reminders` (
  `id` int NOT NULL AUTO_INCREMENT,
  `task_id` int NOT NULL,
  `remind_at` datetime NOT NULL,
  `is_sent` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `task_id` (`task_id`),
  CONSTRAINT `task_reminders_ibfk_1` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `task_reminders` */

/*Table structure for table `tasks` */

DROP TABLE IF EXISTS `tasks`;

CREATE TABLE `tasks` (
  `id` int NOT NULL AUTO_INCREMENT,
  `task_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `user_id` int NOT NULL,
  `project_id` int DEFAULT NULL,
  `folder_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('pending','in_progress','completed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `priority` enum('low','medium','high','urgent') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'medium',
  `category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'general',
  `label_color` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT '#6b7280',
  `deadline` datetime DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `project_id` (`project_id`),
  CONSTRAINT `tasks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tasks_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `tasks` */

insert  into `tasks`(`id`,`task_code`,`title`,`description`,`user_id`,`project_id`,`folder_name`,`status`,`priority`,`category`,`label_color`,`deadline`,`start_date`,`completed_at`,`created_at`,`updated_at`) values 
(9,'TGS001','Membuat P2','Membuat P2 dari pemeriksaan banparpol',2,9,NULL,'in_progress','urgent','general','#6b7280','2026-03-30 23:28:00',NULL,NULL,'2026-03-28 23:28:52','2026-03-30 11:56:45'),
(10,'TGS002','Menginput pada Siap Banparpol','Menginput data pada siap banparpol',2,9,NULL,'completed','urgent','general','#6b7280','2026-03-30 23:29:00',NULL,'2026-04-01 12:37:19','2026-03-28 23:29:32','2026-04-01 13:37:19'),
(11,'TGS003','Membuat LHP Banparpol','Membuat Laporan Hasil Pemeriksaan Banparpol',2,9,NULL,'pending','urgent','general','#6b7280','2026-03-30 23:30:00',NULL,NULL,'2026-03-28 23:30:07','2026-03-28 23:39:43'),
(12,'TGS004','Perhitungan Kekurangan Volume Belanja Modal JIJ','Lakukan perhitungan kekurangan volume pekerjaan Belanja Modal JIJ',2,10,NULL,'pending','urgent','general','#6b7280','2026-03-31 23:34:00',NULL,NULL,'2026-03-28 23:34:28','2026-03-28 23:39:58'),
(13,'TGS005','Dalami Permasalahan Terkait E-Katalog','Lakukan pendalaman terkait kegiatan pengadaan melalui e-katalog',2,10,NULL,'pending','medium','general','#6b7280','2026-04-18 23:35:00',NULL,NULL,'2026-03-28 23:35:41','2026-03-28 23:35:41'),
(14,'TGS006','Perhitungan Kekurangan Volume Belanja Modal Gedung-Bangunan','Lakukan Perhitungan terhadap Kekurangan Volume Belanja Modal Gedung dan Bangunan',2,10,NULL,'pending','medium','general','#6b7280','2026-04-04 23:36:00',NULL,NULL,'2026-03-28 23:36:51','2026-03-28 23:37:46'),
(15,'TGS007','Update Dashboard DRTLHP per Sem 1 2026','Lakukan Pembaharuan Dashboard DRTLHP per Semester 1 2026',2,NULL,NULL,'pending','medium','general','#6b7280','2026-05-10 23:38:00',NULL,NULL,'2026-03-28 23:38:57','2026-03-28 23:38:57'),
(16,'TGS008','Konfirmasi Hasil Uji Lab untuk Bossowa Betton','Konfirmasi Hasil Uji Lab untuk Bossowa Betton terkait Pekerjaan Lapangan Mini Stadion',2,10,NULL,'pending','medium','general','#6b7280','2026-04-02 17:19:00',NULL,NULL,'2026-03-31 17:19:04','2026-03-31 17:19:04');

/*Table structure for table `user_sessions` */

DROP TABLE IF EXISTS `user_sessions`;

CREATE TABLE `user_sessions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `session_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_token` (`session_token`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `user_sessions` */

/*Table structure for table `user_settings` */

DROP TABLE IF EXISTS `user_settings`;

CREATE TABLE `user_settings` (
  `id` int NOT NULL AUTO_INCREMENT,
  `user_id` int NOT NULL,
  `theme` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'light',
  `timezone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Asia/Jakarta',
  `email_notifications` tinyint(1) DEFAULT '1',
  `browser_notifications` tinyint(1) DEFAULT '0',
  `task_reminder_days` int DEFAULT '1',
  `language` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'en',
  `date_format` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Y-m-d',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  CONSTRAINT `user_settings_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `user_settings` */

insert  into `user_settings`(`id`,`user_id`,`theme`,`timezone`,`email_notifications`,`browser_notifications`,`task_reminder_days`,`language`,`date_format`,`created_at`,`updated_at`) values 
(1,1,'light','Asia/Jakarta',1,0,1,'en','Y-m-d','2026-03-24 12:49:54','2026-03-24 12:49:54'),
(2,2,'light','Asia/Jakarta',1,0,1,'en','Y-m-d','2026-03-24 12:59:49','2026-03-24 12:59:49');

/*Table structure for table `users` */

DROP TABLE IF EXISTS `users`;

CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `role` enum('admin','manager','member') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'member',
  `timezone` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'Asia/Jakarta',
  `theme` enum('light','dark','auto') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'light',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_login` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

/*Data for the table `users` */

insert  into `users`(`id`,`username`,`email`,`password_hash`,`full_name`,`avatar`,`role`,`timezone`,`theme`,`created_at`,`updated_at`,`last_login`,`is_active`) values 
(1,'admin','admin@workspace.local','$2y$10$Iwu1Ihon7Nag63zk20GZU.rEZYym30Inh2bBuKA02fDOuY0vgoCm6','Administrator',NULL,'admin','Asia/Jakarta','light','2026-03-24 12:49:54','2026-03-24 13:36:41','2026-03-24 13:36:41',1),
(2,'willybrodus','willybrodus@workspace.local','$2y$10$WuBNALHZnniwM90SGHE1meQhQ9xjAinhwYw3i00d0HB75Txr.5h4C','Malkus Willybrodus Se',NULL,'admin','Asia/Jakarta','light','2026-03-24 12:59:49','2026-04-01 17:25:59','2026-04-01 17:25:59',1);

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
