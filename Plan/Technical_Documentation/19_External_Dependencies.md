# External Dependencies

## Backend Dependencies (server/)

| Package | Version | Purpose |
|---------|---------|---------|
| `@prisma/client` | ^5.20.0 | Database ORM — type-safe database access |
| `bcryptjs` | ^2.4.3 | Password hashing (bcrypt with 10 rounds) |
| `cookie-parser` | ^1.4.7 | Parse cookies (JWT token fallback) |
| `cors` | ^2.8.5 | Cross-Origin Resource Sharing |
| `csv-parse` | ^5.5.6 | CSV file parsing (sync mode) |
| `dotenv` | ^16.4.5 | Load environment variables from `.env` |
| `express` | ^4.21.0 | HTTP server framework |
| `jsonwebtoken` | ^9.0.2 | JWT token signing and verification |
| `morgan` | ^1.10.0 | HTTP request logging |
| `multer` | ^1.4.5-lts.1 | File upload handling (memory storage) |
| `nodemailer` | ^8.0.11 | Email sending via SMTP |

## Backend Dev Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| `nodemon` | ^3.1.7 | Auto-restart server on file changes |
| `prisma` | ^5.20.0 | Prisma CLI (migrations, generate) |

## Frontend Dependencies (package.json)

### Core Frameworks

| Package | Version | Purpose |
|---------|---------|---------|
| `react` | ^19.2.0 | UI library |
| `react-dom` | ^19.2.0 | React DOM renderer |
| `@tanstack/react-query` | ^5.83.0 | Server state management |
| `@tanstack/react-router` | ^1.168.25 | Client-side routing |
| `@tanstack/router-plugin` | ^1.167.28 | TanStack Router Vite plugin |
| `@tanstack/react-start` | ^1.167.65 | SSR framework |

### UI & Styling

| Package | Version | Purpose |
|---------|---------|---------|
| `tailwindcss` | ^4.2.1 | Utility-first CSS framework |
| `@tailwindcss/vite` | ^4.3.0 | Tailwind CSS Vite plugin |
| `tw-animate-css` | ^1.3.4 | Tailwind animation utilities |
| `class-variance-authority` | ^0.7.1 | Component variant management |
| `clsx` | ^2.1.1 | Conditional class name construction |
| `tailwind-merge` | ^3.5.0 | Merge Tailwind classes without conflicts |
| `lucide-react` | ^0.575.0 | Icon library |
| `recharts` | ^2.15.4 | Charting library |

### Radix UI / shadcn Components

| Package | Version | Purpose |
|---------|---------|---------|
| `@radix-ui/react-accordion` | ^1.2.12 | Collapsible accordion |
| `@radix-ui/react-alert-dialog` | ^1.1.15 | Alert/modal dialogs |
| `@radix-ui/react-aspect-ratio` | ^1.1.8 | Aspect ratio container |
| `@radix-ui/react-avatar` | ^1.1.11 | Avatar component |
| `@radix-ui/react-checkbox` | ^1.3.3 | Checkbox input |
| `@radix-ui/react-collapsible` | ^1.1.12 | Collapsible panel |
| `@radix-ui/react-context-menu` | ^2.2.16 | Right-click context menu |
| `@radix-ui/react-dialog` | ^1.1.15 | Modal dialog |
| `@radix-ui/react-dropdown-menu` | ^2.1.16 | Dropdown menu |
| `@radix-ui/react-hover-card` | ^1.1.15 | Hover popover |
| `@radix-ui/react-label` | ^2.1.8 | Form label |
| `@radix-ui/react-menubar` | ^1.1.16 | Menu bar |
| `@radix-ui/react-navigation-menu` | ^1.2.14 | Navigation menu |
| `@radix-ui/react-popover` | ^1.1.15 | Popover |
| `@radix-ui/react-progress` | ^1.1.8 | Progress bar |
| `@radix-ui/react-radio-group` | ^1.3.8 | Radio button group |
| `@radix-ui/react-scroll-area` | ^1.2.10 | Custom scroll area |
| `@radix-ui/react-select` | ^2.2.6 | Select/dropdown |
| `@radix-ui/react-separator` | ^1.1.8 | Visual separator |
| `@radix-ui/react-slider` | ^1.3.6 | Range slider |
| `@radix-ui/react-slot` | ^1.2.4 | Slot/as-child pattern |
| `@radix-ui/react-switch` | ^1.2.6 | Toggle switch |
| `@radix-ui/react-tabs` | ^1.1.13 | Tabbed interface |
| `@radix-ui/react-toggle` | ^1.1.10 | Toggle button |
| `@radix-ui/react-toggle-group` | ^1.1.11 | Toggle button group |
| `@radix-ui/react-tooltip` | ^1.2.8 | Tooltip |

