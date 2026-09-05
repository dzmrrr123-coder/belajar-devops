-- Learn Tracker Database Dump & Seeds
CREATE DATABASE IF NOT EXISTS `learn-tracker` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `learn-tracker`;

-- Table structure for `users`
CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(100) NOT NULL UNIQUE,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `xp` INT NOT NULL DEFAULT 0,
    `streak` INT NOT NULL DEFAULT 0,
    `last_active_date` DATE NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `quests`
CREATE TABLE IF NOT EXISTS `quests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `week` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `xp_reward` INT NOT NULL DEFAULT 10,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `user_quests`
CREATE TABLE IF NOT EXISTS `user_quests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `quest_id` INT NOT NULL,
    `completed_at` DATE NOT NULL DEFAULT (CURRENT_DATE),
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `uq_user_quest` UNIQUE (`user_id`, `quest_id`),
    CONSTRAINT `fk_user_quests_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_user_quests_quest` FOREIGN KEY (`quest_id`) REFERENCES `quests`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `errors`
CREATE TABLE IF NOT EXISTS `errors` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `category` VARCHAR(50) NOT NULL DEFAULT 'General',
    `error_message` TEXT NOT NULL,
    `solution` TEXT NULL,
    `reference_link` VARCHAR(500) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_errors_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `resources`
