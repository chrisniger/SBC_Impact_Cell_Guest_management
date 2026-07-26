# 10 — Settings

## Settings Page (`/settings`)

Only accessible by the **Administrator** role.

### SMTP Settings

Saved to the `SmtpSetting` table (singleton record).

| Field | Type | Default | Notes |
|-------|------|---------|-------|
| SMTP Host | Text input | `SMTP_HOST` env | |
| SMTP Port | Number input | 587 | |
| SMTP Username | Text input | `SMTP_USER` env | |
| SMTP Password | Password input | `SMTP_PASS` env | Value "********" means keep existing; not sent to API |
| From Name | Text input | "SBC Application" | |
| From Email | Email input | falls back to SMTP_USER | |
| Use secure SMTP connection | Switch | false | Maps to Nodemailer `secure` option |

**Status indicator:** Badge shows "Configured" (green) or "Not configured" (amber)

**Test Email:** A separate card allows sending a test email to verify SMTP configuration.

---

## Theme Toggle

Available globally in two places:

1. **Login page:** Top-right corner sun/moon toggle button
2. **Authenticated layout:** Header bar toggle switch (shows "Dark mode" / "Light mode")

### Behavior
- Theme preference stored in `localStorage` under key `cgms.theme`
- Initial value: checks localStorage first, then system preference (`prefers-color-scheme`)
- CSS class `dark` toggled on `<html>` element
- `colorScheme` CSS property set on `<html>`

---

## Profile Page (`/profile`)

Accessible by all authenticated users.

### Account Details

| Field | Type | Notes |
|-------|------|-------|
| Full Name | Text input | Editable |
| Email | Email input | Editable |
| Phone | Text input | Editable |
| Role | Text input | Read-only; shows current active role |

### Change Password

| Field | Type | Validation |
|-------|------|------------|
| Current | Password input | Verified against bcrypt hash |
| New | Password input | Min 6 characters |

---

## Password Reset (Public)

### Forgot Password Dialog
- Opened from login page via "Forgot password?" link
- Single field: Email
- Always returns success (prevents email enumeration)
- Sends reset link if email exists in system

### Reset Password Page (`/reset-password?token=xxx`)
- Token extracted from URL query string
- Fields: New password, Confirm password
- Validation: passwords must match, min 6 characters
- Token validated server-side (must exist, not used, not expired)
- Token expires in 1 hour
