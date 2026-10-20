# BASE3 WordPress bridge: processing and safeguards

This document records behavior verified from the accompanying source. It is
not a legal privacy notice or a statement of deployment-wide compliance.

The generic AJAX bridge dispatches requests into BASE3 components. For the
FileManager, the component requires `system/admin` authorization through
`IAuthorizationService`, a valid WordPress admin-display nonce, and a current user ID.
This ID is used to associate upload sessions with their initiating user.
Uploads and downloaded file contents may contain personal data supplied by
administrators. The bridge does not add a new third-party transfer.

`WordPressManagedFileStorageService` stores managed files under
`DIR_LOCAL/storage/<mode>/<owner-hash>/`. `WordPressFileUploadService` uses
`DIR_LOCAL/filemanager-upload/<owner-hash>/<upload-id>/` for upload-session
metadata and chunks. The default `DIR_LOCAL` is
`WP_CONTENT_DIR/base3-private/`, unless the application configures
`BASE3_WORDPRESS_DATA_DIR`. Files can be deleted through FileManager operations;
the code does not establish a general retention schedule for all stored data.

`Runtime::prepareEnvironment()` writes an Apache `.htaccess` denial and an
`index.php` guard for the default private directory. These do not establish
Nginx access protection. Deployment operators must separately verify that
private directories and backup copies cannot be served publicly, and define
retention, deletion, backup, and data-subject-request procedures.

The controller, hosting region, external recipients, encryption arrangements,
and applicable legal bases depend on the deployment and have not been verified
from this repository alone. The change documented here only consolidates the
FileManager AJAX route; it does not expand the data or storage model.

## Project global chatbot launcher

`Base3WordPressLab` displays the global launcher on normal frontend and admin
pages through WordPress hooks. The frontend now uses the single ClientStack
`GlobalChatbotLauncher` module and CSS, not a project-owned iframe launcher.
On page load the shared ES module creates the button and panel; **only on open**
does it fetch the existing BASE3 `chatbotdisplay` response through the generic,
same-origin `base3_dispatch` endpoint. The render URL includes the configured
identity `chatbot/default`, not chatbot settings, credentials or message text.
The returned chatbot markup and scripts execute in the same WordPress page DOM;
this differs from the former isolated same-origin iframe. The endpoint and
content are trusted first-party BASE3 output and are governed by the existing
host authentication and output contracts.

`GlobalChatbotLauncherSettings` reads the shared presentation configuration from
`ISettingsStore` under `global-chatbot-launcher/default`. It may persist enabled
window modes, resize options and navigation reopening preferences. The shared
browser launcher uses `localStorage` for last mode and panel sizes and
`sessionStorage` for reopening an open panel across navigation; no chat messages
are stored by the launcher. Deletion of these browser values follows normal
browser storage controls; deleting the server-side launcher settings does not
necessarily clear existing per-browser state.

The globally embedded chatbot still uses the `chatbot/default` configuration
from the administration form. The existing Chatbot/Assistant modules are
responsible for message processing, conversational state, storage and any
external provider requests. Providers, hosting regions, legal bases, retention,
backups and access limits have not been verified for the deployment.

The button remains visible on public pages to guests, as in the previous
integration. The code does not itself grant anonymous access to AI providers.
Operators must check actual permissions, consent/ePrivacy requirements, provider
contracts, session persistence, security, retention and accessibility with the
installed WordPress theme and operating environment. The source does not prove
GDPR compliance or WCAG conformance in production.

## WordPress administrator bar and docked launcher

When the shared global chatbot sidebar is open, the WordPress integration
provides `#wpadminbar` as the host header. ClientStack temporarily adjusts
its inline layout on desktop so the header ends at the sidebar. The panel is displayed
above the administration bar. The previous header styles are restored on
close. This introduces no new request, tracking, storage or data recipient.

## BASE3 authorization and administrative audiences (2026-10-19)

The WordPress bridge now registers one `IAuthorizationService` authority (`wordpress`)
that delegates global and resource meta-capability decisions to WordPress native
capability functions. The framework feature checks evaluate code-discovered
`IFeature` audiences against effective BASE3 groups and `IUserCriterion`s.
WordPress user IDs, login/email identity attributes, native role slugs, configured
group names/descriptions and installed criterion data may be read in memory.
The host-owned BASE3 settings datasets are `base3wordpress/groups` and
`base3_authorization_features/catalog`. These settings use the already active
WordPress database-backed `ISettingsStore`, not additional WordPress accounts
or a new BASE3 permission table.

Administrative BASE3 displays reached through WordPress AJAX require native
`manage_options`, developer membership for advanced tools, and the same-origin
WordPress nonce before display instantiation. The shared ClientStack Groups and
Features displays also enforce `system/admin` and the WordPress adapter's
explicit development membership check. Their writes use per-display BASE3
session CSRF tokens; `WordPressSession` binds those tokens to the trusted WP
login session. The former WordPress-only audience form/assets are removed from
the administration page; stored rules and WordPress native permissions are not
rewritten. Public chatbot outputs and feature-gated games retain
explicitly separate visibility; this integration does not grant anonymous users
administrative access. Code-defined developer accounts can be configured as
exact native logins (or complete verified addresses) in `wp-config.php` and must
be revoked there. Browser-side accessibility of controls, configured native
WordPress permissions, private data directory protection (especially Nginx),
retention/deletion, backups, logging and legal notices remain deployment-specific
and were not verified from the uploaded sources.
