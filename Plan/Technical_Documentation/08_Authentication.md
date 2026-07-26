# Authentication System

## Overview

The system uses **stateless JWT authentication**. Tokens are issued on login, stored in `localStorage`, and sent with every API request via the `Authorization: Bearer` header. Logout is purely client-side (token discard). Password reset uses server-generated crypto tokens stored in the database.

---

## JWT Token Generation

### Location: `server/controllers/auth.controller.js:sign()`

```javascript
function sign(user) {
  const roles = normalizeRoles(user)
  return jwt.sign(
    {
      sub: user.id,         // Subject = user UUID
      role: user.role,      // Primary role
      roles,                // All normalized roles (array)
      name: user.fullName,  // User's display name
    },
    process.env.JWT_SECRET,         // Secret key from env
    { expiresIn: process.env.JWT_EXPIRES_IN || "7d" }
  )
}
```

### Token Payload
```json
{
  "sub": "user-uuid",
  "role": "Administrator",
  "roles": ["Administrator", "Supervisor"],
  "name": "John Doe",
  "iat": 1712345678,
  "exp": 1712950478
}
```

### Configuration
| Variable | Default | Description |
|----------|---------|-------------|
| `JWT_SECRET` | (required) | Secret key for signing |
| `JWT_EXPIRES_IN` | `7d` | Token expiration duration |

---

## Token Verification

### Location: `server/middleware/auth.js:requireAuth()`

```javascript
async function requireAuth(req, res, next) {
  // 1. Extract token
  const header = req.headers.authorization || ""
  const token = header.startsWith("Bearer ")
    ? header.slice(7)
    : req.cookies?.token

  if (!token) return res.status(401).json({ error: "Unauthorized" })

  try {
    // 2. Verify JWT
    const payload = jwt.verify(token, process.env.JWT_SECRET)

    // 3. Look up user (ensures account still exists and is active)
    const user = await prisma.user.findUnique({
      where: { id: payload.sub },
      select: {
        id: true, fullName: true, role: true,
        roles: true, active: true, impactCellId: true,
      },
    })

    if (!user?.active) return res.status(401).json({ error: "Unauthorized" })

    // 4. Attach to request
    req.user = {
      sub: user.id,
      name: user.fullName,
      role: user.role,
      roles: normalizeRoles(user),
      impactCellId: user.impactCellId,
    }

    // 5. Active role override
    const requestedRole = req.headers["x-active-role"]
    if (requestedRole && req.user.roles.includes(requestedRole)) {
      req.user.role = requestedRole
    }

    next()
  } catch (err) {
    return res.status(401).json({ error: "Invalid token" })
  }
}
```

### Token Sources (in order of precedence)
1. `Authorization: Bearer <token>` header (primary)
2. `token=<jwt>` cookie (fallback)

---

## Login Flow

```
Client                          Server
  │                               │
  │  POST /api/auth/login         │
  │  { username, password }       │
  │ ──────────────────────────►   │
  │                               │  Finds user by username OR email
  │                               │  (both active and inactive)
  │                               │
  │                               │  bcrypt.compare(password, user.passwordHash)
  │                               │
  │                               │  If match: jwt.sign() → token
  │                               │
  │  { token, user }              │
  │ ◄──────────────────────────   │
  │                               │
  │  Stores token in              │
  │  localStorage[cgms.token]     │
  │                               │
  │  Stores active role in        │
  │  localStorage[cgms.activeRole]│
```

### Login Details
- `username` field accepts **username or email**
- Only **active** users can login (`active: true`)
- Password verification: `bcrypt.compare(password, user.passwordHash)`
- On success: returns JWT token + user object (without `passwordHash`)
- Client stores token in `localStorage` under key `cgms.token`
- Client calls `applyActiveRole()` to set the active role

---

## Logout Flow

```
Client                          Server
  │                               │
  │  POST /api/auth/logout        │
  │ ──────────────────────────►   │
  │                               │  Returns { ok: true }
  │ ◄──────────────────────────   │  (Stateless — no server action)
  │                               │
  │  Clears localStorage:         │
  │  - cgms.token                 │
  │  - cgms.activeRole            │
  │  Sets user = null             │
```

Logout is **stateless** — the server simply acknowledges the request. The actual logout is performed client-side by:
1. Clearing the token from `localStorage`
2. Clearing the active role from `localStorage`
3. Setting `user` state to `null`
4. Redirecting to `/login`

