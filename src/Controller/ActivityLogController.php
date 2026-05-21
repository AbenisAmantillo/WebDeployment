<?php

namespace App\Controller;

use App\Repository\ActivityLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/activity-logs')]
class ActivityLogController extends AbstractController
{
    #[Route('/', name: 'app_activity_log_index', methods: ['GET'])]
    public function index(ActivityLogRepository $activityLogRepository): Response
    {
        // Ensure only admins can access
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Show all logs including admin logs
        $logs = $activityLogRepository->findAllOrderedByDate();

        return $this->render('activity_log/index.html.twig', [
            'logs' => $logs,
        ]);
    }

    #[Route('/clear-all', name: 'app_activity_log_clear_all', methods: ['POST'])]
    public function clearAll(Request $request, ActivityLogRepository $activityLogRepository, EntityManagerInterface $entityManager): RedirectResponse
    {
        // Ensure only admins can access
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Verify CSRF token
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('clear_all_logs', $token)) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->redirectToRoute('app_activity_log_index');
        }

        // Delete all logs
        $deletedCount = $activityLogRepository->deleteAll();

        // Add flash message
        if ($deletedCount > 0) {
            $this->addFlash('success', "Successfully deleted {$deletedCount} activity log(s).");
        } else {
            $this->addFlash('info', 'No activity logs to delete.');
        }

        return $this->redirectToRoute('app_activity_log_index');
    }
}

