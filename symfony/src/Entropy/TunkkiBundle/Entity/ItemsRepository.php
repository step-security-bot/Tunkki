<?php

namespace Entropy\TunkkiBundle\Entity;

/**
 * VarastoRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ItemsRepository extends \Doctrine\ORM\EntityRepository
{
	public function getAllItemChoices()
	{
		$queryBuilder = $this->createQueryBuilder('i')
				->leftJoin('i.packages', 'p')
				->andWhere('p IS NULL')
				->orderBy('i.name', 'ASC');
		return $queryBuilder->getQuery()->getResult();
	}
	public function getItemChoicesWithPrivileges($privileges)
	{
		$queryBuilder = $this->createQueryBuilder('i')
				   ->Where('i.cannotBeRented = false')
				   ->andWhere('i.rent >= 0.00')
				   ->andWhere('i.toSpareParts = false')
				   ->andWhere('i.forSale = false')
				   ->leftJoin('i.packages', 'p')
				   ->andWhere('p IS NULL')
				   ->leftJoin('i.whoCanRent', 'r')
				   ->andWhere('r = :privilege')
				   ->setParameter('privilege', $privileges)
				   ->orderBy('i.name', 'ASC');
		return $queryBuilder->getQuery()->getResult();
	}
}
