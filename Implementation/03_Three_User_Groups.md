# 03 — Three User Groups & Column-Level Access

This is the **single most important design decision** in v2. Every screen, every controller, every Prisma query must answer:
*"Which one of the 3 user groups am I serving, and which column of `Guest` / `ImpactCell` / `ImpactSubmission` can they see and/or edit?"*

The rules below are the **source of truth**. Implement them in:

- `server/lib/access.js` (server-side enforcement)
- `src/lib/access.ts` (UI affordances — display / hide / disable)

Both files **must stay in lock-step**. Never write a one-off check in a controller.

---

## The 3 Groups (canonical)

| # | Group | Sub-roles |
|---|-------|-----------|
| 1 | **Impact Cell** | `Impact_Leaders`, `Impact_Cell_Admin`, `Impact_Cell_Report` |
| 2 | **Follow Up Officer** | `FollowUpOfficer`, `Follow_UP_Admin` |
| 3 | **Follow Up Teams** | `Follow_UP`, `Follow_UP_View_Only` |

---

## Guest column-access matrix

Legend: `V` = view, `E` = edit/write, `—` = hidden. Where a column has a small caption (e.g. `(Owner)`), that group is the canonical owner of the field.

| Column | Impact Cell | Follow Up Officer | Follow Up Teams |
|---|---|---|---|
| `id` | V | V | V |
| `date` | V | V | V |
| `event` / `eventOther` | V | V | V |
| `source` | V | V | V |
| `guestName` | V | V | V |
| `gender`, `maritalStatus`, `age` | V | **E** | V |
| `phone`, `address` | V | **E** | V |
| `nearestImpactCell` | **E** (Owner) | V | V |
| `impactStatus` | **E** (Owner) | V | V |
| `contactedStatus` | V | **E** (Owner) | V |
| `joinWhen` | V | **E** (Owner) | V |
| `daysAvailable`, `comments` | V | **E** | V |
| `visited`, `visitedAt`, `indicatedToJoin`, `visitationStatus`, `feedback` | V | **E** | V |
| `followUpStatus` | V | V (RO) | **E** (Owner) |
| `followUpContacts[]` | V | V (RO) | **E** (Owner, max 3 sections) |
| `followOfficerId` (assignee) | see "Assignment rules" below | — | — |

> **Hidden from non-admins:** `deletedAt`, `updatedAt` raw timestamps (frontend shows human-friendly dates only).

---

## Guest **ASSIGNMENT** matrix (who can assign/reassign to whom)

| Action | Impact Cell group | Follow Up Officer group | Follow Up Teams group |
|---|---|---|---|
| Initial assignment at create | Admin only (owns full lifecycle) | Self-assign allowed while creating | Admin only |
| Reassign within the system | Admin only | Admin / `Follow_UP_Admin` | Admin only (`Follow_UP_Admin` cannot) |
| Reassign target restriction | Any `ASSIGNABLE_FOLLOW_UP_ROLES` | Only `Follow_UP` | n/a |
| Notification trigger on assign | Email accepted rule | none | none |

A guest assigned to a `FollowUpOfficer` group member goes into the Officer's "assigned" workflow. A guest assigned to an `Impact_Leaders` group member flips into the Impact Cell workflow (the cell leader sees them as well).

---

## Impact Cell / Impact Submission matrix

| Resource / Field | Impact Cell | Follow Up Officer | Follow Up Teams |
|---|---|---|---|
| `ImpactCell` (basic info: name, phone, address) | E (cell admin/leader) | V | V |
| `ImpactCell.parentCellId` (split/sub-cell) | E (cell admin) | — | — |
| `ImpactCell.isPrimary` | E (Admin only) | — | — |
| `ImpactSubmission` type=`member` | E (cell leader) | V | — |
| `ImpactSubmission` type=`soul` | E (cell leader) | V | — |
| `ImpactSubmission` type=`report` (weekly) | E (cell leader) | — | — |
| `ImpactSubmission` type=`childbirth` | E (cell leader) | V | — |
| `Soul Search` (read `soul` data) | V | V | — |
| `My Reports` (read own submissions) | V | — | — |

> The matrix is a default; `Administrator` always has full access and `Supervisor` is **read-only** everywhere.

---

## Server-side enforcement — `server/lib/access.js`

```js
// Pseudo-implementation. Always extend the matrix here, never inline in a controller.
const GROUP_GUEST_OWNER = {
  impactCell: ["impactStatus", "nearestImpactCell"],
  followUpOfficer: [
    "gender", "maritalStatus", "age",
    "phone", "address",
    "contactedStatus", "joinWhen",
    "daysAvailable", "comments",
    "visited", "visitedAt", "indicatedToJoin",
    "visitationStatus", "feedback",
  ],
  followUpTeam: ["followUpStatus", "followUpContacts"],
};

const GROUP = {
  impactCell: ["Impact_Leaders", "Impact_Cell_Admin", "Impact_Cell_Report"],
  followUpOfficer: ["FollowUpOfficer", "Follow_UP_Admin"],
  followUpTeam: ["Follow_UP", "Follow_UP_View_Only"],
};

function groupOf(role) {
  if (GROUP.impactCell.includes(role)) return "impactCell";
  if (GROUP.followUpOfficer.includes(role)) return "followUpOfficer";
  if (GROUP.followUpTeam.includes(role)) return "followUpTeam";
  return null;
}

function canViewField(role, field) { /* view = always true except admin-only hidden ones */ }
function canEditField(role, field) {
  if (!role) return false;
  if (role === "Administrator") return true;
  if (role === "Supervisor") return false;
  const g = groupOf(role);
  if (!g) return false;
  return GROUP_GUEST_OWNER[g].includes(field);
}

function stripDisallowed(role, body) {
  const data = { ...body };
  for (const key of Object.keys(data)) {
    if (!canEditField(role, key)) delete data[key];
  }
  return data;
}
```

The `sanitize()` function in `guest.controller.js` must call **`stripDisallowed(req.user.role, body)` FIRST**, then run the existing validations.

---

## Client-side UI affordances — `src/lib/access.ts`

Mirror the server rules and use them to:

- **Hide** fields a group cannot view (e.g., Follow Up Officer cannot see an "edit" button for `impactStatus`).
- **Disable** inputs the group cannot edit (e.g., Follow Up Team sees `contactedStatus` greyed-out).
- **Annotate** the form with subtle `<Tooltip>` explaining why ("managed by the Follow Up Team").

> ⚠️ The UI **must never** be the only line of defence. Even if the UI shows the field, the API must reject the write.

---

## Approval / workflow notes

- **Visitation fields** (`visitationStatus`, `feedback`, `visited`, `visitedAt`) belong to the Follow Up Officer group. When a guest's `contactedStatus` flips away from `Available for Visit`, those fields are cleared for everyone (a cross-cutting rule).
- **`followUpContacts`** is a JSON array, max 3 sections. The Follow Up Team group adds/edits; everyone else sees it read-only.
- **`impactStatus`** is **exclusive** to the Impact Cell group. Other groups see it as a read-only pill under the guest's name.
- **`joinWhen`** is captured once (typically at Officer edit or via Impact Cell read), then shown as a badge everywhere.

---
*Next: [04_Impact_Cell_Hierarchy.md](./04_Impact_Cell_Hierarchy.md).*
