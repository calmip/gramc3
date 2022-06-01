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

use App\Entity\Invitation;
use App\Entity\Individu;
use App\GramcServices\ServiceNotifications;

use Doctrine\ORM\EntityManagerInterface;

/********************
 * Ce service permet de créer et envoyer des invitaiotn pour de nouveaux utilisateurs
 ********************/
 
class ServiceInvitations
{
    public function __construct(private $invit_duree, private ServiceNotifications $sn, private EntityManagerInterface $em){}

    /*****************************
     * Ces deux fonctions sont pompées sur https://stackoverflow.com/questions/1846202/how-to-generate-a-random-unique-alphanumeric-string/13733588#13733588
     **********************************************************************************************/
    private function crypto_rand_secure($min, $max)
    {
        $range = $max - $min;
        if ($range < 1) return $min; // not so random...

        $log = ceil(log($range, 2));
        $bytes = (int) ($log / 8) + 1; // length in bytes
        $bits = (int) $log + 1; // length in bits
        $filter = (int) (1 << $bits) - 1; // set all lower bits to 1
        do {
            $rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));
            $rnd = $rnd & $filter; // discard irrelevant bits
        } while ($rnd > $range);
        return $min + $rnd;
    }

    private function getToken($length)
    {
        $token = "";
        $codeAlphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $codeAlphabet.= "abcdefghijklmnopqrstuvwxyz";
        $codeAlphabet.= "0123456789";
        $max = strlen($codeAlphabet);
    
        for ($i=0; $i < $length; $i++) {
            $token .= $codeAlphabet[$this->crypto_rand_secure(0, $max-1)];
        }
    
        return $token;
    }

    /*****************
     * Crée une nouvelle invitation
     * Une clé de 50 caractères aléatoires est générée, on suppose qu'elle sera unique.
     * La proba que deux clés soient identiques est de 2 * ((1/52)**50)
     * La date est stoquée dans le creationStamp
     * (on n'utilise par gramcDate pour calculer le stamp)
     *************************************************************************/
    private function newInvitation(Individu $inviting, Individu $invited): Invitation
    {
        $em = $this->em;

        $key = $this->getToken(50);
        $stamp = new \DateTime();

        $invitation = new Invitation;
        $invitation->setInviting($inviting)
                   ->setInvited($invited)
                   ->setCreationStamp($stamp)
                   ->setClef($key);

        $em->persist($invitation);
        $em->flush();

        return $invitation;
    }

    /***********************
     * Crée et envoie illico une invitation
     *
     *************************************************************************/
    public function sendInvitation(Individu $inviting, Individu $invited): void
    {
        $invit_duree = $this->invit_duree;
        $sn = $this->sn;
        
        $invitation = $this->newInvitation($inviting, $invited);
        $date_limite = $invitation->getCreationStamp()->add(new \DateInterval($invit_duree));
        
        $sn->sendMessage('notification/invitation-sujet.html.twig',
                         'notification/invitation-contenu.html.twig',
                         [ 'invitation' => $invitation, 'date_limite' => $date_limite ],
                         [ $invited->getMail() ]);
    }
}
