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

namespace App\GramcServices;

use App\Entity\Individu;
use App\Entity\CollaborateurVersion;
use App\Entity\Expertise;
use App\Entity\Journal;
use App\Entity\Rallonge;
use App\Entity\Sso;
use App\Entity\Thematique;

use App\GramcServices\ServiceJournal;

use Doctrine\ORM\EntityManagerInterface;

/********************
 * Ce service permet de créer et envoyer des invitaiotn pour de nouveaux utilisateurs
 ********************/
 
class ServiceIndividus
{
    public function __construct(private ServiceJournal $sj, private EntityManagerInterface $em){}


    /*****************
     * Remplace l'utilisateur $individu par l'utilisateur $new_individu
     *
     * Utilisé lorsqu'on fusionne des comptes: tous les objets liés à $individu
     * sont maintenant attribués à $new_individu
     *************************************************************************/
    public function fusionneIndividus(Individu $individu, Individu $new_individu): void
    {
        $em = $this->em;
        $sj = $this->sj;

        $CollaborateurVersion = $em->getRepository(CollaborateurVersion::class)->findBy(['collaborateur' => $individu]);
        $Expertise = $em->getRepository(Expertise ::class)->findBy(['expert' => $individu]);
        $Journal = $em->getRepository(Journal::class)->findBy(['individu' => $individu]);
        $Rallonge = $em->getRepository(Rallonge::class)->findBy(['expert' => $individu]);
        $Sso = $em->getRepository(Sso::class)->findBy(['individu' => $individu]);
        $Thematique = $individu->getThematique();

        // Supprimer les thématiques dont $individu est expert
        // Attention, $new_individu ne reprend pas ces thématiques
        // il faudra éventuellement refaire l'affectation'
        foreach ($individu->getThematique() as $item) {
            //$em->persist($item);
            $item->getExpert()->removeElement($individu);
        }

        // Les projets dont je suis collaborateur - Attention aux éventuels doublons
        foreach ($CollaborateurVersion  as $item) {
            if (! $item->getVersion()->isCollaborateur($new_individu)) {
                $item->setCollaborateur($new_individu);
            } else {
                $em->remove($item);
            }
        }

        // On fait reprendre les Sso par le nouvel individu
        $sso_de_new = $new_individu->getSso();
        $array_eppn=[];
        foreach ($new_individu->getSso() as $item) {
            $array_eppn[] = $item->getEppn();
        }

        foreach ($Sso  as $item) {
            if (!in_array($item->getEppn(),$array_eppn)) {
                $item->setIndividu($new_individu);
                $em->persist($item);
            } else {
                $em->remove($item);
            }
        }

        // Mes expertises
        foreach ($Expertise  as $item) {
            $item->setExpert($new_individu);
        }

        // Mes rallonges
        foreach ($Rallonge  as $item) {
            $item->setExpert($new_individu);
        }

        // Les entrées de journal (sinon on ne pourra pas supprimer l'ancien individu)
        foreach ($Journal  as $item) {
            $item->setIndividu($new_individu);
        }

        // Une entrée de journal
        $sj->infoMessage('Utilisateur ' . $individu . '(' .  $individu->getIdIndividu()
            . ') remplacé par ' . $new_individu . ' (' .  $new_individu->getIdIndividu() . ')');
    }
}
