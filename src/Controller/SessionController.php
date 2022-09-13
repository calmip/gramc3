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

use App\Entity\Session;
use App\Entity\Projet;
use App\Entity\Version;
use App\Entity\CollaborateurVersion;

use App\GramcServices\GramcDate;
use App\GramcServices\ServiceJournal;
use App\GramcServices\ServiceVersions;
use App\GramcServices\ServiceMenus;
use App\GramcServices\ServiceProjets;
use App\GramcServices\ServiceSessions;
use App\GramcServices\ServicePhpSessions;
use App\GramcServices\Workflow\Session\SessionWorkflow;

use App\BilanSession\BilanSessionA;
use App\BilanSession\BilanSessionB;

use App\Utils\Functions;
use App\GramcServices\Etat;
use App\GramcServices\Signal;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormInterface;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Session controller.
 *
 * @Route("session")
 */
class SessionController extends AbstractController
{
    public function __construct(
        private ServiceJournal $sj,
        private ServiceMenus $sm,
        private ServicePhpSessions $sps,
        private ServiceVersions $sv,
        private ServiceProjets $sp,
        private ServiceSessions $ss,
        private GramcDate $sd,
        private SessionWorkflow $sw,
        private EntityManagerInterface $em
    ) {}

    /**
     * Lists all session entities.
     *
     * @security("is_granted('ROLE_ADMIN')")
     * @Route("/", name="session_index",methods={"GET"})
     * Method("GET")
     */
    public function indexAction(): Response
    {
        $em = $this->em;
        $sessions = $em->getRepository(Session::class)->findAll();

        return $this->render('session/index.html.twig', array(
            'sessions' => $sessions,
        ));
    }

    /**
     * Lists all session entities.
     *
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_PRESIDENT')")
     * @Route("/gerer", name="gerer_sessions",methods={"GET"})
     * Method("GET")
     */
    public function gererAction(Request $request): Response
    {
        $sm = $this->sm;
        $sj = $this->sj;
        $ss = $this->ss;
        
        if ($sm->gererSessions()['ok'] == false) {
            $sj->throwException(__METHOD__ . ':' . __LINE__ . " Ecran interdit " .
        " parce que : " . $sm->gererSessions()['raison']);
        }

        $em       = $this->em;
        $sessions = $em->getRepository(Session::class)->findBy([], ['idSession' => 'DESC']);
        if (count($sessions)==0) {
            $menu[] = [
            'ok' => true,
                        'name' => 'ajouter_session' ,
                        'lien' => 'Créer nouvelle session',
                        'commentaire'=> 'Créer la PREMIERE session'
                        ];
        } else {
            // On supprime de la session php la référence à la SessionCourante Gramc
            $request->getSession()->remove('SessionCourante');

            $etat_session = $ss->getSessionCourante()->getEtatSession();
            $id_session = $ss->getSessionCourante()->getIdSession();

            $menu[] = $sm->ajouterSession();
            $menu[] = $sm->modifierSession();
            $menu[] = $sm->demarrerSaisie();
            $menu[] = $sm->terminerSaisie();
            if ($this->getParameter('noedition_expertise')==false) {
                // On saute une étape si ce paramètre est à true
                $menu[] = $sm->envoyerExpertises();
            }
            $menu[] = $sm->activerSession();
        }
        return $this->render(
            'session/gerer.html.twig',
            [
                'menu'     => $menu,
                'sessions' => $sessions,
                'etat_session' => $etat_session,
                'id_session' => $id_session
            ]
        );
    }

    /////////////////////////////////////////////////////////////////////

    /**
     * Creates a new session entity.
     *
     * @Route("/ajouter", name="ajouter_session",methods={"GET","POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method({"GET", "POST"})
     */
    public function ajouterAction(Request $request): Response
    {
        $sd = $this->sd;
        $ss = $this->ss;
        $em = $this->em;
        $session = $ss->nouvelleSession();
        return $this->modifyAction($request, $session);
    }

