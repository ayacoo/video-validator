<?php

declare(strict_types=1);

namespace Ayacoo\VideoValidator\Tests\Unit\Service\Validator;

use Ayacoo\VideoValidator\Service\Validator\AbstractVideoValidator;
use Ayacoo\VideoValidator\Service\Validator\VimeoValidator;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class VimeoValidatorTest extends UnitTestCase
{
    private VimeoValidator $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new VimeoValidator('vimeo');
    }

    #[Test]
    public function isAbstractVideoValidator(): void
    {
        self::assertInstanceOf(AbstractVideoValidator::class, $this->subject);
    }

    #[Test]
    public function testBuildUrl()
    {
        $mediaId = '123456789';
        $expectedUrl = 'https://vimeo.com/' . $mediaId;
        self::assertSame($expectedUrl, $this->subject->buildUrl($mediaId));
    }

    #[Test]
    public function testGetOEmbedUrlWithDefaultFormat()
    {
        $mediaId = '123456789';
        $expectedUrl = sprintf(
            'https://vimeo.com/api/oembed.%s?width=2048&url=%s',
            'json',
            rawurlencode('https://vimeo.com/' . rawurlencode($mediaId))
        );
        self::assertSame($expectedUrl, $this->subject->getOEmbedUrl($mediaId));
    }

    #[Test]
    public function testGetOEmbedUrlWithXmlFormat()
    {
        $mediaId = '123456789';
        $format = 'xml';
        $expectedUrl = sprintf(
            'https://vimeo.com/api/oembed.%s?width=2048&url=%s',
            rawurlencode($format),
            rawurlencode('https://vimeo.com/' . rawurlencode($mediaId))
        );
        self::assertSame($expectedUrl, $this->subject->getOEmbedUrl($mediaId, $format));
    }
}
