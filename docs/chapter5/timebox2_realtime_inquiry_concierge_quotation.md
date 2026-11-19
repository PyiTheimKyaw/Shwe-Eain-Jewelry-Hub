# Timebox 2 — Realtime Inquiry & Concierge Quotation

## Timebox Summary (Implementation Overview)

Timebox 2 transformed the platform from a static catalog experience into an active concierge workflow by implementing realtime consultation and quotation capabilities end-to-end. The design-to-development sequence began with inquiry lifecycle modeling and API endpoints for case creation, then expanded into synchronized customer-admin conversation flows using Firebase-backed realtime messaging integrated with Laravel authorization and thread orchestration. This created a single operational conversation context where customer intent, media attachments, and staff responses remain continuously visible across both the mobile and admin interfaces.

On top of the communication layer, quotation delivery features were implemented to move inquiries into commercial decision points: admin users can draft and publish structured quotes, and customers can accept or decline within the same conversation journey. Supporting logic services and pipeline presenters were introduced to keep stage transitions, timeline visibility, and chat-card rendering consistent across channels. Testing was initiated as soon as these integrations were stable, with manual QA execution covering inquiry creation, realtime message propagation, quote publish and decision flows, and validation handling, ensuring the module met both responsiveness and process-quality expectations.

## 1) Functional Overview

### Detailed feature categorization

#### A. Inquiry intake module
- Customer can open a new consultation request and provide message context.
- Customer can attach supporting images when explaining design intent.
- Inquiry list gives customers a centralized view of their ongoing conversations.
- Inquiry status visibility helps customers understand progress without calling staff.

#### B. Concierge communication module
- Customer and admin concierge communicate inside one shared conversation thread.
- Realtime message updates reduce response delay and improve service confidence.
- Admin chat console supports active triage and immediate customer follow-up.
- Conversation continuity keeps history available for later quoting decisions.

#### C. Quotation management module
- Admin can prepare and publish a formal quote from the inquiry context.
- Customer can clearly accept or decline the offered quote.
- Quote decision directly drives the next business stage (move forward or revise).

#### D. Business workflow and control logic
- Inquiry pipeline stage is kept synchronized so staff and customer view consistent progress.
- Timeline-style activity improves auditability of key customer journey events.
- Shared thread behavior avoids communication split between customer and admin tools.

#### E. UI/UX enhancements delivered in this timebox
- Concierge chat experience aligns with luxury tone using Dark-Gold visual language.
- Quote cards present commercial decisions clearly inside the chat experience.
- Status indicators reduce ambiguity and improve trust in process transparency.

## 2) Testing Suite

### 2.1 Test Plan Table (Manual QA, black-box)

| Test ID | Scenario | Preconditions | Test Data | Tester Actions (UI) | Expected UI Outcome | Priority |
|---|---|---|---|---|---|---|
| TB2-TP-01 | Customer creates a new inquiry | Customer account logged in | Inquiry title/context and optional image | Tester taps New Inquiry, completes fields, submits | New inquiry appears in inquiry list with initial status badge | High |
| TB2-TP-02 | Customer and admin exchange realtime messages | Inquiry exists and is open in both interfaces | Short sample messages from both sides | Tester sends message from customer app, then replies from admin console | Both messages appear in each side's chat without manual re-entry | High |
| TB2-TP-03 | Admin publishes quote from inquiry | Inquiry already active with conversation context | Quote line descriptions and amounts | Tester opens admin inquiry chat/context, creates and publishes quote | Customer sees quote card in chat with decision actions | High |
| TB2-TP-04 | Customer accepts quote | Published quote visible in customer chat | Customer acceptance action | Tester taps Accept on quote card and confirms | Inquiry pipeline moves forward and customer sees updated stage | High |
| TB2-TP-05 | Customer declines quote | Published quote visible in customer chat | Customer decline reason (optional) | Tester taps Decline on quote card and confirms | Quote card state updates to declined/closed and pipeline reflects non-acceptance | Medium |
| TB2-TP-06 | Validation feedback on incomplete inquiry | Customer logged in | Missing required inquiry message | Tester opens New Inquiry, leaves required field blank, taps Submit | Form remains on screen and shows clear validation message | Medium |

### 2.2 Test Scripts (Manual QA execution)

