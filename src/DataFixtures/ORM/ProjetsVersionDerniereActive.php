<?php

namespace App\DataFixtures\ORM;

use Doctrine\Bundle\FixturesBundle\ORMFixtureInterface;
use Doctrine\Persistence\ObjectManager;

use App\Entity\Version;
use App\Entity\Projet;
use App\Entity\Individu;
use App\GramcServices\ServiceProjets;

/*************
 * Cette fixture synchronise les entités Projet afin que les champs
 * versionDerniere et versionActive soient correctement positionnés
 *******************************/
class ProjetsVersionDerniereActive implements ORMFixtureInterface
{
    public function __construct(ServiceProjets $sp)
    {
        $this->sp = $sp;
    }

    public function load(ObjectManager $em)
    {
        return;
        $projets = $em->getRepository(Projet::class)->findAll();
        foreach ($projets as $projet) {
            $verder = "";
            $veract = "";
            $verder = $this->sp->calculVersionDerniere($projet);
            $veract = $this->sp->calculVersionActive($projet);
            echo "projet " . $projet . " dernière = " . $verder . " active = " . $veract . "\n";
        };
        $em->flush();
    }
}
