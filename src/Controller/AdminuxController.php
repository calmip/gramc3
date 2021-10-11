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

namespace App\Controller;

use Psr\Log\LoggerInterface;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use App\Utils\Functions;
use App\Utils\Etat;

use App\Entity\Projet;
use App\Entity\Version;
use App\Entity\Session;
use App\Entity\Individu;
use App\Entity\CollaborateurVersion;
use App\Entity\User;
use App\Entity\Compta;

use App\GramcServices\ServiceNotifications;
use App\GramcServices\ServiceJournal;
use App\GramcServices\ServiceProjets;
use App\GramcServices\ServiceSessions;
use App\GramcServices\GramcDate;
use App\GramcServices\ServiceVersions;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;

/**
 * AdminUx controller: Commandes curl envoyées par l'administrateur unix
 *
 * @Route("/adminux")
 */
class AdminuxController extends AbstractController
{
    private $sn;
    private $sj;
    private $sp;
    private $ss;
    private $sd;
    private $sv;

    public function __construct(
        ServiceNotifications $sn,
        ServiceJournal $sj,
        ServiceProjets $sp,
        ServiceSessions $ss,
        GramcDate $sd,
        ServiceVersions $sv
    ) {
        $this->sn  = $sn;
        $this->sj  = $sj;
        $this->sp  = $sp;
        $this->ss  = $ss;
        $this->sd  = $sd;
        $this->sv  = $sv;
    }

    /**
     * Met à jour les données de comptabilité à partir d'un unique fichier csv
     *
     * format date, loginname, ressource, type, consommation, quota
     * ressource = cpu, gpu, home, etc.
     * type      = user ou group unix
     * @Route("/compta_update_batch", name="compta_update_batch", methods={"PUT"})
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function UpdateComptaBatchAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        if ($this->getParameter('noconso')==true) {
            throw new AccessDeniedException("Forbidden because of parameter noconso");
        }
        $conso_repository = $em->getRepository(Compta::class);

        $putdata = fopen("php://input", "r");
        //$input = [];

        while ($ligne  =   fgetcsv($putdata)) {
            if (sizeof($ligne) < 5) {
                continue;
            } // pour une ligne erronée ou incomplète

            $date       =   $ligne[0]; // 2019-02-05
            $date       =   new \DateTime($date . "T00:00:00");
            $loginname  =   $ligne[1]; // login
            $ressource  =   $ligne[2]; // cpu, gpu, ...
            $type   =   $ligne[3]; // user, group
            if ($type=="user") {
                $type_nb = Compta::USER;
            } elseif ($type=="group") {
                $type_nb = Compta::GROUP;
            } else {
                return new Response('KO');
            }

            $compta =  $conso_repository->findOneBy([ 'date' => $date, 'loginname' =>  $loginname,  'ressource' => $ressource, 'type' => $type_nb ]);
            if ($compta == null) { // new item
                $compta = new Compta();
                $compta->setDate($date);
                $compta->setLoginname($loginname);
                $compta->setRessource($ressource);
                $compta->setType($type_nb);
                $em->persist($compta);
            }

            $conso  =   $ligne[4]; // consommation

            if (array_key_exists(5, $ligne)) {
                $quota  =   $ligne[5];
            } // quota
            else {
                $quota  =   -1;
            }


            $compta->setConso($conso);
            $compta->setQuota($quota);

            //$input[]    =   $compta;
            //return new Response( Functions::show( $ligne ) );
        }

        try {
            $em->flush();
        } catch (\Exception $e) {
            return new Response('KO');
        }

        //return new Response( Functions::show( $conso_repository->findAll() ) );

        return $this->render('consommation/conso_update_batch.html.twig');
    }


    ///////////////////////////////////////////////////////////////////////////////

    /**
     * set loginname
     *
     * @Route("/users/setloginname", name="set_loginname", methods={"POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     *
     * Positionne le loginname du user demandé dans la version active ou EN_ATTENTE du projet demandé
     *
     */

