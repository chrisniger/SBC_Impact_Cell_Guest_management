# Appendix

## Glossary

| Term | Definition |
|------|------------|
| **SBC** | Summit Bible Church (the application's organization) |
| **Guest** | A church visitor/attendee registered in the system for follow-up |
| **Impact Cell** | A small group/cell within the church community (70 locations) |
| **Impact Leader** | A user role responsible for leading an Impact Cell and submitting reports |
| **Follow-Up Officer** | A user role responsible for contacting and following up with guests |
| **Follow_UP** | A follow-up team role (distinct from Follow-Up Officer) |
| **Contacted Status** | Tracks the result of contacting a guest (7 possible values) |
| **Join When** | Categorizes when a guest joined (First Timer, New Member, Old Member) |
| **Visitation** | An in-person visit to a guest's home or office |
| **Follow-Up Contact** | A record of attempted contact (up to 3 per guest) |
| **SSR** | Server-Side Rendering — rendering React pages on the server |
| **TanStack Start** | SSR framework from TanStack (formerly React Start) |
| **Radix UI** | Headless, accessible React UI primitives |
| **shadcn/ui** | A collection of styled components built on Radix UI |
| **Sonner** | Toast notification library |
| **Singleton Pattern** | Database design pattern where only one row exists in a table (used by SmtpSetting) |

## Role Descriptions

| Role | Permissions Summary |
|------|-------------------|
| **Administrator** | Full system access — user management, guest CRUD, CSV import, settings, notifications, impact cell management, all reports |
| **Supervisor** | View all guests and reports — no edit/delete permissions |
| **FollowUpOfficer** | Create/edit own assigned guests, view dashboard with own data |
| **Follow_UP** | Create/edit own assigned guests (same as FollowUpOfficer, different team) |
| **Follow_UP_Admin** | View all guests, reassign guests to Follow_UP role users, access reports |
| **Follow_UP_View_Only** | View all guests and reports — read-only |
| **Impact_Leaders** | View/update own assigned guests, submit member/report/childbirth/soul data, access impact summary |
| **Impact_Cell_Admin** | View all guests, manage impact cells, view submissions, download CSV exports |
| **Impact_Cell_Report** | View all guests, view submissions (read-only) |

## Enum Display Value Mappings

### ContactedStatus
| API Value | Display Value |
|-----------|--------------|
| No | No |
| Yes | Yes |
| AvailableForVisit | Available for Visit |
| NotAvailableForVisit | Not Available for Visit |
| NotReachable | Not Reachable |
| WrongNumber | Wrong Number |
| Others | Others |

### JoinWhen
| API Value | Display Value |
|-----------|--------------|
| FirstTimer | First Timer (Last 2 Weeks) |
| NewMember | New Members (Last 6 Months) |
| OldMember | Old Members |

### Role
| API Value | Display Value |
|-----------|--------------|
| Administrator | Administrator |
| Supervisor | Supervisor |
| FollowUpOfficer | Follow UP Officer |
| Follow_UP | Follow_UP |
| Follow_UP_Admin | Follow_UP Admin |
| Follow_UP_View_Only | Follow_UP_View_Only |
| Impact_Leaders | Impact_Leaders |
| Impact_Cell_Admin | Impact_Cell_Admin |
| Impact_Cell_Report | Impact_Cell_Report |

## Impact Cell Names (70 Hardcoded)

ACO/JEDO, ASOKORO, EFAB WARU, APO MECHANIC, APO LEGISLATIVE QTRS, APO RESETTLEMENT, APO RESETTLEMENT B, APO-DUTSE, BAZE UNIVERSITY ABUJA, BWARI, DAKWO 2 SANTOS ESTATE, DAWAKI, DURUMI 1, DURUMI CELL A: SUCCESS, DURUMI CELL B: JOYFUL, DURUMI CELL C: PEACE, DURUMI CELL D: TESTIMONY, DURUMI 3, GADUWA ESTATE, GALADIMAWA - CELL A, GALADIMAWA VILLAGE, GAMES VILLAGE 1, GARKI AREA 11, GUZAPE, GWAGWALADA, GWAGWALADA CENTER, GWAGWALADA - CHILDREN'S CHURCH, GWAGWALADA - BY KEYSTONE, GWAGWALADA - KUTUNKU, GWARIMPA, HILLVIEW ESTATE, IDU, JABI, JAHI, JIKWOYI, KABAYI MARARABA 2, KABUSA CENTRE, KABUSA GARDENS, KABUSA1, KADO, KARU, KEFFI, KUBWA, KUBWA CENTER, KUJE, LIFE CAMP 2, LOKOGOMA, LOKOGOMA 2 MINFA 1, LOKOGOMA 4(DONGONGADA), LUGBE 3 TRADE MOORE, LUGBE ACROSS, LUGBE- CELL B, PYAKASA, RUGA LUGBE, MABUSHI, MANUAL ASSIGNMENT, MASAKA 1, MPAPE, NYANYA FHA, OLYMPIA IMPACT CELL, OUTER GAMES VILLAGE, PORT-HARCOURT, PRINCE & PRINCESS 1, RICHYGOLD HOMES/CEDARCREST, SUNCITY/ GALADIMAWA, WUMBA, WUSE, WUYE 1

## References

### Documentation
- Laravel 12: https://laravel.com/docs/12.x
- Laravel Sanctum: https://laravel.com/docs/12.x/sanctum
- Spatie Laravel Permission: https://spatie.be/docs/laravel-permission
- React 19: https://react.dev
- TanStack Router: https://tanstack.com/router
- TanStack React Query: https://tanstack.com/query
- TanStack Start: https://tanstack.com/start
- Tailwind CSS v4: https://tailwindcss.com
- Radix UI: https://www.radix-ui.com
- shadcn/ui: https://ui.shadcn.com
- Recharts: https://recharts.org
- Sonner: https://sonner.emilkowal ski
- Prisma: https://www.prisma.io
- Express.js: https://expressjs.com

### Key Source Files Referenced

| File | Purpose |
|------|---------|
| `server/server.js` | Express entry point, middleware stack, SSR fallback |
| `prisma/schema.prisma` | Database schema (8 models, 3 enums) |
| `server/middleware/auth.js` | requireAuth + requireRole middleware |
| `server/lib/roles.js` | Backend role constants and helpers |
| `server/lib/mailer.js` | Nodemailer SMTP transport |
| `server/lib/notifications.js` | Notification action dispatch |
| `server/controllers/auth.controller.js` | JWT auth, login, password reset |
| `server/controllers/guest.controller.js` | Guest CRUD, sanitize(), reassign |
| `server/controllers/impact.controller.js` | Cells, submissions, public join |
| `server/controllers/csv.controller.js` | CSV import pipeline |
| `src/lib/api.ts` | Frontend API client + normalizers |
| `src/lib/auth-context.tsx` | AuthProvider + ThemeProvider |
| `src/lib/types.ts` | TypeScript interfaces |
| `src/components/AppLayout.tsx` | Sidebar, header, navigation |
| `src/routes/_authenticated.tsx` | Auth gate |
| `src/routes/_authenticated/dashboard.tsx` | All dashboards (2226 lines) |
| `src/routes/_authenticated/guests.tsx` | Guest list + CRUD (897 lines) |
| `package.json` | All dependencies and scripts |
