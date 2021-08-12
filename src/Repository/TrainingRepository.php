<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Occupation;
use App\Entity\OccupationSkill;
use App\Entity\Training;
use App\Entity\Skill;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\ResultSetMapping;
use Doctrine\ORM\Query\ResultSetMappingBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Training|null find($id, $lockMode = null, $lockVersion = null)
 * @method Training|null findOneBy(array $criteria, array $orderBy = null)
 * @method Training[]    findAll()
 * @method Training[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TrainingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Training::class);
    }

    /**
     * Recherche de formation par métier
     *
     * @access public
     */
    public function searchTrainingByOccupation(
        ?User $user = null,
        Occupation $occupation,
        ?array $params = []
    ): ?array
    {
        $entityManager = $this->getEntityManager();
        $rsm = new ResultSetMappingBuilder($entityManager);
        $rsm->addRootEntityFromClassMetadata('App\Entity\Training', 't');
        //$rsm->addFieldResult('t', 'score', 'score');
        //$rsm->addScalarResult('distance', 'distance');
        /*$rsm->addScalarResult('knowledge_coeff', 'knowledge_coeff');
        $rsm->addScalarResult('knowledge_optional_coeff', 'knowledge_optional_coeff');
        $rsm->addScalarResult('skill_coeff', 'skill_coeff');
        $rsm->addScalarResult('skill_optional_coeff', 'skill_optional_coeff');
        $rsm->addScalarResult('skill_weight', 'skill_weight');
        $rsm->addScalarResult('training_completion', 'training_completion');
        $rsm->addScalarResult('institution_completion', 'institution_completion');
        $rsm->addScalarResult('occupation_weight', 'occupation_weight');
        $rsm->addScalarResult('acquired_skill_coefficient', 'acquired_skill_coefficient');
        $rsm->addScalarResult('not_acquired_skill_coefficient', 'not_acquired_skill_coefficient');*/

        $filter = '';
        $select = '';
        $having = '';
        /*?bool $isOnline = null,
        ?bool $isOnlineMonitored = null,
        ?bool $isPresential = null,
        ?float $minPrice = null,
        ?float $maxPrice = null,
        ?int $distance = null,
        ?float $latitude = null,
        ?float $longitude = null*/
        if ($params && is_array($params)) {
            // Recherche par ville
            if (
                array_key_exists('distance', $params) &&
                array_key_exists('location', $params)
            ) {
                $select = "CASE
                    WHEN t.latitude IS NULL AND t.longitude IS NULL THEN NULL 
                    ELSE 
                    (
                        2 * 
                        ASIN(
                            (
                                SQRT(
                                    POW(COS(((PI()/180) * 49.18687424193316)) - COS(((PI()/180) * t.latitude)) * COS((PI()/180) * (-0.3658107651082919 - t.longitude)), 2) +
                                    POW(COS(((PI()/180) * t.latitude)) * SIN((PI()/180) * (-0.3658107651082919 - t.longitude)), 2) +
                                    POW(SIN(((PI()/180) * 49.18687424193316)) - SIN(((PI()/180) * t.latitude)), 2)    
                                )
                            ) / 2
                        ) / 
                        (PI()/180) *
                        ".Training::SEARCH_DEG_CONVERSION."
                    )
                END AS distance,";
                $having = " AND distance < ".intval($params['distance']);
            }

            $filterParams = [];
            if (array_key_exists('isOnline', $params) && is_bool($params['isOnline']))
                $filterParams[] = "t.is_online = " . intval($params['isOnline']);
            if (array_key_exists('isOnlineMonitored', $params) && is_bool($params['isOnlineMonitored']))
                $filterParams[] = "t.is_online_monitored = " . intval($params['isOnlineMonitored']);
            if (array_key_exists('isPresential', $params) && is_bool($params['isPresential']))
                $filterParams[] = "t.is_presential = " . intval($params['isPresential']);
            if (
                array_key_exists('minPrice', $params) && !empty($params['minPrice']) && $params['minPrice'] !== null &&
                array_key_exists('maxPrice', $params) && !empty($params['maxPrice']) && $params['minPrice'] !== null
            )
                $filterParams[] = "(t.price > " . floatval($params['minPrice']) . " AND t.price < " . floatval($params['maxPrice']) . ")";

            if (count($filterParams) > 0) {
                $filter = "WHERE (" . implode($filterParams, ' AND ') . ")";
            }
        }

        $query = $this->getEntityManager()->createNativeQuery(" 
            SELECT 
                t.*,  
                $select 
                IFNULL(sq1.weight, 0) AS skill_weight,
                IFNULL(sq1.maxWeight, 0) AS max_skill_weight,
                IFNULL(sq2.trainingCompletion, 0) AS training_completion,
                IFNULL(sq2.institutionCompletion, 0) AS institution_completion,
                IFNULL(sq2.occupationWeight, 0) AS occupation_weight,
                IFNULL(sq1.knowledgeCoeff, 0) AS knowledge_coeff,
                IFNULL(sq1.maxKnowledgeCoeff, 0) AS max_knowledge_coeff,
                IFNULL(sq1.knowledgeOptionalCoeff, 0) AS knowledge_optional_coeff,
                IFNULL(sq1.maxKnowledgeOptionalCoeff, 0) AS max_knowledge_optional_coeff,
                IFNULL(sq1.skillCoeff, 0) AS skill_coeff,
                IFNULL(sq1.maxSkillCoeff, 0) AS max_skill_coeff,
                IFNULL(sq1.skillOptionalCoeff, 0) AS skill_optional_coeff,       
                IFNULL(sq1.maxSkillOptionalCoeff, 0) AS max_skill_optional_coeff,
                IFNULL(sq3.acquiredSkillCoefficient, 0) AS acquired_skill_coefficient,
                IFNULL(sq3.notAcquiredSkillCoefficient, 0) AS not_acquired_skill_coefficient,
                (
                    IFNULL(CAST(sq1.weight AS UNSIGNED), 0) + 
                    IFNULL(CAST(sq2.occupationWeight AS UNSIGNED), 0) +
                    IFNULL(CAST(sq3.acquiredSkillCoefficient AS UNSIGNED), 0) -
                    IFNULL(CAST(sq3.notAcquiredSkillCoefficient AS UNSIGNED), 0)
                ) 
                    * CAST(sq2.institutionCompletion AS UNSIGNED) 
                    * CAST(sq2.trainingCompletion AS UNSIGNED) 
                AS score,
                (
                    IFNULL(CAST(sq1.maxWeight AS UNSIGNED), 0) + 
                    :occupationCoefficient +
                    IFNULL(CAST(sq3.maxAcquiredSkillCoefficient AS UNSIGNED), 0)
                ) 
                    * 100
                    * 100
                AS max_score
            FROM training AS t
            LEFT JOIN (
                SELECT 
                    ssq1.training_id,
                    ssq1.maxKnowledgeCoeff,
                    ssq1.maxKnowledgeOptionalCoeff,
                    ssq1.maxSkillCoeff,
                    ssq1.maxSkillOptionalCoeff,
                    ssq1.knowledgeCoeff,
                    ssq1.knowledgeOptionalCoeff,
                    ssq1.skillCoeff,
                    ssq1.skillOptionalCoeff,
                    CAST(ssq1.knowledgeCoeff AS UNSIGNED) + CAST(ssq1.knowledgeOptionalCoeff AS UNSIGNED) + CAST(ssq1.skillCoeff AS UNSIGNED) + CAST(ssq1.skillOptionalCoeff AS UNSIGNED) AS weight,
                    CAST(ssq1.maxKnowledgeCoeff AS UNSIGNED) + CAST(ssq1.maxKnowledgeOptionalCoeff AS UNSIGNED) + CAST(ssq1.maxSkillCoeff AS UNSIGNED) + CAST(ssq1.maxSkillOptionalCoeff AS UNSIGNED) AS maxWeight
                FROM (
                    SELECT
                        t.id AS training_id,
                        COALESCE(ts.id) AS ts_id,
                        SUM(IF(os.relation_type = :essentialRelationType AND os.skill_type = :knowledgeSkillType, :knowledgeCoefficient, 0)) AS maxKnowledgeCoeff,
                        SUM(IF(os.relation_type = :optionalRelationType AND os.skill_type = :knowledgeSkillType, :knowledgeOptionalCoefficient, 0)) AS maxKnowledgeOptionalCoeff,
                        SUM(IF(os.relation_type = :essentialRelationType AND os.skill_type = :skillSkillType, :skillCoefficient, 0)) AS maxSkillCoeff,
                        SUM(IF(os.relation_type = :optionalRelationType AND os.skill_type = :skillSkillType, :skillOptionalCoefficient, 0)) AS maxSkillOptionalCoeff,
                        SUM(IF(os.relation_type = :essentialRelationType AND os.skill_type = :knowledgeSkillType AND ts.id IS NOT NULL, :knowledgeCoefficient, 0)) AS knowledgeCoeff,
                        SUM(IF(os.relation_type = :optionalRelationType AND os.skill_type = :knowledgeSkillType AND ts.id IS NOT NULL, :knowledgeOptionalCoefficient, 0)) AS knowledgeOptionalCoeff,
                        SUM(IF(os.relation_type = :essentialRelationType AND os.skill_type = :skillSkillType AND ts.id IS NOT NULL, :skillCoefficient, 0)) AS skillCoeff,
                        SUM(IF(os.relation_type = :optionalRelationType AND os.skill_type = :skillSkillType AND ts.id IS NOT NULL, :skillOptionalCoefficient, 0)) AS skillOptionalCoeff
                    FROM training t
                    INNER JOIN occupation_skill os ON os.occupation_id = :occupationId 
                    LEFT JOIN training_skill AS ts ON ts.training_id = t.id AND ts.skill_id = os.skill_id AND ts.is_to_acquire = 1
                    GROUP BY t.id
                ) AS ssq1
                GROUP BY ssq1.training_id
            ) AS sq1 ON sq1.training_id = t.id
            LEFT JOIN (
                SELECT
                    t.id AS training_id,
                    t.completion AS trainingCompletion,
                    i.completion AS institutionCompletion,
                    IF (t.occupation_id IS NOT NULL AND t.occupation_id = :occupationId, :occupationCoefficient, 0) AS occupationWeight
                FROM training t
                INNER JOIN user i ON i.id = t.user_id
                GROUP BY t.id
            ) AS sq2 ON sq2.training_id = t.id
            LEFT JOIN (
                SELECT 
                    t.id AS training_id,
                    COUNT(DISTINCT(ts.id)) * :acquiredCoefficient AS maxAcquiredSkillCoefficient,
                    SUM(IF(us.id IS NOT NULL, 1, 0)) * :acquiredCoefficient AS acquiredSkillCoefficient,
                    SUM(IF(us.id IS NULL, 1, 0)) * :notAcquiredCoefficient AS notAcquiredSkillCoefficient
                FROM training t
                    INNER JOIN training_skill ts ON ts.training_id = t.id AND ts.is_required = 1
                    LEFT JOIN user_skill us ON us.skill_id = ts.skill_id AND us.is_selected = 1 AND us.user_id = :userId
                    GROUP BY t.id
            ) AS sq3 ON sq3.training_id = t.id
            $filter
            GROUP BY t.id
            HAVING score IS NOT NULL AND score > 0
            $having
            ORDER BY score DESC
            ", $rsm);
        $query->setParameter('occupationId', $occupation->getId());
        $query->setParameter('userId', ($user) ? $user->getId() : NULL);
        $query->setParameter('essentialRelationType', OccupationSkill::RELATION_TYPE_ESSENTIAL);
        $query->setParameter('optionalRelationType', OccupationSkill::RELATION_TYPE_OPTIONAL);
        $query->setParameter('knowledgeSkillType', OccupationSkill::SKILL_TYPE_KNOWLEDGE);
        $query->setParameter('skillSkillType', OccupationSkill::SKILL_TYPE_SKILL);
        $query->setParameter('occupationCoefficient', Training::SEARCH_OCCUPATION_COEFFICIENT);
        $query->setParameter('skillCoefficient', Training::SEARCH_SKILL_COEFFICIENT);
        $query->setParameter('skillOptionalCoefficient', Training::SEARCH_OPTIONAL_SKILL_COEFFICIENT);
        $query->setParameter('knowledgeCoefficient', Training::SEARCH_KNOWLEDGE_COEFFICIENT);
        $query->setParameter('knowledgeOptionalCoefficient', Training::SEARCH_OPTIONAL_KNOWLEDGE_COEFFICIENT);
        $query->setParameter('acquiredCoefficient', Training::SEARCH_ACQUIRED_REQUIRED_SKILL_COEFFICIENT);
        $query->setParameter('notAcquiredCoefficient', Training::SEARCH_NOT_ACQUIRED_REQUIRED_SKILL_COEFFICIENT);

        return $query->getResult();
    }

    /*
    --- DEBUG ---
    SET @essentialRelationType = 'essential';
    SET @optionalRelationType = 'optional';
    SET @knowledgeSkillType = 'knowledge';
    SET @skillSkillType = 'skill/competence';
    SET @knowledgeCoefficient = 2;
    SET @knowledgeOptionalCoefficient = 1;
    SET @skillCoefficient = 10;
    SET @skillOptionalCoefficient = 5;
    SET @acquiredCoefficient = 2;
    SET @notAcquiredCoefficient = 20;
    SET @occupationId = 92;
    SET @userId = 1;
    SET @occupationCoefficient = 100;

    SELECT
        t.*,
        IFNULL(sq1.weight, 0) AS skill_weight,
        IFNULL(sq1.maxWeight, 0) AS max_skill_weight,
        IFNULL(sq2.trainingCompletion, 0) AS training_completion,
        IFNULL(sq2.institutionCompletion, 0) AS institution_completion,
        IFNULL(sq2.occupationWeight, 0) AS occupation_weight,
        IFNULL(sq1.knowledgeCoeff, 0) AS knowledge_coeff,
        IFNULL(sq1.maxKnowledgeCoeff, 0) AS max_knowledge_coeff,
        IFNULL(sq1.knowledgeOptionalCoeff, 0) AS knowledge_optional_coeff,
        IFNULL(sq1.maxKnowledgeOptionalCoeff, 0) AS max_knowledge_optional_coeff,
        IFNULL(sq1.skillCoeff, 0) AS skill_coeff,
        IFNULL(sq1.maxSkillCoeff, 0) AS max_skill_coeff,
        IFNULL(sq1.skillOptionalCoeff, 0) AS skill_optional_coeff,
        IFNULL(sq1.maxSkillOptionalCoeff, 0) AS max_skill_optional_coeff,
        IFNULL(sq3.acquiredSkillCoefficient, 0) AS acquired_skill_coefficient,
        IFNULL(sq3.notAcquiredSkillCoefficient, 0) AS not_acquired_skill_coefficient,
        (
            IFNULL(CAST(sq1.weight AS UNSIGNED), 0) +
            IFNULL(CAST(sq2.occupationWeight AS UNSIGNED), 0) +
            IFNULL(CAST(sq3.acquiredSkillCoefficient AS UNSIGNED), 0) -
            IFNULL(CAST(sq3.notAcquiredSkillCoefficient AS UNSIGNED), 0)
        )
            * CAST(sq2.institutionCompletion AS UNSIGNED)
            * CAST(sq2.trainingCompletion AS UNSIGNED)
        AS score,
        (
            IFNULL(CAST(sq1.maxWeight AS UNSIGNED), 0) +
            @occupationCoefficient +
            IFNULL(CAST(sq3.maxAcquiredSkillCoefficient AS UNSIGNED), 0)
        )
            * 100
            * 100
        AS max_score
    FROM training AS t
    LEFT JOIN (
        SELECT
            ssq1.training_id,
            ssq1.maxKnowledgeCoeff,
            ssq1.maxKnowledgeOptionalCoeff,
            ssq1.maxSkillCoeff,
            ssq1.maxSkillOptionalCoeff,
            ssq1.knowledgeCoeff,
            ssq1.knowledgeOptionalCoeff,
            ssq1.skillCoeff,
            ssq1.skillOptionalCoeff,
            CAST(ssq1.knowledgeCoeff AS UNSIGNED) + CAST(ssq1.knowledgeOptionalCoeff AS UNSIGNED) + CAST(ssq1.skillCoeff AS UNSIGNED) + CAST(ssq1.skillOptionalCoeff AS UNSIGNED) AS weight,
            CAST(ssq1.maxKnowledgeCoeff AS UNSIGNED) + CAST(ssq1.maxKnowledgeOptionalCoeff AS UNSIGNED) + CAST(ssq1.maxSkillCoeff AS UNSIGNED) + CAST(ssq1.maxSkillOptionalCoeff AS UNSIGNED) AS maxWeight
        FROM (
            SELECT
                t.id AS training_id,
                COALESCE(ts.id) AS ts_id,
                SUM(IF(os.relation_type = @essentialRelationType AND os.skill_type = @knowledgeSkillType, @knowledgeCoefficient, 0)) AS maxKnowledgeCoeff,
                SUM(IF(os.relation_type = @optionalRelationType AND os.skill_type = @knowledgeSkillType, @knowledgeOptionalCoefficient, 0)) AS maxKnowledgeOptionalCoeff,
                SUM(IF(os.relation_type = @essentialRelationType AND os.skill_type = @skillSkillType, @skillCoefficient, 0)) AS maxSkillCoeff,
                SUM(IF(os.relation_type = @optionalRelationType AND os.skill_type = @skillSkillType, @skillOptionalCoefficient, 0)) AS maxSkillOptionalCoeff,
                SUM(IF(os.relation_type = @essentialRelationType AND os.skill_type = @knowledgeSkillType AND ts.id IS NOT NULL, @knowledgeCoefficient, 0)) AS knowledgeCoeff,
                SUM(IF(os.relation_type = @optionalRelationType AND os.skill_type = @knowledgeSkillType AND ts.id IS NOT NULL, @knowledgeOptionalCoefficient, 0)) AS knowledgeOptionalCoeff,
                SUM(IF(os.relation_type = @essentialRelationType AND os.skill_type = @skillSkillType AND ts.id IS NOT NULL, @skillCoefficient, 0)) AS skillCoeff,
                SUM(IF(os.relation_type = @optionalRelationType AND os.skill_type = @skillSkillType AND ts.id IS NOT NULL, @skillOptionalCoefficient, 0)) AS skillOptionalCoeff
            FROM training t
            INNER JOIN occupation_skill os ON os.occupation_id = @occupationId
            LEFT JOIN training_skill AS ts ON ts.training_id = t.id AND ts.skill_id = os.skill_id AND ts.is_to_acquire = 1
            GROUP BY t.id
        ) AS ssq1
        GROUP BY ssq1.training_id
    ) AS sq1 ON sq1.training_id = t.id
    LEFT JOIN (
        SELECT
            t.id AS training_id,
            t.completion AS trainingCompletion,
            i.completion AS institutionCompletion,
            IF (t.occupation_id IS NOT NULL AND t.occupation_id = @occupationId, @occupationCoefficient, 0) AS occupationWeight
        FROM training t
        INNER JOIN user i ON i.id = t.user_id
        GROUP BY t.id
    ) AS sq2 ON sq2.training_id = t.id
    LEFT JOIN (
        SELECT
            t.id AS training_id,
            COUNT(DISTINCT(ts.id)) * @acquiredCoefficient AS maxAcquiredSkillCoefficient,
            SUM(IF(us.id IS NOT NULL, 1, 0)) * @acquiredCoefficient AS acquiredSkillCoefficient,
            SUM(IF(us.id IS NULL, 1, 0)) * @notAcquiredCoefficient AS notAcquiredSkillCoefficient
        FROM training t
            INNER JOIN training_skill ts ON ts.training_id = t.id AND ts.is_required = 1
            LEFT JOIN user_skill us ON us.skill_id = ts.skill_id AND us.is_selected = 1 AND us.user_id = @userId
            GROUP BY t.id
    ) AS sq3 ON sq3.training_id = t.id
    GROUP BY t.id
    HAVING score IS NOT NULL AND score > 0
    ORDER BY score DESC

     */

    /*public function searchTrainingByOccupation(Occupation $occupation): ?array
    {
        $query = $this->getEntityManager()->createNativeQuery("
            SELECT
                t.*,
                CAST(sq1.weight AS UNSIGNED) + CAST(sq2.occupationWeight AS UNSIGNED) AS score
            FROM training AS t
            LEFT JOIN (
                SELECT
                    ssq1.training_id,
                    CAST(ssq1.knowledgeCoeff AS UNSIGNED) + CAST(ssq1.knowledgeOptionalCoeff AS UNSIGNED) + CAST(ssq1.skillCoeff AS UNSIGNED) + CAST(ssq1.skillOptionalCoeff AS UNSIGNED) AS weight
                FROM (
                    SELECT
                        ANY_VALUE(t.id) AS training_id,
                        SUM(IF(os.relation_type = :essentialRelationType AND os.skill_type = :knowledgeSkillType, :knowledgeCoefficient, 0)) AS knowledgeCoeff,
                        SUM(IF(os.relation_type = :optionalRelationType AND os.skill_type = :knowledgeSkillType, :knowledgeOptionalCoefficient, 0)) AS knowledgeOptionalCoeff,
                        SUM(IF(os.relation_type = :essentialRelationType AND os.skill_type = :skillSkillType, :skillCoefficient, 0)) AS skillCoeff,
                        SUM(IF(os.relation_type = :optionalRelationType AND os.skill_type = :skillSkillType, :skillOptionalCoefficient, 0)) AS skillOptionalCoeff
                    FROM training t
                    INNER JOIN occupation_skill os ON os.occupation_id = :occupationId
                    INNER JOIN training_skill ts ON ts.training_id = t.id AND os.skill_id = ts.skill_id
                    GROUP BY t.id
                ) AS ssq1
                GROUP BY ssq1.training_id
            ) AS sq1 ON sq1.training_id = t.id
            LEFT JOIN (
                SELECT
                    ANY_VALUE(t.id) AS training_id,
                    IF (toc.training_id IS NOT NULL, :occupationCoefficient, 1) AS occupationWeight
                FROM training t
                LEFT JOIN training_occupation toc ON toc.training_id = t.id AND toc.occupation_id = :occupationId
                GROUP BY t.id
            ) AS sq2 ON sq2.training_id = t.id
            GROUP BY t.id
            HAVING score IS NOT NULL AND score > 0
            ORDER BY score DESC
            ", $rsm);
        $query->setParameter('occupationId', $occupation->getId());
        $query->setParameter('essentialRelationType', OccupationSkill::RELATION_TYPE_ESSENTIAL);
        $query->setParameter('optionalRelationType', OccupationSkill::RELATION_TYPE_OPTIONAL);
        $query->setParameter('knowledgeSkillType', OccupationSkill::SKILL_TYPE_KNOWLEDGE);
        $query->setParameter('skillSkillType', OccupationSkill::SKILL_TYPE_SKILL);
        $query->setParameter('occupationCoefficient', Training::SEARCH_OCCUPATION_COEFFICIENT);
        $query->setParameter('skillCoefficient', Training::SEARCH_SKILL_COEFFICIENT);
        $query->setParameter('skillOptionalCoefficient', Training::SEARCH_OPTIONAL_SKILL_COEFFICIENT);
        $query->setParameter('knowledgeCoefficient', Training::SEARCH_KNOWLEDGE_COEFFICIENT);
        $query->setParameter('knowledgeOptionalCoefficient', Training::SEARCH_OPTIONAL_KNOWLEDGE_COEFFICIENT);

        return $query->getResult();
    }*/

    /**
     * Recherche de formation par compétence
     *
     * @access public
     */
    public function searchTrainingBySkill(Skill $skill): ?array
    {
        $entityManager = $this->getEntityManager();
        $rsm = new ResultSetMappingBuilder($entityManager);
        $rsm->addRootEntityFromClassMetadata('App\Entity\Training', 't');

        $query = $this->getEntityManager()->createNativeQuery(" 
            SELECT
                t.*,
                IF (ts.is_to_acquire = 1, 100, 50) AS score,
                100 AS max_score
            FROM training t
            INNER JOIN training_skill ts ON ts.training_id = t.id AND ts.skill_id = :skillId
            GROUP BY t.id
            ", $rsm);
        $query->setParameter('skillId', $skill->getId());

        return $query->getResult();
    }

    // /**
    //  * @return Training[] Returns an array of Training objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('t.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Training
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
