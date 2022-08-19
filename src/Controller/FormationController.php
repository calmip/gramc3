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

use App\Entity\Formation;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormInterface;
use Doctrine\ORM\EntityManagerInterface;


/**
 * Formation controller.
 *
 * @Route("formation")
 */
class FormationController extends AbstractController
{
    public function __construct(private AuthorizationCheckerInterface $ac, private EntityManagerInterface $em) {}

    /**
     * @Route("/gerer",name="gerer_formations", methods={"GET"} )
     * @Security("is_granted('ROLE_OBS')")
     */
    public function gererAction(): Response
    {
        $ac = $this->ac;
        $em = $this->em;

        // Si on n'est pas admin on n'a pas accès au menu
        $menu = $ac->isGranted('ROLE_ADMIN') ? [ ['ok' => true,'name' => 'ajouter_formation' ,'lien' => 'Ajouter une formation','commentaire'=> 'Ajouter une formation'] ] : [];

        return $this->render(
            'formation/liste.html.twig',
            [
            'menu' => $menu,
            'formations' => $em->getRepository(Formation::class)->findBy([], ['numeroForm' => 'ASC'])
            ]
        );
    }

    /**
     * Creates a new formation entity.
     *
     * @Route("/ajouter", name="ajouter_formation", methods={"GET","POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method({"GET", "POST"})
     */
    public function newAction(Request $request): Response
    {
        $formation = new formation();
        $form = $this->createForm('App\Form\FormationType', $formation, ['ajouter' => true ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->em;
            $em->persist($formation);
            $em->flush($formation);

            return $this->redirectToRoute('gerer_formations');
        }

        return $this->render(
            'formation/ajouter.html.twig',
            [
            'menu' => [ [
                        'ok' => true,
                        'name' => 'gerer_formations',
                        'lien' => 'Retour vers la liste des formations',
                        'commentaire'=> 'Retour vers la liste des formations'
                        ] ],
            'formation' => $formation,
            'form' => $form->createView(),
            ]
        );
    }

    /**
     * Displays a form to edit an existing formation entity.
     *
     * @Route("/{id}/modify", name="modifier_formation", methods={"GET","POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method({"GET", "POST"})
     */
    public function modifyAction(Request $request, Formation $formation): Response
    {
        $deleteForm = $this->createDeleteForm($formation);
        $editForm = $this->createForm('App\Form\FormationType', $formation, ['modifier' => true ]);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->em->flush();

            return $this->redirectToRoute('gerer_formations');
        }

        return $this->render(
            'formation/modif.html.twig',
            [
            'menu' => [ [
                        'ok' => true,
                        'name' => 'gerer_formations',
                        'lien' => 'Retour vers la liste des formations',
                        'commentaire'=> 'Retour vers la liste des formations'
                        ] ],
            'formation' => $formation,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
            ]
        );
    }

    /**
     * Deletes a formation entity.
     *
     * @Route("/{id}/supprimer", name="supprimer_formation", methods={"GET"})
     * @Route("/{id}/supprimer", name="formation_delete", methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     */
    public function supprimerAction(Request $request, Formation $formation): Response
    {
        $em = $this->em;
        $em->remove($formation);
        $em->flush($formation);
        return $this->redirectToRoute('gerer_formations');
    }

    /**
      * Creates a form to delete a formation entity.
      *
      * @param Formation $formation The formation entity
      *
      * @return \Symfony\Component\Form\Form The form
      */
    private function createDeleteForm(formation $formation): FormInterface
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('formation_delete', array('id' => $formation->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }
}

