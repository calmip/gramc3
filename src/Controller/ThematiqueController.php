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

use App\Entity\Thematique;
use App\Entity\Individu;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormInterface;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Thematique controller.
 *
 * @Route("thematique")
 */
class ThematiqueController extends AbstractController
{
    public function __construct(private AuthorizationCheckerInterface $ac, private EntityManagerInterface $em) {}

    /**
     * Lists all thematique entities.
     *
     * @Security("is_granted('ROLE_ADMIN')")
     * @Route("/", name="thematique_index",methods={"GET"})
     * Method("GET")
     */
    public function indexAction(): Response
    {
        $em = $this->em;

        $thematiques = $em->getRepository(Thematique::class)->findAll();

        return $this->render('thematique/index.html.twig', array(
            'thematiques' => $thematiques,
        ));
    }

    /**
     * @Route("/gerer",name="gerer_thematiques",methods={"GET"} )
     * Security("is_granted('ROLE_OBS')")
     */
    public function gererAction(): Response
    {
        $ac = $this->ac;
        $em = $this->em;

        // Si on n'est pas admin on n'a pas accès au menu
        $menu = $ac->isGranted('ROLE_ADMIN') ? [ ['ok' => true,'name' => 'ajouter_thematique' ,'lien' => 'Ajouter une thématique','commentaire'=> 'Ajouter une thématique'] ] : [];

        return $this->render(
            'thematique/liste.html.twig',
            [
            'menu' => $menu,
            'thematiques' => $em->getRepository(Thematique::class)->findBy([], ['libelleThematique' => 'ASC'])
            ]
        );
    }

    /**
     * Creates a new thematique entity.
     *
     * @Route("/new", name="thematique_new",methods={"GET","POST"})
     * @Route("/ajouter", name="ajouter_thematique",methods={"GET","POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method({"GET", "POST"})
     */
    public function newAction(Request $request): Response
    {
        $em = $this->em;
        $thematique = new Thematique();
        $form = $this->createForm(
            'App\Form\ThematiqueType',
            $thematique,
            [
            'ajouter' => true,
            'experts'   => $em->getRepository(Individu::class)->findBy(['expert' => true ]),
            ]
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->em;
            $em->persist($thematique);
            $em->flush($thematique);

            return $this->redirectToRoute('gerer_thematiques');
        }

        return $this->render(
            'thematique/ajouter.html.twig',
            [
            'menu' => [ [
                        'ok' => true,
                        'name' => 'gerer_thematiques',
                        'lien' => 'Retour vers la liste des thématiques',
                        'commentaire'=> 'Retour vers la liste des thématiques'
                        ] ],
            'thematique' => $thematique,
            'edit_form' => $form->createView(),
            ]
        );
    }

    /**
     * Deletes a thematique entity.
     *
     * @Route("/{id}/supprimer", name="supprimer_thematique",methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method("GET")
     */
    public function supprimerAction(Request $request, Thematique $thematique): Response
    {
        $em = $this->em;
        $em->remove($thematique);
        try {
            $em->flush();
        }
        catch ( \Exception $e) {
            $request->getSession()->getFlashbag()->add("flash erreur",$e->getMessage());
        }
        return $this->redirectToRoute('gerer_thematiques');
    }

    /**
     * Displays a form to edit an existing laboratoire entity.
     *
     * @Route("/{id}/modify", name="modifier_thematique",methods={"GET","POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method({"GET", "POST"})
     */
    public function modifyAction(Request $request, Thematique $thematique): Response
    {
        $em = $this->em;
        $editForm = $this->createForm(
            'App\Form\ThematiqueType',
            $thematique,
            [
            'modifier'  => true,
            'experts'   => $em->getRepository(Individu::class)->findBy(['expert' => true ]),
            ]
        );
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->em->flush();

            return $this->redirectToRoute('gerer_thematiques');
        }

        return $this->render(
            'thematique/modif.html.twig',
            [
            'menu' => [ [
                        'ok' => true,
                        'name' => 'gerer_thematiques',
                        'lien' => 'Retour vers la liste des thématiques',
                        'commentaire'=> 'Retour vers la liste des thématiques'
                        ] ],
            'thematique' => $thematique,
            'edit_form' => $editForm->createView(),
            ]
        );
    }
    /**
     * Finds and displays a thematique entity.
     *
     * @Route("/{id}", name="thematique_show",methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method("GET")
     */
    public function showAction(Thematique $thematique): Response
    {
        $deleteForm = $this->createDeleteForm($thematique);

        return $this->render('thematique/show.html.twig', array(
            'thematique' => $thematique,
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing thematique entity.
     *
     * @Route("/{id}/edit", name="thematique_edit",methods={"GET","POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method({"GET", "POST"})
     */
    public function editAction(Request $request, Thematique $thematique): Response
    {
        $deleteForm = $this->createDeleteForm($thematique);
        $editForm = $this->createForm('App\Form\ThematiqueType', $thematique);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->em->flush();

            return $this->redirectToRoute('thematique_edit', array('id' => $thematique->getId()));
        }

        return $this->render('thematique/edit.html.twig', array(
            'thematique' => $thematique,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Deletes a thematique entity.
     *
     * @Route("/{id}", name="thematique_delete",methods={"DELETE"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method("DELETE")
     */
    public function deleteAction(Request $request, Thematique $thematique): Response
    {
        $form = $this->createDeleteForm($thematique);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->em;
            $em->remove($thematique);
            $em->flush($thematique);
        }

        return $this->redirectToRoute('thematique_index');
    }

    /**
     * Creates a form to delete a thematique entity.
     *
     * @param Thematique $thematique The thematique entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Thematique $thematique): FormInterface
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('thematique_delete', array('id' => $thematique->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }
}
