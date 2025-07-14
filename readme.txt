=== Secure FluentForm Uploads ===
Contributors: makingtheimpact
Tags: fluentform, security, uploads, encryption, private-files
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Enhances FluentForm upload security by moving files to a private directory, encrypting metadata, and implementing secure access controls.

== Description ==

Secure FluentForm Uploads is a security enhancement plugin that works with FluentForm to provide additional protection for uploaded files. It implements several security measures to ensure your form uploads remain private and secure.

= Key Features =

* Moves uploaded files to a private directory outside the web root
* Encrypts file metadata and content, stored with a .php extension
* Implements secure file access controls
* Provides admin-only access to uploaded files
* Includes automatic file cleanup
* Supports configurable file type restrictions
* Configurable download link expiry with server-side checks (links are timestamped)
* Logs all file access attempts

= Security Features =

* File encryption using AES-256-CBC
* Secure random file naming
* Protection against direct file access
* MIME type validation
* IP-based access logging
* Configurable file expiry
* Automatic cleanup of expired files
* Files stored with .php extension to prevent execution

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/secure-fluentform-uploads` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Use the Settings->Secure FF Uploads screen to configure the plugin
4. Ensure your server has the OpenSSL PHP extension installed

== Frequently Asked Questions ==

= Does this plugin require FluentForm? =

Yes, this plugin is designed to work specifically with FluentForm and requires it to be installed and activated.

= What file types are supported? =

The plugin supports a wide range of file types including images, documents, audio, video, and archives. You can configure allowed file types in the plugin settings.

= How secure is the file encryption? =

The plugin uses AES-256-CBC encryption for both file content and metadata, with unique encryption keys generated for each installation. Files are stored with a .enc extension to prevent execution.

== Screenshots ==

1. Plugin settings page
2. File access management
3. Security configuration options

== Changelog ==

= 1.0.3 =
* Allow pages to load inside iframes by using SAMEORIGIN frame options

= 1.0 =
* Initial release

== Upgrade Notice ==

= 1.0 =
Initial release
