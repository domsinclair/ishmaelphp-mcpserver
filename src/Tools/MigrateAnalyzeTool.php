<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\IshCliBridge;

/**
 * ish:migrate:analyze — Predict potential risks in pending migrations.
 */
final class MigrateAnalyzeTool implements Tool
{
    private ProjectContext $context;

    public function __construct(ProjectContext $context)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return 'ish:migrate:analyze';
    }

    public function getDescription(): string
    {
        return 'Analyze pending migrations for safety risks like data loss or long-running locks.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'module' => ['type' => ['string', 'null'], 'description' => 'Specific module to analyze.'],
            ],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type' => 'object',
            'required' => ['success', 'pending_migrations', 'risks'],
            'properties' => [
                'success' => ['type' => 'boolean'],
                'pending_migrations' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'module' => ['type' => 'string'],
                        ]
                    ]
                ],
                'risks' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'migration' => ['type' => 'string'],
                            'severity' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                            'type' => ['type' => 'string'],
                            'message' => ['type' => 'string'],
                            'suggestion' => ['type' => 'string'],
                        ]
                    ]
                ],
                'output' => ['type' => 'string'],
                'error' => ['type' => ['string', 'null']],
            ],
        ];
    }

    public function execute(array $input): array
    {
        $bridge = new IshCliBridge($this->context);
        $options = ['pretend' => true];
        if (isset($input['module'])) {
            $options['module'] = $input['module'];
        }

        $result = $bridge->execute('migrate', $options);

        if (!$result['success']) {
            return [
                'success' => false,
                'pending_migrations' => [],
                'risks' => [],
                'output' => $result['output'],
                'error' => $result['error'],
            ];
        }

        $pending = $this->parsePendingMigrations($result['output']);
        $risks = $this->analyzeRisks($pending);

        return [
            'success' => true,
            'pending_migrations' => $pending,
            'risks' => $risks,
            'output' => $result['output'],
            'error' => null,
        ];
    }

    /**
     * @return array<int, array{name: string, module: string, path: string}>
     */
    private function parsePendingMigrations(string $output): array
    {
        $pending = [];
        // Typically output looks like:
        // [Module] Name
        // SQL...
        
        $lines = explode("\n", $output);
        $currentModule = 'Core';
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '---') || str_starts_with($line, 'SQL:')) {
                continue;
            }

            if (preg_match('/^\[(.*?)\]\s+(.*)$/', $line, $matches)) {
                $module = $matches[1];
                $name = $matches[2];
                
                // Try to find the file path
                $path = $this->findMigrationPath($module, $name);
                
                $pending[] = [
                    'name' => $name,
                    'module' => $module,
                    'path' => $path,
                ];
            }
        }
        
        return $pending;
    }

    private function findMigrationPath(string $module, string $name): ?string
    {
        $root = $this->context->getRoot();
        if (!$root) return null;

        $searchPath = ($module === 'Core') 
            ? $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations'
            : $root . DIRECTORY_SEPARATOR . 'Modules' . DIRECTORY_SEPARATOR . $module . DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'Migrations';

        if (!is_dir($searchPath)) return null;

        foreach (glob($searchPath . DIRECTORY_SEPARATOR . '*' . $name . '.php') as $file) {
            return $file;
        }

        return null;
    }

    /**
     * @param array<int, array{name: string, module: string, path: string}> $pending
     * @return array<int, array{migration: string, severity: string, type: string, message: string, suggestion: string}>
     */
    private function analyzeRisks(array $pending): array
    {
        $risks = [];

        foreach ($pending as $m) {
            if (!$m['path'] || !is_file($m['path'])) {
                continue;
            }

            $content = file_get_contents($m['path']);
            if (!$content) continue;

            // 1. Data Loss: dropping tables or columns
            if (preg_match('/dropTable|dropColumn/i', $content)) {
                $risks[] = [
                    'migration' => $m['name'],
                    'severity' => 'high',
                    'type' => 'Data Loss',
                    'message' => 'Migration contains dropTable or dropColumn operations.',
                    'suggestion' => 'Ensure you have a backup and that the data is truly no longer needed. Consider renaming instead of dropping if unsure.',
                ];
            }

            // 2. Destructive changes: renaming
            if (preg_match('/renameTable|renameColumn/i', $content)) {
                $risks[] = [
                    'migration' => $m['name'],
                    'severity' => 'medium',
                    'type' => 'Breaking Change',
                    'message' => 'Migration renames a table or column.',
                    'suggestion' => 'Update all application code and queries that reference the old name.',
                ];
            }

            // 3. Performance: adding columns without nullable or default to potentially large tables
            // Note: This is a heuristic. We don't know the table size here.
            if (preg_match('/addColumn/i', $content) && !preg_match('/nullable|default/i', $content)) {
                $risks[] = [
                    'migration' => $m['name'],
                    'severity' => 'medium',
                    'type' => 'Performance / Locking',
                    'message' => 'Adding a non-nullable column without a default value.',
                    'suggestion' => 'For large tables, this can cause long-running table locks. Consider making it nullable first, then filling data, then adding the constraint.',
                ];
            }

            // 4. Raw SQL
            if (preg_match('/executeRaw|query\(/i', $content)) {
                $risks[] = [
                    'migration' => $m['name'],
                    'severity' => 'low',
                    'type' => 'Raw SQL',
                    'message' => 'Migration uses raw SQL.',
                    'suggestion' => 'Ensure the raw SQL is cross-database compatible if necessary.',
                ];
            }
        }

        return $risks;
    }
}
