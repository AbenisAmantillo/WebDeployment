<?php

namespace App\Form;

use App\Entity\Furniture;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class FurnitureType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name')
            ->add('price')
            ->add('stock')
            ->add('status', ChoiceType::class, [
                    'choices' => [
                        'Available' => 'available',
                        'Sold' => 'sold',
                    ],
                    'placeholder' => 'Select Status',
                ])
            ->add('imageFile', FileType::class, [
                'label' => 'Furniture Image (JPEG, PNG, GIF, WebP)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image file',
                    ])
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Furniture::class,
        ]);
    }
}