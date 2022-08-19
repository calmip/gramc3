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

use App\Entity\Laboratoire;
use App\Utils\Functions;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Laboratoire controller.
 *
 * @Route("laboratoire")
 */
class LaboratoireController extends AbstractController
{
    public function __construct(private AuthorizationCheckerInterface $ac, private EntityManagerInterface $em) {}

    /**
     * Liste tous les laboratoires
     * 
     * @Route("/gerer",name="gerer_laboratoires", methods={"GET"} )
     * @Security("is_granted('ROLE_OBS')")
     */
    public function gererAction(): Response
    {
        $ac = $this->ac;
        $em = $this->em;

        // Si on n'est pas admin on n'a pas accès au menu
        $menu = $ac->isGranted('ROLE_ADMIN') ? [ ['ok' => true,'name' => 'ajouter_laboratoire' ,'lien' => 'Ajouter un laboratoire','commentaire'=> 'Ajouter un laboratoire'] ] : [];

        return $this->render(
            'laboratoire/liste.html.twig',
            [
            'menu' => $menu,
            'laboratoires' => $em->getRepository(Laboratoire::class)->findBy([], ['numeroLabo' => 'ASC'])
            ]
        );
    }

    /**
     * Ajoute un nouveau laboratoire
     *
     * @Route("/ajouter", name="ajouter_laboratoire", methods={"GET","POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method({"GET", "POST"})
     */
    public function ajouterAction(Request $request): Response
    {
        $laboratoire = new Laboratoire();
        $form = $this->createForm('App\Form\LaboratoireType', $laboratoire, ['ajouter' => true ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->em;
            $em->persist($laboratoire);
            if (Functions::Flush($em,$request)) return $this->redirectToRoute('gerer_laboratoires');
        }

        return $this->render(
            'laboratoire/ajouter.html.twig',
            [
            'menu' => [ [
                        'ok' => true,
                        'name' => 'gerer_laboratoires',
                        'lien' => 'Retour vers la liste des laboratoires',
                        'commentaire'=> 'Retour vers la liste des laboratoires'
                        ] ],
            'laboratoire' => $laboratoire,
            'form' => $form->createView(),
            ]
        );
    }

    /**
     * Modifie un laboratoire
     *
     * @Route("/{id}/modify", name="modifier_laboratoire", methods={"GET","POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method({"GET", "POST"})
     */
    public function modifyAction(Request $request, Laboratoire $laboratoire): Response
    {
        $em = $this->em;
        $editForm = $this->createForm('App\Form\LaboratoireType', $laboratoire, ['modifier' => true ]);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            if (Functions::Flush($em,$request)) return $this->redirectToRoute('gerer_laboratoires');
        }

        return $this->render(
            'laboratoire/modif.html.twig',
            [
            'menu' => [ [
                        'ok' => true,
                        'name' => 'gerer_laboratoires',
                        'lien' => 'Retour vers la liste des laboratoires',
                        'commentaire'=> 'Retour vers la liste des laboratoires'
                        ] ],
            'laboratoire' => $laboratoire,
            'form' => $editForm->createView(),
            ]
        );
    }

    /**
     * Supprime un laboratoire
     *
     * @Route("/{id}/supprimer", name="supprimer_laboratoire", methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method("DELETEEEE")
     */
    public function supprimerAction(Request $request, Laboratoire $laboratoire): Response
    {
        $em = $this->em;
        $em->remove($laboratoire);

        try {
            $em->flush();
        }
        catch ( \Exception $e) {
            $request->getSession()->getFlashbag()->add("flash erreur",$e->getMessage());
        }
        
        return $this->redirectToRoute('gerer_laboratoires');
    }

}
