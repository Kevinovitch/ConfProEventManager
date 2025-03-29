<?php

namespace App\DataFixtures;

use App\Entity\Conference;
use App\Entity\Media;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Fixtures for conference media (slides and videos)
 */
class MediaFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // Get all scheduled conferences to add media to
        $conferences = $manager->getRepository(Conference::class)->findBy([
            'status' => Conference::STATUS_SCHEDULED
        ]);

        // If no scheduled conferences found, get any conference
        if (empty($conferences)) {
            $conferences = $manager->getRepository(Conference::class)->findAll();
        }

        foreach ($conferences as $conference) {
            // Add slides presentation
            $slides = new Media();
            $slides->setConference($conference);
            $slides->setType(Media::TYPE_SLIDES);
            $slides->setTitle('Presentation Slides: ' . $conference->getTitle());
            $slides->setFilename('presentation-' . $this->slugify($conference->getTitle()) . '.pdf');
            $slides->setFileSize(mt_rand(500000, 3000000)); // Random size between 500KB and 3MB
            $slides->setUrl('/uploads/media/' . $slides->getFilename());
            $manager->persist($slides);

            // Add additional slides for some conferences
            if (mt_rand(0, 1) === 1) {
                $additionalSlides = new Media();
                $additionalSlides->setConference($conference);
                $additionalSlides->setType(Media::TYPE_SLIDES);
                $additionalSlides->setTitle('Additional Resources: ' . $conference->getTitle());
                $additionalSlides->setFilename('resources-' . $this->slugify($conference->getTitle()) . '.pdf');
                $additionalSlides->setFileSize(mt_rand(200000, 1000000));
                $additionalSlides->setUrl('/uploads/media/' . $additionalSlides->getFilename());
                $manager->persist($additionalSlides);
            }

            // Add video recording
            $video = new Media();
            $video->setConference($conference);
            $video->setType(Media::TYPE_VIDEO);
            $video->setTitle('Full Recording: ' . $conference->getTitle());
            $video->setFilename('recording-' . $this->slugify($conference->getTitle()) . '.mp4');
            $video->setFileSize(mt_rand(5000000, 50000000)); // Random size between 5MB and 50MB
            $video->setUrl('/uploads/media/' . $video->getFilename());
            $manager->persist($video);

            // Add highlights video for some conferences
            if (mt_rand(0, 1) === 1) {
                $highlights = new Media();
                $highlights->setConference($conference);
                $highlights->setType(Media::TYPE_VIDEO);
                $highlights->setTitle('Highlights: ' . $conference->getTitle());
                $highlights->setFilename('highlights-' . $this->slugify($conference->getTitle()) . '.mp4');
                $highlights->setFileSize(mt_rand(2000000, 10000000));
                $highlights->setUrl('/uploads/media/' . $highlights->getFilename());
                $manager->persist($highlights);
            }
        }

        $manager->flush();
    }

    /**
     * Get dependencies for this fixture
     */
    public function getDependencies(): array
    {
        return [
            ConferenceFixtures::class,
        ];
    }

    /**
     * Simple slugify function to create filenames
     */
    private function slugify(string $text): string
    {
        // Replace non letter or digits by -
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        // Transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        // Remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);
        // Trim
        $text = trim($text, '-');
        // Remove duplicate -
        $text = preg_replace('~-+~', '-', $text);
        // Lowercase
        $text = strtolower($text);

        return $text;
    }
}