# Product Requirements Document (PRD)

# CourtOS

### White-Label Court Revenue Operating System for Pickleball Facilities

**Version:** 1.1 (consolidated)
**Last Updated:** 2026-06-11

---

## 1. Executive Summary

CourtOS is a multi-tenant, white-label platform that helps pickleball court operators increase court utilization, automate bookings, reduce administrative workload, and maximize revenue.

The product is not a booking system. Booking is one component of a larger operating system designed to ensure that court inventory is sold efficiently and that facilities require minimal manual intervention to operate.

**Product definition:** CourtOS is a revenue optimization platform for court-based businesses. Success is measured by more court hours sold, less staff time required, higher facility revenue, and higher court utilization.

---

## 2. Problem Statement

Pickleball facilities generate revenue from court time. Court time is a finite inventory — every unbooked court hour is lost revenue.

Today, operators typically manage reservations through Facebook Messenger, WhatsApp, phone calls, spreadsheets, and generic booking tools. This creates:

- Manual scheduling overhead
- Double bookings
- Payment reconciliation work
- High cancellation impact
- Poor visibility into utilization
- Unsold court inventory

Operators need a system that automates operations while maximizing court occupancy.

---

## 3. Vision & Product Principles

**Vision:** Enable court operators to run facilities with minimal staff involvement while maximizing utilization and revenue through automation.

### Principle 1: Revenue First

Every feature must increase utilization, increase revenue, or reduce operational cost. If it does none of these, it is not a priority.

### Principle 2: Inventory Optimization

Court hours are inventory. The system should maximize the percentage of available court hours that are sold.

### Principle 3: Automation Over Management

Operators should not manage bookings. The system should manage bookings.

---

## 4. Target Customers & Roles

### Primary Customer: Facility Operators

Pickleball clubs, sports centers, resorts, country clubs, private court operators, and franchise operators.

### Secondary Customer: Players

Goals: find available courts, book quickly, pay online, receive confirmation.

### System Roles

| Role | Description | Permissions |
|---|---|---|
| Super Admin | Platform owner | Manage tenants, manage subscriptions, view system analytics |
| Operator | Facility administrator | Manage courts, bookings, schedules; view reports |
| Staff | Facility staff member | View bookings, manage check-ins, limited operational controls |
| Player | Customer | Create and view bookings, manage personal reservations |

---

## 5. Success Metrics

| Metric | Formula | Target |
|---|---|---|
| Facility Utilization Rate | Booked court hours ÷ Available court hours | 70%+ |
| Booking Automation Rate | Bookings completed without staff intervention | 90%+ |
| Revenue Recovery Rate | Recovered cancellations ÷ Total cancellations | 50%+ |
| Booking Completion Rate | Completed checkouts ÷ Started checkouts | 85%+ |
| Average Booking Time | Time from opening booking page to confirmation | < 30 seconds |

---

## 6. MVP Scope

### Module 1: Court Inventory Management

**Purpose:** Define and manage court inventory.

Features:

- Create, edit, and archive courts
- Configure operating hours
- Set pricing
- Set availability

Acceptance criteria:

- Support 20+ courts per facility
- Real-time availability updates

### Module 2: Real-Time Booking Engine

**Purpose:** Sell court inventory.

Player flow: Select Date → Select Court → Select Time → Checkout → Confirmation

Features:

- Real-time availability
- Reservation creation, modification, and cancellation
- Guest self-service cancellation (no login) by booking reference + email
- Booking history

Acceptance criteria:

- No double bookings
- Booking completed in under 30 seconds

#### Guest Self-Service Cancellation

Players book and cancel without an account. After checkout, the player lands
on a confirmation screen showing the unique booking reference (also emailed in
the confirmation). To cancel later, the player enters that reference plus the
email they booked under on the public cancellation page — no login required.

- The reference is an unguessable UUID; requiring a matching email is the
  anti-abuse control. Errors never reveal whether a reference exists.
- Only `CONFIRMED` bookings can be cancelled this way. Unpaid `PENDING_PAYMENT`
  holds expire on their own and are not cancellable through this flow.
- A guest cancellation frees the slot immediately and notifies the waitlist
  (Module 4). Per the refund rules in section 9, player-initiated cancellation
  does **not** auto-refund in the MVP — refunds remain an operator action.

### Module 3: Payments

**Purpose:** Collect revenue automatically.

Features:

- Online payment
- Deposit or full payment
- Payment confirmation

Acceptance criteria:

- Payment updates booking status automatically
- Failed payments release inventory

(Full payment architecture in section 9.)

