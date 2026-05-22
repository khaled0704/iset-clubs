<?php

namespace App\Form;

use App\Entity\Feedback;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Range;

class FeedbackType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('content', TextareaType::class, [
                'label' => 'Votre avis',
                'attr' => ['class' => 'form-control', 'rows' => 3]
            ])
            ->add('rating', IntegerType::class, [
                'label' => 'Note (1-5)',
                'attr' => ['class' => 'form-control', 'min' => 1, 'max' => 5],
                'constraints' => [new Range(['min' => 1, 'max' => 5])]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Feedback::class]);
    }
}