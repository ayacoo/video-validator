<?php

declare(strict_types=1);

namespace Ayacoo\VideoValidator\Event;

/**
 * Dispatched in the backend video overview module before querying videos.
 * Listeners may add additional media extensions via addExtension().
 * Only extensions registered in onlineMediaHelpers are accepted.
 */
final class ModifyVideoOverviewExtensionsEvent
{
    /**
     * @param string[]              $extensions      Extensions already shown in the module
     * @param string[]              $allowedExtensions All extensions in onlineMediaHelpers
     * @param array<string, string> $iconMap         Map of extension key => TYPO3 icon identifier
     */
    public function __construct(
        private array $extensions,
        private readonly array $allowedExtensions,
        private array $iconMap = [],
    ) {
    }

    /**
     * @return string[]
     */
    public function getExtensions(): array
    {
        return $this->extensions;
    }

    /**
     * Extensions registered in $GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['onlineMediaHelpers'].
     *
     * @return string[]
     */
    public function getAllowedExtensions(): array
    {
        return $this->allowedExtensions;
    }

    /**
     * Returns a map of extension key => TYPO3 icon identifier for all registered extensions.
     *
     * @return array<string, string>
     */
    public function getIconMap(): array
    {
        return $this->iconMap;
    }

    /**
     * Add a media extension to the overview.
     *
     * @param string $extension       Lowercase extension key (e.g. "tiktok")
     * @param string $iconIdentifier  TYPO3 icon identifier; defaults to generic video icon
     *
     * Silently ignored if the extension is not registered as an onlineMediaHelper.
     */
    public function addExtension(string $extension, string $iconIdentifier = 'mimetypes-media-video'): void
    {
        $extension = strtolower($extension);
        if (!in_array($extension, $this->allowedExtensions, true)) {
            return;
        }
        if (!in_array($extension, $this->extensions, true)) {
            $this->extensions[] = $extension;
        }
        $this->iconMap[$extension] = $iconIdentifier;
    }
}
