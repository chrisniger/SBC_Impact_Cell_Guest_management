1. The 10 Roles (from  RolesAndPermissionsSeeder )
┌─────┬─────────────────────────┬─────────────────────────────────────────────────┐
│ #   │ Role name               │ Group (from RoleHelper)                         │
├─────┼─────────────────────────┼─────────────────────────────────────────────────┤
│ 1   │ Administrator           │ (no group — global admin)                       │
│ 2   │ Supervisor              │ (no group — read-only)                          │
│ 3   │ FollowUpOfficer         │ followUpOfficer                                 │
│ 4   │ Follow_UP_Admin         │ followUpOfficer                                 │
│ 5   │ Follow_UP               │ followUpTeam                                    │
│ 6   │ Follow_UP_View_Only     │ followUpTeam                                    │
│ 7   │ Impact_Leaders          │ impactCell                                      │
│ 8   │ Impact_Cell_Admin       │ impactCell                                      │
│ 9   │ Impact_Cell_Report      │ impactCell (view-only)                          │
│ 10  │ Impact_Zonal_Cordinator │ impactCell (branch — zonal dashboard overrides) │


───────────────────────────────────────────────────────────────────────────────
2. What Each Role Sees on  /dashboard 
🔵 Administrator (variant:  admin )
- Header: "Administrator · Full system access"
- KPI strip (7 cards):
1. Total Guests (default color) – trend "in the system"
2. Pending Contacts (indigo) – trend "no contact yet"
3. Total Calls (emerald) – trend "guests contacted"
4. Visited (emerald) – trend "confirmed visits"
5. Impact Cells (blue) – trend "registered cells"
6. Total Submissions (indigo) – trend "across all forms"
7. Total Users (default) – trend "system accounts"
- Section: "Recent Activity" — quick-link cards: Impact Cells, Guests, Reports, Notifications, Audit Log
- Empty queue (admin has no assigned guests)


🟣 Supervisor (variant:  admin  — same dashboard as Admin, read-only)
- Header: "Administrator · Full system access" (label follows the variant)
- Identical KPI strip & quick-link section
-  RoleHelper::canEditField  returns  false  for every Guest field — read-only on Guests
- No  group  →  stripDisallowed  strips every field from any write; defensive empty writes
- Nav bar shows only: Dashboard, Profile


🟡 FollowUpOfficer ( followUpOfficer  group, variant:  officer )
- Header: "Follow Up Officer — Your assigned guests"
- KPI strip (5 cards), all scoped to  follow_officer_id = user.id :
1. Pending Contacts (indigo) – trend "≤ pending outreach"
2. Total Calls (emerald) – trend "guests contacted"
3. Visited (emerald) – trend "confirmed visits"
4. Pending Visit (amber) – trend "available, awaiting visit"
5. Response Rate (default) – value  "NN.N%" , trend "visited ÷ total calls"
- Top-8 queue (rows: name, phone, contacted status, visited badge, date) sorted by contact-status priority → created_at desc
- Empty-state shown when no assigned guests
- Nav: Dashboard, My Guests, Profile


🟡 Follow_UP_Admin ( followUpOfficer  group, variant:  officer )
- Identical dashboard to FollowUpOfficer (same variant dispatch)
- One extra privilege per  RoleHelper : may write the  follow_officer_id  column → can reassign guests to other officers


🟠 Follow_UP ( followUpTeam  group, variant:  team )
- Header: "Follow Up Team — Team-wide queue and KPIs"
- KPI strip (4 cards), scoped to ALL guests:
1. Pending Contacts (indigo) – trend "not yet contacted"
2. Contacted Today (emerald) – trend "contact sections logged today"
3. Wrong Number (rose) – trend "marked wrong number"
4. Not Reachable (amber) – trend "could not be reached"
- Team Queue section — table with columns: Guest, Phone, Status (inline  <select>  with options — Not Contacted, Contacted, Wrong Number, Not Reachable), Latest Contact, Officer, Updated. Up to 20 rows. Inline  PATCH /guests/{id}/follow-up-status  from the row's  <select>  (no reload).
- Group-owned fields:  follow_up_status  +  follow_up_contacts  are the only Guest fields this role can write
- Nav: Dashboard, My Guests, Profile


