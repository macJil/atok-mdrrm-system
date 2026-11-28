# Atok MDRRM System

WEBSYS1 rebuild for the Municipal Disaster Risk Reduction and Management Office of Atok, Benguet. The eight modules cover households, incidents, response, evacuation, relief inventory, distribution, announcements and disaster summaries.

## Course foundation

Use grouped folders for multi-page workflows and root-level pages for standalone modules, with PHP sessions for named staff and resident accounts and one PDO/MySQL connection using prepared statements. Use ordinary forms, four-space indentation and simple classes where they clarify an operation. Keep map selection, weather and report exports separate from core CRUD.

## Implementation practices

- Validate submitted data in PHP even when a form also uses HTML validation. Restrict choices such as roles, statuses, and barangays to known values.
- Bind user-provided SQL values with PDO prepared statements. Only build query fragments from fixed, application-defined choices; keep result limits numeric.
- Escape dynamic values with the shared `h()` helper when rendering HTML. Do not copy the older lab examples that place submitted values directly in SQL or page output.
- Use the shared session and role checks for protected pages. Every POST is checked against the session CSRF token; passwords use PHP's password hashing and verification functions. Sign-in is throttled after five failures for the same email-and-client pair within 15 minutes.
- Deploy operational installations over HTTPS. Session cookies are HTTP-only, use SameSite=Lax, and are marked Secure when the request uses HTTPS.
- Use transactions when one action changes related records or balances. Reuse shared search, pagination and mobile-number helpers; keep page-specific work procedural. Add a class only when it represents a useful responsibility, not just to wrap a single query.
- Keep state-changing actions on POST, show clear validation or save errors, and retain operational history when records are deactivated or corrected.

## Interface

Use a text municipal masthead, square-cornered controls, Bootstrap tables and responsive grids, one shared stylesheet and small scripts for interactive maps. Use approved office branding when available.

## Local setup

