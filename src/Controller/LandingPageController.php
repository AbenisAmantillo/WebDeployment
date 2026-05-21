<?php

namespace App\Controller;

use App\Repository\PropertyRepository;
use App\Repository\FurnitureRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class LandingPageController extends AbstractController
{
    #[Route('/', name: 'landing_page')]
    public function index(PropertyRepository $propertyRepository, FurnitureRepository $furnitureRepository): Response
    {
        // Redirect logged-in users to their appropriate dashboard
        if ($this->getUser()) {
            $roles = $this->getUser()->getRoles();

            if (in_array('ROLE_ADMIN', $roles) || in_array('ROLE_STAFF', $roles)) {
                return $this->redirectToRoute('app_dashboard');
            }

            if (in_array('ROLE_USER', $roles)) {
                return $this->redirectToRoute('app_client_dashboard');
            }
        }
        $stats = [
            ['number' => '2,500+', 'label' => 'Active listings'],
            ['number' => '15,000+', 'label' => 'Homes & rentals closed'],
            ['number' => '250+', 'label' => 'Licensed agents'],
            ['number' => '50+', 'label' => 'Neighborhoods served'],
        ];

        // Fetch available properties from the database
        // Try both 'available' and 'Available' status to handle case variations
        $availableProperties = $propertyRepository->findBy(['status' => 'available'], ['id' => 'DESC'], 3);
        if (empty($availableProperties)) {
            $availableProperties = $propertyRepository->findBy(['status' => 'Available'], ['id' => 'DESC'], 3);
        }
        
        // If still empty, get all properties (fallback)
        if (empty($availableProperties)) {
            $availableProperties = $propertyRepository->findBy([], ['id' => 'DESC'], 3);
        }

        $featuredProperties = $availableProperties;

        // Fetch available furnitures from the database
        // Try both 'available' and 'Available' status to handle case variations
        $availableFurnitures = $furnitureRepository->findBy(['status' => 'available'], ['id' => 'DESC'], 3);
        if (empty($availableFurnitures)) {
            $availableFurnitures = $furnitureRepository->findBy(['status' => 'Available'], ['id' => 'DESC'], 3);
        }

        // If still empty, get all furnitures (fallback)
        if (empty($availableFurnitures)) {
            $availableFurnitures = $furnitureRepository->findBy([], ['id' => 'DESC'], 3);
        }

        $services = [
            [
                'icon' => 'home',
                'title' => 'Buy & sell homes',
                'description' => 'From first showing to closing: offers, negotiations, and paperwork handled with clear, local market insight.',
            ],
            [
                'icon' => 'building',
                'title' => 'Rentals & leases',
                'description' => 'Match with rentals that fit your budget and timeline — or list your unit and find qualified tenants faster.',
            ],
            [
                'icon' => 'furniture',
                'title' => 'Furnish your space',
                'description' => 'Curated furniture and staging-ready pieces so your new place feels move-in ready.',
            ],
            [
                'icon' => 'chart',
                'title' => 'Valuations & insights',
                'description' => 'Pricing guidance and comparable sales data so you list competitively or bid with confidence.',
            ],
        ];

        $propertyFocus = [
            ['label' => 'Condominiums', 'blurb' => 'City living & amenities'],
            ['label' => 'Houses & lots', 'blurb' => 'Space for families & yards'],
            ['label' => 'Townhomes', 'blurb' => 'Low-maintenance living'],
            ['label' => 'Commercial', 'blurb' => 'Retail & office space'],
        ];

        $howItWorks = [
            ['step' => '1', 'title' => 'Search & shortlist', 'text' => 'Filter by location, price, and availability. Save favorites and compare listings side by side.'],
            ['step' => '2', 'title' => 'Tour & verify', 'text' => 'Schedule viewings, ask questions, and review details with our team so there are no surprises.'],
            ['step' => '3', 'title' => 'Close with confidence', 'text' => 'Offers, contracts, and handover — we stay with you through keys or lease signing.'],
        ];

        return $this->render('landing_page/index.html.twig', [
            'stats' => $stats,
            'featured_properties' => $featuredProperties,
            'featured_furnitures' => $availableFurnitures,
            'services' => $services,
            'property_focus' => $propertyFocus,
            'how_it_works' => $howItWorks,
        ]);
    }
}