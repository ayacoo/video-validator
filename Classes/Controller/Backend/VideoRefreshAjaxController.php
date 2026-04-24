<?php

declare(strict_types=1);

namespace Ayacoo\VideoValidator\Controller\Backend;

use Ayacoo\VideoValidator\Service\VideoService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\ResourceFactory;

/**
 * AJAX endpoint for the "Refresh status" action in the Video Validator overview.
 * Delegates the actual validation to VideoService::validateFile().
 */
class VideoRefreshAjaxController
{
    public function __construct(
        private readonly VideoService $videoService,
        private readonly ResourceFactory $resourceFactory,
    ) {
    }

    public function refresh(ServerRequestInterface $request): ResponseInterface
    {
        $parsedBody = $request->getParsedBody() ?? [];
        $fileUid = (int)($parsedBody['fileUid'] ?? 0);
        if ($fileUid <= 0) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid file uid'], 400);
        }

        // Access check: reuse BE user's storage/filemount access
        try {
            $file = $this->resourceFactory->getFileObject($fileUid);
        } catch (FileDoesNotExistException) {
            return new JsonResponse(['success' => false, 'message' => 'File not found'], 404);
        }

        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if ($backendUser === null) {
            return new JsonResponse(['success' => false, 'message' => 'Not authenticated'], 401);
        }
        if (!$backendUser->isAdmin() && !isset($backendUser->getFileStorages()[$file->getStorage()->getUid()])) {
            return new JsonResponse(['success' => false, 'message' => 'Access denied'], 403);
        }

        try {
            $status = $this->videoService->validateFile($fileUid);
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return new JsonResponse(['success' => true, 'status' => $status]);
    }
}
