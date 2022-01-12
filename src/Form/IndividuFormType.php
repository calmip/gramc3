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

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;

//use App\Utils\IndividuForm;
use App\Form\IndividuForm\IndividuForm;

class IndividuFormType extends AbstractType
{
    private $coll_login;
    private $nodata;

    // Utilisation des paramètres coll_login et nodata
    // On pourrait aussi passer par $options de buildForm, sauf que le formulaire est construit la plupart du temps par
    // l'intermédiaire d'un CollectionType, et je ne sais pas comment passer les paramètres
    public function __construct($coll_login, $nodata)
    {
        $this -> coll_login = $coll_login;
        $this -> nodata = $nodata;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($this->coll_login == true)
        {
             $builder->add(
                'login',
                CheckboxType::class,
                [
                    'label'     => 'login calcul',
                    'required'  => false,
                    'attr' => [ 'title' => 'Demander l\'ouverture d\'un compte sur le supercalculateur' ]
                ]
            );
        };
        if ($this->nodata == false)
        {
             $builder->add(
                'clogin',
                CheckboxType::class,
                [
                    'label'     => 'accès callisto',
                    'required'  => false,
                    'attr' => [ 'title' => 'Demander un accès à la plateforme Callisto' ]
                ]
            );
        };
        $builder->add(
            'mail',
            TextType::class,
            [
                'label'     => 'email',
                'attr'      => [ 'size' => '50' ],
                'required'  => false,
            ]
        )
        ->add(
            'prenom',
            TextType::class,
            [
                'label'     => 'prénom',
                'attr'      => [ 'size' => '50' ],
                'required'  => false,
            ]
        )
        ->add(
            'nom',
            TextType::class,
            [
                'label'     => 'nom',
                'attr'      => [ 'size' => '50' ],
                'required'  => false,
            ]
        )
        ->add(
            'statut',
            EntityType ::class,
            [
                'label'      => 'statut',
                'multiple'   => false,
                'expanded'   => false,
                'required'   => false,
                'class'      => 'App:Statut',
                'placeholder' => '-- Indiquez le statut',
            ]
        )
        ->add(
            'laboratoire',
            EntityType ::class,
            [
                'label'     => 'laboratoire',
                'multiple'  => false,
                'expanded'  => false,
                'required'   => false,
                'class'     => 'App:Laboratoire',
                'placeholder' => '-- Indiquez le laboratoire',
            ]
        )
        ->add(
            'etablissement',
            EntityType ::class,
            [
                'label'     => 'établissement',
                'multiple'  => false,
                'expanded'  => false,
                'required'   => false,
                'class'     => 'App:Etablissement',
                'placeholder' => "-- Indiquez l'établissment",
            ]
        )
        ->add(
            'delete',
            CheckboxType::class,
            [
                'label'     =>  'supprimer',
                'required'  =>  false,
            ]
        )
        ->add(
            'id',
            HiddenType::class,
            [

            ]
        )
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
            'data_class' => IndividuForm::class,
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix()
    {
        return 'Individu';
    }
}
