<?php

	namespace App\DataFixtures\ORM;

	use Doctrine\Bundle\FixturesBundle\ORMFixtureInterface;
	use Doctrine\Persistence\ObjectManager;

	use App\Entity\Projet;
	use App\Entity\Version;
	use App\Entity\Expertise;
	use App\Entity\CollaborateurVersion;

	use App\Utils\Etat;

	// Cette fixture ne fait rien
	// Elle ne sert qu'à éviter à bin/console doctrine:fixtures:load de renvoyer une erreur !
	class None  implements ORMFixtureInterface
	{
		public function load(ObjectManager $em) {}
	}

