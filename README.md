# URL Shortener (PHP + MySQL + Vue 3 + Tailwind)

A lightweight URL Shortener web app with:
- PHP 8.x backend API (`api.php`) using PDO (prepared statements)
- MySQL database (`schema.sql`)
- Vue 3 + Tailwind single-page frontend (`index.html`)
- Apache rewrite support for clean short links (`.htaccess`)

## 1) Prerequisites

Install one of the following local stacks:
- XAMPP
- WAMP
- MAMP

Make sure these services are running:
- Apache (or Nginx + PHP)
- MySQL

Also confirm your PHP version is 8.x.

## 2) Project Placement

Place this project folder in your web server root, for example:
- XAMPP: `C:\xampp\htdocs\url-shortener`
- WAMP: `C:\wamp64\www\url-shortener`
- MAMP (Windows): `C:\MAMP\htdocs\url-shortener`

## 3) Create Database and Table

1. Open phpMyAdmin (usually `http://localhost/phpmyadmin`).
2. Go to SQL tab.
3. Run the contents of `schema.sql`.

This creates:
- Database: `url_shortener`
- Table: `urls`

## 4) Configure Database Credentials

Open `api.php` and update these values as needed:
- `$dbHost`
- `$dbName`
- `$dbUser`
- `$dbPass`

Default values are suitable for many local XAMPP setups (`root` with empty password).

## 5) Run the Application

Open in browser:
- `http://localhost/url-shortener/index.html`

If your folder name differs, adjust the URL accordingly.

## 5.1) Clean Short Links

This project supports short links in this format:
- `http://localhost/url-shortener/Ab12Xy`

Requirements:
- Apache `mod_rewrite` enabled
- `.htaccess` support allowed in Apache config

If you want a custom domain style output (like `https://short.me/Ab12Xy`), set this in `api.php`:
- `$baseShortUrl = 'https://short.me';`

## 6) How It Works

- Submit a long URL from the frontend form.
- Frontend sends `POST /api.php` with JSON payload.
- Backend validates URL and checks for existing mapping.
- If URL already exists, existing short link is returned.
- Otherwise, backend generates a random 6-8 char alphanumeric code and stores it.
- Open `api.php?c=SHORTCODE` to redirect to the original URL.

## 7) API Endpoints

### Create/Reuse Short URL
- Method: `POST`
- URL: `api.php`
- Body (JSON):
  ```json
  {
    "url": "https://example.com/some/long/path"
  }
  ```

Success response (example):
```json
{
  "success": true,
  "short_code": "Ab12Xy",
  "short_url": "http://localhost/url-shortener/Ab12Xy",
  "reused": false
}
```

### Redirect
- Method: `GET`
- URL: `/Ab12Xy` (preferred) or `api.php?c=Ab12Xy`
- Behavior: HTTP 302 redirect if found, otherwise 404 page.

## 8) Security Notes

- SQL injection protection via PDO prepared statements.
- URL format validation with scheme check (`http://` or `https://`) and `filter_var`.
- Random short code generation uses `random_int`.
- Collision handling for unique short codes.

## 9) Production Recommendations

- Restrict CORS to your domain instead of `*`.
- Store DB credentials in environment variables.
- Serve via HTTPS.
- Add rate limiting and request logging.
- Add authentication if you need private link management.

## 10) GitHub Submission Steps

From your project folder, run:

```bash
git init
git add .
git commit -m "Initial URL shortener project"
git branch -M main
git remote add origin https://github.com/<your-username>/<your-repo>.git
git push -u origin main
```

If the remote already exists, use:

```bash
git remote set-url origin https://github.com/<your-username>/<your-repo>.git
git push -u origin main
```
