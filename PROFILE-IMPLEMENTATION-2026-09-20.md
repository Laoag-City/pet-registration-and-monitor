# PawID My Profile implementation

## 1. Files modified

Added:

- `app/Http/Controllers/ProfileController.php`
- `app/Http/Requests/UpdateProfileRequest.php`
- `resources/views/profile/show.blade.php`
- `resources/views/profile/edit.blade.php`
- `tests/Feature/ProfileTest.php`
- `bootstrap/profile-readonly.php`
- This report.

Modified:

- `routes/web.php`
- `resources/views/layouts/app.blade.php`

Existing audit and medical/QR workflow changes were preserved. No migration, seeder, `.env`, PostgreSQL configuration, or existing password was changed for this feature.

## 2. Routes

| Method | URL | Name |
|---|---|---|
| GET | `/profile` | `profile.show` |
| GET | `/profile/edit` | `profile.edit` |
| PUT | `/profile` | `profile.update` |

All use `auth`, `auth.session`, and `role:user`. Guests redirect to login. This self-service feature is for regular pet owners; Staff/Admin continue using their existing management workflows. No profile route accepts a user ID.

## 3. Controller behavior

The account is determined solely by the authenticated session. Update locks and edits that existing user; it does not create a new account.

Because PawID currently associates pets using owner email, profile changes synchronize the existing pets' owner email/name/contact fields in the same database transaction. Pet IDs, user ID, actual ownership, medical rows, and QR references remain intact. The current authenticated user is refreshed without logging out. Public QR pages read the updated owner's name through the User/Pet relationship; no new QR-specific name copy is stored.

Changing email to an address already attached to otherwise unmatched pet records is rejected to prevent claiming those records. A simulated pet-update failure verifies that the user update rolls back too.

## 4. Validation

- Full name: required string, maximum 255 characters; 120 for pet owners to fit the existing pet owner columns.
- Email: required valid email, normalized to lowercase, unique excluding the authenticated user's own ID; same length limits as name.
- Contact number: nullable string, maximum 40 characters, preserving the existing permissive format for spaces, prefixes and extensions.
- Date of birth: nullable valid `YYYY-MM-DD` date, not in the future; impossible dates are rejected.
- Protected IDs, roles, permissions, credentials, tokens and backend timestamps are prohibited.

Validation errors return to Edit Profile, retain submitted values and display Bootstrap messages next to the affected fields. Nullable contact/DOB fields remain compatible with existing records.

## 5. Views and navigation

My Profile displays Full Name, Email Address, Contact Number and Date of Birth, with an Edit Profile button. Edit Profile provides Save Changes and Cancel. Successful updates return to My Profile with:

“Your personal details have been updated successfully.”

The existing Bootstrap layout, cards, colors and typography are reused. My Profile appears in the regular owner's desktop/mobile sidebar and account dropdown. Both profile pages highlight exactly one item in each navigation menu.

## 6. Security

- Server-side role/authentication middleware and Form Request authorization.
- Account selection comes only from the session, never URL/body IDs.
- Explicit four-field update allowlist; no unrestricted mass assignment.
- Role escalation and account-ID manipulation requests are rejected.
- CSRF protection remains enabled on profile updates.
- Password hashes, remember tokens and backend fields are not rendered by the profile views.
- Own-pet access, medical record associations and existing public QR links survive email/name changes.
- Existing Admin User Management remains separate and unchanged by this feature.

Email verification is not currently enforced by PawID: there is no active verified middleware/verification workflow and User does not implement MustVerifyEmail. On email change, any old `email_verified_at` value is cleared because it cannot verify the new address. Normal edits leave that status unchanged. No new verification requirement or notification flow was introduced.

## 7. Editable profile fields

Full name, email address, contact number and date of birth only. User ID, role, pet IDs, medical data, permissions and authentication fields cannot be edited here.

## 8. Password functionality

PawID has Admin password editing, but no existing owner self-service password-change page. This feature does not add or link owners to the Admin password editor. Personal details can be saved without changing a password; submitted password fields are rejected. Existing password hashes and remember tokens remain unchanged during profile updates.

## 9. Tests added

`ProfileTest` adds 35 cases including data-provider cases. Coverage includes:

- Login, view/edit/save, correct displayed identity and success message.
- Guest redirection and Staff/Admin separation.
- No rendered credentials/backend controls.
- Email normalization, own-current-email acceptance, duplicate rejection, and login with the new email.
- Same user ID, maintained session, unchanged passwords/tokens and other users' records.
- Unchanged medical rows and continued pet/QR access after email/name updates.
- URL/query/body ID manipulation and 14 protected-field injection cases.
- Invalid/oversized fields, formatted contact numbers, nullable fields and impossible/future DOB.
- Inline validation and old-input preservation.
- Orphan-pet email-claim prevention and existing column limits.
- Desktop/mobile navigation active states, CSRF enforcement and transaction rollback.

## 10. Results and data safety

- `php artisan test --compact`: **93 passed, 1,074 assertions** across the full suite, including earlier authentication, medical workflow and QR tests.
- `php bootstrap/profile-readonly.php`: **15 checks passed** against existing PostgreSQL accounts, using read-only transactions and in-memory sessions/cache. Only GET profile pages were requested; no real profile was edited.
- PHP syntax checks passed for controller, request, test, helper and routes.
- `php artisan route:list --path=profile -vv` confirms all three routes and their authentication/role middleware.
- `git diff --check` found no whitespace errors; Git emitted only line-ending normalization notices.
- Before/after domain counts match: **11 users, 6 pets, 2 vaccinations, 1 deworming record**.

All mutation tests used SQLite `:memory:` under the existing fail-closed test configuration. No destructive database commands, development migrations, existing-account resets, or live record edits/deletions were performed.

## 11. Remaining limitations

- Browser inventory was empty, so interactive visual/mobile checks were unavailable. Blade output, navigation HTML, authentication and HTTP behavior were tested; no visual-browser success is claimed.
- PawID still uses email-based pet ownership. This feature preserves that relationship atomically; it does not introduce a stable owner foreign key or rewrite the schema.
- Owner self-service password change remains outside this implementation. Existing Admin credential management continues to work and remains covered by the regression suite.
