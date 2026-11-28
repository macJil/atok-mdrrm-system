# Follow a request through the system

The URL opens a PHP page directly. The page checks the session, validates submitted fields, runs a prepared PDO query, and either renders the result or redirects after saving. Header and footer, permissions, map picking and PDF formatting are shared where several pages need them.

| Presenter | Open this code | Follow the data and show the result |
|---|---|---|
| 1 · Client and database | config.php, database/presentation.sql | The configured database selects the dataset. Follow one household ID into member, representative and distribution rows. |
| 2 · Registry | households/form.php, households/member.php, households/enroll.php | An address and registry reason create the household. Selecting an active member establishes its representative. Staff verification links a member to one resident account. |
| 3 · Resident intake | index.php, includes/session.php, incidents/add.php, incidents/image.php | Login checks the password hash. The incident form takes ownership from the session and contact details from the submitted form. Image and incident are saved together. |
| 4 · Response | response_teams.php, incidents/view.php | Verify and assign writes the assignment and action. Completing the last outstanding assignment updates the incident and adds a dated remark. |
| 5 · Evacuation | evacuation/index.php, evacuation/view.php | Center registration sets capacity. A locked transaction checks the selected group before inserting individual stays. Checkout sets a departure time. |
| 6 · Relief | relief_inventory/movements.php, relief_distribution.php | Stock-in adds balance and movement history. Distribution locks the goods, subtracts each quantity and commits the receipt and lines together. |
| 7 · Notices | announcement.php, index.php, dashboard/resident.php | A staff action sets publication status. Resident and public pages each select only the notices their audience can see. |
| 8 · Dashboard and summaries | dashboard/staff.php, summaries.php, includes/report_pdf.php | Follow the active-stay count and the inclusive arrival-date filter. Selected totals feed the PDF. |
| 9 · Map and weather | map/index.php, map/boundary.php, includes/weather.php, assets/js/weather.js | Role determines the marker query. PHP validates optional coordinates. The staff dashboard loads the forecast separately and renders the official advisory link. |
