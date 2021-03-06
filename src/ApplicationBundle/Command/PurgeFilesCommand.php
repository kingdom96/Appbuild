<?php

namespace LinkValue\Appbuild\ApplicationBundle\Command;

use LinkValue\Appbuild\ApplicationBundle\Service\FilesPurgerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Command appbuild:application:purge-files.
 */
class PurgeFilesCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('appbuild:application:purge-files')
            ->setDescription('Purge unused uploaded files.')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Immediately purge all unused files without asking for confirmation.')
            ->addOption(
                'unused-date-filter',
                null,
                InputOption::VALUE_REQUIRED,
                'Set the unused files Finder date filter.
                Be aware that you may compromise the upload workflow if you purge an unused uploaded file before the user had the time to submit its form.
                Default value is "< now - 12hours".'
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        foreach ([
                     'appbuild.application.application_image_files_purger' => 'application images',
                     'appbuild.application.build_files_purger' => 'build files',
                 ] as $filesPurgerServiceId => $filesPurgerLabel) {
            $output->writeln(sprintf('Start purging %s.', $filesPurgerLabel));

            /** @var FilesPurgerInterface $filesPurger */
            $filesPurger = $this->getContainer()->get($filesPurgerServiceId);

            // Apply configuration
            if ($unusedDateFilter = $input->getOption('unused-date-filter')) {
                $filesPurger->setUnusedFilesFinderDateFilter($unusedDateFilter);
            }

            // Check if there is something to purge
            $filesToPurge = $filesPurger->getUnusedFiles();
            if ($filesToPurge->count() === 0) {
                $output->writeln(sprintf('Nothing to purge.'));

                continue;
            }

            if (!$input->getOption('force')) {
                // Show all files to purge
                $output->writeln('List of unused files:');
                foreach ($filesToPurge as $file) {
                    $output->writeln($file->getRealPath());
                }

                // Ask confirmation
                $questionHelper = $this->getHelper('question');
                $question = new ConfirmationQuestion('Are you sure to delete these files (y/n)? ', false);
                if (!$questionHelper->ask($input, $output, $question)) {
                    continue;
                }
            }

            // Purge
            $filesPurger->purge();
        }
    }
}
