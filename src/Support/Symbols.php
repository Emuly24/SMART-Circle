<?php

declare(strict_types=1);

namespace SmartCircle\Support;

/**
 * Symbol shortcuts the tutor can type in notes and on the board: \alpha → α, \deg → °, \therefore → ∴ ...
 * The same list lives in public/assets/js/board/board-core.js (SYMBOL_WORDS); keep them together.
 */
final class Symbols
{
    /** Longest names first is not needed: names end at a non-letter. */
    public const WORDS = [
        // Greek (lower case)
        'alpha' => 'α', 'beta' => 'β', 'gamma' => 'γ', 'delta' => 'δ', 'epsilon' => 'ε', 'theta' => 'θ', 'lambda' => 'λ',
        'mu' => 'μ', 'pi' => 'π', 'rho' => 'ρ', 'sigma' => 'σ', 'tau' => 'τ', 'phi' => 'φ', 'omega' => 'ω', 'eta' => 'η',
        // Greek (capitals)
        'Delta' => 'Δ', 'Sigma' => 'Σ', 'Omega' => 'Ω', 'Theta' => 'Θ', 'Phi' => 'Φ', 'Pi' => 'Π', 'Lambda' => 'Λ',
        // Maths
        'times' => '×', 'div' => '÷', 'pm' => '±', 'neq' => '≠', 'approx' => '≈', 'equiv' => '≡', 'leq' => '≤', 'geq' => '≥',
        'infty' => '∞', 'propto' => '∝', 'sqrt' => '√', 'cbrt' => '∛', 'deg' => '°', 'angle' => '∠', 'perp' => '⊥',
        'parallel' => '∥', 'therefore' => '∴', 'because' => '∵', 'triangle' => '△', 'sum' => 'Σ', 'cdot' => '·',
        'in' => '∈', 'notin' => '∉', 'subset' => '⊂', 'union' => '∪', 'intersect' => '∩', 'empty' => '∅', 'xi' => 'ξ',
        'half' => '½', 'third' => '⅓', 'quarter' => '¼', 'prime' => '′',
        // Arrows and chemistry
        'to' => '→', 'gets' => '←', 'rightarrow' => '→', 'leftarrow' => '←', 'eqm' => '⇌', 'implies' => '⇒', 'iff' => '⇔',
        'up' => '↑', 'down' => '↓', 'plus' => '⁺', 'minus' => '⁻', 'ohm' => 'Ω', 'micro' => 'µ', 'celsius' => '°C',
        // Grammar and marking
        'tick' => '✓', 'star' => '★', 'para' => '¶', 'section' => '§', 'ldquo' => '“', 'rdquo' => '”', 'dash' => '—',
    ];

    /** Groups shown in the notes editor's symbol picker. @return array<string, list<string>> */
    public static function picker(): array
    {
        return [
            'Greek' => ['α', 'β', 'γ', 'δ', 'Δ', 'ε', 'η', 'θ', 'λ', 'μ', 'π', 'ρ', 'σ', 'Σ', 'τ', 'φ', 'ω', 'Ω'],
            'Maths' => ['×', '÷', '±', '≠', '≈', '≡', '≤', '≥', '∞', '∝', '√', '∛', '°', '∠', '⊥', '∥', '∴', '∵', '△', '½', '¼', '¾', '′', '·'],
            'Sets' => ['∈', '∉', '⊂', '∪', '∩', '∅', 'ξ', 'ℕ', 'ℤ', 'ℝ'],
            'Arrows' => ['→', '←', '⇌', '⇒', '⇔', '↑', '↓', '↔'],
            'Science' => ['°C', 'Ω', 'µ', '⁺', '⁻', '²⁺', '²⁻', '³⁺', 'ΔH', '⊖', 'λ', 'ν', 'ħ'],
            'Writing' => ['“', '”', '‘', '’', '—', '–', '…', '¶', '§', '✓', '★', '•'],
        ];
    }

    /** Replaces \name with its symbol where the name is a known word. */
    public static function replace(string $text): string
    {
        if (!str_contains($text, '\\')) {
            return $text;
        }

        return (string) preg_replace_callback('/\\\\([A-Za-z]+)/', static fn (array $m): string => self::WORDS[$m[1]] ?? $m[0], $text);
    }
}
