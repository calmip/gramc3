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

use App\Entity\MetaThematique;
use App\Entity\Thematique;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;


/**
 * Metathematique controller.
 *
 * @Route("metathematique")
 */
class MetaThematiqueController extends AbstractController
{
    public function __construct(private AuthorizationCheckerInterface $ac, private EntityManagerInterface $em) {}

    /**
     * Lists all metaThematique entities.
     *
     * @Security("is_granted('ROLE_ADMIN')")
     * @Route("/", name="metathematique_index", methods={"GET"})
     * Method("GET")
     */
    public function indexAction(): Response
    {
        $em = $this->em;

        $metaThematiques = $em->getRepository(MetaThematique::class)->findAll();

        return $this->render('metathematique/index.html.twig', array(
            'metaThematiques' => $metaThematiques,
        ));
    }

    /**
     * @Route("/gerer",name="gerer_metaThematiques", methods={"GET"} )
     * @Security("is_granted('ROLE_OBS')")
     */
    public function gererAction(): Response
    {
        $ac = $this->ac;
        $em = $this->em;

        $menu = $ac->isGranted('ROLE_ADMIN') ? [ ['ok' => true,'name' => 'ajouter_metaThematique' ,'lien' => 'Ajouter une metathématique','commentaire'=> 'Ajouter une metathématique'] ] : [];
        return $this->render(
            'metathematique/liste.html.twig',
            [
            'menu' => $menu,
            'metathematiques' => $em->getRepository(MetaThematique::class)->findBy([], ['libelle' => 'ASC'])
            ]
        );
    }

    /**
     * Creates a new metaThematique entity.
     *
     * @Route("/new", name="metathematique_new", methods={"GET","POST"})
     * @Route("/ajouter", name="ajouter_metaThematique", methods={"GET","POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method({"GET", "POST"})
     */
    public function newAction(Request $request): Response
    {
        $metaThematique = new Metathematique();
        $form = $this->createForm('App\Form\MetaThematiqueType', $metaThematique, ['ajouter'  => true,]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->em;
            $em->persist($metaThematique);
            $em->flush($metaThematique);

            return $this->redirectToRoute('gerer_metaThematiques');
        }

        return $this->render(
            'metathematique/ajouter.html.twig',
            [
            'menu' => [ [
                        'ok' => true,
                        'name' => 'gerer_metaThematiques',
                        'lien' => 'Retour vers la liste des metathématiques',
                        'commentaire'=> 'Retour vers la liste des metathématiques'
                        ] ],
            'metaThematique' => $metaThematique,
            'edit_form' => $form->createView(),
            ]
        );
    }

    /**
     * Deletes a thematique entity.
     *
     * @Route("/{id}/supprimer", name="supprimer_metaThematique", methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method("GET")
     */
    public function supprimerAction(Request $request, MetaThematique $thematique): Response
    {
        $em = $this->em;
        $em->remove($thematique);
        try {
            $em->flush();
        }
        catch ( \Exception $e) {
            $request->getSession()->getFlashbag()->add("flash erreur",$e->getMessage());
        }
        return $this->redirectToRoute('gerer_metaThematiques');
    }

    /**
     * Displays a form to edit an existing laboratoire entity.
     *
     * @Route("/{id}/modify", name="modifier_metaThematique", methods={"GET","POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method({"GET", "POST"})
     */
    public function modifyAction(Request $request, MetaThematique $thematique): Response
    {
        $editForm = $this->createForm(
            'App\Form\MetaThematiqueType',
            $thematique,
            [
            'modifier'  => true,
            ]
        );
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->em->flush();
            return $this->redirectToRoute('gerer_metaThematiques');
        }

        return $this->render(
            'metathematique/modif.html.twig',
            [
            'menu' => [ [
                        'ok' => true,
                        'name' => 'gerer_metaThematiques',
                        'lien' => 'Retour vers la liste des metathématiques',
                        'commentaire'=> 'Retour vers la liste des metathématiques'
                        ] ],
            'metathematique' => $thematique,
            'edit_form' => $editForm->createView(),
            ]
        );
    }
    /**
     * Finds and displays a metaThematique entity.
     *
     * @Route("/{id}", name="metathematique_show", methods={"GET"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method("GET")
     */
    public function showAction(MetaThematique $metaThematique): Response
    {
        $deleteForm = $this->createDeleteForm($metaThematique);

        return $this->render('metathematique/show.html.twig', array(
            'metaThematique' => $metaThematique,
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing metaThematique entity.
     *
     * @Route("/{id}/edit", name="metathematique_edit", methods={"GET","POST"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method({"GET", "POST"})
     */
    public function editAction(Request $request, MetaThematique $metaThematique): Response
    {
        $deleteForm = $this->createDeleteForm($metaThematique);
        $editForm = $this->createForm('App\Form\MetaThematiqueType', $metaThematique);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->em->flush();

            return $this->redirectToRoute('metathematique_edit', array('id' => $metaThematique->getId()));
        }

        return $this->render('metathematique/edit.html.twig', array(
            'metaThematique' => $metaThematique,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Deletes a metaThematique entity.
     *
     * @Route("/{id}", name="metathematique_delete", methods={"DELETE"})
     * @Security("is_granted('ROLE_ADMIN')")
     * Method("DELETE")
     */
    public function deleteAction(Request $request, MetaThematique $metaThematique): Response
    {
        $form = $this->createDeleteForm($metaThematique);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->em;
            $em->remove($metaThematique);
            $em->flush($metaThematique);
        }

        return $this->redirectToRoute('metathematique_index');
    }

    /**
     * Creates a form to delete a metaThematique entity.
     *
     * @param MetaThematique $metaThematique The metaThematique entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(MetaThematique $metaThematique): Response
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('metathematique_delete', array('id' => $metaThematique->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }
}
