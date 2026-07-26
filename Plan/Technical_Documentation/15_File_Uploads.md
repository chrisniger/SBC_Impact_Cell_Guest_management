# File Uploads

## Overview

The system handles file uploads in two contexts:
1. **CSV Import** — Bulk guest creation via CSV file upload (Multer + csv-parse)
2. **CSV Export** — Client-side CSV generation and download (Blob API)
3. **Impact Leader Transfer Receipt** — FileReader data URL for receipt image upload

---

## CSV Import

### Route
```
POST /api/csv/upload
Auth: Required (Administrator only)
Content-Type: multipart/form-data
```

### Backend Implementation

**Middleware:** `server/routes/csv.routes.js`
```javascript
const upload = multer({
  storage: multer.memoryStorage(),
  limits: { fileSize: 5 * 1024 * 1024 }, // 5MB limit
})
router.post("/upload", requireAuth, requireRole("Administrator"), upload.single("file"), c.upload)
```

**Controller:** `server/controllers/csv.controller.js`

### File Processing Pipeline

```javascript
exports.upload = async (req, res) => {
  // 1. Check file exists
  if (!req.file) return res.status(400).json({ error: "CSV file required" })

  // 2. Parse CSV
  const records = parse(req.file.buffer.toString("utf8"), {
    columns: true,
    skip_empty_lines: true,
    trim: true,
  })

  // 3. Load officers for name→ID resolution
  const officers = await prisma.user.findMany({
    where: { role: { in: ASSIGNABLE_FOLLOW_UP_ROLES } },
  })
  const byName = new Map(officers.map((o) => [o.fullName.toLowerCase(), o.id]))

  // 4. Load existing phones for duplicate detection
  const existingPhones = new Set(
    (await prisma.guest.findMany({ select: { phone: true } }))
      .map((g) => g.phone).filter(Boolean),
  )

  // 5. Process each row
  for (const r of records) {
    const phone = pickField(r, "Phone", "phone", ...)
    if (phone && existingPhones.has(phone)) {
      skipped.push({ row: r, reason: "duplicate phone" })
      continue
    }
    // ... map fields, push to toCreate array
  }

  // 6. Bulk insert
  const created = await prisma.guest.createMany({ data: toCreate })
  res.json({ created: created.count, skipped: skipped.length, skippedDetails: skipped })
}
```

### CSV Column Mapping

The `pickField()` function tries multiple column names for each field:

```javascript
function pickField(row, ...keys) {
  for (const key of keys) {
    const value = row[key]
    if (value == null) continue
    const text = String(value).trim()
    if (text) return text
  }
  return ""
}
```

**Column Name Flexibility:**
| Target Field | Accepted CSV Headers |
|-------------|---------------------|
| guestName | Guest Name, guestName, Name, Full Name, fullName |
| phone | Phone, phone, Phone Number, phoneNumber, Mobile, mobile |
| event | Event, EVENT, event |
| eventOther | Event Other, eventOther, EventOther |
| address | Address, address, Residential Address, ResidentialAddress |
| gender | Gender, gender |
| maritalStatus | Marital Status, MaritalStatus, maritalStatus |
| age | Age, age, Age (years), ageYears |
| nearestImpactCell | Nearest Impact Cell, NearestImpactCell, Impact Cell, ImpactCell, impactCell |
| impactStatus | Impact Status, impactStatus, ImpactStatus |
| contactedStatus | Contacted Status, ContactedStatus, contactedStatus |
| joinWhen | Join When, JoinWhen, joinWhen |
| followUpStatus | Follow Up Status, FollowUpStatus, followUpStatus |
| followOfficerId | Follow Officer, Follow Up Officer, Follow_UP Officer, FollowUPOfficer, followOfficer, Assigned Officer |

### Status Normalization

**Contacted Status** (case-insensitive):
```
"no"                   → "No"
"yes"                  → "Yes"
"available for visit"  → "Available for Visit"
"not available for visit" → "Not Available for Visit"
"not reachable"        → "Not Reachable"
"wrong number"         → "Wrong Number"
"others"               → "Others"
```

**Follow Up Status** (case-insensitive):
```
"not contacted"  → "NOT CONTACTED"
"contacted"      → "CONTACTED"
"wrong number"   → "WRONG NUMBER"
"not reachable"  → "NOT REACHABLE"
```

