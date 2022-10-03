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

namespace App\Controller;

use App\Entity\Projet;
use App\Entity\Compta;
use App\Utils\Functions;

use App\GramcServices\ServiceMenus;
use App\GramcServices\ServiceJournal;
use App\GramcServices\ServiceVersions;
use App\GramcServices\GramcDate;
use App\GramcServices\GramcGraf\Calcul;
use App\GramcServices\DonneesFacturation;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\HttpFoundation\RedirectResponse;

use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\DateType;

use Doctrine\ORM\EntityManagerInterface;


use Knp\Snappy\Pdf;

/**
 * ProjetFctController rassemble les controleurs dédiés au bouton "Euro" (données de facturation)
 *
 * @Security("is_granted('ROLE_OBS')")
 * @Route("projet")
 */
 // Tous ces controleurs sont exécutés au moins par OBS, certains par ADMIN seulement
 // et d'autres par DEMANDEUR

class ProjetDfctController extends AbstractController
{
    public function __construct(
        private ServiceJournal $sj,
        private ServiceMenus $sm,
        private Calcul $gcl,
        private GramcDate $sd,
        private ServiceVersions $sv,
        private DonneesFacturation $dfct,
        private Pdf $pdf,
        private EntityManagerInterface $em
    ) {}

    /**
     * Appelé quand on clique sur le bouton € dans la page projets par année
     * Affiche les données de facturation actuelles
     *
     * @Route("/{id}/dfctliste/{annee}", name="dfct_liste", methods={"GET","POST"})
     * Method({"GET","POST"})
     */

    public function dfctlisteAction(Projet $projet, $annee, Request $request): Response
    {
        $dessin_heures = $this -> gcl;
        $sm     = $this->sm;
        $sd     = $this->sd;
        $em     = $this->em;
        $dfct   = $this->dfct;
        $emises = $dfct->getNbEmises($projet, $annee);
        $version= $dfct->getVersion($projet, $annee);
        $menu   = [];
        $menu[] = $sm->projetsAnnee();

        $jourdelan = new \DateTime($annee.'-01-01');
        $ssylvestre= new \DateTime($annee.'-12-31');

        $debut_periode = $version -> getFctStamp();
        if ($debut_periode==null) {
            $d = $annee . '-01-01';
            $debut_periode = new \DateTime($d);
        }

        $fin_periode = $sd;
        $fin_periode->sub(new \DateInterval('P1D'));

        $form   = $this->createFormBuilder()
            ->add(
                'fctstamp',
                DateType::class,
                [
                    'data'   => $fin_periode,
                    'label'  => 'Fin de pếriode:',
                    'widget' => 'single_text'
                    ]
            )
            ->add('submit', SubmitType::class, ['label' => 'OK'])
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $fin_periode = $form->getData()['fctstamp'];
        }

        $conso_periode = $dfct->getConsoPeriode($projet, $debut_periode, $fin_periode);
        //if ($conso_periode == -1) $conso_periode = 'N/A';

        $id_projet     = $projet->getIdProjet();
        $compta_repo   = $em -> getRepository(Compta::class);
        $db_conso      = $compta_repo->conso($id_projet, $annee);

        // conso  sur la période
        $struct_data   = $dessin_heures->createStructuredData($debut_periode, $fin_periode, $db_conso);
        if (count($struct_data) > 10) {
            $dessin_heures->resetConso($struct_data);
            $image_conso_p = $dessin_heures->createImage($struct_data)[0];
        } else {
            $image_conso_p = null;
        }

        // conso sur toute l'année
        $struct_data   = $dessin_heures->createStructuredData($jourdelan, $ssylvestre, $db_conso);
        if (count($struct_data) > 10) {
            $dessin_heures->resetConso($struct_data);
            $image_conso_a = $dessin_heures->createImage($struct_data)[0];
        } else {
            $image_conso_a = null;
        }

