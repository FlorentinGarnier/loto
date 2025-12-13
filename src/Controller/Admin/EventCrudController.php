<?php
namespace App\Controller\Admin;

use App\Entity\Event;
use App\Form\EventType;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/events')]
final class EventCrudController extends AbstractController
{
    public function __construct(
        private readonly EventRepository $eventRepo,
        private readonly EntityManagerInterface $em,
    ){}

    #[Route('', name: 'admin_event_index')]
    public function index(): Response
    {
        $events = $this->eventRepo->findBy([], ['date' => 'DESC']);
        return $this->render('admin/event/index.html.twig', [
            'events' => $events,
        ]);
    }

    #[Route('/new', name: 'admin_event_new')]
    public function new(Request $request): Response
    {
        $event = new Event();
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($event);
            $this->em->flush();
            $this->addFlash('success', 'Événement créé');
            return $this->redirectToRoute('admin_event_index');
        }
        return $this->render('admin/event/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_event_show', requirements: ['id' => '\\d+'])]
    public function show(Event $event): Response
    {
        return $this->render('admin/event/show.html.twig', [
            'event' => $event,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_event_edit', requirements: ['id' => '\\d+'])]
    public function edit(Event $event, Request $request): Response
    {
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Événement mis à jour');
            return $this->redirectToRoute('admin_event_index');
        }
        return $this->render('admin/event/edit.html.twig', [
            'form' => $form,
            'event' => $event,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_event_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function delete(Event $event, Request $request): Response
    {
        // Simple CSRF guard
        $token = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete_event_'.$event->getId(), $token)) {
            $this->em->remove($event);
            $this->em->flush();
            $this->addFlash('success', 'Événement supprimé');
        } else {
            $this->addFlash('error', 'Jeton CSRF invalide');
        }
        return $this->redirectToRoute('admin_event_index');
    }
}
