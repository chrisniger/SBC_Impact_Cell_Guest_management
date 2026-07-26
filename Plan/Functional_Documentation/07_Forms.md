# 07 — Forms

## 1. Login Form

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Username or Email | Text input | ✓ | Accepts username or email; used as `username` field in API call |
| Password | Password input (with show/hide toggle) | ✓ | Min 6 chars |
| Remember me | Checkbox | — | Default: checked |

**Actions:** Sign in button, "Forgot password?" link

---

## 2. Add/Edit Guest Dialog

A comprehensive form with 3 sections. For the **Add** dialog, all fields are editable. For the **Edit** dialog, fields may be read-only depending on the user role.

### Section 1 — Basic Information

| Field | Type | Options | Read-only for | Notes |
|-------|------|---------|---------------|-------|
| Date | Text input (dd-mm-yyyy) | — | Follow_UP | Default: today |
| Event | Select | COMBINED SERVICE, CHURCH 1, CHURCH 2, OTHER | Follow_UP | If OTHER, eventOther field appears |
| Event Other | Text input | — | Follow_UP | Shown only when event = OTHER |
| Follow Officer | Select | List of active assignable officers | Follow_UP | |
| Guest Name | Text input | — | Follow_UP | **Required** |
| Gender | Select | Male, Female | Follow_UP | |
| Marital Status | Select | Single, Married, Divorced, Widowed | Follow_UP | |
| Phone | Text input | — | Follow_UP | |
| Age | Number input | — | Follow_UP | |
| Nearest Impact Cell | Select (edit) / Text input (add) | List of impact cells | Follow_UP | In add: free text; in edit: dropdown |
| Impact Status | Select | Contacted, Not Contacted, Not Reachable | — | **Only visible to Impact_Leaders** |
| Address | Text input | — | Follow_UP | |
| Contacted Status | Select | No, Yes, Available for Visit, Not Available for Visit, Not Reachable, Wrong Number, Others | — | Default: No |
| Join When | Select | First Timer (Last 2 Weeks), New Members (Last 6 Months), Old Members | Follow_UP | |

### Section 2 — Visitation Details (conditional)

**Visible only when Contacted Status = "Available for Visit"**

| Field | Type | Options | Required |
|-------|------|---------|----------|
| Days Available | Checkbox group | Sunday, Monday, Tuesday, Wednesday, Thursday, Friday, Saturday | — |
| Visitation Status | Select | Visited, Pending | ✓ |
| Feedback | Textarea | — | — |
| Visited | Select | No, Yes | — |
| Visited At | Select | Home, Office | — |
| Indicated to Join SBC | Select | Yes, No, Others | — |

### Section 3 — Follow Up Team (conditional)

**Visible for Admin and Follow_UP team roles**

| Field | Type | Options | Notes |
|-------|------|---------|-------|
| Follow Up Status | Select | NOT CONTACTED, CONTACTED, WRONG NUMBER, NOT REACHABLE | Required for Follow_UP role |
| Contact Sections | Dynamic array (up to 3) | — | Each section has: date, contact label, comments |

Each Contact Section contains:
- **Date** — Date input (YYYY-MM-DD format)
- **Contacts** — Select (1st Contact, 2nd Contact, 3rd Contact)
- **Follow up Comments** — Textarea

### Section 4 — Detail Comments

| Field | Type | Notes |
|-------|------|-------|
| Detail Comments | Textarea (4 rows) | Free text |

### Validation Rules
- Guest name is required
- If Contacted Status = "Available for Visit", Visitation Status is required
- For Follow_UP role, Follow Up Status is required

---

## 3. Add/Edit User Form (Admin only)

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Full Name | Text input | ✓ | |
| Email | Email input | ✓ | |
| Phone | Text input | — | |
| Username | Text input | ✓ | |
| Password | Text input (plain text) | ✓ (new) | Min 6 chars; for edit: "leave blank to keep current" |
| Access Levels | Checkbox grid (9 roles) | ✓ | User must have at least 1 role; first selected role becomes primary |
| Impact Cell | Select | — | **Visible only when Impact_Leaders role is checked** |
| Active | Switch | — | Default: on |

**Role checkboxes:** Administrator, Supervisor, Follow UP Officer, Follow_UP, Follow_UP Admin, Follow_UP_View_Only, Impact_Leaders, Impact_Cell_Admin, Impact_Cell_Report

---

## 4. Public Join Form (Unauthenticated)

| Field | Type | Required | Notes |
|-------|------|----------|-------|
| Event | Select | ✓ | COMBINED SERVICE, CHURCH 1, CHURCH 2, OTHER |
| Other Event | Text input | ✓ (if OTHER) | |
| Name | Text input | ✓ | |
| Phone | Text input (tel) | ✓ | |
| Gender | Select | ✓ | Male, Female |
| Nearest Impact Center | Select | ✓ | List of all impact cells |

**Success state:** "Submitted successfully. An Impact Cell leader will contact you."

---

## 5. Impact Leader Forms

### 5a. Members Data (41 fields)
Dynamically rendered from submission data. Typical fields include:
- FellowShip Date, Cell, Leader's Name, Couples, Family Members, Singles, Youths, Children, First Timer, New Member, Old Member, Offering, etc.

### 5b. Souls Registration (12 fields)
- Contact, Full Name, Phone Number, Gender, Occupation, Marital Status, Prayer Request, etc.

### 5c. Childbirth Notice (6 fields)
- Child Name, Parent / Guardian, Date of Birth, Gender, Impact Cell, Phone Number

### 5d. Submit Report (13 fields)
- Fellowship Date, Venue, ADULTS (count), CHILDREN (count), FIRST TIMERS, NEW MEMBERS, TOTAL OFFERINGS FOR THIS WEEK, TOTAL OFFERINGS (HQ), TOTAL OFFERINGS (CENTRE), etc.

### Duplicate Prevention
For weekly reports (`type: "report"`), the system prevents duplicate submissions:
- Checks for existing report with the same `impactCellId` and `fellowshipDateKey`
- Returns HTTP 409 if duplicate found

---

## 6. Schedule Visit Dialog

| Field | Type | Options | Notes |
|-------|------|---------|-------|
| Days Available | Checkbox group | Sun–Sat | |
| Visitation Status | Select | Pending, Visited | Default: Pending |
| Visited | Select | No, Yes | Default: No |
| Visit Notes / Feedback | Textarea (4 rows) | — | |

---

## 7. SMTP Settings Form

| Field | Type | Default |
|-------|------|---------|
| SMTP Host | Text input | — |
| SMTP Port | Number input | 587 |
| SMTP Username | Text input | — |
| SMTP Password | Password input | — |
| From Name | Text input | "SBC Application" |
| From Email | Email input | — |
| Use secure SMTP connection | Switch | false |

---

## 8. Notification Rule Form

| Field | Type | Notes |
|-------|------|-------|
| Action | Select | Currently only: "Guest assigned to Impact Leader" |
| Email | Email input | Recipient email |
| Active | Switch | Default: true |

---

## 9. Forgot Password Dialog

| Field | Type | Required |
|-------|------|----------|
| Email | Email input | ✓ |

---

## 10. Reset Password Form

| Field | Type | Validation |
|-------|------|------------|
| New password | Password input | Min 6 characters |
| Confirm password | Password input | Must match new password |

---

## 11. CSV Import

| Field | Type | Notes |
|-------|------|-------|
| File upload | File input (.csv) | Drag & drop area with click to browse |
