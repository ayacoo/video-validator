<?php

declare(strict_types=1);

namespace Ayacoo\VideoValidator\Service\Validator;

use TYPO3\CMS\Core\Utility\GeneralUtility;

class AbstractVideoValidator
{
    /**
     * @param $mediaId
     * @return bool
     */
    public function isVideoOnline($mediaId): bool
    {
        $oEmbed = GeneralUtility::getUrl(
            $this->getOEmbedUrl($mediaId)
        );

        return is_string($oEmbed) ?? false;
    }
}
