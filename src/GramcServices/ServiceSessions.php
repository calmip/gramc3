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

use App\Entity\Session;
use App\Utils\Functions;
use App\GramcServices\Etat;
use App\GramcServices\GramcDate;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormFactoryInterface;


class ServiceSessions
{
    private $recup_attrib_seuil = null;
    private $recup_conso_seuil = null;
    private $recup_attrib_quant =null;
    private $sessions_non_term = null;

    public function __construct(
        $recup_attrib_seuil,
        $recup_conso_seuil,
        $recup_attrib_quant,
        private GramcDate $grdt,
        private FormFactoryInterface $ff,
        private EntityManagerInterface $em
    ) {
        $this->recup_attrib_seuil = intval($recup_attrib_seuil);
        $this->recup_conso_seuil  = intval($recup_conso_seuil);
        $this->recup_attrib_quant = intval($recup_attrib_quant);
        $this->grdt               = $grdt;
        $this->ff                 = $ff;
        $this->em                 = $em;
        $this->sessions_non_term  = null;
    }

    /*******
     * initialise le "cache" des sessions non terminées
     *******/
    private function initSessionsNonTerm(): void
    {
        $this->sessions_non_term = $this->em->getRepository(Session::class)->get_sessions_non_terminees();
    }

//    SUPPRIME CAR NON APPELE
//    /*******
//     * vide le "cache" des sessnios non terminées - utile lorsqu'on crée une session ou change l'état des sessions
//     *******/
//    public function clearCache()
//    {
//        $this->sessions_non_term = null;
//    }

    /***********
    * Renvoie la session courante, c'est-à-dire la PLUS RECENTE session NON TERMINEE
    * Initialise le "cache" au besoin
    * TODO - ne marche pas s'il n'y a pas de session non terminée (lors de l'install)
    ************************************************************/
    public function getSessionCourante(): ?Session
    {
        if ($this->sessions_non_term==null) {
            $this->initSessionsNonTerm();
        }
        if ($this->sessions_non_term != null) {
            return $this->sessions_non_term[0];
        } else {
            return null;
        }
    }

