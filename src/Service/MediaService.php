<?php

namespace App\Service;

use App\Entity\Conference;
use App\Entity\Media;
use App\Repository\MediaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Service for managing conference media
 */
class MediaService
{

    public function __construct(
        private EntityManagerInterface $entityManager,
        private MediaRepository $mediaRepository,
        private NotificationService $notificationService,
        private SluggerInterface $slugger,
        private string $uploadDir
    ) {

    }


    /**
     * Upload a new media file
     */
    public function uploadMedia(Conference $conference, UploadedFile $file, string $title, string $type): ?Media
    {
        // Create safe filename
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $this->slugger->slug($originalFilename);
        $newFilename = $safeFilename . '-' . uniqid() . '.' . $file->guessExtension();

        $tempPath = $file->getPathname();
        $targetPath = $this->uploadDir . '/' . $newFilename;

        // We use copy() instead of move() to avoid WSL/Windows cross-file system problems.
        if (!copy($tempPath, $targetPath)) {
            // Log the error if the copy fails
            throw new \RuntimeException("Failure of the copy of '$tempPath' to '$targetPath'");
        }

        // Create media entity
        $media = new Media();
        $media->setConference($conference);
        $media->setTitle($title);
        $media->setType($type);
        $media->setFilename($newFilename);
        $media->setFileSize($file->getSize());
        $media->setUrl('/uploads/media/' . $newFilename); // Relative path in relation to public/

        // Save to database
        $this->entityManager->persist($media);
        $this->entityManager->flush();

        // Notify participants
        $this->notificationService->notifyMediaPublished($conference, $media);

        return $media;
    }

    /**
     * Delete a media file
     */
    public function deleteMedia(Media $media): bool
    {
        // Remove file
        $fullPath = $this->uploadDir . '/' . $media->getFilename();
        if (file_exists($fullPath)) {
            unlink($fullPath);
        }

        // Remove entity
        $this->entityManager->remove($media);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Get media for a conference by type
     */
    public function getConferenceMediaByType(Conference $conference): array
    {
        $media = $this->mediaRepository->findBy(['conference' => $conference]);

        $groupedMedia = [
            'slides' => [],
            'videos' => []
        ];

        foreach ($media as $item) {
            if ($item->getType() === Media::TYPE_SLIDES) {
                $groupedMedia['slides'][] = $item;
            } else {
                $groupedMedia['videos'][] = $item;
            }
        }

        return $groupedMedia;
    }
}
