# BASE3 WordPress bridge: processing and safeguards

This document records behavior verified from the accompanying source. It is
not a legal privacy notice or a statement of deployment-wide compliance.

The generic AJAX bridge dispatches requests into BASE3 components. For the
FileManager, the component requires `system/admin` authorization through
`IUsermanager`, a valid WordPress admin-display nonce, and a current user ID.
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
