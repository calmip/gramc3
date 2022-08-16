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

use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\ResetType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;

use App\Entity\Individu;
use App\Entity\Laboratoire;
use App\Entity\Etablissement;
use App\Entity\Statut;
use App\Entity\Thematique;

//use App\App;

use Doctrine\ORM\EntityManagerInterface;

class IndividuType extends AbstractType
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this -> em = $em;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['admin'] == true) {
            $builder->add('creationStamp');
        }

        if ($options['user'] == true)
        {
            $builder
                ->add('nom', TextType::class, [ 'label' => 'Nom:'])
                ->add('prenom', TextType::class, [ 'label' => 'Prénom']);
                if ($options['mail'] == true)
                {
                    $builder->add('mail', EmailType::class);
                }
                else
                {
                   $builder->add('mail', EmailType::class, [ 'disabled' => true]);
                }

            if ($options['mail'] == true)
            {
                $builder->add('mail', EmailType::class);
            }
            else
            {
               $builder->add('mail', EmailType::class, [ 'disabled' => true]); 
            }
        }

        if ($options['admin'] == true) {
            $builder
                ->add('admin')
                ->add('expert')
                ->add('responsable')
                ->add('collaborateur')
                ->add('president')
                ->add('desactive');
        }

        if ($options['user'] == true) {
            $builder
                ->add(
                    'labo',
                    EntityType::class,
                    [
                    'label' => 'Laboratoire:',
                    'class' => Laboratoire::class,
                    'multiple' => false,
                    'placeholder'   => '-- Indiquez le laboratoire',
                    'required'  => false,
                    'choices'   => $this->em->getRepository(Laboratoire::class)->findAllSorted(),
                    'attr' => ['style' => 'width:20em'],
                    ]
                );
        }


        if ($options['permanent'] == true) {
            $builder
                ->add(
                    'statut',
                    EntityType::class,
                    [
                    'placeholder'   => '-- Indiquez votre statut',
                    'label' => 'Statut:',
                    'class' => Statut::class,
                    'multiple' => false,
                    'required'  => false,
                    'choices'   => $this->em->getRepository(Statut::class)->findBy(['permanent' => true ]),
                    'attr' => ['style' => 'width:20em'],
                    ]
                );
        } else {
            $builder
                ->add(
                    'statut',
                    EntityType::class,
                    [
                    'placeholder'   => '-- Indiquez votre statut',
                    'label' => 'Statut:',
                    'class' => Statut::class,
                    'multiple' => false,
                    'required'  => false,
                    'attr' => ['style' => 'width:20em'],
                    ]
                );
        }

        $builder
            ->add(
                'etab',
                EntityType::class,
                [
                    'placeholder'   => '-- Indiquez votre établissement',
                    'label' => 'Établissement:',
                    'class' => Etablissement::class,
                    'multiple' => false,
                    'required'  => false,
                    'attr' => ['style' => 'width:20em'],
                    ]
            );

        if ($options['thematique'] == true) {
            $builder->add(
                'thematique',
                EntityType::class,
                [
                'multiple' => true,
                'expanded' => true,
                'class' => Thematique::class,
                ]
            );
        }

        if ($options['submit'] == true) {
            $builder
                ->add(
                    'submit',
                    SubmitType::class,
                    [
                    'label' => 'Valider',
                    'attr'  =>  ['style' => 'width:10em'],
                    ]
                );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(
            [
            'data_class'    => 'App\Entity\Individu',
            'admin'         => false,
            'user'          => true,
            'submit'        => true,
            'thematique'    => false,
            'permanent'     => false,
            'mail'          => true,
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'appbundle_individu';
    }
}
