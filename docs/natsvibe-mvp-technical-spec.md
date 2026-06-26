# NatsVibe MVP Technical Specification

Status: Draft for implementation  
Product area: Poblacion / Makati  
Default display timezone: Asia/Manila  
Canonical storage timezone: UTC  
Minimum age: 18

This document turns the product plan into executable requirements. When this
document conflicts with prototype behavior, this document is the target and the
prototype must be changed deliberately.

## 1. MVP outcome and boundary

NatsVibe helps adults join small, host-led gatherings at public venues without
having to arrive alone.

The MVP is successful when a verified host can create a public-venue hangout,
an eligible user can discover and request to join it, the host can safely
approve members, approved members can coordinate, and admins can investigate
reports.

Included:

- account registration, authentication, and recovery
- profile completion and email/phone verification
- admin-managed venues and tags
- hangout discovery, creation, cancellation, and completion
- join requests and host decisions
- approved-member group chat and private meetup note
- notifications
- safety check-ins, blocking, and reporting
- admin moderation, verification, and audit logs
- post-night attendance and private feedback

Explicitly excluded:

- payments, subscriptions, and ticketing
- dating or one-to-one matching
- live location sharing
- automated venue reservations
- venue partner dashboard
- identity-document verification
- AI-generated or AI-enforced moderation decisions
- nationwide or multi-city launch

## 2. Product invariants

These rules must be enforced by the backend, not only by clients.

1. Only authenticated, active, 18+ users with completed profiles may browse
   hangouts or request to join.
2. Only approved hosts may create a hangout.
3. A hangout must use a listed public venue.
4. The host counts toward capacity.
5. A user has at most one join request and at most one membership per hangout.
6. Approval must never exceed capacity, including concurrent approvals.
7. Only the host, approved members, and authorized admins may access group chat.
8. Exact meetup instructions are hidden until approval.
9. A blocked pair may not join the same new hangout or directly interact.
10. Admin access to private messages requires a linked moderation reason and is
    audit logged.
11. Safety features remain free.
12. User-visible counters and trust labels must not expose a numerical safety
    score or create public shaming.

## 3. Roles and account eligibility

Roles:

- `user`: regular member
- `host`: verified host; includes regular-user permissions
- `admin`: internal operator
- `super_admin`: manages admins and sensitive configuration

Account statuses:

- `active`
- `pending_verification`
- `suspended`
- `banned`
- `deletion_pending`
- `deleted`

Verification levels are separate from role and account status:

- `unverified`
- `email_verified`
- `phone_verified`
- `profile_approved`
- `host_approved`

Host eligibility requires active status, age 18+, completed profile, verified
email, verified phone, approved profile photo, and explicit host approval.

## 4. Authorization matrix

| Action | Eligible user | Host | Admin |
|---|---|---|---|
| Browse listed venues/open hangouts | Yes | Yes | Yes |
| View exact meetup note | Approved member only | Own hangout | With reason |
| Create hangout | No | Yes | Official/admin only |
| Update/cancel hangout | No | Own hangout | Yes |
| Request to join | Yes | Yes, except own hangout | No |
| Decide join request | No | Own hangout | Intervention only |
| Read/send chat | Approved member | Own hangout | With reason |
| Submit report/block | Yes | Yes | Yes |
| Moderate users/reports | No | No | Yes |
| Manage roles/admins | No | No | Super admin |

Laravel Policies must cover `Venue`, `Hangout`, `JoinRequest`, `GroupMessage`,
`Report`, `SafetyCheckin`, and `Profile`. Admin UI permissions do not replace
backend authorization.

## 5. State machines

### 5.1 Hangout

```text
draft -> open -> full -> ongoing -> completed
           ^      |
           |      v
           +-- open

draft/open/full/ongoing -> cancelled
open/full/ongoing       -> flagged -> prior state or cancelled
```

