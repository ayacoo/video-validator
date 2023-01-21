<?php
declare(strict_types=1);

namespace Ayacoo\VideoValidator\Event;

final class ModifyReportServiceEvent
{
    public function __construct(
        private array $reportServices = []
    )
    {
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
