<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Models\EventModel;
use App\Models\ReminderModel;
use App\Services\EventInput;
use App\Services\EventPresenter;
use App\Services\Recurrence;
use DateTimeImmutable;

/**
 * API JSON des événements. Toutes les opérations sont limitées aux événements
 * de l'utilisateur connecté (filtre userID dans chaque requête SQL).
 */
class EventController extends BaseController
{
    /** Amplitude maximale d'une plage demandée (un mois affiché = 42 jours). */
    private const MAX_RANGE_DAYS = 100;

    /**
     * GET /api/events?from=YYYY-MM-DD&to=YYYY-MM-DD — occurrences de la période.
     */
    public function index(): void
    {
        $this->requireAuthApi();

        $from = $this->dateParam($_GET['from'] ?? null);
        $to   = $this->dateParam($_GET['to'] ?? null);

        if ($from === null || $to === null || $to < $from) {
            $this->json(['error' => 'Période invalide (paramètres from et to au format AAAA-MM-JJ).'], 400);
        }
        if ((new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days > self::MAX_RANGE_DAYS) {
            $this->json(['error' => 'Période trop longue (' . self::MAX_RANGE_DAYS . ' jours maximum).'], 400);
        }

        $rows      = (new EventModel())->listInRange($this->userId(), $from, $to);
        $reminders = (new ReminderModel())->offsetsByEvent(array_map(fn ($r) => (int) $r['id'], $rows));

        $this->json([
            'from'   => $from,
            'to'     => $to,
            'events' => EventPresenter::occurrences($rows, $reminders, $from, $to),
        ]);
    }

    /**
     * GET /api/events/search?q=… — recherche dans le titre, la description et le lieu.
     * Pour une série, la date retournée est la prochaine occurrence.
     */
    public function search(): void
    {
        $this->requireAuthApi();

        $query = trim((string) ($_GET['q'] ?? ''));
        if (mb_strlen($query) < 2 || mb_strlen($query) > 100) {
            $this->json(['error' => 'La recherche doit contenir entre 2 et 100 caractères.'], 400);
        }

        $rows      = (new EventModel())->search($this->userId(), $query);
        $reminders = (new ReminderModel())->offsetsByEvent(array_map(fn ($r) => (int) $r['id'], $rows));
        $today     = (new DateTimeImmutable())->format('Y-m-d');

        $results = [];
        foreach ($rows as $row) {
            $date = $row['dateEvent'];
            if ($row['recurrence'] !== 'none') {
                $date = Recurrence::first($row['dateEvent'], $row['recurrence'], $row['recurrence_until'] ?: null, $today)
                    ?? ($row['recurrence_until'] ?: $row['dateEvent']);
            }
            $results[] = EventPresenter::event($row, $reminders[(int) $row['id']] ?? [], $date);
        }

        // À venir d'abord (du plus proche au plus lointain), puis passés (du plus récent au plus ancien)
        usort($results, static function (array $a, array $b) use ($today): int {
            $aFuture = $a['date'] >= $today;
            $bFuture = $b['date'] >= $today;
            if ($aFuture !== $bFuture) {
                return $aFuture ? -1 : 1;
            }
            return $aFuture ? $a['date'] <=> $b['date'] : $b['date'] <=> $a['date'];
        });

        $this->json(['query' => $query, 'results' => $results]);
    }

    public function store(): void
    {
        $this->requireAuthApi();
        $this->requireCsrfApi();

        ['data' => $data, 'errors' => $errors] = EventInput::fromRequest($_POST);
        if ($errors !== []) {
            $this->validationError($errors);
        }

        $model = new EventModel();
        $id    = $model->create($this->userId(), $data, new DateTimeImmutable());

        $this->json([
            'success' => true,
            'message' => 'Événement ajouté.',
            'event'   => $this->present($model, $id),
        ], 201);
    }

    public function update(): void
    {
        $this->requireAuthApi();
        $this->requireCsrfApi();

        $eventId = (int) ($_POST['eventId'] ?? 0);
        if ($eventId <= 0) {
            $this->json(['error' => 'Événement non précisé.'], 400);
        }

        ['data' => $data, 'errors' => $errors] = EventInput::fromRequest($_POST);
        if ($errors !== []) {
            $this->validationError($errors);
        }

        $model = new EventModel();
        if (!$model->update($eventId, $this->userId(), $data, new DateTimeImmutable())) {
            $this->json(['error' => 'Événement introuvable. Il a peut-être été supprimé.'], 404);
        }

        $this->json([
            'success' => true,
            'message' => 'Événement modifié.',
            'event'   => $this->present($model, $eventId),
        ]);
    }

    public function delete(): void
    {
        $this->requireAuthApi();
        $this->requireCsrfApi();

        // « eventID » : nom historique du paramètre, conservé pour compatibilité
        $eventId = (int) ($_POST['eventId'] ?? $_POST['eventID'] ?? 0);
        if ($eventId <= 0) {
            $this->json(['error' => 'Événement non précisé.'], 400);
        }

        if (!(new EventModel())->delete($eventId, $this->userId())) {
            $this->json(['error' => 'Événement introuvable. Il a peut-être déjà été supprimé.'], 404);
        }

        $this->json(['success' => true, 'message' => 'Événement supprimé.']);
    }

    private function present(EventModel $model, int $id): ?array
    {
        $row = $model->findForUser($id, $this->userId());
        if ($row === null) {
            return null;
        }
        $reminders = (new ReminderModel())->offsetsByEvent([$id]);
        return EventPresenter::event($row, $reminders[$id] ?? []);
    }

    private function validationError(array $errors): void
    {
        $this->json([
            'error'  => count($errors) > 1
                ? 'Veuillez corriger les ' . count($errors) . ' champs signalés.'
                : 'Veuillez corriger le champ signalé.',
            'fields' => $errors,
        ], 422);
    }

    private function dateParam(mixed $value): ?string
    {
        if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return null;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $value : null;
    }
}
