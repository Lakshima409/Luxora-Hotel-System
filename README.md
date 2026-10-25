# 🏨 Hotel Management System (Team Member 2)

This is a QloApps-inspired hotel management system module built using:

- ✅ PHP (with MySQLi)
- ✅ Bootstrap 5
- ✅ Modern CSS (Qlo-style)
- ✅ Fully responsive design

## 👥 Developed for:
- **Team Member 2**
  - Check-In & Check-Out
  - Billing and Add-On Services
  - Logs, Role Access, Cron Scripts

---

📂 Folder Structure Overview

├── checkin/ Guest check-in interface
├── checkout/ Guest checkout, express checkout
├── billing/ Billing modules and utils
├── includes/  Shared header, footer, auth, db
├── assets/ CSS, JS, images, fonts
├── dashboard/ Clerk dashboard
├── cronjobs/ Scheduled tasks
├── reservations/ Auto cancel / no-show handlers
├── logs/ Clerk action logs
├── middleware/ Hidden rule enforcement
├── auth/ login/logout
├── database/ schema.sql
├── lang/ Language support
├── index.php Role-based dashboard redirect
└── README.md
---

## ⚙️ Setup Guide

### ✅ Requirements

- PHP 8.x
- MySQL/MariaDB
- Apache (XAMPP, WAMP, MAMP)
- Composer (optional, not required)

### 🛠️ Installation Steps

1. Clone/download the repo and extract to your `htdocs/` (XAMPP)
2. Import `/database/schema.sql` into a MySQL DB named `hotel_management`
3. Configure `/includes/db.php` with your DB credentials
4. Add a test user to `users` table:

```sql
INSERT INTO users (username, password, role) VALUES (
  'clerk1',
  '$2y$10$Y3yJSExDpFk7BXfEIT7SMe3nQU0nFgq7sR2UulbIuHbQ4m/3JKJOy', -- password: 12345
  'clerk'
);

---

## 🔒 Public repository / deployment notes

### Environment variables
The app reads optional environment variables (falling back to local config files and safe defaults):

- Database: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS` (see `db.local.php.example`)
- Mail (OTP): `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_FROM`, `SMTP_FROM_NAME`, `SMTP_SECURE` (see `mail.local.php.example`)
- App: `APP_DEBUG` (set to `1` to show PHP errors; default `0`), `BASE_URL` (deployment URL)

### PHPMailer dependency (required for OTP email)
`verifycode.php` requires PHPMailer, which is **not** bundled in this repository.
Install it before using email verification:

1. **Manual:** download PHPMailer and place it so that `PHPMailer/src/PHPMailer.php` exists in the project root, OR
2. **Composer:** `composer require phpmailer/phpmailer` and adjust the `require` paths in `verifycode.php`.

Until PHPMailer is installed, the resend-OTP feature shows a friendly error instead of failing.

### Production settings
- Keep `APP_DEBUG=0` (default) in production to avoid leaking errors.
- Set `BASE_URL` to your deployed URL.
- Do not commit `db.local.php` or `mail.local.php` (both are git-ignored).

### Copyright & usage
Copyright (c) Luxora Hotel Suite contributors. All rights reserved.
No license is granted for reuse, modification, or redistribution without explicit permission from the owner. Public visibility of this repository does not imply any license.
