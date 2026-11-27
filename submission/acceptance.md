# Acceptance and handoff

Test data came from isolated MAMP databases. Existing municipal records were not used.

| Check | Result and recheck |
|---|---|
| Installation | Imported the empty schema and ran the eight-module workflow suite. Imported the separate classroom schema into another empty database and signed in using each role's documented credentials. |
| Exports | The earlier copy opened an HTML page. The replacement returns a downloadable A4 PDF. Extracted the incident reference and generation time from downloads; staff received response history, residents received their own copy only. Another resident received a 404. The filtered summary download and CSV reported two packs and two bottles for the seeded Paoay distribution. |
| Weather outage | Simulated a failed request and a successful response with zero precipitation probability. The panel showed an outage message in the first case and 0% in the second. The PAGASA advisory link renders independently in the page. |
| Operational safeguards | Two independent sessions competed for the same last evacuation place. One succeeded. Duplicate and over-capacity group arrivals left occupancy unchanged. An insufficient second distribution line rolled back the first deduction and the receipt. |
| Roles and workflows | Exercised account reset/deactivation, household enrollment, private image/report access, response completion, individual checkout, municipal stock history, announcement publication/withdrawal, filtered dashboards, summaries and map privacy. |
| Responsive layout | Checked visible primary actions on the staff dashboard and eight module entry pages at 390 and 1280 pixels. The incident picker also passed side-by-side desktop and stacked phone checks. |
| Navigation | Checked filtered pagination, link destinations and blocked deletion of linked teams and households. |
| Acceptance environment | The first fresh-install check returned HTTP 503 with MAMP MySQL stopped. Started MySQL and repeated the import and login checks successfully. |

## Uncoached teammate exercise

Give a teammate the two fictional logins and two separate browser profiles. Ask them to report a flooding incident, dispatch a team, check two people into Training Assembly Hall, issue two goods, and download the Paoay totals. Observe without directing them. Record the first label or step they struggle with, the role, and whether they finish. On a fresh classroom database, the center should move from one to three occupants; issuing two of each starting good leaves sixteen of each. This shorter exercise omits the presentation's delivery of five units.

## Decisions for office review

The team's visit record identifies the MDRRM Office, Atok, on 17 November 2026. The team reports approval of the eight proposed modules, a dashboard priority, and requests for CAR weather and printable reports. The sample report supplied by the team informed the PDF layout. Confirm office branding, print headings, signatories, and the municipal boundary with the office. Use an interview photograph only after confirming display permission. A timed team rehearsal, direct observation of an unfamiliar teammate, and office approval remain pending.

## Review fixes · 28 November 2026

- Open one notice in two staff sessions. Publish in the first, then save or withdraw from the stale form. Both stale actions were refused and the current notice reloaded.
- Issue supplies in Paoay and change the household to Caliking. The Paoay report retained the original quantity. A separate upgrade from the prior classroom schema preserved older distributions in municipal totals and labeled their barangay unconfirmed.
- Open Print incident report in each role and Print disaster summary with a barangay filter. Checked the A4 output and the inline PDF response; private response remarks appeared only on the staff copy. Use the PDF viewer’s Print control.
- Rechecked the eight-module suite, including simultaneous arrivals, failed stock deductions, resident ownership, and map privacy. Primary actions on the module entry pages passed browser checks at 390 and 1280 pixels.
