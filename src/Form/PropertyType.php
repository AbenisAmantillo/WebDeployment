<?php

namespace App\Form;

use App\Entity\Property;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;


class PropertyType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $statusDisabled = $options['status_disabled'] ?? false;
        
        $builder
            ->add('title')
            ->add('status', ChoiceType::class, [
                    'choices' => [
                        'Available' => 'available',
                        'Pending' => 'pending',
                        'Sold' => 'sold',
                    ],
                    'placeholder' => 'Select Status',
                    'disabled' => $statusDisabled,
                    'attr' => $statusDisabled ? [
                        'class' => 'form-control',
                        'style' => 'background-color: #f3f4f6; cursor: not-allowed;',
                    ] : [
                        'class' => 'form-control',
                    ],
                ])
            ->add('price')
            ->add('address')
            ->add('imageFile', FileType::class, [
                'label' => 'Property Image (JPEG, PNG, GIF, WebP)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image file',
                    ])
                ],
            ])
        ;
                
        // If status is disabled, preserve the original value on submit
        if ($statusDisabled) {
            $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
                $form = $event->getForm();
                $data = $event->getData();
                // Get the original status from the form's data
                $originalStatus = $form->getData()->getStatus();
                // Keep the original status
                $data['status'] = $originalStatus;
                $event->setData($data);
            });
        }
    } 

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Property::class,
            'status_disabled' => false,
        ]);
    }
}