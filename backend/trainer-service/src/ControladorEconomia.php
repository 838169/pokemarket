<?php
declare(strict_types=1);
namespace TrainerService;

final class ControladorEconomia
{
    public function __construct(private GameEconomy $game, private SessionManager $session) {}
    private function uid(): int
    {
        $u = $this->session->user();
        if (!$u) {
            JsonResponse::error('Usuario no autenticado', 401);
        }
        return $u['id'];
    }
    private function respond(callable $f): never
    {
        try {
            JsonResponse::send(['success' => true] + $f($this->uid()));
        } catch (\InvalidArgumentException $e) {
            JsonResponse::error($e->getMessage(), 400);
        } catch (\DomainException $e) {
            JsonResponse::error($e->getMessage(), 409);
        } catch (PokemonCatalogUnavailable $e) {
            JsonResponse::error($e->getMessage(), 503);
        } catch (\Throwable $e) {
            JsonResponse::log('economy_failed', $e);
            JsonResponse::error('No se pudo completar la operación. Intenta de nuevo.', 500);
        }
    }
    public function welcome(): never
    {
        $this->respond(fn($uid) => ['cartera' => $this->game->completeWelcome($uid)]);
    }
    public function unlockScene(): never
    {
        $this->respond(function ($uid) {
            $d = JsonResponse::body();
            return $this->game->unlockScene(
                $uid,
                Input::text($d['escenario'] ?? '', 'Escenario', 20),
            );
        });
    }
    public function wallet(): never
    {
        $this->respond(fn($uid) => ['cartera' => $this->game->wallet($uid)]);
    }
    public function home(): never
    {
        $this->respond(fn($uid) => $this->game->home($uid));
    }
    public function trainerProfile(): never
    {
        $this->respond(fn($uid) => $this->game->trainerProfile($uid));
    }
    public function catalog(): never
    {
        $this->respond(function ($uid) {
            $w = $this->game->wallet($uid);
            $all = MarketCatalog::all();
            $counts = [];
            foreach (MarketCatalog::CATEGORIES as $key => $meta) {
                $group = array_filter($all, fn($p) => $p['categoria'] === $key);
                $meta['desde'] = min(array_column($group, 'precio'));
                $counts[$key] = $meta + ['id' => $key, 'total' => count($group)];
            }
            $category = Input::text($_GET['categoria'] ?? 'todos', 'Categoría', 30);
            $type = Input::text($_GET['tipo'] ?? '', 'Tipo', 20);
            $q = strtolower(Input::text($_GET['q'] ?? '', 'Búsqueda', 80));
            if ($category !== 'todos' && !isset(MarketCatalog::CATEGORIES[$category])) {
                throw new \InvalidArgumentException('Categoría no válida.');
            }
            $validTypes = [
                'normal',
                'fire',
                'water',
                'grass',
                'electric',
                'ice',
                'fighting',
                'poison',
                'ground',
                'flying',
                'psychic',
                'bug',
                'rock',
                'ghost',
                'dragon',
                'dark',
                'steel',
                'fairy',
            ];
            if ($type !== '' && !in_array($type, $validTypes, true)) {
                throw new \InvalidArgumentException('Tipo no válido.');
            }
            $list = array_values(
                array_filter(
                    $all,
                    fn($p) => ($category === 'todos' || $p['categoria'] === $category) &&
                        ($type === '' || in_array($type, $p['tipos'], true)) &&
                        ($q === '' ||
                            str_contains(strtolower($p['nombre']), $q) ||
                            (string) $p['id'] === $q),
                ),
            );
            $sort = Input::text($_GET['orden'] ?? 'calidad', 'Orden', 20);
            if (!in_array($sort, ['calidad', 'precio', 'poder'], true)) {
                throw new \InvalidArgumentException('Orden no válido.');
            }
            usort($list, static function ($a, $b) use ($sort) {
                if ($sort === 'precio') {
                    return $a['precio'] <=> $b['precio'] ?: $a['id'] <=> $b['id'];
                }
                if ($sort === 'poder') {
                    return $b['poder_base'] <=> $a['poder_base'] ?: $a['id'] <=> $b['id'];
                }
                $rank = fn($p) => !$p['sprite_animado_hd']
                    ? 2
                    : (str_contains($p['sprite_animado_hd'], '/champions/')
                        ? 0
                        : 1);
                return $rank($a) <=> $rank($b) ?: $a['id'] <=> $b['id'];
            });
            $rawOffset = $_GET['offset'] ?? '0';
            $rawLimit = $_GET['limit'] ?? '12';
            if (!is_scalar($rawOffset) || !preg_match('/^[0-9]{1,8}$/', (string) $rawOffset)) {
                throw new \InvalidArgumentException('Desplazamiento no válido.');
            }
            $offset = (int) $rawOffset;
            $limit = $this->integer($rawLimit);
            if ($limit > 48) {
                throw new \InvalidArgumentException('El límite máximo es 48.');
            }
            return [
                'pokemon' => array_map(
                    fn($pokemon) => $pokemon + [
                        'niveles' => PokemonProgression::milestones($pokemon),
                    ],
                    array_slice($list, $offset, $limit),
                ),
                'total' => count($list),
                'categorias' => array_values($counts),
                'cartera' => $w,
            ];
        });
    }
    public function purchase(): never
    {
        $this->respond(function ($uid) {
            $d = JsonResponse::body();
            return $this->game->purchase(
                $uid,
                $this->integer($d['pokemon_id'] ?? null),
                isset($d['habilidad']) ? Input::text($d['habilidad'], 'Habilidad', 80) : null,
            ) + [
                'cartera' => $this->game->wallet($uid),
            ];
        });
    }
    public function buyFood(): never
    {
        $this->respond(function ($uid) {
            $d = JsonResponse::body();
            $this->game->buyFood(
                $uid,
                (string) ($d['comida'] ?? ''),
                $this->integer($d['cantidad'] ?? 1),
            );
            return ['cartera' => $this->game->wallet($uid)];
        });
    }
    public function feed(): never
    {
        $this->respond(function ($uid) {
            $d = JsonResponse::body();
            $this->game->feed(
                $uid,
                $this->integer($d['pokemon_id'] ?? null),
                (string) ($d['comida'] ?? ''),
            );
            return $this->game->home($uid);
        });
    }
    public function habitat(): never
    {
        $this->respond(function ($uid) {
            $d = JsonResponse::body();
            $this->game->habitat(
                $uid,
                $this->integer($d['pokemon_id'] ?? null),
                (string) ($d['habitat'] ?? ''),
            );
            return $this->game->home($uid);
        });
    }
    public function ability(): never
    {
        $this->respond(function ($uid) {
            $d = JsonResponse::body();
            $this->game->equipAbility(
                $uid,
                $this->integer($d['pokemon_id'] ?? null),
                Input::text($d['habilidad'] ?? '', 'Habilidad', 80),
            );
            return $this->game->home($uid);
        });
    }
    public function moves(): never
    {
        $this->respond(function ($uid) {
            $d = JsonResponse::body();
            if (!is_array($d['movimientos'] ?? null)) {
                throw new \DomainException('Elige tus ataques.');
            }
            $this->game->equipMoves(
                $uid,
                $this->integer($d['pokemon_id'] ?? null),
                $d['movimientos'],
            );
            return $this->game->home($uid);
        });
    }
    private function integer(mixed $v): int
    {
        if (!is_scalar($v) || !preg_match('/^[1-9][0-9]*$/', (string) $v)) {
            throw new \DomainException('Selecciona una cantidad o un Pokémon válido.');
        }
        return (int) $v;
    }
}
