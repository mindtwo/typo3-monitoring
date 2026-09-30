<?php

declare(strict_types=1);

namespace Mindtwo\Monitoring\Typo3\Command;

use Mindtwo\Monitoring\Contracts\Collector;
use Mindtwo\Monitoring\Contracts\ExplainsSupport;
use Mindtwo\Monitoring\Typo3\MonitorProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * `vendor/bin/typo3 monitoring:collectors` — list the registered collectors
 * and whether they are supported on this host, with the reason when not.
 */
final class CollectorsCommand extends Command
{
    public function __construct(private MonitorProvider $monitors)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('List the registered monitoring collectors and whether they are supported here.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = [];

        foreach ($this->monitors->monitor()->collectors() as $key => $collector) {
            try {
                $supported = $collector->supported();
            } catch (Throwable $exception) {
                $rows[] = [$key, get_class($collector), 'error', $exception->getMessage()];

                continue;
            }

            $rows[] = [
                $key,
                get_class($collector),
                $supported ? 'yes' : 'no',
                $supported ? '' : (string) $this->reason($collector),
            ];
        }

        (new SymfonyStyle($input, $output))->table(['Key', 'Collector', 'Supported', 'Reason'], $rows);

        return Command::SUCCESS;
    }

    private function reason(Collector $collector): ?string
    {
        try {
            return $collector instanceof ExplainsSupport ? $collector->unsupportedReason() : null;
        } catch (Throwable) {
            return null;
        }
    }
}
