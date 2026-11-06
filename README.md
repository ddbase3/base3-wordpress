# BASE3 WordPress bridge

The WordPress `admin-ajax.php` transport uses a single BASE3 action,
`base3_dispatch`, to resolve an `IDisplay` or `IOutput` through the existing
`IClassMap`. WordPress reserves the `action` parameter; `WordPressRequest`
continues to translate a BASE3 `action` value through `base3_action`.

The FileManager uses the existing WordPress `base3_dispatch` HTTP transport.
`Base3WordPressFileManagerOutput` authorizes native `system/admin`, WordPress
`base3_admin_display` nonce and an authenticated user before delegating the
13 actions to the canonical `ClientStack\FileManager\FileManagerHttpService`.
`Base3WordPress` composes the canonical `FileUploadService` with the unchanged
WordPress `IManagedFileStorageService` and `DIR_LOCAL/filemanager-upload/`.
`Base3WordPressLab` owns `chatbot/default` configuration and supplies the
canonical display data to discoverable `ChatbotconfigSettingsTab`.
No FileBridge plugin is required, and no stored resource is moved.

For a standalone, isolated dispatch test (without a WordPress runtime):

```bash
php wp-content/plugins/base3-wordpress/modules/Base3WordPress/test/Adapter/Base3WordPressFileManagerDispatchSmokeTest.php
```

This test checks endpoint generation and dispatch boundaries using stubs. It
does **not** replace an integration check of upload, download, and HTTP status
codes in an installed WordPress instance.

## Global WordPress chatbot

`Base3WordPressLab` injects the **single shared ClientStack
GlobalChatbotLauncher ES module** on frontend pages (`wp_footer`) and WordPress
admin pages (`admin_footer`), provided the host renders its normal footer
hooks. The project renders only the hidden module configuration element and a
`<script type="module">` reference to
`ClientStack/assets/globalchatbotlauncher/index.js`. Styles, window controls,
keyboard resizing and navigation restoration come from the matching ClientStack
assets; the old WordPress-specific launcher JS/CSS is no longer used.

After pulling ClientStack to `wp-content/plugins/base3-wordpress/modules/ClientStack/`,
its `assets/globalchatbotlauncher/` directory must exist and contain the
published `dev/GlobalChatbotLauncher/src/` files. Pull **before** installing
this patch. WordPress' `IAssetResolver` resolves module assets from their actual
module location. On initial load it enqueues the shared assetloader and renders the ClientStack CSS reference;
opening the button fetches the existing `chatbotdisplay` output through the
normal `base3_dispatch` action. No new AJAX action or display implementation is
created. The shared module inserts the resulting display markup and scripts into
the page, rather than using an iframe.

The global identity is **`chatbot/default`**, managed by
**BASE3 -> Tools -> Chatbot -> Configuration**. The adjacent **Launcher** tab
uses the existing `globalchatbotlauncherconfigdisplay` from ClientStack,
persisting shared presentation flags under `global-chatbot-launcher/default`:
floating window, dialog, sidebar, full window, fullscreen, resizing, selected
mode and reopening behaviour. These are presentation-only settings; the chatbot
backend and configuration continue to belong to `chatbot/default`.

Content-embedded chatbots remain independent and can use their own
`config_group` / `config_name`, for example:

```text
[base3 name="chatbotdisplay" data='{"config_group":"content","config_name":"support"}']
```

The shortcode does not create a configuration form; the relevant owner must
provide one for each separate content chatbot identity. The global administration
form is not applied to them.

**The global floating launcher is a configurable BASE3 feature**:
`ClientStack\GlobalChatbotLauncher\GlobalChatbotLauncherFeature` is the sole owner
of `feature/globalchatbotlauncherfeature`. Its default audience is `authenticated`.
Previous WordPressLab audience overrides are not migrated on this demo site.
Operators can change the audience in BASE3 > Tools > BASE3 > Features; a stored
empty audience denies every visitor. The project checks `IAuthorizationService`
before loading launcher assets or rendering its footer markup. The same project
component registers an access guard with the **existing**
`wp_ajax_base3_dispatch` and `wp_ajax_nopriv_base3_dispatch` WordPress hooks
(priority 0), before the shared dispatcher. It checks the decoded
`chatbotdisplay` identity `chatbot/default` and returns HTTP 403 when access is
denied, using the dispatcher's existing data decoder. No new AJAX action,
route, service or permission subsystem is introduced.

