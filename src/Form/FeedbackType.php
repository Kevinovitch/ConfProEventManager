<?php

namespace App\Form;

use App\Entity\Feedback;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Range;

/**
 * Form for submitting feedback
 */
class FeedbackType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('rating', ChoiceType::class, [
                'label' => 'Your overall rating',
                'choices' => [
                    '5 - Excellent' => 5,
                    '4 - Very Good' => 4,
                    '3 - Good' => 3,
                    '2 - Fair' => 2,
                    '1 - Poor' => 1
                ],
                'constraints' => [
                    new NotBlank(),
                    new Range([
                        'min' => 1,
                        'max' => 5
                    ])
                ],
                'expanded' => true,
                'attr' => [
                    'class' => 'rating-options'
                ]
            ])
            ->add('aspectRated', ChoiceType::class, [
                'label' => 'What aspect are you rating the most?',
                'choices' => [
                    'Content quality' => 'content',
                    'Presenter skills' => 'presenter',
                    'Organization' => 'organization',
                    'Technical setup' => 'technical',
                    'Relevance to my needs' => 'relevance'
                ],
                'required' => false,
                'placeholder' => 'Select an aspect',
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('comment', TextareaType::class, [
                'label' => 'Your comments (optional)',
                'required' => false,
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'Share your thoughts about the conference...',
                    'class' => 'form-control'
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null, // We're not binding directly to an entity
        ]);
    }
}