- `draft -> open`: host publishes; all required fields and eligibility pass.
- `open -> full`: approval fills the final available slot.
- `full -> open`: an approved member leaves before the request cutoff.
- `open/full -> ongoing`: scheduler at `scheduled_at`, or host starts within
  60 minutes before the scheduled time.
- `ongoing -> completed`: host marks complete, or scheduler runs six hours after
  start unless an active safety incident exists.
- `cancelled` and `completed` are terminal for MVP.
- A flagged hangout is hidden from discovery pending review.

### 5.2 Join request

```text
pending -> approved
pending -> declined
pending -> cancelled
pending -> expired
approved -> withdrawn
```

- User may cancel only their own pending request.
- Host may approve/decline only requests for their hangout.
- Approval is transactional and capacity-locked.
- Pending requests expire when request cutoff passes, the hangout is cancelled,
  or the event starts.
- Declined/expired requests cannot silently return to pending. A new attempt is
  permitted only if product policy explicitly allows it and creates history.

### 5.3 Report

```text
new -> triaged -> investigating -> action_taken -> resolved
                         |                 |
                         +-> dismissed <---+
resolved/dismissed -> appealed -> investigating
```

Severity: `low`, `medium`, `high`, `critical`. Critical includes credible
immediate threats, stalking, assault, or a missing-person concern and must enter
the urgent operational queue.

### 5.4 Safety check-in

```text
scheduled -> reminder_sent -> safe
                         |-> missed -> escalated -> closed
                         |-> help_requested -> escalated -> closed
```

The MVP sends in-app/push reminders. It does not claim to contact emergency
services or trusted contacts automatically.

## 6. Core business rules

### 6.1 Scheduling

- API accepts ISO 8601 timestamps with an offset.
- Store UTC; return ISO 8601 UTC and an optional display timezone.
- Hangouts must be created at least two hours and at most 60 days ahead.
- Default request cutoff is two hours before start.
- Group size is 3 to 10 inclusive, including host.
- Rescheduling after the first approval notifies all approved/pending users.
- Moving by more than two hours requires approved members to reconfirm.

### 6.2 Capacity-safe approval

Within one database transaction:

1. lock the hangout row `FOR UPDATE`
2. verify requester, host authority, statuses, blocks, cutoff, and eligibility
3. count host plus active approved memberships
4. reject with `409 HANGOUT_FULL` if no slot remains
5. update request, insert membership idempotently, and write an audit event
6. set hangout to `full` when capacity is reached
7. dispatch notification only after commit

### 6.3 Blocking

- Blocking is private and does not notify the blocked user.
- A blocked user cannot view the blocker's non-shared profile, request their
  future hangouts, or send new interaction.
- If both are already approved in one hangout, neither is automatically removed;
  the blocker receives leave/report options and urgent safety guidance.
- Chat hides future messages between the pair where feasible, but existing
  evidence remains available to moderators under retention policy.

### 6.4 Cancellation

- Host cancellation requires a reason and immediately notifies all participants.
- A host cannot cancel a completed hangout.
- Admin cancellation records operator, reason, target, timestamp, and evidence.
- Frequent host cancellations produce an internal flag, never a public score.

## 7. Canonical data model

Use database constraints in addition to validation. Prefer constrained strings
or PHP enums; native database enums are optional because migrations become
harder to evolve.

### 7.1 Identity and profiles

`users`

- `id`, `name`, `email` unique, `phone` unique nullable, `password`
- `date_of_birth` date (do not store mutable age)
- `role`, `status`
- `email_verified_at`, `phone_verified_at`, `last_login_at`
- `suspended_until`, `banned_at`, `deletion_requested_at` nullable
- timestamps and soft deletes
- indexes: `(status, created_at)`, `(role, status)`

`profiles`

- `id`, `user_id` unique FK
- `display_name`, `city`, `bio`, `avatar_path`
- `going_out_style`, `availability`, `safety_preference`
- `completion_status`, `photo_review_status`, `host_verification_status`
- timestamps

`profile_vibe_tag`

