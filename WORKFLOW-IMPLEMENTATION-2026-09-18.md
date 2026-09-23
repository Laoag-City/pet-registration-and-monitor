# PawID medical workflows and public QR pages

Implemented on 18 September 2026 in the existing Laravel application. This report covers this workflow request; the earlier audit document remains a historical record.

## 1. Files modified and added

Modified for this request:

- `app/Http/Controllers/VaccinationController.php`
- `app/Http/Controllers/DewormingController.php`
- `app/Http/Controllers/QRCodeController.php`
- `app/Models/User.php`
- `app/Models/Pet.php`
- `routes/web.php`
- `resources/views/vaccinations.blade.php`
- `resources/views/deworming.blade.php`
- `resources/views/qrcodes.blade.php`
- `resources/views/qrcodes/pdf.blade.php`
- `resources/views/features.blade.php`
- `resources/views/how-it-works.blade.php`
- `tests/Feature/SystemAuditTest.php`

Added:

- `app/Http/Controllers/Concerns/MedicalRecordWorkflow.php`
- `app/Http/Requests/MedicalRecordRequest.php`
- `app/Support/PetQrReference.php`
- `resources/views/medical/steps.blade.php`
- `resources/views/medical/identity.blade.php`
- `resources/views/medical/select.blade.php`
- `resources/views/medical/form.blade.php`
- `resources/views/medical/review.blade.php`
- `resources/views/qrcodes/scan.blade.php`
- `resources/views/qrcodes/scan-not-found.blade.php`
- `resources/views/qrcodes/scan-unavailable.blade.php`
- `tests/Feature/MedicalWorkflowTest.php`
- `tests/Feature/PublicQrScanTest.php`
- `bootstrap/workflow-readonly.php`
- This report.

Changes from the preceding audit were preserved. `.env`, PostgreSQL configuration, seeders and migrations were not modified. No schema migration was needed.

## 2. Routes added or changed

| Method | URL | Named route | Purpose |
|---|---|---|---|
| GET | `/staff/vaccinations/create` | `vaccinations.create` | Owner search, pet selection and details; selection uses validated query parameters. |
| POST | `/staff/vaccinations/review` | `vaccinations.review` | Validate and review before saving, or return to edit details. |
| POST | `/staff/vaccinations` | `vaccinations.store` | Existing route, now requires a matching owner and pet. |
| GET | `/staff/vaccinations/{vaccination}/edit` | `vaccinations.edit` | Edit existing vaccination details. |
| PUT | `/staff/vaccinations/{vaccination}` | `vaccinations.update` | Validate and save corrections. |
| GET | `/staff/deworming/create` | `deworming.create` | Owner search, pet selection and details. |
| POST | `/staff/deworming/review` | `deworming.review` | Validate and review before saving, or return to edit details. |
| POST | `/staff/deworming` | `deworming.store` | Existing route, now requires a matching owner and pet. |
| GET | `/staff/deworming/{deworming}/edit` | `deworming.edit` | Edit existing treatment details. |
| PUT | `/staff/deworming/{deworming}` | `deworming.update` | Validate and save corrections. |
| GET | `/scan/{reference?}` | `qrcodes.scan` | Public names-only scan result, or a safe error response. |

Existing `/pets/{pet}`, QR preview and PDF download routes remain protected, including older QR links, as explicitly selected by the user. No duplicate unprefixed edit endpoints were added.

## 3–5. Controllers, authorization and views

Both existing medical controllers now expose create, review, store, edit and update actions. A small shared workflow concern handles the two selection flows; the Form Request shares validation and role checks. Existing models and medical tables are reused.

The staff route group retains `auth`, `auth.session`, and `role:staff,admin`. No new policy or role middleware class was necessary. The Form Request also rejects non-managers. Create/review/update submissions retain normal CSRF protection.

The public scan GET route is stateless: session startup, session error sharing and CSRF-cookie middleware are excluded only there. It cannot mutate records and does not set session cookies. Medical POST/PUT routes are unaffected.

The old medical add modals were replaced by linked workflow pages. Existing Bootstrap cards, buttons, layout and sidebar are reused. The current step is marked in the four-step heading. Recent lists show Edit only to Staff/Admin. Existing sidebar name patterns correctly cover the new routes. QR page/PDF captions and explanatory copy now distinguish new public codes from private records.

## 6. Owner search

Server-side GET search supports partial name, email and contact-number matching. Laravel `whereLike` provides case-insensitive matching on PostgreSQL. Only `role=user` accounts appear. Search returns 12 owners per page, ordered consistently by name and ID, with search retained in pagination URLs. It selects only the owner-list fields.

After choosing an owner, the page queries only that owner's pets, also 12 per page. Pet cards show name, species, breed, sex and available photo. Empty owners display “No registered pets found for this owner.” No medical form is displayed until a valid owner/pet pair is selected.

`User::pets()` and `Pet::owner()` model the existing `pets.owner_email = users.email` association. No new ownership column or data backfill was introduced.

## 7–8. Vaccination and deworming creation

Both workflows are now:

**Add Record → Select Owner → Select Pet → Enter Details → Review and Save.**

The details and review screens show owner and pet prominently. Vaccinations retain vaccine, administered date, next due date, veterinarian and notes. Deworming retains product, administered date, next due date, weight and notes. No unsupported dosage or other column was invented.

