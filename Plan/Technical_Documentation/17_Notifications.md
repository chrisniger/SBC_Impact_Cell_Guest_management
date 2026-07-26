# Notification System

## Overview

The notification system sends **email notifications** via SMTP when certain actions occur in the system. Notifications are configured through a combination of database-stored SMTP settings, notification rules, and environment variable fallbacks.

---

## Architecture

```
Trigger (e.g., guest reassigned to Impact Leader)
    │
    ▼
Controller calls notify(action, payload)
    │
    ▼
notifications.js: notify()
    ├── Find active NotificationRules for action
    ├── If no rules found but payload.to exists → use payload.to as fallback
    ├── sendMail() for each rule (parallel)
    │
    ▼
mailer.js: sendMail()
    ├── getSmtpSettings() → SMTP config (DB + env fallback)
    ├── Nodemailer createTransport()
    ├── transport.sendMail()
```

---

## Components

### 1. SMTP Settings (`SmtpSetting` model)

Stored in the database as a singleton record (always `id = "singleton"`).

**Fields:**
| Field | Type | Default | Environment Fallback |
|-------|------|---------|---------------------|
| host | String? | null | `SMTP_HOST` |
| port | Int | 587 | `SMTP_PORT` |
| secure | Boolean | false | `SMTP_SECURE` |
| user | String? | null | `SMTP_USER` |
| pass | String? | null | `SMTP_PASS` |
| fromEmail | String? | null | `SMTP_USER` (fallback) |
| fromName | String? | null | "SBC Application" (hardcoded) |

**Configuration check:**
```javascript
function smtpConfigured(settings) {
  return Boolean(settings.host && settings.user && settings.pass)
}
```

**Resolution order:** DB record → environment variables → empty string

### 2. Notification Rules (`NotificationRule` model)

Maps **actions** to **recipient emails**.

| Field | Type | Description |
|-------|------|-------------|
| id | String (UUID) | Primary key |
| action | String | Action identifier (e.g., "GUEST_ASSIGNED_TO_IMPACT_LEADER") |
| email | String | Recipient email address |
| active | Boolean | Whether the rule is active |
| createdAt/updatedAt | DateTime | Timestamps |

**Index:** `@@index([action])` — for efficient rule lookup by action

### 3. Available Actions

Currently only **one action** is defined:

| Value | Label | Trigger |
|-------|-------|---------|
| `GUEST_ASSIGNED_TO_IMPACT_LEADER` | "Guest assigned to Impact Leader" | When a guest is reassigned to an Impact_Leaders role user |

Defined in `server/lib/notifications.js`:
```javascript
const ACTIONS = {
  GUEST_ASSIGNED_TO_IMPACT_LEADER: "Guest assigned to Impact Leader",
}
```

---

## Notification Dispatch

### `notify()` Function
**Location:** `server/lib/notifications.js`

```javascript
async function notify(action, payload) {
  // 1. Find active rules for this action
  const rules = await prisma.notificationRule.findMany({
    where: { action, active: true }
  })

  // 2. Fallback: if no rules but payload.to exists, create ad-hoc rule
  if (!rules.length && payload.to) {
    rules.push({ email: payload.to })
  }

  // 3. Send email to all recipients (parallel)
  await Promise.all(
    rules.map((rule) =>
      sendMail({
        to: rule.email,
        subject: payload.subject,
        text: payload.text,
        html: payload.html,
      }).catch((err) => {
        console.error(`[mail] ${action} failed for ${rule.email}:`, err.message)
      })
    )
  )
}
```

### Guest Reassignment Trigger

**Location:** `server/controllers/guest.controller.js:reassign()`

```javascript
if (g.followOfficer?.role === "Impact_Leaders") {
  await notify("GUEST_ASSIGNED_TO_IMPACT_LEADER", {
    to: g.followOfficer.email,
    subject: `Guest assigned: ${g.guestName}`,
    text: `Hello ${g.followOfficer.fullName},\n\n${g.guestName} has been assigned to you for follow up.\nPhone: ${g.phone || "-"}\nAddress: ${g.address || "-"}\n\nSBC Application`,
    html: `<p>Hello ${g.followOfficer.fullName},</p><p><strong>${g.guestName}</strong> has been assigned to you for follow up.</p><p>Phone: ${g.phone || "-"}<br/>Address: ${g.address || "-"}</p><p>SBC Application</p>`,
  })
}
```

