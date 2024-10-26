<?php

declare(strict_types=1);

namespace Ayacoo\VideoValidator\Service\Validator;

use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class AbstractVideoValidator implements AbstractVideoValidatorInterface
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
