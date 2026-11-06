# Medical history versus next-treatment schedules

The medical tables store administrations in `administered_at`, and a separate
following obligation in `next_due_at`. Done closes the earlier row's schedule
and creates one new actual administration. These are two legitimate historical
administrations, not duplicate entries. Repeated completion remains rejected
under the existing transaction/locking checks.

The UI previously labelled full-history tables Recent and derived its status
from the next-due field. This made a new Done administration without a following
schedule appear as No due date instead of Completed. The medical-page statistics
also counted unscheduled administrations as Up to date.

The tables are now explicitly labelled Vaccination History and Deworming History.
Treatment completion uses the stored actual administration date (on/before today),
not the absence of a next due date. The same cell separately describes the next
treatment schedule. Missing/future administration dates are not labelled Completed.
No stored values are changed by this display logic.

Both medical-page statistics exclude NULL next due dates. Main history queries
remain unfiltered. Owner reminders already require a next due date; staff
dashboard analytics already skip NULL next due dates before adding attention
items. Owner dashboards reuse reminder schedules. Admin dashboards show counts
and recent registrations, not upcoming medical obligations. Those correct queries
were preserved. Importantly, do not remove NULL dates from the latest-record
supersession query: an unscheduled latest administration must still supersede
older overdue schedules.

Excel keeps all previous columns and all authorized history, including blank
next due dates, and appends Treatment Status separately from Schedule Completion
Status. Done never generates a due date when none was entered.

Verification: `python tests/run-without-migrations.py` runs all PHPUnit tests
from temporary copies, substituting the existing migration setup with the
reviewed `tests/Fixtures/schema.sql` SQLite fixture. The original guarded
Tests\TestCase requires :memory: storage. No migration or db commands run.
This checks application behavior against the fixture, not migration execution;
keep the fixture aligned with the schema when future schema changes are made.

Result: 177 tests, 2659 assertions passed. Regression coverage includes Done
with/without a following date, history retention, duplicate prevention, reminders,
attention, owner/admin dashboards, statuses and reopened XLSX downloads.

A read-only local check found six vaccination rows (one without next due date,
with a recorded past administration) and two deworming rows (none without a next
due date). No existing medical rows were updated or deleted. No schema change
or deployment migration is required.
