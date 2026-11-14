# R-50: the twenty scenarios and the acceptance criteria

Written 2026-11-14 for the platform catalogue and entitlements work (`docs/programme/r50/ARCHITECTURE.md`). Nothing here is
a score. Each line says what is proved, by which test, and what is not.

How to read the status:

- **Proved**: the scenario runs from start to finish against InfoDot's real routes, services and database, and every step
  is InfoDot's own.
- **Proved in part**: InfoDot's side runs for real. The rest needs something that does not exist yet (a payment through
  Dot.Finance, or behaviour inside another platform). The test ends with `markTestIncomplete` and a one-line reason, so
  PHPUnit reports it as *incomplete*, never as passed.
- **Blocked**: nothing InfoDot can do alone.

Where a payment is needed, the test sends InfoDot the signed confirmation that Dot.Finance will send. The signature is
made by the test with a key made for it. That proves how InfoDot reacts. It does not prove that Dot.Finance can take a
payment, because Dot.Finance has no payment code yet. No test simulates a payment provider.

Run them: `APP_URL=http://localhost ./vendor/bin/phpunit tests/Feature/Platforms/Scenarios`. The classes are in
`tests/Feature/Platforms/Scenarios/`, one per group, on a shared base (`ScenarioTestCase`). Each test is named
`test_scenario_NN_...`.

## 1. The twenty scenarios (brief section 15)

| # | Scenario | Status | Test (in `tests/Feature/Platforms/Scenarios/`) | What is missing |
|---|---|---|---|---|
| 1 | A newly registered business with no optional platforms activated | Proved | `NewCompanyScenariosTest::test_scenario_01_…` | |
| 2 | A business activating a free platform | Proved | `NewCompanyScenariosTest::test_scenario_02_…` | |
| 3 | A business subscribing to a paid platform | Proved in part | `TrialAndSubscriptionScenariosTest::test_scenario_03_…` | The owner has no checkout. The payment itself is Dot.Finance's, which cannot take one. InfoDot's request, the waiting state and the opening on a signed confirmation are proved. |
| 4 | A business independently activating multiple platforms | Proved | `NewCompanyScenariosTest::test_scenario_04_…` | |
| 5 | A business starting a trial | Proved | `TrialAndSubscriptionScenariosTest::test_scenario_05_…` | A trial involves no money. It is started from the catalogue, ends without a charge, and cannot be started twice. |
| 6 | A business with an expired subscription | Proved in part | `TrialAndSubscriptionScenariosTest::test_scenario_06_…` | The failed payment that leads to expiry is a signed event made by the test. |
| 7 | A business upgrading its subscription | Proved in part | `TrialAndSubscriptionScenariosTest::test_scenario_07_…` | There is no upgrade page or checkout. The plan change on a confirmed payment is proved. |
| 8 | A business cancelling a subscription | Proved in part | `TrialAndSubscriptionScenariosTest::test_scenario_08_…` | A paid plan is cancelled in Dot.Finance, which has no subscription pages yet. The cancel itself, the paid time honoured and the free switch-off from the page are proved. |
| 9 | An administrator granting complimentary access | Proved | `PeopleAndAccessScenariosTest::test_scenario_09_…` | |
| 10 | An employee attempting to access an unauthorised application | Proved | `PeopleAndAccessScenariosTest::test_scenario_10_…` | |
| 11 | A business activating an application without completing unrelated milestones | Proved | `NewCompanyScenariosTest::test_scenario_11_…` | |
| 12 | A business receiving a relevant platform recommendation | Proved | `PeopleAndAccessScenariosTest::test_scenario_12_…` | Recommendations use only facts InfoDot holds (people, industry, business page, enquiries). Ones that need Dot.Analytics data are not built. |
| 13 | A business accessing a platform after verified payment | Proved in part | `TrialAndSubscriptionScenariosTest::test_scenario_13_…` | The payment is a signed confirmation made by the test. A wrong signature changes nothing. |
| 14 | A business experiencing a failed payment | Proved in part | `TrialAndSubscriptionScenariosTest::test_scenario_14_…` | Same: the failure arrives as a signed event made by the test. Grace, notice, recovery are InfoDot's and are proved. |
| 15 | A business with different permissions across multiple employees | Proved | `PeopleAndAccessScenariosTest::test_scenario_15_…` | |
| 16 | A business switching between two activated platforms | Proved in part | `PeopleAndAccessScenariosTest::test_scenario_16_…` | InfoDot's side (Dot Apps, one launch code per platform, same company) is proved. Arriving signed in inside each platform is that platform's. |
| 17 | A business attempting to access an operational but unactivated platform | Proved | `NewCompanyScenariosTest::test_scenario_17_…` | |
| 18 | A platform becoming temporarily unavailable | Proved | `AvailabilityAndDataScenariosTest::test_scenario_18_…` | |
| 19 | A business renewing an expired subscription | Proved in part | `TrialAndSubscriptionScenariosTest::test_scenario_19_…` | The renewing payment is a signed event made by the test. |
| 20 | A business retaining access to its own data after cancellation | Proved in part | `AvailabilityAndDataScenariosTest::test_scenario_20_…` | InfoDot keeps the company's content, the entitlement and its history, and never sends a delete. What each platform keeps and still lets the company read is that platform's own behaviour. |

Count: 11 proved, 9 proved in part, 0 blocked. The nine run as **incomplete** in PHPUnit.

## 2. Acceptance criteria (brief section 16)

"InfoDot" below means this repository. Other platforms are separate repositories and are not changed or tested here.

### Platform experience