    // exemple: curl --insecure --netrc -X POST -d '{ "loginname": "toto", "idIndividu": "6543", "projet": "P1234" }'https://.../adminux/users/setloginname
    public function setloginnameAction(Request $request, LoggerInterface $lg)
    {
        $em = $this->getdoctrine()->getManager();

        if ($this->getParameter('noconso')==true) {
            throw new AccessDeniedException("Accès interdit (paramètre noconso)");
        }

        $content  = json_decode($request->getContent(), true);
        if ($content == null) {
            return new Response(json_encode('KO - Pas de données'));
        }
        if (empty($content['loginname'])) {
            return new Response(json_encode('KO - Pas de nom de login'));
        } else {
            $loginname = $content['loginname'];
        }
        if (empty($content['projet'])) {
            return new Response(json_encode('KO - Pas de projet'));
        } else {
            $idProjet = $content['projet'];
        }
        if (empty($content['idIndividu'])) {
            return new Response(json_encode('KO - Pas de idIndividu'));
        } else {
            $idIndividu = $content['idIndividu'];
        }

        $error = [];
        $projet      = $em->getRepository(Projet::class)->find($idProjet);
        if ($projet == null) {
            $error[]    =   'No Projet ' . $idProjet;
        }

        $individu = $em->getRepository(Individu::class)->find($idIndividu);
        if ($individu == null) {
            $error[]    =   'No idIndividu ' . $idIndividu;
        }

        if ($error != []) {
            return new Response(json_encode(['KO' => $error ]));
        }

        $versions = $projet->getVersion();
        foreach ($versions as $version) {
            if ($version->getEtatVersion() == Etat::ACTIF             ||
                $version->getEtatVersion() == Etat::ACTIF_TEST        ||
                $version->getEtatVersion() == Etat::EN_ATTENTE
              ) {
                foreach ($version->getCollaborateurVersion() as $collaborateurVersion) {
                    $collaborateur  =  $collaborateurVersion->getCollaborateur() ;
                    if ($collaborateur != null && $collaborateur->isEqualTo($individu)) {
                        // Pas de pb pour écraser un loginnma précédent
                        // A moins qu'il ait déjà un mot de passe !
                        if ($collaborateurVersion->getLoginname() != null) {
                            $old_loginname = $collaborateurVersion->getLoginname();
                            $user = $em->getRepository(User::class)->findBy([ 'loginname' => $old_loginname ]);
                            if ($user != null) {
                                return new Response(json_encode('KO - Commencez par appeler clearpassword'));
                            }
                        }
                        $collaborateurVersion->setLoginname($loginname);
                        Functions::sauvegarder($collaborateurVersion, $em);
                        return new Response(json_encode('OK'));
                    }
                }
            }
        }
        return new Response(json_encode(['KO' => 'Mauvais projet ou mauvais idIndividu !' ]));
    }

    /**
      * set password
      *
      * @Route("/users/setpassword", name="set_password", methods={"POST"})
      * @Security("is_granted('ROLE_ADMIN')")

      * Positionne le mot de passe du user demandé, à condition que ce user existe dans la table collaborateurVersion
      */

    // curl --netrc -H "Content-Type: application/json" -X POST -d '{ "loginname": "toto", "password": "azerty", "cpassword": "qwerty" }' https://.../adminux/users/setpassword

    public function setpasswordAction(Request $request, LoggerInterface $lg)
    {
        $em = $this->getDoctrine()->getManager();
        //$sp = $this->sp;
        //$rep= $em->getRepository(Projet::class);

        if ($this->getParameter('noconso')==true) {
            throw new AccessDeniedException("Accès interdit (paramètre noconso)");
        }

        $content  = json_decode($request->getContent(), true);
        if ($content == null) {
            return new Response(json_encode('KO - Pas de données'));
        }
        if (empty($content['loginname'])) {
            return new Response(json_encode('KO - Pas de nom de login'));
        } else {
            $loginname = $content['loginname'];
        }

        if (empty($content['password'])) {
            return new Response(json_encode('KO - Pas de mot de passe'));
        } else {
            $password = $content['password'];
        }

        if (empty($content['cpassword'])) {
            return new Response(json_encode('KO - Pas de version cryptée du mot de passe'));
        } else {
            $cpassword = $content['cpassword'];
        }

        # Calcul de la date d'expiration
        $pwd_duree = $this->getParameter('pwd_duree');  // Le nombre de jours avant expiration du mot de passe
        $grdt      = $this->sd;
        $passexpir = $grdt->getNew()->add(new \DateInterval($pwd_duree));

        # Vérifie que ce loginname est connu
        $cv = $em->getRepository(User::class)->existsLoginname($loginname);
        if ($cv==false) {
            return new Response(json_encode(['KO' => "No user '$loginname' found in any projet" ]));
        }

        # Modifier le mot de passe
        else {
            $user = $em->getRepository(User::class)->findOneBy(['loginname' => $loginname]);
            if ($user==null) {
                $user = new User();
                $user->setLoginname($loginname);
            }

            // Le mot de passe est tronqué à 50 caractères, puis crypté
            $password = substr($password, 0, 50);
            $password = Functions::simpleEncrypt($password);
            $user->setPassword($password);
            $user->setPassexpir($passexpir);
            $user->setCpassword($cpassword);

            // On n'utilise pas Functions::sauvegarder parce que problèmes de message d'erreur
            // TODO - A creuser
            $em->persist($user);
            $em->flush($user);
            //Functions::sauvegarder( null, $em, $lg );
            return new Response(json_encode('OK'));
        }
    }

