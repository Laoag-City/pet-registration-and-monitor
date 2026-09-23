# PawID: Profile, Microchip Removal, and Pet-Care Reminders

Implemented and verified on September 20, 2026 in `C:\xampp\htdocs\pet-registration`.

The existing My Profile feature was retained and retested. Microchip was removed from visible functionality while preserving stored values. Owners now have a notification center, unread indicator, and current vaccination/deworming schedules on their dashboard. The daily reminder command is implemented and has been run successfully; the operating-system scheduler trigger still needs to be configured by the operator, as requested.

## 1. Files created for this request

- `app/Console/Commands/SendPetCareReminders.php`
- `app/Http/Controllers/NotificationController.php`
- `app/Http/Middleware/ShareReminderCount.php`
- `app/Http/Requests/UpdatePetRequest.php`
- `app/Notifications/PetCareReminder.php`
- `app/Notifications/Channels/UniqueDatabaseChannel.php`
- `app/Services/PetCareReminders.php`
- `database/migrations/2026_09_20_000000_create_notifications_table.php`
- `resources/views/notifications/index.blade.php`
- `resources/views/notifications/schedule.blade.php`
- `resources/views/staff/edit-pet.blade.php`
- `tests/Feature/PetCareReminderTest.php`
- `tests/Feature/MicrochipRemovalTest.php`
- `bootstrap/care-readonly.php`
- This report.

## 2. Files modified for this request

- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/StaffController.php`
- `app/Http/Controllers/VaccinationController.php`
- `app/Http/Controllers/DewormingController.php`
- `app/Models/Pet.php`
- `routes/web.php`
- `routes/console.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/dashboard.blade.php`
- `resources/views/register.blade.php`
- `resources/views/staff/register-pet.blade.php`
- `resources/views/pet-details.blade.php`
- `resources/views/qrcodes/pdf.blade.php`
- `resources/views/vaccinations.blade.php`
- `resources/views/deworming.blade.php`
- `tests/Feature/PublicQrScanTest.php`

Existing unrelated changes in the workspace were preserved. `.env`, seeders, PostgreSQL configuration, and existing profile files were not modified for this request.

## 3. Routes added or changed

| Method | Route | Name | Access |
|---|---|---|---|
| GET | `/notifications` | `notifications.index` | Regular owner |
| PATCH | `/notifications/read-all` | `notifications.read-all` | Regular owner, own current reminders |
| PATCH | `/notifications/{notification}/read` | `notifications.read` | Regular owner, own current reminder |
| GET | `/staff/pets/{pet}/edit` | `staff.pets.edit` | Staff/admin |
| PUT | `/staff/pets/{pet}` | `staff.pets.update` | Staff/admin |

The existing authenticated shared routes and owner profile routes now use `ShareReminderCount` for the header indicator. Profile routes remain `GET /profile`, `GET /profile/edit`, and `PUT /profile`; none accept an account ID. Existing `/pets/{pet}` links remain login-protected. Public QR scan links remain opaque `/scan/{reference}` links.

## 4. Controllers added or changed

`NotificationController` lists only current reminders belonging to the authenticated owner and handles individual/all-current read actions. `DashboardController` supplies the owner's current care schedules. `StaffController` removes microchip creation input and adds pet editing with an explicit field allowlist.

Vaccination and deworming controllers now generate eligible reminders after medical saves within the same transaction. They lock the pet while saving/checking reminders. Their history pages retain old records but mark superseded schedules separately and exclude them from current overdue counts.

## 5. Models changed

`Pet` no longer permits mass assignment of `microchip` and hides the legacy attribute from serialization. Its database column and stored values remain intact. The existing `User` model already uses Laravel's `Notifiable` trait; no new user model infrastructure was needed. Vaccination and deworming models and their existing `next_due_at` fields were reused.

## 6. Blade views added or changed

Added a Bootstrap notification center with read/unread state and read actions, a shared schedule display, and a staff/admin Edit Pet form. Added Notifications to the owner navigation and an unread bell/count to the header. Added Upcoming Pet Care to the owner dashboard. Removed microchip labels/inputs/display from both registration templates, pet details, and the authenticated QR PDF. Updated medical history badges to show Superseded where applicable.

## 7. Validation changes

Removed microchip validation and persistence from registration. Legacy submitted microchip fields are ignored and cannot alter stored values. Edit Pet validates name, species, breed, sex, birth date, color, and optional photo using the existing field limits; ownership/ID fields are prohibited. Inline Bootstrap errors and submitted values are retained.

Existing My Profile validation remains in place: required bounded name, valid unique normalized email excluding the session user's own ID, optional contact up to 40 characters, and valid nonfuture birth date. Name/email limits accommodate the existing pet-owner columns. Security fields are prohibited.

## 8. Authorization and security

- Owner notification routes require `auth`, `auth.session`, and `role:user`.
- Notification lookup starts with the session user's Laravel notification relationship. Request/query owner IDs never select an account.
- Current schedule keys additionally verify present pet ownership, medical record, and due date. Another owner's or obsolete reminder returns 404 on a read action.
- Mutation routes use CSRF protection. Staff/admin cannot enter the owner notification workflow.
- Owners cannot create reminders through HTTP or edit medical due dates. Existing medical management remains staff/admin-only.
- Pet edits cannot transfer ownership and never accept credentials or system IDs through mass assignment.
- Notification payloads contain schedule identifiers, dates, and stage metadata; they contain no passwords, tokens, owner contact information, or medical notes.
- Notification reads and page visits do not generate reminders or change existing domain records.

## 9. My Profile implementation

The existing `ProfileController`, `UpdateProfileRequest`, and profile views already satisfy this part of the request and were preserved. Updates derive the account from the authenticated session, keep the same user ID, and transactionally synchronize the owner's existing pet contact snapshots when needed. Changing email preserves pet access, medical records, and existing opaque QR references. QR pages resolve the current owner name through the relationship.

Duplicate emails, role/ID manipulation, orphan-pet email claims, validation errors, and transaction rollback are covered by the existing profile tests, all of which passed again. Admin User Management remains separate.

## 10. Editable profile fields

Full name, email address, contact number, and date of birth. No new owner password-change workflow was introduced. Existing password handling is preserved; editing personal details does not require or reset a password.

## 11. Microchip removed from the UI

Microchip is absent from Register Pet, Edit Pet, pet details, My Pets, All Pets, dashboard, vaccination/deworming pages, QR preview/PDF, and the public QR scan page. Some of those pages already omitted it; both existing and changed pages were checked. Registration and editing work without the field.

There was no Edit Pet route initially, so a small staff/admin workflow was added. Its link appears on pet details for authorized managers.

## 12. Intentionally retained microchip references

The original migration that creates the nullable, unique `pets.microchip` column and historical migrations that position other columns after it remain unchanged. The model's hidden attribute prevents serialization. Regression tests deliberately insert legacy values directly to verify they remain stored and invisible. No active controller, form request, or Blade view reads or writes microchip.

## 13. Vaccination notifications

Uses `Vaccination.next_due_at`. The latest administered record for each pet and normalized vaccine name supplies the schedule. Vaccine names are compared after trimming and case normalization; a different vaccine remains an independent schedule. Reminder messages use current pet names and show Upcoming, Due Today, or Overdue.

## 14. Deworming notifications

Uses `DewormingRecord.next_due_at`. The latest administered deworming record for the pet supplies the schedule, including when a later treatment uses a different product. Statuses, ownership restrictions, date handling, and deduplication match vaccination reminders.

## 15. Notification center

`/notifications` displays pet name/link, treatment type/product, due date, current status, read/unread state, and reminder stage. It paginates 15 entries per page. Mark as Read and Mark All as Read affect only the authenticated owner's current notifications. The header shows the current unread count. Desktop and mobile navigation use the shared Notifications item.

Historical stages remain stored, but only the most urgent generated stage of each current schedule is displayed. Old dates and superseded schedules disappear from the center and unread count immediately, without waiting for the daily command. Display text is based on current records rather than stale saved messages.

## 16. Dashboard reminders

Upcoming Pet Care lists current schedules due within 30 days and all current overdue schedules for the authenticated owner's pets. Items show pet, treatment, due date, status, and days remaining. Schedules more than seven days away can appear here before notification generation begins. The empty state is: “No upcoming vaccination or deworming reminders.”

## 17. Duplicate prevention and reminder stages

Normal stages are seven days before, three days before, due today, and overdue. The command catches up in the current window when a run was missed or staff creates/corrects a schedule between thresholds:

| Days from today | Stage |
|---|---|
| More than 7 | No notification |
| 4–7 | 7-day reminder |
| 1–3 | 3-day reminder |
| 0 | Due today |
| Less than 0 | Overdue, generated once for that schedule |

A deterministic UUID includes the owner, pet, medical type, medical record, due date, and stage. The notifications primary key enforces uniqueness. The custom database channel extends Laravel's database channel and uses `firstOrCreate`, including unique-key race recovery, so repeated deliveries retain the original read timestamp. The scheduler also uses `withoutOverlapping`.

## 18. Superseded records and date corrections

Latest means greatest `administered_at`, with greatest ID breaking same-day ties. Backdated entries do not incorrectly replace a later administered treatment. A latest record with no next due date clears the previous schedule rather than reviving it. Separate vaccine types do not supersede one another.

Staff/admin corrections immediately re-evaluate eligible reminders. Current schedule keys exclude old dates, removed records, former owners, and superseded records from all notification reads. If the corrected date is beyond seven days, no premature notification is created. Medical history is retained, labeled Superseded where appropriate, and excluded from active overdue statistics.

## 19. Scheduled command

`pawid:care-reminders` processes pets in batches and determines owners from the existing relationships. It writes synchronous database notifications only: no mail, SMS, queue worker, or browser polling is required. `routes/console.php` schedules it daily at 08:00 in `config('app.timezone')` with overlap protection.

The current application timezone is **UTC**, so the configured run is **16:00 Asia/Manila**. The application timezone was not silently changed. Date comparisons use Carbon calendar dates in the configured timezone; a Manila midnight-boundary test passed.

## 20. Migration

Created and applied only `2026_09_20_000000_create_notifications_table.php`, PostgreSQL migration batch 5. It creates Laravel's standard notification columns, UUID primary key, and notifiable index. No existing domain tables or due-date columns were altered. Its rollback explicitly refuses to discard notification history; this is a forward-only migration.

The standard notification data column is TEXT. PostgreSQL notification filtering explicitly casts it to JSONB; isolated SQLite tests use JSON extraction. This database difference was caught during live read-only verification and fixed.

## 21. Manual commands and production scheduler setup

From PowerShell:

```powershell
Set-Location 'C:\xampp\htdocs\pet-registration'
& 'C:\xampp\php\php.exe' artisan pawid:care-reminders --dry-run
& 'C:\xampp\php\php.exe' artisan pawid:care-reminders
& 'C:\xampp\php\php.exe' artisan schedule:list
```

The dry run counts new eligible reminders without writing them. Running the normal command repeatedly is safe and does not duplicate stages.

For a Windows deployment, configure one Windows Task Scheduler task manually:

- **Program:** `C:\xampp\php\php.exe`
- **Arguments:** `"C:\xampp\htdocs\pet-registration\artisan" schedule:run`
- **Start in:** `C:\xampp\htdocs\pet-registration`
- **Trigger:** repeat every one minute indefinitely, including when no interactive user is logged in; use an account with the application's normal filesystem/database access.

Laravel decides whether the daily command is due. Do not schedule the reminder command itself every minute. Windows Task Scheduler was **not modified**.

For a Linux production deployment, the equivalent scheduler trigger is:

```cron
* * * * * cd /path/to/pawid && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

