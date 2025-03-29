<?php

namespace App\Controller\Api;

use App\Entity\Conference;
use App\Entity\Media;
use App\Service\ConferenceService;
use App\Service\MediaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * API controller for media management
 */
#[Route('/api/media')]
class MediaApiController extends AbstractController
{
    public function __construct(
        private MediaService $mediaService,
        private ConferenceService $conferenceService
    ){}
    /**
     * List media for a conference
     */
    #[Route('/conference/{id}', name: 'api_media_by_conference', methods: ['GET'])]
    public function getByConference(Conference $conference): JsonResponse
    {
        $media = $this->mediaService->getConferenceMediaByType($conference);

        return $this->json([
            'slides' => $media['slides'],
            'videos' => $media['videos'],
        ], Response::HTTP_OK, [], ['groups' => 'media:read']);
    }

    /**
     * Upload media for a conference
     */
    #[Route('/upload', name: 'api_media_upload', methods: ['POST'])]
    public function upload(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_PRESENTER');

        // Get the conference ID
        $conferenceId = $request->request->get('conference_id');
        if (!$conferenceId) {
            return $this->json(['error' => 'Conference ID is required'], Response::HTTP_BAD_REQUEST);
        }

        // Get the conference entity
        $conference = $this->conferenceService->findConferenceById($conferenceId);
        if (!$conference) {
            return $this->json(['error' => 'Conference not found'], Response::HTTP_NOT_FOUND);
        }

        // Check if the current user is the presenter of the conference
        if ($conference->getPresenter() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'You are not allowed to upload media for this conference'], Response::HTTP_FORBIDDEN);
        }

        // Get uploaded file
        $file = $request->files->get('file');
        if (!$file) {
            return $this->json(['error' => 'No file uploaded'], Response::HTTP_BAD_REQUEST);
        }

        // Get media type and title
        $type = $request->request->get('type');
        $title = $request->request->get('title');

        if (!$type || !in_array($type, [Media::TYPE_SLIDES, Media::TYPE_VIDEO])) {
            return $this->json(['error' => 'Valid type is required (slides or video)'], Response::HTTP_BAD_REQUEST);
        }

        if (!$title) {
            return $this->json(['error' => 'Title is required'], Response::HTTP_BAD_REQUEST);
        }

        // Upload media
        $media = $this->mediaService->uploadMedia($conference, $file, $title, $type);

        if (!$media) {
            return $this->json(['error' => 'Failed to upload media'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json([
            'media' => $media,
            'message' => 'Media uploaded successfully'
        ], Response::HTTP_CREATED, [], ['groups' => 'media:read']);
    }

    /**
     * Delete media
     */
    #[Route('/{id}', name: 'api_media_delete', methods: ['DELETE'])]
    public function delete(Media $media): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_PRESENTER');

        // Check if the current user is the presenter of the conference
        $conference = $media->getConference();
        if ($conference->getPresenter() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'You are not allowed to delete this media'], Response::HTTP_FORBIDDEN);
        }

        $success = $this->mediaService->deleteMedia($media);

        if (!$success) {
            return $this->json(['error' => 'Failed to delete media'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['message' => 'Media deleted successfully']);
    }
}