<?php

namespace App\Controller;

use App\Entity\Furniture;
use App\Form\FurnitureType;
use App\Repository\FurnitureRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/furniture')]
final class FurnitureController extends AbstractController
{
    #[Route(name: 'app_furniture_index', methods: ['GET'])]
    public function index(FurnitureRepository $furnitureRepository): Response
    {
        return $this->render('furniture/index.html.twig', [
            'furniture' => $furnitureRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_furniture_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger, ActivityLogService $activityLogService): Response
    {
        $furniture = new Furniture();
        $form = $this->createForm(FurnitureType::class, $furniture);
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
                        $this->getParameter('furniture_images_directory'),
                        $newFilename
                    );
                    
                    $furniture->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload image');
                }
            }

            $entityManager->persist($furniture);
            $entityManager->flush();

            // Log furniture creation
            if ($this->getUser()) {
                $activityLogService->logActivity($this->getUser(), 'Furniture creation');
            }

            return $this->redirectToRoute('app_furniture_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('furniture/new.html.twig', [
            'furniture' => $furniture,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_furniture_show', methods: ['GET'])]
    public function show(Furniture $furniture): Response
    {
        return $this->render('furniture/show.html.twig', [
            'furniture' => $furniture,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_furniture_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Furniture $furniture, EntityManagerInterface $entityManager, SluggerInterface $slugger, ActivityLogService $activityLogService): Response
    {
        // Store original stock value before form submission
        $originalStock = $furniture->getStock();
        
        $form = $this->createForm(FurnitureType::class, $furniture);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check if stock is being decreased (only if original stock is not null)
            $newStock = $furniture->getStock();
            if ($originalStock !== null && $newStock !== null && $newStock < $originalStock) {
                // Revert to original stock
                $furniture->setStock($originalStock);
                // Refresh form data to show the corrected stock value
                $form->setData($furniture);
                $this->addFlash('error', 'Stock cannot be decreased. You can only increase stock or keep it the same. Current stock: ' . $originalStock);
                
                return $this->render('furniture/edit.html.twig', [
                    'furniture' => $furniture,
                    'form' => $form,
                ]);
            }
            
            // Handle image upload
            $imageFile = $form->get('imageFile')->getData();
            
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('furniture_images_directory'),
                        $newFilename
                    );
                    
                    // Delete old image if exists
                    if ($furniture->getImage()) {
                        $oldImagePath = $this->getParameter('furniture_images_directory').'/'.$furniture->getImage();
                        if (file_exists($oldImagePath)) {
                            unlink($oldImagePath);
                        }
                    }
                    
                    $furniture->setImage($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload image');
                }
            }

            $entityManager->flush();

            // Log furniture update
            if ($this->getUser()) {
                $activityLogService->logActivity($this->getUser(), 'Furniture update');
            }

            return $this->redirectToRoute('app_furniture_index');
        }

        return $this->render('furniture/edit.html.twig', [
            'furniture' => $furniture,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_furniture_delete', methods: ['POST'])]
    public function delete(Request $request, Furniture $furniture, EntityManagerInterface $entityManager, ActivityLogService $activityLogService): Response
    {
        if ($this->isCsrfTokenValid('delete'.$furniture->getId(), $request->getPayload()->getString('_token'))) {
            // Log furniture deletion before removing
            if ($this->getUser()) {
                $activityLogService->logActivity($this->getUser(), 'Furniture deletion');
            }
            
            $entityManager->remove($furniture);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_furniture_index', [], Response::HTTP_SEE_OTHER);
    }
}