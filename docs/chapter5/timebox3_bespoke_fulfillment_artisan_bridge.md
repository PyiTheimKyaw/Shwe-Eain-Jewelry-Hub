# Timebox 3 — Bespoke Fulfillment Workflow & Artisan Bridge

## Timebox Summary (Implementation Overview)

Timebox 3 operationalized the bespoke production lifecycle by bridging accepted commercial decisions into controlled workshop execution. Implementation followed a staged design-to-development path: order conversion and supersession rules were encoded first so revised quote acceptance produces one authoritative active order path, then payment-instruction and deposit-verification capabilities were integrated to enforce financial gates before production begins. This logic-heavy layer was implemented through dedicated lifecycle and verification services to preserve consistency across API and admin actions, especially for edge cases such as credited deposits and superseded order branches.

After financial and order-state controls were stabilized, artisan assignment and production progression features were delivered, including assignment context, milestone transitions, and readiness-for-review signaling. The timebox further integrated reference-image transfer from inquiry context into artisan job details to reduce manufacturing ambiguity and rework risk. Delivery quality was assured through structured manual QA initiation across quote-to-order conversion, deposit confirm or reject paths, assignment gating, artisan status transitions, and QC approval outcomes, confirming that fulfillment behavior remained predictable and auditable across customer, admin, and artisan touchpoints.

## 1) Functional Overview

### Detailed feature categorization

#### A. Order conversion and supersession module

- Accepting a revised quote can create a new active order for the same customer inquiry.
- Older non-active order paths are superseded to prevent operational confusion.
- Previously confirmed deposit value is credited into the active path where applicable.
- Customer journey stays consistent even when commercial terms are revised.

#### B. Deposit collection and verification module

- Admin can issue payment instructions to customer with clear payable context.
- Customer can submit proof of transfer from mobile interface.
- Admin can confirm or reject payment proof based on review outcome.
- Verification state directly controls whether production can proceed.

#### C. Artisan assignment and production module

- Admin can assign an artisan to a qualified order.
- Assignment includes practical work context such as task scope and deadlines.
- Artisan can start work and update milestone progression.
- Artisan can mark work ready for review when workshop production is complete.

#### D. Quality control and readiness module

- Admin performs final QC approval before order becomes ready for customer.
- QC gate prevents premature completion messaging.
- Workflow transitions maintain handover discipline between teams.

#### E. Reference-image bridge and communication continuity

- Customer inquiry images can be reused as workshop references.
- Admin can attach selected visual references to artisan assignment.
- Artisan receives clearer production intent, reducing rework risk.

#### F. UI/UX enhancements delivered in this timebox

- Artisan mobile workspace became operational with job list and detail flow.
- Payment and verification states became visible in customer/admin experiences.
- Status-centric screens improved transparency across customer, admin, and artisan roles.

## 2) Testing Suite

### 2.1 Test Plan Table (Manual QA, black-box)


| Test ID   | Scenario                                                     | Preconditions                                       | Test Data                               | Tester Actions (UI)                                                          | Expected UI Outcome                                                              | Priority |
| --------- | ------------------------------------------------------------ | --------------------------------------------------- | --------------------------------------- | ---------------------------------------------------------------------------- | -------------------------------------------------------------------------------- | -------- |
| TB3-TP-01 | Customer accepts revised quote and active order path updates | Inquiry has at least one previously quoted path     | Revised quote card                      | Tester accepts latest quote from customer chat/card                          | Customer sees active order progress tied to latest accepted quote                | High     |
| TB3-TP-02 | Deposit proof upload and verification cycle                  | Payment instruction already issued                  | Payment proof image/file                | Tester uploads proof as customer; admin reviews and confirms                 | Payment status updates to confirmed and next stage unlocks                       | High     |
| TB3-TP-03 | Deposit rejection handling                                   | Payment proof submitted but invalid                 | Incorrect payment proof                 | Tester rejects payment proof from admin side                                 | Customer sees rejection state and can re-submit corrected proof                  | High     |
| TB3-TP-04 | Assignment gate before/after deposit confirmation            | Two orders prepared: one unpaid, one confirmed      | Artisan staff member + assignment notes | Tester attempts assignment before and after confirmation                     | Before confirmation: assignment blocked; after confirmation: assignment succeeds | High     |
| TB3-TP-05 | Artisan production progression                               | Assigned order available in artisan app             | Assigned job with reference images      | Tester starts production, then marks ready for review                        | Job status progresses through expected workshop stages                           | High     |
| TB3-TP-06 | Admin QC approval to ready state                             | Job marked ready for review                         | QC checklist context                    | Tester taps QC approve from admin order detail                               | Order displays ready-for-collection/handover state                               | High     |
| TB3-TP-07 | Reference image bridge into artisan job detail               | Inquiry has image references selected at assignment | 2-3 sample inquiry images               | Tester assigns artisan with selected references and opens artisan job detail | Artisan sees linked visual references clearly in job detail view                 | Medium   |


