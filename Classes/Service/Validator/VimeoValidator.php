<?php

declare(strict_types=1);

namespace Ayacoo\VideoValidator\Service\Validator;

use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\OnlineMedia\Helpers\VimeoHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class VimeoValidator extends AbstractVideoValidator implements AbstractVideoValidatorInterface
{
    private VimeoHelper $vimeoHelper;

    /**
     * @param string $extension
     */
    public function __construct(string $extension = '')
    {
        $this->vimeoHelper = GeneralUtility::makeInstance(VimeoHelper::class, strtolower($extension));
    }

    /**
     * Get oEmbed data url
     * We use here the function 1:1 from the core, since this is unfortunately not public
     *
     * @param string $mediaId
     * @param string $format
     * @return string
     */
    public function getOEmbedUrl(string $mediaId, string $format = 'json'): string
    {
        return sprintf(
            'https://vimeo.com/api/oembed.%s?width=2048&url=%s',
            rawurlencode($format),
            rawurlencode(sprintf('https://vimeo.com/%s', rawurlencode($mediaId)))
        );
    }

    /**
     * @param File $file
     * @return string
     */
    public function getOnlineMediaId(File $file): string
    {
        return $this->vimeoHelper->getOnlineMediaId($file);
    }

    /**
     * @param string $mediaId
     * @return string
     */
    public function buildUrl(string $mediaId): string
    {
        return '(https://vimeo.com/' . $mediaId . ')';
    }
}
