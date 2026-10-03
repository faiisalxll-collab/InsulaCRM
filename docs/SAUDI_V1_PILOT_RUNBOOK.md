# Saudi Broker OS V1 — Pilot Office Runbook

This runbook is for a **controlled staging/pilot office** only. It does not authorize a merge to `main` or a Production deployment.

## 1. Entry gate

Do not start the pilot unless all of these are true:

- [ ] `docs/SAUDI_V1_RELEASE_CHECKLIST.md` is green for the technical gate.
- [ ] The selected `saudi-real-estate` commit has a green full test suite.
- [ ] Tenant isolation is green.
- [ ] MySQL 8.4 and MariaDB 11.4 migration gates are green.
- [ ] A database backup and uploaded-files backup exist and have been verified.
- [ ] Environment secrets are configured outside Git.

## 2. Pilot tenant configuration

Configure the pilot office tenant with:

- `business_mode = realestate`
- `country = SA`
- `currency = SAR`
- `locale = ar`
- `timezone = Asia/Riyadh`
- metric measurements

Do not reuse a wholesale tenant for the pilot unless its existing data has been reviewed first.

## 3. Pilot accounts

Create the smallest useful team:

1. One office admin.
2. One broker/agent.
3. Optional second broker only for ownership/isolation checks.

Verify before entering live data:

- Admin can see office-wide records.
- Broker can see and mutate only records they are authorized to manage.
- Broker cannot reassign owned clients to another broker through crafted or bulk requests.
- Cross-tenant IDs return no usable data.

## 4. Deployment preparation

On the staging/pilot environment:

1. Confirm the deployed commit SHA matches the approved `saudi-real-estate` commit.
2. Confirm the environment is not Production.
3. Back up:
   - database
   - `storage/app/public` or the equivalent persistent uploaded-file storage
4. Install production dependencies using the existing deployment process.
5. Run the application migrations.
6. Configure the public storage link required for property photos.
7. Clear/rebuild framework caches using the normal environment deployment process.
8. Confirm the application can write uploaded property photos to persistent storage.

Never commit `.env`, credentials, API keys, database passwords, or private customer data.

## 5. First-office data rule

Do not import the office's full historical data for the first pilot.

Start with:

- 2–5 owners
- 5–10 properties
- 2–5 searching clients
- 3–6 property requests

Use a mixture of sale and rental cases where possible.

The goal is to observe the real workflow, not to win a spreadsheet-import contest on day one.

## 6. Required live smoke journey

Complete one full transaction without manually editing database records.

### A. Client and inventory

- [ ] Create an owner/client.
- [ ] Add a Saudi property.
- [ ] Upload at least two property photos.
- [ ] Confirm the owner can hold more than one property without overwriting the first.
- [ ] Create a separate searching client.
- [ ] Create a sale or rental request.

### B. Matching

- [ ] Confirm a suitable property appears as an eligible match.
- [ ] Confirm match score and reasons are visible.
- [ ] Confirm an unsuitable property does not appear as an eligible match.
- [ ] Open the showing directly from the match.

### C. Showing and negotiation

- [ ] Schedule the showing.
- [ ] Record the showing result.
- [ ] Start negotiation from the showing.
- [ ] Confirm the deal is linked to the exact property and exact property request.
- [ ] Record an offer.
- [ ] Counter once.
- [ ] Accept the offer.

After acceptance verify:

- [ ] deal = agreement / under contract
- [ ] property = pending
- [ ] request = paused
- [ ] stale match removed
- [ ] Saudi closing checklist created

### D. Closing and commission

- [ ] Complete the closing checklist.
- [ ] Record contract price.
- [ ] Record total commission and office split.
- [ ] Close the transaction successfully.

For a sale verify:

- [ ] property = sold
- [ ] sold price/date recorded

For a rental verify:

- [ ] property = leased

For both verify:

- [ ] request = fulfilled
- [ ] commission = due
- [ ] dashboard reflects the transaction
- [ ] reports reflect the transaction

Then:

- [ ] mark commission paid
- [ ] confirm payment timestamp
- [ ] confirm office and broker commission amounts

## 7. Failure-path smoke checks

Also test the ugly paths, because real offices specialize in inventing them:

- [ ] Reject an offer.
- [ ] Counter an offer.
- [ ] Mark a transaction lost and confirm eligible property/request state reopens.
- [ ] Attempt to delete a property after a showing or deal and confirm deletion is blocked.
- [ ] Attempt to delete a request after a showing or deal and confirm deletion is blocked.
- [ ] Attempt to delete a client linked to property/request/deal and confirm deletion is blocked.
- [ ] Attempt the same through bulk client deletion.
- [ ] Confirm Saudi deals do not create legacy wholesale buyer matches.

## 8. Broker isolation smoke check

With two broker accounts:

1. Broker A creates a client, property/request and transaction.
2. Broker B attempts normal navigation to A's private records.
3. Broker B attempts direct known IDs.
4. Broker B attempts crafted form submissions.
5. Broker B attempts bulk reassignment.

The pilot fails if Broker B can read or mutate Broker A's protected records outside the intended office permissions.

## 9. What to observe in the office

Record every point where staff:

- leave the platform for WhatsApp to remember transaction state
- copy the same data into Excel
- re-enter owner/client/property data
- cannot tell what the next action is
- cannot find a request, property, showing or deal quickly
- keep a private note because the system has nowhere appropriate for it

These observations, not feature brainstorming, define V2 priorities.

## 10. Stop conditions

Pause the pilot and investigate before adding more live records if any of these happen:

- tenant data appears in another tenant
- broker ownership is bypassed
- a migration fails
- photos disappear from persistent storage
- property/request/deal relationships point to the wrong client
- accepting an offer does not pause inventory/request state correctly
- closing a deal produces the wrong sold/leased/commission state
- transaction history can be deleted after showing/negotiation activity

## 11. Rollback principle

If the pilot must be rolled back:

1. Stop new data entry.
2. Preserve logs and evidence of the failure.
3. Restore from the verified database/files backup using the environment's normal recovery process.
4. Do not attempt ad-hoc production SQL fixes.
5. Reproduce the issue on a disposable environment.
6. Add a regression test before applying the fix to the pilot again.

## 12. Pilot completion

The pilot is ready for a go/no-go review when:

- one sale or rental journey has completed end-to-end
- a second broker isolation scenario has passed
- no transaction state required a parallel spreadsheet
- every defect found during the pilot has a recorded reproduction
- the office can identify the next action from the platform itself

Only after that review should broader onboarding, integrations, automation, or V2 work be considered.
