<?php

declare(strict_types=1);

namespace Ayacoo\VideoValidator\Domain\Dto;

class ValidatorDemand
{
    protected int $days = 7;

    protected array $recipients = [];

    protected string $extension = '';

    protected bool $referencedOnly = false;

    protected int $referenceRoot = 0;

    protected int $limit = 10;

    /**
     * @return int
     */
    public function getDays(): int
    {
        return $this->days;
    }

    /**
     * @param int $days
     */
    public function setDays(int $days): void
    {
        $this->days = $days;
    }

    /**
     * @return array
     */
    public function getRecipients(): array
    {
        return $this->recipients;
    }

    /**
     * @param array $recipients
     */
    public function setRecipients(array $recipients): void
    {
        $this->recipients = $recipients;
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

    /**
     * @return bool
     */
    public function isReferencedOnly(): bool
    {
        return $this->referencedOnly;
    }

    /**
     * @param bool $referencedOnly
     */
    public function setReferencedOnly(bool $referencedOnly): void
    {
        $this->referencedOnly = $referencedOnly;
    }

    /**
     * @return int
     */
    public function getReferenceRoot(): int
    {
        return $this->referenceRoot;
    }

    /**
     * @param int $referenceRoot
     */
    public function setReferenceRoot(int $referenceRoot): void
    {
        $this->referenceRoot = $referenceRoot;
    }

    /**
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @param int $limit
     */
    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }
}
