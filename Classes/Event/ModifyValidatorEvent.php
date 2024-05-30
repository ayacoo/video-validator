<?php

declare(strict_types=1);

namespace Ayacoo\VideoValidator\Event;

use Ayacoo\VideoValidator\Service\Validator\AbstractVideoValidatorInterface;

final class ModifyValidatorEvent
{
    public function __construct(
        private ?AbstractVideoValidatorInterface $validator,
        private string $extension = ''
    ) {
    }

    public function getValidator(): ?AbstractVideoValidatorInterface
    {
        return $this->validator;
    }

    public function setValidator(?AbstractVideoValidatorInterface $validator): void
    {
        $this->validator = $validator;
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    public function setExtension(string $extension): void
    {
        $this->extension = $extension;
    }
}