### 2.2 Test Scripts (Manual QA execution)

#### TB3-TS-01 — Revised quote acceptance and active order path


| Step | Input Data                                 | Expected Result                         | Actual Result                  | Status |
| ---- | ------------------------------------------ | --------------------------------------- | ------------------------------ | ------ |
| 1    | Customer opens latest revised quote card   | Latest quote details are visible        | Actual Result matches Expected | Pass   |
| 2    | Customer taps `Accept` and confirms        | Quote state changes to accepted         | Actual Result matches Expected | Pass   |
| 3    | Customer/admin review order pipeline views | Single active fulfillment path is shown | Actual Result matches Expected | Pass   |


- **Proof of Execution (Screenshots required):**
  - Latest quote card before acceptance.
  - Acceptance confirmation state.
  - Updated order/pipeline state after acceptance.
  - Any previous path shown as inactive/superseded (if visible in UI).

#### TB3-TS-02 — Deposit proof submission and confirmation


| Step | Input Data                                         | Expected Result                     | Actual Result                  | Status |
| ---- | -------------------------------------------------- | ----------------------------------- | ------------------------------ | ------ |
| 1    | Customer opens payment card and selects proof file | Upload flow opens and accepts file  | Actual Result matches Expected | Pass   |
| 2    | Customer submits payment proof                     | Submission acknowledgement is shown | Actual Result matches Expected | Pass   |
| 3    | Admin confirms proof in verification queue         | Deposit status updates to confirmed | Actual Result matches Expected | Pass   |


- **Proof of Execution (Screenshots required):**
  - Customer payment instruction card before upload.
  - Customer proof upload completion state.
  - Admin verification queue with submitted proof.
  - Admin confirmation action result.
  - Updated customer/admin order status after confirmation.

#### TB3-TS-03 — Deposit rejection and re-submission path


| Step | Input Data                                         | Expected Result                               | Actual Result                  | Status |
| ---- | -------------------------------------------------- | --------------------------------------------- | ------------------------------ | ------ |
| 1    | Admin opens submitted payment proof                | Proof details are visible for review          | Actual Result matches Expected | Pass   |
| 2    | Admin rejects payment proof                        | Rejection status is recorded and shown        | Actual Result matches Expected | Pass   |
| 3    | Customer reopens payment area and re-submits proof | Corrected proof can be submitted successfully | Actual Result matches Expected | Pass   |


- **Proof of Execution (Screenshots required):**
  - Admin proof review screen before rejection.
  - Rejection confirmation/result state.
  - Customer view showing rejection feedback.
  - Customer re-submission screen state.

#### TB3-TS-04 — Assignment gating by deposit status


| Step | Input Data                                | Expected Result                                      | Actual Result                  | Status |
| ---- | ----------------------------------------- | ---------------------------------------------------- | ------------------------------ | ------ |
| 1    | Admin attempts assignment on unpaid order | Assignment is blocked with guidance message          | Actual Result matches Expected | Pass   |
| 2    | Admin opens payment-confirmed order       | Assignment action becomes available                  | Actual Result matches Expected | Pass   |
| 3    | Admin assigns artisan and saves           | Assignment appears in admin/artisan assignment views | Actual Result matches Expected | Pass   |


- **Proof of Execution (Screenshots required):**
  - Failed assignment attempt state on unpaid order.
  - Payment-confirmed order state.
  - Successful assignment form submission.
  - Assignment visible in admin or artisan list view.

#### TB3-TS-05 — Artisan production progression


| Step | Input Data                        | Expected Result                             | Actual Result                  | Status |
| ---- | --------------------------------- | ------------------------------------------- | ------------------------------ | ------ |
| 1    | Artisan opens assigned job detail | Assignment details and controls are visible | Actual Result matches Expected | Pass   |
| 2    | Artisan taps `Start Production`   | Job status changes to in-production         | Actual Result matches Expected | Pass   |
| 3    | Artisan taps `Ready for Review`   | Job status changes to ready-for-review      | Actual Result matches Expected | Pass   |


