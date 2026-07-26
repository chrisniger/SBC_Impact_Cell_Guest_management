# 12 — Use Cases

---

## Use Case 1: Administrator Imports CSV of Guests

**Actor:** Administrator

**Preconditions:** User is logged in with Administrator role, has a CSV file ready

**Main Flow:**
1. Administrator navigates to `/import`
2. System displays 3 template options (Follow UP Officer, Follow_UP, Impact Cell)
3. Administrator downloads the appropriate template
4. Administrator populates the CSV with guest data
5. Administrator clicks the upload area and selects the CSV file
6. System parses the CSV, maps column aliases, detects duplicates by phone
7. System creates guests, skipping duplicates
8. System displays: "Imported {N} guests, skipped {M} duplicates"
9. Administrator clicks "View guests" to verify

**Alternative Flows:**
- **Invalid CSV format:** System shows error message
- **All rows are duplicates:** System reports 0 created, N skipped

---

## Use Case 2: Follow UP Officer Views Assigned Guests and Updates Contact Status

**Actor:** Follow UP Officer

**Preconditions:** User is logged in with Follow UP Officer role, has assigned guests

**Main Flow:**
1. User navigates to `/guests`
2. System shows only guests assigned to this officer
3. Guests are sorted by priority (NOT CONTACTED first)
4. User clicks "Edit" on a guest
5. Edit Guest dialog opens in edit mode
6. User updates Contacted Status from "No" to "Yes"
7. User optionally adds comments
8. User clicks "Save changes"
9. System updates the guest and shows success toast

**Alternative Flows:**
- **Viewing (read-only):** If user role is Follow_UP_View_Only, "View" button shown instead of "Edit"
- **Setting AvailableForVisit:** User sets Contacted Status to "Available for Visit", system reveals visitation fields, user must set Visitation Status

---

## Use Case 3: Follow_UP Admin Reassigns Guest to Follow_UP Officer

**Actor:** Follow_UP Admin

**Preconditions:** User is logged in with Follow_UP Admin role

**Main Flow:**
1. User navigates to `/guests`
2. System shows full guest list (all guests)
3. User clicks the reassign icon (two arrows icon) on a guest
4. Reassign dialog opens showing officer dropdown
5. Dropdown lists only officers with "Follow_UP" role
6. User selects a Follow_UP officer
7. User clicks "Save"
8. System reassigns the guest and shows success toast

**Business Rules:**
- Follow_UP Admin can only reassign to Follow_UP role officers
- Follow_UP Admin cannot edit guest fields

---

## Use Case 4: Impact Leader Submits Weekly Report

**Actor:** Impact_Leaders

**Preconditions:** User is logged in with Impact_Leaders role, assigned to an impact cell

**Main Flow:**
1. User navigates to Dashboard (automatically shows Impact Leader dashboard)
2. User selects "Submit Report" tab (`section=report`)
3. System displays the Submit Report form (13 fields)
4. User fills in: Fellowship Date, Venue, Adult count, Children count, Offerings, etc.
5. User clicks Submit
6. System validates: Fellowship Date is required, Impact Cell is required
7. System checks for duplicate: same cell + same fellowship date
8. If duplicate exists: returns error "This impact cell has already submitted a report for that fellowship date"
9. If no duplicate: submission created, KPIs updated

---

## Use Case 5: Guest Registers via Public Join Form

**Actor:** Unauthenticated visitor / potential church member

**Preconditions:** None (no authentication required)

**Main Flow:**
1. Visitor navigates to `/join-impact-cell`
2. System displays the Join Impact Cell form
3. Visitor selects Event, enters Name, Phone, Gender
4. Visitor selects Nearest Impact Center from dropdown
5. Visitor clicks "Submit"
6. System validates all required fields
7. System creates a guest record:
   - contactedStatus: "No"
   - followUpStatus: "NOT CONTACTED"
   - source: "PUBLIC_IMPACT_JOIN"
   - Auto-assigned to the first active Impact_Leaders leader of the selected cell
8. System shows success message: "Submitted successfully. An Impact Cell leader will contact you."

---

## Use Case 6: Administrator Creates New User with Multiple Roles

**Actor:** Administrator

**Preconditions:** User is logged in with Administrator role

**Main Flow:**
1. Administrator navigates to `/users`
2. Clicks "Add User" button
3. System displays Add User form: Full Name, Email, Phone, Username, Password, Access Levels
4. Administrator fills in required fields
5. Administrator checks multiple role checkboxes (e.g., Follow UP Officer and Impact_Leaders)
6. When Impact_Leaders is checked, Impact Cell dropdown appears
7. Administrator selects an Impact Cell
8. Administrator clicks "Save"
9. System creates user with multiple roles, first checked role becomes primary
10. Success toast: "User created"

---

## Use Case 7: Visitor Schedules a Home Visit

**Actor:** Follow UP Officer (or any role with guest edit access)

**Preconditions:** Guest has Contacted Status set to "Available for Visit"

**Main Flow:**
1. User navigates to `/visit-schedule`
2. System shows all guests with Contacted Status = "Available for Visit"
3. User sees cards with guest info: name, phone, impact cell, address
4. User clicks "Schedule visit" on a guest
5. Schedule Visit dialog opens
6. User selects days available (checkboxes), Visitation Status, adds feedback notes
7. User clicks "Save schedule"
8. System updates guest with visitation data
9. Success toast: "Visit schedule updated"

---

## Use Case 8: Impact Cell Admin Reviews Weekly Reports

**Actor:** Impact_Cell_Admin

**Preconditions:** User is logged in with Impact_Cell_Admin role

**Main Flow:**
1. User navigates to Dashboard
2. System shows Impact Cell Admin dashboard with 4 KPIs and 3 stats cards
3. User selects "Weekly Reports" tab
4. System shows table of submitted reports: Fellowship Date, Impact Cell, Adults, Children, Offerings
5. User clicks "View" on a report
6. System shows detail panel with all report data fields
7. User navigates to "Downloads" section in sidebar
8. User clicks "Reports" to download CSV of all reports
9. CSV file downloads with all submission data

**Alternative Flows:**
- **Impact_Cell_Report role:** Same view but all data is read-only (no submit/edit capabilities)