---

## Forgot Password Flow

```
Client                          Server
  │                               │
  │  POST /api/auth/forgot-password
  │  { email }                    │
  │ ──────────────────────────►   │
  │                               │  Looks up user by email
  │                               │  If found:
  │                               │    crypto.randomBytes(32) → hex token
  │                               │    PasswordReset.create({
  │                               │      userId, token,
  │                               │      expiresAt: now + 1 hour
  │                               │    })
  │                               │    sendMail(to, subject, resetUrl)
  │                               │
  │  { ok: true }                 │  (Always returns ok — security)
  │ ◄──────────────────────────   │
```

### Security Notes
- Server **always** returns `{ ok: true }` regardless of whether the email exists (prevents email enumeration)
- Reset token: 64-character hex string from `crypto.randomBytes(32)`
- Token expires in 1 hour (`3600_000` ms)
- Email contains link: `<baseUrl>/reset-password?token=<token>`
- Base URL resolved from: `APP_URL` → `FRONTEND_URL` → `req.headers.origin` → `req.protocol + req.get("host")`

---

## Reset Password Flow

```
Client                          Server
  │                               │
  │  POST /api/auth/reset-password
  │  { token, newPassword }       │
  │ ──────────────────────────►   │
  │                               │  Find PasswordReset by token
  │                               │  Validate:
  │                               │    └── token exists
  │                               │    └── !used
  │                               │    └── !expired (expiresAt < now)
  │                               │
  │                               │  $transaction([
  │                               │    User.update({ passwordHash }),
  │                               │    PasswordReset.update({ used: true })
  │                               │  ])
  │                               │
  │  { ok: true }                 │
  │ ◄──────────────────────────   │
```

### Password Reset Details
- `newPassword` is hashed with `bcrypt.hash(password, 10)`
- Uses Prisma `$transaction` for atomicity
- Token is marked as `used: true` to prevent replay
- Token is deleted from server side after use (used flag)

---

## Active Role Switching

The system supports users with **multiple roles**. An active role mechanism allows users to switch between their assigned roles:

### Client Side
```typescript
// Store
activeRoleStore.get() → localStorage.getItem("cgms.activeRole")
activeRoleStore.set(r) → localStorage.setItem("cgms.activeRole", r)

// Switch role (no API call)
switchRole(role: Role) => {
  activeRoleStore.set(role)
  setUser({ ...current, role })
}

// Every API request
headers.set("X-Active-Role", activeRole)
```

### Server Side
```javascript
// In requireAuth middleware:
const requestedRole = req.headers["x-active-role"]
if (requestedRole && req.user.roles.includes(requestedRole)) {
  req.user.role = requestedRole
}
```

### Flow
1. Frontend sends `X-Active-Role` header with every request
2. Backend overrides `req.user.role` if the requested role is in the user's roles array
3. All `requireRole()` middleware checks use `req.user.role` (which may be the active override)
4. This allows role-based data scoping and authorization to work with the switched role

---

## Password Change

### Location: `server/controllers/auth.controller.js:changePassword()`

```
POST /api/auth/change-password
Auth: Required

Body: { currentPassword, newPassword }

Behavior:
1. Find user by req.user.sub
2. bcrypt.compare(currentPassword, stored hash)
3. If match: bcrypt.hash(newPassword, 10) → update user
4. Return { ok: true }
```

---

## Token Storage Summary

| Item | Storage Key | Type | Set When | Cleared When |
|------|-------------|------|----------|--------------|
| JWT Token | `cgms.token` | localStorage | Login success | Logout |
| Active Role | `cgms.activeRole` | localStorage | Login, switchRole | Logout |
| Token (cookie) | `token` | HTTP cookie | (fallback) | — |

---

## Security Considerations

1. **Password Storage:** bcrypt with 10 rounds of salting
2. **JWT Secret:** Must be set via `JWT_SECRET` environment variable
3. **Token Lifetime:** Configurable via `JWT_EXPIRES_IN` (default 7 days)
4. **No Refresh Tokens:** System uses long-lived tokens only
5. **Password Reset Token:** Crypto-random (32 bytes → 64 hex chars), 1-hour expiry, single-use
6. **Password Reset Email:** Always returns `ok: true` to prevent email enumeration
7. **No Rate Limiting:** Not implemented on any auth endpoint
8. **No Session Revocation:** Tokens cannot be invalidated server-side (stateless)
