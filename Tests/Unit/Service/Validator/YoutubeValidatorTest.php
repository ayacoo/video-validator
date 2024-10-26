<?php

declare(strict_types=1);

namespace Ayacoo\VideoValidator\Tests\Unit\Service\Validator;

use Ayacoo\VideoValidator\Service\Validator\AbstractVideoValidator;
use Ayacoo\VideoValidator\Service\Validator\YoutubeValidator;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class YoutubeValidatorTest extends UnitTestCase
{
    private YoutubeValidator $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new YoutubeValidator('youtube');
    }

    #[Test]
    public function isAbstractVideoValidator(): void
    {
        self::assertInstanceOf(AbstractVideoValidator::class, $this->subject);
    }

    #[Test]
    public function testBuildUrl()
    {
        $mediaId = 'dQw4w9WgXcQ';
        $expectedUrl = 'https://www.youtube.com/watch?v=' . $mediaId;
        self::assertSame($expectedUrl, $this->subject->buildUrl($mediaId));
    }

    #[Test]
    public function testGetOEmbedUrlWithDefaultFormat()
    {
        $mediaId = 'dQw4w9WgXcQ';
        $expectedUrl = sprintf(
            'https://www.youtube.com/oembed?url=%s&format=%s',
            rawurlencode('https://www.youtube.com/watch?v=' . rawurlencode($mediaId)),
            'json'
        );
        self::assertSame($expectedUrl, $this->subject->getOEmbedUrl($mediaId));
    }

    #[Test]
    public function testGetOEmbedUrlWithXmlFormat()
    {
        $mediaId = 'dQw4w9WgXcQ';
        $format = 'xml';
        $expectedUrl = sprintf(
            'https://www.youtube.com/oembed?url=%s&format=%s',
            rawurlencode('https://www.youtube.com/watch?v=' . rawurlencode($mediaId)),
            rawurlencode($format)
        );
        self::assertSame($expectedUrl, $this->subject->getOEmbedUrl($mediaId, $format));
    }
}