#### Deposit + Pay-on-Site Balance

Each court has an optional online **deposit**. When set (a value below the slot
price), the player pays only the deposit online to confirm the booking; the
remaining **balance is paid on-site** at the facility. When no deposit is
configured, the full slot price is collected online (default behaviour).

- The PRD rule from section 9 is preserved: a booking is still confirmed only
  by a verified webhook for the **deposit** payment — never a frontend success
  message. The deposit secures the slot and reduces no-shows.
- The booking stores the full slot price (`amount`) and the online portion
  (`deposit_amount`); the balance due is the difference. The operator's
  booking list surfaces the on-site balance so front desk knows what to collect.
- Fully unpaid, reserve-only bookings are intentionally **not** supported — a
  zero-commitment hold reintroduces the no-show problem this product exists to
  solve. A deposit is the minimum commitment that keeps inventory protected.

#### Party Size & Court Suggestion

Each booking captures an **optional** player headcount (`party_size`, nullable),
and each court has a configurable **capacity** (players per court; default 4 for
pickleball doubles).

Why capture it (first-principles): "how many players?" is a proxy for three
real concerns — (1) **experience/retention**: too many players on one court
means most stand idle and don't return; (2) **revenue & inventory optimization**
(Principles 1 and 2): a large party is a signal to sell more court-hours; and
(3) **operational prep**: front desk staffing and equipment.

How it's used:

- When a player enters a headcount larger than a court's capacity, the booking
  page shows a non-blocking suggestion of how many courts would fit the group:
  `ceil(party_size ÷ capacity)` (e.g. 14 players at capacity 4 → suggest ~4
  courts).
- The suggestion is **purely informational**. Players are never forced to book
  multiple courts, and the field is never required — a solo player books in
  exactly the same frictionless flow. The booking always reserves only the one
  slot the player selected; additional courts are booked separately.
- Operators see the headcount on the bookings list for staffing and equipment
  planning.

Multi-court booking is supported (see below), so the suggestion is actionable:
a group can select the suggested number of courts and pay for them together.

#### Multi-Court Booking (time × court grid)

The public booking page is a **time × court grid**: rows are time slots,
columns are courts, and each open cell is selectable. Players tap one or more
cells (across courts and/or times) to build a selection, then pay for all of
them in a **single checkout**. A solo player simply selects one cell.

- **One payment, many bookings.** Slots booked together share a `group_id`;
  one `payment` (linked to a representative booking) covers the group, charging
  the sum of each slot's deposit/price. The webhook confirms every booking in
  the group at once. Each booking keeps its own reference, so a player can
  cancel an individual court later.
- **Best-effort checkout.** If a selected slot is taken before payment, it is
  dropped, the rest are still booked, and the player is **only charged for the
  slots actually secured**. The dropped slots are shown on the success screen
  ("couldn't book — taken during checkout; you were not charged"). If *every*
  selected slot is gone, nothing is booked and the player is asked to re-pick.
- This supersedes the earlier MVP decision to exclude multi-court booking; the
  grid keeps single-slot booking just as fast while enabling group bookings.

### Module 4: Automated Cancellation Recovery

**Purpose:** Recover lost revenue from cancellations. A cancellation creates unsold inventory.

When a cancellation occurs:

1. Slot becomes available immediately
2. Waitlist is notified
3. Players receive a booking opportunity
4. First player to confirm receives the slot

Features: waitlists, automated notifications, one-click booking.

Acceptance criteria:

- Waitlist processing occurs automatically
- Notifications sent within seconds

### Module 5: No-Show Reduction

**Purpose:** Reduce unused booked inventory.

Features: booking reminders, payment reminders, confirmation reminders.

Acceptance criteria: automated reminders sent before each reservation.

### Module 6: Operator Dashboard

**Purpose:** Give operators actionable business intelligence.

Sections:

- **Revenue Overview** — daily, weekly, monthly revenue
- **Utilization Overview** — court utilization %, peak hours, low-demand hours
- **Booking Insights** — total bookings, cancellation rate, recovery rate

Acceptance criteria: dashboard loads within 2 seconds.

---

## 7. Multi-Tenancy & White-Label Requirements

CourtOS is a SaaS platform. It must support multiple organizations, multiple facilities, independent branding, independent billing, and independent domains. The platform must onboard new customers without engineering involvement.

### Tenant Model

Each customer is a Tenant (e.g., Ace Pickleball Club, Metro Pickleball, Court Republic) with independent branding, courts, bookings, users, and payments.

### Tenant Isolation

