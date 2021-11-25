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

namespace App\Security;

use App\Entity\Individu;
use App\GramcServices\ServiceJournal;

use Symfony\Component\Security\Http\Authenticator\Passport\Badge\BadgeInterface;

/**
 * Passport badges allow to add more information to a passport (e.g. a CSRF token).
 *
 * @author Emmanuel courcelle
 */
class GramcBadge implements BadgeInterface
{
    private $sind = null;
    private $sj = null;
    private $ind = null;
    
    public function __construct(ServiceJournal $sj, Individu $ind)
    {
        $this->sj = $sj;
        $this->ind = $ind;
    }

    /**
     * Checks if this badge is resolved by the security system.
     *
     * Ici on teste le flag Désactivé 
     * After authentication, all badges must return `true` in this method in order
     * for the authentication to succeed.
     */
    public function isResolved(): bool
    {
        //dd($this->ind);
        if ( $this->ind->getDesactive() )
        {
            $this->sj->errorMessage($this->ind . " n'a pas pu s'authentifier (compte désactivé)");
            return false;
        }
        else
        {
            return true;
        }
    }
}
