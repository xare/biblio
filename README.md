# Biblio WordPress Plugin

A comprehensive WordPress plugin for library and bookshop management, integrating point-of-sale inventory systems with WooCommerce and automated book cover management.

**Version:** 1.0  
**Author:** Juan Etxenike Almeida (xaresd@gmail.com)  
**License:** GPL-3.0

## Overview

Biblio provides three integrated modules for managing library and bookshop operations on WordPress with WooCommerce:

- **Biblio Module** — Dashboard and library management core
- **Geslib Module** — Automatic inventory synchronization from POS systems using Geslib format
- **Covers Module** — Automated book cover download and management

The plugin automates the complete workflow from bookshop point-of-sale systems through to WooCommerce products with minimal manual intervention.

## Features

### 📚 Core Features

- **Custom Post Types** — Create and manage library-specific content types
- **Custom Taxonomies** — Organize books by authors, editorials, collections, and categories
- **WooCommerce Integration** — Seamless product management for physical books
- **Admin Dashboard** — Centralized control panel for all plugin features
- **Configurable Post Types** — Define custom post types and taxonomies through admin interface

### 🔄 Geslib Integration (Automatic Inventory Sync)

- **File Monitoring** — Watches `/wp-content/uploads/geslib/` for POS data files (INTER*.zip)
- **Queue Processing** — Multi-stage processing pipeline for data transformation
- **Data Transformation** — Converts pipe-delimited text to structured product data (JSON)
- **Batch Operations** — Efficient processing of large inventory updates
- **Logging & Tracking** — Complete audit trail of all imported files and operations
- **WooCommerce Sync** — Automatically creates/updates WooCommerce products, authors, categories

**Supported Data Types:**
- Products
- Authors (custom taxonomy)
- Editorials/Publishers (custom taxonomy)
- Collections (custom taxonomy)
- Product Categories

### 📖 Covers Module (Book Cover Management)

- **Multi-Source Cover Downloads** — Retrieves book covers from external APIs
- **Automatic Detection** — Scans products and downloads missing covers
- **Media Library Integration** — Stores covers in WordPress media library
- **Product Association** — Automatically assigns covers to book products
- **Batch Processing** — Queue-based cover download system
- **Logging** — Track all cover operations and API calls

## Requirements

- **WordPress** 6.0+
- **PHP** 7.2.24+
- **WooCommerce** 5.0+
- **MySQL/MariaDB** with custom table support
- **Composer** (for dependency management)

## Installation

### 1. Via WordPress Admin

1. Download the plugin as a ZIP file
2. Go to **Plugins → Add New → Upload Plugin**
3. Select the ZIP file and click **Install Now**
4. Activate the plugin

### 2. Via Composer (Recommended for Development)

```bash
cd wp-content/plugins
git clone <repository-url> biblio
cd biblio
composer install
```

### 3. Manual Installation

```bash
# Extract to plugins directory
unzip biblio.zip -d wp-content/plugins/

# Install dependencies
cd wp-content/plugins/biblio
composer install
```

## Configuration

### Initial Setup

1. **Activate the Plugin**
   - Go to Plugins and activate "Biblio a plugin for libraries"
   - Plugin automatically creates required database tables

2. **Access Main Dashboard**
   - Navigate to **Biblio** in admin menu
   - Configure plugin settings and enable modules

3. **Enable Modules** (if needed)
   - **CPT Manager** — Enable custom post types
   - **Taxonomy Manager** — Enable custom taxonomies

### Geslib Configuration

1. **File Upload Directory**
   - Ensure `/wp-content/uploads/geslib/` exists and is writable
   - POS system should upload INTER*.zip files here via FTP

2. **Queue Processing**
   - Set up WP-Cron for automatic processing
   - Or use WP-CLI commands for manual/scheduled execution

3. **Batch Settings**
   - Configure batch sizes in settings
   - Adjust timeout values for large imports

### Covers Configuration

1. **API Keys** (if required by cover service)
   - Configure API credentials in settings
   - Set cover source preferences

2. **Download Schedule**
   - Configure cron job timing
   - Set batch download limits

## Usage

### Admin Dashboard

Access via **Biblio** menu in WordPress admin:

- **Dashboard** — Overview of plugin status and recent activities
- **Settings** — Configure plugin options
- **Logs** — View processing logs and troubleshooting information

### Geslib Operations

#### Automatic Processing (via WP-Cron)

The plugin automatically processes queued files based on WordPress cron schedule.

#### Manual Processing (WP-CLI)

