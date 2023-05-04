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
use App\GramcServices\Etat;
use App\Entity\Session;

use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityManagerInterface;

const VERSION = "3.8.14";

/*
 * Cette classe garde des informations pouvant être reprises par
 * les autres objets, et en particulier par les pages twig (haut et bas de page)
 *
 ******/
class ServiceInfos
{
    private $sessions_non_terminees = null;
    private $session_courante = null;
    private $etat_session_courante = null;
    private $libelle_etat_session_courante = null;
    private $id_session_courante = null;

    public function __construct(private GramcDate $grdte, private EntityManagerInterface $em)
    {
        $this->sessions_non_terminees =
            $em->getRepository(Session::class)->get_sessions_non_terminees();

        if (isset($this->sessions_non_terminees[0])) {
            $this->session_courante = $this->sessions_non_terminees[0];
        }

        if ($this->session_courante != null) {
            $this->etat_session_courante  =  $this->session_courante->getEtatSession();
            if (array_key_exists($this->etat_session_courante, Etat::LIBELLE_ETAT)) {
                $this->libelle_etat_session_courante = Etat::LIBELLE_ETAT[$this->etat_session_courante];
            } else {
                $this->libelle_etat_session_courante = "UNKNOWN";
            }
            $this->id_session_courante = $this->session_courante->getIdSession();
        }
    }

    public function getLibelleEtatSessionCourante(): ?string
    {
        return $this->libelle_etat_session_courante;
    }

    public function getSessionCourante(): Session
    {
        return $this->session_courante;
    }


    public function getEtatSessionCourante(): int
    {
        return $this->etat_session_courante;
    }

    public function sessions_non_terminees(): ?array
    {
        return $this->sessions_non_terminees;
    }

    public function mail_replace($mail): string
    {
        return str_replace('@', ' at ', $mail);
    }

    public function gramc_date($format): GramcDate|string
    {
        $d = $this->grdte;
        if ($format == 'raw') {
            return $d;
        } else {
            return $d->format($format);
        }
    }

    public function prochaine_session_saison(): array
    {
        $annee        = 2000 + intval(substr($this->id_session_courante, 0, 2));
        $type         = substr($this->id_session_courante, 2, 1);
        $mois_courant = intval($this->gramc_date('m'));
        $result['annee']=$annee;
        if ($type == 'A') {
            $result['type']='P';
        } else {
            $result['type']='A';
        }
        return $result;
    } //  function prochaine_session_saison()

    // TODO - strftime est obsolète à partir de php 8.1 !
    public function strftime_fr($format, $date): string
    {
        setlocale(LC_TIME, 'fr_FR.UTF-8');
        return strftime($format, $date->getTimestamp());
    } // function strftime_fr


    public function tronquer_chaine(?string $s, string|int $l): ?string
    {
        if (grapheme_strlen($s)>=intval($l)) {
            return grapheme_substr($s, 0, intval($l)).'...';
        } else {
            return $s;
        }
    }


    public function cette_session(): array
    {
        $aujourdhui    = $this->gramc_date('raw');
        $fin_sess_date = $this->session_courante->getDateFinSession();
        $interval   = date_diff($aujourdhui, $fin_sess_date);
        $duree      = $interval->format('%R%a-%H');
        $jours      = intval($duree);
        return array( 'jours' => $jours, 'fin_sess' => $fin_sess_date->format("d/m/Y") );
    } // function cette_session()

    public function prochaine_session(): string
    {
        return $this->session_courante->getDateDebutSession()->format("d/m/Y");
    } // function prochaine_session

    public function getVersion(): string
    {
        return VERSION;
    }
} // class
