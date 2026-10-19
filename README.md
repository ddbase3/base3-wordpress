# BASE3 WordPress bridge

The WordPress `admin-ajax.php` transport uses a single BASE3 action,
`base3_dispatch`, to resolve an `IDisplay` or `IOutput` through the existing
`IClassMap`. WordPress reserves the `action` parameter; `WordPressRequest`
continues to translate a BASE3 `action` value through `base3_action`.

The FileManager does not have a separate WordPress AJAX command. The
`Base3WordPressFileManagerOutput` component is discovered via `IOutput` and
calls the existing `WordPressFileManagerHttpService`. Its endpoints are created
by `Base3WordPressLabSettings` through `ILinkTargetService`, preserving all
13 ClientStack operations and the `chatbot/default` storage owner.

The output requires a BASE3 `system/admin` permission, a valid WordPress
`base3_admin_display` nonce, and an authenticated user identity before any
file operation. WordPress' native authentication and capability mapping remain
in the existing host adapter. The underlying storage and chunk-upload services
are unchanged.

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
module location. On initial load it enqueues the shared CSS and assetloader;
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

**The launcher remains visible to anonymous site visitors** as before. This is
not an authorization decision; the existing BASE3 services determine whether
chat operations can run. Verify public access, model cost limits, session
handling and provider settings before production use. Also test the five window
modes across frontend themes and WordPress administration, particularly the
sidebar (which docks to `body`).

Remove the obsolete project-owned JS/CSS after deploying the new implementation:

```bash
sh ./remove-obsolete-wordpress-chatbot-launcher-assets.sh
```

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
authoritative for WordPress resources. WordPress administrators see reduced
BASE3 Tools navigation; only explicit membership in BASE3 `development` enables
the complete catalog and the Groups/Features configuration displays.

**Before installing**: configure the initial project-maintainer login in
`wp-config.php` as
`define('BASE3_WORDPRESS_DEVELOPER_USERNAMES', ['your-actual-wp-login']);`.
That account must also have native WordPress `manage_options` to edit the
settings. Never automatically assign `development` to every administrator.

Features can use BASE3 groups, WordPress users, WordPress native roles and
schema-backed criteria. See
[WordPress authorization guide](modules/Base3WordPress/docs/authorization-wordpress.md)
for runtime boundaries, single-site persistence, bootstrap, nonce protection,
read-only native rights metadata and regression tests. Anonymous global chatbot
embedding continues to use the existing generic dispatcher; its public access
and provider usage require independent review.
