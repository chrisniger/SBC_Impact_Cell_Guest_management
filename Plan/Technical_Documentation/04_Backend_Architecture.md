# Backend Architecture

## Technology Stack

| Technology | Version | Purpose |
|-----------|---------|---------|
| Node.js | — | JavaScript runtime |
| Express.js | 4.21.x | HTTP server and routing |
| Prisma Client | 5.20.x | Database ORM (MySQL/MariaDB) |
| JWT (jsonwebtoken) | 9.0.x | Stateless authentication |
| bcryptjs | 2.4.x | Password hashing |
| Multer | 1.4.x | File upload handling |
| Nodemailer | 8.0.x | Email sending (SMTP) |
| csv-parse | 5.5.x | CSV file parsing |
| cookie-parser | 1.4.x | Cookie parsing (token fallback) |
| Morgan | 1.10.x | HTTP request logging |
| dotenv | 16.4.x | Environment variable loading |

## Express Setup

### Entry Point: `server/server.js`

```javascript
// Middleware stack (in order):
app.use(cors({...}))           // CORS with configurable origins
app.use(cookieParser())        // Parse cookies for token fallback
app.use(morgan("tiny"))        // Request logging

// Health check
app.get("/api/health", ...)   // Returns { ok: true }

// JSON body parser (5mb limit) — only under /api
app.use("/api", express.json({ limit: "5mb" }))

// Route mounting
app.use("/api/auth", authRoutes)
app.use("/api/users", userRoutes)
app.use("/api/guests", guestRoutes)
app.use("/api/csv", csvRoutes)
app.use("/api/reports", reportRoutes)
app.use("/api/impact", impactRoutes)
app.use("/api/notifications", notificationRoutes)

// Static frontend (production)
app.use(express.static(distDir, ...))
// SSR fallback for non-API routes
app.use(/^(?!\/api).*/, async (req, res, next) => { ... })

// Global error handler
app.use(errorHandler)

app.listen(PORT)
```

### CORS Configuration
```javascript
app.use(cors({
  origin: process.env.CORS_ORIGIN?.split(",") || true,
  credentials: true,
}))
```
- Supports multiple origins via comma-separated `CORS_ORIGIN` env var
- Defaults to `true` (allow all) if not set
- Credentials enabled for cookie-based auth fallback

### Body Parser
- Only applied to `/api` routes
- 5MB limit for JSON payloads
- FormData (file uploads) handled by Multer separately

## Route Mounting Pattern

```
router.use(requireAuth)        // Router-level middleware (all routes below require auth)
router.get("/", c.list)        // Endpoint → controller method
router.post("/", requireRole(...), c.create)  // With additional role middleware
```

Routes are thin — they only define:
1. HTTP method and path
2. Middleware chain (requireAuth, requireRole)
3. Controller method reference

## Controller Pattern

Controllers follow a "fat controller" pattern where business logic lives in controller functions:

```javascript
exports.list = async (req, res) => {
  // 1. Extract query params
  const { q, status, joinWhen } = req.query
  // 2. Build Prisma where clause
  const where = {}
  // 3. Query database
  const guests = await prisma.guest.findMany({ where, include: {...} })
  // 4. Respond
  res.json(guests)
}
```

Each controller exports named functions that correspond 1:1 with route handlers.

## Middleware Chain

### requireAuth (JWT Verification)
**Location:** `server/middleware/auth.js`

```javascript
async function requireAuth(req, res, next) {
  // 1. Extract token from Authorization header or cookie
  const header = req.headers.authorization || ""
  const token = header.startsWith("Bearer ") ? header.slice(7) : req.cookies?.token
  
  // 2. Verify JWT
  const payload = jwt.verify(token, process.env.JWT_SECRET)
  
  // 3. Look up user (ensures still active)
  const user = await prisma.user.findUnique({ where: { id: payload.sub } })
  if (!user?.active) return res.status(401)
  
  // 4. Set req.user
  req.user = {
    sub: user.id,
    name: user.fullName,
    role: user.role,
    roles: normalizeRoles(user),
    impactCellId: user.impactCellId
  }
  
  // 5. Apply active role override from header
  const requestedRole = req.headers["x-active-role"]
  if (requestedRole && req.user.roles.includes(requestedRole)) {
    req.user.role = requestedRole
  }
  
  next()
}
```

### requireRole (Role Check Factory)
```javascript
function requireRole(...roles) {
  return (req, res, next) => {
    if (!req.user || !roles.includes(req.user.role)) {
      return res.status(403).json({ error: "Forbidden" })
    }
    next()
  }
}
```

## Error Handling

### Global Error Handler
**Location:** `server/middleware/error.js`

```javascript
function errorHandler(err, _req, res, _next) {
  console.error(err)
  const status = err.status || 500
  res.status(status).json({ error: err.message || "Server error" })
}
```
- Placed after all route and static middleware
- Catches unhandled errors from controllers and middleware
- Respects `err.status` if set (e.g., from `sanitize()` which throws `{ status: 400, message: "..." }`)

## SSR Fallback (Production)

When `dist/client/` exists (production build):

1. Static files served from `dist/client/` with 1-hour cache
2. All non-API requests (`/^(?!\/api(?:\/|$)).*/`) are handled by TanStack Start SSR
3. SSR server module imported dynamically from `dist/server/server.js`
4. `createWebRequest()` converts Express request to standard `Request` object
5. `sendWebResponse()` converts Web `Response` back to Express response
6. On SSR failure: responds with 500 and error message
7. If no frontend build exists: logs warning, API-only mode

## Prisma Singleton Pattern

**Location:** `server/db.js`

```javascript
const { PrismaClient } = require("@prisma/client")
const prisma = global.__prisma || new PrismaClient()
if (process.env.NODE_ENV !== "production") global.__prisma = prisma
module.exports = prisma
```
- Uses `global.__prisma` to prevent multiple Prisma Client instances during development (nodemon hot-reload)
- In production, creates new instance on each restart
- Imported by all controllers

## Server Port

| Environment | API Port | Frontend Port | Notes |
|-------------|----------|---------------|-------|
| Development | 3001 | 3000 (Vite) | Vite proxies /api → 3001 |
| Production | 3000 | 3000 (same) | Express serves both API + frontend |

Default port: `process.env.PORT || 3000`

## CORS Origins
- In production: set to deployed frontend URL (e.g., `https://crm.summitdata.one`)
- In development: `http://localhost:3000,http://localhost:3001`

## Health Check
`GET /api/health` → `{ ok: true }` — used for uptime monitoring.
