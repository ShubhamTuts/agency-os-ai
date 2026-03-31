=== Agency OS AI ===
Contributors: codefreex
Tags: project management, task management, client portal, help desk, ai assistant
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.5.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered WordPress project manager with client portal, task manager, tickets, OpenAI tools, SMTP, webhooks, and a branded agency workspace.

== Description ==

🚀 Running an agency inside WordPress should feel organized, fast, and profitable.

But most agency owners still juggle project updates in one tool, task management in another, client communication somewhere else, and support requests in a completely different system. That fragmentation costs time, causes missed details, and makes your delivery process feel messy to clients.

It also means paying for multiple subscriptions when your team really wants one clear operating system.

**Agency OS AI turns WordPress into an AI-powered project management workspace** built for agencies, freelancers, internal teams, and service businesses of all sizes.

Manage projects, tasks, files, support tickets, client access, employee access, email delivery, webhooks, and reporting from one branded workspace you own.

✨ **The free version is generous and fully operational on its own.**

= What is included in free core =

**Project and task management**

- Modern React-powered WordPress project manager dashboard
- Projects, task lists, tasks, milestones, messages, and file management
- Dynamic workflow stages for richer task and ticket pipelines
- Built-in time tracking with manual entry and timer-based capture
- Team performance visibility in the reports screen
- Profile and settings management for every team member

**Client and billing operations**

- Client management with company profiles, contacts, and project linking
- Invoice management with line items, tax totals, send action, and print-to-PDF view
- Free access to clients, invoices, and time tracking directly inside the admin workspace

**Branded client portal**

- Frontend portal shortcode: `[agency_os_ai_portal]`
- Branded login screen shortcode: `[agency_os_ai_login]`
- Client role (aosai_client) and employee role (aosai_employee)
- Admin bar hiding and frontend-first redirect for portal users
- One-click creation of portal, login, and support pages
- PWA basics for an installable frontend portal experience
- Searchable ticket inbox with status and department filters
- Personal Kanban board for task status flow in the frontend portal

**Help desk and support tickets**

- Support ticket management with departments, ticket notes, and dynamic tags
- Department default assignee for automatic ticket routing
- AI-assisted department routing when an OpenAI key is added

**Email and connectivity**

- Built-in SMTP configuration: host, port, authentication, encryption, all from Settings
- Branded email notifications with customizable sender name, sender address, and footer
- Inbound email-to-ticket webhook endpoint (POST /aosai/v1/inbound/email) with token authentication for auto-creating tickets from forwarded emails
- Outbound webhook integrations with HMAC signature verification for ticket.created, task.created, project.created, and other key events

**AI tools**

- 🤖 OpenAI integration using your own API key
- AI playground for direct workspace queries
- AI-assisted ticket department routing baseline

**Company branding**

- Company name, logo URL, colors, and portal welcome copy from one settings screen
- Privacy policy and terms URL fields for frontend trust links
- Login activity and IP tracking controls for client and employee access audits

⚡ This makes Agency OS AI a strong fit if you want a **WordPress project manager, task manager, client portal, help desk, and AI agency management** foundation running on your own site, under your own domain, at no per-seat cost.

= Why teams choose it =

- Keep project delivery, client communication, files, and tickets together instead of scattered across tools
- Give clients and employees a cleaner branded frontend experience
- Reduce wp-admin clutter for non-admin users automatically
- Send reliable branded email directly from WordPress with built-in SMTP, no third-party plugin required
- Connect external tools through outbound webhooks without writing custom API layers
- Auto-create tickets from forwarded emails through the inbound webhook endpoint
- Control branding, sender identity, colors, and portal copy from one settings screen
- Start simple now and extend later through a developer-friendly hook architecture

= Shortcodes =

- `[agency_os_ai_portal]` loads the full frontend workspace
- `[agency_os_ai_portal view="tickets"]` opens the ticket-focused portal view
- `[agency_os_ai_login]` renders the branded login screen

= Extension-ready architecture =

Agency OS AI is built for honest growth. The free core is complete enough to run an agency today and open enough to extend tomorrow.

Developers can add modules, routes, integrations, or future premium layers through a documented hook surface without replacing the portal, settings, or branding systems already in place.

= 📊 Coming soon: Pro features for agencies who want an unfair advantage =