Every business entity (users, courts, bookings, payments, notifications) must include a `tenant_id`. No cross-tenant access is permitted under any circumstances.

### White-Label Branding

Each tenant can configure:

- Logo
- Brand colors
- Email branding / templates
- Domain

### Domain Management

- **Platform domains (default):** `tenant.courtos.com`
- **Custom domains:** e.g., `booking.clubname.com`, `reserve.club.com`

Custom domain requirements: domain verification, SSL provisioning, automatic certificate renewal.

### Tenant Lifecycle

**Provisioning** — A Super Admin (or self-serve signup) creates a new tenant. Creation automatically provisions:

- Organization record
- Operator account
- Default branding
- Default email templates
- Booking portal
- Trial subscription

Target provisioning time: under 5 minutes.

**Suspension** — Super Admin can suspend a tenant and disable bookings while preserving all historical records. Suspension must not delete data.

**Deletion** — Soft deletion only: archive tenant → export data → schedule deletion.

---

## 8. Subscriptions & SaaS Billing

Two independent payment flows exist and must never be mixed:

- **Flow 1 — Court Booking Revenue:** Player → Payment Provider → Facility Operator
- **Flow 2 — CourtOS Subscription Revenue:** Facility Operator → CourtOS Subscription → Platform Owner

### Subscription Plans

| Plan | Courts | Extras |
|---|---|---|
| Starter | Up to 4 | — |
| Growth | Up to 20 | — |
| Enterprise | Unlimited | Custom branding, priority support |

### Subscription States

Trial → Active → Past Due → Suspended → Cancelled

### Billing

Subscription billing is handled through **Stripe** (SaaS billing only — not court booking payments; see section 9).

Supported: monthly billing, annual billing, trial periods.

Billing events handled: new subscription, renewal, failed payment, cancellation.

Failed subscription payments must not immediately disable bookings — a grace period is required.

---

## 9. Payment Architecture (Booking Payments)

### Payment Philosophy

CourtOS is payment-provider agnostic. The booking system must never depend directly on a specific payment provider. All providers integrate through a unified payment abstraction layer, allowing future support for PayMongo, Xendit, HitPay, bank transfers, and regional providers without modifying booking logic.

### Payment Abstraction Layer

The booking engine communicates only through a unified interface with these responsibilities:

- Create payment
- Verify payment
- Process refund
- Receive webhook events

Booking services never call provider-specific APIs directly.

### MVP Provider: PayMongo

Reasoning: supports the Philippine market (GCash, Maya, cards), low onboarding friction for MVP validation, suitable for individual developers and early-stage deployments.

Supported payment methods: GCash, Maya, credit/debit cards, online banking (where available).

### Future Providers

- **Xendit** (growth stage) — subscription billing, enterprise customers, additional payment channels
- **HitPay** (optional expansion) — alternative regional payment processing

### Booking Lifecycle & State Machine

States: `DRAFT`, `PENDING_PAYMENT`, `CONFIRMED`, `COMPLETED`, `CANCELLED`, `REFUNDED`, `EXPIRED`, `FAILED`

Happy path:

```
DRAFT → PENDING_PAYMENT → CONFIRMED → COMPLETED
```

Alternative paths:

```
PENDING_PAYMENT → EXPIRED
PENDING_PAYMENT → FAILED
CONFIRMED → CANCELLED → REFUNDED
```

Rules:

- A booking is not confirmed until payment verification succeeds.
- Frontend payment success messages are **never** authoritative — only verified webhook events can confirm bookings.
- A court cannot have overlapping confirmed bookings.
- Pending bookings must automatically expire.
- Cancelled bookings immediately return inventory to availability.
- Payment failures automatically release inventory.

### Payment Hold Logic

Purpose: prevent inventory loss during checkout.

1. User selects slot
2. Temporary reservation created
3. Payment initiated
4. Inventory held temporarily

If payment succeeds → booking becomes `CONFIRMED`. If payment fails or expires → booking becomes `EXPIRED`/`FAILED` and inventory returns to availability.

### Webhook Processing

All payment providers must support webhook verification. Webhooks are responsible for confirming payments, updating booking status, triggering notifications, and initiating refunds when necessary.

Requirements:

- Webhook processing must be idempotent.
- Duplicate webhook events must not create duplicate bookings or confirmations.

### Refund Management

- Supported types: full refund (MVP), partial refund (future)
- Triggers: operator cancellation, facility closure, administrative actions

### Revenue Ownership (MVP Model)

Player payments belong directly to the facility operator. CourtOS acts only as the booking platform and does not hold customer funds.

---

## 10. Core Engines

