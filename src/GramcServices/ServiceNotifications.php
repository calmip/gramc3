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
use App\Utils\Functions;

use App\GramcServices\ServiceJournal;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/********************
 * Ce service est utilisé pour envoyer des notifications par mail aux utilisateurs
 ********************/

class ServiceNotifications
{
    public function __construct(
        private $mailfrom,
        private $noedition_expertise,
        private \Twig\Environment $twig,
        private TokenStorageInterface $tok,
        private MailerInterface $mailer,
        protected ServiceJournal $sj,
        protected EntityManagerInterface $em
    ) {
        $this->token    = $tok->getToken();
    }

    /*****
     * Envoi d'une notification de test pour tester la configuration mail
     *
     * param $addr  Une adresse mail de destination
     *
     *******************************************/
    public function sendTestMessage(string $addr)
    {
        $sujet = "Test d'envoi de mails depuis gramc";
        $contenu = "Bonjour\nSi $addr reçoit ce mail le test est concluant\nBravo\ngramc";
        $this->sendRawMessage($sujet, $contenu, [ $addr ]);
    }
    /*****
     * Envoi d'une notification
     *
     * param $twig_sujet, $twig_contenu Templates Twig des messages:
     *                                            - soit des fichiers .html.twig
     *                                            - soit la sortie de $twig->createTemplate()
     * param $params                    La notification est un template twig, le contenu de $params est passé à la fonction de rendu
     * param $users                     Liste d'utilisateurs à qui envoyer des emails (cf mailUsers)
     *
     *********/
    public function sendMessage($twig_sujet, $twig_contenu, $params, $users = null): void
    {
        $twig    = $this->twig;
        $body    = $twig->render($twig_contenu, $params);
        $subject = $twig->render($twig_sujet, $params);
        $this->sendRawMessage($subject, $body, $users);
    }

    /*****
     * Envoi d'une notification
     *
     * param $twig_sujet, $twig_contenu Templates Twig des messages (ce sont des strings)
     * param $params                    La notification est un template twig, le contenu de $params est passé à la fonction de rendu
     * param $users                     Liste d'utilisateurs à qui envoyer des emails (cf mailUsers)
     *
     *********/
    public function sendMessageFromString($twig_sujet, $twig_contenu, $params, $users = null): void
    {
        $twig         = $this->twig;
        $sujet_tmpl   = $twig->createTemplate($twig_sujet);
        $contenu_tmpl = $twig->createTemplate($twig_contenu);
        
        $body       =   $twig->render($contenu_tmpl, $params);
        $subject    =   $twig->render($sujet_tmpl, $params);
        $this->sendRawMessage($subject, $body, $users);
    }


    // Bas niveau: Envoi du message
    private function sendRawMessage($subject, $body, array $users = null): void
    {
        $message = new Email();
        $message -> subject($subject);
        $message -> text($body);
        $message -> from($this->mailfrom);

        if ($users != null) {
            $real_users =   [];
            $mails      =   [];

            foreach ($users as $user)
            {

                // Objet Individu
                if ($user instanceof Individu)
                {
                    $real_users[] = $user;
                }

                // email string 
                elseif (is_string($user))
                {
                    $mails[] = $user;
                }
                elseif ($users == null)
                {
                    $this->sj->warningMessage(__METHOD__ . ":" . __LINE__ . ' users contient un utilisateur null');
                }
                else
                {
                    $this->sj->errorMessage(__METHOD__ . ":" . __LINE__ . ' users contient un mauvais type de données: ' . Functions::show($user));
                }
            }

            if ($mails == [])
            {
                $warning = true;
            } else
            {
                $warning = false;
            }

            $mails = array_unique(array_merge($mails, $this->usersToMail($real_users, $warning)));

            // Ajouter un destinataire
            foreach ($mails as $mail) {
                //$message->addTo( $mail);
                $message -> addTo($mail);
            }

            // Ecrire une ligne dans le journal et dans les logs
            $to = '';
            if ($message->getTo() != null) {
                $arrayTo = array_values($message->getTo());
                foreach ($arrayTo as $item) {
                    $to = $to . ' ' . $item->toString();
                }
            }

            // debug
            // return [ 'subject'  =>  $message->getSubject(), 'contenu' => $message->getBody(), 'to'  => $to  ]; // debug only
            $this->sj->infoMessage('email "' . $message->getSubject() . '" envoyé à ' . $to);

	    // Envoi du message
	    try {
               $this->mailer->send($message);
	    }
	    catch ( \Exception $e ) { };
        } else {
            $this->sj->warningMessage(__METHOD__ . ":" . __LINE__ . 'email "' . $message->getSubject() . '" envoyé à une liste vide de destinataires');
        }
    }

    ///////////

    // Renvoie la liste d'utilisateurs associés à un rôle et un objet
    // Params: $mail_roles = liste de roles (A,d,P etc. cf ci-dessous)
    //         $objet      = version (pour E/R) ou thématique (pour ET) ou null (pour les autres roles)
    // Output: Liste d'individus (pour passer à sendMessage)
    //

