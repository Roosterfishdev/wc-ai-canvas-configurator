# WC AI Canvas Configurator

A WooCommerce extension that provides a step-based custom canvas configurator with AI-powered artwork generation.

## Features

- **Step-based Configurator**: 5-step workflow (Size → Upload → Style → Preview → Cart)
- **AI Art Generation**: Transform customer photos into stylized artwork using OpenAI GPT Image 1.5 (via Replicate)
- **Template Mockups**: Generate room mockups showing artwork in context
- **Cloudflare R2 Storage**: S3-compatible object storage for customer images
- **Async Processing**: Uses WooCommerce Action Scheduler for background AI processing
- **Session-based Builds**: Works for both logged-in and guest users
- **Admin Dashboard**: Manage and monitor builds from WooCommerce admin

## Requirements

- WordPress 6.0+
- WooCommerce 7.0+
- PHP 7.4+
- WooCommerce Action Scheduler (included with WooCommerce)

## Installation

1. Upload the `wc-ai-canvas-configurator` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure R2 storage settings (see below)
4. Enable the configurator on desired products

## Configuration

### Cloudflare R2 Storage

Add the following constants to your `wp-config.php`:

```php
// Cloudflare R2 Settings
define( 'WC_AICC_R2_ENDPOINT', 'https://YOUR_ACCOUNT_ID.r2.cloudflarestorage.com' );
define( 'WC_AICC_R2_ACCESS_KEY', 'your-access-key-id' );
define( 'WC_AICC_R2_SECRET_KEY', 'your-secret-access-key' );
define( 'WC_AICC_R2_BUCKET', 'your-bucket-name' );
define( 'WC_AICC_R2_PUBLIC_BASE_URL', 'https://your-cdn-domain.com' ); // Optional CDN URL
```

**Development Mode**: If R2 is not configured, files are stored locally in `wp-content/uploads/wc-aicc-builds/`.

### Replicate AI Provider

To enable real AI-powered image transformation using Replicate with OpenAI's GPT Image 1.5 model, add your API token to `wp-config.php`:

```php
// Replicate AI Settings (required)
define( 'REPLICATE_API_TOKEN', 'r8_your_api_token_here' );

// OpenAI API Key (optional - Replicate may use proxy if not set)
define( 'OPENAI_API_KEY', 'sk-your-openai-key' );
```

Alternatively, set as environment variables:

```bash
export REPLICATE_API_TOKEN=r8_your_api_token_here
export OPENAI_API_KEY=sk-your-openai-key  # optional
```

**How it works:**
- When `REPLICATE_API_TOKEN` is set, the plugin automatically uses Replicate for AI generation
- If the token is not set, the plugin falls back to the stub provider (returns original image)
- Uses OpenAI GPT Image 1.5 via Replicate for image-to-image style transformation
- Supports all 6 art styles with optimized prompts
- `OPENAI_API_KEY` is optional: Replicate can proxy requests, but providing your own key may improve rate limits and reliability