    /************
    * Formulaire permettant de choisir une session
    *
    * $formb   = Un formBuilder (retour de createView)
    * $request = La requête
    *
    * Retourne un tableau contenant:
    *     Le formulaire
    *     La session choisie
    *
    * Utilisation depuis un contrôleur:
    *             $data = $ss->selectSession($this->createFormBuilder(['session' => $une_session"]),$request);
    *
    * todo - Reprendre cette fonction sur le modèle de selectAnnee
    *******************/
    public function selectSession(FormBuilder $formb, Request $request): array
    {
        $form    = $formb
                    ->add(
                        'session',
                        ChoiceType::class,
                        [
                        'multiple'     => false,
                        'required'     =>  true,
                        'label'        => '',
                        'choices'      => $this->em->getRepository(Session::class)->findBy([], ['idSession' => 'DESC']),
                        'choice_label' => function ($session) {
                            return $session->__toString();
                        },
                        ]
                    )
                    ->add('submit', SubmitType::class, ['label' => 'Choisir'])
                    ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $session = $form->getData()['session'];
        } else {
            $session = null;
        }
        return ['form' => $form, 'session' => $session ];
    }

    /************
     * Formulaire permettant de choisir une année
     *
     * $request = La requête
     * $annee   = L'année, si null on prend l'année courante
     *
     * Retourne un tableau contenant:
     *     Le formulaire
     *     L'année choisie
     *
     * Utilisation depuis un contrôleur:
     *             $data = $ss->selectAnnee($request);
     *
     * TODO = Ne pas utiliser Functions::createFormBuilder
     *        Donner un nom au formulaire
     *
     *******************/

    public function selectAnnee(Request $request, $annee = null): array
    {
        $annee_max = $this->grdt->showYear();
        if ($annee == null) {
            $annee=$annee_max;
        }

        $choices = array_reverse(Functions::choicesYear(new \DateTime('2000-01-01'), new \DateTime($annee_max.'-01-01'), 0), true);
        $form    = Functions::createFormBuilder($this->ff, ['annee' => $annee ])
                    ->add(
                        'annee',
                        ChoiceType::class,
                        [
                            'multiple' => false,
                            'required' =>  true,
                            'label'    => '',
                            'choices'  => $choices,
                        ]
                    )
                    ->add('submit', SubmitType::class, ['label' => 'Choisir'])
                    ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $annee = $form->getData()['annee'];
        }

        return ['form'  =>  $form, 'annee'    => $annee ];
    }

    /************
     * Formulaire permettant de choisir un label de session (A ou B dans l'année choisie par ailleurs)
     *
     * $request = La requête
     * $annee   = $sess_lbl = La valeur initiale
     *
     * Retourne:
     *     Le formulaire
     *     Le label choisi: 'A', 'B', 'AB' (les deux)
     *
     * Utilisation depuis un contrôleur:
     *              $data = $ss->selectSessLbl($request, $sess_lbl);
     *              $sess_lbl= $datas['sess_lbl'];
     * 
     *******************/
    public function selectSessLbl(Request $request, string $sess_lbl): array
    {
        if ($sess_lbl == '')
        {
            $sess_lbl = 'AB';
        }
        
        $choices = [ 'A' => 'A', 'B' => 'B', 'les deux' => 'AB'];
        $form    = $this->ff->createNamedBuilder('sess_lbl', FormType::class, ['sess_lbl' => $sess_lbl])
                    ->add(
                        'sess_lbl',
                        ChoiceType::class,
                        [
                            'multiple' => false,
                            'required' => true,
                            'expanded' => true,
                            'choices'  => $choices,
                            'label'    => 'Session prise en compte:'
                        ]
                    )
                    ->add('submit', SubmitType::class, ['label' => 'Choisir'])
                    ->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $sess_lbl = $form->getData()['sess_lbl'];
        }

        return ['form'  =>  $form, 'sess_lbl'    => $sess_lbl ];
    }
    
    /**
     * Retourne toutes les sessions d'une année particulière
     *
     * Param: $annee (2018, 2019, etc)
     * Return: [ $sessionA,$sessionB ] ou [ $sessionA] ou []
     *
     **/
    public function sessionsParAnnee($annee): array
    {
        $annee -= 2000;
        $sessions = [];

        $s = $this->em->getRepository(Session::class)->findOneBy(['idSession' => $annee.'A']);
        if ($s!=null) {
            $sessions[]=$s;
        }

        $s = $this->em->getRepository(Session::class)->findOneBy(['idSession' => $annee.'B']);
        if ($s!=null) {
            $sessions[]=$s;
        }
        return $sessions;
    }



    /********************
    * calc_recup_heures_printemps
    * Si le projet a eu beaucoup d'heures attribuées mais n'en a consommé que peu,
    * on récupère une partie de son attribution
    * cf. la règle 4
    *      param $conso  = Consommation
    *      param $attrib = Attribution
    *      return $recup = Heures pouvant être récupérées
    *********************/
    public function calc_recup_heures_printemps($conso, $attrib): int
    {
        $recup_heures = 0;
        if ($attrib <= 0) {
            return 0;
        }

        if (! $this->recup_attrib_seuil
          ||! $this->recup_conso_seuil
          ||! $this->recup_attrib_quant
          ) {
            return 0;
        }

        if ($attrib >= $this->recup_attrib_seuil) {
            $conso_rel = (100.0 * $conso) / $attrib;
            if ($conso_rel < $this->recup_conso_seuil) {
                $obj = $attrib * $this->recup_attrib_quant / 100;
                $recup_heures = $obj - $conso;
            }
        }
        return $recup_heures;
    }

    /********************************
    * calc_recup_heures_automne
    * Si le projet a consommé moins d'heures en été que demandé par le comité,
    * on récupère ce qui n'a pas été consommé
    *
    * param $conso_ete  = La consommation pour Juillet et Août
    * param $attrib_ete = L'attribution pour l'été
    * return $recup     = Heures pouvant être récupérées
    **********************************/
    public function calc_recup_heures_automne($conso_ete, $attrib_ete): int
    {
        $recup_heures = 0;
        if ($attrib_ete <= 0) {
            return 0;
        }

        if ($conso_ete < $attrib_ete) {
            $recup_heures = $attrib_ete - $conso_ete;
            $recup_heures = 1000 * (intval($recup_heures / 1000));
        }
        return $recup_heures;
    }

    /**********************************
     * Crée une nouvelle session... si nécessaire
     *
     * NB - N'appelle pas persist, c'est l'appelant qui devra le faire,
     *      si la création est confirmée
     *
     * return $session
     **********************************/
    public function nouvelleSession(): Session
    {
        $grdt = $this->grdt;
        $em   = $this->em;

        $sess_info = $this->nextSessionInfo();
        $sess_id   = $sess_info['id'];
        $sess_type = $sess_info['type'];
        $session   = $em->getRepository(Session::class)->find($sess_id);

        if ($session == null) {
            $hparannee = 0;
            $sess_act = $this->getSessionCourante();
            if ($sess_act != null) {
                $hparannee=$sess_act->getHParAnnee();
            };
            $session = new Session();
            $debut = $grdt;
            $fin   = $grdt->getNew();
            $fin->add(\DateInterval::createFromDateString('1 months'));

            $session->setDateDebutSession($debut)
                ->setDateFinSession($fin)
                ->setIdSession($sess_id)
                ->setTypeSession($sess_type)
                ->setHParAnnee($hparannee)
                ->setEtatSession(Etat::CREE_ATTENTE);
        }
        return $session;
    }

    private function nextSessionInfo(): array
    {
        $grdt = $this->grdt;
        $annee = $grdt->format('y');   // 15 pour 2015
        $mois  = $grdt->format('m');   // 5 pour mai

        if ($mois<7) {
            $id_session = $annee.'B';
            $type       = 1;
        } else {
            $id_session = $annee+1 .'A';
            $type       = 0;
        }
        return [ 'id' => $id_session, 'type' => $type ];
    }
}