| Criterion | Status | Evidence or gap |
|---|---|---|
| Every available platform has been inspected | Not done in InfoDot | The audit notes are in `docs/programme/r50/AUDIT_FLEET_NOTES.md`. No platform's own pages were exercised for this work. |
| Original logos and identities are preserved | Proved in part | The Platforms page and detail pages show each platform's own logo (`PlatformCataloguePageTest`). The platforms' own pages are not checked here. |
| Authenticated landing pages are premium and platform-specific | Blocked | Built in each platform (`docs/programme/r50/LANDING_BRIEF.md`). |
| Navigation is consistent | Proved in part | `PlatformNavigationTest` (Dot Apps, bays by category) and the browser specs `k-platform-access.spec.ts`, `l-platform-catalogue.spec.ts`. |
| Primary actions work | Proved | `PlatformCatalogueActionsTest`, `PlatformTrialFromCatalogueTest`, the scenarios above. |
| Mobile and desktop experiences are functional | Proved in part | The browser specs check no sideways scrolling at 375 and 1440 for the platform pages. The new infrastructure and trial pages were looked at in a browser at 1440 and 375, light and dark. |

### Platform discovery

| Criterion | Status | Evidence or gap |
|---|---|---|
| Businesses can browse the complete discoverable catalogue | Proved | `PlatformCataloguePageTest`, `PlatformCatalogueConsistencyTest` |
| Recommendations reflect actual business needs | Proved in part | `PlatformRecommendationsTest`. Only rules over facts InfoDot holds; each shows its reason. |
| Categories and search work | Proved | `PlatformSearchTest`, `PlatformCataloguePageTest` |
| Users understand the value of each application | Not measured | The detail pages carry the catalogue's wording; nobody has been asked. |

### Access management

| Criterion | Status | Evidence or gap |
|---|---|---|
| Registration, operational status and entitlement are separate | Proved | `PlatformCatalogueConsistencyTest`, `PlatformEntitlementScenariosTest`, scenarios 17 and 18 |
| Businesses do not automatically access every platform | Proved | Scenarios 1 and 17, `PlatformDoorsTest` |
| InfoDot centrally manages entitlements | Proved | `PlatformDoorsTest` (every door asks one class), `CompanyPlatformsEndpointTest`, `EntitlementOutboxTest`. Platforms that do not yet read `entitled` are not enforced by InfoDot (`docs/programme/r50/ARCHITECTURE.md` section 10). |
| User-level permissions are enforced | Proved | Scenarios 10 and 15 |
| No artificial business-success milestones restrict access | Proved | Scenario 11 |

### Commercialisation

| Criterion | Status | Evidence or gap |
|---|---|---|
| Free, included, trial and paid access are distinguishable | Proved | `PlatformCataloguePageTest` (every state in words), scenarios 2, 3, 5 |
| Pricing is transparent | Proved | A price is shown only from an approved plan row (`NoPricesShippedTest`, `PlatformCataloguePageTest`). |
| Commercial configuration is centralised | Proved | The admin centre (`AdminCatalogueTest`, `AdminCentreAccessTest`); Dot.Finance reads it from `GET /api/finance/platform-plans` (`FinancePlatformPlansTest`). |
| Actual billing integrations are used where available | Blocked | Dot.Finance has no payment code yet. Paid activation stays closed until it can send a verified confirmation. |
| No fake prices or transactions exist | Proved | `NoPricesShippedTest`; `PlatformSubscriptionEventsTest::test_nothing_else_in_the_application_can_mark_anything_paid`. |

### Analytics

| Criterion | Status | Evidence or gap |
|---|---|---|
| Business-specific access is accurately represented | Proved | `PlatformNavigationTest::test_analytics_shows_the_companys_own_platforms_not_the_fleet`. The page no longer lists intelligence engines as active: nothing recorded about a company backed them. |
| Infrastructure monitoring is separated from customer entitlements | Proved in part | InfoDot's staff view is `/admin/platforms/infrastructure` (`AdminInfrastructureTest`). Dot.Analytics' own clean-up is in that repository. |
| Recommendations are explainable and grounded in real information | Proved | Each shows its reason, from InfoDot's own records. |

### Security

| Criterion | Status | Evidence or gap |
|---|---|---|
| Backend access enforcement works | Proved | `PlatformDoorsTest`; scenarios 1, 10, 15, 17 |
| Tenant isolation is verified | Proved | `PlatformEntitlementScenariosTest::test_another_companys_entitlements_are_invisible_and_unusable`, `PlatformCatalogueActionsTest::test_nobody_can_act_for_a_company_that_is_not_their_current_one`, `TenantIsolationTest` |
| Billing and entitlement changes are auditable | Proved | Entitlement history and catalogue audit trail (`AdminRecordsTest`, `EntitlementLifecycleTest`, scenario 9) |
| Subscription changes do not destroy business data | Proved in part | Scenarios 6, 8, 20 prove InfoDot deletes nothing and sends no delete. What each platform does with its own data is its own. |

### Technical integrity

| Criterion | Status | Evidence or gap |
|---|---|---|
| Existing InfoDot SSO remains functional | Proved | `EcosystemRedeemTest`, `Oidc/` tests, `PlatformLaunchTest`, the full suite |
| Platform switching works | Proved in part | Scenario 16 |
| Existing business workflows remain operational | Proved | The full PHPUnit suite and the browser suite (counts in the pull request) |
| Relevant automated tests pass | Proved | As above. Skipped and incomplete tests are counted apart and never as passes. |
| Known critical regressions are resolved | Proved in part | None known on InfoDot. Other platforms were not tested. |

## 3. What is still needed from others

- Dot.Finance: checkout, the provider, verified webhooks, invoices, renewals, subscription pages. Until then nine of the
  twenty scenarios stay incomplete at their payment step.
- Each platform: read `entitled` from the redeem answer and the platforms read, and land the person on its own page.
- The owner: approved prices. The application ships none.