    /**
      * clear password
      *
      * Efface le mot de passe temporaire pour le user passé en paramètres
      *
      * @Route("/users/clearpassword", name="clear_password", methods={"POST"})
      * @Security("is_granted('ROLE_ADMIN')")
      *
      * Efface le mot de passe du user demandé
      */

    // curl --netrc -H "Content-Type: application/json" -X POST -d '{ "loginname": "toto" }' https://.../adminux/users/clearpassword

    public function clearpasswordAction(Request $request, LoggerInterface $lg)
    {
        $em = $this->getDoctrine()->getManager();

        if ($this->getParameter('noconso')==true) {
            throw new AccessDeniedException("Accès interdit (parametre noconso)");
        }

        $content  = json_decode($request->getContent(), true);
        if ($content == null) {
            return new Response(json_encode('KO - Pas de donnees'));
        }
        if (empty($content['loginname'])) {
            return new Response(json_encode('KO - Pas de nom de login'));
        } else {
            $loginname = $content['loginname'];
        }

        # Vérifie que ce loginname est connu
        $cv = $em->getRepository(User::class)->existsLoginname($loginname);
        if ($cv==false) {
            return new Response(json_encode(['KO' => "No user '$loginname' found in any projet" ]));
        }

        # effacer l'enregistrement
        else {
            $user = $em->getRepository(User::class)->findOneBy(['loginname' => $loginname]);
            if ($user==null) {
                return new Response(json_encode(['KO' => "No password stored for '$loginname'" ]));
            }

            $em->remove($user);
            $em->flush();
        }
        return new Response(json_encode('OK'));
    }

    /**
      * clear loginname
      *
      * Efface le login name (en cas de fermeture d'un compte) pour le user passé en paramètres
      * Efface aussi le mot de passe s'il y en a un
      *
      * @Route("/users/clearloginname", name="clear_loginname", methods={"POST"})
      * @Security("is_granted('ROLE_ADMIN')")
      *
      * Efface le loginname s'il existe, ne fait rien sinon
      */

    // curl --netrc -H "Content-Type: application/json" -X POST -d '{ "loginname": "toto" }' https://.../adminux/users/clearloginname

    public function clearloginnameAction(Request $request, LoggerInterface $lg)
    {
        $em = $this->getDoctrine()->getManager();

        if ($this->getParameter('noconso')==true) {
            throw new AccessDeniedException("Accès interdit (parametre noconso)");
        }

        $content  = json_decode($request->getContent(), true);
        if ($content == null) {
            return new Response(json_encode('KO - Pas de donnees'));
        }
        if (empty($content['loginname'])) {
            return new Response(json_encode('KO - Pas de nom de login'));
        } else {
            $loginname = $content['loginname'];
        }

        # Vérifie que ce loginname est connu
        $cvs = $em->getRepository(User::class)->findByLoginname($loginname);
        $cnt = count($cvs);
        if ($cnt==0) {
            return new Response(json_encode(['KO' => "No user '$loginname' found in any active version" ]));
        }
        elseif ($cnt > 1)
        {
            return new Response(json_encode(['KO' => "$cnt users '$loginname' found !"]));
        }
        else
        {
            # Cherche et efface le mot de passe au besoin
            $user = $em->getRepository(User::class)->findOneBy(['loginname' => $loginname]);
            if ($user!=null) {
                $em->remove($user);
            }
            
            # efface le loginname
            $cv = $cvs[0];
            $cv->setLoginname(null);
            $em->persist($cv);
            $em->flush();
        }
        return new Response(json_encode('OK'));
    }

    /**
     * get versions non terminées
     *
     * @Route("/versions/get", name="get_versions", methods={"POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Exemples de données POST (fmt json):
     *                ''
     *             ou
     *             '{ "projet" : null,     "session" : null }' -> Toutes les VERSIONS ACTIVES quelque soit la session
     *
     *             '{ "projet" : "P01234" }'
     *             ou
     *             '{ "projet" : "P01234", "session" : null }' -> LA VERSION ACTIVE du projet P01234
     *
     *             '{ "session" : "20A"}
     *             ou
     *             '{ "projet" : null,     "session" : "20A"}' -> Toutes les versions de la session 20A
     *
     *             '{ "projet" : "P01234", "session" : "20A"}' -> La version 20AP01234
     *
     * Donc on renvoie une ou plusieurs versions appartenant à différentes sessions, mais une ou zéro versions par projet
     * Les versions renvoyées peuvent être en état: ACTIF, EN_ATTENTE, NOUVELLE_VERSION_DEMANDEE si "session" vaut null
     * Les versions renvoyées peuvent être dans n'importe quel état (sauf ANNULE) si "session" est spécifiée
     *
     * Données renvoyées (fmt json):
     *                 idProjet    P01234
     *                 idSession    20A
     *                 idVersion    20AP01234
     *                 mail        mail du responsable de la version
     *                 attrHeures    Heures cpu attribuées
     *                 quota        Quota sur la machine
     *                 gpfs        sondVolDonnPerm stockage permanent demandé (pas d'attribution pour le stockage)
     *
     */
    // curl --netrc -H "Content-Type: application/json" -X POST -d '{ "projet": "P1234" }' https://.../adminux/versions/get
    // TODO --------- VIRER CETTE FONCTION, REMPLACEE PAR projetsGetAction !!!

