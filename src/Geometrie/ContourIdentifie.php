<?php

declare(strict_types=1);

namespace App\Geometrie;

/**
 * Polygone dont chaque sommet porte un identifiant stable (unique dans la surface) : les jonctions
 * référencent ces identifiants, jamais des index.
 */
final readonly class ContourIdentifie
{
    /**
     * @param list<Sommet> $sommets
     */
    public function __construct(public array $sommets)
    {
    }

    /**
     * @param list<array{id: string, u: int, v: int}> $tableau
     */
    public static function depuisTableau(array $tableau): self
    {
        return new self(array_map(static fn (array $s): Sommet => new Sommet($s['id'], new Point2D($s['u'], $s['v'])), $tableau));
    }

    /**
     * @return list<array{id: string, u: int, v: int}>
     */
    public function versTableau(): array
    {
        return array_map(static fn (Sommet $s): array => ['id' => $s->id, 'u' => $s->point->x, 'v' => $s->point->y], $this->sommets);
    }

    public function polygone(): Polygone
    {
        return new Polygone(array_map(static fn (Sommet $s): Point2D => $s->point, $this->sommets));
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_map(static fn (Sommet $s): string => $s->id, $this->sommets);
    }

    public function a(string $id): bool
    {
        return \in_array($id, $this->ids(), true);
    }

    public function point(string $id): Point2D
    {
        foreach ($this->sommets as $s) {
            if ($s->id === $id) {
                return $s->point;
            }
        }
        throw new \InvalidArgumentException(sprintf('Unknown vertex "%s".', $id));
    }

    public function index(string $id): int
    {
        foreach ($this->sommets as $i => $s) {
            if ($s->id === $id) {
                return $i;
            }
        }
        throw new \InvalidArgumentException(sprintf('Unknown vertex "%s".', $id));
    }

    /**
     * Arêtes consécutives (le dernier sommet rejoint le premier).
     *
     * @return list<array{string, string}>
     */
    public function aretes(): array
    {
        $n = \count($this->sommets);
        $aretes = [];
        for ($i = 0; $i < $n; ++$i) {
            $aretes[] = [$this->sommets[$i]->id, $this->sommets[($i + 1) % $n]->id];
        }

        return $aretes;
    }

    /**
     * Segment de l'arête entre deux sommets (dans l'ordre demandé).
     */
    public function segment(string $debut, string $fin): Segment
    {
        return new Segment($this->point($debut), $this->point($fin));
    }
}
