<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class AccountProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => 'Display name',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Your name',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter your display name.']),
                    new Length([
                        'min' => 3,
                        'minMessage' => 'Display name must be at least {{ limit }} characters.',
                        'max' => 180,
                    ]),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'disabled' => true,
                'attr' => [
                    'class' => 'form-control',
                ],
                'help' => 'Email cannot be changed here. Contact an administrator if you need to update it.',
            ])
            ->add('profileImage', FileType::class, [
                'label' => 'Profile photo',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/jpeg,image/png,image/gif,image/webp',
                ],
                'help' => 'JPEG, PNG, GIF, or WebP. Max 5 MB.',
                'constraints' => [
                    new File([
                        'maxSize' => '5M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                            'image/gif',
                            'image/webp',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image file.',
                    ]),
                ],
            ])
            ->add('removeProfileImage', CheckboxType::class, [
                'label' => 'Remove current profile photo',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
