<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Support;

/**
 * Parses the framework-map.md file into a structured JSON representation.
 */
final class FrameworkMapParser
{
    public static function parse(string $content): array
    {
        $zones = [];
        $currentZone = null;
        
        $lines = explode("\n", $content);
        foreach ($lines as $line) {
            $line = trim($line);
            if (str_contains($line, '`') && (str_starts_with($line, '- `') || str_starts_with($line, '`'))) {
                // Zone found: - `/app/Domain` or `/app/Domain`
                if ($currentZone) {
                    $zones[] = $currentZone;
                }
                $parts = explode('`', $line);
                $path = trim($parts[1] ?? '');
                $currentZone = ['path' => $path, 'intent' => '', 'guidance' => ''];
            } elseif ($currentZone && str_contains($line, '**Intent**:')) {
                $currentZone['intent'] = trim(explode('**Intent**:', $line)[1]);
            } elseif ($currentZone && str_contains($line, '**Guidance**:')) {
                $currentZone['guidance'] = trim(explode('**Guidance**:', $line)[1]);
            } elseif ($currentZone && str_contains($line, '**Reference**:')) {
                $currentZone['reference'] = trim(explode('**Reference**:', $line)[1]);
            }
        }
        
        if ($currentZone) {
            $zones[] = $currentZone;
        }

        return ['zones' => $zones];
    }
}
