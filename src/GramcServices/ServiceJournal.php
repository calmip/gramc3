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
//use App\Utils\Functions;
use App\Entity\Journal;

use Doctrine\ORM\EntityManagerInterface;
//use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
//use Symfony\Bridge\Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/********************
 * Ce service est utilisé pour envoyer des notifications par mail aux utilisateurs
 ********************/

class ServiceJournal
{
    // request_stack, session,logger, security.helper, doc
    // request_stack, session,logger, security.token_storage,doc
    public function __construct(
        RequestStack $rs,
        SessionInterface $ss,
        LoggerInterface $log,
        TokenStorageInterface $tok,
        AuthorizationCheckerInterface $ac,
        EntityManagerInterface $em
    )
    {
        $this->rs    = $rs;
        $this->ss    = $ss;
        $this->log   = $log;
        $this->token = $tok->getToken();
        $this->ac    = $ac;
        $this->em    = $em;
    }

    /**
     * Ecrire quelque chose dans le journal
     *
     * param $message
     * param $niveau Le niveau de log, Journal::WARNING, Journal::INFO etc.
     *               Voir Entity/Journal.php pour les différents niveaux possibles
     *
     * return l'objet inséré
     *
     ***/
    private function journalMessage($message, $niveau)
    {
        return;
        $rs    = $this->rs;
        $ss    = $this->ss;
        $log   = $this->log;
        $token = $this->token;
        $em    = $this->em;

        $journal = new Journal();
        $journal->setStamp(new \DateTime());

        // Si l'erreur provient de l'API, getUser() n'est pas un Individu
        if ($token != null && $token->getUser() != null && $token->getUser() instanceof Individu) {
            $journal->setIndividu($token->getUser());
            $journal->setIdIndividu($token->getUser()->getId());
        } else {
            $journal->setIdIndividu(null);
            $journal->setIndividu(null);
        }

        $journal->setGramcSessId($ss->getId());

        if ($rs->getMasterRequest() != null
        && $rs->getMasterRequest()->getClientIp() != null) {
            $ip = $rs->getMasterRequest()->getClientIp();
        } else {
            $ip = '0.0.0.0';
        }

        $journal->setIp($ip);
        $journal->setMessage(substr($message, 0, 300));
        $journal->setNiveau($niveau);
        $journal->setType(Journal::LIBELLE[$niveau]);

        // nous testons des problèmes de Doctrine pour éviter une exception
        //if( App::getEnvironment() != 'test' )
        //{
        if ($em->isOpen()) {
            $em->persist($journal);
            $em->flush();
        } else {
            $log->error('Entity manager closed, message = ' . $message);
        }
        //}

        return $journal;
    }

    public function emergencyMessage($message)
    {
        $this->log->emergency($message);
        $this->journalMessage($message, Journal::EMERGENCY);
    }

    public function alertMessage($message)
    {
        $this->log->alert($message);
        $this->journalMessage($message, Journal::ALERT);
    }

    public function criticalMessage($message)
    {
        $this->log->critical($message);
        $this->journalMessage($message, Journal::CRITICAL);
    }

    public function errorMessage($message)
    {
        $this->log->error($message);
        $this->journalMessage($message, Journal::ERROR);
    }

    public function warningMessage($message)
    {
        $this->log->warning($message);
        $this->journalMessage($message, Journal::WARNING);
    }

    public function noticeMessage($message)
    {
        $this->log->notice($message);
        $this->journalMessage($message, Journal::NOTICE);
    }

    public function infoMessage($message)
    {
        $this->log->info($message);
        $this->journalMessage($message, Journal::INFO);
    }

    public function debugMessage($message)
    {
        $this->log->debug($message);
        $this->journalMessage($message, Journal::DEBUG);
    }

    /************************
     * Ecrit un message dans le journal (niveau warning)
     * Lance une exception
     * L'exception n'est pas la même suivant qu'on est authentifié ou pas
     *
     **************************************/
    public function throwException($text = null)
    {
        if ($text != null) {
            $this->warningMessage("EXCEPTION " . $text);
        }

        if ($this->ac->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw new AccessDeniedHttpException();
        } else {
            throw new InsufficientAuthenticationException();
        }
    }
}