### Availability Engine

One of the most important systems. Purpose: convert court inventory into sellable time slots.

- **Operating hours** — configurable per court (e.g., Court A: 6 AM–10 PM, Court B: 8 AM–12 AM)
- **Slot duration** — configurable: 30 / 60 / 90 / 120 minutes
- **Booking windows** — operator controls earliest and latest booking dates (e.g., bookings allowed 30 days in advance)
- **Buffer times** — prevent scheduling conflicts (e.g., a 60-minute booking at 8:00 with a 15-minute cleanup buffer makes 9:15 the next available slot)
- **Court blocking** — operators can block courts, reserve them internally, or schedule maintenance; blocked inventory must never appear publicly

### Utilization Engine

Purpose: measure business health.

Metrics: court utilization %, revenue per court, revenue per available hour, peak demand hours, low demand hours, cancellation rate, recovered revenue.

**Occupancy heatmap** — visualize most- and least-booked hours to improve scheduling and pricing decisions.

### Waitlist System

Purpose: recover lost revenue.

- **Automatic enrollment** — a player joins the waitlist when their desired slot is unavailable.
- **Automatic recovery** — when a booking is cancelled: notify the waitlist → offer the slot → confirm the replacement booking.

Target: recover at least 50% of cancelled inventory.

### Notification System

Channels by phase:

| Phase | Channel |
|---|---|
| 1 | Email |
| 2 | SMS |
| 3 | Push notifications |

Notification events: booking created, payment received, booking reminder, booking cancelled, waitlist availability, payment failed, subscription renewal.

Notification providers for SMS/push will be evaluated later.

---

## 11. Audit Logs, Data Ownership & Reporting

### Audit Logs

Every critical action must be tracked.

Logged events: booking created/modified/cancelled, court created/updated, payment processed, role changed, user invited.

Each log entry records: actor, timestamp, previous value, new value, IP address.

### Data Ownership & Export

Tenants own their data. Operators can export bookings, payments, courts, and users in CSV and Excel formats.

### Reporting

- **Revenue reports** — daily, weekly, monthly, custom range
- **Booking reports** — by court, by day, by hour, cancellation reports

### Super Admin System Dashboard

Platform-level metrics: total tenants, active tenants, monthly revenue, failed payments, system health, queue health.

---

## 12. User Journeys

### Player Journey

Goal: book a court quickly.

Open Booking Page → View Availability → Select Slot → Pay → Success Screen

Total time: under 30 seconds.

After payment the player lands on a **terminal success screen** showing the
booking reference (saved for future cancellation). This screen is one-way: the
player cannot navigate back into the checkout or slot picker for the slot they
just paid for. Back navigation is intercepted client-side and sent to a safe
page, and the spent checkout is guarded server-side so it cannot be re-entered.
This prevents the "did my payment go through?" double-payment confusion. The
"Done" action leaves the funnel entirely rather than returning to the booking
page.

### Operator Journey

Goal: monitor business performance.

Open Dashboard → Review Utilization → Identify Empty Hours → Launch Promotion → Increase Occupancy

---

## 13. Non-Functional Requirements

| Category | Requirement |
|---|---|
| Performance | Page load under 2 seconds; booking transaction under 1 second |
| Availability | 99.9% uptime |
| Security | Role-based access control, secure payments, encrypted sensitive data |
| Scalability | 1,000+ facilities; 100,000+ bookings monthly |
| Mobile | Fully responsive; Progressive Web App (PWA) — installable with a web manifest, app icons, and a service worker giving an offline fallback. No native mobile app required for MVP |

---

## 14. Technical Architecture

### Backend

**Laravel 13** (matches the current codebase: `laravel/framework` ^13.7)

Responsibilities: multi-tenant application logic, booking engine, payment processing, booking state management, notification orchestration, white-label tenant management.

Architecture style:

- Modular monolith
- Domain-driven service organization
- Server-driven SPA via Inertia (no separate API layer required for the first-party frontend; a versioned API can be added later for integrations)

### Frontend

**Vue 3 with Inertia.js v3** (matches the current codebase)

- Inertia handles routing and page state (replaces Vue Router/Pinia from earlier drafts)
- Laravel Wayfinder for type-safe route references
- VueUse for composition utilities
- Tailwind CSS v4

Design system: **shadcn-vue**

- All UI components must originate from shadcn-vue.
- Custom components must follow shadcn design principles: spacing, typography, accessibility, and interaction patterns.
- No third-party UI frameworks may be introduced without design system review.

Benefits: consistent user experience, faster development, easier white-label customization (theming via CSS variables).