    public function versionsGetAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $sp = $this->sp;
        $versions = [];

        $content  = json_decode($request->getContent(), true);
        if ($content == null) {
            $id_projet = null;
            $id_session= null;
        } else {
            $id_projet  = (isset($content['projet'])) ? $content['projet'] : null;
            $id_session = (isset($content['session'])) ? $content['session'] : null;
        }

        $v_tmp = [];
        // Tous les projets actifs
        if ($id_projet == null && $id_session == null) {
            $sessions = $em->getRepository(Session::class)->get_sessions_non_terminees();
            foreach ($sessions as $sess) {
                //$versions = $em->getRepository(Version::class)->findSessionVersionsActives($sess);
                $v_tmp = array_merge($v_tmp, $em->getRepository(Version::class)->findSessionVersions($sess));
            }
        }

        // Tous les projets d'une session particulière  (on filtre les projets annulés)
        elseif ($id_projet == null) {
            $sess  = $em->getRepository(Session::class)->find($id_session);
            $v_tmp = $em->getRepository(Version::class)->findSessionVersions($sess);
        }

        // La version active d'un projet donné
        elseif ($id_session == null) {
            $projet = $em->getRepository(Projet::class)->find($id_projet);
            if ($projet != null) {
                $v_tmp[]= $projet->getVersionActive();
            }
        }

        // Une version particulière
        else {
            $projet = $em->getRepository(Projet::class)->find($id_projet);
            $sess  = $em->getRepository(Session::class)->find($id_session);
            $v_tmp[] = $em->getRepository(Version::class)->findOneVersion($sess, $projet);
        }

        // SEULEMENT si session n'est pas spécifié: On ne garde que les versions actives... ou presque actives
        if ($id_session == null) {
            $etats = [Etat::ACTIF, Etat::EN_ATTENTE, Etat::NOUVELLE_VERSION_DEMANDEE, Etat::ACTIF_TEST];
            foreach ($v_tmp as $v) {
                if ($v == null) {
                    continue;
                }
                if ($v->getSession()->getEtatSession() != Etat::TERMINE) {
                    if (in_array($v->getEtatVersion(), $etats, true)) {
                        //if ($v->getProjet()->getMetaEtat() === 'ACCEPTE' || $v->getProjet()->getMetaEtat() === 'NONRENOUVELE')
                        $versions[] = $v;
                    }
                }
            }
        }

        // Si la session est spécifiée: On renvoie la version demandée, quelque soit son état
        // On renvoie aussi l'état de la version et l'état de la session
        else {
            $versions = $v_tmp;
        }

        $retour = [];
        foreach ($versions as $v) {
            if ($v==null) {
                continue;
            }
            $r = $this->__getVersionInfo($v);
            $retour[] = $r;
        };

