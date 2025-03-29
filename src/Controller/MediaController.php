<?php

namespace App\Controller;

use App\Entity\Conference;
use App\Entity\Media;
use App\Form\MediaType;
use App\Service\MediaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Web controller for media management
 */
#[Route('/media')]
class MediaController extends AbstractController
{
    public function __construct(
        private MediaService $mediaService
    ){}
    /**
     * List all media for a conference
     */
    #[Route('/conference/{id}', name: 'app_media_conference', methods: ['GET'])]
    public function conferenceMedia(Conference $conference): Response
    {
        $groupedMedia = $this->mediaService->getConferenceMediaByType($conference);

        return $this->render('media/conference.html.twig', [
            'conference' => $conference,
            'slides' => $groupedMedia['slides'],
            'videos' => $groupedMedia['videos'],
            'can_upload' => $this->isGranted('ROLE_ADMIN') || ($this->isGranted('ROLE_PRESENTER') && $conference->getPresenter() === $this->getUser())
        ]);
    }

    /**
     * Upload new media
     */
    #[Route('/upload/{id}', name: 'app_media_upload', methods: ['GET', 'POST'])]
    public function upload(Conference $conference, Request $request): Response
    {
        // Check if user is the presenter of the conference or an admin
        if (!$this->isGranted('ROLE_ADMIN') && (!$this->isGranted('ROLE_PRESENTER') || $conference->getPresenter() !== $this->getUser())) {
            throw $this->createAccessDeniedException('You are not allowed to upload media for this conference');
        }

        $media = new Media();
        $media->setConference($conference);

        $form = $this->createForm(MediaType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // Manual validation of critical fields
            $title = $form->get('title')->getData();
            $type = $form->get('type')->getData();
            $file = $form->get('file')->getData();

            $errors = [];

            // Manual validation of critical fields
            if (empty($title)) {
                $errors[] = 'The title cannot be empty';
            }

            if (empty($type)) {
                $errors[] = 'The type must be specified';
            }

            if (!$file) {
                $errors[] = 'No file selected';
            } else if (!in_array($file->getMimeType(), [
                'application/pdf',
                'application/vnd.ms-powerpoint',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'video/mp4',
                'video/webm',
                'video/ogg'
            ])) {
                $errors[] = 'Unauthorized file type';
            }

            if (empty($errors)) {
                try {
                    $this->mediaService->uploadMedia(
                        $conference,
                        $file,
                        $title,
                        $type
                    );

                    $this->addFlash('success', 'Media uploaded successfully');
                    return $this->redirectToRoute('app_media_conference', ['id' => $conference->getId()]);
                } catch (\Exception $e) {
                    $errors[] = 'Upload error: ' . $e->getMessage();
                }
            }

            // Display errors
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->render('media/upload.html.twig', [
            'conference' => $conference,
            'form' => $form,
        ]);

    }

    /**
     * Delete media
     */
    #[Route('/{id}/delete', name: 'app_media_delete', methods: ['POST'])]
    public function delete(Media $media, Request $request): Response
    {
        $conference = $media->getConference();

        // Check if user is the presenter of the conference or an admin
        if (!$this->isGranted('ROLE_ADMIN') && (!$this->isGranted('ROLE_PRESENTER') || $conference->getPresenter() !== $this->getUser())) {
            throw $this->createAccessDeniedException('You are not allowed to delete this media');
        }

        if ($this->isCsrfTokenValid('delete'.$media->getId(), $request->request->get('_token'))) {
            $success = $this->mediaService->deleteMedia($media);

            if ($success) {
                $this->addFlash('success', 'Media deleted successfully');
            } else {
                $this->addFlash('error', 'Failed to delete media');
            }
        } else {
            $this->addFlash('error', 'Invalid CSRF token');
        }

        return $this->redirectToRoute('app_media_conference', ['id' => $conference->getId()]);
    }
}