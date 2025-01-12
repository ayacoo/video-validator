<?php

declare(strict_types=1);

namespace Ayacoo\VideoValidator\Event;

use TYPO3\CMS\Core\Resource\FileInterface;

final class ModifyVideoValidateEvent
{
    public function __construct(
        private readonly ?FileInterface $file,
        private readonly array $properties = [],
    ) {
    }

    public function getFile(): ?FileInterface
    {
        return $this->file;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }
}
