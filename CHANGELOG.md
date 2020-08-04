# Changelog

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
