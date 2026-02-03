<?php

namespace App\Command;

use App\DTO\Anomaly\BasePackageResult;
use App\DTO\Anomaly\CountCorrelation;
use App\DTO\Anomaly\DetectionResult;
use App\DTO\Anomaly\GrowthAnomaly;
use App\DTO\Anomaly\PackageRatio;
use App\DTO\Anomaly\Spike;
use App\Entity\Month;
use App\Service\AnomalyDetectionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:detect-anomalies',
    description: 'Detect anomalies in package statistics that might indicate malicious submissions'
)]
class DetectAnomaliesCommand extends Command
{
    public function __construct(
        private readonly AnomalyDetectionService $anomalyDetectionService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('month', 'm', InputOption::VALUE_REQUIRED, 'Month to analyze (YYYYMM)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $thresholds = $this->anomalyDetectionService->getThresholds();

        $targetMonth = $this->parseTargetMonth($input);
        $target = Month::fromYearMonth($targetMonth);
        $baselineEnd = $target->offset(-1)->getYearMonth();
        $baselineStart = $target->offset(-$thresholds->lookbackMonths)->getYearMonth();

        $this->printHeader($io, $targetMonth, $baselineStart, $baselineEnd, $thresholds->lookbackMonths);

        $result = $this->anomalyDetectionService->detect($targetMonth, $baselineStart, $baselineEnd);
        $this->renderResults($io, $result, $thresholds->extremeGrowthThreshold);

        return $this->determineExitCode($result, $thresholds->extremeGrowthThreshold);
    }

    private function parseTargetMonth(InputInterface $input): int
    {
        /** @var string|null $monthOption */
        $monthOption = $input->getOption('month');

        if ($monthOption === null) {
            return Month::create(0)->getYearMonth();
        }

        if (!preg_match('/^[0-9]{6}$/', $monthOption)) {
            throw new \InvalidArgumentException('Month must be in YYYYMM format');
        }

        return (int)$monthOption;
    }

    private function printHeader(
        SymfonyStyle $io,
        int $targetMonth,
        int $baselineStart,
        int $baselineEnd,
        int $lookbackMonths
    ): void {
        $io->title('Anomaly Detection Report');
        $io->text([
            sprintf('Target month: %d', $targetMonth),
            sprintf('Baseline period: %d - %d (%d months)', $baselineStart, $baselineEnd, $lookbackMonths),
        ]);
    }

    private function determineExitCode(DetectionResult $result, float $extremeGrowthThreshold): int
    {
        if ($result->isHighConfidence($extremeGrowthThreshold)) {
            return 2;
        }

        if ($result->hasMirrorAnomalies() || $result->hasArchitectureAnomalies()) {
            return 1;
        }

        return Command::SUCCESS;
    }

    private function renderResults(SymfonyStyle $io, DetectionResult $result, float $extremeGrowthThreshold): void
    {
        $this->renderBasePackageAnomalies($io, $result->basePackageResult);
        $this->renderGrowthAnomalies($io, 'Mirror Anomalies', $result->mirrorAnomalies);
        $this->renderSpikes($io, 'New Mirror Spikes', $result->newMirrorSpikes);
        $this->renderArchitectureAnomalies($io, $result);

        if ($result->isHighConfidence($extremeGrowthThreshold)) {
            $this->renderCountCorrelations($io, $result->countCorrelations);
            $this->renderSpikes($io, 'New Package Spikes', $result->newPackageSpikes);
        }

        $this->renderSummary($io, $result, $extremeGrowthThreshold);
    }

    private function renderBasePackageAnomalies(SymfonyStyle $io, BasePackageResult $result): void
    {
        if (!$result->hasAnomalies()) {
            return;
        }

        $io->section('Base Package Anomalies');
        $io->text(sprintf('Base package median: %s', number_format($result->median)));

        if (!empty($result->outliers)) {
            $io->error('Base packages exceeding threshold - HIGHLY suspicious:');
            $io->table(
                ['Package', 'Count', 'Ratio'],
                array_map(fn(PackageRatio $p): array => [
                    $p->name,
                    number_format($p->count),
                    sprintf('%.2fx', $p->ratio),
                ], $result->outliers)
            );
        }

        if (!empty($result->packagesAboveThreshold)) {
            $io->warning('Non-base packages exceeding base threshold:');
            $io->table(
                ['Package', 'Count', 'Ratio'],
                array_map(fn(PackageRatio $p): array => [
                    $p->name,
                    number_format($p->count),
                    sprintf('%.2fx', $p->ratio),
                ], array_slice($result->packagesAboveThreshold, 0, 10))
            );
        }
    }

    /** @param list<GrowthAnomaly> $anomalies */
    private function renderGrowthAnomalies(SymfonyStyle $io, string $title, array $anomalies): void
    {
        if (empty($anomalies)) {
            return;
        }

        $io->section($title);
        $io->table(
            ['Identifier', 'Count', 'Baseline Avg', 'Growth %'],
            array_map(fn(GrowthAnomaly $a): array => [
                $a->identifier,
                number_format($a->count),
                number_format($a->baselineAvg),
                sprintf('%+.1f%%', $a->growthPercent),
            ], $anomalies)
        );
    }

    private function renderArchitectureAnomalies(SymfonyStyle $io, DetectionResult $result): void
    {
        if (!$result->hasArchitectureAnomalies()) {
            return;
        }

        $io->section('Architecture Anomalies');

        $rows = [];
        foreach ($result->systemArchAnomalies as $a) {
            $rows[] = [
                'system',
                $a->identifier,
                number_format($a->count),
                number_format($a->baselineAvg),
                sprintf('%+.1f%%', $a->growthPercent)
            ];
        }
        foreach ($result->osArchAnomalies as $a) {
            $rows[] = [
                'os',
                $a->identifier,
                number_format($a->count),
                number_format($a->baselineAvg),
                sprintf('%+.1f%%', $a->growthPercent)
            ];
        }

        $io->table(['Type', 'Architecture', 'Count', 'Baseline Avg', 'Growth %'], $rows);
    }

    /** @param list<Spike> $spikes */
    private function renderSpikes(SymfonyStyle $io, string $title, array $spikes): void
    {
        if (empty($spikes)) {
            return;
        }

        $io->section($title);
        $io->table(
            ['Identifier', 'Count'],
            array_map(fn(Spike $s): array => [$s->identifier, number_format($s->count)], array_slice($spikes, 0, 10))
        );
    }

    /** @param list<CountCorrelation> $correlations */
    private function renderCountCorrelations(SymfonyStyle $io, array $correlations): void
    {
        if (empty($correlations)) {
            return;
        }

        $io->section('Suspicious Count Correlations');
        foreach (array_slice($correlations, 0, 5) as $c) {
            $packages = implode(', ', array_slice($c->packages, 0, 8));
            if (count($c->packages) > 8) {
                $packages .= '...';
            }
            $io->text(sprintf('  Delta +%s: %d packages - %s', number_format($c->delta), $c->packageCount, $packages));
        }
    }

    private function renderSummary(SymfonyStyle $io, DetectionResult $result, float $extremeGrowthThreshold): void
    {
        $io->newLine();

        $isHighConfidence = $result->isHighConfidence($extremeGrowthThreshold);

        if ($isHighConfidence) {
            $typeCount = ($result->hasMirrorAnomalies() ? 1 : 0)
                + ($result->hasArchitectureAnomalies() ? 1 : 0)
                + ($result->hasBasePackageAnomalies() ? 1 : 0);
            $io->error(sprintf('High-confidence anomalies detected (%d types) - requires investigation', $typeCount));
        } elseif ($result->hasMirrorAnomalies() || $result->hasArchitectureAnomalies()) {
            $io->warning('Minor anomalies detected (single mirror or architecture spike - may be legitimate)');
        } else {
            $io->success('No high-confidence anomalies detected');
        }

        $baseCount = count($result->basePackageResult->outliers)
            + count($result->basePackageResult->packagesAboveThreshold);
        $mirrorCount = count($result->mirrorAnomalies) + count($result->newMirrorSpikes);
        $archCount = count($result->systemArchAnomalies) + count($result->osArchAnomalies);

        $io->text([
            sprintf('  Base package anomalies: %d', $baseCount),
            sprintf('  Mirror anomalies: %d', $mirrorCount),
            sprintf('  Architecture anomalies: %d', $archCount),
        ]);
    }
}
