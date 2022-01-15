<?php

declare(strict_types=1);

namespace Ayacoo\VideoValidator\Service\Report;

interface AbstractReportServiceInterface
{
    public function makeReport(): void;

    public function getSettings(): array;

    public function setSettings(array $settings): void;

    public function getValidVideos(): array;

    public function setValidVideos(array $validVideos): void;

    public function getInvalidVideos(): array;

    public function setInvalidVideos(array $invalidVideos): void;
}