- `profile_id`, `vibe_tag_id`, unique pair

Do not persist a fallback age, city, avatar, or verification badge in API code.

### 7.2 Venues

`venues`

- `id`, `name`, `slug` unique, `description`
- `area`, `city`, `address`
- `google_maps_url`, `instagram_url`
- `venue_type`, `budget_min`, `budget_max`, `currency` default `PHP`
- `opening_hours` JSON
- `reservation_required`, `reservation_notes`
- `group_capacity_min`, `group_capacity_max`
- `status`, `is_verified`, `is_featured`
- timestamps and soft deletes
- indexes: `(status, city, area)`, `(is_featured, status)`

`venue_photos`

- `id`, `venue_id`, `storage_path`, `alt_text`, `sort_order`, `is_primary`
- enforce one primary photo in application transaction

`venue_vibe_tag`: unique `(venue_id, vibe_tag_id)`.

### 7.3 Hangouts and membership

`hangouts`

- `id`, `host_id`, `venue_id`
- `title`, `description`, `rules`, `host_notes`
- `scheduled_at` UTC, `timezone`, `request_cutoff_at`
- `group_size_limit`, `budget_min`, `budget_max`, `currency`
- `status`, `previous_status`, `cancelled_at`, `cancellation_reason`
- timestamps and soft deletes
- indexes: `(status, scheduled_at)`, `(host_id, status)`, `(venue_id, scheduled_at)`

`hangout_vibe_tag`: unique `(hangout_id, vibe_tag_id)`.

`join_requests`

- `id`, `hangout_id`, `user_id`, `status`, `message`
- `decided_by`, `decided_at`, `cancelled_at`, timestamps
- unique `(hangout_id, user_id)` for MVP
- indexes: `(hangout_id, status, created_at)`, `(user_id, status)`

`hangout_members`

- `id`, `hangout_id`, `user_id`, `role` (`host`, `member`)
- `status` (`active`, `withdrawn`, `removed`)
- `joined_at`, `left_at`, timestamps
- unique `(hangout_id, user_id)`

The host should also have a membership row. This removes repeated `+ 1` counting
logic and gives one source of truth.

### 7.4 Chat, safety, and moderation

`group_messages`

- `id`, `hangout_id`, `sender_id`, `type` (`message`, `announcement`)
- `body`, `reported_at`, timestamps, soft deletes
- index `(hangout_id, id)` for cursor pagination

`trusted_contacts`

- `id`, `user_id`, `name`, `phone_encrypted`, `email_encrypted`, `relationship`
- timestamps

`safety_checkins`

- `id`, `user_id`, `hangout_id`, `scheduled_for`, `reminded_at`
- `responded_at`, `status`, `escalated_at`, timestamps
- unique active check-in per `(user_id, hangout_id)`

`reports`

- `id`, `reporter_id`, polymorphic `reportable_type/reportable_id`
- optional `message_id`, `reason`, `details`, `severity`, `status`
- `assigned_admin_id`, `resolution`, `resolved_at`, timestamps
- indexes: `(status, severity, created_at)`, `(reportable_type, reportable_id)`

`blocks`: unique `(blocker_id, blocked_id)` and a check preventing self-block.

`admin_actions`

- `id`, `admin_id`, `action`, polymorphic target
- `reason`, `before` JSON, `after` JSON, `ip_address`, `user_agent`, timestamps
- append-only; no normal update/delete endpoint

`notifications`

- Laravel database-notification fields plus stable event key and optional
  deduplication key.

### 7.5 Feedback and analytics

`attendance_responses`

- unique `(hangout_id, user_id)`, response, responded_at

`hangout_feedback`

- unique `(hangout_id, reviewer_id)`
- host rating, group-vibe rating, venue rating, safety concern, private notes

`product_events`

- event name, actor id nullable, anonymous/session id nullable, properties JSON,
  occurred_at; never place message content or sensitive report details here

## 8. API standard

