<p align="center">
  <a href="https://codefreex.com/agency-os-ai" target="_blank">
    <img src="assets/branding/logo_agency_os_ai.svg" alt="Agency OS AI Logo" width="400">
  </a>
</p>

<h1 align="center">Agency OS AI</h1>

<p align="center">
  AI-powered WordPress project manager with a branded client portal, employee workspace, support tickets, built-in SMTP, inbound and outbound webhooks, and OpenAI-powered workflow tools.
</p>

<p align="center">
  <img src="https://img.shields.io/wordpress/plugin/v/agency-os-ai.svg?style=flat-square" alt="WordPress Plugin Version">
  <img src="https://img.shields.io/wordpress/plugin/dt/agency-os-ai.svg?style=flat-square" alt="WordPress Plugin Downloads">
  <img src="https://img.shields.io/wordpress/plugin/rating/agency-os-ai.svg?style=flat-square" alt="WordPress Plugin Rating">
  <img src="https://img.shields.io/github/license/codefreex/agency-os-ai?style=flat-square" alt="License">
</p>

## Why Agency OS AI exists

Agencies should not need five separate SaaS tools just to manage delivery, support, files, client communication, and internal coordination.

Agency OS AI brings those core workflows into WordPress with a modern React admin, a branded frontend portal for clients and employees, reliable built-in SMTP email delivery, inbound and outbound webhook connectivity, and a free-core architecture that is honest about what ships today while staying ready for future add-ons.

## What is included in free core

**Project and task management**

- Projects, task lists, tasks, milestones, messages, files, reports, profile, team, and settings
- Branded React admin workspace with updated header logo and animated preloaders
- Team performance visibility in the reports screen

**Branded client portal**

- Frontend portal shortcode: `[agency_os_ai_portal]`
- Frontend login shortcode: `[agency_os_ai_login]`
- Client role (`aosai_client`) and employee role (`aosai_employee`)
- Admin bar hiding and frontend-first redirects for portal users
- One-click page creation for login, portal, and support pages
- PWA basics for an installable frontend portal experience
- Searchable ticket inbox with status and department filters
- Rich project visibility cards with task previews, milestone previews, and next-milestone insight

**Help desk and support tickets**

- Support tickets, departments, ticket notes, and reusable dynamic tags
- Department default assignee for automatic ticket routing on new submissions
- AI-assisted department routing baseline when an OpenAI key is present
- AI ticket assist for summary, routing, priority, suggested first reply, and tags

**Email, automation, and support operations**

- Built-in SMTP configuration: host, port, authentication, encryption, all from the Settings screen
- Branded email notifications with customizable sender name, sender address, and footer text
- Inbound email-to-ticket webhook endpoint (`POST /aosai/v1/inbound/email`) with token authentication for auto-creating tickets from forwarded emails
- Outbound webhook integrations with HMAC signature verification firing on `ticket.created`, `task.created`, `project.created`, and other key events

**AI tools**

- OpenAI integration using your own API key
- AI playground for direct workspace queries
- AI-assisted ticket department routing
- AI ticket triage assist in the portal
- AI productivity brief in reports
- AI team coach brief in access management

**Company branding**

- Company name, logo URL, colors, and portal welcome copy
- Privacy policy and terms URL fields for branded frontend trust links
- Extension-ready email hooks for future provider-specific delivery layers

## Shortcodes

| Shortcode | Purpose |
|---|---|
| `[agency_os_ai_portal]` | Full frontend workspace |
| `[agency_os_ai_portal view="tickets"]` | Ticket-focused portal view |
| `[agency_os_ai_login]` | Branded login screen |

## Documentation