🟠 Follow_UP_View_Only ( followUpTeam  group, variant:  team  — same dashboard, but locked)
- Same Team Queue + KPIs as  Follow_UP 
-  <ViewOnlyBanner>  is rendered at the top (the role key  'Follow_UP_View_Only'  matches the banner's predicate)
- Inline  <select>  in the queue is disabled / read-only
- Group-owned fields are still  follow_up_status  +  follow_up_contacts , but the matrix is gated by canEditField → false.


🟢 Impact_Leaders ( impactCell  group, variant:  impactCell )
- Header: "Impact Cell Leader — Weekly submissions"
- KPI strip (4 cards):
1. Cell (indigo) – value =  cellName , trend "{N} members" or "No members"
2. Members (emerald) – value =  memberCount  (currently set to  0  in the controller — placeholder)
3. Week Submissions (amber) – value = count since  startOfWeek() 
4. Total Submissions (emerald) – value = lifetime count
- Recent Submissions — last 10 own submissions (columns: type, cell, preview name, date)
- Leadership Board — rendered if the user's favorite cell is a primary cell (controller walks sub-cells up to their  parent_cell_id )
- Group-owned Guest fields:  impact_status ,  nearest_impact_cell_id 
- Nav: Dashboard, My Reports, Soul Search


🟢 Impact_Cell_Admin ( impactCell  group, variant:  impactCell )
- Same dashboard as Impact_Leaders (same variant dispatch)
- Per  RoleHelper::canEditField : may additionally reassign  follow_officer_id  (the controller-level reassign permission)
- Group-owned Guest fields:  impact_status ,  nearest_impact_cell_id 


🟢 Impact_Cell_Report ( impactCell  group, variant:  impactCell )
- Same dashboard as Impact_Leaders (same variant dispatch)
- The  report  type  Impact_Cell_Report  is in  Dashboard.tsx  line 531 — drives the empty-state copy / filtering
- View-only pattern like  Follow_UP_View_Only  (likely shows ViewOnlyBanner equivalent)
- Group-owned Guest fields:  impact_status ,  nearest_impact_cell_id 


🟦 Impact_Zonal_Cordinator (role-specific override — variant:  zonal )
- Header: "Zonal Coordinator — Multi-cell oversight"
- KPI strip (4 cards):
1. Impact Cells (indigo) – value =  totalCells , trend "in your zone"
2. Total Submissions (emerald) – lifetime submissions
3. Pending Guests (amber) – trend "awaiting outreach"
4. Contacted Guests (emerald) – trend "contact made"
- Impact Cells section — list of  ZonalCell  rows (id, name, is_primary)
- Recent Submissions — last 10 submissions across the zone
- Per  RoleHelper::canEditField : may reassign  follow_officer_id 
- Nav: Dashboard, Impact Cells, Guests, Reports, Export CSV
────────────────────────────────────────────────────────────────────────────────
3. Common Shell Across All Dashboards
- Top banner reading  <ViewOnlyBanner role={activeRole} />  on every page (renders a notice for  Follow_UP_View_Only  + read-only supervisors)
-  <SectionHeader>  titles use the same iconography per variant
- Empty-states are explicit ( description="..."  props) — each variant explains why the section is empty
- Hover lift + motion-safe fade-in animations (CSS  motion-safe:animate-[fadeIn_0.4s_ease-out] )
────────────────────────────────────────────────────────────────────────────────
4. Sidebar Navigation Per Role (bonus — from  AuthenticatedLayout )
┌────────────────────────────────┬──────────────────────────────────────────────────────────────────────────────────────┐
│ Role                           │ Nav items                                                                            │
├────────────────────────────────┼──────────────────────────────────────────────────────────────────────────────────────┤
│ Administrator                  │ Dashboard · Guests · Impact Cells · Reports · CSV Import · Notifications · Audit Log │
│ Impact_Zonal_Cordinator        │ Dashboard · Impact Cells · Guests · Reports · Export CSV                             │
│ Supervisor                     │ Dashboard · Profile                                                                  │
│ FollowUpOfficer / followUpTeam │ Dashboard · My Guests · Profile (Team Queue is the /guests subset)                   │
│ Cell Leader (impactCell group) │ Dashboard · My Reports · Soul Search                                                 │
└────────────────────────────────┴──────────────────────────────────────────



Impact Portal Plus — Complete Design System (Light ⇄ Dark)
Inventory across Login, Dashboard + 5 variants, plus Guests (Idx/Show/Edit), Reports, Audit, Notifications, CSV Import, ImpactCells (Idx/Show), ImpactSubmissions (Idx/MyReports/Create/Show/SoulSearch), Profile, and Welcome.
The system follows one rule across every page: every utility class is paired with its  dark:  variant using Tailwind's default v3 palette. Below is the A→B mapping — each row shows the same Tailwind token in both modes, the literal hex, and where it surfaces.
────────────────────────────────────────────────────────────────────────────────
1. Neutral Surface Stack (page + card chrome)
┌───────────────┬──────────┬───────┬─────────────────────────────┬─────────────────────────────────────────────────────────────────┐
│ Token         │ Light    │ Dark  │ Tailwind classes            │ Used in                                                         │
│               │ hex      │ hex   │                             │                                                                 │
├───────────────┼──────────┼───────┼─────────────────────────────┼─────────────────────────────────────────────────────────────────┤
│ Page wash     │ #F8FAFC  │ #0307 │ from-slate-50 …             │ All authenticated AuthenticatedLayout, GuestLayout (login)      │
│ start         │          │ 12    │ dark:from-gray-950          │                                                                 │
│ Page wash mid │ #FFFFFF  │ #1118 │ via-white …                 │ Login form panel, dashboard, impact-cell hero                   │
│               │          │ 27    │ dark:via-gray-900           │                                                                 │
│ Page wash end │ #EEF2FF/ │ #0206 │ to-indigo-50/30 …           │ Page bg, welcome page                                           │
│               │ 30       │ 17    │ dark:to-slate-950           │                                                                 │
│ Card surface  │ #FFFFFF  │ #1F29 │ bg-white … dark:bg-gray-800 │ KPICard, EmptyState, table cards, form cards (Genealogy,        │
│               │          │ 37    │                             │ EditGuest, etc.)                                                │
│ Card-hover    │ #F9FAFB  │ #3741 │ hover:bg-gray-50 …          │ Edit form cancel button, dropdown trigger                       │
│ surface       │          │ 51    │ dark:hover:bg-gray-700      │                                                                 │
│ Inner wash    │ #F9FAFB/ │ #0307 │ bg-gray-50/50 …             │ Section headers inside cards (Core, Contact, Meta, AddRule),    │
│               │ 50       │ 12/40 │ dark:bg-gray-900/40         │ sticky footer bar above                                         │
│ Table head    │ #F9FAFB/ │ #1118 │ bg-gray-50/80 …             │ <thead> in every table (Guests, Audit, Impact Submissions,      │
│               │ 80       │ 27/60 │ dark:bg-gray-900/60         │ MyReports, SoulSearch, Notifications, LeadershipBoard)          │
│ Row divider   │ #E5E7EB  │ #3741 │ divide-gray-200 …           │ All <tbody> rows                                                │
│               │          │ 51    │ dark:divide-gray-700        │                                                                 │
│ Row hover     │ #EEF2FF/ │ #3741 │ hover:bg-indigo-50/40 …     │ Every table row on Guests, Audit, Submissions, Reports,         │
│               │ 40       │ 51/40 │ dark:hover:bg-gray-700/40   │ Exports, etc.                                                   │
│ Card border   │ #E5E7EB  │ #3741 │ border-gray-200 …           │ KPI cards, EmptyState card, ImpactSubmissions Show, Guest Show, │
│               │          │ 51    │ dark:border-gray-700        │ Reports chart cards                                             │
│ Inner border  │ #F3F4F6  │ #3741 │ border-gray-100 …           │ Inside form <dl> rows, card-header separators                   │
│               │          │ 51    │ dark:border-gray-700        │                                                                 │
│ Translucent   │ #E5E7EB/ │ #1F29 │ border-gray-200/60 …        │ Sticky top-bar, header band, mobile menu (glassmorphism)        │
│ border        │ 60       │ 37/60 │ dark:border-gray-800/60     │                                                                 │
│ Soft dashed   │ #D1D5DB  │ #4B55 │ border-gray-300 …           │ EmptyState dashed border, CSV drop zone, QuickSubmit dashed     │
│ border        │          │ 63    │ dark:border-gray-600        │ buttons                                                         │
│ Soft shadow   │ rgba(0,0 │ same  │ shadow-[0_4px_20px_rgba(0,0 │ All card surfaces (KPICard, EmptyState, form cards)             │
│               │ ,0,.03)  │       │ ,0,0.03)]                   │                                                                 │
│ Hover-shadow  │ rgba(0,0 │ same  │ hover:shadow-[0_8px_30px_rg │ KPI cards, ImpactCell tiles, QuickLinks                         │
│               │ ,0,.06)  │       │ ba(0,0,0,0.06)]             │                                                                 │
└───────────────┴──────────┴───────┴─────────────────────────────┴─────────────────────────────────────────────────────────────────┘
────────────────────────────────────────────────────────────────────────────────
2. Brand & Accent (Indigo family — the primary only)
┌────────┬─────┬─────┬──────────────────────────┬──────────────────────────────────────────────────────────────────────────────────┐
│ Token  │ Lig │ Dar │ Tailwind classes         │ Used in                                                                          │
│        │ ht  │ k   │                          │                                                                                  │
│        │ hex │ hex │                          │                                                                                  │
├────────┼─────┼─────┼──────────────────────────┼──────────────────────────────────────────────────────────────────────────────────┤
│ indigo │ #EE │ sam │ bg-indigo-50,            │ QuickSubmit, QuickLink hover wash, ImpactCell card glow                          │
│ -50    │ F2F │ e   │ hover:bg-indigo-50/50    │                                                                                  │
│        │ F   │     │                          │                                                                                  │
│ indigo │ #E0 │ sam │ bg-indigo-100            │ CSV drop zone circle; CSV file-info tile                                         │
│ -100   │ E7F │ e   │                          │                                                                                  │
│        │ F   │     │                          │                                                                                  │
│ indigo │ #C7 │ sam │ border-indigo-200,       │ Welcome strip border, hover ring on Sign-in-secondary button                     │
│ -200   │ D2F │ e   │ bg-indigo-200            │                                                                                  │
│        │ E   │     │                          │                                                                                  │
│ indigo │ —   │ #A5 │ dark:text-indigo-300     │ KPI deltas dark, section-header rail text dark, card icons dark (Login info      │
│ -300   │     │ B4F │                          │ icon, CSV drop, Notifications card-icons, AddRule card-icon, etc.)               │
│        │     │ C   │                          │                                                                                  │
│ indigo │ —   │ #81 │ dark:bg-indigo-400       │ Hover state on Login Sign in primary button                                      │
│ -400   │     │ 8CF │                          │                                                                                  │
│        │     │ 8   │                          │                                                                                  │
│ indigo │ #63 │ sam │ bg-indigo-500            │ Active-nav underline dot (Dashboard); pulse dot on Welcome badge; checkbox       │
│ -500   │ 66F │ e   │                          │ accent (text-indigo-600 actually)                                                │
│        │ 1   │     │                          │                                                                                  │
│ indigo │ #4F │ #81 │ bg-indigo-600 …          │ All Primary CTAs (Sign in, Save Changes, Submit Report, Add Rule, Upload &       │
│ -600   │ 46E │ 8CF │ dark:bg-indigo-500 &     │ Import, Add Guest, Mark Contacted, Open dashboard, Get Started), eyebrows        │
│        │ 5   │ 8   │ text-indigo-600 …        │ (text-indigo-600), link default, KPI Indigo accent, focus rings everywhere       │
│        │     │     │ dark:text-indigo-400     │ (focus:ring-indigo-500)                                                          │
│ indigo │ #43 │ #A5 │ hover:bg-indigo-700      │ Primary hover, gradient stop in Login brand panel                                │
│ -700   │ 38C │ B4F │                          │                                                                                  │
│        │ A   │ C   │                          │                                                                                  │
│ indigo │ #37 │ —   │ active:bg-indigo-800     │ Sign-in button :active                                                           │
│ -800   │ 30A │     │                          │                                                                                  │
│        │ 3   │     │                          │                                                                                  │
│ indigo │ #31 │ sam │ via-indigo-900 (Login    │ Login brand panel base, dark hover wash (ImpactCell row, QuickSubmit)            │
│ -900   │ 2E8 │ e   │ bg) — also               │                                                                                  │
│        │ 1   │     │ dark:bg-indigo-900/20    │                                                                                  │
│ indigo │ —   │ tra │ dark:bg-indigo-900/30    │ EmptyState icon container dark, Form shell error bg dark, form-actions dark      │
│ -900/3 │     │ nsp │                          │ hover                                                                            │
│ 0      │     │ are │                          │                                                                                  │
│        │     │ nt  │                          │                                                                                  │
│ indigo │ —   │ tra │ dark:bg-indigo-900/40    │ Section header icon-tile dark, KeyMetric hover badge dark, Mark-Contacted chip   │
│ -900/4 │     │ nsp │                          │ dark                                                                             │
│ 0      │     │ are │                          │                                                                                  │
│        │     │ nt  │                          │                                                                                  │
│ indigo │ —   │ tra │ dark:from-indigo-950/40  │ ImpactCells Show hero band + ImpactSubmissions Show hero band (dark side of      │
│ -950/4 │     │ nsp │                          │ from-indigo-50 via-white to-blue-50)                                             │
│ 0      │     │ are │                          │                                                                                  │
│        │     │ nt  │                          │                                                                                  │
└────────┴─────┴─────┴──────────────────────────┴──────────────────────────────────────────────────────────────────────────────────┘
Hero gradient (used by Login brand panel AND ImpactCell/Submission hero bands):
Light:  bg-gradient-to-br from-indigo-600 via-blue-700 to-indigo-900 
Dark:   dark:from-indigo-950/40 dark:via-gray-900 dark:to-blue-950/40 
────────────────────────────────────────────────────────────────────────────────
3. Secondary Accents (Blue / Cyan / Emerald / Amber / Rose / Red / Violet)
┌────────────────┬────────────────────┬──────────────────────────┬─────────────────────────────────────────────────────────────────┐
│ Family         │ Light bg/text      │ Dark bg/text             │ Status pill surfaces in                                         │
├────────────────┼────────────────────┼──────────────────────────┼─────────────────────────────────────────────────────────────────┤
│ Blue (info)    │ bg-blue-100 /      │ dark:bg-blue-900/40 /    │ StatusPill tone="info" — used by Guests/Show for                │
│                │ text-blue-700      │ dark:text-blue-300       │ AvailableForVisit via info, and ImpactCells/Show Sub-cell chip  │
│                │ #DBEAFE / #1D4ED8  │ #1E3A8A40 / #93C5FD      │                                                                 │
│ Emerald        │ bg-emerald-100 /   │ dark:bg-emerald-900/40 / │ Success pills (Visited, Contacted, Mark Contacted, Enabled      │
│ (success)      │ text-emerald-700   │ dark:text-emerald-300    │ rule, CSV result Created); also Mission-hub indicator dot       │
│                │ #D1FAE5 / #047857  │ #064E3B40 / #6EE7B7      │ (bg-emerald-300 light only); KPI Emerald accent                 │
│ Amber          │ bg-amber-100 /     │ dark:bg-amber-900/40 /   │ Warn pills (Not Contacted, Pending, Pending Visit, CSV          │
│ (warning)      │ text-amber-700     │ dark:text-amber-300      │ Skipped); KPI Amber accent; Read-only banner (border-amber-300  │
│                │ #FEF3C7 / #B45309  │ #78350F40 / #FCD34D      │ bg-amber-50 → dark border-amber-700 bg-amber-900/30)            │
│ Rose (danger)  │ bg-rose-100 /      │ dark:bg-rose-900/40 /    │ KPI Rose accent; Remove row button (text-rose-600               │
│                │ text-rose-700      │ dark:text-rose-300       │ dark:text-rose-400); CSV Errors pill                            │
│                │ #FFE4E6 / #BE123C  │ #88133740 / #FB7185      │                                                                 │
│ Red (brand     │ bg-red-100 /       │ dark:bg-red-900/40 /     │ Administrator role badge, Primary cell pill, ContactsTimeline   │
│ pill /         │ text-red-700       │ dark:text-red-300        │ step circle                                                     │
│ Supervisor     │ #FEE2E2 / #B91C1C  │ #7F1D1D40 / #FCA5A5      │                                                                 │
│ tone)          │                    │                          │                                                                 │
│ Violet         │ text-violet-600,   │ dark:text-violet-400,    │ Welcome feature card (Follow-up cadence) + gradient hero text   │
│ (Welcome page  │ bg-violet-300/30   │ dark:bg-violet-500/15    │                                                                 │
│ only)          │                    │                          │                                                                 │
│ Sky            │ bg-sky-100 /       │ dark:bg-sky-900 /        │ Follow_UP_View_Only role badge only                             │
│                │ text-sky-800       │ dark:text-sky-200        │                                                                 │
│                │ #E0F2FE / #075985  │ #0C4A6E / #BAE6FD        │                                                                 │
│ Purple         │ bg-purple-100 /    │ dark:bg-purple-900 /     │ Supervisor role badge only                                      │
│                │ text-purple-800    │ dark:text-purple-200     │                                                                 │
│                │ #F3E8FF / #6B21A8  │ #581C87 / #E9D5FF        │                                                                 │
│ Green          │ bg-green-100 /     │ dark:bg-green-900 /      │ Impact_Leaders role badge only                                  │
│                │ text-green-800     │ dark:text-green-200      │                                                                 │
│                │ #DCFCE7 / #166534  │ #14532D / #BBF7D0        │                                                                 │
│ Teal           │ bg-teal-100 /      │ dark:bg-teal-900 /       │ Impact_Cell_Report role badge only                              │
│                │ text-teal-800      │ dark:text-teal-200       │                                                                 │
│                │ #CCFBF1 / #115E59  │ #134E4A / #99F6E4        │                                                                 │
└────────────────┴────────────────────┴──────────────────────────┴─────────────────────────────────────────────────────────────────┘
────────────────────────────────────────────────────────────────────────────────
4. Status Pill —  StatusPill  component (all 6 tones)
┌─────────┬─────────────────────────┬────────────────────────────┬────────────────┐
│ Tone    │ Light bg / text         │ Dark bg / text             │ Dot pure-color │
├─────────┼─────────────────────────┼────────────────────────────┼────────────────┤
│ neutral │ gray-100 gray-700       │ gray-700 gray-200          │ gray-500       │
│ success │ emerald-100 emerald-700 │ emerald-900/40 emerald-300 │ emerald-500    │
│ warn    │ amber-100 amber-700     │ amber-900/40 amber-300     │ amber-500      │
│ danger  │ rose-100 rose-700       │ rose-900/40 rose-300       │ rose-500       │
│ brand   │ red-100 red-700         │ red-900/40 red-300         │ red-500        │
│ info    │ blue-100 blue-700       │ blue-900/40 blue-300       │ blue-500       │
└─────────┴─────────────────────────┴────────────────────────────┴────────────────┘
────────────────────────────────────────────────────────────────────────────────
5. KPI Card — accent map (Dashboard)
┌────────────────┬──────────┬──────────┬───────────────────────────────────────────────────────────────────────────────────────────┐
│ accent prop    │ Light    │ Dark     │ Dashboards using                                                                          │
│                │ value    │ value    │                                                                                           │
├────────────────┼──────────┼──────────┼───────────────────────────────────────────────────────────────────────────────────────────┤
│ default        │ gray-900 │ gray-100 │ Officer Response Rate, Leader Total, Sub-cells                                            │
│ (no-prop)      │          │          │                                                                                           │
│ indigo         │ indigo-6 │ indigo-4 │ Officer/Team/L/Zonal/Admin "Pending Contacts", Admin Total Guests, Reports Pending Visit  │
│                │ 00       │ 00       │                                                                                           │
│ emerald        │ emerald- │ emerald- │ Officer/Admin "Total Calls", "Visited", Leader Members, Zonal Contacted Guests, Reports   │
│                │ 600      │ 400      │ Total Calls/Visited                                                                       │
│ amber          │ amber-60 │ amber-40 │ Officer/Admin "Pending Contacts", Officer Pending Visit, Leader This Week, Zonal Pending  │
│                │ 0        │ 0        │ Guests, Reports Pending Contacts                                                          │
│ rose           │ rose-600 │ rose-400 │ Team "Wrong Number" only                                                                  │
│ blue           │ blue-600 │ blue-400 │ Admin "Impact Cells" only                                                                 │
└────────────────┴──────────┴──────────┴───────────────────────────────────────────────────────────────────────────────────────────┘
Status deltas: positive =  emerald-600/dark:emerald-400 , negative =  rose-600/dark:rose-400 .
────────────────────────────────────────────────────────────────────────────────
6. Login-only decoration (one-offs)
┌────────────────────────────────┬───────────────────────────────────────────┬─────────────────────────────────────────────────────┐
│ Element                        │ Light                                     │ Dark                                                │
├────────────────────────────────┼───────────────────────────────────────────┼─────────────────────────────────────────────────────┤
│ Brand panel gradient           │ from-indigo-600 via-blue-700              │ same (solid)                                        │
│                                │ to-indigo-900 (solid)                     │                                                     │
│ Glow orb 1                     │ bg-white/10 blur-3xl 8s                   │ same                                                │
│ Glow orb 2                     │ bg-blue-400/25 blur-3xl 10s               │ same                                                │
│ Glow orb 3                     │ bg-indigo-400/15 blur-3xl 12s             │ same                                                │
│ SVG grid overlay               │ text-white opacity-[0.07]                 │ same                                                │
│ Right-edge rail                │ via-white/30                              │ same                                                │
│ Hero text gradient             │ from-white via-blue-100 to-indigo-200     │ same                                                │
│                                │ (bg-clip-text)                            │                                                     │
│ Status banner (Login session   │ border-green-200 bg-green-50              │ dark:border-green-900/50 dark:bg-green-900/20       │
│ status)                        │ text-green-700                            │ dark:text-green-300                                 │
└────────────────────────────────┴───────────────────────────────────────────┴─────────────────────────────────────────────────────┘
────────────────────────────────────────────────────────────────────────────────
7. Welcome-only decoration
┌──────────────────┬───────────────────────────────────────────────────────────┬───────────────────────────────────────────────────┐
│ Element          │ Light                                                     │ Dark                                              │
├──────────────────┼───────────────────────────────────────────────────────────┼───────────────────────────────────────────────────┤
│ Glow orb 1       │ bg-indigo-300/30 blur-3xl 14s                             │ dark:bg-indigo-500/20                             │
│ Glow orb 2       │ bg-violet-300/30 blur-3xl 18s +2s delay                   │ dark:bg-violet-500/15                             │
│ SVG grid overlay │ opacity-[0.04]                                            │ opacity-[0.06]                                    │
│ Hero text        │ from-indigo-600 via-violet-600 to-indigo-600              │ dark:from-indigo-400 dark:via-violet-400          │
│ gradient         │                                                           │ dark:to-indigo-400                                │
│ Feature card     │ ring-indigo-200 / ring-violet-200 / ring-emerald-200 /    │ dark:ring-indigo-500/30 etc.                      │
│ rings            │ ring-amber-200                                            │                                                   │
│ Feature icon     │ bg-indigo-50 text-indigo-600 (and equivalents)            │ dark:bg-indigo-500/10 dark:text-indigo-400        │
│ tints            │                                                           │                                                   │
│ Trust-strip      │ text-emerald-500                                          │ same                                              │
│ check            │                                                           │                                                   │
└──────────────────┴───────────────────────────────────────────────────────────┴───────────────────────────────────────────────────┘
────────────────────────────────────────────────────────────────────────────────
8. Reports — chart bars (the only place static hex appears)
┌─────────────────────┬───────────────────────────────┬─────────────┬────────────────────────────────────────────┐
│ Chart               │ Bar/area fill                 │ Grid stroke │ Notes                                      │
├─────────────────────┼───────────────────────────────┼─────────────┼────────────────────────────────────────────┤
│ By Contact Status   │ #dc2626 (red-600)             │ #e5e7eb     │ Light only — Reports/Index.tsx literal hex │
│ By Follow Up Status │ #f97316 (orange-500)          │ #e5e7eb     │ Light only                                 │
│ By Event            │ #8b5cf6 (violet-500)          │ #e5e7eb     │ Light only                                 │
│ Monthly Trend       │ stroke #dc2626 / fill #fecaca │ #e5e7eb     │ Light only                                 │
└─────────────────────┴───────────────────────────────┴─────────────┴────────────────────────────────────────────┘
(These hex literals are not currently swapped for dark — a known dark-mode gap you can flag if interested — most of the surface is dark anyway because charts sit on  bg-gray-800  cards.)
────────────────────────────────────────────────────────────────────────────────
9. Form & input atoms (identical everywhere)
┌───────────────────────────────────┬───────────────────────────────────────────┬──────────────────────────────────────────────────┐
│ Component                         │ Light                                     │ Dark                                             │
├───────────────────────────────────┼───────────────────────────────────────────┼──────────────────────────────────────────────────┤
│ TextInput, <select>, <textarea>   │ border-gray-300                           │ dark:border-gray-700                             │
│ border                            │                                           │                                                  │
│ Input surface                     │ bg-white                                  │ dark:bg-gray-900                                 │
│ Input text                        │ (inherits)                                │ dark:text-gray-300                               │
│ Focus ring                        │ focus:border-indigo-500                   │ dark:focus:border-indigo-600                     │
│                                   │ focus:ring-indigo-500                     │ dark:focus:ring-indigo-600                       │
│ Checkbox                          │ border-gray-300 text-indigo-600           │ dark:border-gray-700 dark:bg-gray-900            │
│                                   │ ring-indigo-200/50                        │                                                  │
│ KB order surface (Login ↵ hint)   │ border-gray-200 bg-white text-gray-600    │ dark:border-gray-700 dark:bg-gray-800            │
│                                   │ shadow-sm                                 │ dark:text-gray-400                               │
│ Dropdown trigger btn              │ border-gray-200/80 bg-white/80            │ dark:border-gray-700 dark:bg-gray-800/80         │
│                                   │ text-gray-700                             │ dark:text-gray-200                               │
└───────────────────────────────────┴───────────────────────────────────────────┴──────────────────────────────────────────────────┘
────────────────────────────────────────────────────────────────────────────────
10. Inline-status select on Dashboard Team Queue
 border-gray-300 bg-white text-sm focus:border-indigo-500 focus:ring-indigo-500  ⇔  dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300  (view-only adds  opacity-60 cursor-not-allowed ).
────────────────────────────────────────────────────────────────────────────────
11. Global motion + cursor tokens (apply to all pages)
┌───────────────────────────────────────────────────────────────┬──────────────────────────────────────────────────────────────────┐
│ Class                                                         │ Effect                                                           │
├───────────────────────────────────────────────────────────────┼──────────────────────────────────────────────────────────────────┤
│ transition-all duration-200                                   │ Default hover transition                                         │
│ hover:-translate-y-0.5                                        │ Lift on KPI / impact-cell tile / quick-link / empty-state        │
│ motion-safe:animate-[fadeIn_0.4s_ease-out]                    │ Page + section entrance                                          │
│ animate-[pulse_8s_ease-in-out_infinite] (and 10s/12s)         │ Login brand glow orbs                                            │
│ animate-spin                                                  │ Loading spinners across forms                                    │
│ focus:outline-none focus:ring-2 focus:ring-indigo-500         │ Universal focus state (dark:focus-visible:ring-offset-gray-900   │
│ focus:ring-offset-2                                           │ on Welcome only)                                                 │
└───────────────────────────────────────────────────────────────┴──────────────────────────────────────────────────────────────────┘
────────────────────────────────────────────────────────────────────────────────
Quick "what color goes where" cheat sheet
- Primary action / link / focus ring → Indigo 600 (light) / Indigo 400 (dark)
- Heading copy → Gray 900 / Gray 100
- Body text → Gray 700 / Gray 300
- Muted text → Gray 600 / Gray 400
- Caption (KPIs, table heads, eyebrows) → Gray 500 / Gray 400
- Surface (card / page) → White / Gray 800
- Surface wash → Gray 50/50 or 80 / Gray 900/40 or 60
- Borders → Gray 200 / Gray 700
- Soft borders (translucent, sticky bars) → Gray 200/60 / Gray 800/60
- Success → Emerald (same tones across modes)
- Warning → Amber
- Danger → Rose
- Info → Blue
- Brand role / Primary cell pill → Red