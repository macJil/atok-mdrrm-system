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
3. Import database/foundation.sql. Existing Phase 1 databases can run the same script to add the accounts table.
4. In a private terminal run `php database/initialize_staff.php your-office-email`. Give the one-time displayed password to the staff member privately.
5. Visit the project root and change the temporary password. Staff can create other staff accounts, change account status, and issue new temporary passwords after identity verification. Resident enrollment from households comes in Phase 3.

The login page verifies the saved password hash, renews the session ID and redirects by role. Shared session checks read the current account on each request. Password resets and status changes invalidate earlier sessions. The staff management page locks staff records during changes so at least one staff account remains active.

## Household registry

After importing foundation.sql, import database/households.sql in the rebuild database. Staff open Households to search by member name, barangay or written address. Add a household, add its members, then select an active member as representative. To replace the representative, select another member in the same household. Enrollment verifies residency and issues one resident login per member; hand over the temporary password privately. The sign-in page has no navigation bar.

## Incident reporting

Run database/incidents.sql once after households.sql. Enrollment can now record the resident’s own mobile number. Older accounts can enter a contact number when reporting. Report-specific changes stay with the report. Staff use phone or walk-in intake. Each report receives a reference and a submission timestamp.

The optional image is validated using PHP fileinfo and image metadata. One JPEG, PNG or WebP of up to 2 MiB is stored privately in MySQL with its incident in one transaction. The report, image and print-format page share the same staff/owner permission check. Set upload_max_filesize to at least 2M and post_max_size above that limit. Dashboard status updates and optional map selection belong to later phases.

## Emergency response

Import database/response.sql after incidents.sql. Staff open Response teams on the dashboard to register a team, then review a pending incident on its detail page. Verify and assign dispatches the selected team immediately. Progress, cancellation, resumption and completion appear in dated staff history. Residents see the overall status when they refresh. Previously assigned teams can be deactivated after outstanding work ends; their history prevents permanent deletion.