Prefix all production endpoints with `/api/v1`. JSON uses `snake_case`.

Success:

```json
{
  "data": {},
  "meta": { "request_id": "01J..." }
}
```

Validation/business error:

```json
{
  "error": {
    "code": "HANGOUT_FULL",
    "message": "This hangout is already full.",
    "fields": {}
  },
  "meta": { "request_id": "01J..." }
}
```

Status use:

- `200` read/update, `201` create, `204` successful no-content action
- `401` unauthenticated, `403` unauthorized, `404` hidden/not found
- `409` state/capacity conflict, `422` validation, `429` rate limited

List endpoints use cursor pagination with `data` and `meta.next_cursor`.
Filtering uses explicit query parameters. Sorting is allow-listed. Clients must
not send actor IDs that can be derived from the authenticated token.

### 8.1 Auth and profile

```text
POST   /api/v1/auth/register
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
POST   /api/v1/auth/logout-all
POST   /api/v1/auth/forgot-password
POST   /api/v1/auth/reset-password
POST   /api/v1/auth/email/resend
POST   /api/v1/auth/phone/send-otp
POST   /api/v1/auth/phone/verify-otp
GET    /api/v1/me
PUT    /api/v1/me/profile
POST   /api/v1/me/profile/photo
DELETE /api/v1/me
```

Registration returns pending verification and never auto-approves profiles.
Passwords require at least 10 characters and compromised-password checking where
available. Tokens have device labels, expiration, last-used time, and revocation.
Admin accounts require MFA before production access.

### 8.2 Venues and hangouts

```text
GET    /api/v1/venues
GET    /api/v1/venues/{venue}
GET    /api/v1/hangouts
GET    /api/v1/hangouts/{hangout}
POST   /api/v1/hangouts
PUT    /api/v1/hangouts/{hangout}
POST   /api/v1/hangouts/{hangout}/cancel
POST   /api/v1/hangouts/{hangout}/complete
GET    /api/v1/me/hangouts
```

Discover supports `area`, `from`, `to`, `budget_max`, `vibe_tag`,
`available_slots`, `verified_host`, and `reservation_ready`.

Public hangout response excludes `host_notes`, member contact details, exact
private meetup instructions, and internal flags.

### 8.3 Join requests and members

```text
POST   /api/v1/hangouts/{hangout}/join-requests
GET    /api/v1/hangouts/{hangout}/join-requests
POST   /api/v1/join-requests/{join_request}/approve
POST   /api/v1/join-requests/{join_request}/decline
POST   /api/v1/join-requests/{join_request}/cancel
POST   /api/v1/hangouts/{hangout}/leave
```

The requester is always `auth()->id()`. Approval accepts no `user_id`,
`host_id`, or client-supplied status.

### 8.4 Chat, safety, reports, and blocks

```text
GET    /api/v1/hangouts/{hangout}/messages?cursor=...
POST   /api/v1/hangouts/{hangout}/messages
POST   /api/v1/hangouts/{hangout}/announcements
POST   /api/v1/hangouts/{hangout}/safety-checkins
PUT    /api/v1/safety-checkins/{checkin}
POST   /api/v1/safety-checkins/{checkin}/safe
POST   /api/v1/safety-checkins/{checkin}/help
POST   /api/v1/reports
GET    /api/v1/me/reports
POST   /api/v1/blocks
GET    /api/v1/blocks
DELETE /api/v1/blocks/{block}
GET    /api/v1/notifications
POST   /api/v1/notifications/{notification}/read
POST   /api/v1/notifications/read-all
```

Message body limit is 2,000 characters. MVP messages cannot be edited. User
deletion removes normal visibility but preserves report evidence for the defined
retention period.

### 8.5 Admin

All routes require authenticated admin role, MFA session, Policies, and audit
logging.

