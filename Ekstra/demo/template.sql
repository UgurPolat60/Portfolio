/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19-11.8.6-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: certby_template
-- ------------------------------------------------------
-- Server version	11.8.6-MariaDB-5ubuntu0.1 from Ubuntu

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*M!100616 SET @OLD_NOTE_VERBOSITY=@@NOTE_VERBOSITY, NOTE_VERBOSITY=0 */;

--
-- Current Database: `certby_template`
--



--
-- Table structure for table `accreditation_types`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `accreditation_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accreditation_types`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `accreditation_types` WRITE;
/*!40000 ALTER TABLE `accreditation_types` DISABLE KEYS */;
INSERT INTO `accreditation_types` VALUES
(5,'TÜRKAK','2025-09-17 09:35:24'),
(6,'UKAS','2025-09-17 09:35:27'),
(7,'DAKKS','2025-09-17 09:35:30'),
(8,'IAS','2025-09-17 09:35:41'),
(9,'NONAC','2025-09-17 09:35:50'),
(10,'NAC','2025-09-17 09:35:52'),
(12,'UAF','2025-09-17 09:37:02');
/*!40000 ALTER TABLE `accreditation_types` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `auditor_assignments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `auditor_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plan_id` int(11) NOT NULL,
  `auditor_id` int(11) NOT NULL COMMENT 'users tablosundaki auditor rolündeki kullanıcıya referans',
  `assignment_date` date NOT NULL,
  `assignment_status` enum('assigned','in_progress','completed','cancelled') DEFAULT 'assigned',
  `assignment_notes` text DEFAULT NULL,
  `completed_inspection_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_plan_auditor` (`plan_id`,`auditor_id`),
  KEY `auditor_id` (`auditor_id`),
  KEY `fk_assignment_completed_inspection` (`completed_inspection_id`),
  CONSTRAINT `auditor_assignments_ibfk_1` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assignment_completed_inspection` FOREIGN KEY (`completed_inspection_id`) REFERENCES `completed_inspections` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_auditor_assignments_user` FOREIGN KEY (`auditor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auditor_assignments`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `auditor_assignments` WRITE;
/*!40000 ALTER TABLE `auditor_assignments` DISABLE KEYS */;
/*!40000 ALTER TABLE `auditor_assignments` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `certification_organizations`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `certification_organizations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `certification_organizations`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `certification_organizations` WRITE;
/*!40000 ALTER TABLE `certification_organizations` DISABLE KEYS */;
/*!40000 ALTER TABLE `certification_organizations` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `certifications`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `certifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `document_type_id` int(11) NOT NULL,
  `accreditation_type` varchar(100) DEFAULT NULL,
  `certification_organization` varchar(200) DEFAULT NULL,
  `consultant_id` int(11) DEFAULT NULL,
  `document_number` varchar(50) NOT NULL,
  `scope` text DEFAULT NULL,
  `issue_date` date NOT NULL,
  `expiry_date` date NOT NULL,
  `level` int(11) DEFAULT NULL,
  `status` enum('active','inactive','suspended','cancelled','updated') NOT NULL DEFAULT 'active',
  `inspection_1_status` enum('bekliyor','planlandi','tamamlandi','iptal') DEFAULT 'bekliyor',
  `inspection_2_status` enum('bekliyor','planlandi','tamamlandi','iptal') DEFAULT 'bekliyor',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `belge_masraf` decimal(10,2) DEFAULT 0.00 COMMENT 'Belge masrafı',
  `belge_odenen` decimal(10,2) DEFAULT 0.00 COMMENT 'Belge için ödenen miktar',
  `danisman_masraf` decimal(10,2) DEFAULT 0.00 COMMENT 'Danışman masrafı',
  `danisman_odenen` decimal(10,2) DEFAULT 0.00 COMMENT 'Danışman için ödenen miktar',
  `egitim_masraf` decimal(10,2) DEFAULT 0.00 COMMENT 'Eğitim masrafı',
  `egitim_odenen` decimal(10,2) DEFAULT 0.00 COMMENT 'Eğitim için ödenen miktar',
  `expense_updated_at` timestamp NULL DEFAULT NULL COMMENT 'Masraf son güncelleme tarihi',
  `expense_updated_by` int(11) DEFAULT NULL COMMENT 'Masrafı güncelleyen kullanıcı',
  PRIMARY KEY (`id`),
  UNIQUE KEY `document_number` (`document_number`),
  KEY `company_id` (`company_id`),
  KEY `document_type_id` (`document_type_id`),
  KEY `created_by` (`created_by`),
  KEY `fk_certification_consultant` (`consultant_id`),
  CONSTRAINT `certifications_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `certifications_ibfk_2` FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`id`),
  CONSTRAINT `certifications_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `certifications_ibfk_4` FOREIGN KEY (`consultant_id`) REFERENCES `consultants` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `certifications`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `certifications` WRITE;
/*!40000 ALTER TABLE `certifications` DISABLE KEYS */;
/*!40000 ALTER TABLE `certifications` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `companies`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `companies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `short_name` varchar(100) NOT NULL,
  `trade_name` varchar(200) NOT NULL,
  `address` text NOT NULL,
  `invoice_address` text DEFAULT NULL,
  `website` varchar(200) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `corporate_email` varchar(100) DEFAULT NULL,
  `authorized_person` varchar(100) DEFAULT NULL,
  `contact_person` varchar(100) NOT NULL,
  `contact_phone` varchar(20) NOT NULL,
  `contact_email` varchar(100) NOT NULL,
  `tax_office` varchar(100) NOT NULL,
  `tax_number` varchar(20) NOT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tax_number` (`tax_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `companies`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `companies` WRITE;
/*!40000 ALTER TABLE `companies` DISABLE KEYS */;
/*!40000 ALTER TABLE `companies` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `completed_inspections`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `completed_inspections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plan_id` int(11) NOT NULL,
  `certification_id` int(11) DEFAULT NULL,
  `non_certified_inspection_id` int(11) DEFAULT NULL,
  `inspection_type` enum('certified','non_certified') NOT NULL DEFAULT 'certified',
  `auditor_id` int(11) NOT NULL COMMENT 'users tablosundaki auditor rolündeki kullanıcıya referans',
  `company_id` int(11) NOT NULL,
  `inspection_date` date NOT NULL,
  `completion_date` datetime NOT NULL DEFAULT current_timestamp(),
  `inspection_notes` text DEFAULT NULL,
  `inspection_result` enum('passed','failed','conditional') DEFAULT 'passed',
  `next_inspection_date` date DEFAULT NULL,
  `recommendations` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_completed_plan_id` (`plan_id`),
  KEY `idx_completed_certification` (`certification_id`),
  KEY `idx_completed_auditor` (`auditor_id`),
  KEY `idx_completed_company` (`company_id`),
  KEY `idx_completed_inspection_date` (`inspection_date`),
  KEY `fk_completed_created_by` (`created_by`),
  KEY `fk_completed_updated_by` (`updated_by`),
  KEY `idx_completed_non_certified` (`non_certified_inspection_id`),
  CONSTRAINT `fk_completed_auditor_user` FOREIGN KEY (`auditor_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_completed_certification` FOREIGN KEY (`certification_id`) REFERENCES `certifications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_completed_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_completed_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_completed_non_certified` FOREIGN KEY (`non_certified_inspection_id`) REFERENCES `non_certified_inspections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_completed_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_completed_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci COMMENT='Tamamlanmış tetkikler tablosu';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `completed_inspections`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `completed_inspections` WRITE;
/*!40000 ALTER TABLE `completed_inspections` DISABLE KEYS */;
/*!40000 ALTER TABLE `completed_inspections` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `consultants`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `consultants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_short_name` varchar(100) NOT NULL,
  `company_full_name` varchar(200) NOT NULL,
  `company_address` text DEFAULT NULL,
  `company_email` varchar(100) NOT NULL,
  `company_phone` varchar(20) DEFAULT NULL,
  `consultant_name` varchar(200) DEFAULT NULL,
  `consultant_email` varchar(100) DEFAULT NULL,
  `consultant_phone` varchar(20) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_company_email` (`company_email`),
  KEY `fk_consultant_created_by` (`created_by`),
  CONSTRAINT `fk_consultant_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `consultants`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `consultants` WRITE;
/*!40000 ALTER TABLE `consultants` DISABLE KEYS */;
/*!40000 ALTER TABLE `consultants` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `document_types`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `standard` varchar(100) NOT NULL,
  `validity_period` int(11) NOT NULL COMMENT 'Yıl olarak',
  `interim_audit_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used_count` int(11) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `document_types_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `document_types`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `document_types` WRITE;
/*!40000 ALTER TABLE `document_types` DISABLE KEYS */;
INSERT INTO `document_types` VALUES
(40,'exwsx','wszwszwz',3,2,'2025-10-16 15:06:25','2025-10-16 15:08:47',1,NULL),
(41,'kjhgkhg','223r234',2,1,'2025-11-14 16:42:58','2025-11-14 16:43:20',1,NULL),
(42,'asdasdasdasdasdasda','1231232132132132',2,1,'2025-11-14 17:55:58','2025-11-14 17:56:06',1,NULL);
/*!40000 ALTER TABLE `document_types` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `expense_history`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `expense_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `certification_id` int(11) NOT NULL,
  `expense_type` enum('belge','danisman','egitim') NOT NULL,
  `old_amount` decimal(10,2) DEFAULT 0.00,
  `new_amount` decimal(10,2) DEFAULT 0.00,
  `old_paid` decimal(10,2) DEFAULT 0.00,
  `new_paid` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `certification_id` (`certification_id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `expense_history_certification_fk` FOREIGN KEY (`certification_id`) REFERENCES `certifications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `expense_history_user_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci COMMENT='Masraf değişiklik geçmişi';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expense_history`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `expense_history` WRITE;
/*!40000 ALTER TABLE `expense_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `expense_history` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `inspection_files`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `inspection_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `completed_inspection_id` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `original_file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `file_size` int(11) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL COMMENT 'Dosya MIME tipi',
  `file_content` longblob DEFAULT NULL COMMENT 'Dosya içeriği',
  `file_category` enum('report','photo','document','other') DEFAULT 'document',
  `description` varchar(500) DEFAULT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_inspection_files_inspection` (`completed_inspection_id`),
  KEY `idx_inspection_files_category` (`file_category`),
  KEY `fk_inspection_files_uploaded_by` (`uploaded_by`),
  CONSTRAINT `fk_inspection_files_inspection` FOREIGN KEY (`completed_inspection_id`) REFERENCES `completed_inspections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inspection_files_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci COMMENT='Tetkik dosyaları tablosu';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inspection_files`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `inspection_files` WRITE;
/*!40000 ALTER TABLE `inspection_files` DISABLE KEYS */;
/*!40000 ALTER TABLE `inspection_files` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `mail_history`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mail_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `certification_id` int(11) DEFAULT NULL,
  `template_id` int(11) DEFAULT NULL,
  `recipient_email` varchar(100) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('sent','failed') NOT NULL DEFAULT 'sent',
  `error_message` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `certification_id` (`certification_id`),
  KEY `template_id` (`template_id`),
  KEY `idx_mail_history_status` (`status`),
  KEY `idx_mail_history_sent_at` (`sent_at`),
  KEY `idx_mail_history_recipient` (`recipient_email`),
  CONSTRAINT `mail_history_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `mail_history_ibfk_2` FOREIGN KEY (`certification_id`) REFERENCES `certifications` (`id`),
  CONSTRAINT `mail_history_ibfk_3` FOREIGN KEY (`template_id`) REFERENCES `mail_templates` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mail_history`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `mail_history` WRITE;
/*!40000 ALTER TABLE `mail_history` DISABLE KEYS */;
/*!40000 ALTER TABLE `mail_history` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `mail_template_attachments`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mail_template_attachments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_id` int(11) NOT NULL,
  `file_path` varchar(500) DEFAULT NULL,
  `original_filename` varchar(255) NOT NULL,
  `file_size` int(11) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL COMMENT 'Dosya MIME tipi',
  `file_content` longblob DEFAULT NULL COMMENT 'Dosya içeriği',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_template_attachments_template_id` (`template_id`),
  CONSTRAINT `mail_template_attachments` FOREIGN KEY (`template_id`) REFERENCES `mail_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=251 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mail_template_attachments`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `mail_template_attachments` WRITE;
/*!40000 ALTER TABLE `mail_template_attachments` DISABLE KEYS */;
/*!40000 ALTER TABLE `mail_template_attachments` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `mail_templates`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `mail_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `subject` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=84 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mail_templates`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `mail_templates` WRITE;
/*!40000 ALTER TABLE `mail_templates` DISABLE KEYS */;
INSERT INTO `mail_templates` VALUES
(78,'dxd','xddxd','dxxd','2025-10-16 15:23:32','2025-10-16 15:23:32'),
(79,'dxdx','dxdxd','xdx','2025-10-16 15:23:36','2025-10-16 15:23:36'),
(80,'asdsad','sadsadsa','dsada','2025-11-19 20:07:05','2025-11-19 20:07:05'),
(81,'asdsad','sadsadsa','dsada','2025-11-19 20:07:39','2025-11-19 20:07:39'),
(82,'asdsad','sadsadsa','dsada','2025-11-19 20:07:42','2025-11-19 20:07:42'),
(83,'asdasdsadsad','sadsa','dsadsad','2025-11-19 20:07:56','2025-11-19 20:07:56');
/*!40000 ALTER TABLE `mail_templates` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `non_certified_inspections`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `non_certified_inspections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `consultant_id` int(11) DEFAULT NULL,
  `inspection_title` varchar(200) NOT NULL DEFAULT 'General Inspection',
  `inspection_description` text DEFAULT NULL,
  `inspection_scope` text DEFAULT NULL,
  `status` enum('active','inactive','suspended','cancelled','completed') NOT NULL DEFAULT 'active',
  `priority_level` enum('low','medium','high','urgent') DEFAULT 'medium',
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_non_certified_company` (`company_id`),
  KEY `idx_non_certified_consultant` (`consultant_id`),
  KEY `idx_non_certified_status` (`status`),
  KEY `idx_non_certified_created_by` (`created_by`),
  KEY `idx_non_certified_updated_by` (`updated_by`),
  CONSTRAINT `fk_non_certified_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_non_certified_consultant` FOREIGN KEY (`consultant_id`) REFERENCES `consultants` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_non_certified_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_non_certified_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Non-certified inspections table';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `non_certified_inspections`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `non_certified_inspections` WRITE;
/*!40000 ALTER TABLE `non_certified_inspections` DISABLE KEYS */;
/*!40000 ALTER TABLE `non_certified_inspections` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `plans`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_id` int(11) NOT NULL,
  `consultant_id` int(11) DEFAULT NULL,
  `document_type_id` int(11) DEFAULT NULL,
  `audit_start_date` date NOT NULL,
  `audit_end_date` date NOT NULL,
  `certification_id` int(11) DEFAULT NULL,
  `non_certified_inspection_id` int(11) DEFAULT NULL,
  `inspection_type` enum('certified','non_certified') NOT NULL DEFAULT 'certified',
  `completion_status` enum('pending','completed','cancelled') DEFAULT 'pending',
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `consultant_id` (`consultant_id`),
  KEY `document_type_id` (`document_type_id`),
  KEY `certification_id` (`certification_id`),
  KEY `idx_plans_non_certified` (`non_certified_inspection_id`),
  CONSTRAINT `fk_plans_non_certified` FOREIGN KEY (`non_certified_inspection_id`) REFERENCES `non_certified_inspections` (`id`) ON DELETE CASCADE,
  CONSTRAINT `plans_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `plans_ibfk_2` FOREIGN KEY (`consultant_id`) REFERENCES `consultants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `plans_ibfk_3` FOREIGN KEY (`document_type_id`) REFERENCES `document_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `plans_ibfk_4` FOREIGN KEY (`certification_id`) REFERENCES `certifications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plans`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `plans` WRITE;
/*!40000 ALTER TABLE `plans` DISABLE KEYS */;
/*!40000 ALTER TABLE `plans` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `report_document_files`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `report_document_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `certification_id` int(11) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `stored_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `file_content` longblob DEFAULT NULL COMMENT 'Dosya içeriği',
  `uploaded_by` int(11) DEFAULT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_certification_id` (`certification_id`),
  KEY `idx_deleted_at` (`deleted_at`),
  KEY `fk_report_files_user` (`uploaded_by`),
  CONSTRAINT `fk_report_files_cert` FOREIGN KEY (`certification_id`) REFERENCES `certifications` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_report_files_user` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `report_document_files`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `report_document_files` WRITE;
/*!40000 ALTER TABLE `report_document_files` DISABLE KEYS */;
/*!40000 ALTER TABLE `report_document_files` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `settings`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `settings`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `settings` WRITE;
/*!40000 ALTER TABLE `settings` DISABLE KEYS */;
INSERT INTO `settings` VALUES
(9,'smtp_host','127.0.0.1',NULL,'2026-08-07 23:14:24','2026-08-07 23:14:24'),
(10,'smtp_port','1025',NULL,'2026-08-07 23:14:24','2026-08-07 23:14:24'),
(11,'smtp_username','demo@demo.local',NULL,'2026-08-07 23:14:24','2026-08-07 23:14:24'),
(12,'smtp_password','demo',NULL,'2026-08-07 23:14:24','2026-08-07 23:14:24'),
(13,'smtp_encryption','none',NULL,'2026-08-07 23:14:24','2026-08-07 23:14:24'),
(14,'smtp_from_email','demo@demo.local',NULL,'2026-08-07 23:14:24','2026-08-07 23:14:24'),
(15,'smtp_from_name','Certby Demo',NULL,'2026-08-07 23:14:24','2026-08-07 23:14:24');
/*!40000 ALTER TABLE `settings` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `smtp_templates`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `smtp_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `template_name` varchar(100) NOT NULL COMMENT 'Şablon adı (örn: Gmail Kurumsal, Outlook 365)',
  `smtp_host` varchar(255) NOT NULL COMMENT 'SMTP sunucu adresi',
  `smtp_port` int(11) NOT NULL DEFAULT 587 COMMENT 'SMTP port numarası',
  `smtp_encryption` enum('tls','ssl') NOT NULL DEFAULT 'tls' COMMENT 'Şifreleme türü',
  `smtp_username` varchar(255) NOT NULL COMMENT 'SMTP kullanıcı adı (email)',
  `smtp_password` text NOT NULL COMMENT 'Şifrelenmiş uygulama şifresi',
  `from_name` varchar(255) NOT NULL COMMENT 'Gönderen isim',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1: Aktif, 0: Pasif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL COMMENT 'Oluşturan kullanıcı ID',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_template_name` (`template_name`),
  KEY `fk_smtp_template_created_by` (`created_by`),
  CONSTRAINT `fk_smtp_template_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci COMMENT='SMTP ayar şablonları tablosu';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `smtp_templates`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `smtp_templates` WRITE;
/*!40000 ALTER TABLE `smtp_templates` DISABLE KEYS */;
/*!40000 ALTER TABLE `smtp_templates` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `system_logs`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `log_type` enum('login','logout','create','update','delete','expense_update','expense_payment','export') NOT NULL DEFAULT 'create',
  `level` varchar(20) DEFAULT 'INFO',
  `content` text NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `context` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`context`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_system_logs_level` (`level`),
  KEY `idx_system_logs_created_at` (`created_at`),
  KEY `idx_system_logs_user_id` (`user_id`),
  CONSTRAINT `system_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_turkish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `system_logs`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `system_logs` WRITE;
/*!40000 ALTER TABLE `system_logs` DISABLE KEYS */;
/*!40000 ALTER TABLE `system_logs` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;

--
-- Table structure for table `users`
--

/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('operator','user','auditor','denetci','muhasebeci') NOT NULL DEFAULT 'user',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

SET @OLD_AUTOCOMMIT=@@AUTOCOMMIT, @@AUTOCOMMIT=0;
LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES
(1,'admin','Demo Admin','Demo','Admin','admin@demo.local','$2y$12$h510U/SOJpa2ThLi2fJEOe6Y6bFZrZNhfsyduvweUZI81W1zwqAwy','operator','active','2026-08-07 23:14:24','2026-08-07 23:14:24');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
COMMIT;
SET AUTOCOMMIT=@OLD_AUTOCOMMIT;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*M!100616 SET NOTE_VERBOSITY=@OLD_NOTE_VERBOSITY */;

-- Dump completed on 2026-08-08  2:14:24
