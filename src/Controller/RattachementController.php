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

use App\Entity\Rattachement;
use App\Entity\Individu;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Rattachement controller.
 *
 * @Route("rattachement")
 */
class RattachementController extends AbstractController
{
    public function __construct(private AuthorizationCheckerInterface $ac, private EntityManagerInterface $em) {}

    /**
      * @Route("/gerer",name="gerer_rattachements", methods={"GET"} )
      * @Security("is_granted('ROLE_OBS')")
      */
    public function gererAction()
    {
        $ac = $this->ac;
        $em = $this->em;

        // Si on n'est pas admin on n'a pas accès au menu
        $menu = $ac->isGranted('ROLE_ADMIN') ? [ ['ok' => true,'name' => 'ajouter_rattachement' ,'lien' => 'Ajouter un rattachement','commentaire'=> 'Ajouter un rattachement'] ] : [];

        return $this->render(
            'rattachement/liste.html.twig',
            [
            'menu' => $menu,
            'rattachements' => $em->getRepository(Rattachement::class)->findBy([], ['libelleRattachement' => 'ASC'])
            ]
        );
    }

    /**
     * Creates a new rattachement entity.
     *
     * @Route("/ajouter", name="ajouter_rattachement", methods={"GET","POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method({"GET", "POST"})
     */
    public function newAction(Request $request)
    {
        $em = $this->em;
        $rattachement = new Rattachement();
        $form = $this->createForm(
            'App\Form\RattachementType',
            $rattachement,
            [
            'ajouter' => true,
            'experts'   => $em->getRepository(Individu::class)->findBy(['expert' => true ]),
            ]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->em;
            $em->persist($rattachement);
            $em->flush();

            return $this->redirectToRoute('gerer_rattachements');
        }

        return $this->render(
            'rattachement/ajouter.html.twig',
            [
            'menu' => [ [
                        'ok' => true,
                        'name' => 'gerer_rattachements',
                        'lien' => 'Retour vers la liste des rattachements',
                        'commentaire'=> 'Retour vers la liste des rattachements'
                        ] ],
            'rattachement' => $rattachement,
            'edit_form' => $form->createView(),
            ]
        );
    }
    /**
     * Displays a form to edit an existing rattachement entity.
     *
     * @Route("/{id}/modify", name="modifier_rattachement", methods={"GET","POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method({"GET", "POST"})
     */
    public function modifyAction(Request $request, Rattachement $rattachement)
    {
        $em = $this->em;
        $editForm = $this->createForm(
            'App\Form\RattachementType',
            $rattachement,
            [
            'modifier'  => true,
            'experts'   => $em->getRepository(Individu::class)->findBy(['expert' => true ]),
            ]
        );
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $em->flush();

            return $this->redirectToRoute('gerer_rattachements');
        }

        return $this->render(
            'rattachement/modif.html.twig',
            [
            'menu' => [ [
                        'ok' => true,
                        'name' => 'gerer_rattachements',
                        'lien' => 'Retour vers la liste des rattachements',
                        'commentaire'=> 'Retour vers la liste des rattachements'
                        ] ],
            'rattachement' => $rattachement,
            'edit_form' => $editForm->createView(),
            ]
        );
    }

    /**
     * Deletes a rattachement entity.
     *
     * @Route("/{id}/supprimer", name="supprimer_rattachement", methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method("GET")
     */
    public function supprimerAction(Request $request, Rattachement $rattachement)
    {
        $em = $this->em;
        try
        {
            $em->remove($rattachement);
            $em->flush($rattachement);
        }
        catch (\Exception $e)
        {
            $request->getSession()->getFlashbag()->add("flash erreur",$e->getMessage());
        }
        return $this->redirectToRoute('gerer_rattachements');
    }
}
