# NEWTRI Certificate Portal — Security Specification

## 1. Data Invariants
1. **PII Isolation**: Student email addresses and phone numbers reside strictly in `student_contacts/{certificateId}` and are readable/writable ONLY by verified admins. Public verification (`public_certificates/{qrToken}` and `cert_id_index/{certificateId}`) never stores or exposes email/phone.
2. **No Public Collection Scraping**: `allow list` on `public_certificates`, `cert_id_index`, `student_contacts`, `expenses`, `settings`, and `admins` is strictly restricted to `isAdmin()`. Public verifiers can only perform exact-key `get` lookups by `qrToken` or `certificateId`.
3. **Admin Identity Verification**: Admin privileges require `request.auth != null && request.auth.token.email_verified == true` AND either the bootstrapped owner email (`vibishv66@gmail.com`) or an explicit document in `/admins/$(request.auth.uid)`.
4. **Immutable Creator & Server Timestamps**: `createdBy` must equal `request.auth.uid` on create and cannot change on update; `createdAt` must equal `request.time` on create and remain immutable; `updatedAt` must equal `request.time` on create and update.
5. **Path Variable & Payload Bounds**: Every document ID matches `^[a-zA-Z0-9_-]{1,128}$` and every string field enforces explicit `.size()` limits.

## 2. Dirty Dozen Adversarial Payloads
1. Unauthenticated `list` query on `/student_contacts` -> `PERMISSION_DENIED`
2. Unauthenticated `get` on `/student_contacts/NT-2026-ABC123` -> `PERMISSION_DENIED`
3. Unauthenticated `list` query on `/public_certificates` -> `PERMISSION_DENIED`
4. Authenticated non-admin user `create` on `/public_certificates/{qrToken}` -> `PERMISSION_DENIED`
5. Authenticated user with `email_verified: false` attempting admin write -> `PERMISSION_DENIED`
6. Authenticated non-admin attempting self-escalation `create` on `/admins/{uid}` -> `PERMISSION_DENIED`
7. Admin `create` on `/public_certificates/{qrToken}` with shadow field `isRoot: true` -> `PERMISSION_DENIED`
8. Admin `create` on `/public_certificates/{qrToken}` with spoofed `createdAt` timestamp != `request.time` -> `PERMISSION_DENIED`
9. Admin `update` on `/public_certificates/{qrToken}` mutating immutable `createdBy` or `qrToken` -> `PERMISSION_DENIED`
10. Admin `create` on `/expenses/{id}` with negative `amount: -500` -> `PERMISSION_DENIED`
11. Admin `create` on `/expenses/{id}` with invalid payment `method: "Crypto"` -> `PERMISSION_DENIED`
12. Path injection `get` on `/public_certificates/` with oversized (>128 chars) ID -> `PERMISSION_DENIED`