The free core covers the operational foundation. Agencies who want to scale faster are getting access to the Pro tier with features that go further:

**Advanced planning and scheduling**

- Advanced multi-project Kanban workflows with WIP controls and swimlanes
- Gantt chart planning for project timelines
- Calendar and workload planning across team members

**Billing and revenue**

- Payment collection workflows
- Stripe payment gateway integration
- Deeper billing automation and revenue reporting

**Deeper integrations**

- Multi-provider AI: Claude (Anthropic), Gemini (Google), DeepSeek, xAI Grok, OpenRouter
- Slack team notifications
- GitHub and Bitbucket development pipeline integrations
- BuddyPress community integration
- WooCommerce commerce integration

**Advanced reporting**

- Advanced data exports
- Executive-level reporting dashboards

Agencies who lock in early get access to Pro capabilities as they ship. Every new Pro feature is an additional tool in a single workspace you already own.

== Installation ==

1. Upload the `agency-os-ai` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the Plugins screen in WordPress.
3. Open `Agency OS AI` in wp-admin.
4. Go to `Workspace Settings` and add your company details, branding, and optional OpenAI key.
5. Configure your SMTP settings from the Settings screen for reliable email delivery.
6. Use `One-Click Page Creation` if you want instant portal, login, and support pages.
7. Invite employees and clients from the Team screen.
8. Optionally configure outbound webhooks or the inbound email endpoint for external integrations.

== Frequently Asked Questions ==

= Do I need my own API key to use Agency OS AI? =

No. The core project manager, client portal, task lists, milestones, messages, files, support tickets, SMTP email, webhooks, and reports all work without an OpenAI API key.

The OpenAI key is only required if you want to use the AI playground or AI-assisted ticket routing features. You bring your own key, and your data goes directly from your server to OpenAI without any intermediary.

= Will this plugin slow down my WordPress site? =

No. Agency OS AI loads its React admin bundle only on its own wp-admin workspace screen. The frontend portal bundle loads only on pages that use the portal shortcode. The rest of your WordPress site is unaffected.

= Is my client data secure inside this plugin? =

Yes. Agency OS AI uses WordPress roles, REST nonces, per-request capability checks, and sanitized database operations on every API route. Frontend portal access is role-aware, and non-admin portal users can be fully redirected away from wp-admin. Ticket access is scoped per user so clients only see their own tickets.

= Can I hide the WordPress admin bar for clients and employees? =

Yes. The Settings screen includes admin bar hiding and frontend-first redirect controls specifically for portal roles. Clients and employees using the portal never need to see wp-admin.

= Does the free version include a client portal? =

Yes. The free core includes a fully functional branded frontend portal, a branded login screen, and one-click page creation to get it all running in seconds. The portal is powered by shortcodes you place on any WordPress page.

= Can I use my own SMTP server for sending emails? =

Yes. Version 1.3.0 added a built-in SMTP configuration screen in Settings. You can set the host, port, authentication credentials, and encryption method without any additional plugin.

= Can the plugin create support tickets automatically from forwarded emails? =

Yes. The inbound email webhook endpoint (POST /aosai/v1/inbound/email) accepts forwarded email payloads and creates tickets automatically. You authenticate requests using a token you set in Settings.

= Can I trigger external tools when events happen inside Agency OS AI? =

Yes. Outbound webhooks fire on key events such as ticket.created, task.created, and project.created. Each request includes an HMAC signature so the receiving endpoint can verify the payload is genuine.

= Can I customize company branding, email sender, and portal copy? =

Yes. You can set the company name, logo URL, portal welcome copy, colors, support email address, sender name, sender email, and footer text from the Settings screen.

= Can this grow with my agency as it scales? =

Yes. The plugin ships a developer-friendly hook architecture. Developers can extend it with custom modules, new REST routes, or future Pro add-ons without touching the free core. The Pro tier adds Kanban, Gantt, time tracking, billing, advanced AI, and deeper integrations.

== External Services ==

Agency OS AI can connect to the OpenAI API for AI-related workspace features.

= What data is sent to OpenAI? =

- Text prompts you submit when using the AI playground
- Selected AI model name
- Related task, project, or ticket text, only when an AI feature explicitly needs that context

