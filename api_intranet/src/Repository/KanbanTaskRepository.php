<?php

namespace App\Repository;

use App\Entity\KanbanTask;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<KanbanTask>
 *
 * @method KanbanTask|null find($id, $lockMode = null, $lockVersion = null)
 * @method KanbanTask|null findOneBy(array $criteria, array $orderBy = null)
 * @method KanbanTask[]    findAll()
 * @method KanbanTask[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class KanbanTaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, KanbanTask::class);
    }

}
