# Agency OS AI

AI-powered WordPress project manager with a branded client portal, employee workspace, support tickets, reports, dynamic tags, and OpenAI-powered workflow tools.

## Why Agency OS AI exists

Agencies should not need five separate SaaS tools just to manage delivery, support, files, client communication, and internal coordination.

Agency OS AI brings those core workflows into WordPress with a modern React admin, a frontend portal for clients and employees, and a free-core architecture that is honest about what ships today while staying ready for future add-ons.

## What is included in free core

- Projects, task lists, tasks, milestones, messages, files, reports, profile, team, and settings
- Branded React admin workspace with the updated header logo and animated preloaders
- Frontend portal shortcode: `[agency_os_ai_portal]`
- Frontend login shortcode: `[agency_os_ai_login]`
- Client and employee roles with admin-bar hiding and frontend-first redirects
- One-click page creation for login, portal, and support pages
- Support tickets, departments, ticket notes, and reusable dynamic tags
- Company branding controls for colors, company details, sender identity, and footer credit behavior
- Privacy policy and terms URL settings for branded frontend trust links
- OpenAI connection settings, AI test flow, and AI routing baseline for ticket departments
- Team performance visibility in reports
- PWA basics for the frontend portal
- Extension-ready email hooks for future SMTP, IMAP, or provider-specific delivery layers

## Product positioning

Agency OS AI is building toward a serious open source WordPress project manager and AI agency management platform.

Today, the free core already covers the operational baseline most teams need:

- task manager workflows inside WordPress
- a clean client portal experience
- employee dashboard access without wp-admin clutter
- support tickets and communication history in one place
- OpenAI-assisted agency operations

## Shortcodes

- `[agency_os_ai_portal]`
- `[agency_os_ai_portal view="tickets"]`
- `[agency_os_ai_login]`

## Documentation

Manual-upload HTML docs are included in [`docs/`](./docs):

- [`docs/doc.html`](./docs/doc.html)
- [`docs/userdoc.html`](./docs/userdoc.html)
- [`docs/developer-doc.html`](./docs/developer-doc.html)
- [`docs/addingfeatures.html`](./docs/addingfeatures.html)
- [`docs/buildingaddon.html`](./docs/buildingaddon.html)

These files are prepared for publishing at `themenfreex.com/docs/agency-os-ai/`.

## Development

### Requirements

- WordPress 6.4+
- PHP 8.0+
- Node.js 18+

### Install dependencies

```bash
npm install
```

### Typecheck

```bash
npm run typecheck
```

### Build the admin + portal bundles

```bash
npm run build
```

## Troubleshooting

### Dependency Issues

If you encounter an `ERESOLVE` error when running `npm install`, it may be due to a conflict between the Vite version and other dependencies. You can usually resolve this by running the installation with the `--legacy-peer-deps` flag:

```bash
npm install --legacy-peer-deps
```

## Architecture snapshot

```text
agency-os-ai/
├─ agency-os-ai.php
├─ includes/
│  ├─ api/
│  ├─ admin/
│  ├─ frontend/
│  ├─ helpers/
│  ├─ models/
│  └─ services/
├─ src/
│  ├─ admin/
│  └─ portal/
├─ templates/
├─ docs/
└─ build/
```

## Extension philosophy

The free plugin should feel complete and valuable on its own.

If you build add-ons later, the best path is to extend the free core through hooks, routes, isolated settings, and new asset bundles instead of replacing the portal, settings, or branding systems already in place.

Useful extension surfaces already available in free core include:

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

The live free-core product now includes:

- product branding in the portal login and workspace footer
- policy URL settings for privacy and terms links
- documentation pages with branded product footers

## Coming soon roadmap

These are planned add-on or future expansion areas, not features currently shipped in the free core:

- advanced Kanban and timeline views
- Gantt chart planning
- calendar and workload planning
- deeper ticket automations and SLA workflows
- SMTP, IMAP, Gmail, and provider-specific mail transports
- time tracking and billing overlays
- invoicing and payment workflows
- advanced exports and executive reporting
- multi-provider AI gateways such as Claude, DeepSeek, Gemini, and other specialist models
- richer client success and account-management modules

## Contributing

Contributions are welcome, especially in these areas:

- stability improvements across real agency workflows
- reporting and visualization polish
- portal accessibility and mobile UX
- documentation quality
- extension patterns for future add-ons

When contributing, please keep the docs, README, and actual product behavior aligned.

## License

GPL-2.0-or-later

## Changelog

### 1.2.1 - 2026-03-30
- Fixed a bug that prevented the admin dashboard from loading due to missing JavaScript and CSS assets.
- Regenerated the build files to ensure all assets are up-to-date.
- Bumped the plugin version to 1.2.1.
- Updated documentation.

