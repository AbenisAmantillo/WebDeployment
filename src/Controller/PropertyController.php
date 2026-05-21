<?php

namespace App\Controller;

use App\Entity\Property;
use App\Form\PropertyType;
use App\Repository\PropertyRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/property')]
final class PropertyController extends AbstractController
{
    #[Route(name: 'app_property_index', methods: ['GET'])]
    public function index(PropertyRepository $propertyRepository): Response
    {
        return $this->render('property/index.html.twig', [
            'properties' => $propertyRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_property_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger, ActivityLogService $activityLogService): Response
    {
        $property = new Property();
        $form = $this->createForm(PropertyType::class, $property);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Handle image upload
            $imageFile = $form->get('imageFile')->getData();
            
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('property_images_directory'),
                        $newFilename
                    );
                    
                    $property->setImageFileName($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload image');
                }
            }

            $entityManager->persist($property);
            $entityManager->flush();

            // Log property creation
            if ($this->getUser()) {
                $activityLogService->logActivity($this->getUser(), 'Property creation');
            }

            return $this->redirectToRoute('app_property_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('property/new.html.twig', [
            'property' => $property,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_property_show', methods: ['GET'])]
    public function show(Property $property): Response
    {
        return $this->render('property/show.html.twig', [
            'property' => $property,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_property_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Property $property, EntityManagerInterface $entityManager, SluggerInterface $slugger, ActivityLogService $activityLogService): Response
    {
        // Check if property has transactions
        $hasTransactions = $property->getTransactions()->count() > 0;
        
        // Store original status before form processing
        $originalStatus = $property->getStatus();
        
        $form = $this->createForm(PropertyType::class, $property, [
            'status_disabled' => $hasTransactions,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Prevent status change if property has transactions
            if ($hasTransactions && $property->getStatus() !== $originalStatus) {
                $property->setStatus($originalStatus);
                $this->addFlash('warning', 'Status cannot be changed for properties with transactions.');
            }
            // Handle image upload
            $imageFile = $form->get('imageFile')->getData();
            
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('property_images_directory'),
                        $newFilename
                    );
                    
                    // Delete old image if exists
                    if ($property->getImageFileName()) {
                        $oldImagePath = $this->getParameter('property_images_directory').'/'.$property->getImageFileName();
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }
                    
                    $property->setImageFileName($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload image');
                }
            }

            $entityManager->flush();

            // Log property update
            if ($this->getUser()) {
                $activityLogService->logActivity($this->getUser(), 'Property update');
            }

            return $this->redirectToRoute('app_property_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('property/edit.html.twig', [
            'property' => $property,
            'form' => $form,
            'has_transactions' => $hasTransactions,
        ]);
    }

    #[Route('/{id}', name: 'app_property_delete', methods: ['POST'])]
    public function delete(Request $request, Property $property, EntityManagerInterface $entityManager, ActivityLogService $activityLogService): Response
    {
        if ($this->isCsrfTokenValid('delete'.$property->getId(), $request->getPayload()->getString('_token'))) {
            // Log property deletion before removing
            if ($this->getUser()) {
                $activityLogService->logActivity($this->getUser(), 'Property deletion');
            }
            
            // Delete image file if exists
            if ($property->getImageFileName()) {
                $imagePath = $this->getParameter('property_images_directory').'/'.$property->getImageFileName();
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }
            
            $entityManager->remove($property);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_property_index', [], Response::HTTP_SEE_OTHER);
    }
}