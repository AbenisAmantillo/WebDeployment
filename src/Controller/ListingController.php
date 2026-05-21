<?php

namespace App\Controller;

use App\Entity\Property;
use App\Repository\PropertyRepository;
use App\Repository\FurnitureRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request; 


final class ListingController extends AbstractController
{
    #[Route('/listing', name: 'app_listing')]
    public function index(
        Request $request,
        PropertyRepository $propertyRepository,
        FurnitureRepository $furnitureRepository
    ): Response
    {
        $statusFilter = $request->query->get('status');          // Pending, For Sale, For Rent
        $listingTypeFilter = $request->query->get('listingType'); // Property, Furniture, or empty

        $filters = [
            'status' => ['Available', 'Pending', 'Sold'],
            'listingType' => ['Property', 'Furniture'],
        ];

        // Determine what to fetch
        if ($listingTypeFilter === 'Furniture') {
            if ($statusFilter) {
                $items = $furnitureRepository->findBy(['status' => $statusFilter]);
            } else {
                $items = $furnitureRepository->findAll();
            }
        } elseif ($listingTypeFilter === 'Property') {
            if ($statusFilter) {
                $items = $propertyRepository->findBy(['status' => $statusFilter]);
            } else {
                $items = $propertyRepository->findAll();
            }
        } else {
            // All Listings: merge properties and furniture
            if ($statusFilter) {
                $properties = $propertyRepository->findBy(['status' => $statusFilter]);
                $furnitures = $furnitureRepository->findBy(['status' => $statusFilter]);
            } else {
                $properties = $propertyRepository->findAll();
                $furnitures = $furnitureRepository->findAll();
            }

            // Merge arrays
            $items = array_merge($properties, $furnitures);
        }

        return $this->render('listing/index.html.twig', [
            'properties' => $items, // contains both types if listingType is empty
            'filters' => $filters,
            'selectedStatus' => $statusFilter,
            'selectedListingType' => $listingTypeFilter,
        ]);
    }
}