=== Agency OS AI ===
Contributors: codefreex
Tags: project management, task management, client portal, help desk, ai assistant
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

AI-powered WordPress project manager with client portal, task manager, tickets, reports, and OpenAI workspace tools.

== Description ==

🚀 Running an agency inside WordPress should feel organized, fast, and profitable.

But most agency owners still juggle project updates in one tool, task management in another, client communication somewhere else, and support requests in a completely different system. That fragmentation costs time, causes missed details, and makes your delivery process feel messy.

It also means paying for multiple tools when your team really wants one clear operating system.

Agency OS AI solves that problem by turning WordPress into an AI-powered project management workspace for agencies, freelancers, internal teams, and service businesses.

With Agency OS AI, you can manage projects, tasks, files, support tickets, client access, employee access, and reporting from one branded workspace.

✨ The free version is generous and useful on its own.

You get:

- A modern React-powered WordPress project manager dashboard
- Project, task, task list, milestone, message, file, and report management
- A branded client portal and employee workspace powered by shortcodes
- Client and employee roles with frontend-first access and hidden admin bar support
- One-click page creation for login, portal, and support pages
- Support departments, ticket management, ticket notes, and dynamic tags
- Team access management from the Team screen
- OpenAI connection settings with AI test flow and AI-assisted routing baseline
- Branded email sender settings for notifications and ticket updates
- Privacy policy and terms URL settings for frontend trust links
- Team performance visibility inside the reports screen
- Portal PWA basics for installable frontend access

⚡ This makes Agency OS AI a strong fit if you want a WordPress project manager, task manager, client portal, and AI agency management foundation inside your own site.

= Why teams choose it =

- Keep project delivery, client communication, files, and tickets together
- Give clients and employees a cleaner frontend dashboard experience
- Reduce wp-admin clutter for non-admin users
- Control branding, sender identity, colors, and portal copy from one settings screen
- Start simple now and extend later through a developer-friendly architecture

= Shortcodes =

- `[agency_os_ai_portal]` loads the frontend workspace
- `[agency_os_ai_portal view="tickets"]` opens the ticket-focused portal view
- `[agency_os_ai_login]` renders the branded login screen

= Extension-ready architecture =

Agency OS AI does not pretend to ship everything today.

Instead, the free core is built so you can safely extend it later with more advanced workflows such as Gantt chart views, automated invoicing, deeper email delivery, time tracking, or additional AI model gateways like Claude or DeepSeek through separate add-ons or custom integrations.

Future expansion areas can include advanced Kanban, calendar planning, richer ticket automation, SMTP or IMAP delivery layers, time tracking, invoicing, exports, and broader AI provider support, but those should be shipped honestly as real modules rather than empty promises.


== Installation ==

1. Upload the `agency-os-ai` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the Plugins screen in WordPress.
3. Open `Agency OS AI` in wp-admin.
4. Go to `Workspace Settings` and add your company details, branding, and optional OpenAI key.
5. Use `One-Click Page Creation` if you want instant portal, login, and support pages.
6. Invite employees and clients from the Team screen.

== Frequently Asked Questions ==

= Do I need my own API key? =

Only for AI features. The core project manager, portal, tickets, files, settings, and reports work without an OpenAI key.

= Will this slow down my site? =

The plugin loads its React bundles only on its own workspace screens and shortcode-powered portal views. It is designed to keep the rest of your WordPress site unaffected.

= Is client data secure? =

Agency OS AI uses WordPress roles, REST nonces, capability checks, and sanitized database operations. Frontend access is role-aware, and non-admin portal users can be kept away from wp-admin entirely.

= Can I hide the WordPress admin bar for clients and employees? =

Yes. The free settings screen includes admin-bar hiding and frontend redirect controls for portal roles.

= Does it include a client portal in free? =

Yes. The free core includes a branded frontend portal, a branded login screen, and shortcode-based page creation support.

= Can I customize the email sender and company branding? =

Yes. You can set the company details, logo URL, portal copy, colors, support email, sender name, sender email, and footer text from the settings screen.

= Can this grow with my agency later? =

Yes. The free plugin is built to be extended. Developers can add modules, routes, integrations, or future premium layers without replacing the free-core portal and workspace foundation.

== External Services ==

Agency OS AI can connect to OpenAI for AI-related workspace features.

What data is sent:

- Text prompts you submit for AI actions
- Selected model information
- Related task, project, or ticket text only when an AI feature needs that context

When data is sent:

- Only after you add your own OpenAI API key and actively use an AI feature

Service provider:

- OpenAI
- Terms: https://openai.com/policies/business-terms/
- Privacy Policy: https://openai.com/policies/privacy-policy/
== Changelog ==

= 1.2.2 =

- Fixed critical black-screen bug caused by Vite building chunk URLs as absolute paths (/assets/...) instead of plugin-relative paths; set base: './' so dynamic imports resolve correctly via import.meta.url
- Fixed translation textdomain loading too early; moved load_plugin_textdomain from plugins_loaded (already fired) to init hook
- Updated tested-up-to to 6.9

= 1.2.0 =

- Added branded frontend portal and frontend login shortcodes
- Added client and employee roles with frontend-first access controls
- Added one-click portal page creation
- Added support tickets, departments, ticket notes, and dynamic tags
- Added company identity, portal branding, and branded email sender settings
- Added animated logo preloaders and updated workspace branding assets
- Added team performance visibility to reports
- Added documentation HTML bundle for users and developers
- Improved REST response alignment, admin flows, and portal consistency

== Upgrade Notice ==

= 1.2.0 =

This release turns Agency OS AI into a fuller free-core workspace with branded portal access, support ticketing, richer settings, better reporting, and improved documentation.


