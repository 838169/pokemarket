<?php
declare(strict_types=1);
namespace TrainerService;

final class ControladorExploracion
{
    public function __construct(
        private ExplorationService $game,
        private SessionManager $session,
    ) {}
    public function handle(string $action): never
    {
        $u = $this->session->user();
        if (!$u) {
            JsonResponse::error('Usuario no autenticado', 401);
        }
        try {
            $result =
                $action === 'state'
                    ? $this->game->state($u['id'])
                    : $this->game->act($u['id'], $action, JsonResponse::body());
            JsonResponse::send(['success' => true] + $result);
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        } catch (\DomainException $e) {
            JsonResponse::error($e->getMessage(), 409);
        }
    }
}
