<?php

declare(strict_types=1);

namespace App\Form\Dto;

use App\Calcul\Electricite\Section;
use App\Entity\Equipement;
use App\Entity\Reseau;
use App\Enum\TypeCircuit;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Saisie d'un réseau (circuit) : paramètres, point de départ, équipements membres et liaisons.
 */
final class ReseauSaisie
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $nom = '';

    public TypeCircuit $type = TypeCircuit::Eclairage;

    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{1,3}(\.\d{1,2})?$/', message: 'The section must be a positive number.')]
    public string $section = '1.5';

    #[Assert\NotNull]
    #[Assert\Positive]
    public ?int $protection = 16;

    public ?Equipement $depart = null;

    /** @var list<Equipement> */
    public array $equipements = [];

    #[Assert\Range(min: 0, max: 100)]
    public ?int $reservePct = null;

    /** @var list<LiaisonSaisie> */
    #[Assert\Valid]
    public array $liaisons = [];

    public static function depuis(Reseau $reseau): self
    {
        $saisie = new self();
        $saisie->nom = $reseau->getNom();
        $saisie->type = $reseau->getType();
        $saisie->section = Section::texte(Section::centiemes($reseau->getSection()));
        $saisie->protection = $reseau->getProtection();
        $saisie->reservePct = $reseau->getReservePct();
        $saisie->equipements = array_values($reseau->getEquipements()->toArray());
        $parId = [];
        foreach ($reseau->getProjet()->getPieces() as $piece) {
            foreach ($piece->getEquipements() as $equipement) {
                if (null !== $equipement->getId()) {
                    $parId[$equipement->getId()] = $equipement;
                }
            }
        }
        $saisie->depart = null === $reseau->getDepart() ? null : ($parId[$reseau->getDepart()] ?? null);
        foreach ($reseau->getLiaisons() as $l) {
            if (!isset($parId[$l['equipement']], $parId[$l['parent']])) {
                continue;
            }
            $liaison = new LiaisonSaisie();
            $liaison->equipement = $parId[$l['equipement']];
            $liaison->parent = $parId[$l['parent']];
            $liaison->conducteurs = $l['conducteurs'];
            $saisie->liaisons[] = $liaison;
        }

        return $saisie;
    }

    #[Assert\Callback]
    public function validerLiaisons(ExecutionContextInterface $context): void
    {
        $membres = $this->equipements;
        if (null !== $this->depart) {
            $membres[] = $this->depart;
        }
        $vus = [];
        foreach ($this->liaisons as $i => $l) {
            if (null === $l->equipement || null === $l->parent) {
                continue;
            }
            if ($l->equipement === $l->parent) {
                $context->buildViolation('An element cannot be linked to itself.')->atPath('liaisons['.$i.'].parent')->addViolation();
            }
            if ($l->equipement === $this->depart) {
                $context->buildViolation('The starting point cannot have a parent.')->atPath('liaisons['.$i.'].equipement')->addViolation();
            }
            if (\in_array($l->equipement, $vus, true)) {
                $context->buildViolation('An element has only one parent.')->atPath('liaisons['.$i.'].equipement')->addViolation();
            }
            $vus[] = $l->equipement;
            if (!\in_array($l->equipement, $membres, true) || !\in_array($l->parent, $membres, true)) {
                $context->buildViolation('A link can only join elements of the circuit or its starting point.')->atPath('liaisons['.$i.'].equipement')->addViolation();
            }
        }
    }
}
