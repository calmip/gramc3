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

namespace App\Security\User;

use App\Exception\AccountDeletedException;
use App\Entity\Individu as AppUser;

use Symfony\Component\Security\Core\Exception\AccountExpiredException;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\Exception\LockedException;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

use App\Exception\UserException;
use Symfony\Component\HttpFoundation\Request;

use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

//use App\App;
//use App\Utils\Functions;
use App\GramcServices\ServiceJournal;

class UserChecker implements UserCheckerInterface
{
    private $secu_auto_chk;
    private $token;
    private $sss;
    private $sj;
    private $em;
    
    public function __construct(
        AuthorizationCheckerInterface $secu_auto_chk,
        TokenStorageInterface $tok,
        SessionInterface $sss,
        ServiceJournal $sj,
        EntityManagerInterface $em
    )
    {
        $this->secu_auto_chk = $secu_auto_chk;
        $this->token         = $tok->getToken();
        $this->sss           = $sss;
        $this->sj            = $sj;
        $this->em            = $em;
    }

    public function checkPreAuth(UserInterface $user)
    {
        if (!$user instanceof AppUser) {
            return;
        }

        if ($this->secu_auto_chk->isGranted('ROLE_PREVIOUS_ADMIN')) {
            $this->sj->debugMessage('UserChecker : checkPreAuth : User ' . $user->getPrenom() . ' ' . $user->getNom() .
                " ROLE_PREVIOUS_ADMIN granted");
        }

        if ($user->getDesactive()) {
            $this->sj->warningMessage('UserChecker : checkPreAuth : User ' . $user->getPrenom() . ' ' . $user->getNom() .
                " est désactivé");
            throw new UserException($user);
        } else {
            //  $this->sj->debugMessage('UserChecker : checkPreAuth : User ' . $user->getPrenom() . ' ' . $user->getNom() .
            //   " peut se connecter");
            return true;
        }
    }

    public function checkPostAuth(UserInterface $user)
    {
        if (!$user instanceof AppUser) {
            return;
        }

        // on peut faire sudo même si l'utilisateur n'a pas le droit de se connecter
        if ($user->getDesactive() && ! $this->secu_auto_chk->isGranted('ROLE_ALLOWED_TO_SWITCH')) {
            $this->sj->warningMessage('UserChecker : checkPostAuth : User ' . $user->getPrenom() . ' ' . $user->getNom() .
                " est désactivé");
            throw new UserException($user);
        }

        // on stocke l'information sur SUDO dans la variable de la session 'real_user'
        // s'il n'y a pas de SUDO 'real_user' = $user
        // en cas de SUDO 'real_user' est l'utilisateur qui a fait SUDO

        if (! $this->secu_auto_chk->isGranted('ROLE_PREVIOUS_ADMIN')) {
            if ($this->secu_auto_chk->isGranted('ROLE_ALLOWED_TO_SWITCH') && $this->sss->has('real_user')) {
                $this->sj->debugMessage('UserChecker : checkPostAuth : User ' .
                                         $user->getPrenom() . ' ' .
                                         $user->getNom() . " est connecté en SUDO par " .
                                         $this->token->getUser());
                $this->sss->set('real_user', $this->token->getUser());
                $this->sss->set('sudo_url', Request::createFromGlobals()->headers->get('referer'));
            //$this->sj->debugMessage(__METHOD__ . " sudo_url set to : " . $this->sss->get('sudo_url') );
            } else {
                $this->sss->set('real_user', $user);
                $this->sss->remove('sudo_url');
                //$this->sj->debugMessage(__METHOD__ . " sudo_url removed" );
                //$this->sj->debugMessage('UserChecker : checkPostAuth : User ' . $user->getPrenom() . ' ' . $user->getNom() . " est connecté");
            }
        } else {
            $this->sj->debugMessage('UserChecker : checkPostAuth : User '. $this->token->getUser() . " redevient ".  $user);
            $this->sss->remove('sudo_url');
            $this->sj->debugMessage(__METHOD__ . " sudo_url removed");
        }
        return true;
    }
}
