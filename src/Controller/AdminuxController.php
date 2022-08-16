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
use App\GramcServices\Etat;

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

use Doctrine\ORM\EntityManagerInterface;

/**
 * AdminUx controller: Commandes curl envoyées par l'administrateur unix
 *
 * @Route("/adminux")
 */
class AdminuxController extends AbstractController
{
    public function __construct(
        private ServiceNotifications $sn,
        private ServiceJournal $sj,
        private ServiceProjets $sp,
        private ServiceSessions $ss,
        private GramcDate $sd,
        private ServiceVersions $sv,
        private EntityManagerInterface $em
    ) {}

    /**
     * Met à jour les données de comptabilité à partir d'un unique fichier csv
     *
     * format date, loginname, ressource, type, consommation, quota
     * ressource = cpu, gpu, home, etc.
     * type      = user ou group unix
     * @Route("/compta_update_batch", name="compta_update_batch", methods={"PUT"})
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function UpdateComptaBatchAction(Request $request): Response
    {
        $em = $this->em;
        $sj = $this->sj;
        
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
                $sj -> errorMessage("AdminUxController::UpdateComptaBatchAction - type de ligne bizarre: $type");
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
        }
        catch (\Exception $e)
        {
            $sj -> errorMessage("AdminUxController::UpdateComptaBatchAction - Mise à jour de la compta incomplète");
            return new Response('KO');
        }

        //return new Response( Functions::show( $conso_repository->findAll() ) );
        $sj -> infoMessage(__METHOD__ . "Compta mise à jour");
        return $this->render('consommation/conso_update_batch.html.twig');
    }

    ///////////////////////////////////////////////////////////////////////////////

    /**
     * set loginname
     *
     * @Route("/users/setloginname", name="set_loginname", methods={"POST"})
     * @Route("/utilisateurs/setloginname", name="set_loginname", methods={"POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     *
     * Positionne le loginname du user demandé dans la version active ou EN_ATTENTE du projet demandé
     *
     */

    // exemple: curl --insecure --netrc -X POST -d '{ "loginname": "toto", "idIndividu": "6543", "projet": "P1234" }'https://.../adminux/users/setloginname
    public function setloginnameAction(Request $request, LoggerInterface $lg): Response
    {
        $em = $this->em;
        $sj = $this->sj;

        if ($this->getParameter('noconso')==true) {
            throw new AccessDeniedException("Accès interdit (paramètre noconso)");
        }

        $content  = json_decode($request->getContent(), true);
        if ($content == null) {
            $sj->errorMessage("AdminUxController::setloginnameAction - Pas de données");
            return new Response(json_encode(['KO' => 'Pas de données']));
        }
        if (empty($content['loginname'])) {
            $sj->errorMessage("AdminUxController::setloginnameAction - Pas de nom de login");
            return new Response(json_encode(['KO' => 'Pas de nom de login']));
        } else {
            $loginname = $content['loginname'];
        }
        if (empty($content['projet'])) {
            $sj->errorMessage("AdminUxController::setloginnameAction - Pas de projet");
            return new Response(json_encode(['KO' => 'Pas de projet']));
        } else {
            $idProjet = $content['projet'];
        }
        if (empty($content['idIndividu'])) {
            $sj->errorMessage("AdminUxController::setloginnameAction - Pas de idIndividu");
            return new Response(json_encode(['KO' => 'Pas de idIndividu']));
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
            $sj->errorMessage("AdminUxController::setloginnameAction - " . print_r($error, true));
            return new Response(json_encode(['KO' => $error ]));
        }

        $versions = $projet->getVersion();
        $i=0;
        foreach ($versions as $version) {
            //echo $version->getIdVersion()."\n";
            if ($version->getEtatVersion() == Etat::ACTIF             ||
                $version->getEtatVersion() == Etat::ACTIF_TEST        ||
                $version->getEtatVersion() == Etat::NOUVELLE_VERSION_DEMANDEE ||
                $version->getEtatVersion() == Etat::EN_ATTENTE
              ) {
                foreach ($version->getCollaborateurVersion() as $collaborateurVersion) {
                    $collaborateur  =  $collaborateurVersion->getCollaborateur() ;
                    if ($collaborateur != null && $collaborateur->isEqualTo($individu)) {
                        // Pas de pb pour écraser un loginname précédent
                        // A moins qu'il ait déjà un mot de passe !
                        if ($collaborateurVersion->getLoginname() != null) {
                            $old_loginname = $collaborateurVersion->getLoginname();
                            $user = $em->getRepository(User::class)->findBy([ 'loginname' => $old_loginname ]);
                            if ($user != null) {
                                $sj->errorMessage("AdminUxController::setloginnameAction - Commencez par appeler clearpassword");
                                return new Response(json_encode(['KO' => 'Commencez par appeler clearpassword']));
                            }
                        }
                        $collaborateurVersion->setLoginname($loginname);
                        Functions::sauvegarder($collaborateurVersion, $em);
                        $i += 1;
//                        return new Response(json_encode($collaborateurVersion->getVersion() . ' OK'));
                        break;
                    }
                }
            }
        }
        if ($i > 0 ) {
            $sj -> infoMessage(__METHOD__ . "$i versions modifiées");
            return new Response(json_encode(['OK' => "$i versions modifiees"]));
        } else {
            $sj->errorMessage("AdminUxController::setloginnameAction - Mauvais projet ou mauvais idIndividu !");
            return new Response(json_encode(['KO' => 'Mauvais projet ou mauvais idIndividu !' ]));
        }
    }

