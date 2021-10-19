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


/**
 * Voir https://symfony.com/index.php/doc/4.4/doctrine/events.html#doctrine-entity-listeners
 *
 * Le stamp de modification est inséré dans Version lors d'une MISE A JOUR uniquement
 * Rien ne se passe à la CREATION de la version (il faudrait écrire un événement prePersist)
 * Rien ne se passe si on fait un flush sans modification, ie le stamp n'est pas modifié
 * si on se contente d'ouvrir le projet et de le fermer sans rien modifier
 * Si l'utilisateur connecté n'est pas un utilisateur (un expert ou un admin ) le stamp n'est pas mis à jour
 * car il s'agit de modifications normalement "techniques" uniquement
 ****/

// src/EventListener/UserChangedNotifier.php

namespace App\EventListener;

use App\Entity\Version;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class VersionStamp
{
    private $token;

    public function __construct(TokenStorageInterface $tok)
    {
        $this->token = $tok->getToken();
    }

    // the entity listener methods receive two arguments:
    // the entity instance and the lifecycle event
    public function preUpdate(Version $version, LifecycleEventArgs $event): void
    {
        if ($this->token == null) {
            return;
        }
        $user = $this->token->getUser();
        if ($version->isCollaborateur($user)) {
            $version->setMajInd($user);
            $version->setMajStamp(new \DateTime());
        } else {
            $version->setMajInd(null);
        }
    }
}