**Join When** (case-insensitive):
```
"first timer" / "firsttimer" / "first-timer"  → "FirstTimer"
"new member" / "newmember" / "new-member"     → "NewMember"
"old member" / "oldmember" / "old-member"     → "OldMember"
```

### Smart Status Resolution

The CSV import has logic to handle cases where `Contacted Status` column contains follow-up workflow statuses:

```javascript
const resolvedFollowUpStatus = mapFollowUpStatus(followUpRaw)
  || (isFollowUpWorkflowStatus(contactedRaw)
    ? mapFollowUpStatus(contactedRaw)
    : null)
```

This means if a CSV has `Contacted Status` = "Not Reachable" but no separate `Follow Up Status` column, the system will correctly set `followUpStatus` to "NOT REACHABLE".

### Duplicate Detection

- Phone-based deduplication
- Checks against existing guest phone numbers in the database
- Duplicate entries are skipped and reported in `skippedDetails`
- Deduplication is per-upload-batch (phones from earlier rows in same upload are also tracked)

### Response Format

```json
{
  "created": 45,
  "skipped": 3,
  "skippedDetails": [
    { "row": { "Guest Name": "John", "Phone": "123" }, "reason": "duplicate phone" },
    ...
  ]
}
```

### Error Handling

```javascript
try {
  // ... parsing and import
} catch (error) {
  res.status(400).json({ error: error?.message || "CSV import failed" })
}
```
- Parse errors → 400 with error message
- File too large → Multer error (not caught here)
- No file → 400

---

## CSV Export

### Implementation (Client-Side)
CSV export is generated entirely in the browser using the Blob API.

**Location:** `src/components/AppLayout.tsx`

### Download Functions

#### `downloadCsv(filename, content)`
```typescript
const downloadCsv = (filename: string, content: string) => {
  const blob = new Blob([content], { type: "text/csv;charset=utf-8" })
  const url = URL.createObjectURL(blob)
  const anchor = document.createElement("a")
  anchor.href = url
  anchor.download = filename
  anchor.click()
  URL.revokeObjectURL(url)
}
```

#### `buildSubmissionCsv(rows)`
Generates CSV from ImpactSubmission array:
- Static columns: id, type, submittedAt, impactCell, submittedBy
- Dynamic columns: all keys from `data` JSON objects across all rows (sorted alphabetically)
- Each value is escaped via `csvCell()` (wraps in quotes, escapes inner quotes)

#### `buildGuestCsv()`
Generates CSV from Guest array:
- Static columns: id, date, event, eventOther, followOfficer, guestName, gender, maritalStatus, phone, address, age, nearestImpactCell, impactStatus, contactedStatus, joinWhen, followUpStatus
- Values escaped via `csvCell()`

### CSV Download Items

| Item | Type | Data Source | Roles |
|------|------|-------------|-------|
| Reports | report | apiClient.impact.submissions("report") | Administrator, Impact_Cell_Admin |
| Members Data CSV | member | apiClient.impact.submissions("member") | Administrator, Impact_Cell_Admin |
| Child Notice Data CSV | childbirth | apiClient.impact.submissions("childbirth") | Administrator, Impact_Cell_Admin |
| Guest Data CSV | guest | apiClient.guests.list() | Administrator, Follow_UP Admin |

### CSV Cell Escaping
```typescript
const csvCell = (value: unknown) => {
  const normalized = value === null || value === undefined
    ? ""
    : typeof value === "object"
      ? JSON.stringify(value)
      : String(value)
  return `"${normalized.replace(/"/g, '""')}"`
}
```

---

## Impact Leader Transfer Receipt

**Location:** Dashboard → Members Data section

Impact Leaders can upload a "transfer receipt" image when submitting member data:

```typescript
const handleFile = (e: ChangeEvent<HTMLInputElement>) => {
  const file = e.target.files?.[0]
  if (!file) return
  const reader = new FileReader()
  reader.onload = () => {
    setMemberData((prev) => ({ ...prev, transferReceipt: reader.result as string }))
  }
  reader.readAsDataURL(file)
}
```

The receipt is stored as a **data URL** (base64-encoded string) within the `ImpactSubmission.data` JSON object. No file is stored on the server — it is embedded directly in the JSON payload.
