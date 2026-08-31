# JIGPOLY Polytechnic GPS Attendance System

This PHP application manages lecturer class sessions, QR/location attendance, student attendance history, excuses, feedback, and administrator reports. It has been migrated from MySQL to PostgreSQL for Neon and packaged for Render Free using the same architecture as the previous JIGPOLY project.

## Required Render variables

Only these variables are required:

| Variable | Value |
|---|---|
| `DATABASE_URL` | Neon PostgreSQL connection string |
| `APP_ENV` | `production` |

The application automatically creates the PostgreSQL schema and seeds the initial accounts on the first request.

## Seed accounts

| Role | Email | Password |
|---|---|---|
| Administrator | `admin@jigpoly.edu.ng` | `123` |
| Lecturer | `lecturer@jigpoly.edu.ng` | `123` |
| Student | `student@jigpoly.edu.ng` | `123` |

Change these credentials in `config/db.php` before production use, or require password changes after the first login.

## GitHub and Render deployment

Create a GitHub repository and push the project:

```bash
git init
git add .
git commit -m "Migrate JIGPOLY GPS attendance to Neon PostgreSQL"
git branch -M main
git remote add origin https://github.com/YOUR-USERNAME/YOUR-REPOSITORY.git
git push -u origin main
```

In Render, create **New → Web Service**, connect the GitHub repository, choose Docker, and select the Free plan. The included Dockerfile installs `libpq-dev` before compiling `pdo_pgsql`, which is required for the Neon connection. Add `DATABASE_URL` as a secret and set `APP_ENV=production`. The health path is `/health.php`.

After deployment, visit the Render URL once. The first request creates the tables, colleges, departments, courses, and seed users.

## Free-plan warning

Render Free has an ephemeral filesystem. Uploaded excuse documents under `uploads/excuses/` can be lost after restart, redeploy, or spin-down. Use durable object storage before relying on these documents for production records. Render Free services may also sleep after inactivity.

## Local Docker test

```bash
docker build -t jigpoly-gps-attendance .
docker run --rm -p 8080:80 \
  -e DATABASE_URL="postgresql://USER:PASSWORD@HOST/DB?sslmode=require" \
  -e APP_ENV=production \
  jigpoly-gps-attendance
```

Open `http://localhost:8080/health.php`.

## References

[1]: https://render.com/docs/free "Render Free documentation"
[2]: https://neon.com/postgresql/php/connect "Neon PHP PDO PostgreSQL connection"