= When is data sent to OpenAI? =

Data is sent to OpenAI only after you add your own OpenAI API key in Settings and actively trigger an AI feature. No data is sent automatically or in the background.

= Service provider information =

- Provider: OpenAI, L.L.C.
- Terms of Service: https://openai.com/policies/business-terms/
- Privacy Policy: https://openai.com/policies/privacy-policy/

No other external services are contacted by the free core. Outbound webhooks fire to URLs you configure yourself and are under your full control.

== Changelog ==

= 1.5.0 =
- Added the 1.5.0 workspace expansion including workflow stages, email-template groundwork, login activity, and premium-ready clients, invoices, and time tracking modules.
- Fixed a critical REST bootstrap fatal by keeping premium-only controllers out of the free-core load path unless Pro is active.
- Corrected controller naming and request normalization for premium module routes so pro-enabled installs load them consistently.
- Improved admin route gating so premium screens show the upgrade path instead of exposing unfinished free-core navigation.
- Aligned plugin version metadata to 1.5.0 for WordPress, runtime assets, and package tooling.

= 1.4.3 =
- Added frontend portal task Kanban board with drag-and-drop status updates and quick status controls.
- Normalized task workflow states so older open and done values stay aligned with the Kanban board.
- Upgraded the portal task board with search, project filters, priority filters, and overdue workload context.
- Added login activity tracking model and API with IP and user-agent capture for access auditing.
- Added login activity controls and live audit feed in Settings > Automation.
- Hardened AI model resolution to always pass a valid fallback model before API requests.
- Hardened portal session IP resolution across common proxy headers.

= 1.4.1 =
- Guaranteed model fallback on every AI call to prevent "you must provide a model parameter" errors.
- Portal bootstrap now records last portal IP/time and returns session metadata for auditing.
- Version bump for build alignment.

= 1.4.0 =
- Added AI ticket assist in the frontend portal for summary, priority, department, suggested first reply, and tags
- Added AI team coach guidance in access management using live workspace data
- Added searchable mailbox-style portal ticket inbox with status and department filters
- Added richer client project visibility with task previews, milestone previews, and next milestone context
- Expanded client portal task visibility counts so client dashboards reflect project work more accurately

= 1.3.0 =
- Added built-in SMTP configuration for reliable email delivery from the Settings screen
- Added inbound email-to-ticket webhook endpoint for auto-ticket creation from forwarded emails
- Added outbound webhook integrations with HMAC signature verification for third-party event triggers
- Added department default assignee for automatic ticket assignment
- Fixed 404 errors on dynamically-imported JS assets (Vite base path rebuild)
- Fixed admin bar display on portal pages for administrator roles
- Bumped tested-up-to to 6.9

= 1.2.2 =
- Fixed critical black-screen bug caused by Vite chunk URLs using absolute paths; set base:./ for correct import resolution
- Fixed translation textdomain loading timing
- Updated tested-up-to to 6.9

= 1.2.0 =
- Added branded frontend portal and login shortcodes
- Added client and employee roles with frontend-first access
- Added one-click portal page creation
- Added support tickets, departments, ticket notes, dynamic tags
- Added company branding, portal branding, branded email sender settings
- Added animated logo preloaders and workspace branding
- Added team performance visibility in reports
- Added documentation HTML bundle

== Upgrade Notice ==

= 1.5.0 =
This release ships the 1.5.0 workspace foundation, fixes the premium REST bootstrap fatal, and aligns version metadata across the plugin package.

= 1.4.3 =
This release adds a stronger frontend Kanban workflow, login/IP activity auditing, and hardened AI model fallback handling.

= 1.4.1 =
Fixes AI model errors and adds portal session IP tracking. Update to keep AI features stable after saving settings.

= 1.4.0 =
This release adds AI ticket triage, AI team coaching, a searchable portal ticket inbox, and richer client project visibility in the frontend workspace.

= 1.3.0 =
This release adds built-in SMTP, inbound email-to-ticket webhooks, outbound event webhooks with HMAC verification, and department auto-assignment. Upgrade to get reliable email delivery and external integrations without additional plugins.

= 1.2.0 =
This release turns Agency OS AI into a fuller free-core workspace with branded portal access, support ticketing, richer settings, better reporting, and improved documentation.
