# Environment Configuration

## Environment Variables

### Source Files
- `.env` (actual values — not committed)
- `.env.example` (template — committed)

### Variable Reference

| Variable | Required | Default | Description | Used In |
|----------|----------|---------|-------------|---------|
| `DATABASE_URL` | **Yes** | — | MySQL connection string: `mysql://USER:PASS@HOST:3306/DB_NAME` | `prisma/schema.prisma`, `server/db.js` |
| `JWT_SECRET` | **Yes** | — | Secret key for JWT signing/verification | `server/controllers/auth.controller.js`, `server/middleware/auth.js` |
| `JWT_EXPIRES_IN` | No | `7d` | JWT token expiration duration (e.g., `7d`, `24h`) | `server/controllers/auth.controller.js` |
| `PORT` | No | `3000` | Express server port (production) | `server/server.js` |
| `CORS_ORIGIN` | No | `true` (allow all) | Comma-separated allowed CORS origins | `server/server.js` |
| `NODE_ENV` | No | — | Environment (`production`, `development`) | `server/db.js` |
| `SMTP_HOST` | No | `""` | SMTP server hostname | `server/lib/mailer.js` |
| `SMTP_PORT` | No | `587` | SMTP server port | `server/lib/mailer.js` |
| `SMTP_SECURE` | No | `false` | Use TLS/SSL for SMTP | `server/lib/mailer.js` |
| `SMTP_USER` | No | `""` | SMTP authentication username (also used as fallback `fromEmail`) | `server/lib/mailer.js` |
| `SMTP_PASS` | No | `""` | SMTP authentication password | `server/lib/mailer.js` |
| `SMTP_FROM` | No | `""` | Email from address (overrides name/email composition) | `server/lib/mailer.js` |
| `VITE_API_BASE_URL` | No | `/api` | API base URL for frontend (build-time) | `src/lib/api.ts` |
| `APP_URL` | No | `req.headers.origin` | Application URL (for password reset links) | `server/controllers/auth.controller.js` |
| `FRONTEND_URL` | No | `APP_URL` | Frontend URL (alternative to APP_URL) | `server/controllers/auth.controller.js` |

---

## Environment File Template

```bash
# --- Database (MariaDB / MySQL on Hostinger) ---
DATABASE_URL="mysql://DB_USER:DB_PASSWORD@HOST:3306/DB_NAME"

# --- Auth ---
JWT_SECRET="change_this_to_a_long_random_string"
JWT_EXPIRES_IN="7d"

# --- Server ---
PORT=4000
CORS_ORIGIN="https://crm.summitdata.one"
NODE_ENV=production

# --- SMTP / Email Notifications ---
SMTP_HOST="smtp.example.com"
SMTP_PORT=587
SMTP_SECURE=false
SMTP_USER="no-reply@example.com"
SMTP_PASS="change_this_password"
SMTP_FROM="SBC Guest Management <no-reply@example.com>"

# --- Frontend (build-time) ---
# Leave empty to call the same origin (recommended when frontend + backend deploy together)
VITE_API_BASE_URL=/api
```

---

## Configuration by Environment

### Development

| Setting | Value |
|---------|-------|
| PORT | 3001 (Express) |
| CORS_ORIGIN | `http://localhost:3000,http://localhost:3001` |
| VITE_API_BASE_URL | `/api` (proxied by Vite) |
| NODE_ENV | `development` (or unset) |

### Production

| Setting | Value |
|---------|-------|
| PORT | 3000 (or as configured on Hostinger) |
| CORS_ORIGIN | Deployed domain (e.g., `https://crm.summitdata.one`) |
| VITE_API_BASE_URL | `/api` (same origin deployment) |
| NODE_ENV | `production` |

---

## Environment Variable Resolution Hierarchy

### SMTP Settings
1. Database `SmtpSetting` record (id = "singleton")
2. Environment variables (`SMTP_HOST`, `SMTP_PORT`, etc.)
3. Default values (587 for port, `false` for secure, "SBC Application" for fromName)

### API Base URL (Vite)
```
VITE_API_BASE_URL ?? "/api"
```

### Password Reset URL
```
APP_URL ?? FRONTEND_URL ?? req.headers.origin ?? req.protocol + "://" + req.get("host")
```

### CORS Origins
```javascript
process.env.CORS_ORIGIN?.split(",") || true
```
- Production: single URL or comma-separated list
- Fallback: `true` (allow all origins)

---

## Build-Time vs Run-Time

| Variable | Type | Set When |
|----------|------|----------|
| `VITE_API_BASE_URL` | Build-time | During `npm run build` |
| All other variables | Run-time | When starting the server |
| `DATABASE_URL` | Run-time | Read by Prisma at runtime |

---

## Script Commands

| Script | Command | Loads .env? |
|--------|---------|-------------|
| `npm run dev` | `vite dev` | No (Vite handles) |
| `npm run dev:server` | `nodemon server/server.js` | Yes (via dotenv) |
| `npm run build` | `vite build` | Yes (Vite handles) |
| `npm start` | `node server/server.js` | Yes (via dotenv) |
| `npm run seed` | `node prisma/seed.js` | Yes (via `import "dotenv/config"`) |
| `npm run prisma:migrate` | `prisma migrate deploy` | Yes (Prisma reads DATABASE_URL) |
