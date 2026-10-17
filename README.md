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
