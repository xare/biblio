# Project Overview

This is a WordPress plugin for library management. It appears to synchronize data from an external system called "Geslib" and also handles book covers. The plugin creates custom post types for books, authors, and editorials, extending the standard WordPress data model to accommodate library-specific content.

The plugin is composed of three main modules:

*   **Biblio:** The core module, which seems to provide the basic plugin infrastructure.
*   **Geslib:** This module handles the integration with the Geslib system, importing and managing products, categories, authors, and editorials. It also provides a set of WP-CLI commands for data management.
*   **Covers:** A smaller module for managing book covers.

## Technologies

*   **Backend:** PHP, WordPress Plugin API
*   **Frontend:** JavaScript (ES6+), SCSS
*   **Build Tools:** Gulp, Babel, Browserify, Composer
*   **Dependencies:**
    *   PHP: Guzzle
    *   JS: SweetAlert

# Building and Running

1.  **Install Dependencies:**
    *   Run `composer install` to install the PHP dependencies.
    *   Run `npm install` to install the frontend dependencies.

2.  **Build Assets:**
    *   Run `gulp` to compile the SCSS and JavaScript files. The compiled assets will be placed in the `dist` directory.

3.  **Activate the Plugin:**
    *   The plugin can be activated from the WordPress admin dashboard.

# Development Conventions

*   The project follows the standard WordPress plugin structure.
*   PHP code is organized using namespaces.
*   The plugin uses a service-based architecture, where each module registers its own services (classes) in an `Init` file.
*   The `gulpfile.js` is configured to watch for changes in the `assets` directory and automatically recompile the assets.
*   The project includes a set of WP-CLI commands for managing the plugin's data. These commands are defined in the `inc/Geslib/Commands` and `inc/Covers/Commands` directories.

# Geslib Cron Job

The plugin includes a cron job that periodically processes files from the `/var/www/html/larepartidora/wp-content/plugin/biblio` directory. This process is essential for synchronizing the data from the Geslib system with the WordPress database.

Here is a step-by-step explanation of the process:

1.  **File Discovery:** The cron job scans the `/var/www/html/larepartidora/wp-content/plugin/biblio` directory for files matching the pattern `INTER*.zip`.

2.  **Logging:** For each found `.zip` file, the plugin creates a new entry in the `geslib_log` table with the status "logged".

3.  **Queuing:** The cron job then finds the first entry in the `geslib_log` table with the "logged" status and changes its status to "queued".

4.  **Line Processing:** The content of the corresponding `.zip` file is extracted and read line by line. Each line is then inserted into the `geslib_queues` table.

5.  **Action Execution:** The plugin processes each line from the `geslib_queues` table and performs several actions, such as creating or updating products, authors, and editorials.

6.  **Product Creation:** The ultimate goal of this process is to create or update product records in the WooCommerce `products` post type.

This entire process is handled by the `geslib_cron_event` cron job, which is defined in the `inc/Geslib/Base/Cron.php` file. The cron job is scheduled to run every two hours.
