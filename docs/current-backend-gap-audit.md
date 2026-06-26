# Current Backend Gap Audit

Audit basis: repository state on 2026-06-27. This is an implementation gap list,
not a claim that the prototype is production ready.

## P0 findings

1. Most write and admin-like API routes are public. `venues`, hangout creation,
   join request mutation, profile verification, reports, and vibe-tag creation
   need authentication and authorization.
2. Hangout creation accepts client-supplied `host_id`; join requests accept
   client-supplied `user_id`. Both permit impersonation and must use the
   authenticated user.
3. Join-request listing and status updates do not verify host ownership.
4. Join approval does not lock capacity, check hangout state/cutoff, prevent a
   blocked pairing, or remove membership if the state changes away from approved.
5. Registration auto-approves profiles and inserts fabricated age, city, bio,
   and avatar values.
6. Hangout responses hard-code verification and vibe tags, and expose venue
   address without a defined privacy tier.
7. `/me` has fallback identity/profile data that can misrepresent real users.
8. Public `apiResource('venues')` exposes all CRUD methods unless controller
   behavior restricts them internally.
9. No visible role/status fields or admin MFA boundary exist in the inspected
   schema.
10. Routes are duplicated for singular/plural join request forms, increasing
    contract ambiguity.

## Schema gaps

- `profiles.user_id`, pivot pairs, join request pairs, and member pairs need
  unique constraints.
- Mutable `age` should be replaced with `users.date_of_birth`.
- Planned venue fields, hangout tags/rules/private note, notifications, profile
  photos, and feedback data are absent or incomplete.
- Venue and hangout statuses differ from the product plan.
- Dates use `date_time` without an explicit UTC/API convention.
- Reports use several nullable target columns without a one-target constraint.
- Trusted contact phone/email are stored as plain text.
- Soft-deletion and retention behavior are not encoded.
- Discovery and moderation query indexes are missing.

## API and operational gaps

- No versioned API, canonical response envelope, cursor pagination, or stable
  machine-readable error codes.
- No password reset/logout routes in the inspected API despite the product plan.
- No request classes, policy coverage, visible rate limits, or idempotency.
- No scheduler transitions, notification dispatch contract, or queue retry rules.
- No meaningful automated product tests beyond starter examples.
- No documented backup/restore, monitoring, incident, or deployment procedure.

## Recommended remediation

Follow `docs/natsvibe-mvp-technical-spec.md`, section 20. Do not build more public
features on the current authorization boundary. The first engineering milestone
should secure identity/roles and make venue-to-discovery behavior contract-tested.
