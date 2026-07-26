# Forms and Validation

## Overview

Validation is implemented at two levels:
1. **Frontend:** React Hook Form (with Zod resolvers) + manual validation in custom forms
2. **Backend:** `sanitize()` function in `guest.controller.js` with field-level validation

---

## Frontend Form Patterns

### React Hook Form
The project uses `react-hook-form` with `@hookform/resolvers` and `zod` for schema validation in some forms. Manual state-based forms are also used extensively.

### Form Types

#### Guest Form (Add/Edit)
**Location:** `src/routes/_authenticated/guests.tsx`

- Modal-based via Radix UI Dialog
- Manual state management (`useState` for each field)
- No React Hook Form — uses direct `useState` with `onChange` handlers

**Fields:**
```
date (text, auto-filled with today)
event (select: COMBINED SERVICE, CHURCH 1, CHURCH 2, OTHER)
eventOther (text, visible only when event=OTHER)
guestName (text)
gender (select: Male, Female)
maritalStatus (select: Single, Married, Divorced, Widowed)
phone (text)
address (text)
age (number)
nearestImpactCell (select or autocomplete from impact cells)
impactStatus (select: Contacted, Not Contacted, Not Reachable)
contactedStatus (select from 7 ContactedStatus values)
joinWhen (select: First Timer, New Members, Old Members)
daysAvailable (multi-select checkboxes: Sun-Sat)
comments (textarea)
visited (checkbox → Yes/No)
visitedAt (select: Home, Office — visible only when visited=Yes)
indicatedToJoin (select: Yes, No, Others)
visitationStatus (select: Visited, Pending)
feedback (textarea)
followUpContacts (array of sections, max 3)
followUpStatus (select: NOT CONTACTED, CONTACTED, WRONG NUMBER, NOT REACHABLE)
source (text)
```

**Validation pattern:**
```typescript
// Field change handler
const setAdd = (key: keyof Omit<Guest, "id">, value: any) => {
  setAddDraft((current) => {
    const next = { ...current, [key]: value }
    
    // Dependent field clearing
    if (key === "event" && value !== "OTHER") next.eventOther = ""
    if (key === "visited" && value !== "Yes") next.visitedAt = ""
    if (key === "contactedStatus" && value !== "Available for Visit") {
      next.visitationStatus = ""
      next.feedback = ""
    }
    
    return next
  })
}
```

**Validation on save:**
```typescript
if (!draft.guestName.trim()) { toast.error("Guest name is required"); return }
// No other client-side validations — backend sanitize() handles field validation
```

#### Impact Leader Forms (in Dashboard)
**Location:** `src/routes/_authenticated/dashboard.tsx`

- Four form sections: Members Data, Submit Report, Childbirth Notice, Souls Registration
- Custom `ImpactFormField` component with label + input/select/textarea
- Fields vary by submission type:
  - **Members:** Name, Phone, Gender, Marital Status, Nearest Impact Cell, etc.
  - **Report:** Fellowship Date, Attendance numbers, offerings
  - **Childbirth:** Parent name, Child name, Date of Birth, Phone
  - **Souls:** Name, Phone, Email, Centre, Gender, Date

All format date fields in the `data` JSON payload are normalized to YYYY-MM-DD via the `toDateKey()` function before submission.

#### Public Join Form
**Location:** `src/routes/join-impact-cell.tsx`

- Fully client-side state managed form
- Fields: event, eventOther, name, phone, gender, impactCellId
- All fields except `eventOther` are required
- Fetches cells from public API on mount

#### Profile Form
**Location:** `src/routes/_authenticated/profile.tsx`

- Fields: fullName, email, phone
- Manual state management

#### Password Forms
**Change Password:** currentPassword, newPassword  
**Reset Password:** password, confirm (client-side match check)

#### Notification Rule Form
- Action, email, active fields
- Email format check (includes `@` symbol)

#### SMTP Settings Form
- Host, port, secure, user, pass, fromEmail, fromName
- Password field shows `********` if already configured

---

## Backend Validation (Sanitize)

### Location: `server/controllers/guest.controller.js:sanitize()`

The `sanitize()` function processes all guest create/update requests:

