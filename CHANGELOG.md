# Changelog

## 4.7.4 (2022/02)

- Downgraded the `composer.lock` file to satisfy the PHP 7.4 minimum requirement.

## 4.7.3 (2022/01)

- Updated the API endpoint for the Nijmegen source catalog.

## 4.7.2 (2021/09)

- Dropped the unique index on the UnmappedValues table as text columns cannot be part of an index for some RDBMS's. This constraint was already enforced throughout the codebase, so it introduces no functional difference.

## 4.7.1 (2021/08)

- The `dataset.relatedResource` property harvested from the Eindhoven source catalog is now processed by the `StringHelper::repairURL` method before it is offered to the dataset builder.
- If a string given to the `StringHelper::repairURL` method contains a `|` character, then everything up to and including that character is removed from the string before performing any other repairs.

## 4.7.0 (2021/08)

- The Stelselcatalogus (SC) source catalog is now included in the manual and scheduled executions of DonlSync.
- Updated the Stelselcatalogus source catalog to only harvest datasets which have at least 1 distribution.
- Updated the Nijmegen and Stelselcatalogus source catalogs to repair any harvested `distribution.accessURL` properties before the datasets are offered to the DatasetBuilder.
- Updated the `StringHelper::repairURL` method so that it can repair several new cases of bad URLs.
- Updated the `.env.dist` file to include the CKAN credentials for the Stelselcatalogus source catalog. This appears to have been omitted in version `4.5.0`.
- DonlSync will now register missing mappings during the harvesting process. A mapping is considered missing when the harvested value is equal to the effective value after applying all the mappings _and_ the effective value is not valid according to the DCAT validation model. The missing mappings are included in the `ZIP` archive of the scheduled execution in a `{source catalog}__unmapped__{date}.log` file. Entries in this file are formatted as `{object}, {attribute}, {value}`.

**Note** This version requires an update to the DonlSync database. Run `php DonlSync InstallDatabase` to perform these database updates.

## 4.6.1 (2021/07)

- Fix for Eindhoven source catalog for when a dataschema distribution was being created for a dataset without explicit language information.
- Expanded the CKAN keyword transformer to additionally strip any forward slashes from the keyword.
- Changed the restart policy of the local `docker-compose.yml` to "no".

## 4.6.0 (2021/06)

- Convert HTML to MarkDown in `description` (in dataset and distributions) fields in Eindhoven datasets.

## 4.5.1 (2021/06)

- Change implementation of determination of `dataschema` distributions.
- Let Stelsel Catalogus (SC) use its own defaults mapping.

## 4.5.0 (2021/06)

- Add the "pseudo-harvesting" of Stelsel Catalogus (SC) datasets. This means that _existing_ SC datasets are harvested and corresponding _begrippen_ (concepts) and _gegevenselementen_ (data schemas) are harvested/updated while doing so.

## 4.4.1 (2021/06)

- Harvesting the bounding box metadata from NGR can now be enabled/disabled via a key in the `catalog_NGR.json` configuration file.
- Updated the endpoint of the Nijmegen catalog API and updated the query for retrieving the Nijmegen datasets from the API.

## 4.4.0 (2021/05)

- Added value list table for attributes in `FeatureCatalogue` of NGR datasets.

## 4.3.0 (2021/05)

- Added support for `Datasetschema` of Eindhoven datasets. A `Datasetschema` of an Eindhoven dataset describes the content of a dataset. It is transformed to a distribution.

## 4.2.0 (2021/04)

- Added support for `FeatureCatalogue` of NGR datasets. A `FeatureCatalogue` of an NGR dataset describes the content of a dataset. It is transformed to a distribution.

## 4.1.0 (2021/04)

- Disabled PersistentProperties of the `DONLTargetCatalog`. This feature is currently bugged and prevents certain resources from being sent to CKAN.
- The NGR source catalog will now harvest graphics as DCAT Distributions with type Visualization.
- Moved the NGR method to repair URLs to an application-wide class so that it can be reused for other source catalogs. The new method includes several new edge-cases to repair and is backed by several unit tests.

## 4.0.1 (2021/04)

- Support storing the computed metadata checksum in CKAN for later retrieval.

## 4.0.0 (2021/04)

