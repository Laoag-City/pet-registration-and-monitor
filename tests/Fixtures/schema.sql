-- SQLite-only test fixture. Never load this into the application database.
PRAGMA foreign_keys = ON;
CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name VARCHAR NOT NULL, email VARCHAR NOT NULL UNIQUE, email_verified_at DATETIME, password VARCHAR NOT NULL, remember_token VARCHAR(100), created_at DATETIME, updated_at DATETIME, contact_number VARCHAR(40), date_of_birth DATE, role VARCHAR(20) NOT NULL DEFAULT 'user');
CREATE TABLE password_reset_tokens (email VARCHAR PRIMARY KEY, token VARCHAR NOT NULL, created_at DATETIME);
CREATE TABLE sessions (id VARCHAR PRIMARY KEY, user_id INTEGER, ip_address VARCHAR(45), user_agent TEXT, payload TEXT NOT NULL, last_activity INTEGER NOT NULL);
CREATE TABLE cache (key VARCHAR PRIMARY KEY, value TEXT NOT NULL, expiration INTEGER NOT NULL);
CREATE TABLE cache_locks (key VARCHAR PRIMARY KEY, owner VARCHAR NOT NULL, expiration INTEGER NOT NULL);
CREATE TABLE jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, queue VARCHAR NOT NULL, payload TEXT NOT NULL, attempts INTEGER NOT NULL, reserved_at INTEGER, available_at INTEGER NOT NULL, created_at INTEGER NOT NULL);
CREATE TABLE job_batches (id VARCHAR PRIMARY KEY, name VARCHAR NOT NULL, total_jobs INTEGER NOT NULL, pending_jobs INTEGER NOT NULL, failed_jobs INTEGER NOT NULL, failed_job_ids TEXT NOT NULL, options TEXT, cancelled_at INTEGER, created_at INTEGER NOT NULL, finished_at INTEGER);
CREATE TABLE failed_jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, uuid VARCHAR NOT NULL UNIQUE, connection TEXT NOT NULL, queue TEXT NOT NULL, payload TEXT NOT NULL, exception TEXT NOT NULL, failed_at DATETIME DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE pets (id INTEGER PRIMARY KEY AUTOINCREMENT, code VARCHAR UNIQUE, name VARCHAR(120) NOT NULL, species VARCHAR(60) NOT NULL, breed VARCHAR(120), sex VARCHAR(20) NOT NULL, date_of_birth DATE, color VARCHAR(80), created_at DATETIME, updated_at DATETIME, owner_name VARCHAR(120), owner_email VARCHAR(120), owner_number VARCHAR(40), photo_path VARCHAR, deleted_at DATETIME);
CREATE TABLE vaccinations (id INTEGER PRIMARY KEY AUTOINCREMENT, pet_id INTEGER NOT NULL REFERENCES pets(id) ON DELETE CASCADE, vaccine VARCHAR(120) NOT NULL, administered_at DATE NOT NULL, next_due_at DATE, veterinarian VARCHAR(120), notes TEXT, created_at DATETIME, updated_at DATETIME, schedule_completed_at DATE);
CREATE TABLE deworming_records (id INTEGER PRIMARY KEY AUTOINCREMENT, pet_id INTEGER NOT NULL REFERENCES pets(id) ON DELETE CASCADE, product VARCHAR(120) NOT NULL, administered_at DATE NOT NULL, next_due_at DATE, weight_kg DECIMAL(5,2), notes TEXT, created_at DATETIME, updated_at DATETIME, schedule_completed_at DATE);
CREATE TABLE notifications (id VARCHAR PRIMARY KEY, type VARCHAR NOT NULL, notifiable_type VARCHAR NOT NULL, notifiable_id INTEGER NOT NULL, data TEXT NOT NULL, read_at DATETIME, created_at DATETIME, updated_at DATETIME);
CREATE INDEX notifications_notifiable_index ON notifications(notifiable_type, notifiable_id);
CREATE TABLE animal_health_monitoring_records (id INTEGER PRIMARY KEY AUTOINCREMENT, visit_date DATE NOT NULL, barangay VARCHAR(150) NOT NULL, farmer_name VARCHAR(150) NOT NULL, species VARCHAR(20) NOT NULL, sex VARCHAR(10) NOT NULL, age_value DECIMAL(7,2), age_unit VARCHAR(10), cases INTEGER, clinical_signs TEXT, disorder VARCHAR(255), remarks TEXT, created_at DATETIME, updated_at DATETIME);
CREATE TABLE routine_service_records (id INTEGER PRIMARY KEY AUTOINCREMENT, visit_date DATE NOT NULL, barangay VARCHAR(150) NOT NULL, farmer_name VARCHAR(150) NOT NULL, activity VARCHAR(255) NOT NULL, species VARCHAR(20) NOT NULL, sex VARCHAR(10) NOT NULL, age_value DECIMAL(7,2), age_unit VARCHAR(10), number_of_heads INTEGER NOT NULL, remarks TEXT, created_at DATETIME, updated_at DATETIME);

ALTER TABLE vaccinations ADD COLUMN deleted_at DATETIME;
ALTER TABLE deworming_records ADD COLUMN deleted_at DATETIME;
ALTER TABLE animal_health_monitoring_records ADD COLUMN deleted_at DATETIME;
ALTER TABLE routine_service_records ADD COLUMN deleted_at DATETIME;
CREATE TABLE record_deletion_audits (id INTEGER PRIMARY KEY AUTOINCREMENT, actor_id INTEGER NOT NULL, record_table VARCHAR(80) NOT NULL, record_id INTEGER NOT NULL, action VARCHAR(30) NOT NULL, created_at DATETIME NOT NULL);
CREATE TABLE record_change_audits (id INTEGER PRIMARY KEY AUTOINCREMENT, actor_id INTEGER NOT NULL, record_table VARCHAR(80) NOT NULL, record_id INTEGER NOT NULL, action VARCHAR(30) NOT NULL, reason VARCHAR(500), changes TEXT, created_at DATETIME NOT NULL);
