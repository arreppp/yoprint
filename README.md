# YoPrint CSV Upload System

A production-ready Laravel 13 application for uploading, validating, and processing CSV product data — with real-time status updates, audit logging, bulk operations, and email notifications.

---

## Table of Contents

- [Overview](#overview)
- [Tech Stack](#tech-stack)
- [Features](#features)
- [Project Structure](#project-structure)
- [Database Schema](#database-schema)
- [API Reference](#api-reference)
- [Installation](#installation)
- [Running the App](#running-the-app)
- [Configuration](#configuration)
- [How It Works](#how-it-works)
- [Testing](#testing)

---

## Overview

YoPrint allows users to upload CSV files containing product data. The system validates the file before processing, imports the data asynchronously in the background, and provides real-time progress updates via WebSockets (with a polling fallback). Every action is audit-logged, and users can optionally receive an email notification when processing completes.

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13 (PHP 8.3) |
| Database | SQLite (zero-config; swap to MySQL/PostgreSQL via `.env`) |
| Queue | Laravel Database Queue (no Redis required) |
| WebSockets | Laravel Reverb |
| Frontend | Vanilla JavaScript + Pusher JS SDK |
| Email | Laravel Mail (log driver by default; SMTP configurable) |

---

## Features

- **Drag-and-drop CSV upload** with client-side and server-side validation
- **Background processing** via Laravel queue jobs — non-blocking uploads
- **UPSERT logic** — existing products (matched by `UNIQUE_KEY`) are updated; new ones are inserted
- **Real-time progress** via Laravel Reverb WebSockets, with automatic fallback to polling
- **Pagination, search, and filtering** on the upload history table
- **Bulk operations** — delete or retry multiple uploads at once
- **Download processed CSV** — includes a `status` and `error` column per row
- **Audit logging** — every action (upload, process, delete, retry, download, export) is recorded
- **Email notifications** — sent on completion or failure, with the processed file attached
- **Stats dashboard** — live counts of total, processing, completed, and failed uploads

---

## Project Structure

```
app/
├── Events/
│   └── FileUploadStatusChanged.php     # Broadcasts status via WebSocket
├── Http/
│   ├── Controllers/
│   │   └── UploadController.php        # Handles all HTTP requests
│   ├── Requests/
│   │   └── UploadCsvRequest.php        # Validates file type, size, email
│   └── Resources/
│       ├── FileUploadResource.php      # Transforms FileUpload for API response
│       └── AuditLogResource.php        # Transforms AuditLog for API response
├── Jobs/
│   └── ProcessCsvUpload.php            # Parses CSV, upserts products, saves results
├── Mail/
│   └── UploadCompletedMail.php         # Email notification on completion/failure
├── Models/
│   ├── FileUpload.php                  # Tracks each upload (status, progress, paths)
│   ├── Product.php                     # Stores imported product records
│   └── AuditLog.php                    # Immutable audit trail
└── Services/
    └── CsvValidatorService.php         # Pre-processing validation (columns, encoding)

config/
└── csv.php                             # Max size, required columns, validation toggle

database/migrations/
├── ..._create_file_uploads_table.php
├── ..._create_products_table.php
└── ..._create_audit_logs_table.php

resources/views/
├── uploads/index.blade.php             # Main UI (self-contained HTML/CSS/JS)
└── emails/upload-completed.blade.php   # Email template

routes/
├── api.php                             # 9 API endpoints
└── web.php                             # Single web route (serves the UI)
```

---

## Database Schema

### `file_uploads`

Tracks every CSV upload from submission through processing.

| Column | Type | Description |
|---|---|---|
| `id` | bigint | Primary key |
| `filename` | string | Internal storage path |
| `original_filename` | string | User's original filename |
| `status` | enum | `pending` → `processing` → `completed` / `failed` |
| `total_rows` | integer | Total data rows in the CSV |
| `processed_rows` | integer | Successfully upserted rows |
| `failed_rows` | integer | Rows that errored during processing |
| `error_message` | text | Top-level error (e.g. file not found) |
| `validation_errors` | json | Pre-processing validation errors |
| `processed_file_path` | string | Path to the output CSV |
| `notification_email` | string | Where to send the completion email |
| `started_at` | timestamp | When the job started |
| `completed_at` | timestamp | When the job finished |
| `notification_sent` | boolean | Whether the email was sent |
| `deleted_at` | timestamp | Soft delete |

### `products`

Stores the imported product data. Records are upserted by `unique_key`.

| Column | Type | CSV Column |
|---|---|---|
| `unique_key` | string (unique) | `UNIQUE_KEY` |
| `product_title` | text | `PRODUCT_TITLE` |
| `product_description` | text | `PRODUCT_DESCRIPTION` |
| `style` | string | `STYLE#` |
| `sanmar_mainframe_color` | string | `SANMAR_MAINFRAME_COLOR` |
| `size` | string | `SIZE` |
| `color_name` | string | `COLOR_NAME` |
| `piece_price` | decimal(10,2) | `PIECE_PRICE` |

### `audit_logs`

Immutable record of every system action.

| Column | Type | Description |
|---|---|---|
| `file_upload_id` | bigint (FK) | Related upload (nullable for system actions) |
| `action` | string | `upload`, `process`, `delete`, `retry`, `download`, `export` |
| `status` | string | `success` or `failed` |
| `details` | text | Human-readable description |
| `metadata` | json | Structured extra data |
| `ip_address` | string | Request IP |
| `user_agent` | text | Browser info |

---

## API Reference

All endpoints are prefixed with `/api`.

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/uploads` | Upload a CSV file |
| `GET` | `/uploads` | List uploads (paginated, filterable) |
| `GET` | `/uploads/{id}` | Get a single upload |
| `GET` | `/uploads/{id}/download` | Download the processed CSV |
| `POST` | `/uploads/{id}/retry` | Retry a failed or completed upload |
| `POST` | `/uploads/bulk/delete` | Delete multiple uploads |
| `POST` | `/uploads/bulk/retry` | Retry multiple uploads |
| `GET` | `/audit-logs` | List audit logs (paginated, filterable) |
| `GET` | `/audit-logs/export` | Export audit logs as CSV |

### `POST /api/uploads`

```
Content-Type: multipart/form-data

file               required  CSV file (max 10MB)
notification_email optional  Email to notify on completion
```

**Response `201`:**
```json
{
  "message": "File uploaded successfully",
  "data": {
    "id": 1,
    "original_filename": "products.csv",
    "status": "pending",
    "created_at": "2026-04-25T10:00:00+00:00"
  }
}
```

### `GET /api/uploads`

Query parameters:

| Param | Description |
|---|---|
| `page` | Page number (default: 1) |
| `per_page` | Items per page (default: 15, max: 100) |
| `search` | Filter by filename |
| `status` | Filter by status (`pending`, `processing`, `completed`, `failed`) |
| `sort_by` | Column to sort by (`created_at`, `original_filename`, `status`, `processed_rows`) |
| `sort_order` | `asc` or `desc` (default: `desc`) |
| `start_date` | Filter by date (inclusive) |
| `end_date` | Filter by date (inclusive) |

**Response `200`:**
```json
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 42,
    "last_page": 3
  },
  "stats": {
    "total": 42,
    "pending": 2,
    "processing": 1,
    "completed": 38,
    "failed": 1
  }
}
```

### `POST /api/uploads/bulk/delete`

```json
{ "ids": [1, 2, 3] }
```

### `POST /api/uploads/bulk/retry`

```json
{ "ids": [4, 5] }
```

---

## Installation

### Prerequisites

- PHP 8.2+
- Composer
- Node.js (optional, only if you modify JS/CSS)

### Steps

```bash
# 1. Clone the repo
git clone https://github.com/arreppp/yoprint.git
cd yoprint

# 2. Install PHP dependencies
composer install

# 3. Set up environment
cp .env.example .env
php artisan key:generate

# 4. Create the SQLite database and run migrations
touch database/database.sqlite
php artisan migrate

# 5. Create the storage symlink
php artisan storage:link
```

---

## Running the App

You need three processes running simultaneously. On Windows, double-click `start-dev.bat` to open all three in separate terminal windows automatically.

Or start them manually:

```bash
# Terminal 1 — Web server
php artisan serve

# Terminal 2 — Queue worker (processes CSV jobs)
php artisan queue:work --verbose

# Terminal 3 — WebSocket server (real-time updates)
php artisan reverb:start --verbose
```

Then open **http://localhost:8000**.

---

## Configuration

All settings live in `.env`. Key values:

```env
# App
APP_NAME=YoPrint
APP_URL=http://localhost:8000

# Database (SQLite by default — change to mysql/pgsql as needed)
DB_CONNECTION=sqlite

# Queue (database driver requires no extra services)
QUEUE_CONNECTION=database

# WebSockets (Reverb)
BROADCAST_CONNECTION=reverb
REVERB_APP_KEY=your-key
REVERB_HOST=localhost
REVERB_PORT=8080

# Email (log by default — emails appear in storage/logs/laravel.log)
MAIL_MAILER=log
MAIL_FROM_ADDRESS=noreply@yoprint.com

# For real SMTP delivery, replace with:
# MAIL_MAILER=smtp
# MAIL_HOST=smtp.mailtrap.io
# MAIL_PORT=2525
# MAIL_USERNAME=your_username
# MAIL_PASSWORD=your_password

# CSV Processing
CSV_MAX_SIZE=10240              # Max file size in KB (default: 10MB)
CSV_VALIDATION_ENABLED=true    # Toggle pre-processing validation
CSV_REQUIRED_COLUMNS=UNIQUE_KEY,PRODUCT_TITLE,PRODUCT_DESCRIPTION,STYLE#,SANMAR_MAINFRAME_COLOR,SIZE,COLOR_NAME,PIECE_PRICE
```

---

## How It Works

### Upload Flow

```
User selects CSV
    → Client-side validation (type, size)
    → POST /api/uploads
    → Server validates file (UploadCsvRequest)
    → CsvValidatorService checks columns, encoding, sample rows
    → File stored to storage/app/uploads/
    → FileUpload record created (status: pending)
    → AuditLog entry created (action: upload)
    → ProcessCsvUpload job dispatched to queue
    → 201 response returned immediately
```

### Processing Flow (background job)

```
ProcessCsvUpload job runs
    → Status updated to "processing"
    → FileUploadStatusChanged event broadcast via Reverb
    → CSV parsed row by row
    → Each row: Product::updateOrCreate(['unique_key' => ...], [...])
    → Progress broadcast every 50 rows
    → Processed CSV saved to storage/app/processed/
    → Status updated to "completed" (or "failed")
    → AuditLog entry created (action: process)
    → Email sent if notification_email is set
```

### UPSERT Logic

```php
Product::updateOrCreate(
    ['unique_key' => $row['UNIQUE_KEY']], // match condition
    [
        'product_title'          => $row['PRODUCT_TITLE'],
        'product_description'    => $row['PRODUCT_DESCRIPTION'],
        'style'                  => $row['STYLE#'],
        'sanmar_mainframe_color' => $row['SANMAR_MAINFRAME_COLOR'],
        'size'                   => $row['SIZE'],
        'color_name'             => $row['COLOR_NAME'],
        'piece_price'            => $row['PIECE_PRICE'],
    ]
);
```

If a product with that `UNIQUE_KEY` already exists, it is updated. Otherwise, a new record is inserted. This makes uploads idempotent — uploading the same file twice will not create duplicates.

### Real-time Updates

The frontend connects to Reverb on page load. If the connection succeeds within 5 seconds, status changes are pushed via WebSocket. If connection fails (Reverb not running, network issue), the frontend automatically falls back to polling every 3 seconds. The connection status is shown in the top-right corner of the UI as **● Live** or **● Polling**.

---

## Testing

Two sample CSV files are included in `storage/app/`:

**`test_import.csv`** — 10 products for initial import

**`test_updated.csv`** — 4 rows: 2 updates to existing products, 2 new inserts

### Suggested Test Scenarios

1. **Basic upload** — Upload `test_import.csv`, watch status go `pending → processing → completed`
2. **Upsert** — Upload `test_updated.csv`, confirm P001/P003 prices changed and P011/P012 were added
3. **Validation error** — Upload a CSV missing a required column (e.g. no `UNIQUE_KEY` header) and confirm the error is shown
4. **Retry** — Force a failure (delete the file from storage), then retry via the UI
5. **Bulk delete** — Select multiple completed uploads and delete them
6. **Download** — Click the download button on a completed upload and verify the output CSV
7. **Audit logs** — Click "Audit Logs" and confirm every action above was recorded
8. **Email** — Enter an email address before uploading; check `storage/logs/laravel.log` for the rendered email (log driver)