    /**
      * set password
      *
      * @Route("/utilisateurs/setpassword", name="set_password", methods={"POST"})
      * @Security("is_granted('ROLE_ADMIN')")

      * Positionne le mot de passe du user demandé, à condition que ce user existe dans la table collaborateurVersion
      */

    // curl --netrc -H "Content-Type: application/json" -X POST -d '{ "loginname": "toto", "password": "azerty", "cpassword": "qwerty" }' https://.../adminux/utilisateurs/setpassword

    public function setpasswordAction(Request $request, LoggerInterface $lg): Response
    {
        $em = $this->em;
        $sj = $this->sj;
        //$sp = $this->sp;
        //$rep= $em->getRepository(Projet::class);

        if ($this->getParameter('noconso')==true) {
            throw new AccessDeniedException("Accès interdit (paramètre noconso)");
        }

        $content  = json_decode($request->getContent(), true);
        if ($content == null) {
            $sj->errorMessage("AdminUxController::setpasswordAction - Pas de données");
            return new Response(json_encode(['KO' => 'Pas de données']));
        }
        if (empty($content['loginname'])) {
            $sj->errorMessage("AdminUxController::setpasswordAction - Pas de nom de login");
            return new Response(json_encode(['KO' => 'Pas de nom de login']));
        } else {
            $loginname = $content['loginname'];
        }

        if (empty($content['password'])) {
            $sj->errorMessage("AdminUxController::setpasswordAction - Pas de mot de passe");
            return new Response(json_encode(['KO' => 'Pas de mot de passe']));
        } else {
            $password = $content['password'];
        }

        if (empty($content['cpassword'])) {
            $sj->errorMessage("AdminUxController::setpasswordAction - Pas de version cryptée du mot de passe");
            return new Response(json_encode(['KO' => 'Pas de version cryptée du mot de passe']));
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
            $sj->errorMessage("AdminUxController::setpasswordAction - No user '$loginname' found in any projet");
            return new Response(json_encode(['KO' => "No user '$loginname' found in any projet" ]));
        }

        # Modifier le mot de passe
        else {
            $user = $em->getRepository(User::class)->findOneBy(['loginname' => $loginname]);
            if ($user==null) {
                $user = new User();
                $user->setLoginname($loginname);
                $user->setExpire(false);
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

            $sj -> infoMessage(__METHOD__ . "Mot de passe de $loginname modifié");
            return new Response(json_encode(['OK' => '']));
        }
    }

    /**
      * clear password
      *
      * Efface le mot de passe temporaire pour le user passé en paramètres
      *
      * @Route("/users/clearpassword", name="clear_password", methods={"POST"})
      * @Route("/utilisateurs/clearpassword", name="clear_password", methods={"POST"})
      * @Security("is_granted('ROLE_ADMIN')")
      *
      * Efface le mot de passe du user demandé
      */

    // curl --netrc -H "Content-Type: application/json" -X POST -d '{ "loginname": "toto" }' https://.../adminux/users/clearpassword

    public function clearpasswordAction(Request $request, LoggerInterface $lg): Response
    {
        $em = $this->em;
        $sj = $this->sj;

        if ($this->getParameter('noconso')==true) {
            throw new AccessDeniedException("Accès interdit (parametre noconso)");
        }

        $content  = json_decode($request->getContent(), true);
        if ($content == null) {
            $sj->errorMessage("AdminUxController::clearpasswordAction - Pas de données");
            return new Response(json_encode(['KO' => 'Pas de donnees']));
        }
        if (empty($content['loginname'])) {
            $sj->errorMessage("AdminUxController::clearpasswordAction - Pas de nom de login");
            return new Response(json_encode(['KO' => 'Pas de nom de login']));
        } else
        {
            $loginname = $content['loginname'];
        }

        # Vérifie que ce loginname est connu
        $cv = $em->getRepository(User::class)->existsLoginname($loginname);
        if ($cv==false) {
            $sj->errorMessage("AdminUxController::clearpasswordAction - No user '$loginname' found in any projet");
            return new Response(json_encode(['KO' => "No user '$loginname' found in any projet" ]));
        }

        # effacer l'enregistrement
        else {
            $user = $em->getRepository(User::class)->findOneBy(['loginname' => $loginname]);
            if ($user==null) {
                $sj->errorMessage("AdminUxController::clearpasswordAction - No password stored for '$loginname");
                return new Response(json_encode(['KO' => "No password stored for '$loginname'" ]));
            }

            $em->remove($user);
            $em->flush();
        }

        $sj -> infoMessage(__METHOD__ . "Mot de passe de $loginname effacé");
        return new Response(json_encode(['OK' => '']));
    }

    /**
      * clear loginname
      *
      * Efface le login name (en cas de fermeture d'un compte) pour le user passé en paramètres
      * Efface aussi le mot de passe s'il y en a un
      *
      * @Route("/users/clearloginname", name="clear_loginname", methods={"POST"})
      * @Route("/utilisateurs/clearloginname", name="clear_loginname", methods={"POST"})
      * @Security("is_granted('ROLE_ADMIN')")
      *
      * Efface le loginname s'il existe, ne fait rien sinon
      */

    // curl --netrc -H "Content-Type: application/json" -X POST -d '{ "loginname": "toto", "projet":"P1234" }' https://.../adminux/utilisateurs/clearloginname

    public function clearloginnameAction(Request $request, LoggerInterface $lg): Response
    {
        $em = $this->em;
        $sj = $this->sj;

        if ($this->getParameter('noconso')==true) {
            throw new AccessDeniedException("Accès interdit (parametre noconso)");
        }

        $content  = json_decode($request->getContent(), true);
        if ($content == null) {
            $sj->errorMessage("AdminUxController::clearloginnameAction - Pas de données");
            return new Response(json_encode(['KO' => 'Pas de donnees']));
        }
        if (empty($content['loginname'])) {
            $sj->errorMessage("AdminUxController::clearloginAction - Pas de nom de login");
            return new Response(json_encode(['KO' => 'Pas de nom de login']));
        } else {
            $loginname = $content['loginname'];
        }
        if (empty($content['projet'])) {
            $sj->errorMessage("AdminUxController::clearloginAction - Pas de projet");
            return new Response(json_encode(['KO' => 'Pas de projet']));
        } else {
            $idProjet = $content['projet'];
        }

        # Vérifie que ce loginname est connu
        $cvs = $em->getRepository(User::class)->findByLoginname($loginname);
        $cnt = count($cvs);
        //return new Response(json_encode($cvs));
        if ($cnt==0) {
            $sj->errorMessage("AdminUxController::clearloginAction - No user '$loginname' found in any active version");
            return new Response(json_encode(['KO' => "No user '$loginname' found in any active version" ]));
        }
        else
        {
            # On supprime le username dans TOUTES les versions du projet demandé
            # Si le username existe dans d'autres projets, on le garde dans ces projets, on garde aussi le mot de passe !
            $keepPwd = false;
            foreach ($cvs as $cv) {
                if ($cv->getVersion()->getProjet()->getIdProjet() == $idProjet) {
                    $cv->setLoginname(null);
                    $em->persist($cv);
                }
                else {
                    $keepPwd = true;
                    continue;
                }                    
            }

            # Cherche et efface le mot de passe au besoin...
            # ... SAUF si $keepPwd est true !

            if ($keepPwd == false) {
                $user = $em->getRepository(User::class)->findOneBy(['loginname' => $loginname]);
                if ($user!=null) {
                    $em->remove($user);
                }
            }
                        
            $em->flush();
        }

        $sj -> infoMessage(__METHOD__ . "Compte $loginname supprimé");
        return new Response(json_encode(['OK' => '']));
    }

    private function __getVersionInfo($v, bool $long): array
    {
        $sp    = $this->sp;
        $em    = $this->em;

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
	$r['quota']              = $sp->getConsoRessource($v->getProjet(), 'cpu', $annee)[1];
        if ($long)
        {
            $r['titre']       = $v->getPrjTitre();
            $r['resume']      = $v->getPrjResume();
            $r['labo']        = $v->getPrjLLabo();
            $r['metadonnees'] = $v->getDataMetaDataFormat();
            $r['thematique']  = $v->getAcroMetaThematique();
        }
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
     *             ou
     *             '{ "projet" : "P01234", "long": "true" }'
     *
     * Pour le paramètre "long" voir la doc de versionGet
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
    // curl --netrc -H "Content-Type: application/json" -X POST -d '{ "projet": "P1234" }' https://.../adminux/projets/get

    public function projetsGetAction(Request $request): Response
    {
        $em = $this->em;
        $sp = $this->sp;
        $sj = $this->sj;
        $rep= $em->getRepository(Projet::class);

        $content  = json_decode($request->getContent(), true);
        //print_r($content);
        if ($content == null) {
            $id_projet = null;
            $long = false;

        } else {
            $id_projet  = (isset($content['projet'])) ? $content['projet'] : null;
            $long = (isset($content['long']))? $content['long']: false;
        }

        $p_tmp = [];
        $projets = [];
        if ($id_projet == null) {
            $projets = $rep->findNonTermines();
        } else {
            $p = $rep->findOneBy(["idProjet" => $id_projet]);
            if ($p != null) {
                $projets[] = $p;
            }
        }

        foreach ($projets as $p) {
            $data = [];
            $data['idProjet']   = $p->getIdProjet();
            $data['etatProjet'] = $p->getEtat();
            $data['metaEtat']   = $sp->getMetaEtat($p);
            $data['typeProjet'] = $p->getTypeProjet();
            $va = ($p->getVersionActive()!=null) ? $p->getVersionActive() : null;
            $vb = ($p->getVersionDerniere()!=null) ? $p->getVersionDerniere() : null;
            $v_data = [];
            foreach (["active"=>$va,"derniere"=>$vb] as $k=>$v) {
                if ($v != null) {
                    $v_data[$k] = $this->__getVersionInfo($v,$long);
                }
            }
            $data['versions'] = $v_data;
            $p_tmp[] = $data;
        }

        $sj -> infoMessage(__METHOD__ . " OK");
        return new Response(json_encode($p_tmp));
    }

    /**
     * get versions non terminées
     *
     * @Route("/version/get", name="get_version", methods={"POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     *
     * Exemples de données POST (fmt json):
     *             ''
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
     * Version "longue" - Le paramètre "long" provoque l'envoi de données supplémentaires concernant la ou les versions:
     * -----------------------------------------------------------------------------------------------------------------
     * 
     *             '{ "projet" : "P01234", "session" : null, "long: true" }' -> LA VERSION ACTIVE du projet P01234
     * 
     * Donc on renvoie une ou plusieurs versions appartenant à différentes sessions, mais une ou zéro versions par projet
     * Les versions renvoyées peuvent être en état: ACTIF, EN_ATTENTE, NOUVELLE_VERSION_DEMANDEE si "session" vaut null
     * Les versions renvoyées peuvent être dans n'importe quel état (sauf ANNULE) si "session" est spécifiée
     *
     * Données renvoyées (fmt json):
     *                 idProjet    P01234
     *                 idSession   20A
     *                 idVersion   20AP01234
     *                 mail        mail du responsable de la version
     *                 attrHeures  Heures cpu attribuées
     *                 quota       Quota sur la machine
     *                 gpfs        sondVolDonnPerm stockage permanent demandé (pas d'attribution pour le stockage)
     *
     * Si "long" est spécifié on renvoie aussi:
     *                 titre       prjTitre
     *                 resume      prjResume
     *                 labo        prjLLabo
     *                 metadonnees dataMetaDataFormat
     *                 thematique  metathematique (ATTENTION ! PAS la thématique au sens de Calmip, mais la Metathématique)
     *
     * curl --netrc -H "Content-Type: application/json" -X POST  -d '{ "projet" : "P1234", "session" : "20A" }' https://.../adminux/version/get
     *
     */
     public function versionGetAction(Request $request): Response
     {
        $em = $this->em;
        $sp = $this->sp;
        $sj = $this->sj;
        
        $versions = [];

        $content  = json_decode($request->getContent(),true);
        if ($content == null)
        {
            $id_projet = null;
            $id_session= null;
            $long = false;
        }
        else
        {
            $id_projet  = (isset($content['projet'])) ? $content['projet'] : null;
            $id_session = (isset($content['session']))? $content['session']: null;
            $long = (isset($content['long']))? $content['long']: false;
        }

        $v_tmp = [];
        // Tous les projets actifs
        if ($id_projet == null && $id_session == null)
        {
            $sessions = $em->getRepository(Session::class)->get_sessions_non_terminees();
            foreach ($sessions as $sess)
            {
                //$versions = $em->getRepository(Version::class)->findSessionVersionsActives($sess);
                $v_tmp = array_merge($v_tmp,$em->getRepository(Version::class)->findSessionVersions($sess));
            }
        }

        // Tous les projets d'une session particulière  (on filtre les projets annulés)
        elseif ($id_projet == null)
        {
            $sess  = $em->getRepository(Session::class)->find($id_session);
            $v_tmp = $em->getRepository(Version::class)->findSessionVersions($sess);
        }

        // La version active d'un projet donné
        elseif ($id_session == null)
        {
            $projet = $em->getRepository(Projet::class)->find($id_projet);
            if ($projet != null) $v_tmp[]= $projet->getVersionActive();
        }

        // Une version particulière
        else
        {
            $projet = $em->getRepository(Projet::class)->find($id_projet);
            $sess  = $em->getRepository(Session::class)->find($id_session);
            $v_tmp[] = $em->getRepository(Version::class)->findOneVersion($sess,$projet);
        }

        // SEULEMENT si session n'est pas spécifié: On ne garde que les versions actives... ou presque actives
        if ( $id_session == null )
        {
            $etats = [Etat::ACTIF, Etat::EN_ATTENTE, Etat::NOUVELLE_VERSION_DEMANDEE, Etat::ACTIF_TEST];
            foreach ($v_tmp as $v)
            {
                if ($v == null) continue;
                if ($v->getSession()->getEtatSession() != Etat::TERMINE)
                {
                    if (in_array($v->getEtatVersion(),$etats,true))
                    //if ($v->getProjet()->getMetaEtat() === 'ACCEPTE' || $v->getProjet()->getMetaEtat() === 'NONRENOUVELE')
                    {
                        $versions[] = $v;
                    }
                }
            }
        }

        // Si la session est spécifiée: On renvoie la version demandée, quelque soit son état
        // On renvoie aussi l'état de la version et l'état de la session
        else
        {
            $versions = $v_tmp;
        }

        $retour = [];
        foreach ($versions as $v)
        {
            if ($v==null) continue;
            $annee = 2000 + $v->getSession()->getAnneeSession();
            $attr  = $v->getAttrHeures() - $v->getPenalHeures();
            foreach ($v->getRallonge() as $r)
            {
                $attr += $r->getAttrHeures();
            }

            // Pour une session de type B = Aller chercher la version de type A correspondante et ajouter les attributions
            // TODO - Des fonctions de haut niveau (au niveau projet par exemple) ?
            if ($v->getSession()->getTypeSession())
            {
                $id_va = $v->getAutreIdVersion();
                $va = $em->getRepository(Version::class)->find($id_va);
                if ($va != null)
                {
                    $attr += $va->getAttrHeures();
                    $attr -= $va->getPenalHeures();
                    foreach ($va->getRallonge() as $r)
                    {
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
            $r['mail']            = $v->getResponsable()->getMail();
            $r['attrHeures']      = $attr;
            $r['sondVolDonnPerm'] = $v->getSondVolDonnPerm();
            $r['quota']           = $sp->getConsoRessource($v->getProjet(),'cpu',$annee)[1];
            if ($long)
            {
                $r['titre']       = $v->getPrjTitre();
                $r['resume']      = $v->getPrjResume();
                $r['labo']        = $v->getPrjLLabo();
                $r['metadonnees'] = $v->getDataMetaDataFormat();
                $r['thematique']  = $v->getAcroMetaThematique();
            }
            
            // Pour le déboguage
            // if ($r['quota'] != $r['attrHeures']) $r['attention']="INCOHERENCE";

            $retour[] = $r;
            //$retour[] = $v->getIdVersion();
        };

        // print_r est plus lisible pour le déboguage
        // return new Response(print_r($retour,true));
        $sj -> infoMessage(__METHOD__ . " OK");
        return new Response(json_encode($retour));

     }

    /**
     * Changer le quota de la version active d'un projet
     *
     * @Route("/projets/setquota", name="set_quota", methods={"POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Exemples de données POST (fmt json):
     *
     *             '{ "projet" : "P01234", "session" : "20A", "quota" : "10000"}' -> La version 20AP01234 à condition que ce soit bien la version active !
     *
     * curl --netrc -H "Content-Type: application/json" -X POST  -d '{ "projet" : "P1234", "session" : "20A", "quota" : "10000" }' https://.../adminux/projets/setquota
     */
     public function projetsSetQuotaAction(Request $request): Response
     {
        $em = $this->em;
        $sp = $this->sp;
        $sj = $this->sj;

        // todo - Si ce paramètre n'existe pas ça va planter
        $ressources_conso_group = $this->getParameter('ressources_conso_group');

        // On recherche les ressources marquées "calcul"
        // On initialise le tableau à 'cpu', ou 'cpu','gpu'
        $ressources = [];
        foreach ($ressources_conso_group as $ress)
        {
            if (array_key_exists('type', $ress) && $ress['type'] === 'calcul')
            {
                if (array_key_exists('ress', $ress))
                {
                    $ressources = explode(',',$ress['ress']);
                }
            }
        }

        $content  = json_decode($request->getContent(),true);
        if ($content == null)
        {
            $sj -> errorMessage("AdminUxController::projetsSetQuotaAction - Pas de données");
            return new Response(json_encode(['KO' => 'Pas de données']));
        }

        $idProjet  = (isset($content['projet'])) ? $content['projet'] : null;
        $idSession = (isset($content['session']))? $content['session']: null;
        $quota      = (isset($content['quota']))? $content['quota']: null;

        if ($idProjet === null)
        {
            $sj->errorMessage("AdminUxController::projetsSetQuotaAction - Pas de projet spécifié");
            return new Response(json_encode(['KO' => 'Pas de projet spécifié']));
        }
        if ($idSession === null)
        {
            $sj->errorMessage("AdminUxController::projetsSetQuotaAction - Pas de session spécifiée");
            return new Response(json_encode(['KO' => 'Pas de session spécifiée']));
        }
        if ($quota === null)
        {
            $sj->errorMessage("AdminUxController::projetsSetQuotaAction - Pas de quota spécifié");
            return new Response(json_encode(['KO' => 'Pas de quota spécifié']));
        }
        else
        {
            $quota = intval($quota);
            if ($quota < 0)
            {
                $sj->errorMessage("AdminUxController::projetsSetQuotaAction - quota doit être un entier positif >= 0");
                return new Response(json_encode(['KO' => 'quota doit être un entier positif >= 0']));
            }
        }

        $projet = $em->getRepository(Projet::class)->findOneBy(['idProjet' => $idProjet]);
        if ($projet === null) {
            $sj->errorMessage("AdminUxController::projetsSetQuotaAction - Pas de projet $idProjet");
            return new Response(json_encode(['KO' => "Pas de projet $idProjet"]));
        }

        $session = $em->getRepository(Session::class)->findOneBy(['idSession' => $idSession]);
        if ($session === null) {
            $sj->errorMessage("AdminUxController::projetsSetQuotaAction - Pas de session $idSession");
            return new Response(json_encode(['KO' => "Pas de session $idSession"]));
        }

        $idVersion = $idSession . $idProjet;
        $version = $em->getRepository(Version::class)->findOneBy(['idVersion' => $idVersion]);
        if ($version === null) {
            $sj->errorMessage("AdminUxController::projetsSetQuotaAction - Pas de version $idVersion");
            return new Response(json_encode(['KO' => "Pas de version $idVersion"]));
 
        }

        $veract = $sp->versionActive($projet);
        if ($veract != $version) {
            $sj->errorMessage(__METHOD__ . ':' . __LINE__ . " La version active de $idProjet est $veract, on ne peut pas changer le quota de $idVersion");
            return new Response(json_encode(['KO' => "La version active de $idProjet est $veract, on ne peut pas changer le quota de $idVersion"]));
        }

        // Toutes les vérifications sont terminées, on peut changer le quota
        // Cela revient à écrire directement dans la table de conso
        // On écrit le même quota pour toutes les ressources "calcul"

        $date = $this->sd;  // aujourd'hui
        $loginname = strtolower($idProjet); // Le projet traduit en groupe unix
        $type = 2;                          // Un groupe, pas un utilisateur
        foreach ($ressources as $ress)
        {
            $compta = $em->getRepository(Compta::class)->findOneBy(
                [
                    'date'      => $date,
                    'ressource' => $ress,
                    'loginname' => $loginname,
                    'type'      => $type
                ]);

            // Si pas de compta on crée l'objet (nouveau projet pas encore de compta) !
            if ($compta === null) {
                $compta = new Compta();
                $compta ->setDate($date)
                        ->setRessource($ress)
                        ->setLoginname($loginname)
                        ->setType(2)
                        ->setConso(0);
            }

            $compta->setQuota($quota);
            $em->persist($compta);
            $em->flush();
        }
        
        // OK
        $sj->infoMessage(__METHOD__ . " Le quota de $idVersion est maintenant $quota");
        return new Response(json_encode(['OK' => "Le quota de $idVersion est maintenant $quota"]));
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
     * Par défaut on ne considère QUE les version actives et dernières de chaque projet non terminé
     * MAIS si on AJOUTE un PARAMETRE "session" : "20A" on travaille sur la session passée en paramètres (ici 20A)
     * (on ne considère PAS les projets tests (type==2))
     *
     * On renvoie pour chaque version considérée, la liste des collaborateurs
     * tels que loginname != null (login créé, peut-être à supprimer si login==false),
     * OU loginname=null mais login==true ou clogin==true (login à créer)
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

    public function utilisateursGetAction(Request $request): Response
    {
        $em = $this->em;
        $raw_content = $request->getContent();
        $sj = $this->sj;
        
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
            
            // Si session non spécifiée, on prend toutes les versions de chaque projet !
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

                // Toutes les versions
                // TODO - Viré le 24 Février 2022 - Peut-être qu'on le remettra
                // à condition d'employer un paramètre particulier à la requête
                // Par exemple id_session = all déclenche l'envoi de la totalité des versions des projets non terminés
                // alors que id_session = null déclenche l'envoi des versions DERNIERE et ACTIVE seulement
                
                //foreach ($p->getVersion() as $v) {
                //    // ne pas compter deux fois les versions dernière + active
                //    if (in_array($v,$vs)) continue;
                //    $vs[] = $v;
                //    $vs_labels[] = $v->getIdVersion();
                //}
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

            // $vs contient au moins une version
            $i = 0; // i=0 -> version dernère, $i=1 -> version active
            foreach ($vs as $v) {
                $collaborateurs = $v->getCollaborateurVersion();
                foreach ($collaborateurs as $c) {
                    $m = $c -> getCollaborateur() -> getMail();

                    // si on a spécifié un mail, ne retenir que celui-la
                    if ($mail != null && strtolower($mail) != strtolower($m)) {
                        continue;
                    }

                    // Pas de login demandé ni de login enregistré
                    if ($c->getLogin()==false && $c->getClogin()==false && $c->getLoginname()==null) {
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
                    $v_info['loginname'] = $c->getLoginname();
                    $v_info['deleted'] = $c->getDeleted();
                    
                    $prj_info[$vs_labels[$i]] = $v_info;

                    $users[$m]['projets'][$id_projet] = $prj_info;
                }
                $i += 1;
            }

        }

        // print_r est plus lisible pour le déboguage
        //return new Response(print_r($users,true));
        $sj -> infoMessage(__METHOD__ . " OK");
        return new Response(json_encode($users));
    }

    /**
     * get loginname
     *
     * @Route("/getloginnames/{idProjet}/projet", name="get_loginnames", methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     */
    // curl --netrc -H "Content-Type: application/json" -X GET https://.../adminux/getloginnames/P1234/projet
    public function getloginnamesAction($idProjet): Response
    {
        $em = $this->em;
        $sj = $this->sj;
        
        if ($this->getParameter('noconso')==true) {
            throw new AccessDeniedException("Accès interdit (paramètre noconso)");
        }
        $projet      = $em->getRepository(Projet::class)->find($idProjet);
        if ($projet == null) {
            $sj->infoMessage(__METHOD__ . " No projet $idProjet");
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

        $sj -> infoMessage(__METHOD__ . " OK");
        return new Response(json_encode($output));
    }


    /**
     * Vérifie la base de données, et envoie un mail si l'attribution d'un projet est différente du quota
     *
     * @Route("/quota_check", name="quota_check", methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function quotaCheckAction(Request $request): Response
    {
        $sd = $this->sd;
        $sn = $this->sn;
        $sj = $this->sj;

        if ($this->getParameter('noconso')==true) {
            throw new AccessDeniedException("Accès interdit (paramètre noconso)");
        }

        $annee_courante = $sd->showYear();
        $sp      = $this->sp;
        $projets = $sp->projetsParAnnee($annee_courante)[0];

        // projets à problème
        $msg = "";
        foreach ($projets as $p) {
            // On ne s'occupe pas des projets terminés ou annulés
            // TODO - Tester sur l'état plutôt que sur le meta état,
            //        le méta état est censé être fait SEULEMENT pour l'affichage !
            if ( $p['metaetat'] == "TERMINE" ) continue;
            if ($p['attrib'] != $p['q']) {
                $msg .= $p['p']->getIdProjet() . "\t" . $p['attrib'] . "\t\t" . $p["q"] . "\n";
            }
        }

        if ($msg != "") {
            $dest = $sn->mailUsers([ 'S' ], null);
            $sn->sendMessage('notification/quota_check-sujet.html.twig', 'notification/quota_check-contenu.html.twig', [ 'MSG' => $msg ], $dest);
        }

        $sj -> infoMessage(__METHOD__ . " OK");
        return $this->render('consommation/conso_update_batch.html.twig');
    }

    /**
     * Vérifie la base de données, marque les mots de passe temporaires comme expirés
     * et renvoie les mots de passe cryptés (champ cpassword)
     * On pourra vérifier avec le mot de passe du supercalculateur et savoir s'il a été changé
     * Si le mot de passe est expiré, renvoie null
     *
     * @Route("/users/checkpassword", name="check_password", methods={"GET"})
     * @Route("/utilisateurs/checkpassword", name="check_password", methods={"GET"})
     *
     * curl --netrc -H "Content-Type: application/json" https://.../adminux/utilisateurs/checkpassword
     *
     */
    public function checkPasswordAction(Request $request, LoggerInterface $lg): Response
    {
        $em = $this->em;
        $sj = $this->sj;
        
        if ($this->getParameter('noconso')==true) {
            throw new AccessDeniedException("Accès interdit (paramètre noconso)");
        }

        if ($this->getParameter('noconso')==true) {
            throw new AccessDeniedException("Accès interdit (paramètre noconso)");
        }

        $sd     = $this->sd;
        $users  = $em->getRepository(User::class)->findAll();
        $rusers = [];
        foreach ($users as $user)
        {
            $u = [];
            // Si nécessaire on marque le user comme expiré, mais on ne supprime rien
            if ($user->getPassexpir() <= $sd && $user->getExpire() == false)
            {
                $user->setExpire(true);
                $em->persist($user);
                $em->flush();
                
            }

            // On ne devrait jamais rentrer dans le if mais on ajoute de la robustesse
            if ($user->getPassexpir() > $sd && $user->getExpire() == true)
            {
                $user->setExpire(false);
                $em->persist($user);
                $em->flush();
                
            }

            $u["loginname"] = $user->getLoginname();
            $u["cpassword"] = $user->getCpassword();
            $u['expire'] = $user->getExpire();
            $rusers[] = $u;
        }

        $sj -> infoMessage(__METHOD__ . " OK");
        return new Response(json_encode($rusers));
    }
}
