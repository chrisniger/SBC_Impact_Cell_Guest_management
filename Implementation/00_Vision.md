# 00 — Vision & Users

## The product

**SBC Guest Management** (formerly "Follow UP Officer", re-branded "Follow UP & Impact Cell") is a church management web app for **Summit Baptist Church (SBC)**. It supports:

- Capturing guests from services and the public web join form.
- Routing guests to the right kind of follow-up.
- Tracking follow-up contact through to visit → impact cell → membership.
- Weekly impact cell reporting for the leadership.
- Beautiful, professional dashboards built around 3 distinct user groups.

## The 3 User Groups (the heart of v2)

All three groups interact with the **same `Guest` table** but with **different column-level access**. This is the most important design decision in this redesign.

### Group 1 — Impact Cell
Occupies the impact-cell side of the system. They manage cell membership, weekly reporting, soul registrations, and child namings.

**Sub-roles:** `Impact_Leaders` (active cell leaders), `Impact_Cell_Admin` (oversight), `Impact_Cell_Report` (read-only oversight).
**Owns:** `impactStatus`, `ImpactSubmission` (members, souls, childbirth, reports, weekly).
**Dashboard:** Leadership Board + Member submission form + Soul search + Reports.

### Group 2 — Follow Up Officer
Personal contact work. Officers have **their own** assigned guests. They update contact status, schedule visits, write comments, and maintain demographic/contact data.

**Sub-roles:** `FollowUpOfficer`, `Follow_UP_Admin` (reassigns only).
**Owns:** `contactedStatus`, `visitationStatus*`, `feedback*`, `daysAvailable`, `comments`, `visited*`, phone/address/age etc.
**Dashboard:** Assigned guests (sorted `NOT CONTACTED → CONTACTED → other`).

### Group 3 — Follow Up Teams
A team-level workflow. Members of the team track progress across the team, log detailed contact sections, and ensure no guest falls through.

**Sub-roles:** `Follow_UP`, `Follow_UP_View_Only`.
**Owns:** `followUpStatus`, `followUpContacts[]` (max 3 sections).
**Dashboard:** Team queue with inline status updates.

> See [03_Three_User_Groups.md](./03_Three_User_Groups.md) for the full column-access matrix.

## User personas

| Persona | Role(s) | Daily job-to-be-done |
|---------|---------|---------------------|
| **Pastor / Cell Admin** | `Impact_Cell_Admin` | "Show me which of our impact cells are thriving and which need help." |
| **Cell Leader "John"** | `Impact_Leaders` | "Submit this week's report before Sunday so my pastor can see the numbers." |
| **Follow-Up Officer "Mary"** | `FollowUpOfficer` | "I have 20 newly assigned guests; tell me who to contact first." |
| **Follow-Up Team Member "Tunde"** | `Follow_UP` | "Update status to CONTACTED for the four I called today; log my second contact notes." |
| **Administrator "Chris"** | `Administrator` | "Import 200 guests from this week's combined service CSV." |
| **Supervisor** | `Supervisor` | "Read-only audit. Show me everything; I just need to observe." |

## Success criteria (acceptance gates)

1. **Each user group sees Guests with the right columns.**
   - Impact Cell sees + edits `impactStatus`; cannot edit `followUpStatus`.
   - Follow Up Officer cannot see `followUpStatus` as editable; sees it read-only.
   - Follow Up Team cannot edit demographics, cannot edit `contactedStatus`.

2. **Impact Cells can split.**
   - An Admin can create a sub-cell under a parent cell.
   - The parent cell's dashboard shows the Leadership Board with rolled-up stats per sub-cell.

3. **The dashboards are beautiful.**
   - Linear/Vercel-inspired layout, off-white dark surfaces, soft red accent (#E53935).
   - Responsive, fast, dark + light modes match spec.

4. **Stable production deployment.**
   - Lives on `https://app.summitdata.one`. Building locally, deploying via Hostinger Passenger. No stale `dist/`.

## Out of scope (for this redesign)

- A mobile-native app (we keep the responsive web).
- SMS notifications (only email).
- A public-facing impact-cell map.
- Real-time WebSocket features.
- A new authentication provider (we stick with JWT).

## Open questions for the team

| Question | Owner | When to resolve |
|---------|-------|------------------|
| Will the Impact Cell split feature ever go deeper than 1 level (grandchildren)? | Pastor / Product | Before Phase 03 |
| Should Follow Up Teams also be allowed to import CSV? | Follow-Up Lead | Before Phase 02 |
| Are we committing to React 19 + Express + Prisma for the rebuild, or migrating to Laravel 12? | Admin | Before Phase 01 |
| Do we keep multi-role users (a user with 2 roles)? | Admin | Before Phase 02 |

---
*See [01_Architecture.md](./01_Architecture.md) for the technical foundation.*
