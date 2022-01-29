<?php
declare(strict_types=1);

namespace Ayacoo\VideoValidator\Event;

final class ModifyReportServiceEvent
{
    private array $reportServices;

    /**
     * @param array $reportServices
     */
    public function __construct(array $reportServices = [])
    {
        $this->reportServices = $reportServices;
    }

    /**
     * @return array
     */
    public function getReportServices(): array
    {
        return $this->reportServices;
    }

    /**
     * @param array $reportServices
     */
    public function setReportServices(array $reportServices): void
    {
        $this->reportServices = $reportServices;
    }
}
