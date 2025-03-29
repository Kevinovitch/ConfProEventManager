<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class UuidDatabaseService
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Converts a hexadecimal ID to UUID format
     */
    public function convertHexToUuidFormat(string $hex): string
    {
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20)
        );
    }

    /**
     * Prepares a UUID for SQL comparison
     */
    public function prepareUuidForSql(Uuid $uuid): string
    {
        return '%' . str_replace('-', '', strtolower($uuid->__toString())) . '%';
    }

    /**
     * Finds entities based on a UUID
     */
    public function findEntitiesByUuidField(
        string $tableName,
        string $fieldName,
        Uuid $fieldValue,
        string $entityClass,
        array $orderBy = []
    ): array {
        $conn = $this->entityManager->getConnection();

        $sql = "SELECT LOWER(HEX(id)) as id_hex FROM {$tableName} WHERE LOWER(HEX({$fieldName})) LIKE :fieldValue";

        // Ajouter ORDER BY si nécessaire
        if (!empty($orderBy)) {
            $sql .= " ORDER BY ";
            $orderClauses = [];
            foreach ($orderBy as $field => $direction) {
                $orderClauses[] = "{$field} {$direction}";
            }
            $sql .= implode(', ', $orderClauses);
        }

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'fieldValue' => $this->prepareUuidForSql($fieldValue)
        ])->fetchAllAssociative();

        $entities = [];
        foreach ($result as $row) {
            $entityUuid = Uuid::fromString($this->convertHexToUuidFormat($row['id_hex']));
            $entity = $this->entityManager->getRepository($entityClass)->find($entityUuid);
            if ($entity) {
                $entities[] = $entity;
            }
        }

        return $entities;
    }

    /**
     * Finds a single entity based on a UUID
     */
    public function findOneEntityByUuidFields(
        string $tableName,
        array $conditions,
        string $entityClass
    ) {
        $conn = $this->entityManager->getConnection();

        $sql = "SELECT LOWER(HEX(id)) as id_hex FROM {$tableName} WHERE ";

        $whereClauses = [];
        $parameters = [];

        foreach ($conditions as $field => $value) {
            $paramName = 'param_' . $field;
            $whereClauses[] = "LOWER(HEX({$field})) LIKE :{$paramName}";
            $parameters[$paramName] = $this->prepareUuidForSql($value);
        }

        $sql .= implode(' AND ', $whereClauses);
        $sql .= " LIMIT 1";

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery($parameters)->fetchAssociative();

        if (!$result) {
            return null;
        }

        $entityUuid = Uuid::fromString($this->convertHexToUuidFormat($result['id_hex']));
        return $this->entityManager->getRepository($entityClass)->find($entityUuid);
    }

    /**
     * Counts entities according to a UUID
     */
    public function countEntitiesByUuidField(
        string $tableName,
        string $fieldName,
        Uuid $fieldValue,
        array $additionalConditions = []
    ): int {
        $conn = $this->entityManager->getConnection();

        $sql = "SELECT COUNT(*) as count FROM {$tableName} WHERE LOWER(HEX({$fieldName})) LIKE :fieldValue";

        $parameters = [
            'fieldValue' => $this->prepareUuidForSql($fieldValue)
        ];

        // Additional conditions
        foreach ($additionalConditions as $field => $value) {
            $paramName = 'param_' . $field;
            $sql .= " AND {$field} = :{$paramName}";
            $parameters[$paramName] = $value;
        }

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery($parameters)->fetchAssociative();

        return (int)$result['count'];
    }

    /**
     * Finds entities based on UUID field and additional custom conditions
     *
     * @param string $tableName Table name in database
     * @param array $conditions Array of conditions with format:
     *        - For UUID fields: ['field_name' => ['value' => $uuid, 'operator' => 'LIKE|=|!=', 'type' => 'uuid']]
     *        - For regular fields: ['field_name' => ['value' => $value, 'operator' => '=|!=|<|>|<=|>=|LIKE']]
     * @param string $entityClass Fully qualified entity class name
     * @param array $orderBy Order by clauses ['field' => 'ASC|DESC']
     * @return array Found entities
     */
    public function findEntitiesWithCustomConditions(
        string $tableName,
        array $conditions,
        string $entityClass,
        array $orderBy = []
    ): array {
        $conn = $this->entityManager->getConnection();

        $sql = "SELECT LOWER(HEX(id)) as id_hex FROM {$tableName} WHERE 1=1";
        $parameters = [];

        // Process all conditions
        foreach ($conditions as $field => $condition) {
            // Default values
            $value = $condition['value'] ?? null;
            $operator = $condition['operator'] ?? '=';
            $type = $condition['type'] ?? 'regular';
            $paramName = 'param_' . str_replace('.', '_', $field);

            if ($type === 'uuid' && $value instanceof Uuid) {
                if ($operator === 'LIKE') {
                    $sql .= " AND LOWER(HEX({$field})) LIKE :{$paramName}";
                    $parameters[$paramName] = $this->prepareUuidForSql($value);
                } else {
                    $sql .= " AND LOWER(HEX({$field})) {$operator} :{$paramName}";
                    // Remove % wildcards for exact comparisons
                    $parameters[$paramName] = str_replace('%', '', $this->prepareUuidForSql($value));
                }
            } else {
                $sql .= " AND {$field} {$operator} :{$paramName}";
                $parameters[$paramName] = $value;
            }
        }

        // Add ORDER BY if needed
        if (!empty($orderBy)) {
            $sql .= " ORDER BY ";
            $orderClauses = [];
            foreach ($orderBy as $field => $direction) {
                $orderClauses[] = "{$field} {$direction}";
            }
            $sql .= implode(', ', $orderClauses);
        }

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery($parameters)->fetchAllAssociative();

        // Build entities
        $entities = [];
        foreach ($result as $row) {
            $entityUuid = Uuid::fromString($this->convertHexToUuidFormat($row['id_hex']));
            $entity = $this->entityManager->getRepository($entityClass)->find($entityUuid);
            if ($entity) {
                $entities[] = $entity;
            }
        }

        return $entities;
    }

    /**
     * Perform analytical queries with UUID relationships
     *
     * This method is optimized for complex analytical queries like aggregations,
     * that require joining tables with UUID relationships.
     *
     * @param array $select Select expressions ['f.field', 'COUNT(f.id) as count', 'AVG(f.rating) as avgRating']
     * @param string $fromTable Base table name
     * @param string $fromAlias Alias for the base table
     * @param array $joins Array of table joins [
     *    ['table' => 'other_table', 'alias' => 'o', 'condition' => 'o.id = f.other_id', 'type' => 'INNER|LEFT|RIGHT']
     * ]
     * @param array $conditions Where conditions in format similar to findEntitiesWithCustomConditions
     * @param array $groupBy Optional GROUP BY fields ['f.category', 'f.status']
     * @param array $orderBy Optional ORDER BY clauses ['f.date' => 'DESC']
     * @param int|null $limit Optional result limit
     * @param bool $convertDates Whether to convert date strings to DateTime objects in results
     * @param array $dateFields Fields that should be converted to DateTime objects
     * @return array Query results as associative array
     */
    public function executeAnalyticalQuery(
        array $select,
        string $fromTable,
        string $fromAlias,
        array $joins = [],
        array $conditions = [],
        array $groupBy = [],
        array $orderBy = [],
        ?int $limit = null,
        bool $convertDates = true,
        array $dateFields = ['created_at', 'updated_at', 'submitted_at', 'scheduled_at']
    ): array {
        $conn = $this->entityManager->getConnection();

        // Build SELECT clause
        $selectClause = implode(', ', $select);

        // Start building the query
        $sql = "SELECT {$selectClause} FROM {$fromTable} {$fromAlias}";

        // Add JOINs
        foreach ($joins as $join) {
            $type = $join['type'] ?? 'INNER';
            $sql .= " {$type} JOIN {$join['table']} {$join['alias']} ON {$join['condition']}";
        }

        // Add WHERE conditions
        $sql .= " WHERE 1=1";
        $parameters = [];

        foreach ($conditions as $field => $condition) {
            // Default values
            $value = $condition['value'] ?? null;
            $operator = $condition['operator'] ?? '=';
            $type = $condition['type'] ?? 'regular';
            $paramName = 'param_' . str_replace(['.', '-'], '_', $field);

            if ($type === 'uuid' && $value instanceof Uuid) {
                if ($operator === 'LIKE') {
                    $sql .= " AND LOWER(HEX({$field})) LIKE :{$paramName}";
                    $parameters[$paramName] = $this->prepareUuidForSql($value);
                } else {
                    $sql .= " AND LOWER(HEX({$field})) {$operator} :{$paramName}";
                    // Remove % wildcards for exact comparisons
                    $parameters[$paramName] = str_replace('%', '', $this->prepareUuidForSql($value));
                }
            } else {
                $sql .= " AND {$field} {$operator} :{$paramName}";
                $parameters[$paramName] = $value;
            }
        }

        // Add GROUP BY if needed
        if (!empty($groupBy)) {
            $sql .= " GROUP BY " . implode(', ', $groupBy);
        }

        // Add ORDER BY if needed
        if (!empty($orderBy)) {
            $sql .= " ORDER BY ";
            $orderClauses = [];
            foreach ($orderBy as $field => $direction) {
                $orderClauses[] = "{$field} {$direction}";
            }
            $sql .= implode(', ', $orderClauses);
        }

        // Add LIMIT if specified
        if ($limit !== null) {
            $sql .= " LIMIT {$limit}";
        }

        // Execute query
        $stmt = $conn->prepare($sql);
        foreach ($parameters as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $results = $stmt->executeQuery()->fetchAllAssociative();

        // Convert date strings to DateTime objects if requested
        if ($convertDates) {
            foreach ($results as &$row) {
                foreach ($dateFields as $dateField) {
                    if (isset($row[$dateField]) && $row[$dateField]) {
                        try {
                            $row[$dateField] = new \DateTimeImmutable($row[$dateField]);
                        } catch (\Exception $e) {
                            // Skip if not a valid date
                        }
                    }
                }
            }
        }

        return $results;
    }

    /**
     * Execute a scalar analytical query (returning a single value)
     *
     * @param string $expression The expression to calculate (e.g., "AVG(f.rating)")
     * @param string $fromTable Base table name
     * @param string $fromAlias Alias for the base table
     * @param array $joins Table joins
     * @param array $conditions Where conditions
     * @return mixed The scalar result
     */
    public function executeScalarAnalyticalQuery(
        string $expression,
        string $fromTable,
        string $fromAlias,
        array $joins = [],
        array $conditions = []
    ): mixed {
        $results = $this->executeAnalyticalQuery(
            [$expression . ' as scalar_result'],
            $fromTable,
            $fromAlias,
            $joins,
            $conditions,
            [],
            [],
            1,
            false
        );

        if (empty($results)) {
            return null;
        }

        return $results[0]['scalar_result'] ?? null;
    }
}