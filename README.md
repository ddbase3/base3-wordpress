# BASE3 WordPress

A native WordPress plugin that embeds BASE3 as one complete framework runtime.
It supplies the WordPress entry point, module discovery, lifecycle handling,
HTTP integration, admin integration, and the BASE3 class map.

This repository contains **only the WordPress host integration**. The framework,
foundation modules, project modules, and feature modules are installed separately
in `modules/`. Their repositories and versions remain independent.

## Directory layout

```text
base3-wordpress/
  base3-wordpress.php     Native WordPress entry point
  includes/              Native host lifecycle, HTTP, discovery and administration
  assets/                WordPress editor assets
  modules/
    .gitignore            Retains this directory without tracking installed modules
    Base3Framework/       Installed separately from its own repository
    Base3WordPress/       Installed separately from its own repository
    Base3WordPressLab/    Installed separately from its own repository
    ...                   Other independently installed BASE3 modules
```

`modules/.gitignore` ignores every immediate module directory, while tracking
`modules/.gitignore` itself. Do not force-add module sources, and do not use
`git clean -x` or destructive WordPress plugin replacements against an installation
containing separately managed modules.

Each BASE3 module must contain its own `base3.json`. Module identity, namespace,
version and dependencies come from the manifest, not the directory name.
Compatible modules may also be supplied by the `modules/` directory of another
active native WordPress plugin.

## Runtime and management

The plugin uses the existing BASE3 container, module registry, class map, hooks,
services and workers. WordPress supplies the outer plugin lifecycle, block,
shortcode, REST and AJAX endpoints. The `Base3WordPress` module supplies host
adapters; `Base3WordPressLab` selects the project services.

* WordPress admin: **BASE3 > BASE3** (module overview) and **BASE3 > Werkzeuge**.
* AJAX: `admin-ajax.php?action=base3_dispatch&name=<output>`.
* REST status: `/wp-json/base3/v1/status` (WordPress administrator access).
* Frontend: `[base3 name="exampledisplay"]` and the `base3/display` block.
* CLI: `wp base3 status`, `wp base3 rebuild`, `wp base3 worker`.
* Worker event: `base3_worker_tick`.

The generic `IOutput` and `IDisplay` HTTP entry points do not keep a centralized
public-output allow-list. Each output or display is responsible for checking
whether its own `getOutput()` may return content or perform actions for the
current requester. Admin menu pages have their own WordPress `manage_options`
checks.

The writable BASE3 runtime data path is separate from the plugin directory.
By default it is `WP_CONTENT_DIR/base3-private/`, or the path configured through
`BASE3_WORDPRESS_DATA_DIR`. Renaming the native WordPress plugin does not migrate
or reset that data.

## Installing and updating modules

Install the complete compatible BASE3 module set before activating the native
WordPress plugin. Clone or update each module through its own repository in
`modules/<ModuleDirectory>/`. All framework, foundation and feature module files
must remain unchanged by this repository.

Updates to the native WordPress integration should preserve every installed
module directory. The deployed ZIP patches contain only native integration files
and never overwrite `modules/<ModuleDirectory>/`.

WordPress plugin updates or reinstallations that replace the entire plugin
directory can delete independently deployed module directories. Use controlled
in-place updates that preserve `modules/` rather than a replacement installation.

## Renaming from BASE3 Bridge

For an existing WordPress site, deactivate the old `base3-bridge` plugin while
its original files still exist. Rename the directory from `base3-bridge/` to
`base3-wordpress/`, rename the main file from `base3-bridge.php` to
`base3-wordpress.php`, apply the rename patch from the WordPress installation
root, then activate `base3-wordpress`. Rebuild the class map and verify installed
modules and admin/frontend entry points. All installed module directories stay
where they are beneath the renamed directory.

The internal PHP namespace `Base3WordPressBridge` is deliberately unchanged;
this packaging rename does not modify public PHP class names or the separate
`Base3WordPress` and `Base3WordPressLab` BASE3 modules.

Licensed under GPL-3.0-or-later. The independently installed BASE3 modules retain
their own copyright and licensing terms.