```text
GET/POST/PUT /api/v1/admin/venues...
GET/PUT      /api/v1/admin/users...
GET/PUT      /api/v1/admin/hangouts...
GET/PUT      /api/v1/admin/reports...
GET/PUT      /api/v1/admin/verifications...
GET/POST/PUT /api/v1/admin/tags...
GET          /api/v1/admin/audit-log
```

No destructive hard delete is exposed for moderation records.

## 9. Validation and rate limits

Minimum limits per account/IP, adjustable after observing beta traffic:

- login: 5 attempts/minute, progressive cooldown
- password reset: 3/hour
- OTP send: 3/hour; OTP verify: 5 attempts/code; 10-minute expiry
- create hangout: 5/day
- join request: 10/day and 3/minute
- messages: 20/minute with burst control
- reports: 5/day, with safety UI still allowing emergency guidance
- photo upload: JPEG/PNG/WebP, max 8 MB, re-encode and strip metadata

Normalize phone numbers to E.164. Normalize emails for comparison. Validate URLs
against allowed schemes. Escape user content at render time.

## 10. Notification contract

| Event | In-app | Push | Email |
|---|---:|---:|---:|
| Join request received | Yes | Yes | No |
| Join approved/declined | Yes | Yes | Optional |
| Hangout updated/cancelled | Yes | Yes | Cancellation only |
| New chat message | Yes | Preference | No |
| Host announcement | Yes | Yes | No |
| Safety reminder | Yes | Yes | Optional |
| Report resolved | Yes | Optional | Optional |

Notifications are queued, retry with backoff, and use deduplication keys. Failed
push delivery must not roll back business transactions. Respect per-category
preferences and quiet hours, except critical safety/account-security notices.

## 11. Chat implementation

MVP may use short polling first; Laravel Reverb/WebSockets can be added without
changing the REST history contract. Requirements:

- cursor pagination, newest page first and stable ordering by ID
- authorization on every read/write, not only connection time
- approved active membership required
- no media, links previews, typing indicators, or read receipts in MVP
- report action stores the message ID and an immutable evidence snapshot
- removed/withdrawn members lose send access immediately
- host announcements are rate-limited and visually distinct

## 12. Safety, privacy, and moderation operations

### 12.1 Privacy tiers

Before approval, users may see display name, age band or age, city, bio, public
photo, verification badges, vibe tags, and simplified host history. Never expose
email, phone, date of birth, live location, trusted contacts, or internal notes.

After approval, users see the public venue, meetup point/note, time, group code,
and approved member previews. The product never exposes another user's exact
home or live location.

### 12.2 Moderation workflow

1. Intake validates target and preserves relevant evidence.
2. Triage assigns severity and owner.
3. Critical reports trigger the on-call procedure and may cause reversible
   temporary restrictions.
4. Investigator records evidence and rationale.
5. Admin applies no action, warning, suspension, ban, content restriction, or
   event cancellation.
6. User receives an appropriate outcome notice that protects reporter privacy.
7. One appeal is accepted within 14 days and reviewed by a different admin when
   practical.

Target response goals during staffed beta:

- critical: acknowledge within 15 minutes
- high: within 2 hours
- medium: within 24 hours
- low: within 3 business days

These targets must not be advertised until staffing can support them.

### 12.3 Retention baseline

Legal review must finalize these values before public launch:

- routine chat: 12 months after hangout completion
- report evidence and moderation records: 24 months after closure
- security/audit logs: 12 months
- product analytics: 24 months, minimized/pseudonymized where possible
- deletion request: normal account data removed/anonymized within 30 days,
  except fraud, safety, legal, and audit records with a documented basis

Trusted-contact data is encrypted at rest and never used for marketing.

### 12.4 Required launch documents

- Terms of Service
- Privacy Notice compliant with applicable Philippine privacy requirements
- Community Guidelines
- Safety Center and emergency disclaimer
- Account deletion and data-request process
- Moderation and appeals policy
- Photo/content consent rules

These require qualified legal/privacy review; this specification is not legal
advice.

## 13. Edge-case decisions

