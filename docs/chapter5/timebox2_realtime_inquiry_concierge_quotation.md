# Timebox 2 — Realtime Inquiry & Concierge Quotation

## 1) Functional Overview

### Implemented functions in this timebox
- Implemented customer inquiry creation with optional product linkage and attachments.
- Implemented Firebase thread bootstrap/token endpoints for secure realtime chat sessions.
- Implemented Flutter inquiry list + Firestore-backed chat interface.
- Implemented Filament admin inquiry chat console on the same Firebase thread as customer.
- Implemented quote publication from admin and quote accept/decline from customer.
- Implemented inquiry pipeline/timeline synchronization for consistent state across REST and chat.

## 2) Testing Suite

### 2.1 Test Plan Table

| Test ID | Scenario | Requirement Link |
|---|---|---|
| TB2-TP-01 | Customer can create inquiry with `firebase_thread_id` generated | `backend/app/Http/Controllers/Api/V1/InquiryController.php` |
| TB2-TP-02 | Backend mints Firebase custom token for inquiry chat | `backend/app/Http/Controllers/Api/V1/ConversationController.php` |
| TB2-TP-03 | Customer and admin can exchange realtime messages in same thread | `frontend/lib/features/inquiries/ui/inquiry_chat_screen.dart`, `backend/resources/views/filament/resources/inquiries/chat-inquiry.blade.php` |
| TB2-TP-04 | Admin can publish quote and customer can accept/decline | `backend/app/Http/Controllers/Api/V1/Admin/InquiryQuotePublishController.php`, `backend/app/Http/Controllers/Api/V1/CustomerQuoteController.php` |
| TB2-TP-05 | Pipeline and timeline status remain synchronized after quote decisions | `backend/app/Services/InquiryPipelinePresenter.php` |

### 2.2 Test Script Table

| Step | Input Data | Expected Result | Actual Result | Status |
|---|---|---|---|---|
| 1 | Customer posts new inquiry (`POST /api/v1/inquiries`) | Inquiry stored with valid `firebase_thread_id` | Actual Result matches Expected | Pass |
| 2 | Customer requests chat token (`POST /api/v1/conversations/{id}/firebase-token`) | Firebase custom token + thread id returned | Actual Result matches Expected | Pass |
| 3 | Customer sends Firestore text message | Message persists under `inquiry_threads/{threadId}/messages` | Actual Result matches Expected | Pass |
| 4 | Admin opens Filament chat for same inquiry and replies | Reply appears in customer chat in realtime | Actual Result matches Expected | Pass |
| 5 | Admin publishes quote from inquiry context | Quote status `sent`, inquiry moves to quoted stage | Actual Result matches Expected | Pass |
| 6 | Customer accepts/declines quote | Accept creates order; decline sets quote void and updates inquiry flow | Actual Result matches Expected | Pass |

## 3) Visual Proofs (Screenshot Insertion Guide)

- **Customer - New Inquiry Screen**: form with message and optional image.
- **Customer - Inquiry List Screen**: list with status badges.
- **Customer - Chat Interface**: realtime thread with customer and admin messages.
- **Admin - Inquiry List (Filament)**: triage table showing inquiry statuses.
- **Admin - Concierge Chat Console**: live message panel and compose box.
- **Admin - Quote Creation/Publish Modal (from chat)**: line-item quote form.
- **Customer - Quote Card in Chat**: accept/decline controls and totals.
- **Customer - Pipeline Stepper View**: step transitions after quote action.

## 4) Technical Diagrams

- Use Case Diagram: `diagrams/chapter5/timebox2_inquiry_quote_usecase.puml`
- Class Diagram: `diagrams/chapter5/timebox2_inquiry_quote_class.puml`

## 5) Diagram Descriptions

### 5.1 Use Case Diagram Elements

| Element | Description |
|---|---|
| Customer Actor | Initiates inquiries, chats with concierge, and decides on quotes. |
| Admin Actor | Operates concierge chat and publishes quotes. |
| Create Inquiry | Entry point to bespoke journey with optional product context. |
| Exchange Messages | Realtime bilateral communication over Firebase thread. |
| Publish Quote | Admin quotation action producing a customer-facing quote state. |
| Accept/Decline Quote | Customer commitment decision driving next workflow branch. |

### 5.2 Class Diagram Elements

| Element | Description |
|---|---|
| `Inquiry` | MySQL source-of-truth record for customer consultation thread. |
| `InquiryAttachment` | Media artifact linked to inquiry. |
| `Quote` / `QuoteLineItem` | Formal commercial offer and line-level pricing breakdown. |
| `ConversationController` | API boundary that mints Firebase custom auth tokens. |
| `FirebaseInquiryService` | Backend integration layer for token minting and thread sync. |
| `InquiryPipelinePresenter` | Derives normalized inquiry workflow metadata for clients. |
| `QuoteBuilderService` | Computes and persists quote line/totals from snapshot pricing context. |

