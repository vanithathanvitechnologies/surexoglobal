# Surexoglobal PHP + MySQL API

Separate PHP + MySQL REST API backend for the **Surexoglobal** storefront (`./`) and admin portal (`./#/admin`), built with portable relative local paths so the exact same codebase runs cleanly on both localhost (XAMPP / Laragon / PHP server) and live cPanel hosting (`https://ttbilling.in/surexoglobal/` & `https://ttbilling.in/surexoglobal/#/admin`).

## Folder Structure

```text
surexoglobal/
├── index.html                  # Loads ./api-config.js & ./assets/index-BA_jXLoH.js with local ./logo.jpg
├── api-config.js               # Auto-resolves local/live ./api base path & exposes window.SurexoAPI
├── .htaccess                   # Routes ./api and ./uploads directly, preserves SPA & /admin fallback
├── uploads/                    # Local uploaded images (products, categories, requests, avatars)
└── api/
    ├── .htaccess               # Blocks direct config/sql access, passes Authorization header, clean routes
    ├── index.php               # Health check & unified REST router
    ├── config/
    │   ├── config.php          # Local vs Production MySQL credentials & local upload paths
    │   └── database.php        # PDO MySQL singleton
    ├── helpers/
    │   ├── response.php        # CORS, JSON responses, camelCase formatters, product rating recalculator
    │   ├── auth.php            # Admin & Customer Bearer/Custom-Header token verification
    │   └── upload.php          # Secure local image uploads -> ./uploads/<folder>/<file>
    ├── database/
    │   ├── schema.sql          # 12 MySQL tables + seed data (products, categories, admin, coupons, etc.)
    │   └── install.php         # One-click CLI/browser database initializer & seeder
    ├── website/                # Public & Customer Storefront Endpoints
    │   ├── bootstrap.php       # GET  Initial storefront data (products, categories, reviews, coupons, faqs, etc.)
    │   ├── products.php        # GET  Catalog listing with filters/search/sort & single product detail
    │   ├── categories.php      # GET  Categories with live product counts
    │   ├── reviews.php         # GET  Approved reviews | POST submit customer review (+ recalc rating)
    │   ├── orders.php          # GET  Customer orders / track order | POST place checkout order
    │   ├── coupons.php         # GET  Active coupons | POST validate & calculate discount
    │   ├── custom_requests.php # GET  Requests | POST submit sourcing request (with image upload)
    │   ├── contact.php         # POST Contact Us inquiry -> contact_messages table
    │   ├── auth.php            # POST login, register, logout, forgot_password, change_password | GET/PUT profile
    │   ├── cart.php            # GET/POST/PUT/DELETE persistent cart sync
    │   ├── wishlist.php        # GET/POST/PUT/DELETE persistent wishlist sync
    │   └── upload.php          # POST multipart image uploads for custom requests
    └── admin/                  # Authenticated Admin Portal Endpoints
        ├── auth.php            # POST login, logout | GET me
        ├── bootstrap.php       # GET  All admin master datasets in 1 authenticated call
        ├── dashboard.php       # GET  Live revenue, order/product/customer KPIs & alerts
        ├── products.php        # GET/POST/PUT/PATCH(toggle_stock)/DELETE Products Master
        ├── categories.php      # GET/POST/PUT/DELETE Categories Master
        ├── orders.php          # GET/PUT(status/tracking/payment/notes)/DELETE Orders Master
        ├── reviews.php         # GET/POST/PUT/DELETE Reviews & Default Ratings Master
        ├── requests.php        # GET/PUT(status/quotation/notes)/DELETE Custom Requests Master
        ├── customers.php       # GET/POST/PUT/DELETE Customers Master
        ├── coupons.php         # GET/POST/PUT/DELETE Coupons Master
        ├── messages.php        # GET/PUT/DELETE Contact Us messages
        └── upload.php          # POST multipart image uploads for products/categories/avatars
```

## Quick Setup (Local — XAMPP / Laragon / PHP)

1. Start **Apache** and **MySQL** in XAMPP / Laragon (or copy the `surexoglobal` folder into `htdocs/surexoglobal`).
2. Check database credentials in `api/config/config.php` (defaults for local: host `127.0.0.1`, db `surexoglobal_db`, user `root`, password `""`).
3. Create & seed the MySQL database using either:
   - **phpMyAdmin**: Import `api/database/schema.sql`, OR
   - **One-click installer**: Visit `http://localhost/surexoglobal/api/database/install.php` (or run `php api/database/install.php` in terminal).
4. Open:
   - Website: `http://localhost/surexoglobal/`
   - Admin Portal: `http://localhost/surexoglobal/#/admin`
   - API Health: `http://localhost/surexoglobal/api/`

## Deploying to Live (`https://ttbilling.in/surexoglobal/`)

1. Create a MySQL database & user in cPanel and import `api/database/schema.sql` via phpMyAdmin.
2. Update the production block (`else` branch) in `api/config/config.php` with your cPanel `DB_NAME`, `DB_USER`, and `DB_PASS`.
3. Upload all files (including `api/`, `uploads/`, `api-config.js`, `index.html`, `.htaccess`, and `assets/index-BA_jXLoH.js`) to your `public_html/surexoglobal/` directory.
4. Delete `api/database/install.php` on production after verifying the database is seeded.
5. Verify:
   - Live Website: `https://ttbilling.in/surexoglobal/`
   - Live Admin: `https://ttbilling.in/surexoglobal/#/admin`
   - Live API Health: `https://ttbilling.in/surexoglobal/api/`

## Default Seeded Credentials

- **Admin Portal** (`#/admin`): `admin@surexoglobal.com` / `admin123`
- **Sample Customer** (`#/login`): `nivetha@gmail.com` / `customer123`