```bash
# Process all queues
wp geslib process-all

# Clear queue
wp geslib queue clear

# Delete all products
wp geslib delete-products

# Delete all editorials
wp geslib delete-editorials
```

### Covers Operations

#### Automatic Scanning

Scheduled tasks automatically scan for products missing covers.

#### Manual Scan (WP-CLI)

```bash
# Scan products and download covers
wp covers scan-products

# View covers log
wp covers view-log
```

## Database Schema

### Geslib Tables

```sql
-- Tracks imported files
CREATE TABLE {$wpdb->prefix}geslib_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    startdate DATETIME DEFAULT CURRENT_TIMESTAMP,
    enddate DATETIME,
    status VARCHAR(50),
    lines_count INT
);

-- Queue for processing lines
CREATE TABLE {$wpdb->prefix}geslib_queues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_id INT NOT NULL,
    geslib_id INT,
    type VARCHAR(50),
    data LONGTEXT,
    FOREIGN KEY (log_id) REFERENCES {$wpdb->prefix}geslib_log(id)
);
```

### Custom Fields for Products

Biblio adds custom fields to WooCommerce products:
- `_ean` - EAN/ISBN-13 code
- `_num_paginas` - Number of pages
- `_subtitle` - Book subtitle (from Geslib or DILVE)
- `_author` - Primary author
- `geslib_id` - Geslib product ID
- Editorial/Publisher
- Author(s)
- Collection
- Original Price
- And other book-specific metadata

## Architecture

### Directory Structure

```
biblio/
├── inc/
│   ├── Api/                    # API classes and interfaces
│   ├── Base/                   # Core base classes
│   ├── Biblio/                 # Main Biblio module
│   ├── Geslib/                 # Inventory sync module
│   ├── Covers/                 # Cover management module
│   └── Pages/                  # Admin pages
├── assets/
│   ├── js/                     # JavaScript files
│   └── scss/                   # Stylesheets
├── templates/                  # Admin templates
├── vendor/                     # Composer dependencies
└── tests/                      # PHPUnit tests
```

### Plugin Modules

