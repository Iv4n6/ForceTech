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
- `http://localhost/ForceTech/index.html`

If your folder name differs, adjust the URL accordingly.

## 5.1) Clean Short Links

This project supports short links in this format:
- `http://localhost/ForceTech/Ab12Xy`

Requirements:
- Apache `mod_rewrite` enabled
- `.htaccess` support allowed in Apache config

Base short URL configuration
- By default the backend will auto-detect the current host and build short URLs for the same domain (this avoids forcing a specific domain).
- To override and force a specific domain (for example a production domain), open `api.php` and set:
  - `$baseShortUrl = 'https://yourdomain.example';`

If `$baseShortUrl` is left empty the script will use the current request host and path when returning `short_url` in the API response.

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
  "short_url": "http://localhost/ForceTech/Ab12Xy",
  "reused": false
}
```

### Redirect
- Method: `GET`
- URL: `/Ab12Xy` (preferred) or `api.php?c=Ab12Xy`
- Behavior: HTTP 302 redirect if found, otherwise 404 page.

## 8) Security Notes

- SQL injection protection via PDO prepared statements.
- URL format validation with scheme check (`http://` or `https://`).
- Random short code generation uses `random_int`.
- Collision handling for unique short codes.

## 9) Secrets & Git

- Do not commit secrets (passwords, API keys, private keys) to the repository. Use environment variables or a `.env` file that is ignored by Git.
- This project includes `.env.example` as a template. Copy it to `.env` and fill in private values locally.
- Ensure `.gitignore` contains `.env` and other local-only files (the repository includes a `.gitignore`).
- If you accidentally committed a secret, rotate the secret immediately (change the password/key) and purge it from the git history using a tool such as `git filter-repo` or the BFG Repo-Cleaner. Example (BFG):

```bash
# Remove a specific file from history (example: .env)
bfg --delete-files .env
# Then clean git and force-push (coordinate with collaborators):
git reflog expire --expire=now --all
git gc --prune=now --aggressive
git push --force
```

Note: History-rewriting operations affect collaborators — coordinate before performing them.