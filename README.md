# WordPress Auto External Media Downloader

A WordPress plugin that downloads media from configured external domains to your site's Media Library when you save or publish a post. The plugin then replaces the external media URLs in the post content with the local URLs of the newly downloaded images.

## Features

- Configure a list of allowed external domains in the WordPress admin.
- Automatically download supported media when saving a draft or published post.
- Add downloaded files to the Media Library and associate them with the post.
- Replace the corresponding external URLs in the post content with local URLs.
- Match allowed domains and their subdomains, including `www` variants.

Supported media file extensions are JPG, JPEG, PNG, GIF, SVG, WebP, MP3, WAV, OGG, M4A, MP4, WebM, MOV, and MKV.

## Installation

1. Download or clone this repository.
2. Upload the plugin folder to `/wp-content/plugins/` on your WordPress site.
3. In the WordPress admin, go to **Plugins** and activate **WP Auto External Media Downloader**.
4. Open **Settings → Auto Media Downloader**.
5. Add one allowed domain per line and save your settings.

You can also install the plugin by uploading its ZIP file from **Plugins → Add New Plugin → Upload Plugin**.

## Configuration

Enter hostnames only, one per line. For example:

```text
unsplash.com
pexels.com
cdn.example.com
```

The plugin normalizes `http://`, `https://`, and trailing slashes. It also treats `www.example.com` as `example.com` and allows subdomains of an allowed domain. Do not include a path or URL parameters.

![Configuration screen](configuration.heic)

## How it works

When a user with permission to edit a post saves it, the plugin checks the post content for media URLs in image, audio, video, and source elements, as well as direct links to supported audio and video files. It processes only external URLs whose hostname matches an allowed domain and whose file path has a supported media extension.

Successfully downloaded files are added to the WordPress Media Library and the matching URLs in the post content are replaced with their local attachment URLs. Autosaves and posts that are neither drafts nor published are skipped.

## Requirements

- WordPress 6.0 or later

## License

This plugin is licensed under the GNU General Public License, version 3. See the [GNU GPL v3](https://www.gnu.org/licenses/gpl-3.0.html).