        return $this->render(
            'projetfct/dfctliste.html.twig',
            ['projet'  => $projet,
                             'version' => $version,
                             'annee'   => $annee,
                             'emises'  => $emises,
                             'form'    => $form->createView(),
                             'debut'   => $debut_periode,
                             'fin'     => $fin_periode,
                             'conso'   => $conso_periode,
                             'dessin_periode' => $image_conso_p,
                             'dessin_annee'   => $image_conso_a,
                             'menu'    => $menu
                             ]
        );
    }

    /**
     * Téléchargement d'un pdf avec les données de facturation déjà émises
     *
     * @Route("/{id}/dfctdl/{annee}/{nb}", name="dfct_dl_projet", methods={"GET","POST"})
     * Method({"GET","POST"})
     */

    public function downloaddfctAction(Projet $projet, $annee, $nb, Request $request): Response
    {
        $dfct= $this->dfct;
        $sj  = $this->sj;

        $filename = $dfct->getPath($projet, $annee, $nb);
        if ($filename == '') {
            $sj->errorMessage(__METHOD__ . ":" . __LINE__ . " fichier de données de facturation $nb, projet $projet, année $annee n'existe pas");
            return Functions::pdf(null);
        } else {
            $dwnfn = "Données_de_facturation_".$projet."_".$annee."_".$nb.".pdf";
            return Functions::pdf($filename, $dwnfn);
        }
    }

    /**
     * Génération du pdf contenant les données de facturation
     *
     * @Route("/{id}/dfctgen/{fin_periode}", name="dfct_gen", methods={"GET","POST"})
     * Method({"GET","POST"})
     */
    public function dfct_genAction(Projet $projet, \DateTime $fin_periode, Request $request): Response
    {
        $em     = $this->em;
        $annee  = $fin_periode->format('Y');
        $dfct   = $this->dfct;
        $emises = $dfct->getNbEmises($projet, $annee);
        $numero = count($emises) + 1;
        $version= $dfct->getVersion($projet, $annee);

        $jourdelan = new \DateTime($annee.'-01-01');
        $ssylvestre= new \DateTime($annee.'-12-31');
        $debut_periode = $version -> GetFctStamp();
        if ($debut_periode==null) {
            $debut_periode = $jourdelan;
        } else {
            // Dans version on stocke la fin de la période précédente
            // Donc ici il faut prendre le lendemain
            $debut_periode->add(new \DateInterval('P1D'));
        }

        if ($fin_periode <= $debut_periode) {
            return $this->redirectToRoute('dfct_liste', array('id' => $projet->getId(), 'annee' => $annee));
        }

        $conso    = $dfct->getConsoPeriode($projet, $debut_periode, $fin_periode);

        $id_projet     = $projet->getIdProjet();
        $compta_repo   = $em -> getRepository(Compta::class);
        $db_conso      = $compta_repo->conso($id_projet, $annee);
        $dessin_heures = $this -> gcl;

        // conso  sur la période
        $struct_data   = $dessin_heures->createStructuredData($debut_periode, $fin_periode, $db_conso);
        if (count($struct_data)>10) {
            $dessin_heures->resetConso($struct_data);
            $image_conso_p = $dessin_heures->createImage($struct_data)[0];
        //$image_conso_p = null;
        } else {
            $image_conso_p = null;
        }

        // conso sur toute l'année
        $struct_data   = $dessin_heures->createStructuredData($jourdelan, $ssylvestre, $db_conso);
        if (count($struct_data)>10) {
            $dessin_heures->resetConso($struct_data);
            $image_conso_a = $dessin_heures->createImage($struct_data)[0];
        //$image_conso_a = null;
        } else {
            $image_conso_a = null;
        }

        $html4pdf =  $this->render(
            'projetfct/dfctpdf.html.twig',
            [
            'projet' => $projet,
            'annee'  => $annee,
            'numero' => $numero,
            'debut_periode' => $debut_periode,
            'fin_periode'   => $fin_periode,
            'conso'  => $conso,
            'dessin_periode' => $image_conso_p,
            'dessin_annee'   => $image_conso_a
            ]
        );

        //return $html4pdf;
        $pdf = $this->pdf->getOutputFromHtml($html4pdf->getContent());

        // On stoque la date de fin de la période + 1j
        $stamp = clone $fin_periode;
        $stamp->add(new \DateInterval('P1D'));

        $version -> setFctStamp($stamp);
        $em->persist($version);
        $em->flush();

        // On sauvegarde le pdf
        $dfct->savePdf($projet, $annee, $pdf);

        // On retourne à la liste
        return $this->redirectToRoute('dfct_liste', ['id' => $projet->getId(), 'annee' => $annee]);

        //	    return Functions::pdf( $pdf );
    }
}
