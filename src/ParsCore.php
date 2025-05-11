<?php

namespace ParsCore\Laravel;

use Illuminate\Support\Facades\Log;

/**
 * ParsCore class for parsing and evaluating conditions with a simple syntax: `fn[param1,param2,...]`.
 * Supports logical operators and extensible custom commands.
 */
class ParsCore
{
    /**
     * Registered commands with their configurations.
     *
     * @var array
     */
    protected static array $commands = [
        // Logical operators
        'AND' => [
            'handler' => [self::class, 'handleAnd'],
            'params' => [
                ['type' => 'condition', 'required' => true, 'multiple' => true],
            ],
            'type' => 'condition',
        ],
        'OR' => [
            'handler' => [self::class, 'handleOr'],
            'params' => [
                ['type' => 'condition', 'required' => true, 'multiple' => true],
            ],
            'type' => 'condition',
        ],
        'NOT' => [
            'handler' => [self::class, 'handleNot'],
            'params' => [
                ['type' => 'condition', 'required' => true],
            ],
            'type' => 'condition',
        ],
        // Basic PHP conditions
        'equals' => [
            'handler' => [self::class, 'handleEquals'],
            'params' => [
                ['type' => 'any', 'required' => true],
                ['type' => 'any', 'required' => true],
            ],
            'type' => 'condition',
        ],
        'greater_than' => [
            'handler' => [self::class, 'handleGreaterThan'],
            'params' => [
                ['type' => 'any', 'required' => true],
                ['type' => 'any', 'required' => true],
            ],
            'type' => 'condition',
        ],
        'less_than' => [
            'handler' => [self::class, 'handleLessThan'],
            'params' => [
                ['type' => 'any', 'required' => true],
                ['type' => 'any', 'required' => true],
            ],
            'type' => 'condition',
        ],
    ];

    /**
     * Register a custom command.
     *
     * @param string $name Command name
     * @param callable $handler Handler function
     * @param array $config Command configuration (params, type)
     * @return void
     */
    public static function registerCommand(string $name, callable $handler, array $config): void
    {
        static::$commands[$name] = array_merge($config, ['handler' => $handler]);
    }

    /**
     * Load custom commands from app/ParsCore/CustomCommands.php if it exists.
     *
     * @return void
     */
    protected function loadCustomCommands(): void
    {
        $customCommandsFile = app_path('ParsCore/CustomCommands.php');
        if (file_exists($customCommandsFile)) {
            require_once $customCommandsFile;
        }
    }

    /**
     * Constructor to load custom commands.
     */
    public function __construct()
    {
        $this->loadCustomCommands();
    }

    /**
     * Parse a command string and evaluate its result.
     *
     * @param string|null $input The command string (e.g., "AND[equals[1,1],greater_than[10,5]]")
     * @param mixed $context Optional context
     * @return mixed Boolean for conditions, void or other for actions
     */
    public function parse(?string $input, $context = null): mixed
    {
        if (empty($input)) {
            return true;
        }

        $tree = $this->buildSyntaxTree($input);
        return $this->evaluateSyntaxTree($tree, $context);
    }

    /**
     * Build a syntax tree from the input string.
     *
     * @param string $input The command string
     * @return array The syntax tree
     */
    protected function buildSyntaxTree(string $input): array
    {
        $tokens = $this->tokenize($input);
        return $this->parseTokens($tokens);
    }

    /**
     * Tokenize the input string into commands and operators.
     *
     * @param string $input The command string
     * @return array List of tokens
     */
    protected function tokenize(string $input): array
    {
        $tokens = [];
        $current = '';
        $inBracket = 0;
        $inParams = false;

        for ($i = 0; $i < strlen($input); $i++) {
            $char = $input[$i];

            if ($char === '[') {
                $inBracket++;
                $inParams = true;
                $current .= $char;
            } elseif ($char === ']') {
                $inBracket--;
                $current .= $char;
                if ($inBracket === 0) {
                    $inParams = false;
                }
            } elseif ($char === ',' && $inBracket === 1 && $inParams) {
                $current .= $char;
            } elseif ($char === ',' && !$inParams) {
                if ($current) {
                    $tokens[] = $current;
                    $current = '';
                }
            } else {
                $current .= $char;
            }
        }

        if ($current) {
            $tokens[] = $current;
        }

        return array_map('trim', $tokens);
    }

