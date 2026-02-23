# Divi Dynamic Video

A WordPress plugin that registers a **Video Post** custom post type and a **Divi Builder Video Embed module** for displaying YouTube and Vimeo videos dynamically from post meta or via a manual URL override.

Designed for sharing podcast appearances, video content, or any curated video library on a Divi-powered site.

---

## Requirements

| Requirement | Minimum Version |
|---|---|
| WordPress | 6.0 |
| PHP | 8.0 |
| Divi Theme / Divi Builder | 4.x (current stable) |

---

## Installation

### From a release ZIP (recommended for end users)

1. Download the latest release ZIP from the [Releases page](../../releases).
2. In WordPress admin, go to **Plugins → Add New → Upload Plugin**.
3. Upload the ZIP and click **Install Now**, then **Activate**.
4. No build step required — compiled assets are included in the release.

### From source (developers)

```bash
git clone https://github.com/domkirby/divi-dynamic-video.git
cd divi-dynamic-video
yarn install
yarn build
```

Then copy or symlink the plugin directory into `wp-content/plugins/`, or zip it for upload.

> **Note:** Commit the `build/` directory to your repository so that end users who download a release ZIP do not need to run a build step.

---

## Plugin Structure

```
divi-video-post/
├── divi-video-post.php              # Main plugin bootstrap
├── CLAUDE.md                        # AI developer instructions
├── README.md
├── includes/
│   ├── class-video-post-cpt.php     # CPT, taxonomy, meta, admin meta box
│   └── class-divi-extension.php     # Divi Extension loader
├── modules/
│   └── VideoEmbed/
│       ├── VideoEmbed.php           # ET_Builder_Module PHP class
│       ├── VideoEmbed.jsx           # React component (builder UI preview)
│       └── style.css                # Frontend responsive embed styles
├── assets/
│   └── admin/
│       └── meta-box.css             # Admin meta box styles
└── build/
    └── divi-video-post.min.js       # Compiled JSX output (do not edit directly)
```

---

## Video Post Custom Post Type

A **Video Post** is a custom post type (`video_post`) used to store and manage individual video entries.

### Accessing Video Posts

- **Admin menu:** Videos (with a video camera icon)
- **Archive URL:** `/video-posts/`
- **Single URL:** `/video-posts/post-slug/`
- **REST API:** `wp-json/wp/v2/video_post`

### Meta Fields

Each Video Post stores three custom meta fields, managed via the **Video Details** meta box on the edit screen and accessible via the REST API:

| Meta Key | Type | Description |
|---|---|---|
| `_video_url` | `string` | Full YouTube or Vimeo URL |
| `_video_thumbnail` | `integer` | Attachment ID for a custom poster/thumbnail image |
| `_video_description` | `string` | Optional description text |

#### Video Details Meta Box

The **Video Details** meta box appears below the editor on every Video Post edit screen and provides:

- **Video URL** — a URL input field. Accepts any supported YouTube or Vimeo URL format (see below).
- **Video Thumbnail** — a media picker button that opens the WordPress Media Library. The selected image is stored as an attachment ID and used as the video poster image in the embed module.
- **Video Description** — a plain text area for an optional description.

### Taxonomies

| Taxonomy | Slug | Type | Description |
|---|---|---|---|
| Video Categories | `video_category` | Hierarchical (like categories) | Organise videos into nested categories |
| Tags | `post_tag` | Flat (built-in) | Tag videos with freeform keywords |

---

## Divi Module: Video Embed

The **Video Embed** module (`et_pb_video_embed`) appears in the **Media** section of the Divi Builder. It embeds a YouTube or Vimeo video in a fully responsive container.

### Module Settings

| Setting | Options | Default | Description |
|---|---|---|---|
| **Video Mode** | Dynamic / Manual Override | Dynamic | Where to source the video URL |
| **Video URL** | Any URL | _(empty)_ | Shown only in Manual mode |
| **Aspect Ratio** | 16:9 / 4:3 / 1:1 | 16:9 | Controls the embed container's proportions |
| **Show Thumbnail as Poster** | Yes / No | Yes | Displays the Video Post's thumbnail as a background image before the video loads |

### Video Mode: Dynamic

The module reads `_video_url` from the current post's meta at render time. This is the intended mode when the module is used inside a **Divi Theme Builder** single-post template targeting the `video_post` post type.

- The video URL is resolved server-side via `get_post_meta()`.
- If `_video_thumbnail` is set, it is used as the poster background; otherwise the post's featured image is used.
- If no URL is found, the module renders an empty `<div class="dvp-no-video">` (hidden by default).
- In the Divi Builder preview, dynamic mode shows a placeholder message since post meta cannot be resolved inside the builder.

### Video Mode: Manual Override

A specific URL is entered directly in the module settings. Useful for embedding a video on any page, independent of the `video_post` post type.

- A live iframe preview is rendered inside the Divi Builder.
- The **Video URL** field is hidden when the mode is set to Dynamic.

### Supported URL Formats

The module automatically detects the platform and extracts the video ID from the following URL formats:

**YouTube:**
- `https://www.youtube.com/watch?v=VIDEO_ID`
- `https://youtu.be/VIDEO_ID`
- `https://www.youtube.com/embed/VIDEO_ID`

**Vimeo:**
- `https://vimeo.com/VIDEO_ID`
- `https://player.vimeo.com/video/VIDEO_ID`

### Responsive Embed

The embed is wrapped in a ratio container using the CSS padding-bottom technique, making it fully responsive at any viewport width. The ratio CSS classes applied are:

| Ratio | Class | Padding-bottom |
|---|---|---|
| 16:9 | `.dvp-ratio-16x9` | 56.25% |
| 4:3 | `.dvp-ratio-4x3` | 75% |
| 1:1 | `.dvp-ratio-1x1` | 100% |

---

## Build Process (Developers)

The Divi Builder module's React/JSX component (`modules/VideoEmbed/VideoEmbed.jsx`) must be compiled before it functions inside the Divi Builder's visual editor. The compiled output is `build/divi-video-post.min.js`, which Divi's extension system enqueues automatically.

```bash
# Install dependencies (run once)
yarn install

# Watch for changes during development
yarn start

# Production build (output to build/)
yarn build
```

**For releases:** Always run `yarn build` and commit the resulting `build/divi-video-post.min.js` before tagging a release. This ensures the plugin is installable without a build step.

> The `node_modules/` directory is gitignored and should never be committed.

---

## Releases & Automatic Updates

This plugin uses **GitHub Releases** for distribution and automatic updates. It does not appear on the WordPress.org plugin directory. Installed sites check for updates via the GitHub API and are notified through the standard WordPress **Plugins → Updates** screen.

### How the updater works

1. WordPress periodically checks for plugin updates by querying the `update_plugins` transient.
2. The updater (`includes/class-github-updater.php`) hooks into that check and calls `https://api.github.com/repos/domkirby/divi-dynamic-video/releases/latest`.
3. The response is cached for **12 hours** to avoid hitting the GitHub API rate limit.
4. If the release's tag version is greater than the installed version, WordPress shows the standard "update available" notice.
5. Clicking **Update Now** downloads the ZIP and runs the normal WordPress plugin installer.

Draft and pre-release releases are **ignored** — only full releases trigger an update notification.

### Creating a release (maintainer checklist)

1. **Bump the version** in two places:
   - `divi-video-post.php` — the `Version:` plugin header and the `DVP_VERSION` constant
   - `package.json` — the `version` field

2. **Build compiled assets:**
   ```bash
   yarn build
   git add build/divi-video-post.min.js
   ```

3. **Commit and tag:**
   ```bash
   git add divi-video-post.php package.json
   git commit -m "Release v1.x.x"
   git tag v1.x.x
   git push origin main --tags
   ```

4. **Create the GitHub Release:**
   - Go to **Releases → Draft a new release** on GitHub.
   - Select the tag you just pushed.
   - Write release notes in the body (Markdown supported — displayed in the WordPress update popup).
   - **Attach a release asset** named exactly `divi-video-post.zip`. This ZIP must contain a single top-level directory named `divi-video-post/` so WordPress installs the plugin into the correct location.
   - Publish the release (do **not** mark it as a pre-release).

5. **Verify** by visiting **Dashboard → Updates** on an installed site; the new version should appear within 12 hours, or immediately after clicking **Check Again**.

### Release asset ZIP structure

```
divi-video-post.zip
└── divi-video-post/
    ├── divi-video-post.php
    ├── includes/
    ├── modules/
    ├── assets/
    └── build/
```

If no `divi-video-post.zip` asset is attached, the updater falls back to GitHub's auto-generated source ZIP (`zipball_url`). This still works, but the extracted directory will have a temporary name that the updater renames automatically during post-install.

### Flushing the update cache

To force WordPress to re-check for updates immediately (e.g., during testing), call:

```php
DVP_GitHub_Updater::flush_cache();
```

Or from WP-CLI:

```bash
wp eval "DVP_GitHub_Updater::flush_cache();"
wp plugin list --update=available
```

---

## Security

- All video URLs are escaped with `esc_url()` before output.
- Meta values are sanitized on save (`sanitize_url`, `absint`, `sanitize_textarea_field`).
- The admin meta box is protected by a WordPress nonce (`dvp_save_meta`).
- No user-generated HTML is inserted into iframe attributes.

---

## Frequently Asked Questions

**The video doesn't appear on the frontend — only a blank space.**

Make sure the Video Post has a URL saved in the **Video Details** meta box, and that the Divi Theme Builder template targets `video_post` single posts. The module renders nothing when no URL is found.

**The builder shows "Video will load from post meta on the frontend" instead of a preview.**

This is correct behaviour for **Dynamic** mode. The builder cannot resolve post meta at design time. Switch to **Manual Override** to see a live preview while editing.

**The Video URL field is hidden in the module settings.**

The **Video URL** field is intentionally hidden when **Video Mode** is set to Dynamic. Switch the mode to **Manual Override** to reveal it.

**I changed the video thumbnail but the poster image didn't update.**

Divi caches rendered modules. Clear the Divi Builder's static cache under **Divi → Theme Options → Builder → Static CSS File Generation**.