- Concurrent final-slot approvals: first committed transaction wins; other gets
  `409 HANGOUT_FULL`.
- Host suspended before event: freeze group actions; admin reassigns/cancels and
  notifies members.
- Venue archived/closed: existing hangout is flagged for host/admin relocation;
  it is removed from new venue selection.
- Member account deleted: membership becomes anonymized/withdrawn while required
  safety evidence remains restricted.
- Approved member withdraws before cutoff: slot reopens and host is notified.
- Approved member withdraws after cutoff: slot does not automatically reopen.
- Host no-show: members can report and submit attendance; moderation reviews.
- Mutual block inside existing group: both retain safety/report/leave controls;
  no forced disclosure of who blocked whom.
- Scheduler outage: transitions are idempotent and catch up on next run.
- Duplicate client retry: mutation endpoints use an idempotency key where double
  execution has meaningful consequences.

## 14. Non-functional requirements

- API availability target for beta: 99.5% monthly
- API p95 excluding uploads: under 500 ms at expected beta load
- mobile crash-free sessions: at least 99.5%
- all production traffic uses TLS
- database backups daily, seven daily plus four weekly copies
- beta RPO: 24 hours; RTO: 8 hours
- restore drill before public beta and quarterly thereafter
- structured logs include request ID and actor ID but never passwords, tokens,
  OTPs, trusted-contact details, or message bodies
- production errors and queue failures alert operators
- accessibility target: WCAG 2.2 AA for admin web and equivalent mobile support
- supported mobile OS policy is documented before store submission

## 15. Security baseline

- Sanctum bearer tokens for mobile; secure session cookies for web admin
- revoke tokens on password reset, ban, and suspected compromise
- MFA for admins; least-privilege roles
- Form Requests for validation; Policies for authorization
- mass-assignment allow lists; never accept actor/owner fields from clients
- database transactions and unique constraints for invariants
- storage uses private objects and time-limited URLs where appropriate
- uploads are re-encoded, metadata stripped, and malware-scanned when available
- dependency, secret, and static-analysis checks in CI
- CORS allow list by environment
- audit sensitive reads as well as writes
- pre-release threat model covering account takeover, stalking, enumeration,
  capacity races, report abuse, scraping, and admin misuse

## 16. Test strategy and acceptance criteria

### 16.1 Automated layers

- unit tests: state transitions, eligibility, capacity, notification decisions
- feature tests: every endpoint's happy path, validation, auth, and ownership
- policy tests: every role/action pair
- concurrency integration test: two final-slot approvals
- scheduler/queue tests: retries, idempotency, catch-up behavior
- end-to-end tests: admin venue creation through mobile discovery; request through
  approval and chat; report through resolution
- security tests: IDOR, mass assignment, rate limiting, token revocation, upload
- contract tests: stable mobile-facing JSON resources

### 16.2 First vertical-slice acceptance

Admin creates venue and hangout; mobile discovers it and opens details:

- unauthorized users cannot create/update venues or hangouts
- admin can create a complete listed venue
- verified host can create an open hangout using that venue
- regular/unverified user cannot create a hangout
- discover returns only eligible open/full future hangouts, paginated
- filters for area/date/budget/tags work
- card reports capacity including host
- details hide private meetup note from non-members
- timestamps are ISO 8601 and render correctly in Asia/Manila
- deleted/archived venue behavior follows edge-case policy
- API and UI show loading, empty, validation, offline, and server-error states
- feature and policy test suites pass in CI

### 16.3 Join-and-chat acceptance

- authenticated eligible user creates one request without supplying `user_id`
- host alone can decide it
- concurrent decisions cannot overfill the hangout
- approved membership grants chat/private-details access
- decline/cancel does not grant access
- block rules are enforced
- all decisions notify after commit and write appropriate audit history

## 17. Environments, CI/CD, and operations

Environments: `local`, `testing`, `staging`, `production`. Staging uses synthetic
data and production-like queues/storage without production personal data.

CI on every change:

