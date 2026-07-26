# 09 — Notifications

## Architecture

The notification system has two components:

1. **Backend:** Nodemailer-based email sending with configurable SMTP
2. **Frontend:** Sonner toast notifications for user feedback

---

## SMTP Configuration

SMTP settings are stored in the `SmtpSetting` table (singleton record with id="singleton") and can be configured via the Settings page (`/settings`).

### Configuration Fields

| Field | Env Variable | DB Field | Default |
|-------|-------------|----------|---------|
| SMTP Host | `SMTP_HOST` | `host` | — |
| SMTP Port | `SMTP_PORT` | `port` | 587 |
| Secure | `SMTP_SECURE` | `secure` | false |
| Username | `SMTP_USER` | `user` | — |
| Password | `SMTP_PASS` | `pass` | — |
| From Email | — | `fromEmail` | SMTP_USER fallback |
| From Name | — | `fromName` | "SBC Application" |

### Fallback Chain
1. Saved DB settings (from Settings page)
2. Environment variables
3. Empty/default values

### SMTP Status Indicator
- **Configured** (green badge): When `host`, `user`, and `pass` are all non-empty
- **Not configured** (amber badge): Otherwise

### Test Email
- Available on the Settings page
- Sends a test email to a specified address
- Returns `{ ok: true, configured: boolean }`

---

## Notification Rules

Notification rules map **actions** to **email recipients**.

### Actions

Currently only one action is defined:

| Action Key | Display Label |
|-----------|---------------|
| `GUEST_ASSIGNED_TO_IMPACT_LEADER` | "Guest assigned to Impact Leader" |

### Rule Management (Admin only)

| Operation | Endpoint |
|-----------|----------|
| List rules | `GET /api/notifications/rules` |
| Create rule | `POST /api/notifications/rules` |
| Update rule | `PUT /api/notifications/rules/:id` |
| Delete rule | `DELETE /api/notifications/rules/:id` |

### Rule Fields
- `action` — Must be a valid action key
- `email` — Recipient email (validated to contain "@")
- `active` — Boolean toggle to enable/disable (default: true)

### How Rules Are Evaluated

When a notification event occurs:
1. System looks up all **active** rules for the given action
2. If no active rules exist but a `to` email was provided in the payload, a fallback rule is created with that email
3. Email is sent to **all** matching rule recipients
4. Errors are logged but do not block execution (each send is wrapped in a `.catch()`)

---

## Email Sending

### Nodemailer Transport

```javascript
nodemailer.createTransport({
  host: settings.host,
  port: Number(settings.port || 587),
  secure: Boolean(settings.secure),
  auth: { user: settings.user, pass: settings.pass },
});
```

### When Is Email Triggered?

**Guest assigned to Impact Leader:** When a guest is reassigned to an officer with role `Impact_Leaders`, the `notify()` function is called automatically:

```javascript
await notify("GUEST_ASSIGNED_TO_IMPACT_LEADER", {
  to: officer.email,
  subject: `Guest assigned: ${guest.guestName}`,
  text: `Hello ${officer.fullName},\n\n${guest.guestName} has been assigned to you...`,
  html: `<p>Hello ${officer.fullName},</p><p><strong>${guest.guestName}</strong> has been assigned...</p>`,
});
```

### Email Fallback
If SMTP is not configured, emails are **skipped** and a warning is logged:
```
[mail] SMTP not configured; skipped email to {email}: {subject}
```

---

## Frontend Notifications

Sonner toast library is used for in-app notifications:

| Type | Usage | Example |
|------|-------|---------|
| Success | Operation confirmation | "Guest added", "Saved", "Imported 15 guests" |
| Error | Operation failure | "Failed to load data", "Invalid credentials" |
| Info | General messages | "Welcome, {name}" |

- Position: top-right
- Rich colors enabled (`richColors`)
- Auto-dismiss

### Notification Bell (Header)
- A bell icon in the top header bar
- Currently displays a **hardcoded badge "3"**
- No backend integration for real notification count

---

## API Endpoints

| Method | Path | Description | Auth |
|--------|------|-------------|------|
| GET | `/api/notifications/smtp` | Get SMTP settings | Required |
| PUT | `/api/notifications/smtp` | Update SMTP settings | Required |
| GET | `/api/notifications/actions` | List available actions | Required |
| GET | `/api/notifications/rules` | List notification rules | Required |
| POST | `/api/notifications/rules` | Create rule | Required |
| PUT | `/api/notifications/rules/:id` | Update rule | Required |
| DELETE | `/api/notifications/rules/:id` | Delete rule | Required |
| POST | `/api/notifications/test` | Send test email | Required |