---

## Mailer Implementation

**Location:** `server/lib/mailer.js`

### Nodemailer Transport

```javascript
function createTransport(settings) {
  if (!smtpConfigured(settings)) return null
  return nodemailer.createTransport({
    host: settings.host,
    port: Number(settings.port || 587),
    secure: Boolean(settings.secure),
    auth: {
      user: settings.user,
      pass: settings.pass,
    },
  })
}
```

### sendMail() Function

```javascript
async function sendMail({ to, subject, text, html }) {
  const settings = await getSmtpSettings()
  const transport = createTransport(settings)

  if (!transport) {
    console.warn(`[mail] SMTP not configured; skipped email to ${to}: ${subject}`)
    return { skipped: true }
  }

  return transport.sendMail({
    from: settings.from ||
      `${settings.fromName} <${settings.fromEmail || settings.user}>`,
    to,
    subject,
    text,
    html,
  })
}
```

### SMTP Settings Resolution

```javascript
async function getSmtpSettings() {
  const saved = await prisma.smtpSetting.findUnique({
    where: { id: "singleton" }
  }).catch(() => null)

  return {
    host: saved?.host || process.env.SMTP_HOST || "",
    port: saved?.port || Number(process.env.SMTP_PORT || 587),
    secure: saved?.secure ?? String(process.env.SMTP_SECURE).toLowerCase() === "true",
    user: saved?.user || process.env.SMTP_USER || "",
    pass: saved?.pass || process.env.SMTP_PASS || "",
    fromEmail: saved?.fromEmail || process.env.SMTP_USER || "",
    fromName: saved?.fromName || "SBC Application",
    from: process.env.SMTP_FROM || "",
  }
}
```

---

## API Endpoints (Admin Only)

All notification endpoints require `Authentication` + `Administrator` role.

### GET /api/notifications/smtp
Returns current SMTP settings (password masked as `********`).

### PUT /api/notifications/smtp
Updates SMTP settings (upsert by id "singleton").
- Password is only updated if value is not `"********"` (masked placeholder)
- Falls back to environment variables for unset fields

### GET /api/notifications/actions
Returns available notification actions:
```json
[
  { "value": "GUEST_ASSIGNED_TO_IMPACT_LEADER", "label": "Guest assigned to Impact Leader" }
]
```

### GET /api/notifications/rules
Lists all notification rules ordered by creation date descending.

### POST /api/notifications/rules
Creates a new notification rule.
- Validates: action must exist in ACTIONS, email must contain "@"

### PUT /api/notifications/rules/:id
Updates a notification rule (partial update).

### DELETE /api/notifications/rules/:id
Deletes a notification rule.

### POST /api/notifications/test
Sends a test email to verify SMTP configuration.
```json
{
  "ok": true,
  "configured": true
}
```

---

## Environment Variables (SMTP)

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| SMTP_HOST | No | "" | SMTP server hostname |
| SMTP_PORT | No | 587 | SMTP server port |
| SMTP_SECURE | No | false | Use TLS/SSL |
| SMTP_USER | No | "" | SMTP authentication username |
| SMTP_PASS | No | "" | SMTP authentication password |
| SMTP_FROM | No | "" | From address (overrides fromName/fromEmail) |

---

## Sonner Toast Notifications

In addition to email, the frontend uses **Sonner** for in-app toast notifications:

```typescript
import { toast } from "sonner"

toast.success("Guest created successfully")
toast.error(err.message || "Failed to save")
```

**Toaster configuration** (in `__root.tsx`):
```tsx
<Toaster richColors position="top-right" />
```

Toast events occur for:
- Login success/failure
- Guest CRUD operations
- User CRUD operations
- File upload success/failure
- Guest reassignment
- Password change/reset
- Profile update
- Download completion/failure
- Form validation errors
