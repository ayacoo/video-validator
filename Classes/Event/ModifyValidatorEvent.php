<?php
declare(strict_types=1);

namespace Ayacoo\VideoValidator\Event;

use Ayacoo\VideoValidator\Service\Validator\AbstractVideoValidatorInterface;

final class ModifyValidatorEvent
{
    private ?AbstractVideoValidatorInterface $validator;

    private string $extension;

    /**
     * @param AbstractVideoValidatorInterface|null $validator
     */
    public function __construct(
        AbstractVideoValidatorInterface $validator = null,
        string                          $extension = ''
    )
    {
        $this->validator = $validator;
        $this->extension = $extension;
    }

    /**
     * @return AbstractVideoValidatorInterface|null
     */
    public function getValidator(): ?AbstractVideoValidatorInterface
    {
        return $this->validator;
    }

    /**
     * @param AbstractVideoValidatorInterface|null $validator
     */
    public function setValidator(?AbstractVideoValidatorInterface $validator): void
    {
        $this->validator = $validator;
    }

    /**
     * @return string
     */
    public function getExtension(): string
    {
        return $this->extension;
    }

    /**
     * @param string $extension
     */
    public function setExtension(string $extension): void
    {
        $this->extension = $extension;
    }
}
