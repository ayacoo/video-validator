<?php

declare(strict_types=1);

namespace Ayacoo\VideoValidator\Service\Validator;

use TYPO3\CMS\Core\Resource\File;

interface AbstractVideoValidatorInterface
{
    public function isVideoOnline($mediaId): bool;

    public function getOEmbedUrl(string $mediaId, string $format = 'json'): string;

    public function getOnlineMediaId(File $file): string;

    public function buildUrl(string $mediaId): string;
}
