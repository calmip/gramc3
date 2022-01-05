<?php

namespace App\Repository;

use App\Entity\Formation;


/**
 * @ method Formation|null find($id, $lockMode = null, $lockVersion = null)
 * @ method Formation|null findOneBy(array $criteria, array $orderBy = null)
 * @ method Formation[]    findAll()
 * @ method Formation[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FormationRepository extends \Doctrine\ORM\EntityRepository
{
    /* Renvoie les formations pour lesquelles le numéro d'ordre est < 10 */
    public function getFormationsPourVersion()
    {
        return $this->createQueryBuilder('f')
            ->where('f.numeroForm < 10')
            ->orderBy('f.numeroForm', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // /**
    //  * @return Formation[] Returns an array of Formation objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('f.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Formation
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
