<?php

namespace App\Form;

use App\Entity\Conference;
use App\Entity\Session;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Form for creating and editing sessions
 */
class SessionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('conference', EntityType::class, [
                'class' => Conference::class,
                'choice_label' => 'title',
                'placeholder' => 'Select a conference',
                'choices' => $options['scheduled_conferences'],
                'attr' => [
                    'class' => 'form-control'
                ],
                'disabled' => $options['is_edit'],
            ])
            ->add('startTime', DateTimeType::class, [
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('endTime', DateTimeType::class, [
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('room', ChoiceType::class, [
                'choices' => array_combine($options['available_rooms'], $options['available_rooms']),
                'placeholder' => 'Select a room',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Session::class,
            'available_rooms' => [],
            'scheduled_conferences' => [],
            'is_edit' => false,
        ]);
    }
}