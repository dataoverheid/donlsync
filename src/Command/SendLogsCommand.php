<?php

namespace DonlSync\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use DonlSync\Application;
use DonlSync\Catalog\Target\ITargetCatalog;
use DonlSync\Configuration;
use DonlSync\Database\Repository\ExecutionMessageRepository;
use DonlSync\Exception\DonlSyncRuntimeException;
use DonlSync\Exception\InputException;
use DonlSync\Helper\StringHelper;
use Exception;
use Jenssegers\Blade\Blade;
use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;
use ZipArchive;

/**
 * Class SendLogsCommand.
 *
 * Sends the logs generated by DonlSync to the recipients configured in the application settings
 * file.
 */
class SendLogsCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    public function configure(): void
    {
        $this->setName('SendLogs');
        $this->setDescription(
            'Sends the generated logs of today\'s DonlSync execution to the configured ' .
            'recipient');
        $this->addOption(
            'date', null, InputOption::VALUE_REQUIRED,
            'The date on which the logs were generated'
        );
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception Thrown on any unrecoverable error
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $application = new Application($input, $output);
        $date        = $input->getOption('date');

        if (null === $date || '' == trim($date)) {
            throw new InputException('date argument is missing or empty');
        }

        if ('false' === $_ENV['SMTP_ENABLE']) {
            $output->writeln('email is disabled by environment setting');

            return 1;
        }

        $files    = $this->findLogFiles($date);
        $summary  = $this->loadAndDeleteSummaryFile($date);
        $messages = $this->loadImportAlerts($application->database_connection(), $date);

        if (0 === count($files)) {
            $output->writeln('found no logs to send');

            return 0;
        }

        $mail_client = $application->smtp_client();
        $mail_client->addAttachment(Application::RESOURCES_DIR . 'images/overheid.nl.png');
        $mail_client->addAttachment($this->createZIPArchive($files, $date));

        $this->removeProcessedLogFiles($files);
        $this->addRecipients($mail_client, $application->config('email_recipients'));
        $this->generateEmailTitle($mail_client, $date);
        $this->generateEmailBody(
            $mail_client, $application->blade_engine(), $summary, $messages, $date,
            $application->version(), $application->target_catalog()
        );

        $mail_client->send();

        return 0;
    }

    /**
     * Find all logs that match the given date.
     *
     * @param string $date The date contained within the log filenames
     *
     * @return SplFileInfo[] The found files
     */
    private function findLogFiles(string $date): array
    {
        $finder = new Finder();
        $files  = $finder->in(Application::LOG_DIR)->files()->depth('== 0')->name([
            '*__import__' . $date . '.log',
            '*__analysis__' . $date . '.log',
            '*__unmapped__' . $date . '.log',
        ]);

        return iterator_to_array($files);
    }

    /**
     * Loads, parses and then deletes the JSON summary file associated with the given date.
     *
     * @param string $date The date contained within the filename
     *
     * @return int[] The summary as a {string key} => {int} array
     */
    private function loadAndDeleteSummaryFile(string $date): array
    {
        $file = Application::LOG_DIR . 'summary__' . $date . '.json';

        if (!file_exists($file)) {
            throw new DonlSyncRuntimeException('summary cannot be found at ' . $file);
        }

        $summary = json_decode(file_get_contents($file), true);
        unlink($file);

        return $summary;
    }

    /**
     * Loads all unprocessed import alerts generated during the import process. These alerts will be
     * marked as processed in the database afterwards.
     *
     * @param Connection $connection The active database connection
     * @param string     $date       The date to identify the alerts by
     *
     * @return array<int, array> The generated alerts
     */
    private function loadImportAlerts(Connection $connection, string $date): array
    {
        try {
            $query_date = sprintf('%s-%s-%s',
                mb_substr($date, 0, 4),
                mb_substr($date, 4, 2),
                mb_substr($date, 6, 2)
            );

            return ExecutionMessageRepository::getMessagesByDate(
                $connection, $query_date, true
            );
        } catch (DBALException $e) {
            return [];
        }
    }

    /**
     * Creates a ZIP archive from the given files.
     *
     * @param SplFileInfo[] $files The files to add to the ZIP archive
     * @param string        $date  The date on which the logs were generated
     *
     * @return string The absolute filepath to the created ZIP archive
     */
    private function createZIPArchive(array $files, string $date): string
    {
        $log_archive_file = sprintf('%s/DonlSync__%s.zip', Application::LOG_DIR, $date);
        $log_archive      = new ZipArchive();
        $log_archive->open($log_archive_file, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($files as $file) {
            $log_archive->addFile($file->getRealPath(), $file->getBasename());
        }

        $log_archive->close();

        return $log_archive_file;
    }

    /**
     * Delete the processed logs as they are now also contained within the created ZIP archive.
     *
     * @param SplFileInfo[] $files The files to delete
     */
    private function removeProcessedLogFiles(array $files): void
    {
        foreach ($files as $file) {
            unlink($file->getRealPath());
        }
    }

    /**
     * Add the configured recipient to the email client.
     *
     * @param PHPMailer     $client           The email client to modify
     * @param Configuration $email_recipients The recipients which should receive the email
     */
    private function addRecipients(PHPMailer $client, Configuration $email_recipients): void
    {
        foreach ($email_recipients->all() as $recipient) {
            try {
                $client->addAddress($recipient['email'], $recipient['name']);
            } catch (PHPMailerException $e) {
                throw new DonlSyncRuntimeException('Failed to add recipient', 0, $e);
            }
        }
    }

    /**
     * Generate a title for the email based on the environment and date.
     *
     * @param PHPMailer $client The email client
     * @param string    $date   The date to include in the title
     */
    private function generateEmailTitle(PHPMailer $client, string $date): void
    {
        $client->Subject = sprintf('%s (%s) - %s',
            $_ENV['APPLICATION_NAME'], $_ENV['APPLICATION_ENVIRONMENT'],
            StringHelper::formatNonDateString($date)
        );
    }

    /**
     * Renders the body of the summary email that will be sent.
     *
     * @param PHPMailer         $client  The mail client sending the email
     * @param Blade             $blade   The blade engine used to render the template
     * @param int[]             $summary The dataset summary
     * @param array<int, array> $alerts  Any alerts generated during the import process
     * @param string            $date    The date being processed
     * @param string            $version The version of the application
     * @param ITargetCatalog    $target  The catalog to which the datasets were sent
     */
    private function generateEmailBody(PHPMailer $client, Blade $blade, array $summary,
                                       array $alerts, string $date, string $version,
                                       ITargetCatalog $target): void
    {
        $client->Body = $blade->make('email_summary', [
            'date'         => StringHelper::formatNonDateString($date),
            'target_name'  => $target->getCatalogSlugName(),
            'target_url'   => $target->getCatalogEndpoint(),
            'summary_keys' => array_keys($summary),
            'summary'      => $summary,
            'alerts'       => $alerts,
            'email_source' => $client->From,
            'version'      => $version,
            'environment'  => $_ENV['APPLICATION_ENVIRONMENT'],
        ])->render();
    }
}
