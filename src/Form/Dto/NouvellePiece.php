<?php

declare(strict_types=1);

namespace App\Form\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * « Start from scratch » : rectangle rapide (largeur, longueur) ou tableau de sommets.
 */
final class NouvellePiece
{
    public const RECTANGLE = 'rectangle';
    public const SOMMETS = 'sommets';

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $nom = '';

    #[Assert\NotNull]
    #[Assert\Positive]
    public ?int $hauteurSousPlafond = 2500;

    #[Assert\Choice(choices: [self::RECTANGLE, self::SOMMETS])]
    public string $mode = self::RECTANGLE;

    #[Assert\Positive]
    public ?int $largeur = 4000;

    #[Assert\Positive]
    public ?int $longueur = 3000;

    /** @var array<int, array{x: int|null, y: int|null}> saisie brute du formulaire, les cases vides valent null */
    public array $formeSol = [['x' => 0, 'y' => 0], ['x' => 4000, 'y' => 0], ['x' => 4000, 'y' => 3000], ['x' => 0, 'y' => 3000]];

    #[Assert\Callback]
    public function validerMode(ExecutionContextInterface $context): void
    {
        if (self::RECTANGLE === $this->mode) {
            foreach (['largeur' => $this->largeur, 'longueur' => $this->longueur] as $champ => $valeur) {
                if (null === $valeur) {
                    $context->buildViolation('This value should not be blank.')->setTranslationDomain('validators')->atPath($champ)->addViolation();
                }
            }
        } elseif (\count($this->formeSol) < 3) {
            $context->buildViolation('The floor outline needs at least 3 vertices.')->atPath('formeSol')->addViolation();
        }
    }

    /**
     * @return list<array{x: int, y: int}>
     */
    public function contour(): array
    {
        if (self::RECTANGLE === $this->mode) {
            $l = $this->largeur ?? 0;
            $w = $this->longueur ?? 0;

            return [['x' => 0, 'y' => 0], ['x' => $l, 'y' => 0], ['x' => $l, 'y' => $w], ['x' => 0, 'y' => $w]];
        }

        return array_map(
            static fn (array $p): array => ['x' => (int) ($p['x'] ?? 0), 'y' => (int) ($p['y'] ?? 0)],
            array_values($this->formeSol),
        );
    }
}
