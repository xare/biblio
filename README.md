# Biblio WP plugin

This is a Wordpress plugin that creates an automatic transfer of data from a book shop point of sale which uses Geslib propietary software (from Trebenque S.L. company in Spain) to a wordpress site in form of woocommerce products in several steps which involve 
- scanning a directory where several text files get added.
- parsing each file line by line and create a item in a queue to be processed.
- Processing the queues and feeding data to woocommerce products.
- Scanning each product and download from 2 APIs for pictures for the covers of each product which are all books and assign the pictures as the image of each corresponding product.

## Introduction
The libraries Point Of Sale, computer has a software that produces a text file which name is INTER000 where
000 will change numerically until 999.

These files are zipped and stored regularly in the Point´s Of Sale computer's folder, and then synchronized automatically via ftp with a scheduled taks provided by Geslib from the Point of Sale server towards the woocommerce hosting server at wordpress folder /wp-contents/uploads/geslib

The files are stored into a database table called geslib_log which keeps a log for each uploaded file and if the file is waiting to be proccessed (status = "logged"), is being queued or has already been processed.

The most recently added file in logged status is then set its' status to queued. And then the script opens the referred file and reads each line. 

As lines contain different information we have three possibilities:
- The line does not provide any information needed for the script to work thus gets skipped.
- The line provides information needed for the script to work the line content gets stored into the geslib_queue table.
- The line provides information needed by the script but it refers to a previously parsed line, these can be considered "child" lines whereas the rest of lines which may provide information can be considered "parent" lines. Since each line has its purpose the hierarchy is easy to foresee and a number we call geslib_id establishes the link between a "parent" line and a "child" line.

Once all the lines have been stored in geslib_queues, the information they contain has to be transformed and restored in the same table but instead of containing the line itself we will store a json object which contains all the information from both the parent line and its children.

Once this process is finished each line is reread but this time the information is stored in the woocommerce database.

## Geslib application

This is the structure of the whole plugin.

In order to work, first of all this plugin defines a number of custom fields for the woocommerce products and two custom taxonomies referred to by the products.

Then two tables are used: 

´´´
table geslib_log

id int primary autoincrement
filename string
startdate datetime
enddate datetime
status text
lines_count int

table geslib_queues

id int primary autoincrement
log_id int
geslib_id int
type string
data text
´´´

In wp-contents/plugins/biblio/inc/Geslib/Api/GeslibApiReadFiles.php we define the functions that scan the wp-contents/uploads/geslib folder for it´s contents. It unzip the uploaded files, and then adds a row in geslib_log with the startdate, the filename, the lines count and status "Logged.".

In wp-contents/plugins/biblio/inc/Geslib/Api/GeslibApiLog.php we read each row of geslib_log and when we find row with both status = "logged" and the smallest value of "startdate". 
It will store each line win geslib_queues with the value for "type" "store_lines".

Then a second function will read all the rows form geslib_queue of type "store_lines" and will create new rows with the following type values:
- 'store_products',
- 'store_autors', 
- 'store_editorials',
- 'store_colecciones', 
- 'store_categories'.

This way we regroup the data that were stored as lines with values separated by vertical lines "|" into json structured data strings where different lines referring to the same entity whether it is a product, an author, an editorial, a collection or a product category are stored.

In wp-contents/plugins/biblio/inc/Geslib/Api/GeslibApiDbQueueManager.php we process any row within the geslib_queue, in a first stage those rows where the type "store_lines" and they moved to other rows with values 'store_products', 'store_authors', 'store_editorials', 'store_colecciones' and 'store_categories'. At a second stage these lines are getting read and processed.




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

