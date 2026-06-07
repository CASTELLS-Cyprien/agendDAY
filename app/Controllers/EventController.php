<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Models\EventModel;

class EventController extends BaseController
{
    private const MAX_TITLE_LENGTH       = 200;
    private const MAX_DESCRIPTION_LENGTH = 2000;

    public function getAll(): void
    {
        $this->requireAuthApi();
        $events = (new EventModel())->getAllByUser((int) $_SESSION['id']);
        $this->json($events);
    }

    public function store(): void
    {
        $this->requireAuthApi();

        $title       = trim($_POST['title'] ?? '');
        $time        = trim($_POST['time'] ?? '');
        $date        = trim($_POST['date'] ?? '');
        $description = trim($_POST['descriptionEvent'] ?? '');

        $errors = $this->validate($title, $time, $date, $description);
        if (!empty($errors)) {
            $this->json(['error' => implode(' ', $errors)], 400);
        }

        try {
            $id = (new EventModel())->create(
                (int) $_SESSION['id'],
                $title,
                $time,
                $date,
                $description
            );
            $this->json(['success' => true, 'id' => $id, 'message' => 'Événement ajouté avec succès.']);
        } catch (\PDOException $e) {
            error_log('Erreur ajout événement : ' . $e->getMessage());
            $this->json(['error' => 'Erreur lors de l\'ajout de l\'événement.'], 500);
        }
    }

    public function update(): void
    {
        $this->requireAuthApi();

        $eventId     = (int) ($_POST['eventId'] ?? 0);
        $title       = trim($_POST['title'] ?? '');
        $time        = trim($_POST['time'] ?? '');
        $date        = trim($_POST['date'] ?? '');
        $description = trim($_POST['descriptionEvent'] ?? '');

        $errors = $this->validate($title, $time, $date, $description);
        if (!empty($errors)) {
            $this->json(['error' => implode(' ', $errors)], 400);
        }

        $model = new EventModel();

        if ($model->findByIdAndUser($eventId, (int) $_SESSION['id']) === null) {
            $this->json(['error' => 'Événement introuvable ou accès non autorisé.'], 403);
        }

        try {
            $model->update($eventId, (int) $_SESSION['id'], $title, $time, $date, $description);
            $this->json(['success' => true, 'message' => 'Événement modifié avec succès.']);
        } catch (\PDOException $e) {
            error_log('Erreur modification événement : ' . $e->getMessage());
            $this->json(['error' => 'Erreur lors de la mise à jour.'], 500);
        }
    }

    public function delete(): void
    {
        $this->requireAuthApi();

        $eventId = (int) ($_POST['eventID'] ?? 0);
        if ($eventId <= 0) {
            $this->json(['error' => 'ID de l\'événement manquant.'], 400);
        }

        try {
            $deleted = (new EventModel())->delete($eventId, (int) $_SESSION['id']);
            if ($deleted) {
                $this->json(['success' => true]);
            } else {
                $this->json(['error' => 'Événement non trouvé ou non autorisé.'], 403);
            }
        } catch (\PDOException $e) {
            error_log('Erreur suppression événement : ' . $e->getMessage());
            $this->json(['error' => 'Erreur lors de la suppression.'], 500);
        }
    }

    public function getByDate(): void
    {
        $this->requireAuthApi();

        $date = trim($_POST['date'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->json(['error' => 'Format de date invalide.'], 400);
        }

        try {
            $events = (new EventModel())->getByDate((int) $_SESSION['id'], $date);
            $this->json($events);
        } catch (\PDOException $e) {
            error_log('Erreur récupération événements : ' . $e->getMessage());
            $this->json(['error' => 'Erreur lors de la récupération des événements.'], 500);
        }
    }

    private function validate(
        string $title,
        string $time,
        string $date,
        string $description
    ): array {
        $errors = [];

        if ($title === '') {
            $errors[] = 'Le titre est requis.';
        } elseif (\strlen($title) > self::MAX_TITLE_LENGTH) {
            $errors[] = 'Le titre est trop long (' . self::MAX_TITLE_LENGTH . ' caractères max).';
        }

        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) {
            $errors[] = 'Heure invalide (format HH:MM requis).';
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $errors[] = 'Date invalide (format YYYY-MM-DD requis).';
        }

        if (\strlen($description) > self::MAX_DESCRIPTION_LENGTH) {
            $errors[] = 'La description est trop longue (' . self::MAX_DESCRIPTION_LENGTH . ' caractères max).';
        }

        return $errors;
    }
}