### Forms & Validation

| Package | Version | Purpose |
|---------|---------|---------|
| `react-hook-form` | ^7.71.2 | Form state management |
| `@hookform/resolvers` | ^5.2.2 | Form resolver integration |
| `zod` | ^3.24.2 | Schema validation |

### Utility

| Package | Version | Purpose |
|---------|---------|---------|
| `date-fns` | ^4.1.0 | Date manipulation |
| `sonner` | ^2.0.7 | Toast notifications |
| `cmdk` | ^1.1.1 | Command menu |
| `embla-carousel-react` | ^8.6.0 | Carousel |
| `input-otp` | ^1.4.2 | OTP input |
| `react-day-picker` | ^9.14.0 | Date picker |
| `react-resizable-panels` | ^4.6.5 | Resizable panel layout |
| `vaul` | ^1.1.2 | Drawer component |

### Build & Dev

| Package | Version | Purpose |
|---------|---------|---------|
| `vite` | ^7.3.1 | Build tool and dev server |
| `@vitejs/plugin-react` | ^5.2.0 | React Fast Refresh for Vite |
| `vite-tsconfig-paths` | ^6.1.1 | TypeScript path aliases for Vite |
| `typescript` | ^5.8.3 | TypeScript compiler |
| `eslint` | ^9.32.0 | Code linting |
| `@eslint/js` | ^9.32.0 | ESLint JavaScript rules |
| `typescript-eslint` | ^8.56.1 | TypeScript ESLint rules |
| `eslint-config-prettier` | ^10.1.1 | ESLint + Prettier compatibility |
| `eslint-plugin-prettier` | ^5.2.6 | Prettier as ESLint rule |
| `eslint-plugin-react-hooks` | ^5.2.0 | React Hooks ESLint rules |
| `eslint-plugin-react-refresh` | ^0.4.20 | Fast Refresh ESLint rules |
| `prettier` | ^3.7.3 | Code formatter |
| `globals` | ^15.15.0 | Global variables for ESLint |
| `esbuild` | ^0.28.0 | Fast bundler (used by Vite) |

### Cloudflare / Deployment

| Package | Version | Purpose |
|---------|---------|---------|
| `@cloudflare/vite-plugin` | ^1.25.5 | Cloudflare integration |
| `@lovable.dev/vite-tanstack-config` | ^1.5.1 | Lovable.dev config (project scaffold) |

### Shared Dependencies (used in both frontend and backend)

| Package | Frontend Role | Backend Role |
|---------|--------------|--------------|
| `bcryptjs` | — | Password hashing |
| `cookie-parser` | — | Cookie parsing |
| `cors` | — | CORS middleware |
| `csv-parse` | — | CSV parsing |
| `dotenv` | — | Env loading |
| `express` | — | HTTP server |
| `jsonwebtoken` | — | JWT |
| `morgan` | — | Logging |
| `multer` | — | File upload |
| `nodemailer` | — | Email |

Note: Backend dependencies are in `node_modules/` at root level (single `package.json` project). The `server/package.json` only contains `{"type": "commonjs"}`. All packages are installed via the root `package.json`.
