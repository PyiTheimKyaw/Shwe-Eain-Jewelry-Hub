# Demo Script Checklist

Simple, presentation-ready runbook with clear role flow.

## 0) Pre-Demo Setup (once)

- Start backend and app environments.
- Prepare login accounts:
  - Admin (Filament)
  - Artisan (mobile)
  - Customer (mobile)
- Use one real test inquiry/order so all three roles see the same data.

## 1) Admin Flow (Create the source of truth)

- Open Filament inquiry chat for the customer.
- Publish a quote with at least 1 line item and fill:
  - `weight_grams`
  - `metal_type`
  - `stone_details`
  - optional product reference (with images)
- Confirm quote is sent and visible in chat timeline.
- After customer accepts, open the created order.
- Send deposit payment instructions.
- After customer uploads proof, confirm payment.
- Assign artisan:
  - choose `task_scope` (`line_item` or `full_order`)
  - add Special instructions
- Expected demo point: Admin enters specs/instructions once; data is persisted and reused by other roles.

## 2) Artisan Flow (See full making context)

- Login as artisan and open assigned job detail.
- Show visible sections:
  - Assignment status + order status
  - Admin Instructions
  - Making Specs (title/type, weight, metal type, stone details)
  - Design Photos gallery
- If assignment is `line_item`, verify only scoped item is shown.
- Start job (if in `deposit_confirmed` state).
- Upload progress photo with:
  - stage selection
  - short note
- Mark ready for review.
- Expected demo point: Artisan has complete guidance without asking admin.

## 3) Customer Flow (Transparency view)

- Login as customer and open Orders.
- Open Order Details from the order card.
- Show that customer sees the same:
  - specs (weight/metal/stone)
  - design photos
  - relevant instruction context
- Open Timeline and show milestones/progress updates.
- Show artisan update photo + stage/note appearing in chat/timeline context.
- Expected demo point: Customer sees the same canonical data the artisan uses.

## 4) Three-Way Sync Proof (say this explicitly)

- Admin wrote specs once.
- Artisan sees exactly those specs and photos.
- Customer sees the same specs/photos in Order Detail.
- Progress updates flow back to customer timeline/chat.

## 5) Optional 30-second QA Confirmation (end slide)

- Backend tests passed: `BespokeOrderWorkflowTest`
- Flutter analyze passed on modified artisan/order files
- Documentation updated: `docs/FYP_Technical_Log.md`, `docs/Local_Setup.md`
