# LAMAS — Library Attendance Monitoring and Analytics System

A simple PHP 8+ and MySQL system for recording library visits by students and faculty.

## Main modules
1. **Dashboard** — today's visits, total visits, registered people, department totals and recent activity.
2. **Attendance** — manual attendance and QR-code attendance with a 30-second duplicate-scan guard.
3. **Students & Faculty** — register, search, edit, activate/deactivate people, assign departments and generate/renew QR codes.
4. **Reports & Analytics** — date-range and department filters, student/faculty totals, department charts, printable report and CSV export.

## Requirements
- Windows with XAMPP (PHP 8.1+ and MySQL/MariaDB)
- Modern browser
- Internet connection for Bootstrap, Chart.js, QRCode.js and html5-qrcode CDN assets

## Installation
1. Clone or copy this repository into `C:\xampp\htdocs\lamas`.
2. Start Apache and MySQL in XAMPP.
3. Open phpMyAdmin and import `database/schema.sql`.
4. Check `config/config.php`. Default XAMPP settings are database `library_attendance_db`, user `root`, and blank password. Update `BASE_URL` if the folder name is different.
5. Open `http://localhost/lamas/setup.php` and create the first administrator (password must be at least 10 characters).
6. **Delete `setup.php` after creating the administrator.**
7. Sign in at `http://localhost/lamas/login.php`.

## QR code workflow
- Register a person to generate a QR code.
- Save or print the QR code when it is shown. The raw token is shown only once.
- The QR scanner records the visit when the librarian submits the scanned token.
- If a QR code is lost, use the **QR** action to revoke the old token and generate a replacement.

## Notes
- The application records visits; it does not manage books, borrowing, or returns.
- Departments are seeded from the initial project assumptions. Confirm the department list with the client before using real data.
- Use synthetic/demo data during development. Back up the database regularly.
- QR camera access generally requires localhost or HTTPS. CDN libraries require an internet connection.
- CSV export is available from Reports & Analytics. Use the browser's Print function to save a report as PDF.
- The database schema is unchanged by the added application pages. Import it once for a fresh installation; do not re-import over an existing database with records.

## Project status
Core MVP pages are implemented. Test the complete workflow locally with sample data before presenting or using the system in production.