# Nine-member live demonstration

30 minutes including live actions. Q&A follows. Open the classroom database, staff and resident sessions in separate browser profiles, and a signed-out window. Start the timer at slide 1.

## ATOK MDRRM SYSTEM  /  MEMBER 1 · 00:00–00:35 — Atok MDRRM System for Atok

We visited the Municipal Disaster Risk Reduction and Management Office in Atok on 17 November 2026. Today we will follow one household through a fictional incident, evacuation and relief issue. Open separate staff and resident browser sessions before starting.

## ATOK MDRRM SYSTEM  /  MEMBER 1 · 00:35–01:10 — The stakeholder visit

Our team presented all eight course modules. The staff approved the proposed modules, and the team reports that the head also approved the project. The office kept our contact details for further suggestions. Show a classroom-cleared visit photograph here if available.

## ATOK MDRRM SYSTEM  /  MEMBER 1 · 01:10–02:00 — What staff asked to see

The staff emphasized the dashboard and asked for weather information and printable reports. We show a Sayangan forecast and link to the official Northern Luzon advisories. The forecast describes one location. Open the dashboard and identify both sources.

## ATOK MDRRM SYSTEM  /  MEMBER 1 · 02:00–04:00 — Read the fictional database records

Open database/presentation.sql at the household and member inserts. Mila represents the household. Ari can receive supplies for it. In config.php show how PDO opens this database. Staff use the same internal household record even when the person receiving aid changes.

## ATOK MDRRM SYSTEM  /  MEMBER 2 · 04:00–07:15 — Plain PHP modules and household registration

Open Households, search Ramos, then open the household and its two members. In households/form.php follow one address from POST to the prepared statement. In households/enroll.php show the member-to-account link. Mila already has an account, so leave the existing enrollment in place.

## ATOK MDRRM SYSTEM  /  MEMBER 3 · 07:15–10:45 — Submit an incident as a resident

In the resident window open Report an incident. Enter a flooding report in Paoay using the training lane, check the prefilled phone, and submit. In incidents/add.php point to the signed-in account ID saved beside the editable contact details. Read the new reference aloud for the staff presenter.

## ATOK MDRRM SYSTEM  /  MEMBER 4 · 10:45–14:00 — Keep response and incident status in step

Use Incident reports to find that exact reference; an older pending example is already in the queue. Verify and assign the Municipal Field Team with a short dated remark. Refresh the resident view to show Responding. Complete the assignment and refresh again. Show how incidents/view.php checks remaining assignments before setting Resolved.

## ATOK MDRRM SYSTEM  /  MEMBER 5 · 14:00–17:15 — Count the people who actually arrive

Open Training Assembly Hall. One walk-in is already staying there. Select the Ramos household and check in both members. Show three staying and two places remaining. In evacuation/view.php find the capacity check inside the transaction. Attempt a repeat arrival and show that the total stays at three.

## ATOK MDRRM SYSTEM  /  MEMBER 6 · 17:15–20:30 — Distribute several goods in one transaction

Open municipal inventory and read the starting balances of eighteen. Record a stock-in of five units for each good, then distribute two food packs and two bottles to the Ramos household at Supply desk. Enter Ari as the receiver. Open the receipt and verify twenty-one of each remain. In relief_distribution/index.php show the commit after all lines.

## ATOK MDRRM SYSTEM  /  MEMBER 7 · 20:30–23:30 — Publish and withdraw a preparedness notice

Create a preparedness reminder as a draft. Refresh Mila’s dashboard and locate the existing published notice. Publish the new reminder for residents, then explicitly enable login-page display. Check the signed-out page. Withdraw and refresh. Show the publication and visibility conditions in index.php.

## ATOK MDRRM SYSTEM  /  MEMBER 8 · 23:30–25:20 — Read the dashboard in people and records

Filter the staff dashboard to Paoay. Follow the flooding count into the incident list. Return and compare the assembly hall’s three occupants against its current stays. Show dashboard/staff.php counting stays with no checkout time. Municipal stock still covers all barangays.

## ATOK MDRRM SYSTEM  /  MEMBER 8 · 25:20–27:00 — Print the staff disaster summary

Select Paoay in Disaster summaries and use today for both dates. Download the PDF and CSV. Compare the lines for bottles and packs, then choose an earlier period and show how arrivals change while occupancy remains current. In summaries/index.php trace the same query results into both downloads.

## ATOK MDRRM SYSTEM  /  MEMBER 9 · 27:00–29:20 — Find the same incident on the shared map

Open the community map as Mila. Point out the verified road obstruction and the open center. In map/index.php show that household rows are requested only for staff. Open Mila’s incident and download the resident PDF. Compare it to the staff download; response remarks appear only in the staff copy.

## ATOK MDRRM SYSTEM  /  MEMBER 9 · 29:20–30:00 — Documentation and handoff

Return to the interview requests. Demonstrate the weather panel and the advisory link. The proposed PDF layout, signatories, seals, municipal boundary and permission to use photographs still need office confirmation. The two SQL files let another team rehearse privately with the same starting records. We are ready for questions.

## Supporting documents

Use the team’s 17 November visit record for client inputs, and the supplied sample for the proposed print layout. Bring a permitted photograph separately. For the weather demonstration use the [Open-Meteo forecast documentation](https://open-meteo.com/en/docs) and [PAGASA Northern Luzon regional page](https://bagong.pagasa.dost.gov.ph/regional-forecast/nlprsd). Demonstrate counts with the fictional records in database/presentation.sql.
