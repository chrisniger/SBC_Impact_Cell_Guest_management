# 05 — Business Processes

## Assignment Rules

1. **Assigned-only roles** (Follow UP Officer, Follow_UP, Impact_Leaders) can only view and interact with guests where `followOfficerId` matches their own user ID.
2. On the `/guests` page, assigned-only roles see a **filtered view** showing only their own guests.
3. On the dashboard, all queries for assigned-only roles include a `where: { followOfficerId: req.user.sub }` filter.
4. Guests can be assigned to officers with roles in the `ASSIGNABLE_FOLLOW_UP_ROLES` group: Follow UP Officer, Follow_UP, Impact_Leaders.

## Reassignment Permissions

1. **Administrator**: Can reassign any guest to any active officer in the assignable roles.
2. **Follow_UP Admin**: Can only reassign guests to officers with the "Follow_UP" role. This is enforced server-side:
   ```javascript
   const allowedRoles = req.user.role === "Follow_UP_Admin" ? ["Follow_UP"] : ASSIGNABLE_FOLLOW_UP_ROLES;
   ```
3. **Reassign endpoint**: `POST /api/guests/:id/reassign` with `{ officerId: string }`
4. Reassignment validation checks:
   - Target officer must exist and be active
   - Target officer's role must be in the allowed list
5. If reassigning to an Impact_Leaders officer, a notification email is sent to that officer.

## Guest Ownership Rules

1. **Admin**: Can view, edit, and delete all guests.
2. **Assigned-only roles**: Can only edit guests where `guest.followOfficer === user.fullName`.
3. **Follow_UP role**: Has **read-only access to basic fields** (guestName, phone, address, gender, maritalStatus, age) but can edit follow-up specific fields (followUpStatus, followUpContacts).
4. **Follow_UP Admin**: Cannot edit guests at all; can only reassign.
5. **View-only roles**: Cannot edit any fields, see read-only form mode.

## Priority Sorting Logic

For assigned-only roles (Follow UP Officer, Follow_UP) and follow-up team roles (Follow_UP, Follow_UP Admin, Follow_UP_View_Only), the guest list is sorted by Follow Up Status priority:

```javascript
const priority = (status) => 
  (status === "NOT CONTACTED" || !status) ? 0 : 
  status === "CONTACTED" ? 1 : 2;
```

Priority order:
1. **NOT CONTACTED** or empty (priority 0) — shown first
2. **CONTACTED** (priority 1)
3. **WRONG NUMBER**, **NOT REACHABLE**, or any other status (priority 2)

This ensures officers focus on guests who have not yet been contacted.

## Duplicate Detection During CSV Import

1. Before importing, the system loads all existing phone numbers from the database.
2. For each CSV row, the phone number is extracted and checked against existing numbers.
3. If a duplicate phone is found, that row is **skipped** (not imported).
4. A count of skipped rows is returned in the response: `{ created: number, skipped: number, skippedDetails: array }`.
5. Phone matching is exact string comparison.

## Contact Section Limits

1. Follow Up Contact sections are limited to a **maximum of 3**.
2. The "+ Add Contact Section" button is disabled when `contacts.length >= 3`.
3. The first contact defaults to "1st Contact", the second to "2nd Contact", the third to "3rd Contact".
4. Server-side validation rejects arrays with more than 3 elements: `data.followUpContacts.length > 3`.

## Visitation Status Clearing

When a guest's Contacted Status changes away from "AvailableForVisit", the following fields are automatically cleared:
- `visitationStatus` → set to empty string
- `feedback` → set to empty string

This is implemented in the `handleStatusChange` function in both the Add and Edit guest dialogs.

## Follow Up Status Requirement for Follow_UP Role

For users with the Follow_UP role, the Follow Up Status field is **required** before saving. The form validates this:
```javascript
if (requireFollowUpStatus && !g.followUpStatus) {
  toast.error("Follow Up Team status is required");
  return;
}
```

## Impact Status Visibility

The `impactStatus` field on guests is **only visible** to users with the Impact_Leaders role:
```javascript
const showImpactStatus = user?.role === "Impact_Leaders";
```

## Bulk Delete

1. Only **Administrator** role can perform bulk delete.
2. Checkboxes appear on each row and a "select all" checkbox in the table header.
3. After selecting guests, a "Delete selected (N)" button appears.
4. Confirmation dialog: "Delete N selected guests?"
5. Bulk delete sends individual DELETE requests for each selected guest via `Promise.all`.

## Event Validation

Valid events are limited to: `COMBINED SERVICE`, `CHURCH 1`, `CHURCH 2`, `OTHER`.
- If event is not "OTHER", `eventOther` is set to null.
- If event is "OTHER", the `eventOther` field becomes visible and required.

## Template Variants for CSV Import

Three CSV templates exist:
1. **Follow UP Officer** — Basic fields (date, event, eventOther, followOfficer, guestName, gender, maritalStatus, phone, address, age, nearestImpactCell, contactedStatus, joinWhen)
2. **Follow_UP** — Adds Follow Up Status column
3. **Impact Cell** — Adds Impact Status column

The system maps common column aliases automatically (e.g., "Phone", "Phone Number", "Mobile" all map to phone).
