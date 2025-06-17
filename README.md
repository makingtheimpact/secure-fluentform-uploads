# Secure FluentForm Uploads

A WordPress plugin that enhances FluentForm upload security by implementing private storage, encryption, and secure access controls.

## Features

- 🔒 Moves uploads to a private directory outside web root
- 🔐 Encrypts file metadata and content using AES-256-CBC, stored with a .php extension
- 👮‍♂️ Implements secure file access controls
- 👤 Admin-only access to uploaded files
- 🧹 Automatic file cleanup
- 📝 Configurable file type restrictions
- ⏱️ Link expiry for secure file sharing
- 📊 Access logging

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- OpenSSL PHP extension
- FluentForm plugin

## Installation

1. Upload the plugin files to `/wp-content/plugins/secure-fluentform-uploads`
2. Activate the plugin through WordPress admin
3. Configure settings at Settings → Secure FF Uploads
4. Ensure OpenSSL PHP extension is installed

## Security Features

- AES-256-CBC encryption for files and metadata
- Secure random file naming
- Protection against direct file access
- MIME type validation
- IP-based access logging
- Configurable file expiry
- Automatic cleanup of expired files
- Files stored with .php extension to prevent execution

## Configuration

The plugin can be configured through the WordPress admin panel:

- Set custom upload directory
- Configure allowed file types
- Configure download link expiry (timestamps are stored to enforce expiration)
- Enable/disable automatic cleanup
- View access logs

## Support

For support, please [open an issue](https://github.com/makingtheimpact/secure-fluentform-uploads/issues) on GitHub.

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed by [Making The Impact LLC](https://makingtheimpact.com) 