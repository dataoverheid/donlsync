# Data.Overheid.nl synchronization

Synchronizes external catalogs with the [data.overheid.nl](https://data.overheid.nl) catalog.

## License

Licensed under the [CC0](https://creativecommons.org/publicdomain/zero/1.0/) license. [Full license text](https://creativecommons.org/publicdomain/zero/1.0/legalcode)

## Requirements

Minimum version requirements:

| Software | Version        |
|----------|----------------|
| PHP      | `^7.4`, `^8.0` |
| Composer | *              |

The `composer.json` file includes which PHP extensions should be installed and configured.

DonlSync has been verified to work with both MySQL `5.7` and up (or MariaDB equivalent) and Postgres `11` and up.

**Note:**
While memory optimizations have been made, certain external catalogs may exceed the standard allotted RAM during execution,
it is advised to give PHP at least 1 GB of RAM to ensure proper execution.

## Installation

Follow these instructions to install DonlSync.

### Composer

Run the following command in your terminal of choice:

```shell
cd /path/to/DonlSync
composer install --prefer-dist --no-dev --no-suggest --optimize-autoloader --classmap-authoritative
```

### Configuration

Now open the generated `.env` file and fill in all the `{key}={value}` pairs. Some keys have sensible defaults set already.

Open the `./config/email_recipients.json` file and configure all the email-addresses that will receive the daily summary of the 
DonlSync imports. The `composer install` step includes the instructions for how this file should be formatted. 

### Database

In a terminal of choice we prepare the database:

```bash
cd /path/to/donlsync
php DonlSync InstallDatabase
```

### Cron

Add the following line to the crontab (execute every day at 20:00):

```shell
0 20 * * * (bash /path/to/donlsync/bin/scheduled_import.sh)
15 0 * * 1 (bash /path/to/donlsync/bin/log_cleanup.sh)
```

**Note:** If you intend to run DonlSync for multiple environments, ensure that there is at least a 2-hour gap between the scheduled 
executions of these environments as to not overload the source catalogs (or target catalog).

## Docker

Donlsync can be run as a Docker container. To build the Docker image, execute:

```shell
git rev-parse --short HEAD > ./CHECKSUM
docker build --pull --tag "donl-sync:$(cat ./VERSION)" --rm ./
```

## Usage

The import can be manually started with `bash /path/to/donlsync/bin/manual_import.sh /path/to/log/directory` where 
`/path/to/log/directory` is the directory in which the import logs for that execution should be stored. A manual import will not send
an email summary.

It is also possible to start the import of a specific source catalog, this can be achieved with:

```shell
cd /path/to/donlsync
php DonlSync SynchronizeCatalog --catalog={catalog to synchronize}
```