1. Place the folder in htdocs and start Apache and MySQL.
2. Set host, port, username, password and base URL in the ignored `config.local.php` for your machine. It overrides the safe defaults in `config.php`; do not commit local credentials.
3. Choose **one** full import in phpMyAdmin on a fresh database. Use `database/website.sql` for an empty installation or `database/presentation.sql` for the fictional classroom installation. Each file creates its own database and all tables. The older module scripts document incremental upgrades.
4. Set `$database` in config.php to `atok_mdrrm_rebuild` or `atok_mdrrm_classroom` to match. Typical MySQL ports are 3306 on XAMPP and 8889 on MAMP. Set `$baseUrl` to the project folder with a leading slash. Use PHP 8.2+ with PDO MySQL, mbstring, fileinfo and iconv.
5. For the empty installation run `php database/initialize_staff.php your-office-email` privately. For the classroom installation use the two fictional logins in the opening comments of presentation.sql. Keep classroom credentials and SQL files outside the published web directory for an operational site.
6. Open the project in your browser (for standard MAMP, http://localhost:8888/atok-mdrrm-system/). The presentation, timed speaker notes, code walkthrough and acceptance record are in [submission](submission/). The staff dashboard includes the forecast and advisory link. Residents can print or download a PDF of their own incident report; staff can print incident reports and filtered disaster summaries. Use the PDF viewer’s printer control or save the PDF.

The sign-in page includes MDRRMO contact information, the emergency contacts transcribed from the supplied Atok hotline poster, privacy guidance, and public announcements. Confirm hotline numbers with the relevant offices before operational use. The sign-in form posts to `login.php`, which verifies the saved password hash, applies sign-in throttling, renews the session ID and redirects by role. Shared session checks read the current account on each request. Password resets and status changes invalidate earlier sessions. The staff management page locks staff records during changes so at least one staff account remains active.

## Upgrading an existing installation

For an installation already at the weather and PDF release (f4cfc88), back up the database, then import `database/upgrade-review.sql`, `database/upgrade-map-stock-history.sql`, `database/upgrade-auth-security.sql`, and `database/upgrade-summary-date-indexes.sql` once each, in that order. These upgrades add announcement revisions, optional walk-in coordinates, the stock-movement correction link, persistent login-throttling storage, and date-query indexes. Import the upgrades before installing the updated PHP. Fresh installations already include these fields and indexes. Older distributions keep an unconfirmed barangay until verified from original records.

## Household registry

Staff open Households to search by member name, barangay or written address. Add a household, add its members, then select an active member as representative. To replace the representative, select another member in the same household. Enrollment verifies residency and issues one resident login per member; hand over the temporary password privately. Temporary passwords are short, exclude easily confused characters, are shown once, and require a password change at first sign-in. Do not print or share them publicly. The sign-in page has no navigation bar.

To update a resident's login email or manage sign-in access, open the household and choose Manage member. From there, staff can explicitly restore an inactive resident account or issue a password reset after verifying the resident's identity. These are separate actions. A reset shows a temporary password once, signs out existing sessions, and requires a new password at next sign-in. Staff cannot retrieve an existing password. The **Staff accounts** page is for staff accounts only.

## Incident reporting

Enrollment can now record the resident’s own mobile number. Older accounts can enter a contact number when reporting. Report-specific changes stay with the report. Staff use phone or walk-in intake. Each report receives a reference and a submission timestamp.

The optional image is validated using PHP fileinfo and image metadata. One JPEG, PNG or WebP of up to 2 MiB is stored privately in MySQL with its incident in one transaction. The report, image and print-format page share the same staff/owner permission check. Set upload_max_filesize to at least 2M and post_max_size above that limit.

## Emergency response

Staff open Response teams on the dashboard to register a team, then review a pending incident on its detail page. Verify and assign dispatches the selected team immediately. Progress, cancellation, resumption and completion appear in dated staff history. Residents see the overall status when they refresh. Previously assigned teams can be deactivated after outstanding work ends; their history prevents permanent deletion.

## Evacuation management

From the staff dashboard open Evacuation centers, add a center and select Manage stays. Household arrivals are saved as one transaction; checkout ends an individual stay. Search returning walk-ins by name before registering a new person. New walk-ins may have an optional location pin selected on the map; it is retained on the staff-only walk-in record and is not published on the community map. Occupancy is the number of stays without a checkout time. Closed centers accept no arrivals. History protects previously used centers from deletion.

## Municipal relief inventory

Open Relief inventory from the staff dashboard. When adding a good, enter its opening quantity; a positive opening balance is recorded as a stock-in movement in the same transaction. Use Record or find a stock movement for later deliveries and issues. Movements are immutable: use Correct to append a full compensating movement with a reason. The original remains in the ledger, and distribution-linked stock-outs cannot be reversed through inventory because they are part of a receipt. The encoder comes from the signed-in staff account. Zero stock displays Out of stock; a positive balance at or below the minimum displays Low stock.

## Page organization

Keep request handling, prepared SQL and the form for a task together. The household form handles both creation and editing, with its validation and SQL above the fields. Share incident ownership checks because details, images, and report copies need identical permissions. Keep multi-page workflows grouped by module; standalone modules use root-level page names such as `accounts.php`, `announcement.php`, `response_teams.php`, `relief_distribution.php`. Creation, editing and confirmation screens open only when requested. All modules use the same session checks, header and footer.

## Household relief distribution

Open Relief distribution on the staff dashboard, register a relief point, select an affected household, enter the actual receiver and choose quantities. One POST saves the distribution, receipt lines and municipal stock movements together. History opens the receipt for each issue.

## Announcements

From the staff dashboard choose Manage announcements. Save a draft or publish a notice to residents. The optional login-page checkbox starts unchecked. The login page shows the three most recently published public notices below sign-in. Withdrawing a notice removes it from both audiences and clears public display for its next publication.

## Dashboards, disaster summaries and maps

Open Manage relief points to edit an existing point or mark it inactive. Existing records keep their written locations and start without pins. Household details now include deletion confirmation. Incident details also serve their print copy; image access is a separate binary response. The map legend uses compact text codes and wraps for narrow screens; Show all mapped locations fits the current pins without replacing Show all Atok.

The staff dashboard has Overview and Disaster Reports tabs. Overview links the current incident and household counts to the selected barangay. Inventory covers the whole municipality. Disaster Reports shows four totals, expandable breakdowns and a filtered office PDF. Household classification and occupancy are current; incident counts, arrivals and distributions use the selected dates.

Account deactivation is confirmed and preserves the account and its references. Announcements are withdrawn rather than deleted. Marking an enrolled household member inactive also deactivates the linked sign-in account while retaining household, evacuation-stay and distribution history; reactivating a member does not restore account access. Incident and summary PDFs use black-and-white office styling with blank signature fields; they do not imply approval or invent signatories.

The shared picker supports written-only locations and optional pin selection. The bundled approximate outline comes from the previous project's Atok extract (license in assets/data). Confirm it with the municipality before operational use. Current location requires localhost or HTTPS and browser permission. Map tiles use an external service. Address lookup is opt-in: coordinates are sent to Photon only after the user selects “Suggest address from selected pin”; a written address works without lookup. The community map includes located verified/responding/resolved incidents, open centers and active distribution points. Household pins are staff-only.
