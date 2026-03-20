<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Tools;

use Ishmael\McpServer\Contracts\Tool;
use Ishmael\McpServer\Project\ProjectContext;
use Ishmael\McpServer\Support\ComposerBridge;
use Ishmael\McpServer\Support\IshCliBridge;
use Ishmael\McpServer\Support\RegistryToolHelper;
use ZipArchive;

final class FeaturePackAcquireTool implements Tool
{
    private const DEFAULT_REGISTRY_BASE = 'https://vtl-ishmael-registry.test';
    private const USER_AGENT            = 'Ishmael-MCP-Server/1.0 (automated)';

    private ?ProjectContext $context;

    public function __construct(?ProjectContext $context = null)
    {
        $this->context = $context;
    }

    public function getName(): string
    {
        return 'ish:featurePack:acquire';
    }

    public function getDescription(): string
    {
        return 'Full end-to-end acquisition of a feature pack from the registry: download → unpack → composer require → migrate → integrate. Dry-run by default; set dry_run=false to apply all steps.';
    }

    public function getInputSchema(): array
    {
        return [
            'type'                 => 'object',
            'additionalProperties' => false,
            'required'             => ['slug', 'version'],
            'properties'           => [
                'slug' => [
                    'type'        => 'string',
                    'description' => 'Feature pack slug in the form {vendor}/{pack} (e.g. vtl-software/upload).',
                ],
                'version' => [
                    'type'        => 'string',
                    'description' => 'Semantic version to acquire (e.g. 1.0.0).',
                ],
                'dry_run' => [
                    'type'        => 'boolean',
                    'description' => 'When true (default) downloads and inspects the pack but makes no permanent changes. Set false to apply all steps.',
                ],
                'registry_url' => [
                    'type'        => 'string',
                    'description' => 'Optional registry base URL override.',
                ],
                'insecure' => [
                    'type'        => 'boolean',
                    'description' => 'Skip SSL certificate verification. Development only.',
                ],
                'no_scripts' => [
                    'type'        => 'boolean',
                    'description' => 'Pass --no-scripts to composer require.',
                ],
                'run_migrate' => [
                    'type'        => 'boolean',
                    'description' => 'Run database migrations after install. Defaults to true when the pack contains a migrations directory.',
                ],
            ],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type'       => 'object',
            'required'   => ['success', 'dry_run', 'steps'],
            'properties' => [
                'success' => ['type' => 'boolean'],
                'dry_run' => ['type' => 'boolean'],
                'steps'   => [
                    'type'  => 'array',
                    'items' => [
                        'type'       => 'object',
                        'required'   => ['step', 'success'],
                        'properties' => [
                            'step'    => ['type' => 'string'],
                            'success' => ['type' => 'boolean'],
                            'skipped' => ['type' => 'boolean'],
                            'detail'  => ['type' => 'string'],
                            'error'   => ['type' => 'string'],
                        ],
                    ],
                ],
                'module_name'      => ['type' => 'string'],
                'composer_package' => ['type' => 'string'],
                'has_migrations'   => ['type' => 'boolean'],
                'extract_path'     => ['type' => 'string'],
                'error'            => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $input): array
    {
        $slug    = trim($input['slug'] ?? '');
        $version = trim($input['version'] ?? '');
        $dryRun  = (bool)($input['dry_run'] ?? true);

        if ($slug === '' || $version === '') {
            return ['success' => false, 'dry_run' => $dryRun, 'steps' => [], 'error' => 'slug and version are required.'];
        }

        $parts = explode('/', $slug, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            return ['success' => false, 'dry_run' => $dryRun, 'steps' => [], 'error' => 'slug must be in the form {vendor}/{pack}.'];
        }

        [$vendor, $pack] = $parts;

        $steps   = [];
        $overall = true;

        // ------------------------------------------------------------------ //
        // Step 1 — Download                                                   //
        // ------------------------------------------------------------------ //
        $zipPath = $this->download($vendor, $pack, $version, $input, $steps);

        if ($zipPath === null) {
            return ['success' => false, 'dry_run' => $dryRun, 'steps' => $steps, 'error' => 'Download failed — see steps for detail.'];
        }

        // ------------------------------------------------------------------ //
        // Step 2 — Unpack / Inspect                                           //
        // ------------------------------------------------------------------ //
        $unpackResult = $this->unpack($zipPath, $dryRun, $steps);

        if (!$unpackResult['success']) {
            if (!$dryRun) {
                @unlink($zipPath);
            }
            return ['success' => false, 'dry_run' => $dryRun, 'steps' => $steps, 'error' => 'Unpack failed — see steps for detail.'];
        }

        $moduleName      = $unpackResult['module_name'];
        $composerPackage = $unpackResult['composer_package'];
        $composerVersion = $unpackResult['composer_version'];
        $hasMigrations   = $unpackResult['has_migrations'];
        $extractPath     = $unpackResult['extract_path'] ?? null;

        // If dry run we stop here and report what would happen next.
        if ($dryRun) {
            $runMigrate = (bool)($input['run_migrate'] ?? $hasMigrations);

            $steps[] = $this->stepSkipped(
                'composer_require',
                "Would run: composer require $composerPackage" . ($composerVersion !== '' ? ":^$composerVersion" : '')
            );
            $steps[] = $this->stepSkipped(
                'migrate',
                $hasMigrations
                    ? "Would run: ish migrate --module=$moduleName"
                    : 'No migrations directory detected in pack; migrate step would be skipped.'
            );
            $steps[] = $this->stepSkipped(
                'integrate',
                "Would register $moduleName in modules.php, merge config, add route stub."
            );

            @unlink($zipPath);

            return [
                'success'          => true,
                'dry_run'          => true,
                'steps'            => $steps,
                'module_name'      => $moduleName,
                'composer_package' => $composerPackage,
                'has_migrations'   => $hasMigrations,
            ];
        }

        // ------------------------------------------------------------------ //
        // Step 3 — composer require                                           //
        // ------------------------------------------------------------------ //
        if ($composerPackage !== '') {
            $composerFlags = [];
            if (!empty($input['no_scripts'])) {
                $composerFlags['no-scripts'] = true;
            }

            $packageSpec = $composerPackage . ($composerVersion !== '' ? ':^' . $composerVersion : '');
            $bridge      = new ComposerBridge($this->context);
            $result      = $bridge->execute('require', [$packageSpec], $composerFlags);

            $stepOk  = $result['success'];
            $overall = $overall && $stepOk;
            $steps[] = [
                'step'    => 'composer_require',
                'success' => $stepOk,
                'skipped' => false,
                'detail'  => $result['output'],
                'error'   => $result['error'] ?? '',
            ];

            if (!$stepOk) {
                @unlink($zipPath);
                return [
                    'success'          => false,
                    'dry_run'          => false,
                    'steps'            => $steps,
                    'module_name'      => $moduleName,
                    'composer_package' => $composerPackage,
                    'has_migrations'   => $hasMigrations,
                    'error'            => 'composer require failed — see steps for detail.',
                ];
            }
        } else {
            $steps[] = $this->stepSkipped('composer_require', 'No composer package name found in pack; skipping composer require.');
        }

        // ------------------------------------------------------------------ //
        // Step 4 — Migrate                                                    //
        // ------------------------------------------------------------------ //
        $runMigrate = (bool)($input['run_migrate'] ?? $hasMigrations);

        if ($runMigrate && $hasMigrations) {
            $cliBridge = new IshCliBridge($this->context);
            $result    = $cliBridge->execute('migrate', ['module' => $moduleName]);
            $stepOk    = $result['success'];
            $overall   = $overall && $stepOk;
            $steps[]   = [
                'step'    => 'migrate',
                'success' => $stepOk,
                'skipped' => false,
                'detail'  => $result['output'],
                'error'   => $result['error'] ?? '',
            ];
        } else {
            $steps[] = $this->stepSkipped('migrate', $hasMigrations ? 'run_migrate=false; skipped by caller.' : 'No migrations directory in pack.');
        }

        // ------------------------------------------------------------------ //
        // Step 5 — Integrate                                                  //
        // ------------------------------------------------------------------ //
        $integrateTool = new FeaturePackIntegrateTool($this->context);
        $intResult     = $integrateTool->execute([
            'packs'   => [$slug],
            'dryRun'  => false,
            'confirm' => true,
            'options' => [
                'registerModules' => true,
                'mergeConfig'     => true,
                'publishAssets'   => false,
                'addRoutes'       => true,
            ],
        ]);

        $stepOk  = $intResult['executed'] && empty($intResult['conflicts']);
        $overall = $overall && $stepOk;
        $steps[] = [
            'step'    => 'integrate',
            'success' => $stepOk,
            'skipped' => false,
            'detail'  => implode('; ', $intResult['messages']),
            'error'   => !$stepOk ? implode('; ', array_column($intResult['conflicts'], 'reason')) : '',
        ];

        // Clean up the temporary ZIP now that everything succeeded.
        @unlink($zipPath);

        return [
            'success'          => $overall,
            'dry_run'          => false,
            'steps'            => $steps,
            'module_name'      => $moduleName,
            'composer_package' => $composerPackage,
            'has_migrations'   => $hasMigrations,
            'extract_path'     => $extractPath,
        ];
    }

    // ---------------------------------------------------------------------- //
    // Private helpers                                                          //
    // ---------------------------------------------------------------------- //

    /**
     * Download the ZIP from the registry. Returns the local file path on
     * success, or null on failure (appends a step entry either way).
     *
     * @param array<string,mixed> $input
     * @param array<int,array>    $steps
     */
    private function download(string $vendor, string $pack, string $version, array $input, array &$steps): ?string
    {
        $baseUrl     = $this->resolveBaseUrl($input['registry_url'] ?? null);
        $downloadUrl = rtrim($baseUrl, '/') . "/registry/download/{$vendor}/{$pack}/{$version}.zip";
        $insecure    = (bool)($input['insecure'] ?? (getenv('ISH_MCP_INSECURE_TLS') === '1'));

        $host       = parse_url($downloadUrl, PHP_URL_HOST);
        $isLocalDev = $host && (
            str_ends_with($host, '.test') ||
            str_ends_with($host, '.local') ||
            str_ends_with($host, '.localhost') ||
            $host === 'localhost'
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $downloadUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);

        if ($insecure || $isLocalDev) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        $body        = curl_exec($ch);
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $curlError   = curl_error($ch);
        curl_close($ch);

        if ($body === false || $curlError !== '') {
            $steps[] = ['step' => 'download', 'success' => false, 'skipped' => false, 'detail' => '', 'error' => "cURL error: $curlError"];
            return null;
        }

        if ($httpCode >= 400) {
            $decoded = json_decode((string)$body, true);
            $message = (is_array($decoded) && isset($decoded['error'])) ? $decoded['error'] : "HTTP $httpCode from registry.";
            $steps[] = ['step' => 'download', 'success' => false, 'skipped' => false, 'detail' => '', 'error' => $message];
            return null;
        }

        if (!str_contains($contentType, 'application/zip') && !str_contains($contentType, 'application/octet-stream')) {
            $decoded = json_decode((string)$body, true);
            $message = (is_array($decoded) && isset($decoded['error'])) ? $decoded['error'] : "Unexpected content-type: $contentType";
            $steps[] = ['step' => 'download', 'success' => false, 'skipped' => false, 'detail' => '', 'error' => $message];
            return null;
        }

        $zipPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . "{$pack}-{$version}.zip";
        if (file_put_contents($zipPath, $body) === false) {
            $steps[] = ['step' => 'download', 'success' => false, 'skipped' => false, 'detail' => '', 'error' => "Could not write ZIP to $zipPath"];
            return null;
        }

        $bytes   = strlen((string)$body);
        $steps[] = ['step' => 'download', 'success' => true, 'skipped' => false, 'detail' => "Downloaded $bytes bytes from $downloadUrl to $zipPath", 'error' => ''];

        return $zipPath;
    }

    /**
     * Inspect (dry_run) or extract (live) the ZIP.
     *
     * @param array<int,array> $steps
     * @return array{success: bool, module_name: string, composer_package: string, composer_version: string, has_migrations: bool, extract_path: ?string}
     */
    private function unpack(string $zipPath, bool $dryRun, array &$steps): array
    {
        $zip    = new ZipArchive();
        $opened = $zip->open($zipPath);

        if ($opened !== true) {
            $steps[] = ['step' => 'unpack', 'success' => false, 'skipped' => false, 'detail' => '', 'error' => "Cannot open ZIP (ZipArchive error $opened)."];
            return ['success' => false, 'module_name' => '', 'composer_package' => '', 'composer_version' => '', 'has_migrations' => false, 'extract_path' => null];
        }

        $unpackTool = new FeaturePackUnpackTool($this->context);
        $inspection = $unpackTool->inspect($zip);
        $zip->close();

        if (!empty($inspection['errors'])) {
            $steps[] = ['step' => 'unpack', 'success' => false, 'skipped' => false, 'detail' => '', 'error' => implode('; ', $inspection['errors'])];
            return ['success' => false, 'module_name' => '', 'composer_package' => '', 'composer_version' => '', 'has_migrations' => false, 'extract_path' => null];
        }

        $moduleName      = $inspection['module_name'];
        $composerPackage = $inspection['composer_package'];
        $composerVersion = $inspection['composer_version'];
        $hasMigrations   = $inspection['has_migrations'];

        if ($dryRun) {
            $detail  = "Inspected archive: module=$moduleName, package=$composerPackage"
                     . ($hasMigrations ? ', has migrations' : ', no migrations');
            $steps[] = ['step' => 'unpack', 'success' => true, 'skipped' => false, 'detail' => $detail, 'error' => ''];

            return ['success' => true, 'module_name' => $moduleName, 'composer_package' => $composerPackage, 'composer_version' => $composerVersion, 'has_migrations' => $hasMigrations, 'extract_path' => null];
        }

        $result = $unpackTool->execute([
            'zip_path'  => $zipPath,
            'overwrite' => false,
        ]);

        $stepOk  = $result['success'];
        $detail  = $stepOk ? "Extracted to: " . ($result['extract_path'] ?? '') : '';
        $error   = !$stepOk ? implode('; ', $result['errors'] ?? []) : '';
        $steps[] = ['step' => 'unpack', 'success' => $stepOk, 'skipped' => false, 'detail' => $detail, 'error' => $error];

        return [
            'success'          => $stepOk,
            'module_name'      => $moduleName,
            'composer_package' => $composerPackage,
            'composer_version' => $composerVersion,
            'has_migrations'   => $hasMigrations,
            'extract_path'     => $result['extract_path'] ?? null,
        ];
    }

    private function resolveBaseUrl(?string $override): string
    {
        if ($override !== null) {
            return $override;
        }

        if ($this->context !== null) {
            return RegistryToolHelper::getRegistryBaseUrl($this->context);
        }

        return self::DEFAULT_REGISTRY_BASE;
    }

    private function stepSkipped(string $step, string $detail): array
    {
        return ['step' => $step, 'success' => true, 'skipped' => true, 'detail' => $detail, 'error' => ''];
    }
}
