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

use App\GramcServices\GramcDate;
use App\Utils\Etat;

use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityManagerInterface;

/*
 * Quelques fonctions utiles pour les formulaires
 * 
 ******/
class ServiceForms
{
    private $em;
    private $vl;

    public function __construct(ValidatorInterface $vl, EntityManagerInterface $em)
    {
	$this->vl = $vl;
        $this->em = $em;
    }

    public function formError( $data, $constraintes )
    {
	$violations = $this->vl->validate( $data, $constraintes );
    
	if (count($violations)>0 )
	{
	    $errors = "<strong>Erreurs : </strong>";
	    foreach ($violations as $violation)
	    {
		$errors .= $violation->getMessage() .' ';
	    }
	    return $errors;
	}
	else
	{
	    return "OK";
	}
    }

} // class
