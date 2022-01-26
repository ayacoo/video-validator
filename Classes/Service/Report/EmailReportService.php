<?php

declare(strict_types=1);

namespace Ayacoo\VideoValidator\Service\Report;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\Mailer;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class EmailReportService implements AbstractReportServiceInterface
{
    protected array $settings = [];

    protected array $validVideos = [];

    protected array $invalidVideos = [];

    /**
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface
     */
    public function makeReport(): void
    {
        $subject = 'TYPO3 ' . $this->settings['extension'] . ' validation report';
        $email = GeneralUtility::makeInstance(FluidEmail::class);
        $email
            ->from(new Address($GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress']))
            ->format('html')
            ->setTemplate('VideoReport')
            ->subject($subject)
            ->assign('headline', $subject)
            ->assign('days', $this->settings['days'])
            ->assign('numberOfVideos', count($this->getInvalidVideos()) + count($this->getValidVideos()))
            ->assign('referencedOnly', $this->settings['referencedOnly'])
            ->assign('invalidVideos', $this->getInvalidVideos())
            ->assign('validVideos', $this->getValidVideos());
        // Fix for scheduler
        if ($GLOBALS['TYPO3_REQUEST'] ?? '' instanceof ServerRequestInterface) {
            $email->setRequest($GLOBALS['TYPO3_REQUEST']);
        }
        foreach ($this->settings['recipients'] as $recipient) {
            $email->to($recipient);
            GeneralUtility::makeInstance(Mailer::class)->send($email);
        }
    }

    /**
     * @return array
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    /**
     * @param array $settings
     */
    public function setSettings(array $settings): void
    {
        $this->settings = $settings;
    }

    /**
     * @return array
     */
    public function getValidVideos(): array
    {
        return $this->validVideos;
    }

    /**
     * @param array $validVideos
     */
    public function setValidVideos(array $validVideos): void
    {
        $this->validVideos = $validVideos;
    }

    /**
     * @return array
     */
    public function getInvalidVideos(): array
    {
        return $this->invalidVideos;
    }

    /**
     * @param array $invalidVideos
     */
    public function setInvalidVideos(array $invalidVideos): void
    {
        $this->invalidVideos = $invalidVideos;
    }

}
