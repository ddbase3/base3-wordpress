# BASE3 modules distributed by native WordPress plugins

One BASE3 runtime is shared across active WordPress plugins. The BASE3 class map
uses `base3.json` manifests found in module directories, including modules
provided by other active native WordPress plugins.

## Provider layout

```text
wp-content/plugins/example-wordpress-extension/
  example-wordpress-extension.php
  modules/
    ExampleModule/
      base3.json
      src/
      assets/
      tpl/
```

A WordPress plugin that requires the native BASE3 host integration may declare:

```php
/**
 * Plugin Name: Example BASE3 Extension
 * Requires Plugins: base3-wordpress
 */
```

The registry rejects duplicate module names or namespaces, missing manifest
dependencies, and incompatible versions. It never chooses between duplicates.
The physical location of a module is used for its assets and templates, while
its logical identity is taken from `base3.json`.

Modules are ordinary BASE3 packages. Their code does not register a second
container, bootstrap or independent class map. The WordPress integration never
requires specialized code for a particular BASE3 module.

## UI and HTTP

The native WordPress plugin provides a generic `base3_dispatch` HTTP endpoint.
A BASE3 output's `getOutput()` is responsible for deciding whether it may return
content or perform actions for the current requester. There is no centralized
allow-list of public `IOutput` or `IDisplay` implementations.

The project module `Base3WordPressLab` supplies the BASE3 administration tab
configuration for the existing ClientStack `tabcontroldisplay`, rendered under
**BASE3 > Werkzeuge**. Frontend placement uses the `base3/display` Gutenberg
block or the `[base3 name="..."]` shortcode.

## Git ownership

The host integration repository `base3-wordpress` tracks its own PHP, assets,
documentation and `modules/.gitignore`. The actual contents of `modules/` are
installed and updated through separate repositories, including `Base3Framework`,
`Base3WordPress`, `Base3WordPressLab` and all other installed modules.

Never force-add those module directories to the host integration repository.
Avoid WordPress plugin reinstallations and updates that replace the entire
`base3-wordpress/` directory, because that can remove independently installed
modules even though Git ignores them.