### Authentication

Laravel Fortify (already installed): secure session management, password reset, optional 2FA and passkeys. Authorization via role-based access control (section 4).

### Database

**PostgreSQL** — tenant storage, court inventory, booking records, payment records, audit history.

Requirements: ACID-compliant transactions, database-level booking conflict prevention, tenant isolation.

### Cache & Queue

**Redis** — queue processing, notification jobs, booking reminders, waitlist automation, caching frequently accessed availability data. Required for production deployments and all background processing.

### Background Jobs

Required jobs: booking reminders, waitlist processing, payment reconciliation, notification dispatch, booking expiration. All jobs execute asynchronously through Redis queues.

### Payments

- **Booking payments:** PayMongo via the payment abstraction layer (section 9)
- **SaaS subscription billing:** Stripe (section 8)

### Testing

Pest v4 (feature-test-first). Booking conflict prevention, state transitions, webhook idempotency, and tenant isolation must all be covered by automated tests.

### Infrastructure & Deployment

- **Hosting:** DigitalOcean — development, staging, and production environments
- **Server management:** Laravel Forge — provisioning, deployment automation, SSL management, scheduled jobs, queue workers
- **Deployment:** Git-based — `main` → production, `develop` → staging, `feature/*` → development
- **Storage:** local storage (MVP), DigitalOcean Spaces (future)

### Backups

- Database: daily automated backups, 30-day retention
- Files: daily snapshots
- Recovery objective: maximum 24 hours of data loss

### Observability

- **Monitoring:** Laravel Pulse; Laravel Telescope (non-production environments only)
- **Logging:** centralized application logs, booking event logs, payment event logs
- **Alerts:** failed webhook processing, queue failures, database connectivity issues, error rates, payment failures

### Security

- Secure session management and password reset (Fortify)
- Role-based access control
- API security: rate limiting, CSRF protection, input validation
- Sensitive data encrypted at rest where applicable
- PCI-compliant payment practices via providers (PayMongo / Stripe) — CourtOS never stores raw card data

---

## 15. Required MVP Services & Data Model

### Services

Court Service, Booking Service, Availability Service, Payment Service, Notification Service, Tenant Service, Audit Service, Subscription Service.

### Tables

`tenants`, `subscriptions`, `users`, `roles`, `facilities`, `resources`, `bookings`, `booking_events`, `payments`, `payment_events`, `notifications`, `audit_logs`, `domains`, `branding_settings`, `waitlists`

---

## 16. Future-Proofing: Generic Resource Model

The system must not be hardcoded to pickleball. The internal architecture follows:

```
Facility → Resource → Booking
```

A "court" is one type of Resource. Future resource types: tennis court, badminton court, basketball court, futsal court, meeting room.

Use the generic `resources` model internally rather than hardcoding "Pickleball Court" throughout the backend. This preserves future market expansion without major architectural changes.

---

## 17. Development Roadmap

| Phase | Theme | Deliverables |
|---|---|---|
| 1 | Foundation: Inventory & Booking | Multi-tenancy, authentication, court management, booking engine + availability engine |
| 2 | Revenue Automation | PayMongo payments (abstraction layer), queue system, waitlists, cancellation recovery, email notifications |
| 3 | Business Intelligence | Utilization dashboard, revenue analytics, reporting, audit logs |
| 4 | White-Label & Hardening | Branding configuration, custom domains, Stripe SaaS billing, tenant provisioning automation, production hardening |

---

## 18. Future Modules (Post-MVP)

Intentionally excluded from MVP. These will only be implemented if validated through customer demand.

- **Dynamic Pricing** — raise prices during peak hours/weekends/holidays, lower during low-utilization periods. Goal: increase revenue per court hour.
- **Promotions Engine** — automatically promote empty inventory: last-minute discounts, off-peak promotions, membership specials. Goal: fill unused courts.
- **Membership System** — recurring revenue, priority booking, member discounts. Goal: increase retention.
- **Equipment Rentals** — paddle, ball, locker rental. Goal: increase average transaction value.
- **QR Check-In** — verify attendance and reduce abuse.
- **Tournament Management** — additional operator monetization.
- **Native mobile applications**
- **Coaching management**
- **AI-based demand forecasting**

---

## 19. MVP Launch Acceptance Criteria

A tenant can, **without requiring engineering support**:

- Sign up
- Configure branding
- Create courts
- Accept bookings
- Receive payments
- Manage reservations
- Recover cancellations through waitlists
- View utilization metrics

This is the minimum definition of a production-ready white-label SaaS MVP.
