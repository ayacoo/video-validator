<?php
declare(strict_types=1);

namespace Ayacoo\VideoValidator\Event;

use Ayacoo\VideoValidator\Service\Report\AbstractReportServiceInterface;

final class ModifyReportServiceEvent
{
    private ?AbstractReportServiceInterface $reportService;

    /**
     * @param AbstractReportServiceInterface|null $reportService
     */
    public function __construct(
        AbstractReportServiceInterface $reportService = null
    )
    {
        $this->reportService = $reportService;
    }

    /**
     * @return AbstractReportServiceInterface|null
     */
    public function getReportService(): ?AbstractReportServiceInterface
    {
        return $this->reportService;
    }

    /**
     * @param AbstractReportServiceInterface|null $reportService
     */
    public function setReportService(?AbstractReportServiceInterface $reportService): void
    {
        $this->reportService = $reportService;
    }
}