For comprehensive documentation, visit [themefreex.com/doc/agency-os-ai](https://themefreex.com/doc/agency-os-ai).

## Development

### Requirements

- WordPress 6.4+
- PHP 8.0+
- Node.js 18+

### Install dependencies

```bash
npm install
```

If you hit an `ERESOLVE` conflict, use:

```bash
npm install --legacy-peer-deps
```

### Typecheck

```bash
npm run typecheck
```

### Build the admin and portal bundles

```bash
npm run build
```

## Architecture snapshot

```text
agency-os-ai/
|- agency-os-ai.php
|- includes/
|  |- api/
|  |- admin/
|  |- frontend/
|  |- helpers/
|  |- models/
|  `- services/
|- src/
|  |- admin/
|  `- portal/
|- templates/
`- build/
```

## Extension philosophy

The free plugin should feel complete and valuable on its own.

If you build add-ons, the best path is to extend the free core through hooks, REST routes, isolated settings, and new asset bundles instead of replacing the portal, settings, or branding systems already in place.

Useful extension surfaces available in free core:

```php
apply_filters( 'aosai_admin_js_data', $data );
do_action( 'aosai_register_pro_routes' );
apply_filters( 'aosai_email_payload', $payload );
apply_filters( 'aosai_pre_send_email', null, $payload );
do_action( 'aosai_before_send_email', $payload );
do_action( 'aosai_after_send_email', $result, $payload );
```

## Branding

Agency OS AI is a product of [Themefreex](https://themefreex.com) by [Codefreex](https://codefreex.com).

The free core includes:

- Product branding in the portal login and workspace footer
- Policy URL settings for privacy and terms links
- Documentation pages with branded product footers

## Coming soon roadmap

These are planned add-on or future expansion areas, not features currently shipped in the free core:

- Kanban board views for visual task management
- Gantt chart planning for project timelines
- Calendar and workload planning across team members
- Time tracking and billing overlays
- Automated invoicing and payment workflows
- Stripe payment gateway integration
- Multi-provider AI: Claude (Anthropic), Gemini (Google), DeepSeek, xAI Grok, OpenRouter
- Slack team notifications
- GitHub and Bitbucket development pipeline integrations
- BuddyPress and WooCommerce integrations
- Advanced data exports and executive reporting dashboards

## Contributing

Contributions are welcome, especially in these areas:

- Stability improvements across real agency workflows
- Reporting and visualization polish
- Portal accessibility and mobile UX
- Documentation quality
- Extension patterns for future add-ons

When contributing, please keep the docs, README, and actual product behavior aligned.

## License

GPL-2.0-or-later

## Changelog

### 1.4.0 - 2026-03-31
- Added AI ticket assist in the frontend portal for summary, priority, department, suggested first reply, and tags
- Added AI team coach brief in access management for live team guidance
- Added searchable mailbox-style portal ticket inbox with status and department filters
- Added richer client project visibility with task previews, milestone previews, and next milestone context
- Expanded client portal task visibility counts so client dashboards reflect public project work more accurately

### 1.3.0 - 2026-03-31
- Added built-in SMTP configuration for reliable email delivery from the Settings screen (host, port, auth, encryption)
- Added inbound email-to-ticket webhook endpoint (`POST /aosai/v1/inbound/email`) with token authentication for auto-creating tickets from forwarded emails
- Added outbound webhook integrations with HMAC signature verification for `ticket.created`, `task.created`, `project.created`, and other key events
- Added department default assignee for automatic ticket assignment on new submissions
- Fixed 404 errors on dynamically-imported JS assets caused by incorrect Vite base path; rebuilt with correct relative base
- Fixed admin bar display on portal pages for administrator roles
- Bumped tested-up-to to 6.9

### 1.2.2 - 2026-03-31
- Fixed critical black-screen bug: Vite was building chunk URLs as absolute paths (`/assets/...`) causing 404s on any WordPress install. Set `base: './'` so dynamic imports resolve relative to the entry script via `import.meta.url`.
- Fixed translation textdomain loading too early: `load_plugin_textdomain` was registered on `plugins_loaded` inside code that itself runs on `plugins_loaded` (already fired), so translations never loaded. Moved hook to `init`.
- Updated tested-up-to to 6.9.

### 1.2.1 - 2026-03-30
- Fixed a bug that prevented the admin dashboard from loading due to missing JavaScript and CSS assets.
- Regenerated the build files to ensure all assets are up-to-date.
- Bumped the plugin version to 1.2.1.
- Updated documentation.
