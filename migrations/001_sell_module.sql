PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS customers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  full_name TEXT NOT NULL,
  email TEXT NOT NULL,
  phone TEXT NOT NULL,
  preferred_contact TEXT NOT NULL CHECK (preferred_contact IN ('email','telefono','whatsapp')),
  consent_at TEXT NOT NULL,
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS watch_submissions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  public_token TEXT NOT NULL UNIQUE,
  customer_id INTEGER NOT NULL REFERENCES customers(id),
  brand TEXT NOT NULL,
  model TEXT NOT NULL,
  reference TEXT,
  approximate_year INTEGER,
  movement TEXT NOT NULL CHECK (movement IN ('automatico','manual','cuarzo','desconocido')),
  condition_summary TEXT NOT NULL,
  running_status TEXT NOT NULL,
  has_box INTEGER NOT NULL DEFAULT 0,
  has_papers INTEGER NOT NULL DEFAULT 0,
  has_accessories INTEGER NOT NULL DEFAULT 0,
  asking_price REAL,
  notes TEXT,
  status TEXT NOT NULL DEFAULT 'recibida',
  outcome TEXT NOT NULL DEFAULT 'pendiente',
  technician_notes TEXT,
  final_value REAL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS watch_images (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  submission_id INTEGER NOT NULL REFERENCES watch_submissions(id) ON DELETE CASCADE,
  original_name TEXT NOT NULL,
  mime_type TEXT NOT NULL,
  byte_size INTEGER NOT NULL,
  image_data BLOB NOT NULL,
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS valuations (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  submission_id INTEGER NOT NULL REFERENCES watch_submissions(id) ON DELETE CASCADE,
  provider TEXT NOT NULL,
  model TEXT NOT NULL,
  status TEXT NOT NULL,
  result_json TEXT,
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS availability_rules (
  weekday INTEGER PRIMARY KEY CHECK (weekday BETWEEN 1 AND 7),
  enabled INTEGER NOT NULL DEFAULT 0,
  start_time TEXT NOT NULL,
  end_time TEXT NOT NULL,
  slot_minutes INTEGER NOT NULL CHECK (slot_minutes BETWEEN 15 AND 240)
);

CREATE TABLE IF NOT EXISTS blocked_slots (
  starts_at TEXT PRIMARY KEY,
  reason TEXT,
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS appointments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  submission_id INTEGER NOT NULL UNIQUE REFERENCES watch_submissions(id),
  booking_code TEXT NOT NULL UNIQUE,
  starts_at TEXT NOT NULL UNIQUE,
  status TEXT NOT NULL DEFAULT 'solicitada' CHECK (status IN ('solicitada','confirmada','cancelada')),
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS submission_history (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  submission_id INTEGER NOT NULL REFERENCES watch_submissions(id) ON DELETE CASCADE,
  event_type TEXT NOT NULL,
  detail TEXT NOT NULL,
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS purchase_inquiries (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  public_code TEXT NOT NULL UNIQUE,
  product_id TEXT NOT NULL,
  product_name TEXT NOT NULL,
  listed_price REAL NOT NULL,
  full_name TEXT NOT NULL,
  email TEXT NOT NULL,
  phone TEXT NOT NULL,
  preferred_contact TEXT NOT NULL CHECK (preferred_contact IN ('email','telefono','whatsapp')),
  consent_at TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'recibida',
  created_at TEXT NOT NULL
);

INSERT OR IGNORE INTO availability_rules (weekday, enabled, start_time, end_time, slot_minutes) VALUES
  (1,1,'09:00','17:00',60),(2,1,'09:00','17:00',60),(3,1,'09:00','17:00',60),
  (4,1,'09:00','17:00',60),(5,1,'09:00','17:00',60),(6,0,'09:00','17:00',60),(7,0,'09:00','17:00',60);

CREATE TABLE IF NOT EXISTS schema_migrations (
  name TEXT PRIMARY KEY,
  applied_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

UPDATE availability_rules
SET enabled = 1, start_time = '09:00', end_time = '17:00'
WHERE weekday BETWEEN 1 AND 5
  AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '002_business_hours_09_17');

UPDATE availability_rules
SET enabled = 0, start_time = '09:00', end_time = '17:00'
WHERE weekday BETWEEN 6 AND 7
  AND NOT EXISTS (SELECT 1 FROM schema_migrations WHERE name = '002_business_hours_09_17');

INSERT OR IGNORE INTO schema_migrations (name) VALUES ('002_business_hours_09_17');
