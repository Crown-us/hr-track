You are a senior full-stack software engineer, Laravel architect, and UI/UX designer.

PROJECT: HRIS Lite — Human Resource Information System
PURPOSE: Build a professional, portfolio-ready HRIS prototype for a software developer internship interview. This is an independent demonstration project inspired by a public internship roadmap, NOT a commissioned company system.

IMPORTANT: Work directly in the current repository. First inspect existing files and environment. If the repository already contains a project, preserve existing code and avoid destructive overwrites. If empty, scaffold the application. Do not claim commands or tests passed unless executed successfully.

TECH STACK
- Latest stable mutually compatible Laravel and PHP versions supported by the environment
- Inertia.js + React + TypeScript
- Tailwind CSS + shadcn/ui (or compatible React components)
- MySQL
- Laravel authentication and authorization
- Vite
- Laravel Pint, ESLint, Prettier
- Pest or PHPUnit for automated tests
- Use existing stable libraries rather than unnecessary dependencies

DESIGN DIRECTION
Create a polished enterprise SaaS dashboard inspired by modern HR platforms, but do not copy proprietary designs.
- Visual style: minimal, elegant, premium, high information density
- Palette: off-white #F8FAFC, navy #0F172A, indigo #4F46E5, emerald #10B981, muted gray
- Consistent colors across ALL pages
- Typography: clean modern sans-serif
- Responsive sidebar, topbar, breadcrumbs, empty states, loading states, and accessible forms
- Light mode first; dark mode optional
- UI language: Bahasa Indonesia
- Use realistic but entirely fictional seed data
- No real employee, applicant, or patient data

MVP SCOPE — IMPLEMENT THESE FIRST

1. AUTHENTICATION AND ROLE-BASED ACCESS
- Login, logout, authenticated session
- Roles: admin, hr_staff, viewer
- Enforce authorization on server side through policies or gates
- Protect every private route
- Provide development-only seeded accounts documented in README
- Never hardcode credentials in production application code

2. DASHBOARD
- Summary cards: total employees, active vacancies, total applicants, upcoming interviews
- Applicant pipeline overview
- Recent recruitment activity
- Upcoming contract expiration preview if contract data exists
- Responsive charts only when backed by real database queries

3. EMPLOYEE MANAGEMENT
- CRUD employee records
- Employee number, full name, work email, phone, department, position, employment status, join date
- Department and position relational tables
- Search, filters, sorting, pagination
- Server-side validation, unique constraints, soft delete where appropriate
- No sensitive national identity fields in MVP

4. JOB VACANCY MANAGEMENT
- CRUD vacancies
- Job title, department, employment type, location, description, requirements, opening date, closing date, status
- Draft, published, closed statuses
- Search, filters, pagination

5. APPLICANT TRACKING SYSTEM
- CRUD applicants
- Link applicants to vacancies
- Fields: full name, email, phone, application date, notes
- Pipeline statuses: screening, interview, offering, accepted, rejected
- Define allowed transitions centrally and enforce them on the backend
- Keep status-change history with actor, old status, new status, timestamp
- Applicant detail page with timeline
- Kanban-style pipeline or accessible status board
- Prevent unauthorized status changes

6. INTERVIEW SCHEDULING
- Schedule interview date/time, location or meeting link, interviewer, notes
- Validate scheduling data
- Generate downloadable PDF interview invitation using a maintained Laravel-compatible library
- The PDF is a demonstration document; do not send emails automatically

7. DOCUMENT UPLOAD
- Optional PDF CV upload for applicants
- Validate MIME type and file size on the server
- Store files privately and authorize downloads
- Prevent path traversal and public exposure
- Never execute uploaded content

ARCHITECTURE AND DATABASE
Start by proposing an ERD and migrations for:
users, departments, positions, employees, vacancies, applicants, applications, application_status_histories, interviews, applicant_documents.
Use clear foreign keys, unique indexes, appropriate nullability, timestamps, and referential integrity.
Separate applicant identity from applications so one person can apply to multiple vacancies.
Use Form Requests, Policies, Eloquent relationships, controllers, and focused service classes where justified.
Avoid unnecessary repository abstractions.
Prevent N+1 queries and apply pagination.
Use database transactions for multi-step status changes.

SECURITY AND QUALITY
- CSRF protection, authentication, authorization
- Secure password hashing
- Server-side validation
- Escape user-generated content
- Private document storage
- Safe logging without sensitive applicant details
- Do not expose stack traces or secrets
- Never commit .env or credentials
- Follow existing project coding conventions
- Explicit error handling
- Small maintainable functions
- Type-safe frontend props and forms
- Accessible inputs and keyboard navigation

AUTOMATED TESTS
Implement tests for:
- Unauthenticated access is denied
- Role permissions are enforced
- Employee CRUD and validation
- Vacancy CRUD and validation
- Applicant application creation
- Allowed and forbidden status transitions
- Status history persistence
- Private document access authorization
- PDF generation response
- Relevant database constraints

DELIVERABLES
- Working Laravel/Inertia/React application
- Migrations, factories, seeders
- Responsive UI
- README with prerequisites and setup commands
- .env.example
- ERD in Mermaid
- Feature list and architecture overview
- Screenshots instructions
- Demo walkthrough for a 3-minute interview presentation
- Honest list of known limitations

IMPLEMENTATION PROCESS
PHASE 0: Inspect repository, versions, and environment; identify blockers.
PHASE 1: Write docs/PLAN.md and docs/ERD.md; scaffold project.
PHASE 2: Implement authentication, roles, migrations, factories, and seeders.
PHASE 3: Build consistent dashboard shell and employee management.
PHASE 4: Implement vacancies and applicant tracking.
PHASE 5: Implement interviews, PDF, and private CV uploads.
PHASE 6: Run formatting, type checks, tests, and build; fix verified failures.
PHASE 7: Finalize README and interview demo script.

CRITICAL EXECUTION RULES
- Do not attempt all phases in one giant change.
- Begin with PHASE 0 and PHASE 1 only.
- Present the proposed database schema, page structure, architecture, implementation sequence, and risks.
- Then implement the approved initial foundation.
- After each phase, summarize changed files, commands run, actual test results, and next steps.
- If a command cannot run, explain the blocker instead of inventing a success.
- Do not deploy, publish, send emails, or push to remote without explicit approval.
- Keep the scope realistic for a 5–7 day MVP.
- Prioritize working end-to-end flows over decorative features.
- Ask for approval before making destructive changes or expanding scope.

START NOW: inspect the repository and environment, then create the initial plan and ERD.