<?php

/**
 * This file is part of GRAMC (Computing Ressource Granting Software)
 * GRAMC stands for : Gestion des Ressources et de leurs Attributions pour Mésocentre de Calcul
 *
 * GRAMC is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 *  GRAMC is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with GRAMC.  If not, see <http://www.gnu.org/licenses/>.
 *
 *  authors : Emmanuel Courcelle - C.N.R.S. - UMS 3667 - CALMIP
 *            Nicolas Renon - Université Paul Sabatier - CALMIP
 **/


/************
 * Voir https://symfony.com/index.php/doc/4.4/doctrine/events.html#doctrine-entity-listeners
 *
 * Lorsqu'une nouvelle version est créée ou lorsqu'une version est supprimée, on recalcule
 * la dernière version et on met à jour le champ correspondant du projet
 *
 * Lorsqu'une version est active, on met à jour l'entité Projet correspondante
 *
 * cela permet de garder une cohérence dans la base de données
 *
 **************************************/

namespace App\EventListener;

use App\Entity\Version;
use App\GramcServices\ServiceProjets;

use Doctrine\Persistence\Event\LifecycleEventArgs;
use Doctrine\ORM\EntityManagerInterface;

class ProjetDerniereVersion
{
    private $sp;
    private $em;

    public function __construct(ServiceProjets $sp, EntityManagerInterface $em)
    {
        $this->sp = $sp;
        $this->em = $em;
    }

    public function postPersist(Version $version, LifecycleEventArgs $event): void
    {
        $projet = $version->getProjet();
        //$this->sp->calculVersionDerniere($projet);
        $projet->setVersionDerniere($version);
        $this->em->persist($projet);
        $this->em->flush();
    }
    public function postRemove(Version $version, LifecycleEventArgs $event): void
    {
        $projet = $version->getProjet();
        $this->sp->calculVersionDerniere($projet);
        $this->em->persist($projet);
        $this->em->flush();		// ne marche pas si on ne met pas flush ici
    }
    public function postUpdate(Version $version, LifecycleEventArgs $event): void
    {
        $projet = $version->getProjet();
        $this->sp->calculVersionDerniere($projet);
        $this->em->persist($projet);
        $this->em->flush();
    }
}
