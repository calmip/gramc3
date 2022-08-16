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
 *  authors : Miloslav Grundmann - C.N.R.S. - UMS 3667 - CALMIP
 *            Emmanuel Courcelle - C.N.R.S. - UMS 3667 - CALMIP
 *            Nicolas Renon - Université Paul Sabatier - CALMIP
 **/

namespace App\Repository;

use App\GramcServices\Etat;
use App\Entity\Session;
use App\Entity\Projet;

/**
 * VersionRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class VersionRepository extends \Doctrine\ORM\EntityRepository
{
    public function findNonTermines()
    {
        return $this->getEntityManager()
                   ->createQuery('SELECT p FROM App:Version p WHERE ( NOT p.etatVersion = :termine AND NOT p.etatVersion = :annule)')
                   ->setParameter('termine', Etat::getEtat('TERMINE'))
                   ->setParameter('annule', Etat::getEtat('ANNULE'))
                   ->getResult();
    }

    public function findOneVersion(Session $session, Projet $projet)
    {
        return $this->getEntityManager()
            ->createQuery('SELECT partial v.{idVersion,etatVersion,prjTitre,prjLLabo,prjThematique,demHeures,attrHeures,penalHeures} FROM App:Version v WHERE ( v.projet = :projet AND v.session = :session AND NOT v.etatVersion = :annule)')
            ->setParameter('annule', Etat::getEtat('ANNULE'))
            ->setParameter('projet', $projet)
            ->setParameter('session', $session)
            ->getOneOrNullResult();
    }

    public function findVersions($projet)
    {
        return $this->getEntityManager()
        ->createQuery('SELECT partial v.{idVersion,etatVersion,prjTitre,prjLLabo,demHeures,attrHeures,politique}  FROM App:Version v WHERE ( v.projet = :projet AND NOT v.etatVersion = :annule) ORDER BY v.idVersion ASC')
        ->setParameter('annule', Etat::getEtat('ANNULE'))
        ->setParameter('projet', $projet)
        ->getResult();
    }

    public function findSessionVersions($session)
    {
        return $this->getEntityManager()
        ->createQuery('SELECT partial v.{idVersion,etatVersion,prjGenciDari,prjTitre,prjLLabo,demHeures,attrHeures,penalHeures,politique,sondVolDonnPerm,prjResume,dataMetaDataFormat}  FROM App:Version v JOIN v.session s WHERE ( s = :session AND NOT v.etatVersion = :annule)')
        ->setParameter('annule', Etat::getEtat('ANNULE'))
        ->setParameter('session', $session)
        ->getResult();
    }

    public function findVersionsAnnee($annee)
    {
        // 2022 -> 22
        $subAnnee = substr(strval($annee), -2);

        return $this->getEntityManager()
        ->createQuery('SELECT partial v.{idVersion,etatVersion,prjGenciDari,prjTitre,prjLLabo,demHeures,attrHeures,politique}  FROM App:Version v  WHERE ( v.idVersion LIKE :pattern AND NOT v.etatVersion = :annule)')
        ->setParameter('annule', Etat::getEtat('ANNULE'))
        ->setParameter('pattern', $subAnnee . '%')
        //->setParameter('pattern', $annee . 'T' . $annee)
        ->getResult();
    }


    /*
     *  Renvoie les versions actives de la session, c-à-d qui sont en état: ACTIF, EN_ATTENTE, NOUVELLE_VERSION_DEMANDEE
     *
     *  NOTE - Fonction écrite pour AdminuxController::versionGetAction() mais finalement PAS UTILISEE
     *
     *
     * */
    public function findSessionVersionsActives($session)
    {
        return $this->getEntityManager()
        ->createQuery('SELECT partial v.{idVersion,etatVersion,prjGenciDari,prjTitre,prjLLabo,demHeures,attrHeures,politique}  FROM App:Version v JOIN v.session s WHERE ( s = :session AND (v.etatVersion = :actif OR v.etatVersion = :nouvelle_version_demandee OR v.etatVersion = :en_attente))')
        ->setParameter('nouvelle_version_demandee', Etat::getetat('NOUVELLE_VERSION_DEMANDEE'))
        ->setParameter('en_attente', Etat::getetat('EN_ATTENTE'))
        ->setParameter('actif', Etat::getetat('ACTIF'))
        ->setParameter('session', $session)
        ->getResult();
    }

    public function findAnneeTestVersions($annee)
    {
        return $this->getEntityManager()
        ->createQuery('SELECT partial v.{idVersion,etatVersion,prjGenciDari,prjTitre,prjLLabo,demHeures,attrHeures,politique}  FROM App:Version v  WHERE ( v.idVersion LIKE :pattern AND NOT v.etatVersion = :annule)')
        ->setParameter('annule', Etat::getEtat('ANNULE'))
        ->setParameter('pattern', '%T' . $annee . '%')
        //->setParameter('pattern', $annee . 'T' . $annee)
        ->getResult();
    }

    public function countVersions($projet)
    {
        return $this->getEntityManager()
         ->createQuery('SELECT count(v) FROM App:Version v WHERE ( v.projet = :projet AND NOT v.etatVersion = :annule)')
        ->setParameter('annule', Etat::getEtat('ANNULE'))
        ->setParameter('projet', $projet)
        ->getSingleScalarResult();
    }

    public function countEtat($etat)
    {
        return $this->getEntityManager()
         ->createQuery('SELECT count(v) FROM App:Version v WHERE ( v.etatVersion = :etat)')
        ->setParameter('etat', Etat::getEtat($etat))
        ->getSingleScalarResult();
    }

    public function demHeures($projet)
    {
        return $this->getEntityManager()
         ->createQuery('SELECT SUM(v.demHeures) FROM App:Version v WHERE ( v.projet = :projet AND NOT v.etatVersion = :annule)')
        ->setParameter('annule', Etat::getEtat('ANNULE'))
        ->setParameter('projet', $projet)
        ->getSingleScalarResult();
    }

    public function attrHeures($projet)
    {
        return $this->getEntityManager()
         ->createQuery('SELECT SUM(v.attrHeures) FROM App:Version v WHERE ( v.projet = :projet AND NOT v.etatVersion = :annule)')
        ->setParameter('annule', Etat::getEtat('ANNULE'))
        ->setParameter('projet', $projet)
        ->getSingleScalarResult();
    }

    public function info($projet)
    {
        return $this->getEntityManager()
         ->createQuery('SELECT COUNT(v), SUM(v.demHeures), SUM(v.attrHeures) FROM App:Version v WHERE ( v.projet = :projet AND NOT v.etatVersion = :annule)')
        ->setParameter('annule', Etat::getEtat('ANNULE'))
        ->setParameter('projet', $projet)
        ->getSingleResult();
    }

    public function etat(Projet $projet)
    {
        return $this->getEntityManager()
         ->createQuery('SELECT v.etatVersion FROM App:Version v JOIN App:Projet p WHERE ( p.versionDerniere = v AND p = :projet)')
        ->setParameter('projet', $projet)
        ->getOneOrNullResult();
    }

    public function exists($idVersion)
    {
        return $this->getEntityManager()
         ->createQuery('SELECT COUNT(v) FROM App:Version v  WHERE v.idVersion = :id')
        ->setParameter('id', $idVersion)
        ->getSingleScalarResult();
    }

    public function countThematique($thematique)
    {
        return $this->getEntityManager()
         ->createQuery('SELECT COUNT(v) FROM App:Version v  WHERE v.prjThematique; = :thematique')
        ->setParameter('thematique', $thematique)
        ->getSingleResult();
    }

    // Renvoie les projets de type SESS qui appartiennent à la session passée en paramètres
    public function findVersionsSessionTypeSess($session)
    {
        return $this->getEntityManager()
        ->createQuery('SELECT v FROM App:Version v, App:Projet p WHERE (v.session = :session AND v.projet = p AND p.typeProjet = 1)')
        ->setParameter('session', $session)
        ->getResult();
    }
}
