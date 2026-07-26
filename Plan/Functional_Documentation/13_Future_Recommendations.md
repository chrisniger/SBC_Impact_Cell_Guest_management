# 13 — Future Recommendations

The following observations are based on codebase inspection and identify areas for improvement when porting to Laravel 12:

---

## 1. Incomplete Features

### Reports Section (Sidebar)
The sidebar includes a "Reports" navigation item that is **disabled** with a coming-soon indicator:
```typescript
const FUTURE_NAV: NavItem[] = [
  { to: "#reports", label: "Reports", icon: BarChart3, roles: REPORT_ROLES },
];
```
This item uses `to: "#reports"` (a fragment only) and has `disabled` attribute with "coming soon" styling. The Reports page has not been implemented yet.

### Notification Bell Badge
The notification bell in the header displays a **hardcoded badge "3"**:
```jsx
<span className="absolute right-2 top-2 h-4 min-w-4 rounded-full bg-primary px-1 text-[10px] leading-4 text-white">3</span>
```
There is no backend integration for real-time notification counts.

---

## 2. Missing Infrastructure

### Pagination
The guest list loads all records at once with no pagination. The API endpoint `GET /api/guests` returns the complete result set. For large guest databases, this will cause:
- Slow initial page loads
- Excessive memory usage on the client
- Poor performance on the server

### Rate Limiting
There is no rate limiting middleware on any API endpoint. The system is vulnerable to:
- Brute force attacks on login
- API abuse from automated scripts
- Excessive CSV imports or data submissions

### Email Verification
User registration does not include email verification. Users can be created with any email address without confirmation.

### No WhatsApp/SMS Notifications
The notifications system only supports email. The codebase references only email-based notification rules. SMS and WhatsApp channels are not implemented but the extensible rule system could accommodate them.

---

## 3. Data Integrity

### Soft Deletes
The system uses **hard deletes** for guest records (`prisma.guest.delete()`). Only user accounts support deactivation (soft delete via `active: false`). Consider implementing soft deletes for:
- Guest records (to recover accidentally deleted data)
- Impact submissions
- Notification rules

### Migration Files
The Prisma migrations directory exists but the codebase inspection notes that migrations have **not yet been applied** to the database. The Laravel port should ensure all migrations are run before deployment.

---

## 4. Technical Debt

### Enum Consistency
Role names have inconsistent casing across the codebase:
- Server-side enum: `FollowUpOfficer`, `Follow_UP`, `Impact_Leaders`
- Frontend display: "Follow UP Officer", "Follow_UP", "Impact_Leaders"
- Mapping is handled by `ROLE_TO` and `ROLE_FROM` dictionaries

The Laravel implementation should standardize enum naming.

### Frontend Data Fetching
The current implementation uses raw `fetch()` calls wrapped in a custom `request()` function. Consider using a proper data fetching library or TanStack Query for:
- Caching
- Request deduplication
- Optimistic updates
- Error boundaries

### Form Validation
Form validation is primarily **client-side only** with toast error messages. Server-side validation exists only for critical fields. The Laravel port should implement comprehensive server-side validation with form request classes.

### Inline Password Display
The Add/Edit User form displays passwords as plain text (no password masking). This should use `type="password"` in production.

---

## 5. UI/UX Improvements

### Mobile Responsiveness
While the app uses Tailwind responsive classes, some components may not be fully optimized for mobile:
- Guest table has horizontal scroll on mobile
- Some dialogs may overflow on small screens

### Loading States
Some pages lack loading indicators (e.g., skeleton loaders) during data fetching. Only the guest detail page shows a "Loading guest…" text.

### Error Handling
API error messages are passed directly to toast notifications, which may expose internal details to users. Implement user-friendly error messages.

---

## 6. Security Recommendations

- Add CSRF protection
- Implement request rate limiting
- Add password complexity requirements (uppercase, numbers, special chars)
- Consider 2FA for admin accounts
- Sanitize all inputs server-side (currently some fields pass through without validation)