- **Proof of Execution (Screenshots required):**
  - Artisan job detail before start.
  - Job state immediately after start.
  - Job state after ready-for-review action.
  - Artisan jobs list reflecting latest state.

#### TB3-TS-06 — Admin QC approval


| Step | Input Data                               | Expected Result                              | Actual Result                  | Status |
| ---- | ---------------------------------------- | -------------------------------------------- | ------------------------------ | ------ |
| 1    | Admin opens order detail at review stage | QC approval action is available              | Actual Result matches Expected | Pass   |
| 2    | Admin performs `QC Approve`              | Order status updates to ready/handover state | Actual Result matches Expected | Pass   |
| 3    | Customer/admin recheck order status      | Ready state is visible in progression views  | Actual Result matches Expected | Pass   |


- **Proof of Execution (Screenshots required):**
  - Admin order detail before QC approve.
  - QC approve confirmation/action state.
  - Order detail after QC approval with ready status.
  - Customer-facing status state after QC completion.

#### TB3-TS-07 — Reference image bridge to artisan


| Step | Input Data                                                 | Expected Result                                     | Actual Result                  | Status |
| ---- | ---------------------------------------------------------- | --------------------------------------------------- | ------------------------------ | ------ |
| 1    | Admin assigns artisan and selects inquiry reference images | Assignment saves with selected references           | Actual Result matches Expected | Pass   |
| 2    | Artisan opens assigned job detail                          | Selected reference images are visible in job detail | Actual Result matches Expected | Pass   |
| 3    | Artisan reviews references in context                      | Visual references support production understanding  | Actual Result matches Expected | Pass   |


- **Proof of Execution (Screenshots required):**
  - Admin assignment form with selected references.
  - Assignment success state.
  - Artisan job detail showing linked references.

## 3) Primary UI Design Screens (Dark-Gold Showcase)

This section is for design presentation evidence only (separate from QA test proof).


| Screen Name                      | Design Objective                             | Key UI Elements To Highlight                                          | Screenshot Placeholder           |
| -------------------------------- | -------------------------------------------- | --------------------------------------------------------------------- | -------------------------------- |
| Customer Deposit Screen/Card     | Build trust during payment proof submission  | Clear payable amount, upload action prominence, status badges         | `[Insert Screenshot: TB3-UI-01]` |
| Admin Payment Verification Queue | Enable rapid and confident financial triage  | Pending/review badges, proof preview layout, decision actions         | `[Insert Screenshot: TB3-UI-02]` |
| Admin Artisan Assignment Modal   | Make production handoff structured and clear | Artisan selector, task scope, deadline/priorities, reference selector | `[Insert Screenshot: TB3-UI-03]` |
| Artisan Jobs List                | Highlight actionable workshop workload view  | Status-first cards, priority/deadline cues, concise metadata          | `[Insert Screenshot: TB3-UI-04]` |
| Artisan Job Detail               | Support execution confidence                 | Specs section, reference image panel, milestone action controls       | `[Insert Screenshot: TB3-UI-05]` |
| Admin QC Review State            | Emphasize final quality checkpoint           | QC approval controls, order readiness indicators, audit clarity       | `[Insert Screenshot: TB3-UI-06]` |


## 4) Technical Diagrams

- Use Case Diagram: `diagrams/chapter5/timebox3_fulfillment_artisan_usecase.puml`
- Class Diagram: `diagrams/chapter5/timebox3_fulfillment_artisan_class.puml`

## 5) Diagram Descriptions (Business-Oriented)

### 5.1 Use Case Diagram Explanation

#### Element: Customer


| Field                            | Description                                                |
| -------------------------------- | ---------------------------------------------------------- |
| Business Purpose (Why)           | Keeps client involved in bespoke order progression         |
| Business Behavior (What it does) | Submits required payment proof and monitors order movement |


#### Element: Admin


| Field                            | Description                                                           |
| -------------------------------- | --------------------------------------------------------------------- |
| Business Purpose (Why)           | Provides operational control and quality assurance                    |
| Business Behavior (What it does) | Validates payments, assigns work, and performs final quality approval |


#### Element: Artisan


| Field                            | Description                                            |
| -------------------------------- | ------------------------------------------------------ |
| Business Purpose (Why)           | Represents production execution capability             |
| Business Behavior (What it does) | Produces jewelry and reports when work is review-ready |


#### Element: Accept Latest Quote (Create/Supersede Order)


