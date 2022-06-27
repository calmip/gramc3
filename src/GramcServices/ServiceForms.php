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

use App\GramcServices\GramcDate;
use App\GramcServices\Etat;
use App\Validator\Constraints\PagesNumber;

use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\File;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityManagerInterface;

/*************************************************************
 * Quelques fonctions utiles pour les formulaires
 *
 *************************************************************/
class ServiceForms
{
    public function __construct(private $max_size_doc, private ValidatorInterface $vl, private FormFactoryInterface $ff, private EntityManagerInterface $em, private ServiceJournal $sj)
    {}

    /******************************************
     * Appelle le service de validation sur les data par rapport à des contraintes
     * retourne une chaine de caractères: soit les erreurs de validation, soit "OK"
     *
     *************************************/
    public function formError($data, $constraintes): string
    {
        $violations = $this->vl->validate($data, $constraintes);

        if (count($violations)>0) {
            $errors = "<strong>Erreurs : </strong>";
            foreach ($violations as $violation) {
                $errors .= $violation->getMessage() .' ';
            }
            return $errors;
        } else {
            return "OK";
        }
    }

    /**************************************************
     *
     * Crée un formulaire qui permettra de téléverser un fichier pdf
     * Gère le mécanisme de soumission et validation
     * Fonctionne aussi bien en ajax avec jquery-upload-file-master
     * que de manière "normale"
     *
     * params = request
     *          dirname : répertoire de destination
     *          filename: nom définitif du fichier
     *
     * return = la form si pas encore soumise
     *          ou une string: "OK"
     *          ou un message d'erreur
     *
     ********************************/
    public function televerserFichier(Request $request, $dirname, $filename): FormInterface|string
    {
        $sj = $this->sj;
        $ff = $this->ff;
        $max_size_doc = intval($this->max_size_doc);
        $maxSize = strval(1024 * $max_size_doc) . 'k';

        $format_fichier = new \Symfony\Component\Validator\Constraints\File(
        [
            'mimeTypes'=> [ 'application/pdf' ],
            'mimeTypesMessage'=>' Le fichier doit être un fichier pdf. ',
            'maxSize' => $maxSize,
            'uploadIniSizeErrorMessage' => ' Le fichier doit avoir moins de {{ limit }} {{ suffix }}. ',
            'maxSizeMessage' => ' Le fichier est trop grand ({{ size }} {{ suffix }}), il doit avoir moins de {{ limit }} {{ suffix }}. ',
        ]
        );

        $form = $ff
        ->createNamedBuilder('fichier', FormType::class, [], ['csrf_protection' => false ])
        ->add(
            'fichier',
            FileType::class,
            [
                'required'          =>  true,
                'label'             => "Fichier attaché",
                'constraints'       => [$format_fichier , new PagesNumber() ]
            ]
        )
        ->getForm();

        $form->handleRequest($request);

        // form soumise et valide = On met le fichier à sa place et on retourne OK
        if ($form->isSubmitted() && $form->isValid()) {
            $tempFilename = $form->getData()['fichier'];

            if (is_file($tempFilename) && ! is_dir($tempFilename)) {
                $file = new File($tempFilename);
            } elseif (is_dir($tempFilename)) {
                return "Erreur interne : Le nom  " . $tempFilename . " correspond à un répertoire" ;
            } else {
                return "Erreur interne : Le fichier " . $tempFilename . " n'existe pas" ;
            }

            $file->move($dirname, $filename);
            $sj->debugMessage(__METHOD__ . ':' . __LINE__ . " Fichier attaché -> " . $filename);
            return 'OK';
        }

        // formulaire non valide ou autres cas d'erreur = On retourne un message d'erreur
        elseif ($form->isSubmitted() && ! $form->isValid()) {
            if (isset($form->getData()['fichier']))
            {
                return  $this->formError($form->getData()['fichier'], [$format_fichier , new PagesNumber() ]);
            }
            else
            {
                return "<strong>Erreurs :</strong>Fichier trop gros ou autre problème";
            }
        }
        
        elseif ($request->isXMLHttpRequest())
        {
            return "Le formulaire n'a pas été soumis";
        }

        // formulaire non soumis = On retourne le formulaire
        else
        {
            return $form;
        }
    }
} // class
