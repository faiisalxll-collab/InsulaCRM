# Saudi Broker OS V1 — Release Gate

This checklist defines the minimum gate for a **pilot office release** of the Saudi real-estate workflow. It deliberately excludes second-phase automation and network features.

## Frozen V1 workflow

Client → Property / Property Request → Match → Showing → Offer / Negotiation → Deal → Closing → Commission

## Required product checks

- [x] Saudi clients can be created, edited, viewed, and assigned to an authorized broker.
- [x] Every new Saudi property request must be linked to a client.
- [x] Every standalone Saudi property must be linked to an owner/client; nested owner-property entry derives that owner safely from the route.
- [x] One client/owner can have multiple properties.
- [x] One client can have multiple property requests.
- [x] Saudi properties support sale/rent, district, plan, area, price, facing direction, street width, age, floors, units, furnishing, finance eligibility, coordinates, and photos.
- [x] Saudi address normalization preserves local Arabic/English address tokens and does not apply US street/state conventions.
- [x] Legacy MLS input is not persisted through Saudi property forms.
- [x] Property photo upload/delete is tenant-scoped and removes stored files when the property is deleted.
- [x] Requests support sale/rent criteria and are broker-scoped.
- [x] Matching is deterministic, explainable, tenant-scoped, and reverse-refreshes on property/request changes.
- [x] Stale matches are removed when requests or properties are no longer active.
- [x] A match can open a prefilled showing directly.
- [x] A showing can start an idempotent deal tied to both the property and property request.
- [x] Offers support accept, reject, and counter.
- [x] Saudi offer financing is limited to cash, bank finance, or other; FHA/VA/Conventional remain legacy wholesale-only values.
- [x] Saudi deal updates do not persist wholesale-only fields such as assignment fee, earnest money, MLS, inspection period, or due-diligence dates.
- [x] Accepting an offer moves the deal to agreement, pauses the request, and marks the property pending.
- [x] Successful sale closes the request and marks the property sold with price/date.
- [x] Successful rental closes the request and marks the property leased.
- [x] Lost transactions reopen eligible pending inventory/request state.
- [x] Closing checklist is created automatically.
- [x] Commission status supports pending, due, and paid.
- [x] Dashboard is broker-scoped for non-admin users.
- [x] Reports are broker-scoped for non-admin users.
- [x] Saudi V1 navigation exposes only the core workflow.
- [x] Global search in Saudi V1 searches clients, directly linked transactions, properties, and property requests; broker results are ownership-scoped.
- [x] Global search in Saudi V1 does not expose the legacy wholesale Buyer database.
- [x] Legacy Buyer, Disposition, Investor Packet, ARV/Comparable Sales, field-scout intake, document generation, CMA, and legacy AI surfaces are not exposed as Saudi V1 workflow.
- [x] Wholesale mode remains isolated from Saudi V1 behavior.

## Security gate

- [x] TenantScope remains enabled for tenant-owned models.
- [x] Direct Policy checks explicitly reject cross-tenant Lead, Property, Deal, Request, Match, and Showing access where applicable.
- [x] Brokers cannot attach records to another broker's client through crafted form requests.
- [x] Brokers cannot reassign owned clients to another broker by crafting agent_id.
- [x] Cross-tenant offer/checklist/document/showing mutations remain covered by regression tests.
- [x] Tenant admins remain blocked from platform database backups.
- [x] No production merge or deployment occurs from this branch before the pilot gate below passes.

## CI gate

Before pilot deployment:

- [x] Latest code HEAD has a green full test suite.
- [x] Tenant isolation test step is green.
- [x] No skipped/ignored failure was introduced to make CI green.
- [x] Migration rollback and re-apply pass on disposable database services.
- [x] MySQL 8.4 and MariaDB 11.4 migration behavior passes for the Saudi V1 migration set.

Verified on hardened Saudi V1 code HEAD `1cc0059e16d65f3d27ddff4cf82c8f6a74358672` by GitHub Actions run `37139930019`: the tenant-isolation step, full test suite, MySQL 8.4 migration gate, and MariaDB 11.4 migration gate all completed successfully. Both database gates run the full migration set, roll back the five Saudi V1 migrations, re-apply them, and verify migration status. This verified baseline includes the Saudi-aware global search, broker-scoped search isolation, localized address normalization, legacy-route isolation, Saudi-only offer financing validation, removal of legacy CMA/AI surfaces from Saudi property pages, and the end-to-end Saudi pilot flow test.

## Pilot deployment gate

Execution guide: `docs/SAUDI_V1_PILOT_RUNBOOK.md`.

Before the first real office receives access:

1. Back up the target database and uploaded files.
2. Configure the office tenant as:
   - business_mode: realestate
   - country: SA
   - currency: SAR
   - locale: ar
   - timezone: Asia/Riyadh
3. Configure environment secrets outside the repository.
4. Run migrations using the deployment process only after the backup is verified.
5. Ensure the public storage link is configured for property photos.
6. Create one admin plus at least one broker test account.
7. Do not import the office's entire history on day one. Start with a small live dataset.

## Required smoke test with realistic Saudi data

Run this exact sequence after deployment:

1. Create an owner/client.
2. Add a Saudi property with at least two photos.
3. Create a second client who is searching for a property.
4. Create a sale or rental request.
5. Confirm a suitable property is matched and reasons are visible.
6. Schedule a showing from the match.
7. Complete the showing and record the outcome.
8. Start negotiation from the showing.
9. Record an offer.
10. Counter it once, then accept it.
11. Confirm property becomes pending and request becomes paused.
12. Complete the closing checklist.
13. Enter contract price and commission.
14. Close the deal successfully.
15. Confirm:
    - request = fulfilled
    - sale property = sold, or rental property = leased
    - stale matches disappear
    - commission = due
16. Mark the commission paid and confirm paid timestamp.
17. Verify dashboard and reports reflect the transaction.
18. Repeat the scenario with a second broker and confirm neither broker can see or mutate the other's private records.

## Explicitly outside V1

Do **not** delay the pilot for these items:

- Broker-to-broker network.
- WhatsApp API automation.
- Heavy AI matching or AI decision-making.
- Automatic scraping of property portals.
- Link-to-listing import.
- Saudi-specific Excel/CSV importer improvements beyond the existing generic import foundation.
- Native mobile app.
- Public marketplace.
- Payment/subscription billing.
- Advanced office-to-office commission sharing.

These belong to V2 after observing the first office's real workflow.

## Pilot success criteria

The pilot is successful when the office can complete the core flow without falling back to WhatsApp or spreadsheets for the transaction state:

**client → property/request → match → showing → negotiation → deal → commission**

Record every place where staff leave the system or duplicate work. Those observations define V2 priorities.
