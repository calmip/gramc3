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

use App\Utils\Etat;
use App\Utils\Functions;
//use App\App;

use App\Entity\Projet;
use App\Entity\Individu;
use App\Entity\Version;
use App\Entity\Rallonge;
use App\Entity\Session;

/**
 * RallongeRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class RallongeRepository extends \Doctrine\ORM\EntityRepository
{


    public function findSessionRallonges($sessions)
    {
    $rallonges = [];
    foreach( $sessions as $session )
        {
            $dql     =   'SELECT r FROM App:Rallonge r';
            $dql    .=  " INNER JOIN App:Version v WITH r.version = v ";
            $dql    .=  " WHERE  v.session = :session  ";

        $session_rallonges = $this->getEntityManager()
                                ->createQuery( $dql )
                                ->setParameter('session', $session )
                                ->getResult();
        $rallonges = array_merge( $session_rallonges, $rallonges);
        }
    return $rallonges;
    }

    public function findRallongesOuvertes(Version $version)
    {
	    if( $version == null )
        {
	        return [];
        }
        
	    $dql     =   'SELECT r FROM App:Rallonge r';
	    $dql    .=  " WHERE  ( r.version = :version ";
	    $dql    .=  " AND  ( r.etatRallonge = :ed_dem OR r.etatRallonge = :ed_exp OR r.etatRallonge = :att ) ";
	    $dql    .= " )";
	
	    return $this->getEntityManager()
	                        ->createQuery( $dql )
	                        ->setParameter('version', $version )
	                        ->setParameter('ed_dem', Etat::EDITION_DEMANDE)
	                        ->setParameter('ed_exp', Etat::EDITION_EXPERTISE)
	                        ->setParameter('att',Etat::EN_ATTENTE)
	                        ->getResult();
    }

    public function findRallongesExpert(Individu $expert)
    {

    $dql     =   'SELECT r FROM App:Rallonge r';
    $dql    .=  " WHERE  ( r.expert = :expert AND  r.etatRallonge = :ed_exp )";
    
    return $this->getEntityManager()
                        ->createQuery( $dql )
                        ->setParameter('ed_exp', Etat::EDITION_EXPERTISE)
                        ->setParameter('expert',$expert)
                        ->getResult();
    }
    
}
