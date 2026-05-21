<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/clients')]
#[IsGranted('ROLE_ADMIN')]
class ClientsController extends AbstractController
{
    #[Route('/', name: 'app_clients_index', methods: ['GET'])]
    public function index(UserRepository $userRepository): Response
    {
        $allUsers = $userRepository->findAll();

        // Filter users with ROLE_USER or ROLE_STAFF, exclude ROLE_ADMIN
        $clients = array_filter($allUsers, function($user) {
            $roles = $user->getRoles();
            // Exclude if user has ROLE_ADMIN
            if (in_array('ROLE_ADMIN', $roles)) {
                return false;
            }
            // Include if user has ROLE_USER or ROLE_STAFF
            return in_array('ROLE_USER', $roles) || in_array('ROLE_STAFF', $roles);
        });

        return $this->render('clients/index.html.twig', [
            'clients' => $clients,
            'total_clients' => count($clients),
        ]);
    }

    #[Route('/new', name: 'app_client_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $userPasswordHasher): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user, ['is_new' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hash password
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));
            }

            // Set role (convert single role to array)
            $selectedRole = $form->get('roles')->getData();
            $user->setRoles([$selectedRole]);

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'User created successfully.');
            return $this->redirectToRoute('app_clients_index');
        }

        return $this->render('clients/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_client_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, UserRepository $userRepository, EntityManagerInterface $em, UserPasswordHasherInterface $userPasswordHasher, ActivityLogService $activityLogService, int $id): Response
    {
        $user = $userRepository->find($id);

        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        $form = $this->createForm(UserType::class, $user, ['is_new' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Update password if provided
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));
            }

            // Update role (convert single role to array)
            $selectedRole = $form->get('roles')->getData();
            $user->setRoles([$selectedRole]);

            $em->flush();

            // Log user update
            if ($this->getUser()) {
                $activityLogService->logActivity($this->getUser(), 'Record update');
            }

            $this->addFlash('success', 'User updated successfully.');
            return $this->redirectToRoute('app_clients_index');
        }

        return $this->render('clients/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }




    #[Route('/{id}', name: 'app_client_delete', methods: ['POST'])]
    public function delete(Request $request, UserRepository $userRepository, EntityManagerInterface $em, ActivityLogService $activityLogService, int $id): Response
    {
        $client = $userRepository->find($id);

        if (!$client) {
            throw $this->createNotFoundException('Client not found');
        }

        // Validate CSRF token
        if ($this->isCsrfTokenValid('delete' . $client->getId(), $request->request->get('_token'))) {
            // Log user deletion before removing
            if ($this->getUser()) {
                $activityLogService->logActivity($this->getUser(), 'User deletion');
            }
            
            $em->remove($client);
            $em->flush();
        }

        return $this->redirectToRoute('app_clients_index');
    }
}
