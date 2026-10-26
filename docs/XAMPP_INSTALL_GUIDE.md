# Windows / XAMPP Installation Guide

Written for someone who has not used XAMPP before. Follow the steps in
order.

## 1. Install prerequisites

1. Download XAMPP for Windows with **PHP 8.3** from
   https://www.apachefriends.org/ and run the installer. Accept the
   defaults (it installs to `C:\xampp`).
2. During/after setup, open **XAMPP Control Panel** and click **Start**
   next to both **Apache** and **MySQL**. Both rows should turn green.
   - If MySQL fails to start, the most common cause on Windows is port
     3306 already being used by another MySQL/MariaDB install, or by
     SQL Server. Use XAMPP's "Config" button next to MySQL to view its
     log for the exact error.
3. Confirm PHP works: open a Command Prompt and run `C:\xampp\php\php.exe -v`.
   It should print `PHP 8.3.x`.

## 2. Copy the project files

1. Copy the entire `farm-erp` folder (the one containing `public/`,
   `src/`, `database/`, etc.) into `C:\xampp\htdocs\`, so you end up with
   `C:\xampp\htdocs\farm-erp\public\index.php`.

## 3. Create the database

1. Open `http://localhost/phpmyadmin/` in a browser (Apache and MySQL
   must be running).
2. Click **New** in the left sidebar, type `farm_erp` as the database
   name, choose collation `utf8mb4_unicode_ci`, click **Create**.

You do **not** need to import any `.sql` file by hand -- the next step
creates all tables for you via migrations.

## 4. Configure the application

1. In `C:\xampp\htdocs\farm-erp\`, copy `.env.example` to a new file
   named `.env` (same folder).
2. Open `.env` in a text editor (Notepad is fine) and set:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=farm_erp
   DB_USERNAME=root
   DB_PASSWORD=
   ```
   (A stock XAMPP MySQL install has user `root` with an **empty**
   password by default -- change this if you have set one.)
3. Generate an application key and paste it over the placeholder value
   of `APP_KEY` in `.env`:
   ```
   C:\xampp\php\php.exe -r "echo bin2hex(random_bytes(32));"
   ```
4. Set `APP_URL` to `http://localhost/farm-erp/public`.

## 5. Run migrations, seed data, and create the first administrator

Open a Command Prompt **in the project folder**
(`cd C:\xampp\htdocs\farm-erp`) and run, one at a time:

```
C:\xampp\php\php.exe bin\console.php migrate
C:\xampp\php\php.exe bin\console.php seed
C:\xampp\php\php.exe bin\console.php create-admin
```

The third command asks for your name, email and a password (minimum 10
characters) and creates the one and only account that starts with every
permission in the system. There is no default password -- you choose it
here.

## 6. Open the application

- Public shop: `http://localhost/farm-erp/public/`
- Staff sign-in: `http://localhost/farm-erp/public/login`

If pages return a 404 or show raw PHP source instead of rendering,
Apache's `mod_rewrite` module is probably not enabled. In
`C:\xampp\apache\conf\httpd.conf`, make sure this line is **not**
commented out (no `#` at the start):

```
LoadModule rewrite_module modules/mod_rewrite.so
```

and that the `<Directory "C:/xampp/htdocs">` block has
`AllowOverride All` (so the project's `public/.htaccess` takes effect).
Restart Apache from the Control Panel after any change here.

## 7. Everyday use

- **Start working:** open XAMPP Control Panel, start Apache + MySQL,
  then browse to the URLs above.
- **Add staff accounts:** sign in as the administrator, go to **Users &
  Permissions**, create a user, and tick the roles they need.
- **Back up your data:** see `docs/BACKUP_RESTORE.md`.

## 8. Optional: Docker

A native XAMPP install (above) is the supported path and is what this
guide and the acceptance testing cover. If you would rather run this in
Docker, the application itself needs nothing Docker-specific -- any
PHP 8.1+ / MySQL 8 container pair works, provided:

- the `public/` folder is the web root,
- `mod_rewrite` (or your reverse proxy's equivalent URL rewriting) sends
  every request to `public/index.php` the way `public/.htaccess` does,
- `.env` is mounted/provided with real database credentials.

No `docker-compose.yml` is shipped with this delivery -- writing and
testing one against a real Docker daemon was out of scope for this pass
(the sandbox this project was built in has no Docker daemon available;
see `docs/FINAL_ACCEPTANCE_REPORT.md`). A minimal compose file for a
`php:8.3-apache` + `mysql:8` pair is a short, low-risk follow-up if you
want it.
