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
3. For a fresh database, import the files in database/ in this order: foundation.sql, households.sql, incidents.sql, response.sql, evacuation.sql, relief_inventory.sql, relief_distribution.sql, announcements.sql, then map.sql. On an existing installation, apply only the missing stages.
4. In a private terminal run `php database/initialize_staff.php your-office-email`. Give the one-time displayed password to the staff member privately.
5. Visit the project root and change the temporary password. Staff can create other staff accounts, change account status, and issue new temporary passwords after identity verification. Staff enroll residents from household member details.

The login page verifies the saved password hash, renews the session ID and redirects by role. Shared session checks read the current account on each request. Password resets and status changes invalidate earlier sessions. The staff management page locks staff records during changes so at least one staff account remains active.

## Household registry

After importing foundation.sql, import database/households.sql in the rebuild database. Staff open Households to search by member name, barangay or written address. Add a household, add its members, then select an active member as representative. To replace the representative, select another member in the same household. Enrollment verifies residency and issues one resident login per member; hand over the temporary password privately. The sign-in page has no navigation bar.

## Incident reporting

Run database/incidents.sql once after households.sql. Enrollment can now record the resident’s own mobile number. Older accounts can enter a contact number when reporting. Report-specific changes stay with the report. Staff use phone or walk-in intake. Each report receives a reference and a submission timestamp.

The optional image is validated using PHP fileinfo and image metadata. One JPEG, PNG or WebP of up to 2 MiB is stored privately in MySQL with its incident in one transaction. The report, image and print-format page share the same staff/owner permission check. Set upload_max_filesize to at least 2M and post_max_size above that limit.

## Emergency response

Import database/response.sql after incidents.sql. Staff open Response teams on the dashboard to register a team, then review a pending incident on its detail page. Verify and assign dispatches the selected team immediately. Progress, cancellation, resumption and completion appear in dated staff history. Residents see the overall status when they refresh. Previously assigned teams can be deactivated after outstanding work ends; their history prevents permanent deletion.

## Evacuation management

Import database/evacuation.sql after households.sql. From the staff dashboard open Evacuation centers, add a center and select Manage stays. Household arrivals are saved as one transaction; checkout ends an individual stay. Search returning walk-ins by name before registering a new person. Occupancy is the number of stays without a checkout time. Closed centers accept no arrivals. History protects previously used centers from deletion.

## Municipal relief inventory

Import database/relief_inventory.sql after foundation.sql. Open Relief inventory from the staff dashboard. Add a good with a unit and minimum quantity, then use Record or find a stock movement for deliveries and issues. The balance and history entry are saved together. The encoder comes from the signed-in staff account. Zero stock displays Out of stock; a positive balance at or below the minimum displays Low stock.

## Page organization

Keep request handling, prepared SQL and the form for a task together. The household form handles both creation and editing, with its validation and SQL above the fields. Share incident ownership checks because details, images, and report copies need identical permissions. Team maintenance stays on one page; evacuation lists and stays, and inventory goods and movements, have distinct pages. All modules use the same session checks, header and footer.

## Household relief distribution

Import database/relief_distribution.sql after households.sql and relief_inventory.sql. Open Relief distribution on the staff dashboard, register a relief point, select an affected household, enter the actual receiver and choose quantities. One POST saves the distribution, receipt lines and municipal stock movements together. History opens the receipt for each issue.

## Announcements

For an existing installation, import database/announcements.sql before updating the website. From the staff dashboard choose Manage announcements. Save a draft or publish a notice to residents. The optional login-page checkbox starts unchecked. The login page shows the three most recently published public notices below sign-in. Withdrawing a notice removes it from both audiences and clears public display for its next publication.

## Dashboards, disaster summaries and maps

Import database/map.sql once after all module schemas, before opening the updated site. Open Manage relief points to edit an existing point or mark it inactive. Existing records keep their written locations and start without pins. Household details now include deletion confirmation. Incident details also serve their print copy; image access is a separate binary response.

The staff dashboard starts with five pending reports in oldest-first order. Barangay selection applies to incidents, centers and affected households. Goods needing attention always use the municipal stock balance. The disaster summary dates apply to incident reporting, evacuation arrivals and goods issued; household registry status and people staying now describe the current records.

The shared picker supports written-only locations and optional pin selection. The bundled approximate outline comes from the previous project's Atok extract (license in assets/data). Confirm it with the municipality before operational use. Current location requires localhost or HTTPS and browser permission. Tiles and address suggestions use external services; a written address works offline when the local site is available. The community map includes located verified/responding/resolved incidents, open centers and active distribution points. Household pins are staff-only.
