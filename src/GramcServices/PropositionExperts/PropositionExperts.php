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

namespace App\GramcServices\PropositionExperts;

use App\Utils\Functions;
use App\Entity\Version;
use App\Entity\Projet;
use App\Entity\Expertise;
use App\Entity\Individu;

use App\PropositionExperts\PropositionExpertsType1;
use App\PropositionExperts\PropositionExpertsType2;

use App\GramcServices\ServiceJournal;
use Doctrine\ORM\EntityManagerInterface;

/***********************************************************************************************
 * PropositionExperts - Propose un expert, l'algorithme dépend du:
 *                          - type de projet
 *                          - rattachement administratif
 *
 **********************************************************************/
abstract class PropositionExperts
{
    public function __construct(protected ServiceJournal $sj, protected EntityManagerInterface $em) {}

    abstract public function getProposition(Version $version);

    // Cherche un expert acceptable dans les versions précédentes
    // Retourne $expert, ou null si pas trouvé
    protected function getExpertVersionPrecedente($version, $exclus)
    {
        $versionPrecedente  =  $version->versionPrecedente();
        if ($versionPrecedente == null) {
            $derniereExpertise = null;
        } else {
            $derniereExpertise  =   $versionPrecedente->getOneExpertise();
        }

        if ($derniereExpertise != null) {
            $expert = $derniereExpertise->getExpert();
            if ($expert == null) {
                $this->sj->noticeMessage(__METHOD__ . ":" . __LINE__ . " L'expertise de la version précédente " .
                        $version->getIdVersion(). "(" .$derniereExpertise->getId() . ") n'a pas d'expert !");
            } elseif ($expert->isExpert() == false) {
                $this->sj->noticeMessage(__METHOD__ . ":" . __LINE__ . " L'expert de la version précédente " .
                        $version->getIdVersion(). "(" .$derniereExpertise->getId() . ") " . $expert . " n'est plus un expert");
            } elseif (array_key_exists($expert->getIdIndividu(), $exclus)) {
                $this->sj->noticeMessage(__METHOD__ . ":" . __LINE__ . " L'expert de la version précédente  " .
                        $version->getIdVersion(). "(" .$derniereExpertise->getId() . ") " . $expert . " est un collaborateur");
            } else {
                return $expert;
            }
        } elseif ($versionPrecedente != null) {
            $this->sj->noticeMessage(__METHOD__ . ":" . __LINE__ . " La version précédente " . $versionPrecedente . " n'a pas d'expertise !");
        }

        // On recherche un expert dans les versions encore antérieures
        $versions = $this->em->getRepository(Version::class)->findVersions($version);
        $dernierIdVersion = $version->getIdVersion();
        foreach ($versions as $version) {
            $expertise = $version->getOneExpertise();
            if ($expertise != null && $version->getIdVersion() != $dernierIdVersion) {
                $expert = $expertise->getExpert();
                if ($expert == null) {
                    $this->sj->errorMessage(__METHOD__ .  ":" . __LINE__ . " L'expertise de la version " .  $version->getIdVersion(). "(" .$expertise->getId() . ") n'a pas d'expert !");
                } elseif ($expert->isExpert() == false) {
                    $this->sj->noticeMessage(__METHOD__ . ":" . __LINE__ . " L'expert de la version " .
                        $version->getIdVersion(). "(" .$expertise->getId() . ") " . $expert . " n'est plus un expert");
                } elseif (array_key_exists($expert->getIdIndividu(), $exclus)) {
                    $this->sj->noticeMessage(__METHOD__ . ":" . __LINE__ . " L'expert de la version " .
                        $version->getIdVersion(). "(" .$expertise->getId() . ") " . $expert . " est un collaborateur");
                } else {
                    return $expert;
                }
            }
            //else
            //    $this->sj->noticeMessage(__METHOD__ .  ":" . __LINE__ . " II version " . $version->getIdVersion() . " n'a pas d'expertise !");
        }
        return null;
    }

    // on cherche un expert le moins sollicité et non exclus parmis la liste passée en entrée
    // Renvoie l'expert, ou null
    protected function getExpertDisponible($experts, $exclus)
    {
        if ($experts==null || count($experts)==0) {
            return null;
        }

        if (count($experts) == 1) {
            if (!array_key_exists($experts[0]->getIdIndividu(), $exclus)) {
                return $experts[0];
            } else {
                return null;
            }
        }

        // $nb_expertises contient le nombre d'experties pour chaque expert: key = nombre d'expertises, val = expert
        $nb_expertises = [];
        foreach ($experts as $expert) {
            if ($expert->isExpert() &&  ! array_key_exists($expert->getIdIndividu(), $exclus)) {
                $nb_expertises[ $this->em->getRepository(Expertise::class)->countExpertises($expert) ] = $expert;
            } elseif (! $expert->isExpert()) {
                $this->sj->errorMessage(__METHOD__  .  ":" . __LINE__ . " " .  $expert . " est proposé comme expert mais n'est pas un expert !");
            }
        }

        // On trie sur les clés et on renvoie le premier élément
        if (count($nb_expertises) != 0) {
            ksort($nb_expertises);
            return  reset($nb_expertises);
        } else {
            return null;
        }
    }
}

//~ function PropositionExpertsFactory($version)
//~ {
    //~ if ($version -> getTypeProjet() == Projet::PROJET_TEST || $this->getTypeProjet() == Projet::PROJET_FIL)
    //~ {
        //~ return new PropositionExpertsType2();
    //~ }
    //~ else
    //~ {
        //~ return new PropositionExpertsType1();
    //~ }
//~ }
