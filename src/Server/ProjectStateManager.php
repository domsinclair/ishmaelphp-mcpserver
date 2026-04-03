<?php

declare(strict_types=1);

namespace Ishmael\McpServer\Server;

use Ishmael\McpServer\Support\JsonSchemaValidator;

/**
 * ProjectStateManager persists and validates the MCP orchestration state for a single unit of work.
 *
 * Persistence location: <projectRoot>/.ishmael/mcp_state.json
 * If project root cannot be discovered, falls back to cwd/.ishmael/mcp_state.json.
 *
 * Responsibilities in Phase 1:
 * - Maintain current state and mode (quick|standard)
 * - Enforce allowed state transitions per ORCHESTRATION_PLAN.md
 * - Provide reset and basic artifact immutability bookkeeping hooks (no-op enforcement for now)
 */
final class ProjectStateManager
{
    public const MODE_STRICT = 'strict';
    public const MODE_GUIDED = 'guided';
    public const MODE_FREEFORM = 'freeform';

    /** @deprecated Use MODE_STRICT, MODE_GUIDED or MODE_FREEFORM */
    public const MODE_QUICK = 'quick';
    /** @deprecated Use MODE_STRICT, MODE_GUIDED or MODE_FREEFORM */
    public const MODE_STANDARD = 'standard';

    public const INIT = 'INIT';
    public const ANALYSIS_COMPLETE = 'ANALYSIS_COMPLETE';
    public const ARCHITECTURE_COMPLETE = 'ARCHITECTURE_COMPLETE';
    public const IMPLEMENTATION_IN_PROGRESS = 'IMPLEMENTATION_IN_PROGRESS';
    public const IMPLEMENTATION_COMPLETE = 'IMPLEMENTATION_COMPLETE';
    public const REVIEW_COMPLETE = 'REVIEW_COMPLETE';
    public const ACCEPTED = 'ACCEPTED';
    public const ITERATION_REQUIRED = 'ITERATION_REQUIRED';

    /** @var array<string, list<string>> */
    private const ALLOWED_TRANSITIONS = [
        self::INIT => [self::ANALYSIS_COMPLETE],
        self::ANALYSIS_COMPLETE => [self::ARCHITECTURE_COMPLETE, self::INIT],
        self::ARCHITECTURE_COMPLETE => [self::IMPLEMENTATION_IN_PROGRESS, self::ANALYSIS_COMPLETE],
        self::IMPLEMENTATION_IN_PROGRESS => [self::IMPLEMENTATION_COMPLETE],
        self::IMPLEMENTATION_COMPLETE => [self::REVIEW_COMPLETE],
        self::REVIEW_COMPLETE => [self::ACCEPTED, self::ITERATION_REQUIRED],
        self::ACCEPTED => [self::INIT],
        self::ITERATION_REQUIRED => [self::IMPLEMENTATION_IN_PROGRESS, self::ARCHITECTURE_COMPLETE],
    ];

    private string $stateFile;

    /** @var array<string,mixed> */
    private array $data = [];

    public function __construct(?string $projectRoot = null)
    {
        $root = $projectRoot ?? getcwd() ?: '.';
        $ishDir = $root . DIRECTORY_SEPARATOR . '.ishmael';
        if (!is_dir($ishDir)) {
            @mkdir($ishDir, 0777, true);
        }
        $this->stateFile = $ishDir . DIRECTORY_SEPARATOR . 'mcp_state.json';
        $this->load();
    }

    /** Get the absolute path to the state file. */
    public function getStateFile(): string
    {
        return $this->stateFile;
    }

    /** Get current mode (quick|standard). */
    public function getMode(): string
    {
        $this->load();
        $mode = is_string($this->data['mode'] ?? null) ? (string)$this->data['mode'] : self::MODE_STRICT;
        
        // Map legacy modes to new modes if found
        if ($mode === self::MODE_QUICK) {
            return self::MODE_FREEFORM;
        }
        if ($mode === self::MODE_STANDARD) {
            return self::MODE_STRICT;
        }

        return $mode;
    }

    /** Set mode and persist. */
    public function setMode(string $mode): void
    {
        $this->load();
        $validModes = [
            self::MODE_STRICT,
            self::MODE_GUIDED,
            self::MODE_FREEFORM,
            self::MODE_QUICK,
            self::MODE_STANDARD,
        ];
        if (!in_array($mode, $validModes, true)) {
            throw new \InvalidArgumentException('Invalid mode: ' . $mode);
        }
        $this->data['mode'] = $mode;
        $this->save();
    }