**Biblio Module** (`Inc\Biblio\`)
- Core plugin functionality
- Dashboard and settings
- Custom post type and taxonomy management
- Settings API integration

**Geslib Module** (`Inc\Geslib\`)
- POS file parsing and processing
- Queue management and transformation
- WooCommerce product synchronization
- Logging and tracking

**Covers Module** (`Inc\Covers\`)
- API integration for cover downloads
- Product scanning and matching
- Media library management
- Cover update scheduling

## Geslib Processing Pipeline

The Geslib module implements a sophisticated multi-stage processing pipeline:

### Stage 1: File Discovery and Parsing

1. POS system generates `INTER###.txt` files (pipe-delimited data)
2. Files are zipped and uploaded to `/wp-content/uploads/geslib/` via FTP
3. Plugin discovers and unzips files, creates entry in `geslib_log` table
4. File marked with status "logged" awaiting processing

### Stage 2: Queue Population

1. Oldest "logged" file selected for processing
2. Each line from file stored in `geslib_queues` with type "store_lines"
3. Parent-child relationships established via `geslib_id` field

### Stage 3: Data Transformation

1. "store_lines" entries are processed and grouped by entity type
2. Pipe-delimited values converted to JSON objects
3. Related child lines merged with parent data
4. New queue entries created with types:
   - `store_products` — Product data
   - `store_authors` — Author taxonomy
   - `store_editorials` — Publisher taxonomy
   - `store_colecciones` — Collection taxonomy
   - `store_categories` — Category data

### Stage 4: WooCommerce Synchronization

1. Each transformed queue entry processed
2. Products created/updated in WooCommerce
3. Custom fields populated (ISBN, subtitle, editorial, author, etc.)
4. Taxonomies assigned
5. File status updated to "processed"

#### Queue Processing Types

The Geslib module uses a multi-stage queue system. Each stage is processed sequentially:

| Queue Type | Description | Handler |
|-----------|-------------|---------|
| `store_lines` | Raw file lines stored from INTER*** files | `GeslibApiLines::storeToLines()` |
| `build_content` | Groups related lines (authors, categories, synopses) | `GeslibApiDbLinesManager` |
| `store_products` | Creates/updates WooCommerce products | `GeslibApiDbProductsManager::storeProduct()` |
| `store_autors` | Creates/updates author taxonomy terms | `GeslibApiDbTaxonomyManager::storeAuthor()` |
| `store_editorials` | Creates/updates publisher taxonomy terms | `GeslibApiDbTaxonomyManager::storeEditorial()` |
| `store_categories` | Creates/updates product categories | `GeslibApiDbTaxonomyManager::storeCategory()` |
| `store_colecciones` | Creates/updates collection terms | `GeslibApiDbTaxonomyManager::storeCollection()` |

#### Line Types in INTER*** Files

The Geslib format uses specific line prefixes to identify entity types:

| Prefix | Entity | Description |
|--------|--------|-------------|
| `GP4` | Product | Book/product data (main entity) |
| `AUT` | Author | Author name and metadata |
| `1L` | Editorial | Publisher information |
| `2` | Collection | Book series/collection |
| `3` | Product Category | Category definition |
| `5` | Category Link | Links products to categories |
| `B` | Stock | Stock quantity |
| `LA` | Author Link | Links authors to products |
| `6E` | Synopsis | Extended product description |

### Cron Jobs

The plugin registers scheduled tasks via WordPress Cron:

| Cron Hook | Schedule | Description |
|-----------|----------|-------------|
| `geslib_cron_event` | Every 2 hours | Main import processing |
| `geslib_removeUncategorized_cron_event` | Every 2 hours | Cleanup uncategorized products |

#### Manual Cron Execution

```bash
# Trigger Geslib cron manually
wp cron event run geslib_cron_event

# List scheduled events
wp cron event list
```

### WP-CLI Commands

The plugin provides comprehensive WP-CLI commands for management:

#### Product Management

```bash
# Process all queues
wp geslib process-all

# Store products to WooCommerce
wp geslib store-products [--process-store-products]

# Delete all products
wp geslib delete-products

# Show product count
wp geslib show-tables product_count
```

#### Taxonomy Management

```bash
# Store authors
wp geslib store-authors

# Store editorials
wp geslib store-editorials

# Store categories
wp geslib storeProductCategories
```

#### Queue Management

```bash
# View queue status
wp geslib queue

# Process specific queue line
wp geslib lines

# Clear queue
wp geslib queue clear
```

#### Data Cleanup

```bash
# Delete editorials
wp geslib delete-editorials

# Delete all terms
wp geslib delete-all-terms

# Delete product categories
wp geslib delete-product-categories
```

#### Table Management

```bash
# Truncate tables
wp geslib truncate-table geslib_log
wp geslib truncate-table geslib_queues
```

### Covers Module Pipeline

The Covers module automatically manages book cover images:

1. **Scan** — Finds all products without cover images
2. **Match** — Uses ISBN or product data to identify books
3. **Download** — Retrieves covers from configured APIs
4. **Store** — Saves to WordPress media library
5. **Assign** — Sets as product featured image
6. **Log** — Tracks all operations for debugging

#### DILVE API Integration

The Covers module integrates with the DILVE (Documentación e Información en Línea) API for book metadata and-cover retrieval:

**API Endpoint:** `https://www.dilve.es/dilve/dilve/getRecordsX.do`

**Authentication:**
- `user` - DILVE user credential
- `password` - DILVE password

**Supported Metadata (via ONIX 2.1):**
| Field | WooCommerce Meta Field |
|-------|----------------------|
| Title | `$product->get_name()` |
| Subtitle | `_subtitle` |
| Description | `$product->get_description()` |
| Author | `_author` |
| Publisher | Editorial taxonomy |
| Cover URL | Product featured image |
| Price | Regular price |
| Pages | `_num_paginas` |
| Dimensions | Product dimensions |
| Publication Date | `date_created` |

**API Sources:**
- DILVE: `https://www.dilve.es/dilve/dilve/getRecordsX.do`
- CEGAL: `https://www.cegalenred.com/peticiones/fichalibro.xml.php`

#### Covers Cron Jobs

| Cron Hook | Schedule | Description |
|-----------|----------|-------------|
| `covers_cron_event` | Twice daily | Scans products, downloads covers from DILVE/CEGAL |
| `covers_missing_covers_cron_event` | Twice daily | Attaches local missing covers |

#### Covers WP-CLI Commands

```bash
# Scan products and download covers
wp covers scan-products

# View covers log
wp covers view-log
```

## Development

### Setting Up Development Environment

```bash
cd wp-content/plugins/biblio

# Install dependencies including dev tools
composer install

# Run tests
./vendor/bin/phpunit

# Run tests with coverage report
./vendor/bin/phpunit --coverage-html ./coverage
```

### Testing

The plugin includes comprehensive PHPUnit test suite:

```bash
# Run all tests
npm run test

# Run unit tests only
npm run test:unit

# Run integration tests
npm run test:integration

# Run specific test file
./vendor/bin/phpunit tests/Unit/Base/BaseControllerTest.php
```

See [TESTING.md](TESTING.md) for complete testing documentation.

### Code Quality

The plugin follows PSR-4 autoloading standards and OOP principles. Recent refactoring has introduced:

- **Dependency Injection** — All classes accept dependencies via constructor
- **Interfaces** — Abstract logging and path providers
- **Service Container** — Centralized dependency management
- **Unit Tests** — 30+ tests covering core functionality

See [REFACTORING.md](REFACTORING.md) for detailed refactoring notes.

### Adding New Features

1. **Create service class** in appropriate `inc/` subdirectory
2. **Implement interface** if abstracting functionality
3. **Register in Init class** for automatic loading
4. **Write tests** in `tests/Unit/` directory
5. **Update documentation** as needed

## Troubleshooting

### Common Issues

**Files not being processed:**
- Check `/wp-content/uploads/geslib/` directory exists and is writable
- Verify WP-Cron is enabled: `wp cron test`
- Check logs in `/wp-content/debug.log` for errors

**Covers not downloading:**
- Verify API keys are configured if required
- Check product has ISBN or identifier for matching
- Review covers logs in admin dashboard

**Database errors:**
- Ensure database user has CREATE TABLE privileges
- Check MySQL/MariaDB is running with sufficient resources
- Verify custom tables exist: `wp db tables | grep geslib`

### Debug Mode

Enable WordPress debug logging in `wp-config.php`:

```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

View logs at `/wp-content/debug.log`

## Support & Contribution

- **Issues** — Report bugs via GitHub issues
- **Questions** — Check documentation files (TESTING.md, REFACTORING.md)
- **Contributing** — Follow code standards and include tests

## Changelog

### Version 1.0
- Initial release
- Biblio, Geslib, and Covers modules
- WooCommerce integration
- Comprehensive PHPUnit test suite
- Full refactoring with dependency injection

## License

This plugin is licensed under the GPL-3.0 license. See LICENSE file for details.

## Related Documentation

- [TESTING.md](TESTING.md) — PHPUnit testing guide
- [REFACTORING.md](REFACTORING.md) — Code refactoring documentation
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [WooCommerce Documentation](https://woocommerce.com/documentation/)






## Covers Application


## Appendix

### Tree structure

´´´
.
├── .gitignore
├── GEMINI.md
├── README.md
├── assets
│   ├── js
│   │   ├── biblio.js
│   │   ├── covers.js
│   │   ├── coversAdmin.js
│   │   ├── geslibAdmin.js
│   │   ├── geslibUpdateValues.js
│   │   └── pagination.js
│   └── scss
│       ├── biblio.scss
│       ├── covers.scss
│       ├── coversAdmin.scss
│       ├── geslibAdmin.scss
│       └── modules
├── biblio.php
├── composer.json
├── composer.lock
├── gulpfile.js
├── inc
│   ├── Api
│   │   ├── BiblioApi.php
│   │   ├── Callbacks
│   │   └── SettingsApi.php
│   ├── Base
│   │   ├── Activate.php
│   │   ├── BaseController.php
│   │   ├── Cron.php
│   │   ├── CustomPostTypeController.php
│   │   ├── CustomTaxonomyController.php
│   │   ├── Deactivate.php
│   │   ├── Enqueue.php
│   │   └── SettingsLinks.php
│   ├── Covers
│   │   ├── Api
│   │   ├── Base
│   │   ├── Commands
│   │   ├── Init.php
│   │   └── Pages
│   ├── Geslib
│   │   ├── Api
│   │   ├── Base
│   │   ├── Commands
│   │   ├── Init.php
│   │   └── Pages
│   ├── Init.php
│   └── Pages
│       └── Dashboard.php
├── package-lock.json
├── package.json
├── templates
│   ├── adminCoversDashboard.php
│   ├── adminCustomPostType.php
│   ├── adminDashboard.php
│   ├── adminDilveLines.php
│   ├── adminDilveLogs.php
│   ├── adminGeslibDashboard.php
│   ├── adminGeslibFiles.php
│   ├── adminGeslibLogger.php
│   ├── adminGeslibLogs.php
│   ├── adminGeslibQueues.php
│   ├── adminTaxonomy.php
│   └── tabscontent
│       ├── biblio
│       ├── covers
│       └── geslib
└── tree.md

24 directories, 45 files
´´´


## Appendix Formato exportación GeslibPLUS a Internet

[Formato exportación GeslibPlus a Internet](Formato%20exportacio%CC%81n%20GeslibPLUS%20a%20Internet.pdf)

