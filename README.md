# Atok MDRRM System

WEBSYS1 rebuild for the Municipal Disaster Risk Reduction and Management Office of Atok, Benguet. The eight modules cover households, incidents, response, evacuation, relief inventory, distribution, announcements and disaster summaries.

## Course foundation

Use module folders and direct CRUD pages modeled on the laundry and QuickFix labs, PHP sessions for named staff and resident accounts, and one PDO/MySQL connection with prepared statements. Use ordinary forms, four-space indentation and simple classes where they clarify an operation. Keep map selection, weather and report exports separate from core CRUD.

## Interface

Use a text municipal masthead, square-cornered controls, Bootstrap tables and responsive grids, one shared stylesheet and small scripts for interactive maps. Use approved office branding when available.

The first implementation milestone is login, household CRUD and staff-led resident enrollment.

## Local setup

1. Place the folder in htdocs and start Apache and MySQL.
2. Set host, port, username, password and base URL directly in config.php for your machine. Keep private settings local.
3. Choose **one** full import in phpMyAdmin on a fresh database. Use `database/website.sql` for an empty installation or `database/presentation.sql` for the fictional classroom installation. Each file creates its own database and all tables. The older module scripts document incremental upgrades.
4. Set `$database` in config.php to `atok_mdrrm_rebuild` or `atok_mdrrm_classroom` to match. Typical MySQL ports are 3306 on XAMPP and 8889 on MAMP. Set `$baseUrl` to the project folder with a leading slash. Use PHP 8.2+ with PDO MySQL, mbstring, fileinfo and iconv.
5. For the empty installation run `php database/initialize_staff.php your-office-email` privately. For the classroom installation use the two fictional logins in the opening comments of presentation.sql. Keep classroom credentials and SQL files outside the published web directory for an operational site.
6. Open the project in your browser (for standard MAMP, http://localhost:8888/atok-mdrrm-system/). The presentation, timed speaker notes, code walkthrough and acceptance record are in [submission](submission/). Both dashboards include the forecast and advisory link. Choose Print on incident details or Disaster summaries to open an A4 document in the PDF viewer. Use the viewer’s printer control or save the PDF. The summary also offers a CSV download.

The login page verifies the saved password hash, renews the session ID and redirects by role. Shared session checks read the current account on each request. Password resets and status changes invalidate earlier sessions. The staff management page locks staff records during changes so at least one staff account remains active.

## Upgrading an existing installation

For an installation already at the weather and PDF release (f4cfc88), back up and select its database in phpMyAdmin, then import only `database/upgrade-review.sql` once. Fresh installations already contain these fields. Older distributions keep an unconfirmed barangay until verified from original records.

## Household registry

Staff open Households to search by member name, barangay or written address. Add a household, add its members, then select an active member as representative. To replace the representative, select another member in the same household. Enrollment verifies residency and issues one resident login per member; hand over the temporary password privately. The sign-in page has no navigation bar.

## Incident reporting

Enrollment can now record the resident’s own mobile number. Older accounts can enter a contact number when reporting. Report-specific changes stay with the report. Staff use phone or walk-in intake. Each report receives a reference and a submission timestamp.

The optional image is validated using PHP fileinfo and image metadata. One JPEG, PNG or WebP of up to 2 MiB is stored privately in MySQL with its incident in one transaction. The report, image and print-format page share the same staff/owner permission check. Set upload_max_filesize to at least 2M and post_max_size above that limit.

## Emergency response

Staff open Response teams on the dashboard to register a team, then review a pending incident on its detail page. Verify and assign dispatches the selected team immediately. Progress, cancellation, resumption and completion appear in dated staff history. Residents see the overall status when they refresh. Previously assigned teams can be deactivated after outstanding work ends; their history prevents permanent deletion.

## Evacuation management

From the staff dashboard open Evacuation centers, add a center and select Manage stays. Household arrivals are saved as one transaction; checkout ends an individual stay. Search returning walk-ins by name before registering a new person. Occupancy is the number of stays without a checkout time. Closed centers accept no arrivals. History protects previously used centers from deletion.

## Municipal relief inventory

Open Relief inventory from the staff dashboard. Add a good with a unit and minimum quantity, then use Record or find a stock movement for deliveries and issues. The balance and history entry are saved together. The encoder comes from the signed-in staff account. Zero stock displays Out of stock; a positive balance at or below the minimum displays Low stock.

## Page organization

Keep request handling, prepared SQL and the form for a task together. The household form handles both creation and editing, with its validation and SQL above the fields. Share incident ownership checks because details, images, and report copies need identical permissions. Team maintenance stays on one page; evacuation lists and stays, and inventory goods and movements, have distinct pages. All modules use the same session checks, header and footer.

## Household relief distribution

Open Relief distribution on the staff dashboard, register a relief point, select an affected household, enter the actual receiver and choose quantities. One POST saves the distribution, receipt lines and municipal stock movements together. History opens the receipt for each issue.

## Announcements

From the staff dashboard choose Manage announcements. Save a draft or publish a notice to residents. The optional login-page checkbox starts unchecked. The login page shows the three most recently published public notices below sign-in. Withdrawing a notice removes it from both audiences and clears public display for its next publication.

## Dashboards, disaster summaries and maps

Open Manage relief points to edit an existing point or mark it inactive. Existing records keep their written locations and start without pins. Household details now include deletion confirmation. Incident details also serve their print copy; image access is a separate binary response.

The staff dashboard starts with five pending reports in oldest-first order. Barangay selection applies to incidents, centers and affected households. Goods needing attention always use the municipal stock balance. The disaster summary dates apply to incident reporting, evacuation arrivals and goods issued; household registry status and people staying now describe the current records.

The shared picker supports written-only locations and optional pin selection. The bundled approximate outline comes from the previous project's Atok extract (license in assets/data). Confirm it with the municipality before operational use. Current location requires localhost or HTTPS and browser permission. Tiles and address suggestions use external services; a written address works offline when the local site is available. The community map includes located verified/responding/resolved incidents, open centers and active distribution points. Household pins are staff-only.