    /**
     * Log a tool invocation.
     */
    public function logToolInvocation(string $toolName, array $arguments = [], array $result = []): void
    {
        $this->load();
        $this->data['tool_invocation_log'][] = [
            'tool' => $toolName,
            'arguments' => $arguments,
            'result' => $result,
            'ts' => (int)floor(microtime(true) * 1000),
        ];
        $this->save();
    }

    /**
     * Log a resource access.
     */
    public function logResourceAccess(string $uri): void
    {
        $this->load();
        $this->data['resource_access_log'][] = [
            'uri' => $uri,
            'ts' => (int)floor(microtime(true) * 1000),
        ];
        $this->save();
    }

    /**
     * Get the resource access log.
     */
    public function getResourceAccessLog(): array
    {
        $this->load();
        return $this->data['resource_access_log'] ?? [];
    }

    /**
     * Get the tool invocation log.
     */
    public function getToolInvocationLog(): array
    {
        $this->load();
        return $this->data['tool_invocation_log'] ?? [];
    }

    /** Get current orchestration state. */
    public function getState(): string
    {
        $this->load();
        return is_string($this->data['state'] ?? null) ? (string)$this->data['state'] : self::INIT;
    }

    /**
     * Attempt a state transition. Returns true if persisted, false if illegal.
     */
    public function transition(string $to): bool
    {
        $this->load();
        $from = $this->getState();
        $allowed = self::ALLOWED_TRANSITIONS[$from] ?? [];
        if (!in_array($to, $allowed, true)) {
            return false;
        }

        // Record simple timeline
        $now = (int)floor(microtime(true) * 1000);
        $this->data['history'][] = [ 'from' => $from, 'to' => $to, 'ts' => $now ];
        $this->data['state'] = $to;

        // Artifact immutability hook: mark the previous state as locked
        $locked = $this->data['locked'] ?? [];
        if (!in_array($from, $locked, true)) {
            $locked[] = $from;
        }
        $this->data['locked'] = array_values(array_unique($locked));

        // When ACCEPTED -> reset to INIT (for next task) per Section 3.1
        if ($to === self::ACCEPTED) {
            $this->data['state'] = self::INIT;
            $this->data['locked'] = [];
            $this->data['history'][] = [ 'from' => self::ACCEPTED, 'to' => self::INIT, 'ts' => $now ];
        }

        $this->save();
        return true;
    }

    /** List of stages considered locked (artifact immutability bookkeeping). */
    public function getLockedStages(): array
    {
        $this->load();
        $locked = $this->data['locked'] ?? [];
        return is_array($locked) ? array_values(array_map('strval', $locked)) : [];
    }

    /** Reset orchestration state to INIT, clearing locks and history. */
    public function reset(): void
    {
        $this->data = [
            'mode' => self::MODE_STRICT,
            'state' => self::INIT,
            'locked' => [],
            'history' => [],
            'tool_invocation_log' => [],
            'version' => 1,
        ];
        $this->save();
    }

    /** Validate an artifact against a provided JSON schema. Returns list of errors. */
    public function validateArtifact(array $artifact, array $schema): array
    {
        $validator = new JsonSchemaValidator();
        return $validator->validate($artifact, $schema);
    }

    private function load(): void
    {
        if (!is_file($this->stateFile)) {
            $this->reset();
            return;
        }

        // Avoid infinite recursion if reset() or save() are called
        static $loading = false;
        if ($loading) return;
        $loading = true;

        $raw = @file_get_contents($this->stateFile);
        if ($raw === false) {
            $loading = false;
            $this->reset();
            return;
        }
        $parsed = json_decode($raw, true);
        if (!is_array($parsed)) {
            $loading = false;
            $this->reset();
            return;
        }
        $this->data = $parsed;
        // Backfill defaults
        if (!isset($this->data['mode'])) {
            $this->data['mode'] = self::MODE_STRICT;
        }
        if (!isset($this->data['state'])) {
            $this->data['state'] = self::INIT;
        }
        if (!isset($this->data['locked']) || !is_array($this->data['locked'])) {
            $this->data['locked'] = [];
        }
        if (!isset($this->data['history']) || !is_array($this->data['history'])) {
            $this->data['history'] = [];
        }
        if (!isset($this->data['tool_invocation_log']) || !is_array($this->data['tool_invocation_log'])) {
            $this->data['tool_invocation_log'] = [];
        }
        if (!isset($this->data['version'])) {
            $this->data['version'] = 1;
        }
        $this->save(); // normalize on disk
        $loading = false;
    }

    private function save(): void
    {
        @file_put_contents($this->stateFile, json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