    public function mailUsers($mail_roles = [], $objet = null): array
    {
        $em    = $this->em;
        $users = [];
        foreach ($mail_roles as $mail_role) {
            switch ($mail_role) {
                case 'D': // demandeur
                    $user = $this->token->getUser();
                    if ($user != null) {
                        $users  =  array_merge($users, [ $user ]);
                    } else {
                        $this->sj->errorMessage(__METHOD__ . ":" . __LINE__ ." Utilisateur n'est pas connecté !");
                    }
                    break;
                case 'A': // admin
                    $new_users = $em->getRepository(Individu::class)->findBy(['admin'  =>  true ]);
                    if ($new_users == null) {
                        $this->sj->warningMessage(__METHOD__ . ":"  . __LINE__ .' Aucun admin !');
                    } else {
                        if (! is_array($new_users)) {
                            $new_users = $new_users->toArray();
                        }
                        $users = array_merge($users, $new_users);
                    }
                    break;
                case 'S': // sysadmin
                    $new_users = $em->getRepository(Individu::class)->findBy(['sysadmin'  =>  true ]);
                    if ($new_users == null) {
                        $this->sj->warningMessage(__METHOD__ . ":"  . __LINE__ .' Aucun sysadmin !');
                    } else {
                        if (! is_array($new_users)) {
                            $new_users = $new_users->toArray();
                        }
                        $users = array_merge($users, $new_users);
                    }
                    break;
                case 'P': //président
                    $new_users = $em->getRepository(Individu::class)->findBy(['president'  =>  true ]);
                    if ($new_users == null) {
                        $this->sj->warningMessage(__METHOD__ . ":" .  __LINE__ .' Aucun président !');
                    } else {
                        if (! is_array($new_users)) {
                            $new_users = $new_users->toArray();
                        }
                        $users = array_merge($users, $new_users);
                    }
                    break;

                case 'E': // expert
                    if ($objet == null) {
                        $this->sj->warningMessage(__METHOD__ . ":" . __LINE__ .' Objet null pour expert');
                        break;
                    }
                    $new_users  = $objet->getExperts();
                    //$this->sj->debugMessage(__METHOD__ .":" . __LINE__ .  " experts : " . Functions::show($new_users) );
                    if ($new_users == null) {
                        $this->sj->warningMessage(__METHOD__ . ":" . __LINE__ ." Aucun expert pour l'objet " . $objet . ' !');
                    } else {
                        if (! is_array($new_users)) {
                            $new_users = $new_users->toArray();
                        }
                        //$this->sj->debugMessage(__METHOD__ .":" . __LINE__ .  " experts après toArray : " . Functions::show($new_users) );
                        $users  =  array_merge($users, $new_users);
                    }
                    break;
                case 'R': // responsable
                    if ($objet == null) {
                        $this->sj->warningMessage(__METHOD__ . ":" . __LINE__ .' Objet null pour responsable');
                        break;
                    }
                    $new_users  = $objet->getResponsables();
                    if ($new_users == null) {
                        $this->sj->warningMessage(__METHOD__ . ":" . __LINE__ ." Aucun responsable pour l'objet " . $objet . ' !');
                    } else {
                        if (! is_array($new_users)) {
                            $new_users = $new_users->toArray();
                        }
                        $users  =  array_merge($users, $new_users);
                    }
                    break;
                case 'ET': // experts pour la thématique
                    // Si noedition_expertise, on n'a pas de "comité d'attribution" constitué
                    // Dans ce cas, on n'envoie pas de mail aux experts de la thématique = ils n'y comprendraient rien
                    if ($this->noedition_expertise == false)
                    {
                        if ($objet == null) {
                            $this->sj->warningMessage(__METHOD__ . ":" .  __LINE__ .' Objet null pour experts de la thématique');
                            break;
                        }
                        $new_users  = $objet->getExpertsThematique();
                        if ($new_users == null) {
                            $this->sj->warningMessage(__METHOD__ . ":" . __LINE__ ." Aucun expert pour la thématique pour l'objet " . $objet . ' !');
                        } else {
                            if (! is_array($new_users)) {
                                $new_users = $new_users->toArray();
                            }
                            $users  =  array_merge($users, $new_users);
                        }
                    }
                    break;
            }
        }
        return $users;
    }

    /////////////////////////
    //
    // obtenir des adresses mail à partir des utilisateurs
    //

    public function usersToMail($users, $warning = false): array
    {
        $mail   =   [];

        if ($users == null) {
            if ($warning == true) {
                $this->sj->warningMessage(__METHOD__ . ":" . __LINE__ .' La liste des utilisateurs est vide');
            }
            return $mail;
        }

        foreach ($users as $user) {
            if ($user != null && $user instanceof Individu) {
                $user_mail =  $user->getMail();
                if ($user_mail != null) {
                    $mail[] = $user_mail;
                } else {
                    $this->sj->warningMessage(__METHOD__ . ":" . __LINE__ . ' Utilisateur '. $user . " n'a pas de mail");
                }
            } elseif ($user == null) {
                $this->sj->errorMessage(__METHOD__ . ":" . __LINE__ . ' Utilisater null dans la liste');
            } elseif (! $user instanceof Individu) {
                $this->sj->errorMessage(__METHOD__ . ":".  __LINE__ . ' Un objet autre que Individu dans la liste des utilisateurs');
            }
        }

        return array_unique($mail);
    }
}
