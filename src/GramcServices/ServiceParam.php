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

use App\Entity\Param;
use Doctrine\ORM\EntityManagerInterface;

/* Ce service est utilisé pour renvoyer les paramètres stocqués dans la table Param
 * Ces paramètres peuvent être modifiés par l'interface graphique, ce qui n'est pas le cas
 * des paramètres du fichier parameter.yml
 *
 */
class ServiceParam
{
    public function __construct(private EntityManagerInterface $em) {}

    /* Renvoie la valeur du paramètre s'il existe, null sinon */
    public function getParameter($parameter): ?string
    {
        $param = $this->em->getRepository(Param::class)->findOneBy([ 'cle' => $parameter ]);
        if ($param == null) {
            return null;
        } else {
            return $param->getVal();
        }
    }

    /* renvoie true/false suivant que le paramètre existe ou pas */
    public function hasParameter($parameter): bool
    {
        $param = $this->getParameter($parameter);
        if ($param==null) {
            return false;
        } else {
            return true;
        }
    }
}