#### TB2-TS-01 — Create new inquiry with optional attachment
| Step | Input Data | Expected Result | Actual Result | Status |
|---|---|---|---|---|
| 1 | Customer taps `New Inquiry` | Inquiry form opens | Actual Result matches Expected | Pass |
| 2 | Customer enters inquiry message/subject | Form accepts input | Actual Result matches Expected | Pass |
| 3 | Customer optionally attaches image and taps `Submit` | Inquiry is created and success state appears | Actual Result matches Expected | Pass |
| 4 | Customer returns to inquiry list/detail | New inquiry appears with visible status badge | Actual Result matches Expected | Pass |
- **Proof of Execution (Screenshots required):**
  - New Inquiry form with data entered.
  - Attachment preview state (if attached).
  - Success state after submission.
  - Inquiry list/detail showing newly created inquiry.

#### TB2-TS-02 — Realtime customer-admin conversation
| Step | Input Data | Expected Result | Actual Result | Status |
|---|---|---|---|---|
| 1 | Customer sends message A | Message appears in customer thread | Actual Result matches Expected | Pass |
| 2 | Admin opens same inquiry and replies with message B | Admin reply posts successfully | Actual Result matches Expected | Pass |
| 3 | Customer observes updated thread | Message B appears in customer chat in correct sequence | Actual Result matches Expected | Pass |
- **Proof of Execution (Screenshots required):**
  - Customer message sent state.
  - Admin console showing customer message.
  - Admin reply sent state.
  - Customer chat showing admin reply.

#### TB2-TS-03 — Admin publishes quote
| Step | Input Data | Expected Result | Actual Result | Status |
|---|---|---|---|---|
| 1 | Admin opens quote creation from inquiry chat | Quote form opens | Actual Result matches Expected | Pass |
| 2 | Admin enters line items and totals | Form accepts quote details | Actual Result matches Expected | Pass |
| 3 | Admin taps `Publish Quote` | Quote card appears in customer chat | Actual Result matches Expected | Pass |
| 4 | Customer/admin view inquiry stage | Stage reflects quotation state | Actual Result matches Expected | Pass |
- **Proof of Execution (Screenshots required):**
  - Admin quote form with completed values.
  - Admin post-publish confirmation state.
  - Customer chat showing published quote card.
  - Inquiry stage indicator after publish.

#### TB2-TS-04 — Customer accepts quote
| Step | Input Data | Expected Result | Actual Result | Status |
|---|---|---|---|---|
| 1 | Customer taps `Accept` on quote card | Confirmation prompt/state appears (if configured) | Actual Result matches Expected | Pass |
| 2 | Customer confirms acceptance | Quote state changes to accepted | Actual Result matches Expected | Pass |
| 3 | Customer views pipeline/chat state | Progression reflects acceptance outcome | Actual Result matches Expected | Pass |
- **Proof of Execution (Screenshots required):**
  - Quote card before acceptance.
  - Acceptance confirmation prompt (if applicable).
  - Quote card after acceptance.
  - Pipeline stage after acceptance.

#### TB2-TS-05 — Customer declines quote
| Step | Input Data | Expected Result | Actual Result | Status |
|---|---|---|---|---|
| 1 | Customer taps `Decline` on quote card | Decline confirmation prompt/state appears | Actual Result matches Expected | Pass |
| 2 | Customer confirms decline | Quote state changes to declined | Actual Result matches Expected | Pass |
| 3 | Customer checks pipeline/status | Non-acceptance state is clearly shown | Actual Result matches Expected | Pass |
- **Proof of Execution (Screenshots required):**
  - Quote card before decline.
  - Decline confirmation prompt (if applicable).
  - Quote card after decline.
  - Pipeline/status state after decline.

#### TB2-TS-06 — Incomplete inquiry validation
| Step | Input Data | Expected Result | Actual Result | Status |
|---|---|---|---|---|
| 1 | Customer leaves required message field blank | Form remains editable | Actual Result matches Expected | Pass |
| 2 | Customer taps `Submit` | Validation message appears near required field | Actual Result matches Expected | Pass |
| 3 | Customer checks inquiry list | New inquiry is not created | Actual Result matches Expected | Pass |
- **Proof of Execution (Screenshots required):**
  - New Inquiry form before submit with missing data.
  - Validation message state after submit.

## 3) Primary UI Design Screens (Dark-Gold Showcase)

This section is for design presentation evidence only (separate from QA test proof).