**Getting tokens:**
1. **Replicate**: Sign up at [replicate.com](https://replicate.com) → Account Settings → API Tokens
2. **OpenAI** (optional): [platform.openai.com](https://platform.openai.com) → API Keys

**Cost Considerations:**
- GPT Image 1.5 "low" quality: ~$0.009 per image (used for previews)
- Monitor usage in your Replicate and OpenAI dashboards
- Consider implementing rate limits for high-traffic sites

### Display Settings (Bricks Builder Compatible)

The plugin supports page builders like Bricks that override WooCommerce templates.

Go to **WooCommerce → Settings → Products → AI Canvas Configurator** to configure:

**Render Mode:**
- **Hook (automatic)**: Injects configurator via WooCommerce hooks (default)
- **Shortcode only**: Disable auto-injection, use `[wc_aicc_configurator]` manually

**Hook Location** (when using Hook mode):
- Before Add to Cart Form (recommended for Bricks)
- After Add to Cart Form
- Before Product Summary
- After Product Summary
- After Product Meta
- After Share Buttons

**Hook Priority**: Lower numbers execute earlier (default: 5)

### Using the Shortcode

For full control over placement, use shortcode mode:

```
[wc_aicc_configurator]
```

On a product page, it auto-detects the current product. On other pages, specify the product:

```
[wc_aicc_configurator product_id="123"]
```

This is useful when:
- Using Bricks or other page builders that override WooCommerce templates
- Creating custom product page layouts
- Embedding the configurator in a landing page

### Enable Configurator on Products

1. Edit a **Variable Product** in WooCommerce
2. Go to the "AI Canvas" tab in Product Data
3. Check "Enable AI Canvas Configurator"
4. Ensure your product has variations with size attributes (e.g., "16x20", "24x36")

## Usage

### Customer Flow

1. **Select Size**: Customer chooses a canvas size (product variation)
2. **Upload Image**: Customer uploads their photo (JPG, PNG, WebP up to 10MB)
3. **Select Style**: Customer picks an AI art style and adds optional notes
4. **Generate Preview**: AI processes the image (async via Action Scheduler)
5. **Add to Cart**: Customer adds the customized product to their cart

### Admin Features

- **WooCommerce → AI Canvas Builds**: View all builds with status, filter by status
- **Retry**: Re-queue failed builds for processing
- **Delete**: Remove builds and associated storage files
- **View Assets**: Direct links to original, final art, and mockup images

## Architecture

```
wc-ai-canvas-configurator/
├── wc-ai-canvas-configurator.php    # Main plugin file
├── includes/
│   ├── class-autoloader.php         # PSR-4 style autoloader
│   ├── class-activator.php          # Activation hooks
│   ├── class-deactivator.php        # Deactivation hooks
│   ├── class-session-manager.php    # Session key management
│   ├── models/
│   │   └── class-build.php          # Build model
│   ├── repository/
│   │   └── class-build-repository.php # Database operations
│   ├── storage/
│   │   ├── class-storage-interface.php
│   │   └── class-r2-storage.php     # Cloudflare R2 adapter
│   ├── providers/
│   │   ├── class-ai-provider-interface.php
│   │   ├── class-stub-ai-provider.php        # Stub implementation
│   │   ├── class-replicate-ai-provider.php   # Replicate GPT Image 1.5 provider
│   │   └── class-ai-provider-factory.php
│   ├── mockup/
│   │   ├── class-mockup-generator-interface.php
│   │   ├── class-stub-mockup-generator.php      # Stub implementation
│   │   ├── class-template-mockup-generator.php  # Template compositing
│   │   └── class-mockup-generator-factory.php
│   ├── api/
│   │   └── class-rest-controller.php # REST API endpoints
│   ├── jobs/
│   │   ├── class-job-handler.php    # Action Scheduler job
│   │   └── class-cleanup-job.php    # Daily cleanup
│   ├── woocommerce/
│   │   ├── class-product-integration.php
│   │   ├── class-cart-integration.php
│   │   └── class-order-integration.php
│   ├── admin/
│   │   ├── class-admin-controller.php
│   │   ├── class-product-meta.php
│   │   └── class-settings.php        # WooCommerce settings integration
│   └── frontend/
│       └── class-configurator.php    # Shortcode handler
├── assets/
│   ├── css/
│   │   ├── configurator.css
│   │   └── admin.css
│   ├── js/
│   │   └── configurator.js
│   └── images/
│       └── styles/                  # Style thumbnails
└── templates/
    ├── configurator.php             # Frontend template
    └── admin/
        └── builds-list.php          # Admin builds list
```

## REST API Endpoints

All endpoints are under `/wp-json/wc-aicc/v1/`:

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/builds` | Create a new build |
| POST | `/builds/{uuid}/upload` | Upload image to build |
| POST | `/builds/{uuid}/generate` | Start AI generation |
| GET | `/builds/{uuid}` | Get build status and URLs |
| GET | `/styles` | Get available AI styles |
| POST | `/session` | Create/get session key |

## Database Table

The plugin creates `{prefix}ai_builds` table:

| Column | Type | Description |
|--------|------|-------------|
| id | BIGINT | Auto-increment ID |
| build_uuid | CHAR(36) | Unique build identifier |
| session_key | VARCHAR | Session key for guest users |
| user_id | BIGINT | WordPress user ID (nullable) |
| product_id | BIGINT | WooCommerce product ID |
| variation_id | BIGINT | WooCommerce variation ID |
| size_label | VARCHAR | Size label (e.g., "16x20") |
| aspect_ratio | VARCHAR | Aspect ratio (e.g., "4:5") |
| style_id | VARCHAR | Selected AI style ID |
| notes | TEXT | Customer notes |
| status | VARCHAR | Build status |
| regen_count | INT | Regeneration attempts |
| original_key | VARCHAR | R2 key for original image |
| cropped_key | VARCHAR | R2 key for cropped image |
| final_art_key | VARCHAR | R2 key for final artwork |
| mockup_key | VARCHAR | R2 key for mockup |
| error_message | TEXT | Error message if failed |
| created_at | DATETIME | Creation timestamp |
| updated_at | DATETIME | Last update timestamp |

## Extending

### Adding a Real AI Provider

1. Create a new class implementing `AI_Provider_Interface`
2. Register it in `AI_Provider_Factory::register_default_providers()`
3. Or use the filter: `add_filter('wc_aicc_ai_providers', ...)`

Example:

```php
class Replicate_AI_Provider implements \WC_AICC\Providers\AI_Provider_Interface {
    public function get_id() {
        return 'replicate';
    }
    
    public function generate($source_url, $style_id, $notes, $aspect_ratio) {
        // Call Replicate API
        // Return ['success' => true, 'image_url' => '...']
    }
    // ... implement other methods
}
```

### Adding a Real Mockup Generator

1. Create a class implementing `Mockup_Generator_Interface`
2. Use GD/Imagick to composite artwork onto room templates
3. Register via `wc_aicc_mockup_generators` filter

## Build Statuses

| Status | Description |
|--------|-------------|
| `draft` | Build created, awaiting image upload or style selection |
| `processing` | AI generation in progress |
| `ready` | Generation complete, ready for cart |
| `failed` | Generation failed (see error_message) |
| `ordered` | Added to a WooCommerce order |
| `expired` | Cleaned up after 72 hours |

## Cleanup

A daily cleanup job runs at 3 AM to:
- Find builds older than 72 hours (except `ordered`)
- Delete associated R2/local storage files
- Remove database records

## Security

- REST endpoints require valid nonce
- Builds are only accessible to the owning session/user
- File uploads validated for type and size
- All database queries use prepared statements

## License

GPL-2.0+

## TODO

- [x] ~~Implement real AI provider integration (Replicate, Stability AI)~~ ✓ Done
- [ ] Implement real image cropping with aspect ratio enforcement
- [ ] Implement template-based mockup compositing
- [ ] Add more AI style options with real thumbnails
- [ ] Add variation attribute support for custom aspect ratios
- [ ] Add admin settings page for global configuration
- [ ] Add email notifications for build completion
- [ ] Add build regeneration limit configuration
- [ ] Add support for additional AI providers (Stability AI, OpenAI DALL-E)