Review validates all fields without creating a record. “Edit Details” returns the values for correction. Final save validates the owner and pet again and rechecks the relationship inside a transaction with row locks. Thus Owner A plus Owner B's pet cannot be saved, including when ownership changes after review.

Validation failures return to a GET form with old input and useful messages rather than redirecting to the POST-only review URL. Successful save redirects to the appropriate record list with a success message.

## 9–10. Medical corrections

Staff and Admin can edit records displayed in Recent Vaccinations / Recent Deworming. Existing values, owner and pet are shown. No arbitrary age limit is imposed; tests explicitly edit a record administered in 2015.

The update request prohibits owner and pet IDs and only updates allowed medical fields. The original `pet_id` remains unchanged. Owners receive 403 when manually requesting the implemented edit/update endpoints. Missing record IDs return 404. Success messages are:

- “Vaccination record updated successfully.”
- “Deworming record updated successfully.”

Record deletion remains outside this request and was not added.

## 11. Public QR scan page

Newly generated SVG/PDF QR codes target `/scan/{reference}`. The reference uses Laravel authenticated encryption, hides the internal ID, and is scoped specifically to public pet identification. It includes the pet's creation timestamp to avoid resolving an old reference to a reused ID. No persistent token table or mass QR regeneration is required.

The controller resolves the pet and then its current owner through the database relationship. It passes only two strings to the public view: owner name and pet name. URL-supplied owner names/IDs are ignored. Owner snapshots are not trusted for public display.

Public HTML does not include email, phone, address, medical history, microchip, PET code, password/hash, owner/pet model objects, or authenticated Admin information. A missing/deleted pet, missing regular owner, malformed/tampered reference or unrelated encrypted payload produces a generic 404. A database query failure produces a generic 503 rather than debug details. Responses disable caching, indexing and referrer sharing.

As requested, older `/pets/{id}` QR links still require login. Existing codes, IDs and records were not changed. Opening the QR preview or downloading a credential now generates the new public destination; older printed tags need to be replaced individually if the public page is desired. Previously generated public links continue working when another code is generated for the same pet.

## 12. Security protections

- Role middleware plus Form Request authorization for medical mutations.
- Server-side regular-owner validation and exact owner–pet matching at selection, review and save.
- Transactional ownership recheck at creation.
- Immutable pet association during corrections, including crafted update requests.
- Existing owner-only access to private pet/QR-generation endpoints preserved.
- Encrypted, purpose-scoped references and minimal public view data.
- Generic public errors even with local debug enabled, and no session cookies on scan responses.
- Escaped Blade text, bounded inputs and bound search parameters.

## 13–14. Tests and verification

**Final automated result: 58 tests passed, 810 assertions.** All mutation tests run in SQLite `:memory:` under the existing fail-closed test database guard.

Added 32 test cases across the new medical/public-QR test classes. Coverage includes Staff/Admin login and creation for both types; each workflow step; review without saving; edit-details preservation; both roles editing older records; owner/guest denial; immutable associations; mismatched owner/pet rejection; changed ownership after review; invalid dates; missing records; name/email/contact/partial search; no results; pagination; empty pets; invalid client selections; safe validation redirects; public names/privacy; tampered/missing/deleted QR references; current-owner lookup; legacy private routes; and simulated public database failures. Existing audit tests were updated for the newly required owner selection and public QR payload.

Non-destructive checks:

| Check | Result |
|---|---|
| `php artisan about` | Starts successfully; Laravel 12.69.1 / PHP 8.2.12 / PostgreSQL driver. |
| `php artisan migrate:status` | All 11 existing migrations Ran; no new migration. |
| `php artisan route:list --except-vendor` | 40 application routes; no naming conflict. |
| `php artisan test --compact` | 58 passed / 810 assertions. |
| `php bootstrap/workflow-readonly.php` | 34 passing PostgreSQL-backed read-only checks, including actual owner search, selection, forms, existing vaccination edit forms, public names/privacy and legacy protection. |
| Previous read-only route audit | 92 checks, 0 failures. |
| PHP syntax | 28 application/route/test/helper files passed. |
| `npm.cmd run build` | Passed; 58 modules built. |
| `git diff --check` | No whitespace errors. |

The PostgreSQL snapshot during this task contained 12 users, 5 pets, 1 vaccination and 0 deworming records. The existing vaccination edit page was loaded for Staff/Admin without updating it. There was no live deworming record to open; its edit/save behavior was tested with isolated fixtures. PostgreSQL helper checks use READ ONLY transactions and roll back. No live users/pets/medical records were created, deleted or edited by these checks. No existing password or database configuration was changed.

## 15. Remaining limitations

- Browser inventory was empty; interactive desktop/mobile visual checks and physical QR scanning were unavailable. Rendered Blade pages, HTTP behavior, real SVG/PDF generation and QR payload routing were tested. Do not treat those as visual/device verification.
- The existing ownership model still relies on email rather than a stable owner foreign key. This implementation validates and uses that existing relationship without migrating data.
- Public references depend on the application's encryption key. Future key rotation needs appropriate previous-key handling to keep already printed new references usable. No keys were changed or printed here.
- Older printed codes intentionally remain login-protected. New public pages require newly generated codes, per the user's explicit preference.