    /**
     * Parse tokens into a syntax tree.
     *
     * @param array $tokens List of tokens
     * @return array The syntax tree
     */
    protected function parseTokens(array $tokens): array
    {
        $node = ['type' => 'command', 'name' => '', 'params' => [], 'children' => []];

        if (count($tokens) === 1) {
            $token = $tokens[0];
            $parts = explode('[', $token, 2);
            $node['name'] = $parts[0];
            if (isset($parts[1])) {
                $params = rtrim($parts[1], ']');
                $node['params'] = $params ? explode(',', $params) : [];
            }
            return $node;
        }

        $node['name'] = array_shift($tokens);
        $params = [];
        $currentParam = '';

        foreach ($tokens as $token) {
            if ($token === ',') {
                if ($currentParam) {
                    $params[] = $this->buildSyntaxTree($currentParam);
                    $currentParam = '';
                }
                continue;
            }
            $currentParam .= ($currentParam ? ',' : '') . $token;
        }

        if ($currentParam) {
            $params[] = $this->buildSyntaxTree($currentParam);
        }

        $node['children'] = $params;
        return $node;
    }

    /**
     * Evaluate the syntax tree and return the result.
     *
     * @param array $node The syntax tree node
     * @param mixed $context Optional context
     * @return mixed Result of evaluation
     */
    protected function evaluateSyntaxTree(array $node, $context = null): mixed
    {
        if (!isset(static::$commands[$node['name']])) {
            $this->logError("Command {$node['name']} is not defined", $node['name'], $node['params']);
            return isset(static::$commands[$node['name']]['type']) && static::$commands[$node['name']]['type'] === 'condition' ? false : null;
        }

        $commandConfig = static::$commands[$node['name']];

        $params = [];
        foreach ($node['children'] as $child) {
            $params[] = $this->evaluateSyntaxTree($child, $context);
        }

        if (!$this->validateParams($params, $commandConfig['params'], $node['name'])) {
            $this->logError("Invalid parameters for {$node['name']}", $node['name'], $params);
            return $commandConfig['type'] === 'condition' ? false : null;
        }

        try {
            return call_user_func($commandConfig['handler'], $params, $context);
        } catch (\Exception $e) {
            $this->logError("Error executing command {$node['name']}: {$e->getMessage()}", $node['name'], $params);
            return $commandConfig['type'] === 'condition' ? false : null;
        }
    }

    /**
     * Validate command parameters against the schema.
     *
     * @param array $params Provided parameters
     * @param array $schema Parameter schema
     * @param string $commandName Command name for logging
     * @return bool Whether parameters are valid
     */
    protected function validateParams(array $params, array $schema, string $commandName): bool
    {
        $requiredCount = count(array_filter($schema, fn($s) => $s['required']));
        if (count($params) < $requiredCount) {
            return false;
        }

        if ($commandName === 'NOT' && count($params) !== 1) {
            return false;
        }

        foreach ($schema as $index => $rule) {
            if (!isset($params[$index]) && $rule['required']) {
                return false;
            }
            if (isset($params[$index])) {
                $value = $params[$index];
                if ($rule['type'] === 'condition' && !is_bool($value)) {
                    return false;
                }
                // 'any' type allows any value
            }
        }

        return true;
    }

    /**
     * Log an error with details.
     *
     * @param string $message Error message
     * @param string $command Command name
     * @param array $params Command parameters
     */
    protected function logError(string $message, string $command, array $params): void
    {
        Log::error("ParsCore: {$message}", [
            'command' => $command,
            'params' => $params,
        ]);
    }

    // Logical operators

    /**
     * Handle the `AND` logical operator.
     *
     * @param array $params Array of evaluated conditions
     * @param mixed $context Optional context
     * @return bool
     */
    protected static function handleAnd(array $params, $context = null): bool
    {
        foreach ($params as $condition) {
            if (!$condition) {
                return false;
            }
        }
        return true;
    }

    /**
     * Handle the `OR` logical operator.
     *
     * @param array $params Array of evaluated conditions
     * @param mixed $context Optional context
     * @return bool
     */
    protected static function handleOr(array $params, $context = null): bool
    {
        foreach ($params as $condition) {
            if ($condition) {
                return true;
            }
        }
        return false;
    }

    /**
     * Handle the `NOT` logical operator.
     *
     * @param array $params Single condition
     * @param mixed $context Optional context
     * @return bool
     */
    protected static function handleNot(array $params, $context = null): bool
    {
        return !$params[0];
    }

    // Basic PHP conditions

    /**
     * Check if two values are equal.
     *
     * @param array $params [value1, value2]
     * @param mixed $context Optional context
     * @return bool
     */
    protected static function handleEquals(array $params, $context = null): bool
    {
        return $params[0] === $params[1];
    }

    /**
     * Check if the first value is greater than the second.
     *
     * @param array $params [value1, value2]
     * @param mixed $context Optional context
     * @return bool
     */
    protected static function handleGreaterThan(array $params, $context = null): bool
    {
        return $params[0] > $params[1];
    }

    /**
     * Check if the first value is less than the second.
     *
     * @param array $params [value1, value2]
     * @param mixed $context Optional context
     * @return bool
     */
    protected static function handleLessThan(array $params, $context = null): bool
    {
        return $params[0] < $params[1];
    }
}