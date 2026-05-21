<?php

namespace App\Form;

use App\Entity\Notification;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class NotificationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('recipient', EntityType::class, [
                'class' => User::class,
                'choice_label' => 'username',
                'label' => 'Client',
                'placeholder' => 'Select a client',
                'choices' => $options['recipients'],
                'constraints' => [
                    new NotBlank(['message' => 'Please select a client.']),
                ],
            ])
            ->add('title', TextType::class, [
                'label' => 'Title (optional)',
                'required' => false,
                'attr' => ['placeholder' => 'e.g. Payment reminder'],
            ])
            ->add('message', TextareaType::class, [
                'label' => 'Message',
                'attr' => ['rows' => 5, 'placeholder' => 'Message shown in the client app'],
                'constraints' => [
                    new NotBlank(['message' => 'Please enter a message.']),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Notification::class,
            'recipients' => [],
        ]);
        $resolver->setAllowedTypes('recipients', 'array');
    }
}