## 22. Automated tests added

Added 28 reminder cases and 3 microchip/pet-edit cases. They cover both medical types at all four reminder stages; owner isolation; date windows and null schedules; repeated generation/delivery; read-state preservation; stage progression; separate vaccine schedules; backdated/same-day records; superseded schedules; staff/admin corrections; immediate generation on medical creation; profile email changes; deletion/ownership/role changes; read/all-read authorization; CSRF; dry runs; page visits without generation; timezone boundaries; rollback on failed notification writes; registration/edit without microchip; hidden legacy values; QR compatibility; and edit validation/authorization.

The public QR regression fixture now explicitly stores a legacy microchip value to test its continued invisibility. All existing profile, medical workflow, system audit, and public QR tests were retained.

## 23. Test results and data preservation

| Check | Result |
|---|---|
| Complete feature/unit suite | **124 passed, 1,482 assertions** |
| New read-only PostgreSQL checks | **34 passed, 0 failures** |
| Existing profile read-only checks | **15 passed, 0 failures** |
| PostgreSQL reminder command, first run | **1 reminder created** |
| PostgreSQL reminder command, second run | **0 reminders created** |
| Final dry run | **0 new reminders** |
| PHP syntax checks | Passed for 16 changed/new application/helper PHP files |
| `git diff --check` | Passed; existing CRLF normalization notices only |
| `artisan about`, `migrate:status`, route inspection, `schedule:list` | Passed |

Tests were verified to use SQLite `:memory:` before execution. The existing `Tests\TestCase` fails closed unless the connection is isolated in-memory SQLite with no database URL. No test migrations or fixture writes were directed to `pet_registration`.

Live checks use a PostgreSQL READ ONLY transaction with array sessions/cache and rollback. Complete before/after checksums match for all rows in each domain table:

| Existing table | Count before/after | Complete contents unchanged |
|---|---|---|
| users | 11 / 11 | Yes, including password hashes and IDs |
| pets | 6 / 6 | Yes, including legacy microchip and ownership data |
| vaccinations | 2 / 2 | Yes |
| deworming_records | 1 / 1 | Yes |

The only intentional live database additions were the notifications table, its migration entry, and one eligible reminder. No existing users, pets, medical records, passwords, or QR-related pet identifiers were changed.

Verification artifacts are in `storage/logs/care-tests.txt`, `care-baseline.json`, `care-live-checks.json`, `care-before.json`, and `care-after.json`. These audit artifacts report aggregates/checksums, not credentials. Existing Laravel log entries were retained. The initial PostgreSQL JSON-query failure is resolved; the final test run's additional error entries are deliberate synthetic failure tests, and subsequent live checks completed without new application errors.

## 24. Remaining setup and limitations

- **Configure the operating-system scheduler trigger** using section 21. The Laravel daily schedule is registered, but a PHP application cannot run scheduled work without its external trigger. No Windows task was created automatically.
- Browser control returned no available browsers/apps. Page rendering and HTTP-kernel workflows were tested, but visual layout, mobile interaction, and an end-to-end physical QR-camera scan were not verified.
- Vaccines use the existing free-text name field. Case and surrounding spaces are normalized, but different spellings/synonyms are separate vaccine schedules; there is no existing vaccine catalog to resolve them automatically.
- The current application timezone remains UTC. Any intended move to Manila scheduling should be made explicitly in application timezone configuration and followed by restarting long-running application processes.
- No unresolved functional failure was observed in the completed checks. No email/SMS delivery or owner password-change feature was added.
