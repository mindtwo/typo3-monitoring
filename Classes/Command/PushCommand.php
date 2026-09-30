<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Typo3\Command;

use Mindtwo\Monitoring\Typo3\MonitorProvider;
use Mindtwo\Monitoring\Typo3\Support\Typo3ConfigurationRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `vendor/bin/typo3 monitoring:push` — build and deliver a snapshot. Wire it
 * to cron, or run it as a TYPO3 scheduler task ("Execute console commands").
 * The scheduler discards output and only looks at the exit code.
 */
final class PushCommand extends Command
{
    public function __construct(
        private MonitorProvider $monitors,
        private Typo3ConfigurationRepository $config,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Build a monitoring snapshot and push it to the configured endpoint.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print the snapshot payload instead of sending it')
            ->addOption('compact', null, InputOption::VALUE_NONE, 'With --dry-run, print compact instead of pretty JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $monitor = $this->monitors->monitor();

        if ((bool) $input->getOption('dry-run')) {
            $flags = (bool) $input->getOption('compact') ? 0 : JSON_PRETTY_PRINT;
            $output->writeln($monitor->snapshot()->toJson($flags));

            return Command::SUCCESS;
        }

        if (! $this->config->enabled()) {
            // The per-environment kill switch: a scheduler task is identical on
            // every stage, so a disabled stage must stay green and quiet.
            $output->writeln('Monitoring is disabled (enabled = 0 in the extension configuration or MONITORING_ENABLED) — nothing pushed.');

            return Command::SUCCESS;
        }

        $result = $monitor->push();

        if ($result->success) {
            $output->writeln(sprintf('Monitoring snapshot delivered (HTTP %s).', $result->statusCode ?? 'n/a'));

            return Command::SUCCESS;
        }

        $output->writeln(sprintf(
            '<error>Monitoring snapshot could not be delivered: %s</error>',
            $result->error ?? 'unknown error'
        ));

        return Command::FAILURE;
    }
}