    /**
     *
     * @Route("/{id}/modify", name="modifier_session",methods={"GET","POST"})
     * @security("is_granted('ROLE_ADMIN')")
     * Method({"GET", "POST"})
     */
    public function modifyAction(Request $request, Session $session): Response
    {
        $sd = $this->sd;
        $em = $this->em;

        // On supprime de la session php la référence à la SessionCourante Gramc
        $request->getSession()->remove('SessionCourante');
        
        $debut = $sd;
        $fin   = $sd->getNew();
        $fin->add(\DateInterval::createFromDateString('0 months'));

        if ($session->getDateDebutSession() == null) {
            $session->setDateDebutSession($debut);
        }
        if ($session->getDateFinSession() == null) {
            $session->setDateFinSession($fin);
        }

        $editForm = $this->createForm(
            'App\Form\SessionType',
            $session,
            [ 'all' => false, 'buttons' => true ]
        );
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $em->persist($session);
            $em->flush();

            return $this->redirectToRoute('gerer_sessions');
        }

        return $this->render(
            'session/modify.html.twig',
            [
            'edit_form' => $editForm->createView(),
            'session'   => $session,
            ]
        );
    }

    /**
     *
     * @Route("/terminer_saisie", name="terminer_saisie",methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method("GET")
     */
     // On vient de cliquer sur le bouton Expertises
    public function terminerSaisieAction(Request $request): Response
    {
        $ss = $this->ss;
        $em = $this->em;

        // On supprime de la session php la référence à la SessionCourante Gramc
        $request->getSession()->remove('SessionCourante');

        $session_courante = $ss->getSessionCourante();
        $workflow = $this->sw;

        if ($workflow->canExecute(Signal::DAT_FIN_DEM, $session_courante)) {
            $workflow->execute(Signal::DAT_FIN_DEM, $session_courante);
            $em->flush();
        }

        else
        {
            $msg = "Impossible de changer l'état de la session, allez voir le journal";
            $request->getSession()->getFlashbag()->add("flash erreur"," $msg");
        }
        
        // Si le paramètre noedition_expertise vaut true, on saute une étape dans le workflow !
        if ($this->getParameter('noedition_expertise')==true)
        {
            if ($workflow->canExecute(Signal::CLK_ATTR_PRS, $session_courante))
            {
                $workflow->execute(Signal::CLK_ATTR_PRS, $session_courante);
                $em->flush();
            }

            else
            {
                $msg = "Impossible de changer l'état de la session, allez voir le journal";
                $request->getSession()->getFlashbag()->add("flash erreur"," $msg");
            }
        }
                
        return $this->redirectToRoute('gerer_sessions');
    }
    
    /**
      * Avant changement d'état de la version
      *
      * @Route("/avant_changer_etat/{rtn}/{ctrl}",
      *        name="session_avant_changer_etat",
      *        defaults= {"rtn" = "X" },
      *        methods={"GET"})
      * @Security("is_granted('ROLE_ADMIN')")
      * Method("GET")
      *
      */
    public function avantActiverAction($rtn, $ctrl): Response
    {
        $ss  = $this->ss;
        $sps = $this->sps;
        $sj  = $this->sj;
        $em  = $this->em;

        $session = $ss->getSessionCourante();
        $connexions = $sps->getConnexions();
        
        return $this->render(
            'session/avant_changer_etat.html.twig',
            [
            'session'    => $session,
            'ctrl'       => $ctrl,
            'connexions' => $connexions,
            'rtn'        => $rtn,
            ]
        );
    }

    /**
     *
     * @Route("/activer", name="activer_session",methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method("GET")
     */
    public function activerAction(Request $request): Response
    {
        $em = $this->em;
        $sd = $this->sd;
        $sps = $this->sps;
        $ss = $this->ss;
        $sj = $this->sj;

        $session_courante      = $ss->getSessionCourante();
        $etat_session_courante = $session_courante->getEtatSession();

        // manu - correction juillet 2021 !
        //$sessions = $em->getRepository(Session::class)->findBy([],['idSession' => 'DESC']);
        $sessions = $em->getRepository(Session::class)->get_sessions_non_terminees();

        $ok = false;
        $mois = $sd->format('m');

        $workflow = $this->sw;

        // On active une session A = trois signaux envoyés sur trois sessions différentes !
        if ($mois == 1 ||  $mois == 12)
        {
            if ($workflow->canExecute(Signal::CLK_SESS_DEB, $session_courante) && $etat_session_courante == Etat::EN_ATTENTE) {
                // On termine les deux sessions A et B de l'année précédente
                foreach ($sessions as $session) {
                    // On ne termine pas la session qui va démarrer !
                    if ($session->getIdSession() == $session_courante->getIdSession()) {
                        continue;
                    }

                    if ($workflow->canExecute(Signal::CLK_SESS_FIN, $session)) {
                        $err = $workflow->execute(Signal::CLK_SESS_FIN, $session);
                    }
                }
                $ok = $workflow->execute(Signal::CLK_SESS_DEB, $session_courante);
                $em->flush();
            }
        }

        // On active une session B = deux signaux envoyés sur deux sessions différentes
        elseif ($mois == 6 ||  $mois == 7)
        {
            // manu - corrigé le 20 juillet 2021
            //if( $workflow->canExecute(Signal::CLK_SESS_DEB , $session_courante)  && $etat_session_courante == Etat::EN_ATTENTE )
            //{
            //    $ok = $workflow->execute(Signal::CLK_SESS_DEB , $session_courante );
            //    $em->flush();
            //}
            // il faut envoyer le signal aux DEUX sessions A et B
            // Le signal se propage aux versions sous-jacentes
            // Les versions de A passeront de NOUVELLE_VERSION_DEMANDEE à TERMINE
            // Les versions de B passeront de EN_ATTENTE à ACTIF
            foreach ($sessions as $session) {
                if ($workflow->canExecute(Signal::CLK_SESS_DEB, $session)) {
                    $ok = $workflow->execute(Signal::CLK_SESS_DEB, $session);
                    $em->flush();
                }
            }
        }

        else
        {
            $msg = "Une session ne peut être activée qu'en Décembre, Janvier, Juin ou Juillet";
            $request->getSession()->getFlashbag()->add("flash erreur"," $msg");
            $sj->errorMessage(__METHOD__ . ':' . __LINE__ . " $msg");
            return $this->redirectToRoute('gerer_sessions');
        }

        if ($ok==false) {
            $msg = "Impossible d'activer la session, allez voir le journal !";
            $request->getSession()->getFlashbag()->add("flash erreur"," $msg");
        }
        return $this->redirectToRoute('gerer_sessions');
    }

    /**
     *
     * @Route("/envoyer_expertises", name="envoyer_expertises",methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN') or is_granted('ROLE_PRESIDENT')")
     * Method("GET")
     */
    public function envoyerExpertisesAction(Request $request): Response
    {
        $ss = $this->ss;
        $sm = $this->sm;
        $sj = $this->sj;
        $em = $this->em;

        // ACL
        if ($sm->envoyerExpertises()['ok'] == false) {
            $sj->throwException(__METHOD__ . ':' . __LINE__ . " impossible pour l'instant parce que : " .
            $sm->envoyerExpertises()['raison']);
        }

        $session_courante = $ss->getSessionCourante();
        $workflow = $this->sw;

        // On supprime de la session php la référence à la SessionCourante Gramc
        $request->getSession()->remove('SessionCourante');

        if ($workflow->canExecute(Signal::CLK_ATTR_PRS, $session_courante))
        {
            $workflow->execute(Signal::CLK_ATTR_PRS, $session_courante);
            $em->flush();
        }

        else
        {
            $msg = "Impossible de passer la session en expertise, allez voir le journal !";
            $request->getSession()->getFlashbag()->add("flash erreur"," $msg");
        }

        return $this->redirectToRoute('gerer_sessions');
    }

    /**
     *
     * @Route("/demarrer_saisie", name="demarrer_saisie",methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method("GET")
     */
     // On vient de cliquer sur le bouton Demandes
    public function demarrerSaisieAction(Request $request): Response
    {
        $ss = $this->ss;
        $sps = $this->sps;
        $em = $this->em;

        // On supprime de la session php la référence à la SessionCourante Gramc
        $request->getSession()->remove('SessionCourante');

        $session_courante       = $ss->getSessionCourante();

        $workflow = $this->sw;

        // Supprimer toutes les sessions php, donc déloger les utilisateurs éventuellement connectés
        $sps->clearPhpSessions();
        
        if ($workflow->canExecute(Signal::DAT_DEB_DEM, $session_courante))
        {
            $workflow->execute(Signal::DAT_DEB_DEM, $session_courante);
            $em->flush();
        }

        else
        {
            $msg = "Impossible de démarrer la saisie, allez voir le journal !";
            $request->getSession()->getFlashbag()->add("flash erreur"," $msg");
        }
    }

    /**
     * Creates a new session entity.
     *
     * @Route("/new", name="session_new",methods={"GET","POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method({"GET", "POST"})
     */
    public function newAction(Request $request): Response
    {
        $session = new Session();
        $form = $this->createForm('App\Form\SessionType', $session, ['all' => true ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->em;
            $em->persist($session);
            $em->flush($session);

            return $this->redirectToRoute('session_show', array('id' => $session->getId()));
        }

        return $this->render('session/new.html.twig', array(
            'session' => $session,
            'form' => $form->createView(),
        ));
    }

    /**
     * Finds and displays a session entity.
     *
     * @Security("is_granted('ROLE_ADMIN')")
     * @Route("/{id}/show", name="session_show",methods={"GET"})
     * Method("GET")
     */
    public function showAction(Session $session): Response
    {
        $deleteForm = $this->createDeleteForm($session);

        return $this->render('session/show.html.twig', array(
            'session' => $session,
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Meme chose que show, mais présenté "à la gramc"
     *
     * @Security("is_granted('ROLE_ADMIN')")
     * @Route("/{id}/consulter", name="consulter_session",methods={"GET"})
     * Method("GET")
     */
    public function consulterAction(Session $session): Response
    {
        $sm = $this->sm;
        $menu = [ $sm->gererSessions() ];

        return $this->render('session/consulter.html.twig', array(
            'session' => $session,
            'menu' => $menu
        ));
    }

    /**
     * Displays a form to edit an existing session entity.
     *
     * @Security("is_granted('ROLE_ADMIN')")
     * @Route("/{id}/edit", name="session_edit",methods={"GET","POST"})
     * Method({"GET", "POST"})
     */
    public function editAction(Request $request, Session $session): Response
    {
        $deleteForm = $this->createDeleteForm($session);
        $editForm = $this->createForm('App\Form\SessionType', $session, [ 'all' => true ]);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->em->flush();

            return $this->redirectToRoute('session_edit', array('id' => $session->getId()));
        }

        return $this->render('session/edit.html.twig', array(
            'session' => $session,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     *
     * Entrée du commentaire de session par le président
     * 
     * @Route("/commentaires", name="session_commentaires",methods={"GET","POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method({"GET", "POST"})
     */
    public function commentairesAction(Request $request): Response
    {
        $sm = $this->sm;
        $ss = $this->ss;

        // On supprime de la session php la référence à la SessionCourante Gramc
        $request->getSession()->remove('SessionCourante');

        $session_courante      = $ss->getSessionCourante();
        $etat_session_courante = $session_courante->getEtatSession();
        $workflow              = $this->sw;

        $editForm = $this->createForm('App\Form\SessionType', $session_courante, [ 'commentaire' => true ]);
        $editForm->handleRequest($request);

        $menu[] = $sm->envoyerExpertises();
        $menu[0]['priorite'] = 1;   // On repasse en haute priorité pour voir toujours ce bouton en bas de l'écran !

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->em->flush();
        }

        return $this->render(
            'session/commentaires.html.twig',
            [
            'session'   => $session_courante,
            'edit_form' => $editForm->createView(),
            'menu'      => $menu
            ]
        );
    }

    /**
     * Creates a form to delete a session entity.
     *
     * @param Session $session The session entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Session $session):FormInterface
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('session_delete', array('id' => $session->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }

    /**
     *
     * @Route("/bilan", name="bilan_session",methods={"GET","POST"})
     * @Security("is_granted('ROLE_OBS')")
     * Method({"GET","POST"})
     */
    public function bilanAction(Request $request): Response
    {
        $em      = $this->em;
        $ss      = $this->ss;
        $sp      = $this->sp;
        $sv      = $this->sv;
        $session = $ss->getSessionCourante();
        $data    = $ss->selectSession($this->createFormBuilder(['session'=>$session]), $request); // formulaire
        $session = $data['session']!=null ? $data['session'] : $session;
        $form    = $data['form']->createView();

        $versions = $em->getRepository(Version::class)->findBy(['session' => $session ]);
        $form_labels = [];
        $form_total = [];
        if (count($versions)>0) {
            $v0 = $versions[0];
            $formation = $sv -> buildFormations($v0);
            foreach ($formation as $f) {
                // cf. buildFormations ALL_EMPTY
                if (is_bool($f)) continue;
                $fl = [];
                $fl['acro'] = $f['acro'];
                $fl['nom']  = $f['nom'];
                $form_labels[] = $fl;
                $form_total[$f['acro']] = 0;
            }
        }

        foreach ($versions as $v) {
            $formation = $sv -> buildFormations($v);
            foreach ($formation as $f) {
                if ($f['acro']=='ALL_EMPTY') continue;
                $form_total[$f['acro']] += intval($f['rep']);
            }
        }
        return $this->render(
            'session/bilan.html.twig',
            [
            'form'      => $form,
            'idSession' => $session->getIdSession(),
            'versions'  => $versions,
            'form_labels' => $form_labels,
            'form_total' => $form_total
        ]
        );
    }

    /**
     *
     * @Route("/bilan_annuel", name="bilan_annuel",methods={"GET","POST"})
     * @Security("is_granted('ROLE_OBS')")
     * Method({"GET","POST"})
     */
    public function bilanAnnuelAction(Request $request): Response
    {
        $ss   = $this->ss;
        $sd   = $this->sd;
        $annee = $sd->showYear();
        $mois = $sd->showMonth();

        if ($mois === "12")
        {
            $annee = strval(intval($annee) + 1);
        }
        
        $data = $ss->selectAnnee($request,$annee);
        // TODO - Utiliser cette methode pour recuperer les paramètres:
        //        https://ourcodeworld.com/articles/read/1041/how-to-retrieve-specific-and-all-yaml-parameters-from-services-yaml-in-symfony-4
        $avec_commentaires = $this->getParameter('commentaires_experts_d');
        return $this->render(
            'session/bilanannuel.html.twig',
            [
            'form' => $data['form']->createView(),
            'annee'=> $data['annee'],
            'avec_commentaires' => $avec_commentaires
            //'versions'  =>  $em->getRepository(Version::class)->findBy( ['session' => $data['session'] ] )
            ]
        );
    }

    /**
     *
     *
     * @Route("/{id}/questionnaire_csv", name="questionnaire_csv",methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method("GET")
     */
    public function questionnaireCsvAction(Request $request, Session $session): response
    {
        $sp = $this->sp;
        $em = $this->em;

        $entetes =  [
            'Projet',
            'Demande',
            'Attribution',
            'Consommation',
            'Titre',
            'Thématique',
            'Responsable scientifique',
            'Laboratoire',
            'Langages utilisés',
            'gpu',
            'Nom du code',
            'Licence',
            'Heures/job',
            'Ram/cœur',
            'Ram partagée',
            'Efficacité parallèle',
            'Stockage temporaire',
            'Post-traitement',
            'Meta données',
            'Nombre de datasets',
            'Taille des datasets'
        ];
        $sortie = join("\t", $entetes) . "\n";

        $versions = $em->getRepository(Version::class)->findBy(['session' => $session ]);

        foreach ($versions as $version) {
            $langage = "";
            if ($version->getCodeCpp()== true) {
                $langage .= " C++ ";
            }
            if ($version->getCodeC()== true) {
                $langage .= " C ";
            }
            if ($version->getCodeFor()== true) {
                $langage .= " Fortran ";
            }
            $langage .=  Functions::string_conversion($version->getCodeLangage());
            $ligne = [
                ($version->getIdVersion() != null) ? $version->getIdVersion() : 'null',
                $version->getDemHeuresTotal(),
                $version->getAttrHeuresTotal(),
                $sp->getConsoCalculVersion($version),
                Functions::string_conversion($version->getPrjTitre()),
                Functions::string_conversion($version->getPrjThematique()),
                $version->getResponsable(),
                $version->getLabo(),
                trim($langage),
                Functions::string_conversion($version->getGpu()),
                Functions::string_conversion($version->getCodeNom()),
                Functions::string_conversion($version->getCodeLicence()),
                Functions::string_conversion($version->getCodeHeuresPJob()),
                Functions::string_conversion($version->getCodeRamPCoeur()),
                Functions::string_conversion($version->getCodeRamPart()),
                Functions::string_conversion($version->getCodeEffParal()),
                Functions::string_conversion($version->getCodeVolDonnTmp()),
                Functions::string_conversion($version->getDemPostTrait()),
                Functions::string_conversion($version->getDataMetaDataFormat()),
                Functions::string_conversion($version->getDataNombreDatasets()),
                Functions::string_conversion($version->getDataTailleDatasets()),

            ];
            $sortie     .=   join("\t", $ligne) . "\n";
        }

        return Functions::csv($sortie, 'bilan_reponses__session_'.$session->getIdSession().'.csv');
    }

    //////////////////////////////////////////////////////////////////////
    //
    //
    //
    //////////////////////////////////////////////////////////////////////

    /**
     *
     * @Route("/{annee}/bilan_annuel_csv", name="bilan_annuel_csv",methods={"GET"})
     * @Security("is_granted('ROLE_OBS')")
     * Method("GET")
     *
     */
    public function bilanAnnuelCsvAction(Request $request, $annee): Response
    {
        $sd      = $this->sd;
        $sp      = $this->sp;
        $em = $this->em;

        $entetes = ['Projet','Thématique','Titre','Responsable','Quota'];

        // Les mois pour les consos
        array_push($entetes, 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre');

        $entetes[] = "total";
        $entetes[] = "Total(%/quota)";

        $sortie     =   join("\t", $entetes) . "\n";

        // Sommes-nous dans l'année courante ?
        $annee_courante_flg = ($sd->showYear()==$annee);

        //////////////////////////////

        $conso_flds = ['m01','m02','m03','m04','m05','m06','m07','m08','m09','m10','m11','m12'];

        // 2019 -> 19A et 19B
        $session_id_A = substr($annee, 2, 2) . 'A';
        $session_id_B = substr($annee, 2, 2) . 'B';
        $session_A = $em->getRepository(Session::class)->findOneBy(['idSession' => $session_id_A ]);
        $session_B = $em->getRepository(Session::class)->findOneBy(['idSession' => $session_id_B ]);

        $versions_A= $em->getRepository(Version::class)->findBy(['session' => $session_A ]);
        $versions_B= $em->getRepository(Version::class)->findBy(['session' => $session_B ]);

        // On stoque dans le tableau $id_projets une paire: [$projet, $version] où $version est la version A, ou la B si elle existe
        $id_projets= [];
        foreach (array_merge($versions_A, $versions_B) as $v) {
            $projet = $v -> getProjet();
            $id_projet = $projet->getIdProjet();
            $id_projets[$id_projet] = [ $projet, $v ];
        }

        // Les totaux
        $tq  = 0;        // Le total des quotas
        $tm  = [0,0,0,0,0,0,0,0,0,0,0,0];        // La conso totale par mois
        $tttl= 0;        // Le total de la conso

        // Calcul du csv, ligne par ligne
        foreach ($id_projets as $id_projet => $paire) {
            $line   = [];
            $line[] = $id_projet;
            $p      = $paire[0];
            $v      = $paire[1];
            $line[] = $p->getThematique();
            $line[] = $p->getTitre();
            $r      = $v->getResponsable();
            $line[] = $r->getPrenom() . ' ' . $r->getNom();
            //$quota  = $v->getQuota();
            //$line[] = $quota;
            $consoRessource = $this->sp->getConsoRessource($p, 'cpu', $annee);
            $quota          = $consoRessource[1];
            $line[] = $quota;
            for ($m=0;$m<12;$m++) {
                $c = $sp->getConsoMois($p, $annee, $m);
                $line[] = $c;
                $tm[$m] += $c;
            }

            // Si on est dans l'année courante on ne fait pas le total
            $ttl = ($annee_courante_flg) ? 'N/A' : $sp->getConsoCalcul($p, $annee);
            if ($quota>0) {
                $ttlp   = ($annee_courante_flg) ? 'N/A' : 100.0 * $ttl / $quota;
            } else {
                $ttlp = 0;
            }

            $line[] = $ttl;
            $line[] = ($ttlp=='N/A') ? $ttlp : intval($ttlp);

            $sortie .= join("\t", $line) . "\n";

            // Mise à jour des totaux
            $tq   += $quota;
            if ($ttl==='N/A') {
                $tttl = 'N/A';
                ;
            } else {
                $tttl += $ttl;
            }
        }

        // Dernière ligne
        $line   = [];
        $line[] = 'TOTAL';
        $line[] = '';
        $line[] = '';
        $line[] = '';
        $line[] = $tq;
        for ($m=0; $m<12; $m++) {
            $line[] = $tm[$m];
        }
        $line[] = $tttl;

        if ($tq > 0) {
            $line[] = ($annee_courante_flg) ? 'N/A' : intval(100.0 * $tttl / $tq);
        } else {
            $line[] = 'N/A';
        }
        $sortie .= join("\t", $line) . "\n";

        return Functions::csv($sortie, 'bilan_annuel_'.$annee.'.csv');
    }

    /**
     *
     * @Route("/{annee}/bilan_annuel_labo_csv", name="bilan_annuel_labo_csv",methods={"GET"})
     * @Security("is_granted('ROLE_OBS')")
     * Method("GET")
     *
     */
    public function bilanLaboCsvAction(Request $request, $annee): Response
    {
        $entetes = ['Laboratoire','Nombre de projets','Heures demandées','Heures attribuées','Heure consommées','projets'];
        $sortie  = join("\t", $entetes) . "\n";

        $sp            = $this->sp;
        $stats         = $sp->projetsParCritere($annee, 'getAcroLaboratoire');
        $acros         = $stats[0];
        $num_projets   = $stats[1];
        $liste_projets = $stats[2];
        $dem_heures    = $stats[3];
        $attr_heures   = $stats[4];
        $conso         = $stats[5];

        // Calcul du csv
        foreach ($acros as $k) {
            $ligne   = [];
            $ligne[] = $k;
            $ligne[] = $num_projets[$k];
            $ligne[] = $dem_heures[$k];
            $ligne[] = $attr_heures[$k];
            $ligne[] = $conso[$k];
            $ligne[] = implode(',', $liste_projets[$k]);
            $sortie .= join("\t", $ligne) . "\n";
        }

        return Functions::csv($sortie, 'bilan_annuel_par_labo'.$annee.'.csv');
    }

        /**
     *
     * @Route("/{annee}/bilan_annuel_thema_csv", name="bilan_annuel_thema_csv",methods={"GET"})
     * @Security("is_granted('ROLE_OBS')")
     * Method("GET")
     *
     */
    public function bilanThemaCsvAction(Request $request, $annee): Response
    {
        $entetes = ['Thématique','Nombre de projets','Heures demandées','Heures attribuées','Heure consommées','projets'];
        $sortie  = join("\t", $entetes) . "\n";

        $sp            = $this->sp;
        $stats         = $sp->projetsParCritere($annee, 'getAcroThematique');
        $acros         = $stats[0];
        $num_projets   = $stats[1];
        $liste_projets = $stats[2];
        $dem_heures    = $stats[3];
        $attr_heures   = $stats[4];
        $conso         = $stats[5];

        // Calcul du csv
        foreach ($acros as $k) {
            $ligne   = [];
            $ligne[] = $k;
            $ligne[] = $num_projets[$k];
            $ligne[] = $dem_heures[$k];
            $ligne[] = $attr_heures[$k];
            $ligne[] = $conso[$k];
            $ligne[] = implode(',', $liste_projets[$k]);
            $sortie .= join("\t", $ligne) . "\n";
        }

        return Functions::csv($sortie, 'bilan_annuel_par_thematique'.$annee.'.csv');
    }

    /**
     *
     * @Route("/{annee}/bilan_annuel_users_csv", name="bilan_annuel_users_csv",methods={"GET"})
     * @Security("is_granted('ROLE_OBS')")
     * Method("GET")
     *
     */
    public function bilanUserCsvAction(Request $request, $annee): Response
    {
        $entetes = ['Nom','Prénom','Login','mail','Statut','Heures cpu','Heures GPU'];
        $sortie  = join("\t", $entetes) . "\n";
        $em = $this->em;
        $sp = $this->sp;
        
        // Les collaborateurs-versions de cette année
        $cvs = $em->getRepository(CollaborateurVersion::class)->findAllUsers($annee);
        
        // On les copie dans un tableau $users, indexé par le loginname
        $users = [];
        foreach ($cvs as $cv) {
            // On peut avoir deux fois le même CollaborateurVersion (sessions A et B)
            $loginname = $cv->getLoginname();
            if (isset ($users[$loginname])) {
                continue;
            }
            
            $u = [];
            $u['indiv'] = $cv->getCollaborateur();
            $u['hcpu'] = $sp->getConsoRessource($cv, 'cpu', $annee)[0];
            $u['hgpu'] = $sp->getConsoRessource($cv, 'gpu', $annee)[0];
            $users[$loginname] = $u;
        }

        // Calcul de csv
        foreach ($users as $loginname => $u) {
            $ligne = [];
            $ligne[] = $u['indiv']->getNom();
            $ligne[] = $u['indiv']->getPrenom();
            $ligne[] = $loginname;
            $ligne[] = $u['indiv']->getMail();
            $ligne[] = $u['indiv']->getStatut();
            $ligne[] = $u['hcpu'];
            $ligne[] = $u['hgpu'];
            $sortie .= join("\t", $ligne) . "\n";
        }
        return Functions::csv($sortie, 'bilan_annuel_par_utilisateur'.$annee.'.csv');
    }

    /**
     *
     * Génère le bilan de session au format CSV
     *
     * @Security("is_granted('ROLE_OBS')")
     * @Route("/{id}/bilan_csv", name="bilan_session_csv",methods={"GET"})
     * Method("GET")
     */
    public function bilanCsvAction(Request $request, Session $session): Response
    {
        $em                 = $this->em;
        $ss                 = $this->ss;
        $grdt               = $this->sd;
        $sp                 = $this->sp;
        $ressources_conso_group = $this->getParameter('ressources_conso_group');
        $type_session       = $session->getLibelleTypeSession(); // A ou B
        $id_session         = $session->getIdSession();
        $annee_cour         = $session->getAnneeSession();
        $session_courante_A = $em->getRepository(Session::class)->findOneBy(['idSession' => $annee_cour .'A']);
        if ($session_courante_A == null) {
            return new Response('Session courante nulle !');
        }

        if ($type_session == 'A') {
            $bilan_session = new BilanSessionA($ressources_conso_group, $grdt, $session, $sp, $ss, $em);
        } else {
            $bilan_session = new BilanSessionB($ressources_conso_group, $grdt, $session, $sp, $ss, $em);
        }

        $csv = $bilan_session->getCsv();

        return Functions::csv($csv[0], $csv[1]);
    }

    // type session A ou B
    public static function typeSession(Session $session): string
    {
        return substr($session->getIdSession(), -1);
    }

    // années
    public static function codeSession(Session $session): int
    {
        return intval(substr($session->getIdSession(), 0, -1));
    }
}
