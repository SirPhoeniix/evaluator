<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerSdk\Evaluator\Console\ReportRenderer;

use SprykerSdk\Evaluator\Dto\ReportDto;
use SprykerSdk\Evaluator\Dto\ReportLineDto;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;

class ReportRenderer
{
    /**
     * @param \SprykerSdk\Evaluator\Dto\ReportDto $report
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return void
     */
    public function render(ReportDto $report, OutputInterface $output): void
    {
        foreach ($report->getReportLines() as $reportLine) {
            $this->renderTitle($reportLine, $output);

            $output->writeln('');

            $reportLine->getViolations()
                ? $this->renderCheckerViolations($reportLine->getViolations(), $output)
                : $this->renderSuccess($output);

            $output->writeln('');
        }
    }

    /**
     * @param \SprykerSdk\Evaluator\Dto\ReportLineDto $reportLine
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return void
     */
    protected function renderTitle(ReportLineDto $reportLine, OutputInterface $output): void
    {
        $checkerName = (string)preg_replace('/[\W_]/', ' ', $reportLine->getCheckerName());
        $separator = str_repeat('=', strlen($checkerName));

        $output->writeln($separator);
        $output->writeln(strtoupper($checkerName));
        $output->writeln($separator);
    }

    /**
     * @param array<\SprykerSdk\Evaluator\Dto\ViolationDto> $violations
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return void
     */
    protected function renderCheckerViolations(array $violations, OutputInterface $output): void
    {
        $table = new Table($output);
        $table
            ->setHeaders(['#', 'Message', 'Target'])
            ->setRows($this->getRows($violations));
        $table->render();
    }

    /**
     * @param array<\SprykerSdk\Evaluator\Dto\ViolationDto> $violations
     *
     * @return array<array<mixed>>
     */
    protected function getRows(array $violations): array
    {
        $rows = [];

        foreach ($violations as $index => $violation) {
            $rows[] = [++$index, $violation->getMessage(), $violation->getTarget()];
        }

        return $rows;
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return void
     */
    protected function renderSuccess(OutputInterface $output): void
    {
        $output->writeln('<bg=green>Success!</>');
    }
}
