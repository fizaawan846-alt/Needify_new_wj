# Stage 5 – Needs Management

## Overview
The **Needs Management** feature lets users:
- Browse community needs (`needs.php`) with search, category, status filters and pagination.
- Post a new need (`post-need.php`) with strict validation and CSRF protection.
- View, edit, and delete **their own** needs (`my-needs.php`, `edit-need.php`, `delete-need.php`).
- Communicate with request owners via the **Contact Poster** button (links to `messages.php`).

All pages use the shared security helpers from `includes/functions.php`:
- `e()` – output escaping
- `sanitize_text()` – input cleaning
- `format_price()` – PKR formatting
- CSRF token generation/validation (`post_need_csrf`, `edit_need_csrf`, `delete_need_csrf`).
- Session timeout (100 s inactivity) and secure cookie settings from `config/session.php`.

## Files Added / Updated
| File | Purpose |
|------|---------|
| `needs.php` | Public board with filters, safe SQL using prepared statements, and card UI. |
| `post-need.php` | Form handling, server‑side validation, CSRF, budget handling, DB insert. |
| `my-needs.php` | Dashboard showing logged‑in user's needs, delete form with CSRF, flash messages. |
| `edit-need.php` | Edit form with CSRF, validation, update query, success token regeneration. |
| `delete-need.php` | POST‑only delete with CSRF and ownership check. |
| `tests/test_stage5.php` | Simple syntax‑check script for all Stage 5 files. |
| `assets/css/main.css` (extended) | Styles for need cards, tables, flash messages, and responsive layout. |

## How to Run the Feature
1. **Start MySQL** in XAMPP (ensure the `needify` database exists and the `needs` table is created). Example schema:
```sql
CREATE TABLE needs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    budget DECIMAL(10,2) NULL,
    status ENUM('open','in_progress','fulfilled','closed') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```
2. Open the site in a browser (e.g. `http://localhost/Needify/`).
3. Sign up / log in, then navigate to **Browse Needs** or **My Needs**.
4. Use the **Post a Need** button to create a request. Validation errors appear inline; successful posts redirect back with a flash message.
5. In **My Needs**, edit or delete your own entries. Deleting prompts a JavaScript `confirm()` and then posts to `delete-need.php`.

## Testing
Run the automated suite (requires PHP CLI):
```bash
php tests/test_stage1.php
php tests/test_stage2.php
php tests/test_stage3.php
php tests/test_stage4.php
php tests/test_stage5.php
```
All Stage 5 files now pass the syntax check.

## Known Issue
The earlier test suites (Stage 1‑4) report a MySQL socket permission error because MySQL is not running. Start the MySQL service in XAMPP and re‑run the tests to verify full integration.

---
*If you need any additional UI tweaks, pagination, or further backend validation, let me know!*