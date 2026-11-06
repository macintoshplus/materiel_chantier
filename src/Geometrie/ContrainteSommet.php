<?php

declare(strict_types=1);

namespace App\Geometrie;

/**
 * Contrainte définissant un sommet d'une surface. Les angles sont en dixièmes de degré (entiers).
 *
 * `Angle` : le sommet est à `distance` de `depuis`. Avec `reference` (sommet), l'angle intérieur, en `depuis`,
 * entre l'arête vers `reference` et la nouvelle arête (polygone anti-horaire) ; sans `reference`, l'angle de la
 * nouvelle arête avec l'horizontale (`u`), dans le sens anti-horaire.
 */
final readonly class ContrainteSommet
{
    public function __construct(
        public string $id,
        public TypeContrainte $type,
        public ?string $depuis = null,
        public ?string $reference = null,
        public ?string $direction = null,
        public ?int $distance = null,
        public ?int $angle = null,
        public ?int $u = null,
        public ?int $v = null,
        public ?string $vers = null,
    ) {
    }

    public static function fixe(string $id, int $u, int $v): self
    {
        return new self($id, TypeContrainte::Fixe, u: $u, v: $v);
    }

    public static function distance(string $id, string $depuis, string $direction, int $distance): self
    {
        return new self($id, TypeContrainte::Distance, depuis: $depuis, direction: $direction, distance: $distance);
    }

    public static function angle(string $id, string $depuis, ?string $reference, int $angle, int $distance): self
    {
        return new self($id, TypeContrainte::Angle, depuis: $depuis, reference: $reference, distance: $distance, angle: $angle);
    }

    public static function controle(string $id, string $depuis, string $vers, int $distance): self
    {
        return new self($id, TypeContrainte::Controle, depuis: $depuis, distance: $distance, vers: $vers);
    }

    /**
     * @return array<string, mixed>
     */
    public function versTableau(): array
    {
        return array_filter([
            'id' => $this->id,
            'type' => $this->type->value,
            'depuis' => $this->depuis,
            'reference' => $this->reference,
            'direction' => $this->direction,
            'distance' => $this->distance,
            'angle' => $this->angle,
            'u' => $this->u,
            'v' => $this->v,
            'vers' => $this->vers,
        ], static fn (mixed $v): bool => null !== $v);
    }

    /**
     * @param array<mixed> $t
     *
     * @throws ErreurGeometrique constraint_malformed
     */
    public static function depuisTableau(array $t): self
    {
        $id = $t['id'] ?? null;
        $type = \is_string($t['type'] ?? null) ? TypeContrainte::tryFrom($t['type']) : null;
        if (!\is_string($id) || null === $type) {
            throw new ErreurGeometrique('constraint_malformed', ['vertex' => \is_string($id) ? $id : '?'], \is_string($id) ? $id : null);
        }
        $texte = static fn (string $k): ?string => \is_string($t[$k] ?? null) ? $t[$k] : null;
        $entier = static fn (string $k): ?int => \is_int($t[$k] ?? null) ? $t[$k] : null;

        return new self($id, $type, $texte('depuis'), $texte('reference'), $texte('direction'), $entier('distance'), $entier('angle'), $entier('u'), $entier('v'), $texte('vers'));
    }
}
