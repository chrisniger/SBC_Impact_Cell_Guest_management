# Appendix — Glossary and References

## Glossary

| Term | Definition |
|------|------------|
| **SBC** | The church organization name (likely "Salem" or similar) |
| **Guest** | A person who attended a church service/event and is registered in the system for follow-up |
| **Follow-Up** | The process of contacting guests after an event to build relationships |
| **Impact Cell** | A small group/cell within the church organization, geographically distributed |
| **Impact Leader** | A leader assigned to an impact cell who manages members and submits data |
| **Officer** | A follow-up team member assigned to contact guests |
| **Contacted Status** | Tracks the outcome of contact attempts with a guest |
| **Follow Up Status** | Workflow status for the Follow_UP team (NOT CONTACTED, CONTACTED, etc.) |
| **Follow Up Contacts** | A log of up to 3 contact attempts with dates and notes |
| **Visitation** | A scheduled home visit for guests who indicated availability |
| **Join When** | Guest category indicating when they joined (FirstTimer, NewMember, OldMember) |
| **Public Join** | The unauthenticated form for visitors to register for an impact cell |
| **CSV Template** | Predefined column structure for bulk importing guest data |
| **Fellowship Date** | The date of an impact cell's weekly fellowship meeting |
| **Child Naming** | A church ceremony for naming newborn children of members |
| **Souls Registration** | Records of new converts/souls won through evangelism |
| **KPI** | Key Performance Indicator — dashboard metric cards |

## Schema Quick Reference

### Database Tables

| Table | Purpose |
|-------|---------|
| `User` | System users with roles, authentication, impact cell assignment |
| `Guest` | Guest records with contact status, assignment, visitation data |
| `ImpactCell` | Impact cell groups with location info |
| `ImpactSubmission` | Data submissions from impact leaders (members, souls, childbirth, reports) |
| `SmtpSetting` | Singleton SMTP email configuration |
| `NotificationRule` | Action-to-email mapping for notification triggers |
| `PasswordReset` | Password reset tokens with expiry |
| `AuditLog` | System activity log |

### Key Enums

| Enum | Values |
|------|--------|
| `Role` | Administrator, Supervisor, FollowUpOfficer, Follow_UP, Follow_UP_Admin, Follow_UP_View_Only, Impact_Leaders, Impact_Cell_Admin, Impact_Cell_Report |
| `ContactedStatus` | No, Yes, AvailableForVisit, NotAvailableForVisit, NotReachable, WrongNumber, Others |
| `JoinWhen` | FirstTimer, NewMember, OldMember |

## API Endpoint Reference

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| POST | `/api/auth/login` | No | Login |
| POST | `/api/auth/logout` | Yes | Logout |
| GET | `/api/auth/me` | Yes | Current user |
| PUT | `/api/auth/profile` | Yes | Update profile |
| POST | `/api/auth/change-password` | Yes | Change password |
| POST | `/api/auth/forgot-password` | No | Request reset |
| POST | `/api/auth/reset-password` | No | Execute reset |
| GET | `/api/users` | Yes | List users |
| POST | `/api/users` | Yes | Create user |
| PUT | `/api/users/:id` | Yes | Update user |
| DELETE | `/api/users/:id` | Yes | Deactivate user |
| GET | `/api/guests` | Yes | List guests |
| GET | `/api/guests/:id` | Yes | Get guest |
| POST | `/api/guests` | Yes | Create guest |
| PUT | `/api/guests/:id` | Yes | Update guest |
| DELETE | `/api/guests/:id` | Yes | Delete guest |
| POST | `/api/guests/:id/reassign` | Yes | Reassign guest |
| GET | `/api/reports/dashboard` | Yes | Dashboard KPIs |
| GET | `/api/reports/officer-performance` | Yes | Officer stats |
| GET | `/api/reports/audit` | Yes | Audit log |
| GET | `/api/impact/cells` | Yes | List impact cells |
| GET | `/api/impact/public/cells` | No | Public cell list |
| POST | `/api/impact/public/join` | No | Public join form |
| POST | `/api/impact/cells` | Yes | Create cell |
| PUT | `/api/impact/cells/:id` | Yes | Update cell |
| GET | `/api/impact/summary` | Yes | Impact summary |
| GET | `/api/impact/submissions` | Yes | List submissions |
| POST | `/api/impact/submissions` | Yes | Create submission |
| POST | `/api/csv/upload` | Yes | Upload CSV |
| GET | `/api/notifications/smtp` | Yes | Get SMTP settings |
| PUT | `/api/notifications/smtp` | Yes | Update SMTP |
| GET | `/api/notifications/actions` | Yes | List actions |
| GET | `/api/notifications/rules` | Yes | List rules |
| POST | `/api/notifications/rules` | Yes | Create rule |
| PUT | `/api/notifications/rules/:id` | Yes | Update rule |
| DELETE | `/api/notifications/rules/:id` | Yes | Delete rule |
| POST | `/api/notifications/test` | Yes | Test email |

## Technology Versions (Current Stack)

| Technology | Version (from package.json) |
|------------|---------------------------|
| React | ^19.0.0 |
| TanStack Router | ^1.114.0 |
| TanStack Query | ^5.67.0 |
| Express | ^4.21.0 |
| Prisma | ^6.4.0 |
| Vite | ^6.3.0 |
| Node.js | >=18 |
| Tailwind CSS | ^4.1.0 |
| Recharts | ^2.15.0 |
| Nodemailer | ^6.9.0 |
| bcryptjs | ^2.4.3 |
| jsonwebtoken | ^9.0.0 |
| zod | ^3.24.0 |
| csv-parse | ^5.6.0 |