1. install locked dependencies
2. lint/format check
3. static analysis
4. unit and feature tests using a clean database
5. frontend typecheck/build where applicable
6. dependency and secret scan

Deployment:

1. backup and health check
2. build immutable release
3. run backward-compatible migrations
4. deploy app/workers/scheduler
5. smoke-test auth, discovery, queue, and storage
6. observe error/latency dashboards

Every release has a rollback/run-forward plan. Destructive schema changes use a
multi-release expand/migrate/contract process.

Operational runbooks are required for database restore, queue backlog, push
failure, storage failure, elevated errors, account compromise, data incident,
and critical safety report.

## 18. Analytics and success metrics

Privacy-minimized events:

```text
signup_started
signup_completed
verification_completed
profile_completed
hangout_viewed
join_requested
join_approved
join_declined
hangout_started
attendance_confirmed
hangout_completed
report_submitted
```

North-star metric: weekly attended hangouts with at least three confirmed
participants and no substantiated critical safety incident.

Supporting metrics:

- profile completion and verification conversion
- time to first join request
- request approval rate and median decision time
- capacity fill rate and attendance rate
- repeat participation within 30 days
- host and member cancellation/no-show rates
- reports per completed hangout, by severity
- time to triage and resolution

Do not optimize growth metrics without reviewing safety guardrails alongside
them.

## 19. Beta launch operating plan

Initial scope: Poblacion/Makati, 20-30 reviewed venues, 10-15 trained seed hosts,
and 50-100 invited adult testers.

Before inviting testers:

- name a safety/moderation owner and staffed hours
- publish launch documents and emergency disclaimers
- complete admin MFA and route authorization audit
- seed venues and scheduled hangouts
- rehearse report, ban, cancellation, backup restore, and incident workflows
- establish support channel and response expectations

Beta exit criteria, measured over at least four weeks:

- at least 20 completed hangouts
- at least 60% approved-member attendance
- at least 30% 30-day repeat participation among attendees
- median join decision under 12 hours during staffed periods
- no unresolved critical incident past its operating target
- crash-free sessions at least 99.5%
- no known P0 security/privacy defect

These are initial hypotheses and should be adjusted based on cohort size and
actual operating capacity.

## 20. Implementation order

### P0: secure the prototype

- place protected and admin routes behind Sanctum, role middleware, and Policies
- derive host/requester identity from authentication
- remove auto-verification and hard-coded user/profile data
- add role/status/date-of-birth and verification rules
- add uniqueness, indexes, soft-delete choices, and capacity transaction
- normalize response/error contracts and add `/api/v1`
- add tests for IDOR, role permissions, and final-slot race

### P1: first vertical slice

- canonical venue/hangout schema and resources
- admin venue and hangout management
- host hangout creation
- discover/details with privacy tiers and filters
- staging deployment, logs, backups, smoke tests

### P2: joining and coordination

- request lifecycle, capacity-safe approvals, membership model
- notifications and approved-member meetup details
- chat history/sending, announcements, block enforcement

### P3: safety and beta readiness

- safety check-ins and trusted contacts
- reporting/moderation/appeals/audit logs
- attendance and post-night feedback
- legal pages, analytics, runbooks, load/security testing

### Later

- saved hangouts, waitlist, co-hosts, templates, invite links
- venue suggestions/promos/partners and reservations
- subscriptions, official paid events, referrals, additional cities
- carefully reviewed AI assistance; never autonomous banning

## 21. Decisions still requiring owner sign-off

These are intentionally explicit rather than silently guessed:

- exact staffed moderation hours and escalation contacts
- whether age is publicly exact or shown as an age band
- final chat/report/audit retention periods after legal review
- OTP, push, email, storage, monitoring, and hosting providers
- whether admin may intervene in approvals or must only suspend/cancel
- supported iOS/Android versions
- production RPO/RTO and budget

Until decided, engineering should use interfaces/configuration that keep these
choices replaceable.
