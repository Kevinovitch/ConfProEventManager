<?php

namespace App\Repository;

use App\Entity\Conference;
use App\Entity\Feedback;
use App\Service\UuidDatabaseService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Feedback>
 */
class FeedbackRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private UuidDatabaseService $uuidDatabaseService,
    )
    {
        parent::__construct($registry, Feedback::class);
    }

    /**
     * Get feedback statistics for a conference
     */
    public function getConferenceStats(Conference $conference): array
    {
        // Get average rating and count
        $avgAndCount = $this->uuidDatabaseService->executeAnalyticalQuery(
            ['AVG(f.rating) as avgRating', 'COUNT(f.id) as count'],
            'feedback',
            'f',
            [
                ['table' => 'registration', 'alias' => 'r', 'condition' => 'f.registration_id = r.id']
            ],
            [
                'r.conference_id' => [
                    'value' => $conference->getId(),
                    'type' => 'uuid',
                    'operator' => '='
                ]
            ]
        );

        // Get rating distribution
        $distribution = $this->uuidDatabaseService->executeAnalyticalQuery(
            ['f.rating', 'COUNT(f.id) as count'],
            'feedback',
            'f',
            [
                ['table' => 'registration', 'alias' => 'r', 'condition' => 'f.registration_id = r.id']
            ],
            [
                'r.conference_id' => [
                    'value' => $conference->getId(),
                    'type' => 'uuid',
                    'operator' => '='
                ]
            ],
            ['f.rating'],  // GROUP BY
            ['f.rating' => 'ASC']  // ORDER BY
        );

        // Format distribution
        $ratings = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        foreach ($distribution as $item) {
            $ratings[$item['rating']] = (int)$item['count'];
        }

        return [
            'avgRating' => round($avgAndCount[0]['avgRating'] ?? 0, 1),
            'count' => (int)($avgAndCount[0]['count'] ?? 0),
            'distribution' => $ratings
        ];
    }

    /**
     * Get latest feedback with comments
     */
    public function getLatestComments(Conference $conference, int $limit = 5): array
    {
        // Use a direct DBAL approach to avoid syntax problems
        $conn = $this->getEntityManager()->getConnection();

        $sql = "SELECT f.rating, f.comment, f.submitted_at as submittedAt 
            FROM feedback f
            INNER JOIN registration r ON f.registration_id = r.id
            WHERE LOWER(HEX(r.conference_id)) = :conferenceId
            AND f.comment IS NOT NULL 
            AND f.comment != ''
            ORDER BY f.submitted_at DESC
            LIMIT :limit";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue('conferenceId', str_replace('%', '', $this->uuidDatabaseService->prepareUuidForSql($conference->getId())));
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);

        $results = $stmt->executeQuery()->fetchAllAssociative();

        // We convert dates as in UuidDatabaseService
        foreach ($results as &$row) {
            if (isset($row['submittedAt']) && $row['submittedAt']) {
                try {
                    $row['submittedAt'] = new \DateTimeImmutable($row['submittedAt']);
                } catch (\Exception $e) {
                    // Ignore if not a valid date
                }
            }
        }

        return $results;
    }


    public function getAverageRating(): ?float
    {
        return $this->createQueryBuilder('f')
            ->select('AVG(f.rating)')
            ->getQuery()
            ->getSingleScalarResult();
    }

}