<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Typo3\Command;

use Mindtwo\Monitoring\Typo3\MonitorProvider;
use Mindtwo\Monitoring\Typo3\Support\SnapshotSummary;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * `vendor/bin/typo3 monitoring:show` — collect and display the snapshot as a
 * metric/status/details table, or the full JSON payload with --json.
 */
final class ShowCommand extends Command
{
    public function __construct(private MonitorProvider $monitors)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Collect and display the monitoring snapshot for this installation.')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Print the full snapshot payload as JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $snapshot = $this->monitors->monitor()->snapshot();

        if ((bool) $input->getOption('json')) {
            $output->writeln($snapshot->toJson(JSON_PRETTY_PRINT));

            return Command::SUCCESS;
        }

        $io = new SymfonyStyle($input, $output);
        $io->table(['Metric', 'Status', 'Details'], SnapshotSummary::rows($snapshot));

        $payload = $snapshot->toArray();
        $io->writeln(sprintf(
            'Collected at %s for environment %s. Use --json for the full payload.',
            is_string($payload['collected_at'] ?? null) ? $payload['collected_at'] : 'n/a',
            is_string($payload['environment'] ?? null) ? $payload['environment'] : 'n/a'
        ));

        return Command::SUCCESS;
    }
}
