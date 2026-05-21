<?php

namespace App\Form;

use App\Entity\Transaction;
use App\Entity\Property;
use App\Entity\User;
use App\Repository\PropertyRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;

class TransactionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('property', EntityType::class, [
                'class' => Property::class,
                'choice_label' => 'title',
                'label' => 'Property',
                'placeholder' => 'Select a property',
                'attr' => [
                    'class' => 'form-control',
                ],
                'query_builder' => function (PropertyRepository $er) {
                    return $er->createQueryBuilder('p')
                        ->where('p.status != :sold')
                        ->setParameter('sold', 'sold')
                        ->orderBy('p.title', 'ASC');
                },
            ])
            ->add('customer', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'username',
                'label' => false,
                'attr' => [
                    'style' => 'display: none;',
                ],
                'data' => $options['current_user'],
                'required' => true,
            ])
            ->add('purchaseType', ChoiceType::class, [
                'label' => 'Purchase Type',
                'choices' => [
                    'Buy' => 'buy',
                    'Rent' => 'rent',
                ],
                'placeholder' => 'Select purchase type',
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('date', DateType::class, [
                'label' => 'Purchase Date',
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'form-control',
                ],
                'data' => new \DateTime(),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Transaction::class,
            'current_user' => null,
        ]);
    }
}