Blocks and `[base3 ...]` shortcodes are still rendered directly by
`BlockBridge::render()` and are not governed by this launcher feature, even
when explicitly configured with `chatbot/default`. The generic conversation
and agent endpoints remain unchanged. The new feature controls **only** the
floating-launcher UI and the AJAX display-load request for its configured
identity, not a general chatbot-service access policy. Verify feature audience
behavior for logged-in and anonymous visitors, public provider limits,
session handling and the five window modes in the actual WordPress deployment.

Remove all replaced static catalog classes, WordPress duplicate upload/HTTP
services and old WordPress launcher assets after extracting the patch:

```sh
sh scripts/remove-obsolete-wordpress-integration.sh
```

This script does not touch `wp-content/base3-private` or any uploaded data.

Isolated project tests (ClientStack checkout required):

```bash
php wp-content/plugins/base3-wordpress/modules/Base3WordPressLab/test/GlobalChatbotSmokeTest.php
node wp-content/plugins/base3-wordpress/modules/Base3WordPressLab/test/GlobalChatbotDomSmokeTest.js
```

These checks validate composition, URLs, settings flags and ES-module exports,
but do not replace real WordPress/browser integration tests.

## Single-site BASE3 authorization (WordPress)

The WordPress integration uses the shared BASE3 `IAuthorizationService` and
`UserCriterionEvaluator`; it does not retain a separate `IUsermanager::can()`
permission engine. Native WordPress capabilities and meta-capabilities remain
authoritative for WordPress resources. The WordPress project grants BASE3 `development` membership to accounts with
native `manage_options`, so site administrators can reach the Groups/Features
configuration without a separate bootstrap allowlist. Optional exact logins or
verified email addresses may extend membership, but cannot replace WordPress's
native administration decision for protected operations.

Features can use BASE3 groups, WordPress users, WordPress native roles and
schema-backed criteria. See
[WordPress authorization guide](modules/Base3WordPress/docs/authorization-wordpress.md)
for runtime boundaries, single-site persistence, bootstrap, nonce protection,
read-only native rights metadata and regression tests. Anonymous global chatbot
embedding continues to use the existing generic dispatcher; its public access
and provider usage require independent review.

## Shared authorization administration (WordPress)

The WordPress project uses classmap-discovered `UiFoundation\Api\ISettingsTab`
contributions and the existing `ClientStack` displays `authorizationgroupsdisplay`,
`authorizationfeaturesdisplay` and `authorizationadministrationdisplay`.
`Base3WordPress` supplies the host-owned audience and native authorization
services; the native role/capability viewer remains read-only. The old
WordPress-specific audience display/assets are removed by the accompanying
cleanup script, not replaced with another WordPress template.

Use updated `ClientStack` (commit `a0fe672`) and `UiFoundation` (commit
`83cc937`) with this integration. The installed manifest versions alone do
not distinguish those new October 2026 interface/display commits.

After extracting the patch in the `base3-wordpress` plugin root, run
`sh scripts/remove-obsolete-wordpress-integration.sh` and the
smoke tests in `modules/Base3WordPress/docs/authorization-wordpress.md`.
A native WordPress role slug is selected via `Criterion` >
`wordpressrolecriterion` in the unchanged shared UI; its numeric native role
field is not a WordPress role-slug selector.

## Dynamic administration after the 2026-11-06 integration

Native **BASE3 > Werkzeuge** renders `settingsdisplay`. The shared ClientStack
settings renderer enumerates the current `ISettingsTab` classmap; the project
no longer owns static lists of provider, chatbot, developer or game tabs.
Direct `base3_dispatch` display calls are protected by WordPress authorization
and a WordPress admin nonce, then the owning tab's existing feature decision.
Game entries remain independently embeddable. For the BASE3 feature editor,
native WordPress `manage_options` provides the project `development` membership;
no `wp-config.php` allowlist is required. Neither existing feature audiences nor
the former public launcher audience are migrated. The WordPress site is a single-site demo (tested version 7.1.4).

The two project modules have their own Git ownership. This patch updates the
bridge and their source trees, not ClientStack, Chatbot, UiFoundation or other
generic modules. Do not reset or delete independently installed generic modules.

## Native WordPress administrator settings regression (2026-11-06)

The WordPress host must not register its own `anonymous` and `authenticated`
`IGroup` definitions alongside the canonical `Base3Framework` definitions.
The resulting duplicate group names made WordPress authorization fail closed,
so the generic `SettingsDisplay` showed no available entries even for native
site administrators. `scripts/remove-obsolete-wordpress-integration.sh` removes
the former duplicate classes. The WordPress `administration` group and
project-owned `development` group remain, including the native `manage_options`
criterion. After extracting the patch, run the removal script before opening
the Tools page. The real-classmap regression test is at
`modules/Base3WordPress/test/Authorization/WordPressGroupDiscoverySmokeTest.php`.