```javascript
function sanitize(body) {
  const data = { ...body }

  // 1. Officer relation mapping
  if (Object.prototype.hasOwnProperty.call(data, "followOfficerId")) {
    if (data.followOfficerId) {
      data.followOfficer = { connect: { id: data.followOfficerId } }
    }
    delete data.followOfficerId
  }

  // 2. Visitation status validation
  if (data.visitationStatus !== undefined && data.visitationStatus !== null) {
    if (!["Visited", "Pending"].includes(data.visitationStatus)) {
      throw Object.assign(new Error("visitationStatus must be 'Visited' or 'Pending'"), { status: 400 })
    }
  }

  // 3. Follow-up status validation
  if (data.followUpStatus !== undefined && data.followUpStatus !== null && data.followUpStatus !== "") {
    if (!["NOT CONTACTED", "CONTACTED", "WRONG NUMBER", "NOT REACHABLE"].includes(data.followUpStatus)) {
      throw Object.assign(new Error("Invalid Follow Up Team status"), { status: 400 })
    }
  }

  // 4. Impact status validation
  if (data.impactStatus !== undefined && data.impactStatus !== null && data.impactStatus !== "") {
    if (!["Contacted", "Not Contacted", "Not Reachable"].includes(data.impactStatus)) {
      throw Object.assign(new Error("Invalid Impact Status"), { status: 400 })
    }
  }

  // 5. Follow-up contacts validation (max 3)
  if (data.followUpContacts !== undefined && data.followUpContacts !== null) {
    if (!Array.isArray(data.followUpContacts) || data.followUpContacts.length > 3) {
      throw Object.assign(new Error("Follow-up contacts must contain up to 3 sections"), { status: 400 })
    }
    data.followUpContacts = data.followUpContacts.map((item, index) => ({
      comments: String(item?.comments ?? ""),
      date: item?.date || new Date().toISOString().slice(0, 10),
      contact: ["1st Contact", "2nd Contact", "3rd Contact"].includes(item?.contact)
        ? item.contact
        : ["1st Contact", "2nd Contact", "3rd Contact"][index] || "1st Contact",
    }))
  }

  // 6. Event validation
  if (data.event !== undefined && data.event !== null && data.event !== "") {
    if (!["COMBINED SERVICE", "CHURCH 1", "CHURCH 2", "OTHER"].includes(data.event)) {
      throw Object.assign(new Error("Invalid event"), { status: 400 })
    }
    if (data.event !== "OTHER") data.eventOther = null
  }

  return data
}
```

---

## Valid Values Reference

### Guest Fields

| Field | Valid Values | Notes |
|-------|-------------|-------|
| event | COMBINED SERVICE, CHURCH 1, CHURCH 2, OTHER | |
| eventOther | Free text | Cleared if event != OTHER |
| gender | Male, Female | |
| maritalStatus | Single, Married, Divorced, Widowed | |
| contactedStatus | No, Yes, AvailableForVisit, NotAvailableForVisit, NotReachable, WrongNumber, Others | |
| visited | true, false | |
| visitedAt | Home, Office | |
| indicatedToJoin | Yes, No, Others | |
| visitationStatus | Visited, Pending | Only valid when contactedStatus = AvailableForVisit |
| followUpStatus | NOT CONTACTED, CONTACTED, WRONG NUMBER, NOT REACHABLE | |
| impactStatus | Contacted, Not Contacted, Not Reachable | |
| joinWhen | FirstTimer, NewMember, OldMember | |
| followUpContacts | Array of sections (max 3) | Each section: { comments, date, contact } |
| daysAvailable | Comma-separated day names | e.g., "Mon,Tue,Wed" |

### User Fields

| Field | Valid Values | Notes |
|-------|-------------|-------|
| role | Any of 9 Role enum values | |
| roles | Array of Role values | Sanitized via `sanitizeRoles()` |
| active | true, false | |

### Impact Submission Types

| Type | Description | Required Fields |
|------|-------------|----------------|
| member | Member data | Flexible JSON |
| report | Weekly report | impactCellId, fellowshipDateKey |
| childbirth | Childbirth notice | Flexible JSON |
| soul | Soul registration | Flexible JSON |

---

## Contact Section Limits

The `followUpContacts` field supports up to **3 contact sections** with labels:
1. `1st Contact`
2. `2nd Contact`
3. `3rd Contact`

Each section:
```json
{
  "comments": "Spoke about upcoming events",
  "date": "2024-03-15",
  "contact": "1st Contact"
}
```

---

## Dependent Field Clearing

When certain fields change, dependent fields are automatically cleared:

| Parent Field | Value | Cleared Fields |
|-------------|-------|----------------|
| event | != "OTHER" | eventOther |
| visited | != "Yes" | visitedAt |
| contactedStatus | != "Available for Visit" | visitationStatus, feedback |

This is implemented in both:
1. **Frontend:** In the `setAdd`/`setEdit` change handlers
2. **Backend:** In `serializeGuest()` — only sends visitationStatus/feedback when contactedStatus is "Available for Visit"

---

## Toast Error Display

All validation failures are displayed via `toast.error()`:
```typescript
toast.error("Guest name is required")
toast.error(err.message || "Failed to load data")
```

Backend validation errors flow through:
```
Backend throws { message, status } → Controller catches → res.status(err.status).json({ error: err.message })
→ Frontend fetch throws Error(err.message) → Component catch → toast.error(err.message)
```
