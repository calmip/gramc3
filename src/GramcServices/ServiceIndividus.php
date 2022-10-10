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
use App\Entity\CommentaireExpert;
use App\Entity\Expertise;
use App\Entity\Journal;
use App\Entity\Rallonge;
use App\Entity\Version;
use App\Entity\Sso;
use App\Entity\Thematique;

use App\GramcServices\ServiceJournal;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Doctrine\ORM\EntityManagerInterface;

/********************
 * Ce service permet de créer et envoyer des invitaiotn pour de nouveaux utilisateurs
 ********************/
 
class ServiceIndividus
{
    public function __construct(private ServiceJournal $sj, private TokenStorageInterface $ts, private EntityManagerInterface $em){}


    /*****************
     * Remplace l'utilisateur $individu par l'utilisateur $new_individu
     *
     * Utilisé lorsqu'on fusionne des comptes: tous les objets liés à $individu
     * sont maintenant attribués à $new_individu
     * 
     * NOTE - C'est le code appelant qui devra faire le flush()
     *************************************************************************/
    public function fusionnerIndividus(Individu $individu, Individu $new_individu): void
    {
        $em = $this->em;
        $sj = $this->sj;
        $ts = $this->ts;

        $connected = $ts->getToken()->getUser();
        if ($individu->getId() === $connected->getId())
        {
            $sj->throwException(__METHOD__ . ':' . __LINE__ . " Pas possible de fusionner $individu, car vous êtes cet individu !");
        }
        $CollaborateurVersion = $em->getRepository(CollaborateurVersion::class)->findBy(['collaborateur' => $individu]);
        $Expertise = $em->getRepository(Expertise ::class)->findBy(['expert' => $individu]);
        $Journal = $em->getRepository(Journal::class)->findBy(['individu' => $individu]);
        $Rallonge = $em->getRepository(Rallonge::class)->findBy(['expert' => $individu]);
        $Sso = $em->getRepository(Sso::class)->findBy(['individu' => $individu]);
        $Thematique = $individu->getThematique();
        $commentaires =  $em->getRepository(CommentaireExpert::class)->findBy(['expert' => $individu]);

        // Supprimer les thématiques dont $individu est expert
        // Attention, $new_individu ne reprend pas ces thématiques
        // il faudra éventuellement refaire l'affectation
        foreach ($individu->getThematique() as $item) {
            //$em->persist($item);
            $item->getExpert()->removeElement($individu);
        }

        // Les projets dont je suis collaborateur - Attention aux éventuels doublons
        foreach ($CollaborateurVersion  as $item) {
            if (! $item->getVersion()->isCollaborateur($new_individu)) {
                $item->setCollaborateur($new_individu);
                $em->persist($item);
            } else {
                $em->remove($item);
            }
            $version = $item->getVersion();
            $majInd = $version->getMajInd();
            if ($majInd == $individu)
            {
                $version->setMajInd($new_individu);
                $em->persist($version);
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

        // Mes commentaires d'expert
        foreach ($commentaires as $item) {
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

        // Le profil: si le profil de $new_individu est incomplet, on prend les informations de $individu
        //            Seulement nom/prénom/statut/laboratoire/établissement
        $this->copierProfil($individu, $new_individu);
    }

    /*********************************************************
     * copie le profil de $individu sur $new_individu
     * ATTENTION:
     *     1/ Ne copie que les champs nom/prénom/statut/laboratoire/établissement
     *     2/ A CONDITION que le champ de $new_individu soit VIDE !
     **************************************/
    private function copierProfil(Individu $individu, Individu $new_individu)
    {
        $em = $this->em;
        
        if ($this->validerProfil($new_individu)) return;
        if ($new_individu->getPrenom() == null) $new_individu->setPrenom($individu->getPrenom());
        if ($new_individu->getNom() == null) $new_individu->setNom($individu->getNom());
        if ($new_individu->getLabo() == null) $new_individu->setLabo($individu->getLabo());
        if ($new_individu->getEtab() == null) $new_individu->setEtab($individu->getEtab());
        if ($new_individu->getStatut() == null) $new_individu->setStatut($individu->getStatut());

        $em->persist($new_individu);
    }
    
    /*********************************
     * valide le profil de l'utilisateur passé en paramètre
     * 
     * Un profil est valide si tous les champs sont remplis
     * 
     **********************************/
     public function validerProfil(Individu $individu): bool
     {
         $ok = true;
         if ($individu->getPrenom() == null) $ok = false;
         if ($individu->getNom() == null) $ok = false;
         if ($individu->getMail() == null) $ok = false;
         if ($individu->getStatut() == null) $ok = false;
         if ($individu->getLabo() == null) $ok = false;
         if ($individu->getEtablissement() == null) $ok = false;
         return $ok;
     }
}
