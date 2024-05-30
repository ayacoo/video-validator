<?php

declare(strict_types=1);

namespace Ayacoo\VideoValidator\Tests\Functional\Domain\Repository;

use Ayacoo\VideoValidator\Domain\Dto\ValidatorDemand;
use Ayacoo\VideoValidator\Domain\Repository\FileRepository;
use Ayacoo\VideoValidator\Service\VideoService;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class FileRepositoryTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['video_validator'];

    private FileRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = $this->get(FileRepository::class);
    }

    #[Test]
    public function getVideosByExtensionForNoRecordsReturnsEmptyResult(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Basic.csv');

        $limit = 1;

        $validatorDemand = new ValidatorDemand();
        $validatorDemand->setReferencedOnly(false);
        $validatorDemand->setLimit($limit);
        $validatorDemand->setExtension('myvideo');

        $rows = $this->subject->getVideosByExtension($validatorDemand, time());

        self::assertCount(0, $rows);
    }

    #[Test]
    public function getVideosByExtensionWithRecordsReturnsLimitedResult(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Basic.csv');

        $limit = 5;

        $validatorDemand = new ValidatorDemand();
        $validatorDemand->setReferencedOnly(false);
        $validatorDemand->setLimit($limit);
        $validatorDemand->setExtension('youtube');

        $rows = $this->subject->getVideosByExtension($validatorDemand, time());

        self::assertCount(1, $rows);
        self::assertSame(1, $rows[0]['uid']);
    }

    #[Test]
    public function getVideosForReportWithStatusReturnsValidResult(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ValidatedVideos.csv');

        $limit = 10;

        $validatorDemand = new ValidatorDemand();
        $validatorDemand->setReferencedOnly(false);
        $validatorDemand->setLimit($limit);
        $validatorDemand->setExtension('youtube');
        $validatorDemand->setDays(7);

        $rows = $this->subject->getVideosForReport($validatorDemand, VideoService::STATUS_SUCCESS);
        self::assertCount(1, $rows);

        $rows = $this->subject->getVideosForReport($validatorDemand, VideoService::STATUS_ERROR);
        self::assertCount(1, $rows);
    }
}
