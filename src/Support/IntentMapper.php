<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Support;

/**
 * Handles term normalization and intent detection based on the intent map.
 */
final class IntentMapper
{
    private array $intentMap;

    public function __construct(string $mapPath)
    {
        if (!file_exists($mapPath)) {
            throw new \RuntimeException("Intent map not found at: {$mapPath}");
        }

        $content = file_get_contents($mapPath);
        $this->intentMap = json_decode($content, true) ?: [];
    }

    /**
     * Detects intent from natural language input.
     * 
     * @param string $input
     * @return array{intent: ?string, confidence: float, terms: string[]}
     */
    public function detect(string $input): array
    {
        $input = strtolower($input);
        $detectedTerms = $this->detectGlossaryTerms($input);
        
        $bestIntent = null;
        $maxConfidence = 0.0;

        foreach ($this->intentMap['intents'] ?? [] as $id => $config) {
            $confidence = $this->calculateConfidence($input, $detectedTerms, $config);
            if ($confidence >= ($config['confidence_threshold'] ?? 0.6) && $confidence > $maxConfidence) {
                $maxConfidence = $confidence;
                $bestIntent = $id;
            }
        }

        return [
            'intent' => $bestIntent,
            'confidence' => $maxConfidence,
            'terms' => $detectedTerms
        ];
    }

    public function getClarificationRules(string $intentId): ?array
    {
        return $this->intentMap['clarification_rules'][$intentId] ?? null;
    }

    public function getBehaviourContract(string $intentId): ?array
    {
        return $this->intentMap['behaviour_contracts'][$intentId] ?? null;
    }

    public function getIntentDescription(string $intentId): ?string
    {
        return $this->intentMap['intents'][$intentId]['description'] ?? null;
    }

    private function detectGlossaryTerms(string $input): array
    {
        $detected = [];
        // Sort glossary keys to check more specific terms first if they were substrings, 
        // but here we want to ensure we check all.
        foreach ($this->intentMap['glossary'] ?? [] as $term => $config) {
            foreach ($config['synonyms'] ?? [] as $synonym) {
                // Use word boundaries for short synonyms to avoid false positives 
                // but keep str_contains for phrases.
                $synonymLower = strtolower($synonym);
                if (strlen($synonymLower) < 4) {
                    if (preg_match('/\b' . preg_quote($synonymLower, '/') . '\b/', $input)) {
                        $detected[] = $term;
                        break;
                    }
                } else {
                    if (str_contains($input, $synonymLower)) {
                        $detected[] = $term;
                        break;
                    }
                }
            }
        }
        return array_unique($detected);
    }

    private function calculateConfidence(string $input, array $detectedTerms, array $config): float
    {
        $requiredTerms = (array)($config['required_terms'] ?? []);
        $supportingTerms = (array)($config['supporting_terms'] ?? []);

        // Check required terms
        $matchedRequired = array_intersect($requiredTerms, $detectedTerms);
        if (count($matchedRequired) < count($requiredTerms)) {
            return 0.0;
        }

        // Match supporting terms from input OR if a required term also happens to be a supporting term
        $matchedSupporting = 0;
        foreach ($supportingTerms as $term) {
            $termLower = strtolower((string)$term);
            // Search in raw input or in detected normalized terms
            if (str_contains($input, $termLower) || in_array($termLower, $detectedTerms)) {
                $matchedSupporting++;
            }
        }

        $baseScore = 0.5; // Start at 0.5 if all required terms match
        
        // If there are supporting terms, they can add up to 0.5 to the score.
        // If no supporting terms defined, we give full bonus for required terms match.
        $supportingBonus = !empty($supportingTerms) ? ($matchedSupporting / count($supportingTerms)) * 0.5 : 0.5;
        
        $confidence = min(1.0, $baseScore + $supportingBonus);

        // Debug (only if we could see output)
        // fwrite(STDERR, "Intent: {$config['description']}, Confidence: {$confidence}, Matched Supporting: {$matchedSupporting}\n");

        return (float)$confidence;
    }
}
