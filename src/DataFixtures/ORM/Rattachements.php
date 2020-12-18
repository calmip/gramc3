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

namespace App\DataFixtures\ORM;

use Doctrine\Bundle\FixturesBundle\ORMFixtureInterface;
use Doctrine\Persistence\ObjectManager;

use App\Entity\Version;
use App\Entity\Rattachement;
use App\Entity\Thematique;
use App\Entity\MetaThematique;
use App\Entity\Individu;

/* ANITI et ONERA ne sont plus des thématiques, ce sont des "rattachements" */
class Rattachements  implements ORMFixtureInterface
{
	public function load(ObjectManager $em)
	{
		// Est-ce déjà traité ?
		$themaAniti = $em->getRepository(Thematique::class)  ->findOneBy( ['idThematique' => 20] );
		$themaInfo  = $em->getRepository(Thematique::class)  ->findOneBy( ['idThematique' => 7] );
		if ($themaAniti == null)
		{
			echo "Aniti/Onera déjà traités !\n";
			return;
		}

		// Remplir la table rattachements
		$ratt = new Rattachement();
		$ratt->setLibelleRattachement('ANITI');
		$em->persist( $ratt );

		$ratt = new Rattachement();
		$ratt->setLibelleRattachement('ONERA');
		$em->persist( $ratt );
		
		$em->flush();
		
		echo "Table Rattachement remplie\n";

		// Modifier la table versions
		// Les versions de thématique ANITI et ONERA sont modifiées comme suit:
		// ANITI:
		//    - Thématique   => 7 (Informatique, automatique)
		//    - Rattachement => 1 (ANITI)
		// ONERA:
		//    - labo         => 68(ONERA)
		//    - Rattachement => 2 (ONERA)
		// AUTRES:
		//    - Rattachement => 0 (Académique)
		
		$rattAniti  = $em->getRepository(rattachement::class)->findOneby( ['idRattachement' => 1] );
		$rattOnera  = $em->getRepository(rattachement::class)->findOneby( ['idRattachement' => 2] );
		$rattAcad   = null;
		
		$versions   = $em->getRepository(Version::class)     ->findAll();
		$nb_versions= 0;
		$nb_aniti   = 0;
		$nb_onera   = 0;
		$nb_acad    = 0;
		
		foreach( $versions as $version )
		{
			if ($version->getPrjThematique() == $themaAniti)
			{
				$version->setPrjThematique($themaInfo);
				$version->setPrjRattachement($rattAniti);
				$nb_aniti += 1;
			}
				
			elseif (strpos($version->getPrjLLabo(),'ONERA')!==false)
			{
				$version->setPrjRattachement($rattOnera);
				$nb_onera += 1;
			}
			
			else
			{
				$version->setPrjRattachement($rattAcad);	
				$nb_acad += 1;
			}
			$em->persist($version);
			
			$nb_versions++;
			if ($nb_versions % 100 == 0)
			{
				$em->flush();
			}
		}
		
		$em->flush();
		
		echo "Modification de la table Version effectuée\n\n";
		echo "BILAN:\n";
		echo "======\n\n";
		echo "Versions traitées              = $nb_versions\n";
		echo "Versions ANITI traitées        = $nb_aniti\n";
		echo "Versions ONERA traitées        = $nb_onera\n";
		echo "Versions SANS RATT traitées    = $nb_acad\n";
		if ($nb_versions != $nb_aniti+$nb_onera+$nb_acad)
		{
			echo "+++++++++++++ ATTENTION ++++++++++++ PAS COHERENT\n";
		}
		echo "\n";
		
		// Modifier l'affectation de deux experts (hl + jle)	
		$hl = $em->getRepository(Individu::class) -> findOneBy( [ 'idIndividu' => 1317 ] );
		$jle= $em->getRepository(Individu::class) -> findOneBy( [ 'idIndividu' => 511 ] );

		$themaAniti->removeExpert($hl);
		$em->persist($themaAniti);
		$rattAniti->addExpert($hl);
		$em->persist($rattAniti);
		
		$themaOnera = $em->getRepository(Thematique::class)->findOneBy( ['idThematique' => 21] );
		$themaOnera->removeExpert($jle);
		$em->persist($themaOnera);
		$rattOnera->addExpert($jle);
		$em->persist($rattOnera);
		$em->flush();
		echo "Modification des affectations de HL et JLE OK\n";
		
		// Supprimer les thématiques ONERA et ANITI
		$em->remove($themaAniti);
		$em->remove($themaOnera);
		$em->flush();
		echo "Suppression de ANITI et ONERA de la table Thematiques OK\n";		

		// Supprimer les métathématiques ONERA et ANITI
		$metathemaAniti = $em->getRepository(MetaThematique::class)->findOneBy( ['idMetaThematique' => 10] );
		$em->remove($metathemaAniti);
		$metathemaOnera = $em->getRepository(MetaThematique::class)->findOneBy( ['idMetaThematique' => 11] );
		$em->remove($metathemaOnera);
		$em->flush();
		echo "Suppression de ANITI et ONERA de la table MetaThematiques OK\n\n";		
		
	}
}
