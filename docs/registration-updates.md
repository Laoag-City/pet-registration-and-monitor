# Registration updates

Receipt Number is optional plain text (maximum 100 characters). Laravel's global TrimStrings and ConvertEmptyStringsToNull middleware normalize submitted values. Staff and admins use the existing protected pet create/update routes; owner permissions are unchanged. Blade escapes receipts on authenticated details pages. Receipts remain outside the Pet serialization allowlist and public QR responses.

The photo and receipt controls share a dedicated Bootstrap row with half-width desktop columns and full-width mobile columns. Existing photos and storage behavior are unchanged.

Owner sign-up uses App\Support\LaoagBarangays for the Address / Barangay (Laoag City) options and server validation. This field is optional, since no required-address business rule exists. Historical accounts retain a null barangay.

Staff pet management, staff owner management, and admin account management use ten records per page, retain query parameters, and sort by name then ID. Existing Excel exports continue to query the full record set independently of pagination.

Two additive migrations introduce pets.receipt_number (nullable string, 100 characters) and users.barangay (nullable string). No existing records or photo files are altered. These migrations have not been applied to an application database. Apply them through the normal reviewed release process; no deployment is included.

Regression coverage is in tests/Feature/RegistrationUpdatesTest.php, alongside the existing photo, permissions, QR, and Excel export suites. Tests require isolated SQLite :memory: storage, enforced by Tests\TestCase.
