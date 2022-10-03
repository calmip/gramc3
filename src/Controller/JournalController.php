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

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use App\Utils\Functions;
use App\Entity\Journal;
use App\Form\SelectJournalType;
use Symfony\Component\Form\FormInterface;
use Doctrine\ORM\EntityManagerInterface;


/**
 * Journal controller.
 *
 * @Route("journal")
 * @Security("is_granted('ROLE_ADMIN')")
 */
class JournalController extends AbstractController
{
    public function __construct(private FormFactoryInterface $ff, private EntityManagerInterface $em) {}

    /**
     * Lists all Journal entities.
     *
     * @Route("/list", name="journal_list", methods={"GET","POST"})
     * Method({"GET", "POST"})
     */
    public function listAction(Request $request): Response
    {
        $data = $this->index($request);

        // journal/list.html.twig

        return $this->render(
            'journal/list.html.twig',
            [
            'journals'  => $data['journals'],
            'form'      => $data['form']->createView(),
        ]
        );
    }

    /**
     * Lists all Journal entities.
     * CRUD
     *
     * @Route("/", name="journal_index", methods={"GET","POST"})
     * Method({"GET", "POST"})
     */

    public function indexAction(Request $request): Response
    {
        $data = $this->index($request);


        return self::render(
            'journal/index.html.twig',
            [
            'journals'  => $data['journals'],
            'form'      => $data['form']->createView(),
        ]
        );
    }

    /**
     * Creates a new journal entity.
     *
     * @Route("/new", name="journal_new", methods={"GET","POST"})
     * Method({"GET", "POST"})
     */
    public function newAction(Request $request): Response
    {
        $journal = new Journal();
        $form = $this->createForm('App\Form\JournalType', $journal);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->em;
            $em->persist($journal);
            $em->flush($journal);

            return $this->redirectToRoute('journal_show', array('id' => $journal->getId()));
        }

        return $this->render('journal/new.html.twig', array(
            'journal' => $journal,
            'form' => $form->createView(),
        ));
    }

    /**
     * Finds and displays a journal entity.
     *
     * @Route("/{id}", name="journal_show", methods={"GET"})
     * Method("GET")
     */
    public function showAction(Journal $journal): Response
    {
        $deleteForm = $this->createDeleteForm($journal);

        return $this->render('journal/show.html.twig', array(
            'journal' => $journal,
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Displays a form to edit an existing journal entity.
     *
     * @Route("/{id}/edit", name="journal_edit", methods={"GET","POST"})
     * Method({"GET", "POST"})
     */
    public function editAction(Request $request, Journal $journal): Response
    {
        $deleteForm = $this->createDeleteForm($journal);
        $editForm = $this->createForm('App\Form\JournalType', $journal);
        $editForm->handleRequest($request);

        if ($editForm->isSubmitted() && $editForm->isValid()) {
            $this->em->flush();

            return $this->redirectToRoute('journal_edit', array('id' => $journal->getId()));
        }

        return $this->render('journal/edit.html.twig', array(
            'journal' => $journal,
            'edit_form' => $editForm->createView(),
            'delete_form' => $deleteForm->createView(),
        ));
    }

    /**
     * Deletes a journal entity.
     *
     * @Route("/{id}", name="journal_delete", methods={"DELETE"})
     * Method("DELETE")
     */
    public function deleteAction(Request $request, Journal $journal): Response
    {
        $form = $this->createDeleteForm($journal);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->em;
            $em->remove($journal);
            $em->flush($journal);
        }

        return $this->redirectToRoute('journal_index');
    }

    /**
     * Creates a form to delete a journal entity.
     *
     * @param Journal $journal The journal entity
     *
     * @return \Symfony\Component\Form\Form The form
     */
    private function createDeleteForm(Journal $journal): FormInterface
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('journal_delete', array('id' => $journal->getId())))
            ->setMethod('DELETE')
            ->getForm()
        ;
    }

    private function index(Request $request): array
    {
        $ff = $this->ff;
        $em = $this->em;

        // quand on n'a pas de class on doit définir un nom du formulaire pour HTML
        //$form = Functions::getFormBuilder($ff, 'jnl_requetes', SelectJournalType::class, [] )->getForm();
        $form = $ff->createNamedBuilder('jnl_requetes', SelectJournalType::class, null, []) -> getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // on récupère un array avec des données du formulaire [ 'debut' => ... , 'fin' => ... , 'niveau' => .....]
            $data = $form->getData();
        } else {
            // des valeurs par défaut
            $data['dateDebut']  = new \DateTime();  // attention, cette valeur remplacée par la valeur dans Form/SelectJournalType
            $data['dateFin'] = new \DateTime();
            $data['dateFin']->add(\DateInterval::createFromDateString('1 day')); // attention, cette valeur remplacée par la valeur dans Form/SelectJournalType
            $data['niveau'] = Journal::INFO; // attention, cette valeur remplacée par la valeur dans Form/SelectJournalType
        }

        $journals =  $em->getRepository(Journal::class)->findData($data['dateDebut'], $data['dateFin'], $data['niveau']);
        return [ 'journals' => $journals, 'form' => $form ];
    }
}