- Minimum PHP version raised to `7.4`. Several parts of the codebase were updated to use the new features introduced in this PHP version, such as class property type-hinting.
- Introduced support for `PHP 8.0`.
- Included a new source catalog 'Eindhoven'. This source catalog harvests the [data.eindhoven.nl](https://data.eindhoven.nl) catalog.
- Refactored all database interactions such that different RDBM's can be used. There is no longer a hard requirement on MySQL.
- The PHPDoc of class properties were expanded to include a description of the property.
- Updated the various Composer dependencies.
- Included a `docker-compose.yml` file for local development.
- All shell scripts were moved the the `./bin` directory.
- The `Application` god-object now has an accompanying interface `ApplicationInterface`.
- Optimized the procedure for comparing the harvested dataset to the dataset on the catalog. Only a single `package_show` API call will now be used for multiple comparions (`persistent_properties` and `resource.id checks`).
- The `NGRSourceCatalog` is now capable of harvesting geo and temporal metadata from the NGR source catalog.
- The fallback license has been updated to `licentieonbekend` to better describe the understanding of the license metadata.

## 3.2.0 (2020/09)

- Added Docker support.
- Updated several Composer dependencies.
- Set executable bits to the `*.sh` files in the `shell/` directory.

## 3.1.5 (2020/09)

- Updated Gitlab CI pipeline to perform level 6 static analysis. All code is now expected to pass level 6.
- Updated typehints of the various `*BuildRule` classes to ensure proper type-hinting throughout the application.
- Updated several `array` typehints to more accurately describe the contents of said `array`.
- Reduced code duplication in the various `*BuildRule` implementations.
- Renamed `DCATDistributionBuildRule` to `DONLDistributionBuildRule` as to properly indicate which type of `DCATEntity` is being built by the `BuildRule`.
- Explicit `int` to `string` conversion while writing output.

## 3.1.4 (2020/08)

- Fixed a bug that prevented the recognition of pre-existing resources.
- Updated `Composer` dependencies.
- Increased UnitTest coverage of:
    - `DonlSync\Catalog\Target\DONL`

## 3.1.3 (2020/08)

- The output of `Application::version()` is now only computed once per execution. 
- `SendLogsCommand` now throws a `DonlSyncRuntimeException` when adding a recipient fails.
- `DateTimer` now throws a `DonlSyncRuntimeException` in the following cases:
    - When trying to end a timer that hasn't started yet.
    - When the `DateTimeFormat` is invalid.
    - When the given configuration is missing keys.
- Several typehints have been updated to indicate that they *can* contain `null` values.
- `DONLTargetCatalog` now throws a `DonlSyncRuntimeException` when not all credentials are provided to methods that require credentials.
- Updated `Composer` dependencies.
- Updated Gitlab CI pipeline to include several analysis tools.
- Increased UnitTest coverage of:
    - `DonlSync\Helper`
    - `DonlSync`

## 3.1.2 (2020/08)

- Removed unnecessary `file_exists()` call in `Configuration::createFromJSONFile()` as `is_readable()` already covers that case.
- `DateTimer` now throws `DonlSyncRuntimeException`s on any `DateTime` related error.
- Increased UnitTest coverage of:
    - `DonlSync`

## 3.1.1 (2020/08)

- Fixed a bug that prevented an empty JSON list from being accepted by `MappingLoader::loadJSONContentsFromURL()`.
- `BuilderConfiguration::getDefaults()` can now properly return `null` when no `DefaultMapper` is assigned.
- Updated several `composer` dependencies.
- Updated PHP-CS-Fixer config to include `test/`.
- Updated `phpunit.xml.dist` to PHPUnit 9.3's XSD.
- Increased UnitTest coverage of:
    - `DonlSync\Command`
    - `DonlSync\Dataset`
    - `DonlSync\Dataset\Builder`
    - `DonlSync\Dataset\Mapping`
    - `DonlSync\Helper`
- Added Gitlab CI integration.

## 3.1.0 (2020/07)

- The `Summarizer` now maintains and stores a summary on a per catalog basis. 
- The email template will now display the import summary per catalog rather than only the total summary.
- The SendLogs command will now throw a `DonlSyncRuntimeException` if the summary file cannot be found.

## 3.0.6 (2020/07)

- Slight tuning to the API call used to retrieve all the dataset IDs from the NGR catalog. Results are now explicitly sorted and facets are no longer included in the response.

## 3.0.5 (2020/07)

- Updated README.md to include requirements for the configured MySQL user.
- Fixed shell scripts for systems which do not have `TMPDIR` defined as an environment variable.
- Removed catalog URI from ExecutionMessage format.

## 3.0.4 (2020/07)

- Dataset Identifier conflicts are now registered as an ExecutionMessage of the import. They will now be shown as part of the daily email summary as a result. 

## 3.0.3 (2020/07)

- Introduced database patch file to upgrade from version `2.0` to version `3.x`.
- Added shell script `log_cleanup.sh` which will periodically cleanup old logs from the scheduled executions and updated the installation instructions to include this new script.

## 3.0.2 (2020/07)

- Introduced `CHANGELOG.md` to track changes between versions.
- Introduced `CONTRIBUTING.md` to provide guidelines on how to contribute.

## 3.0.1 (2020/07)

- Dataset rejections by the target catalog are included in the daily email summary.

## 3.0.0 (2020/07)

- Increased minimum PHP version to `7.3`.
- Updated several `composer` dependencies to newer versions.
- Updated codestyle guidelines and enforced them throughout the entire codebase.
- Introduced shell scripts to execute DonlSync manually and in a scheduled manner. The old shell scripts specific for each environment were removed as they are now obsolete.
- Moved all 'sensitive' configuration values to `.env` and ensured Git will not track this file. A `.env.dist` file is tracked which contains the placeholder values for the `.env` file.
- Introduced a `post-install-cmd` script to generate all the files (and directories) which are not tracked by Git.
- Updated email summary to include more data about the daily execution. It now includes the total number of processed datasets.
- Email summary updated to a HTML message. The message body is now generated using the PHP Blade engine (https://github.com/jenssegers/blade).
- Removed support for multiple environments. The `.env` file now dictates the environment used. The environment dictated in the `.env` file represents the environment of DonlSync itself *and* the environment of the target catalog. These environments should always be identical. In order to target more than 1 environment it is now necessary to install this project multiple times (1 for each environment).
- Merged the codebases for the CBS and CBSDerden catalog. These catalogs are ran by the same software. As such, a single `ISourceCatalog` implementation called `ODataCatalog` now represents both catalogs. This implementation now serves either CBS or CBSDerden based on the configuration injected.
- CBS and CBSDerden `Distribution.AccessURL` properties are now based on a custom buildrule which generates the appropriate value based on the mappings provided by CBS. The 'old' `AccessURL` has been moved to the `DownloadURL` property of the same `Distribution`.
- Moved all ODataCatalog XPath selectors to the configuration file of the source catalog. It is now possible to define multiple XPath selectors for a single property. The `ODataMetadataExtractor` will try these selectors one by one until it encounters a non-empty value.
- Moved all NGR XPath selectors to the configuration file of the NGR source catalog. It is now possible to define multiple XPath selectors for a single property. The `NGXMLMetadataExtractor` will try these selectors one by one until it encounters a non-empty value.
- Updated and introduced several XPath selectors for source catalog NGR to better support the several metadata profiles currently in use on https://nationaalgeoregister.nl.
- Moved all mapping files to a Github repository (https://github.com/dataoverheid/donlsync-mappings) and updated all references to these files.
- Moved all default value settings to an online mapping file which is hosted on Github (https://github.com/dataoverheid/donlsync-mappings).
- Default values are now managed via the `DefaultMapper` which is another implementation of the `AbstractMapper`. All DCAT buildrules are updated to use this new class.
- Removed the `AppGlobals` class and moved its functionality into several configuration files.
- DonlSync now enforces a maximum number of Distributions which may be present for any given dataset. This maximum is defined in the `.env` file. If a dataset has more than the configured maximum only the first *N* will be synchronized, where *N* is the configured maximum amount of Distributions.
- Introduced a global application container that acts as a dependency repository for the rest of the application.
- Removed all `Controller`s. All command logic is now part the `Command` being executed.
- Updated the database table schema of `ProcessedDataset` and `ExecutionMessage`. The `environment` column is no longer applicable and has been removed. Several SQL indices were introduced for these tables.
- The database table repository classes now have a `createTable` method used to create the database tables they represent.
- Introduced a command `php DonlSync InstallDatabase` to create all required database tables.
- Refactored several buildrule implementations to reduce code duplication.

## 2.0.0 (n/a)

No data available.

## 1.0.0 (n/a)

No data available.