        // print_r est plus lisible pour le déboguage
        // return new Response(print_r($retour,true));
        return new Response(json_encode($retour));
    }

    private function __getVersionInfo($v)
    {
        $sp    = $this->sp;
        $em    = $this->getDoctrine()->getManager();

        $annee = 2000 + $v->getSession()->getAnneeSession();
        $attr  = $v->getAttrHeures() - $v->getPenalHeures();
        foreach ($v->getRallonge() as $r) {
            $attr += $r->getAttrHeures();
        }

        // Pour une session de type B = Aller chercher la version de type A correspondante et ajouter les attributions
        // TODO - Des fonctions de haut niveau (au niveau projet par exemple) ?
        if ($v->getSession()->getTypeSession()) {
            $id_va = $v->getAutreIdVersion();
            $va = $em->getRepository(Version::class)->find($id_va);
            if ($va != null) {
                $attr += $va->getAttrHeures();
                $attr -= $va->getPenalHeures();
                foreach ($va->getRallonge() as $r) {
                    $attr += $r->getAttrHeures();
                }
            }
        }
        $r = [];
        $r['idProjet']        = $v->getProjet()->getIdProjet();
        $r['idSession']       = $v->getSession()->getIdSession();
        $r['idVersion']       = $v->getIdVersion();
        $r['etatVersion']     = $v->getEtatVersion();
        $r['etatProjet']      = $v->getProjet()->getEtatProjet();
        $resp = $v->getResponsable();
        $r['mail']            = $resp == null ? null : $resp->getMail();
        $r['attrHeures']      = $attr;
        $r['sondVolDonnPerm'] = $v->getSondVolDonnPerm();
        // Pour le déboguage
        // if ($r['quota'] != $r['attrHeures']) $r['attention']="INCOHERENCE";
        $r['quota']			  = $sp->getConsoRessource($v->getProjet(), 'cpu', $annee)[1];
        return $r;
    }

    /**
     * get projets non terminés
     *
     * @Route("/projets/get", name="get_projets", methods={"POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Exemples de données POST (fmt json):
     *             ''
     *             ou
     *             '{ "projet" : null     }' -> Tous les projets non terminés
     *
     *             '{ "projet" : "P01234" }' -> Le projet P01234
     *
     * Renvoie les informations utiles sur les projets non terminés, à savoir:
     *     - typeProjet
     *     - etatProjet
     *     - metaEtat
     *     - nepasterminer (True/False)
     *     - versionActive   -> On renvoie les mêmes données que getVersion
     *     - versionDerniere -> On renvoie les mêmes données que getVersion
     *
     * Données renvoyées pour versionActive et versionDerniere:
     *          idProjet    P01234
     *          idSession   20A
     *          idVersion   20AP01234
     *          mail        mail du responsable de la version
     *          attrHeures  Heures cpu attribuées
     *          quota       Quota sur la machine
     *          gpfs        sondVolDonnPerm stockage permanent demandé (pas d'attribution pour le stockage)
     *
     */
    // curl --netrc -H "Content-Type: application/json" -X POST -d '{ "projets": "P1234" }' https://.../adminux/projets/get

    public function projetsGetAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $sp = $this->sp;
        $rep= $em->getRepository(Projet::class);

        $content  = json_decode($request->getContent(), true);
        //print_r($content);
        if ($content == null) {
            $id_projet = null;
        } else {
            $id_projet  = (isset($content['projet'])) ? $content['projet'] : null;
        }

        $p_tmp = [];
        $projets = [];
        if ($id_projet == null) {
            $projets = $rep->findNonTermines();
        } else {
            $projets[] = $rep->findOneBy(["idProjet" => $id_projet]);
        }

        foreach ($projets as $p) {
            $data = [];
            $data['idProjet']      = $p->getIdProjet();
            $data['etatProjet']    = $p->getEtat();
            $data['metaEtat']      = $sp->getMetaEtat($p);
            $va = ($p->getVersionActive()!=null) ? $p->getVersionActive() : null;
            $vb = ($p->getVersionDerniere()!=null) ? $p->getVersionDerniere() : null;
            $v_data = [];
            foreach (["active"=>$va,"derniere"=>$vb] as $k=>$v) {
                if ($v != null) {
                    $v_data[$k] = $this->__getVersionInfo($v);
                }
            }
            $data['versions'] = $v_data;
            $p_tmp[] = $data;
        }

        return new Response(json_encode($p_tmp));
    }

    /**
     * get utilisateurs
     *
     * @Route("/utilisateurs/get", name="get_utilisateurs", methods={"POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     *
     * Exemples de données POST (fmt json):
     *             ''
     *             ou
     *             '{ "projet" : null,     "mail" : null }' -> Tous les collaborateurs avec login
     *
     *             '{ "projet" : "P01234" }'
     *             ou
     *             '{ "projet" : "P01234", "mail" : null }' -> Tous les collaborateurs avec login du projet P01234 (version ACTIVE)
     *
     *             '{ "mail" : "toto@exemple.fr"}
     *             ou
     *             '{ "projet" : null,     "mail" : "toto@exemple.fr"}' -> Tous les projets dans lesquels ce collaborateur a un login (version ACTIVE de chaque projet)
     *
     *             '{ "projet" : "P01234", "mail" : "toto@exemple.fr" }' -> rien ou toto si toto avait un login sur ce projet
     *
     * Par défaut on ne considère QUE les versions actives et dernières de CHAQUE PROJET
     * MAIS si on AJOUTE un PARAMETRE "session" : "20A" on travaille sur la session passée en paramètres (ici 20A)
     *
     * On renvoie pour chaque projet, ou pour un projet donné, la liste des collaborateurs qui doivent avoir un login
     *
     * Données renvoyées (fmt json):
     *
     *             "toto@exemple.fr" : {
     *                  "idIndividu": 75,
     *                  "nom" : "Toto",
     *                  "prenom" : "Ernest",
     *                  "projets" : {
     *                  "P01234" : "toto",
     *                  "P56789" : "etoto"
     *                  }
     *              },
     *             "titi@exemple.fr": ...
     *
     *
     */

    // curl --netrc -H "Content-Type: application/json" -X POST  -d '{ "projet" : "P1234", "mail" : null, "session" : "19A" }' https://.../adminux/utilisateurs/get

    public function utilisateursGetAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $raw_content = $request->getContent();
        if ($raw_content == '' || $raw_content == '{}') {
            $content = null;
        } else {
            $content  = json_decode($request->getContent(), true);
        }
        if ($content == null) {
            $id_projet = null;
            $id_session= null;
            $mail      = null;
        } else {
            $id_projet  = (isset($content['projet'])) ? $content['projet'] : null;
            $mail       = (isset($content['mail'])) ? $content['mail'] : null;
            $id_session = (isset($content['session'])) ? $content['session'] : null;
        }
        //return new Response(json_encode([$id_projet,$id_session]));

        // $sessions  = $em->getRepository(Session::class)->get_sessions_non_terminees();
        $users = [];
        $projets = [];

        // Tous les collaborateurs de tous les projets non terminés
        if ($id_projet == null && $mail == null) {
            $projets = $em->getRepository(Projet::class)->findNonTermines();
        }

        // Tous les projets dans lesquels une personne donnée a un login
        elseif ($id_projet == null) {
            $projets = $em->getRepository(Projet::class)->findNonTermines();
        }

        // Tous les collaborateurs d'un projet
        elseif ($mail == null) {
            $p = $em->getRepository(Projet::class)->find($id_projet);
            if ($p != null) {
                $projets[] = $p;
            }
        }

        // Un collaborateur particulier d'un projet particulier
        else {
            $p = $em->getRepository(Projet::class)->find($id_projet);
            if ($p->getEtatProjet() != Etat::TERMINE) {
                $projets[] = $p;
            }
        }

        //
        // Construire le tableau $users:
        //      toto@exemple.com => [ 'idIndividu' => 34, 'nom' => 'Toto', 'prenom' => 'Ernest', 'projets' => [ 'p0123' => 'toto', 'p456' => 'toto1' ] ]
        //
        //$pdbg=[];
        //foreach ($projets as $p) {
            //$pdbg[] = $p->getIdProjet();
        //};
        //return new Response(json_encode($pdbg));

        foreach ($projets as $p) {
            $id_projet = $p->getIdProjet();
            
            // Si session non spécifiée, on prend la version active ET dernière de chaque projet !
            $vs = [];
            $vs_labels = [];
            if ($id_session==null) {
                if ($p->getVersionDerniere() == null) {
                    $this->sj->warningMessage("ATTENTION - Projet $p SANS DERNIERE VERSION !");
                    continue;   // oups, projet bizarre
                } else {
                    $vs[] = $p->getVersionDerniere();
                    $vs_labels[] = 'derniere';
                }
                if ($p->getVersionActive() != null) {
                    $vs[] = $p->getVersionActive();
                    $vs_labels[] = 'active';
                }
            }

            // Sinon, on prend la version de cette session... si elle existe
            else {
                $id_version = $id_session . $id_projet;
                $req        = $em->getRepository(Version::class)->findBy(['idVersion' =>$id_version]);
                //return new Response(json_encode($req[0]));
                if ($req != null) {
                    $vs[] = $req[0];
                    $vs_labels[] = $id_version;
                }
            }

            // $vs contient 1 ou 2 versions
            $i = 0; // i=0 -> version dernère, $i=1 -> version active
            
            foreach ($vs as $v) {
                $collaborateurs = $v->getCollaborateurVersion();
                foreach ($collaborateurs as $c) {
                    $m = $c -> getCollaborateur() -> getMail();
                    if ($mail != null && strtolower($mail) != strtolower($m)) {
                        continue;
                    }

                    if (!isset($users[$m])) {
                        $users[$m] = [];
                        $users[$m]['nom']        = $c -> getCollaborateur() -> getNom();
                        $users[$m]['prenom']     = $c -> getCollaborateur() -> getPrenom();
                        $users[$m]['idIndividu'] = $c -> getCollaborateur() -> getIdIndividu();
                        $users[$m]['projets']    = [];
                    }

                    if ( isset($users[$m]['projets'][$id_projet])) {
                        $prj_info = $users[$m]['projets'][$id_projet];
                    } else {
                        $prj_info = [];
                    }
                    
                    if (!isset($prj_info['loginname'])) {
                        $prj_info['loginname'] = $c->getLoginname();
                    }
                    
                    $v_info = [];
                    $v_info['version'] = $v->getIdVersion();
                    $v_info['login'] = $c->getLogin();
                    $v_info['clogin'] = $c->getClogin();

                    $prj_info[$vs_labels[$i]] = $v_info;

                    $users[$m]['projets'][$id_projet] = $prj_info;
                }
                $i += 1;
            }
        }


        // print_r est plus lisible pour le déboguage
        //return new Response(print_r($users,true));
        return new Response(json_encode($users));
    }

    /**
	 * get users
	 *
	 * @Route("/users/get", name="get_users", methods={"POST"})
	 * @Security("is_granted('ROLE_ADMIN')")
	 *
	 * Exemples de données POST (fmt json):
	 * 			   ''
	 *             ou
	 *             '{ "projet" : null,     "mail" : null }' -> Tous les collaborateurs avec login
	 *
	 *             '{ "projet" : "P01234" }'
	 *             ou
	 *             '{ "projet" : "P01234", "mail" : null }' -> Tous les collaborateurs avec login du projet P01234 (version ACTIVE)
	 *
	 *             '{ "mail" : "toto@exemple.fr"}
	 *             ou
	 *             '{ "projet" : null,     "mail" : "toto@exemple.fr"}' -> Tous les projets dans lesquels ce collaborateur a un login (version ACTIVE de chaque projet)
	 *
	 *             '{ "projet" : "P01234", "mail" : "toto@exemple.fr" }' -> rien ou toto si toto avait un login sur ce projet
	 *
	 * Par défaut on ne considère QUE les version actives de CHAQUE PROJET
	 * MAIS si on AJOUTE un PARAMETRE "session" : "20A" on travaille sur la session passée en paramètres (ici 20A)
	 *
	 * On renvoie pour chaque projet, ou pour un projet donné, la liste des collaborateurs qui doivent avoir un login
	 *
	 * Données renvoyées (fmt json):
	 * 
	 *             "toto@exemple.fr" : {
	 *                  "idIndividu": 75,
	 *                  "nom" : "Toto",
	 * 				    "prenom" : "Ernest",
	 *                  "projets" : {
	 * 			           "P01234" : "toto",
	 *                     "P56789" : "etoto"
	 *                  }
	 *              },
	 *             "titi@exemple.fr": ...
	 *
	 *
	 */

	// curl --netrc -H "Content-Type: application/json" -X POST  -d '{ "projet" : "P0044", "mail" : null, "session" : "19A" }' https://attribution-ressources-dev.calmip.univ-toulouse.fr/gramc2-manu/adminux/users/get

    // TODO --------- VIRER CETTE FONCTION, REMPLACEE PAR utilisateursGetAction !!!
	 public function usersGetAction(Request $request)
	 {
		$em = $this->getDoctrine()->getManager();
		$raw_content = $request->getContent();
		if ($raw_content == '' || $raw_content == '{}')
		{
			$content = null;
		}
		else
		{
			$content  = json_decode($request->getContent(),true);
		}
		if ($content == null)
		{
			$id_projet = null;
			$id_session= null;
			$mail      = null;
		}
		else
		{
			$id_projet  = (isset($content['projet'])) ? $content['projet'] : null;
			$mail       = (isset($content['mail']))? $content['mail']: null;
			$id_session = (isset($content['session']))? $content['session']: null;
		}

//		$sessions  = $em->getRepository(Session::class)->get_sessions_non_terminees();
		$users = [];
		$projets   = [];

		// Tous les collaborateurs de tous les projets non terminés
		if ($id_projet == null && $mail == null)
		{
			$projets = $em->getRepository(Projet::class)->findNonTermines();
		}

		// Tous les projets dans lesquels une personne donnée a un login
		elseif ($id_projet == null)
		{
			$projets = $em->getRepository(Projet::class)->findNonTermines();
		}

		// Tous les collaborateurs d'un projet
		elseif ($mail == null)
		{
			$p = $em->getRepository(Projet::class)->find($id_projet);
			if ($p != null)
			{
				$projets[] = $p;
			}
		}

		// Un collaborateur particulier d'un projet particulier
		else
		{
			$p = $em->getRepository(Projet::class)->find($id_projet);
			if ($p->getEtatProjet() != Etat::TERMINE)
			{
				$projets[] = $p;
			}
		}

		//
		// Construire le tableau $users:
		//      toto@exemple.com => [ 'idIndividu' => 34, 'nom' => 'Toto', 'prenom' => 'Ernest', 'projets' => [ 'p0123' => 'toto', 'p456' => 'toto1' ] ]
		//
		foreach ($projets as $p)
		{
			// Si session non spécifiée, on prend la version active de chaque projet !
			if ($id_session==null)
			{
				$v = $p->getVersionActive();
			}

			// Sinon, on prend la version de cette session... si elle existe
			else
			{
				$id_version = $id_session . $p->getIdProjet();
				$v          = $em->getRepository(Version::class)->find($id_version);
			}

			if ($v != null)
			{
				$collaborateurs = $v->getCollaborateurVersion();
				foreach ($collaborateurs as $c)
				{
					if ($c->getLogin())
					{
						$m = $c -> getCollaborateur() -> getMail();
						if ($mail != null && strtolower($mail) != strtolower($m))
						{
							continue;
						}

						if (!isset($users[$m]))
						{
							$users[$m] = [];
							$users[$m]['nom']        = $c -> getCollaborateur() -> getNom();
							$users[$m]['prenom']     = $c -> getCollaborateur() -> getPrenom();
							$users[$m]['idIndividu'] = $c -> getCollaborateur() -> getIdIndividu();
							$users[$m]['projets']    = [];
						}
						$users[$m]['projets'][$p->getIdProjet()] = $c->getLoginname();
					}
				}
			}
		}

		// print_r est plus lisible pour le déboguage
		//return new Response(print_r($users,true));
		return new Response(json_encode($users));
	 }


    /**
     * get loginname
     *
     * @Route("/getloginnames/{idProjet}/projet", name="get_loginnames", methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     */
    // curl --netrc -H "Content-Type: application/json" -X GET https://.../adminux/getloginnames/P1234/projet
    public function getloginnamesAction($idProjet)
    {
        $em = $this->getDoctrine()->getManager();
        if ($this->getParameter('noconso')==true) {
            throw new AccessDeniedException("Accès interdit (paramètre noconso)");
        }
        $projet      = $em->getRepository(Projet::class)->find($idProjet);
        if ($projet == null) {
            return new Response(json_encode(['KO' => 'No Projet ' . $idProjet ]));
        }

        $versions    = $projet->getVersion();
        $output      =   [];
        $idProjet    =   $projet->getIdProjet();

        foreach ($versions as $version) {
            if ($version->getEtatVersion() == Etat::ACTIF) {
                foreach ($version->getCollaborateurVersion() as $collaborateurVersion) {
                    if ($collaborateurVersion->getLogin() == false) {
                        continue;
                    }

                    $collaborateur  = $collaborateurVersion->getCollaborateur() ;
                    if ($collaborateur != null) {
                        $loginname  = $collaborateurVersion->getLoginname();
                        $prenom     = $collaborateur->getPrenom();
                        $nom        = $collaborateur->getNom();
                        $idIndividu = $collaborateur->getIdIndividu();
                        $mail       = $collaborateur->getMail();
                        $login      = $collaborateurVersion->getLogin();
                        $clogin     = $collaborateurVersion->getClogin();
                        $output[] =   [
                                'idIndividu' => $idIndividu,
                                'idProjet' =>$idProjet,
                                'mail' => $mail,
                                'prenom' => $prenom,
                                'nom' => $nom,
                                'login' => $login,
                                'loginname' => $loginname,
                                'clogin' => $clogin,
                                ];
                    }
                }
            }
        }
        return new Response(json_encode($output));
    }


    /**
     * Vérifie la base de données, et envoie un mail si l'attribution d'un projet est différente du quota
     *
     * @Route("/quota_check", name="quota_check", methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function quotaCheckAction(Request $request)
    {
        $sd = $this->sd;
        $sn = $this->sn;

        if ($this->getParameter('noconso')==true) {
            throw new AccessDeniedException("Accès interdit (paramètre noconso)");
        }

        $annee_courante = $sd->showYear();
        $sp      = $this->sp;
        $projets = $sp->projetsParAnnee($annee_courante)[0];

        // projets à problème
        $msg = "";
        foreach ($projets as $p) {
            if ($p['attrib'] != $p['q']) {
                $msg .= $p['p']->getIdProjet() . "\t" . $p['attrib'] . "\t\t" . $p["q"] . "\n";
            }
        }

        if ($msg != "") {
            $dest = $sn->mailUsers([ 'S' ], null);
            $sn->sendMessage('notification/quota_check-sujet.html.twig', 'notification/quota_check-contenu.html.twig', [ 'MSG' => $msg ], $dest);
        }

        return $this->render('consommation/conso_update_batch.html.twig');
    }

    /**
     * Vérifie la base de données, supprime les mots de passe temporaires "expirés"
     * et renvoie les mots de passe cryptés (champ cpassword)
     * On pourra vérifier avec le mot de passe du supercalculateur et savoir s'il a été changé
     * Si le mot de passe est expiré, renvoie null
     *
     * @Route("/users/checkpassword", name="check_password", methods={"GET"})
     *
     * curl --netrc -H "Content-Type: application/json" https://.../adminux/users/checkpassword
     *
     */
    public function checkPasswordAction(Request $request, LoggerInterface $lg)
    {
        $em = $this->getdoctrine()->getManager();
        if ($this->getParameter('noconso')==true) {
            throw new AccessDeniedException("Accès interdit (paramètre noconso)");
        }

        if ($this->getParameter('noconso')==true) {
            throw new AccessDeniedException("Accès interdit (paramètre noconso)");
        }

        $sd     = $this->sd;
        $users  = $em->getRepository(User::class)->findAll();
        $rusers = [];
        foreach ($users as $user) {
            $u = [];
            //echo ($user->getPassexpir()->format('Y-m-d')."   ".$sd->format('Y-m-d')."\n");
            if ($user->getPassexpir() <= $sd) {
                $u["loginname"] = $user->getLoginname();
                $u["cpassword"] = null;
            } else {
                $u["loginname"] = $user->getLoginname();
                $u["cpassword"] = $user->getCpassword();
            }
            $rusers[] = $u;
        }
        return new Response(json_encode($rusers));
    }
}