| Field                            | Description                                             |
| -------------------------------- | ------------------------------------------------------- |
| Business Purpose (Why)           | Keeps only one active fulfillment path per inquiry      |
| Business Behavior (What it does) | Activates the latest commercial agreement for execution |


#### Element: Upload Deposit Proof


| Field                            | Description                                 |
| -------------------------------- | ------------------------------------------- |
| Business Purpose (Why)           | Confirms customer payment intent            |
| Business Behavior (What it does) | Sends proof needed before production starts |


#### Element: Verify Payment (Confirm/Reject)


| Field                            | Description                                    |
| -------------------------------- | ---------------------------------------------- |
| Business Purpose (Why)           | Protects financial governance                  |
| Business Behavior (What it does) | Approves valid payments or requests correction |


#### Element: Assign Artisan


| Field                            | Description                                  |
| -------------------------------- | -------------------------------------------- |
| Business Purpose (Why)           | Connects order to workshop capacity          |
| Business Behavior (What it does) | Allocates the job to responsible craft staff |


#### Element: Attach Reference Images


| Field                            | Description                                            |
| -------------------------------- | ------------------------------------------------------ |
| Business Purpose (Why)           | Reduces production ambiguity                           |
| Business Behavior (What it does) | Sends customer design references into workshop context |


#### Element: Start Production


| Field                            | Description                                               |
| -------------------------------- | --------------------------------------------------------- |
| Business Purpose (Why)           | Marks operational work kickoff                            |
| Business Behavior (What it does) | Changes order from waiting state to active making process |


#### Element: Mark Ready for Review


| Field                            | Description                                     |
| -------------------------------- | ----------------------------------------------- |
| Business Purpose (Why)           | Signals production completion for inspection    |
| Business Behavior (What it does) | Passes item from artisan to admin quality check |


#### Element: Approve QC


| Field                            | Description                                      |
| -------------------------------- | ------------------------------------------------ |
| Business Purpose (Why)           | Protects brand quality before customer handover  |
| Business Behavior (What it does) | Confirms item is ready for collection or release |


### 5.2 Class Diagram Explanation

#### Element: Quote


| Field                            | Description                                             |
| -------------------------------- | ------------------------------------------------------- |
| Business Purpose (Why)           | Holds the commercial agreement being considered         |
| Business Behavior (What it does) | Becomes the source for active fulfillment when accepted |


#### Element: Order


| Field                            | Description                                   |
| -------------------------------- | --------------------------------------------- |
| Business Purpose (Why)           | Tracks end-to-end fulfillment execution       |
| Business Behavior (What it does) | Stores current production and readiness stage |


#### Element: Payment


| Field                            | Description                                                          |
| -------------------------------- | -------------------------------------------------------------------- |
| Business Purpose (Why)           | Tracks monetary steps needed for fulfillment                         |
| Business Behavior (What it does) | Records deposit and final payment outcomes with current review state |


#### Element: OrderAssignment


| Field                            | Description                                            |
| -------------------------------- | ------------------------------------------------------ |
| Business Purpose (Why)           | Formalizes who in workshop owns the work               |
| Business Behavior (What it does) | Stores artisan ownership, scope, and assignment status |


#### Element: InquiryImageCandidate


| Field                            | Description                                |
| -------------------------------- | ------------------------------------------ |
| Business Purpose (Why)           | Retains customer visual intent references  |
| Business Behavior (What it does) | Preserves images that can guide production |


#### Element: OrderAssignmentReferenceImage


| Field                            | Description                                           |
| -------------------------------- | ----------------------------------------------------- |
| Business Purpose (Why)           | Connects selected references to a specific assignment |
| Business Behavior (What it does) | Ensures artisans see the right design materials       |


#### Element: OrderBuilderService


| Field                            | Description                                        |
| -------------------------------- | -------------------------------------------------- |
| Business Purpose (Why)           | Keeps order activation rules consistent            |
| Business Behavior (What it does) | Converts accepted quote into active execution path |


#### Element: OrderLifecycleService


| Field                            | Description                                                    |
| -------------------------------- | -------------------------------------------------------------- |
| Business Purpose (Why)           | Prevents invalid stage movement                                |
| Business Behavior (What it does) | Applies stage rules for assignment, production, review, and QC |


#### Element: PaymentVerificationService


| Field                            | Description                                                        |
| -------------------------------- | ------------------------------------------------------------------ |
| Business Purpose (Why)           | Protects financial reliability before production                   |
| Business Behavior (What it does) | Applies confirm and reject decisions and updates order progression |


