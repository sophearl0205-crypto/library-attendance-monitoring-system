# LAMAS — Library Attendance Monitoring and Analytics System

A PHP 8+ / MySQL web application for recording library visits by students and faculty, with manual entry, QR scanning, department analytics and reports.

## Requirements
- XAMPP with PHP 8.1+ and MySQL/MariaDB
- Modern browser
- Internet connection for CDN assets and QR scanner library (can be vendored for offline use later)

## Installation
1. Copy this repository into `C:\\xampp\\htdocs\\lamas`.
2. Start Apache and MySQL in XAMPP.
3. Open phpMyAdmin and import `database/schema.sql`.
4. Edit `config/config.php` if your MySQL credentials differ.
5. Visit `http://localhost/lamas/setup.php` and create the first administrator.
6. Delete or restrict `setup.php` after initial setup.
7. Sign in at `http://localhost/lamas/login.php`.

## Initial scope
- Admin and librarian roles
- Department and person records (student/faculty)
- Manual attendance and QR token lookup
- Attendance history and department analytics
- Audit trail foundation

## Security notes
Use only synthetic/demo data during development. Serve over HTTPS when deployed. QR codes contain opaque random tokens, not personal information. Passwords are stored using PHP password hashing.

## Status
Initial project foundation. Test locally before using with real library records.
