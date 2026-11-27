# Atok MDRRM System

WEBSYS1 rebuild for the Municipal Disaster Risk Reduction and Management Office of Atok, Benguet. The eight modules cover households, incidents, response, evacuation, relief inventory, distribution, announcements and disaster summaries.

## Course foundation

Use module folders and direct CRUD pages modeled on the laundry and QuickFix labs, PHP sessions for named staff and resident accounts, and one PDO/MySQL connection with prepared statements. Use ordinary forms, four-space indentation and simple classes where they clarify an operation. Keep map selection, weather and report exports separate from core CRUD.

## Interface

Use a text municipal masthead, square-cornered controls, Bootstrap tables and responsive grids, one shared stylesheet and small scripts for interactive maps. Use approved office branding when available.

The first implementation milestone is login, household CRUD and staff-led resident enrollment.

## Run Phase 1 locally

1. Put this repository in your web server's htdocs folder under the name `atok-mdrrm-system`. Start Apache and MySQL. Enable PHP PDO and pdo_mysql.
2. Import `database/foundation.sql` in phpMyAdmin with an account allowed to create databases. This creates a separate empty rebuild database. User tables arrive in Phase 2.
3. Check `config.php`. To override settings on your machine, create an ignored `config.local.php` beside it. For default MAMP installations the contents are:

   ```php
   <?php
   return [
       'port' => 8889,
       'password' => 'root',
   ];
   ```

   Use your actual credentials. XAMPP commonly uses port 3306. If the folder name differs, override `base_url` with its URL path.
4. Open http://localhost:8888/atok-mdrrm-system/ on MAMP or http://localhost/atok-mdrrm-system/ on XAMPP. Confirm **Database connected**. On a narrow screen open Menu and select Service status. Use Tab to check the keyboard focus outline.

## Explain the shared files

- `index.php` checks the selected database with SELECT 1 and displays a controlled message on failure.
- `config.php` reads local settings and opens one reusable PDO connection per request.
- `includes/session.php` starts the session before output. Future protected pages call requireRole('staff') or requireRole('resident') before handling a request.
- `includes/header.php` and `includes/footer.php` supply navigation and load the bundled Bootstrap assets.
- `assets/css/site.css` sets the municipal colors, square controls and keyboard focus. Bootstrap handles the responsive grid and menu.
