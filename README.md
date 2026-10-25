HOTEL MANAGEMENT SYSTEM (TEAM MEMBER 2)

This is a QloApps-inspired hotel management system module built using:

- PHP (with MySQLi)
- Bootstrap 5
- Modern CSS (Qlo-style)
- Fully responsive design

DEVELOPED FOR

Team Member 2
- Check-In & Check-Out
- Billing and Add-On Services
- Logs, Role Access, Cron Scripts

FOLDER STRUCTURE OVERVIEW

```text
├── checkin/       Guest check-in interface
├── checkout/      Guest checkout, express checkout
├── billing/       Billing modules and utils
├── includes/      Shared header, footer, auth, db
├── assets/        CSS, JS, images, fonts
├── dashboard/     Clerk dashboard
├── cronjobs/      Scheduled tasks
├── reservations/  Auto cancel / no-show handlers
├── logs/          Clerk action logs
├── middleware/    Hidden rule enforcement
├── auth/          Login/logout
├── database/      schema.sql
├── lang/          Language support
├── index.php      Role-based dashboard redirect
└── README.md
```

SETUP GUIDE

REQUIREMENTS

- PHP 8.x
- MySQL/MariaDB
- Apache (XAMPP, WAMP, MAMP)
- Composer (optional, not required)

INSTALLATION STEPS

1. Clone or download the repository and extract it into your `htdocs/` directory if using XAMPP.
2. Import `/database/schema.sql` into a MySQL database named `hotel_management`.
3. Configure `/includes/db.php` with your database credentials.
4. Add a test user to the `users` table:

```sql
INSERT INTO users (username, password, role) VALUES (
  'clerk1',
  '$2y$10$Y3yJSExDpFk7BXfEIT7SMe3nQU0nFgq7sR2UulbIuHbQ4m/3JKJOy',
  'clerk'
);
```

The example password for this test account is `12345`. Use this account for testing only.

PUBLIC REPOSITORY / DEPLOYMENT NOTES

ENVIRONMENT VARIABLES

The application reads optional environment variables, falling back to local configuration files and safe defaults.

Database:
- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

See `db.local.php.example`.

Mail (OTP):
- `SMTP_HOST`
- `SMTP_PORT`
- `SMTP_USER`
- `SMTP_PASS`
- `SMTP_FROM`
- `SMTP_FROM_NAME`
- `SMTP_SECURE`

See `mail.local.php.example`.

Application:
- `APP_DEBUG` — Set to `1` to display PHP errors. The default is `0`.
- `BASE_URL` — The deployment URL.

PHPMAILER DEPENDENCY (REQUIRED FOR OTP EMAIL)

`verifycode.php` requires PHPMailer, which is not bundled in this repository.

Install it before using email verification.

Option 1: Manual installation

Download PHPMailer and place it so that `PHPMailer/src/PHPMailer.php` exists in the project root.

Option 2: Composer installation

Run:

```bash
composer require phpmailer/phpmailer
```

Then adjust the `require` paths in `verifycode.php` as needed.

Until PHPMailer is installed, the resend-OTP feature displays a friendly error instead of failing.

PRODUCTION SETTINGS

- Keep `APP_DEBUG=0` in production to avoid exposing error details.
- Set `BASE_URL` to your deployed URL.
- Do not commit `db.local.php` or `mail.local.php`; both files are ignored by Git.

COPYRIGHT AND USAGE

Copyright (c) Luxora Hotel Suite contributors. All rights reserved.

No license is granted for reuse, modification, or redistribution without explicit permission from the owner. Public visibility of this repository does not imply any license.
