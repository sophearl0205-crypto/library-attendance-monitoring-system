# LAMAS — Library Attendance Monitoring and Analytics System

LAMAS is a PHP 8.0+ and MySQL/MariaDB application for recording and analyzing library visits by students and faculty. The project keeps its frontend libraries in `assets/vendor/` so the UI, charts, QR generation, and scanner do not depend on a CDN connection.

## Development setup (Windows / XAMPP)

1. Place the development checkout at `C:\xampp\htdocs\LAMAS-Development` and start Apache and MySQL.
2. Create a dedicated local development database. Do not point this checkout at production or at a database containing important records until you have backed it up and reviewed the migration plan.
3. Copy `config/local.example.php` to `config/local.php` and set the local database connection values and `base_url` to `/LAMAS-Development`. `config/local.php` is ignored by Git; never commit hosting credentials.
4. For a **brand-new empty development database only**, import `database/schema.sql` once, then visit `http://localhost/LAMAS-Development/setup.php` and create the first Super Admin.
5. For an existing LAMAS database with an account, sign in and open `http://localhost/LAMAS-Development/database/migrate.php`. Review the displayed upgrade, then choose **Apply pending migrations**. The upgrade adds demo markers and maps legacy `admin`/`librarian` roles to `super_admin`/`moderator`; it does not drop or recreate application tables. If an existing database has no users yet, run the migration from the XAMPP command prompt before opening setup:

   ```powershell
   C:\xampp\php\php.exe C:\xampp\htdocs\LAMAS-Development\database\migrate.php
   ```

   Then create the first Super Admin with `setup.php`.
6. Seed historical sample attendance in the local development database from a command prompt:

   ```powershell
   C:\xampp\php\php.exe C:\xampp\htdocs\LAMAS-Development\database\seed_demo_data.php
   ```

   Run the same script with `--remove` to remove marked demo visits and demo-only people. Do not run it against production.

## Roles

- **Super Admin:** full application access, including creating, editing, changing roles, and removing users.
- **Moderator:** access to application modules and read-only access to the user list. User changes are rejected server-side.
- Accounts linked to attendance or audit history are deactivated instead of hard-deleted, preserving historical references. The last active Super Admin cannot be removed or demoted.

## Modules

- Dashboard with attendance totals and recent visits.
- Attendance entry with live name/ID search and a camera scanner that starts only after the user presses **Open Scanner**.
- Students and faculty registration, search, department assignment, and QR code renewal.
- User management with role-based authorization.
- Monthly analytics with year and multi-month filters, custom date ranges, all-time export, CSV, and print-to-PDF layout.
- Demo attendance is identified by `is_demo` and labeled in attendance/report views and CSV exports.

## Offline behavior and camera access

Bootstrap, Chart.js, QRCode.js, and html5-qrcode are served locally from `assets/vendor/`. Run the PHP and MySQL server locally for offline use. Browser camera access generally requires localhost or HTTPS; access over a plain-HTTP LAN address may not permit camera use.

## Database safety

`database/schema.sql` is for a fresh empty database only. Existing installations must use the additive migration runner. Migrations are recorded in `lamas_schema_migrations` and guard their schema changes so interrupted runs can be retried. Back up the database before applying changes. Demo rows are marked and can be removed with the seeder's `--remove` option.

## Hosting notes

The code uses PDO MySQL and MySQL/MariaDB SQL and is intended for XAMPP and compatible PHP/MySQL shared hosting. The migration page can be run by a Super Admin on hosts where CLI access is unavailable. Keep `config/local.php` out of source control and use HTTPS for hosted access.