CREATE TABLE IF NOT EXISTS `resources` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `week` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `url` VARCHAR(500) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table structure for `pomodoro_sessions`
CREATE TABLE IF NOT EXISTS `pomodoro_sessions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `duration_minutes` INT NOT NULL DEFAULT 25,
    `completed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_pomodoro_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `questions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `quest_id` INT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `topic` VARCHAR(100) NULL,
    `status` ENUM('open', 'in_review', 'answered', 'archived') NOT NULL DEFAULT 'open',
    `priority` ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
    `answer` TEXT NULL,
    `reference_link` VARCHAR(500) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `answered_at` DATETIME NULL,
    CONSTRAINT `fk_questions_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_questions_quest` FOREIGN KEY (`quest_id`) REFERENCES `quests`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quests Seed
INSERT INTO `quests` (`id`, `week`, `title`, `description`, `xp_reward`) VALUES
(1, 1, 'Bikin Database Tokoonline', 'Rancang skema database MySQL relational untuk platform toko online lengkap dengan tabel users, products, categories, orders, dan order_items.', 15),
(2, 1, 'Jauhkan Foreign Key & Relasi Kuat', 'Terapkan foreign key constraint, index, referential integrity (ON DELETE CASCADE/RESTRICT) pada tabel-tabel berelasi.', 10),
(3, 2, 'CRUD Produk PHP Native', 'Bangun fitur Create, Read, Update, Delete produk menggunakan PHP native dengan query prepared statement MySQLi demi keamanan SQL Injection.', 20),
(4, 2, 'Login & Auth System Terproteksi', 'Implementasikan alur autentikasi user dengan hashing password password_hash() BCRYPT, validasi sesi login, dan proteksi halaman.', 15),
(5, 3, 'Refactor Kode ke OOP PHP', 'Ubah kode PHP prosedural menjadi Object Oriented Programming dengan Class, Property, Method, Constructor, dan Encapsulation (Private/Public/Protected).', 25),
(6, 4, 'Polimorfisme & Design Pattern', 'Terapkan konsep Inheritance, Interface, dan Abstract Class pada arsitektur model/service (misal payment gateway driver dummy).', 20),
(7, 5, 'Migrasi ke Laravel Framework', 'Setup proyek Laravel 11, konfigurasi environment .env, buat database migrations, seeder data palsu, dan model Eloquent.', 30),
(8, 6, 'Auth Laravel & Custom Middleware', 'Implementasi sistem authentication Laravel Breeze/Fortify serta pasang custom Middleware untuk proteksi role dan route guard.', 25),
(9, 7, 'Dockerize App (PHP, Nginx, MySQL)', 'Tulis Dockerfile multi-stage untuk PHP-FPM dan docker-compose.yml untuk orkestrasi Nginx webserver, PHP service, dan MySQL database.', 30),
(10, 8, 'Build & Push ke Docker Hub', 'Bangun Docker image production yang efisien, tagging dengan semantic versioning (v1.0.0), dan push ke publik Docker Hub repository.', 20),
(11, 9, 'Deploy ke AWS EC2 Linux VPS', 'Provisioning VPS Ubuntu di AWS EC2, konfigurasi inbound Security Group, koneksi SSH, install Docker Engine & jalankan container.', 40),
(12, 10, 'Live URL, Domain & SSL Certbot', 'Setup reverse proxy Nginx pada server publik, konfigurasi DNS record domain/subdomain, dan terapkan SSL HTTPS Let\'s Encrypt Certbot gratis.', 30),
(13, 11, 'Rapikan Portfolio GitHub & Readme', 'Sempurnakan dokumentasi README.md proyek di GitHub dengan diagram arsitektur Mermaid, screenshot fitur, dan panduan instalasi profesional.', 35),
(14, 12, 'Review Akhir, Portofolio & CV DevOps', 'Lakukan final security and performance audit, susun CV ATS-friendly dengan showcase portofolio DevOps, dan latihan mock interview PKL.', 25)
ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `description` = VALUES(`description`), `xp_reward` = VALUES(`xp_reward`);

-- Resources Seed
INSERT INTO `resources` (`week`, `title`, `type`, `url`) VALUES
(1, 'Tutorial Desain Relasional Database MySQL', 'video', 'https://www.youtube.com/results?search_query=desain+database+relasional+mysql'),
(1, 'MySQL Official Documentation - Foreign Keys & Constraints', 'dokumentasi', 'https://dev.mysql.com/doc/refman/8.0/en/create-table-foreign-keys.html'),
(1, 'SQLBolt - Latihan Interaktif Perintah SQL', 'praktek', 'https://sqlbolt.com/'),
(2, 'PHP Native CRUD & Prepared Statements Security Guide', 'video', 'https://www.youtube.com/results?search_query=php+pdo+mysqli+prepared+statement'),
(2, 'PHP Manual - Hashing Password yang Benar', 'dokumentasi', 'https://www.php.net/manual/en/function.password-hash.php'),
(2, 'OWASP PHP Security Cheat Sheet', 'praktek', 'https://cheatsheetseries.owasp.org/cheatsheets/PHP_Configuration_Cheat_Sheet.html'),
(3, 'Dasar-Dasar Object Oriented Programming (OOP) PHP', 'video', 'https://www.youtube.com/results?search_query=php+oop+dasar'),
(3, 'PHP: The Right Way - Object-Oriented Programming', 'dokumentasi', 'https://phptherightway.com/#object-oriented_programming'),
(3, 'Latihan Refactoring Prosedural ke OOP', 'praktek', 'https://refactoring.guru/design-patterns/php'),
(4, 'Interface, Abstract Class & Polimorfisme di PHP', 'video', 'https://www.youtube.com/results?search_query=php+interface+abstract+class'),
(4, 'Prinsip SOLID dalam PHP Secara Sederhana', 'dokumentasi', 'https://www.freecodecamp.org/news/solid-principles-explained-in-plain-english/'),
(4, 'Studi Kasus: Membuat Payment Gateway Interface Dummy', 'praktek', 'https://github.com/kamranahmedse/design-patterns-for-humans'),
(5, 'Laravel 11 Crash Course untuk Pemula', 'video', 'https://www.youtube.com/results?search_query=laravel+11+tutorial+indonesia'),
(5, 'Dokumentasi Resmi Laravel - Migrations & Eloquent', 'dokumentasi', 'https://laravel.com/docs/11.x/migrations'),
(5, 'Latihan REST API Sederhana dengan Laravel', 'praktek', 'https://laravel.com/docs/11.x/eloquent-resources'),
(6, 'Sistem Autentikasi Laravel Breeze & Middleware Guard', 'video', 'https://www.youtube.com/results?search_query=laravel+breeze+middleware+authentication'),
(6, 'Dokumentasi Laravel - Routing & Middleware Guide', 'dokumentasi', 'https://laravel.com/docs/11.x/middleware'),
(6, 'Implementasi Multi-Role & Permissions', 'praktek', 'https://spatie.be/docs/laravel-permission/v6/introduction'),
(7, 'Docker Dasar untuk Pemula (Container & Image)', 'video', 'https://www.youtube.com/results?search_query=docker+dasar+indonesia'),
(7, 'Best Practices Penulisan Dockerfile', 'dokumentasi', 'https://docs.docker.com/develop/develop-images/dockerfile_best-practices/'),
(7, 'Docker Compose Multi-Container (Nginx + PHP + MySQL)', 'praktek', 'https://docs.docker.com/compose/gettingstarted/'),
(8, 'Build Image Production & Push ke Docker Hub', 'video', 'https://www.youtube.com/results?search_query=docker+push+dockerhub+tutorial'),
(8, 'Docker Hub Official Guide - Managing Repositories', 'dokumentasi', 'https://docs.docker.com/docker-hub/repos/'),
(8, 'Optimasi Ukuran Docker Image dengan Multi-Stage Build', 'praktek', 'https://docs.docker.com/build/building/multi-stage/'),
(9, 'Tutorial Deploy ke AWS EC2 Linux dari Nol', 'video', 'https://www.youtube.com/results?search_query=deploy+aws+ec2+docker+indonesia'),
(9, 'AWS EC2 Documentation - Getting Started with Linux', 'dokumentasi', 'https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/EC2_GetStarted.html'),
(9, 'Setup SSH Key, Firewall & Docker di Ubuntu Server', 'praktek', 'https://ubuntu.com/tutorials/install-and-configure-docker'),
(10, 'Konfigurasi Nginx Reverse Proxy & SSL Let\'s Encrypt', 'video', 'https://www.youtube.com/results?search_query=nginx+reverse+proxy+ssl+certbot'),
(10, 'Certbot Let\'s Encrypt Documentation for Nginx', 'dokumentasi', 'https://certbot.eff.org/instructions?ws=nginx&os=ubuntufocal'),
(10, 'Cloudflare DNS Setup & Strict HTTPS Encryption', 'praktek', 'https://developers.cloudflare.com/dns/'),
(11, 'Cara Membuat Portofolio GitHub DevOps yang Menarik', 'video', 'https://www.youtube.com/results?search_query=cara+membuat+portfolio+github+devops'),
(11, 'Make a README - Standard Markdown Guide', 'dokumentasi', 'https://www.makeareadme.com/'),
(11, 'Mermaid.js - Diagram Arsitektur Langsung di Markdown', 'praktek', 'https://mermaid.js.org/syntax/flowchart.html'),
(12, 'Persiapan Wawancara PKL & CV ATS Friendly untuk DevOps', 'video', 'https://www.youtube.com/results?search_query=cv+ats+friendly+programmer+devops'),
(12, 'DevOps Roadmap 2026 - Checklist Kompetensi Industri', 'dokumentasi', 'https://roadmap.sh/devops'),
(12, 'Simulasi Mock Interview & Troubleshooting Linux/Docker', 'praktek', 'https://sadservers.com/');
