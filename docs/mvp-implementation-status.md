# MVP Implementation Status

Updated: 2026-06-27

## Implemented in this backend repository

- versioned `/api/v1` routes
- Sanctum-protected member routes and role-protected admin routes
- active-account middleware and Filament admin access restriction
- 18+ registration, pending-by-default verification, login/logout/logout-all,
  password reset request and reset handlers
- authenticated profile read/update, profile-photo upload, and deletion request
- canonical MVP schema additions for roles, statuses, verification, venues,
  hangouts, members, tags, moderation, feedback, and notifications
- listed venue discovery and admin venue CRUD API
- hangout discovery/filtering, privacy tiers, verified-host creation, updates,
  cancellation, completion, and personal hangout list
- authenticated join requests with transactional capacity-safe approval,
  decline/cancel/leave flows, block checks, and host ownership checks
- approved-member chat and host announcements
- blocks, trusted contacts with encrypted contact fields, safety check-ins,
  attendance, post-night feedback, and reports
- database notifications for join decisions, cancellation, and safety reminders
- configurable queued Expo push and transactional email delivery
- notification preferences, unread filtering, and read/read-all controls
- private report evidence uploads with admin moderation visibility
- private-channel real-time group-message broadcast events
- TOTP admin MFA enrollment with production enforcement switch
- delayed account anonymization with a configurable retention grace period
- dependency-aware API health checks and request-ID tracing
- venue/hangout favorites, full-capacity waitlists with promotion, and shareable invite deep links
- audited admin user suspension, banning, restoration, and automatic suspension expiry
- scheduled hangout/check-in lifecycle job
- moderation queue and audited report/profile-review mutations
- normalized validation/HTTP error envelope
- seed compatibility, isolated fresh migration/seed verification, and feature tests

## Verification performed

- `php artisan test`: 27 tests, 81 assertions, passing
- Laravel Pint check for touched areas: passing
- isolated SQLite `migrate:fresh --seed`: passing
- scheduler registration with isolated cache: passing
- `git diff --check`: passing

## External configuration still required before production

- real email provider and sender/domain configuration for password reset
- SMS/OTP provider and phone-verification delivery adapter
- Expo push access token and mobile-device token registration
- object storage/CDN plus an external image malware/moderation scanner
- production queue, cache/lock, scheduler worker, backups, monitoring, and alerts
- admin MFA provider/configuration
- legal/privacy review and final retention periods

## Connected sibling applications

The existing `natsvibe-frontend` React admin app now uses authenticated admin
access and the versioned backend contracts for venues, hangouts, verification,
reports, and tags. Its production TypeScript/Vite build passes.

The existing `natsvibe-mobile` React Native app now uses `/api/v1`, persisted
Sanctum sessions, backend venue/discovery data, secure host creation, authenticated
join requests, host approvals, approved-member chat, trusted contacts, and safety
check-ins. Its TypeScript check passes.

## Recommended next repository milestone

Configure the selected email, Expo, object-storage, broadcast, Redis, and monitoring
providers, then run MySQL integration/concurrency tests in CI. SQLite verifies
behavior and migrations but cannot fully prove MySQL row-lock contention behavior.