| Screen Name | Design Objective | Key UI Elements To Highlight | Screenshot Placeholder |
|---|---|---|---|
| Customer Inquiry List | Show premium communication hub for customers | Dark-Gold cards, status badges, clear call-to-action button | `[Insert Screenshot: TB2-UI-01]` |
| Customer Inquiry Chat | Convey concierge-style premium assistance | Gold/customer message bubbles, elegant timestamps, media support | `[Insert Screenshot: TB2-UI-02]` |
| Customer Quote Card View | Present quote decisions with clarity and confidence | Prominent totals, accept/decline controls, readable hierarchy | `[Insert Screenshot: TB2-UI-03]` |
| Admin Concierge Chat Console | Support efficient high-touch response from staff | Conversation panel, composer area, inquiry context side panel | `[Insert Screenshot: TB2-UI-04]` |
| Admin Quote Publish Form | Ensure professional quote creation workflow | Structured line items, totals section, publish action visibility | `[Insert Screenshot: TB2-UI-05]` |

## 4) Technical Diagrams

- Use Case Diagram: `diagrams/chapter5/timebox2_inquiry_quote_usecase.puml`
- Class Diagram: `diagrams/chapter5/timebox2_inquiry_quote_class.puml`

## 5) Diagram Descriptions (Business-Oriented)

### 5.1 Use Case Diagram Explanation

#### Element: Customer
| Field | Description |
|---|---|
| Business Purpose (Why) | Enables direct service request from buyer to shop |
| Business Behavior (What it does) | Starts inquiries, chats with staff, and makes quote decisions |

#### Element: Admin
| Field | Description |
|---|---|
| Business Purpose (Why) | Represents concierge and sales operations |
| Business Behavior (What it does) | Responds to customer needs and prepares commercial offers |

#### Element: Create Inquiry
| Field | Description |
|---|---|
| Business Purpose (Why) | Captures customer intent early |
| Business Behavior (What it does) | Opens a formal service case for follow-up |

#### Element: Request Chat Access
| Field | Description |
|---|---|
| Business Purpose (Why) | Protects private customer conversation space |
| Business Behavior (What it does) | Allows only authorized participants into the inquiry chat |

#### Element: Exchange Realtime Messages
| Field | Description |
|---|---|
| Business Purpose (Why) | Improves responsiveness and conversion confidence |
| Business Behavior (What it does) | Shows live back-and-forth communication in one thread |

#### Element: Publish Quote
| Field | Description |
|---|---|
| Business Purpose (Why) | Moves consultation into a commercial proposal stage |
| Business Behavior (What it does) | Presents a formal offer customer can act on |

#### Element: Accept Quote
| Field | Description |
|---|---|
| Business Purpose (Why) | Converts interest into confirmed progress |
| Business Behavior (What it does) | Advances the customer journey to fulfillment preparation |

#### Element: Decline Quote
| Field | Description |
|---|---|
| Business Purpose (Why) | Supports non-conversion handling professionally |
| Business Behavior (What it does) | Closes or reroutes the case without ambiguity |

#### Element: Refresh Pipeline / Timeline
| Field | Description |
|---|---|
| Business Purpose (Why) | Maintains operational transparency |
| Business Behavior (What it does) | Keeps both sides informed of current inquiry stage |

### 5.2 Class Diagram Explanation

#### Element: Inquiry
| Field | Description |
|---|---|
| Business Purpose (Why) | Tracks each customer consultation as a managed case |
| Business Behavior (What it does) | Stores who requested help and where they are in the process |

#### Element: InquiryAttachment
| Field | Description |
|---|---|
| Business Purpose (Why) | Preserves customer reference context |
| Business Behavior (What it does) | Stores supporting images used during discussion |

#### Element: Quote
| Field | Description |
|---|---|
| Business Purpose (Why) | Represents a formal business offer |
| Business Behavior (What it does) | Records offer value and decision status |

#### Element: QuoteLineItem
| Field | Description |
|---|---|
| Business Purpose (Why) | Improves transparency of the offer |
| Business Behavior (What it does) | Breaks a quote into understandable components |

#### Element: FirebaseInquiryService
| Field | Description |
|---|---|
| Business Purpose (Why) | Keeps communication timely across interfaces |
| Business Behavior (What it does) | Synchronizes chat and event updates for customer and admin visibility |

#### Element: QuoteBuilderService
| Field | Description |
|---|---|
| Business Purpose (Why) | Ensures quote preparation is consistent |
| Business Behavior (What it does) | Structures quote details into a customer-ready offer |

#### Element: InquiryPipelinePresenter
| Field | Description |
|---|---|
| Business Purpose (Why) | Keeps stage communication clear |
| Business Behavior (What it does) | Produces a clear status view for apps and admin panel |

