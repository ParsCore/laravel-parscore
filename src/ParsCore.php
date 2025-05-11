<?php

namespace ParsCore\Laravel;

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
                ['type' => 'any', 'required' => true, 'multiple' => true],
            ],
            'type' => 'condition',
        ],
        'OR' => [
            'handler' => [self::class, 'handleOr'],
            'params' => [
                ['type' => 'any', 'required' => true, 'multiple' => true],
            ],
            'type' => 'condition',
        ],
        'NOT' => [
            'handler' => [self::class, 'handleNot'],
            'params' => [
                ['type' => 'any', 'required' => true],
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
     * Log messages and data for debugging.
     *
     * @param array|string $data Data to log
     * @return void
     */
    protected function logger($data): void
    {
        // Currently using var_dump for output; can be extended to file logging or Laravel's Log
        // var_dump($data);
        // Example for future file logging:
        // file_put_contents(storage_path('logs/parscore.log'), print_r($data, true) . PHP_EOL, FILE_APPEND);
        // Example for Laravel logging:
        // \Illuminate\Support\Facades\Log::debug('ParsCore', is_array($data) ? $data : ['message' => $data]);
    }

    /**
     * Parse a command string and evaluate its result.
     *
     * @param string|null $input The command string (e.g., "AND[true,true]")
     * @param mixed $context Optional context
     * @return mixed Boolean for conditions, void or other for actions
     */
    public function parse(?string $input, $context = null): mixed
    {
        if (empty($input)) {
            return true;
        }

        $tree = $this->buildSyntaxTree($input);
        $this->logger(['Syntax tree' => $tree]);
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
        $this->logger(['Tokens' => $tokens]);
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

        for ($i = 0; $i < strlen($input); $i++) {
            $char = $input[$i];

            if ($char === '[') {
                $inBracket++;
                $current .= $char;
            } elseif ($char === ']') {
                $inBracket--;
                $current .= $char;
                if ($inBracket === 0 && $current) {
                    $tokens[] = $current;
                    $current = '';
                }
            } elseif ($char === ',' && $inBracket === 0) {
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
                $paramsStr = rtrim($parts[1], ']');
                $params = [];
                $currentParam = '';
                $inNestedBracket = 0;

                for ($i = 0; $i < strlen($paramsStr); $i++) {
                    $char = $paramsStr[$i];
                    if ($char === '[') {
                        $inNestedBracket++;
                        $currentParam .= $char;
                    } elseif ($char === ']') {
                        $inNestedBracket--;
                        $currentParam .= $char;
                    } elseif ($char === ',' && $inNestedBracket === 0 && $currentParam !== '') {
                        $params[] = trim($currentParam);
                        $currentParam = '';
                    } else {
                        $currentParam .= $char;
                    }
                }

                if ($currentParam !== '') {
                    $params[] = trim($currentParam);
                }

                $node['params'] = $params;
            }
            return $node;
        }

        $node['name'] = array_shift($tokens);
        $children = [];
        foreach ($tokens as $token) {
            if (in_array(strtolower($token), ['true', 'false']) || is_numeric($token)) {
                $children[] = ['type' => 'value', 'value' => $token];
            } else {
                $children[] = $this->buildSyntaxTree($token);
            }
        }
        $node['children'] = $children;

        $this->logger(['Parsed node' => $node]);
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
        if ($node['type'] === 'value') {
            $value = $node['value'];
            if (strtolower($value) === 'true') {
                return true;
            }
            if (strtolower($value) === 'false') {
                return false;
            }
            if (is_numeric($value)) {
                return (int)$value;
            }
            return $value;
        }

        if (!isset(static::$commands[$node['name']])) {
            $this->logger(['Error' => "Command {$node['name']} is not defined", 'params' => $node['params']]);
            return static::$commands[$node['name']]['type'] ?? 'condition' === 'condition' ? false : null;
        }

        $commandConfig = static::$commands[$node['name']];

        $params = [];
        if (!empty($node['children'])) {
            foreach ($node['children'] as $child) {
                $params[] = $this->evaluateSyntaxTree($child, $context);
            }
        } else {
            foreach ($node['params'] as $param) {
                if (is_numeric($param)) {
                    $params[] = (int)$param;
                } elseif (strtolower($param) === 'true') {
                    $params[] = true;
                } elseif (strtolower($param) === 'false') {
                    $params[] = false;
                } elseif (strpos($param, '[') !== false) {
                    $params[] = $this->parse($param, $context);
                } else {
                    $params[] = $param;
                }
            }
        }

        $this->logger(['Evaluating command' => $node['name'], 'params' => $params]);

        if (!$this->validateParams($params, $commandConfig['params'], $node['name'])) {
            $this->logger(['Error' => "Invalid parameters for {$node['name']}", 'params' => $params, 'expected' => $commandConfig['params']]);
            return $commandConfig['type'] === 'condition' ? false : null;
        }

        try {
            $this->logger(['Executing handler' => $node['name'], 'params' => $params]);
            return call_user_func($commandConfig['handler'], $params, $context, $this);
        } catch (\Exception $e) {
            $this->logger(['Error' => "Error executing command {$node['name']}: {$e->getMessage()}", 'params' => $params]);
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
            $this->logger(['Error' => "Not enough parameters for {$commandName}", 'provided' => $params, 'required' => $requiredCount]);
            return false;
        }

        if ($commandName === 'NOT' && count($params) !== 1) {
            $this->logger(['Error' => "NOT command requires exactly one parameter", 'provided' => $params]);
            return false;
        }

        foreach ($schema as $index => $rule) {
            if (!isset($params[$index]) && $rule['required']) {
                $this->logger(['Error' => "Missing required parameter at index {$index} for {$commandName}", 'provided' => $params]);
                return false;
            }
        }

        return true;
    }

    // Logical operators

    /**
     * Handle the `AND` logical operator.
     *
     * @param array $params Array of conditions (any type)
     * @param mixed $context Optional context
     * @param ParsCore|null $parser Parser instance for logging
     * @return bool
     */
    protected static function handleAnd(array $params, $context = null, ?ParsCore $parser = null): bool
    {
        $parser?->logger(['Inside handleAnd' => $params]);
        foreach ($params as $param) {
            $result = static::toBoolean($param);
            if (!$result) {
                return false;
            }
        }
        return true;
    }

    /**
     * Handle the `OR` logical operator.
     *
     * @param array $params Array of conditions (any type)
     * @param mixed $context Optional context
     * @param ParsCore|null $parser Parser instance for logging
     * @return bool
     */
    protected static function handleOr(array $params, $context = null, ?ParsCore $parser = null): bool
    {
        $parser?->logger(['Inside handleOr' => $params]);
        foreach ($params as $param) {
            $result = static::toBoolean($param);
            if ($result) {
                return true;
            }
        }
        return false;
    }

    /**
     * Handle the `NOT` logical operator.
     *
     * @param array $params Single condition (any type)
     * @param mixed $context Optional context
     * @param ParsCore|null $parser Parser instance for logging
     * @return bool
     */
    protected static function handleNot(array $params, $context = null, ?ParsCore $parser = null): bool
    {
        $parser?->logger(['Inside handleNot' => $params]);
        return !static::toBoolean($params[0]);
    }

    /**
     * Convert a value to boolean.
     *
     * @param mixed $value Value to convert
     * @return bool
     */
    protected static function toBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return $value != 0;
        }
        if (is_string($value)) {
            return !empty($value);
        }
        return (bool)$value;
    }

    // Basic PHP conditions

    /**
     * Check if two values are equal.
     *
     * @param array $params [value1, value2]
     * @param mixed $context Optional context
     * @param ParsCore|null $parser Parser instance for logging
     * @return bool
     */
    protected static function handleEquals(array $params, $context = null, ?ParsCore $parser = null): bool
    {
        $parser?->logger(['Inside handleEquals' => $params]);
        return $params[0] === $params[1];
    }

    /**
     * Check if the first value is greater than the second.
     *
     * @param array $params [value1, value2]
     * @param mixed $context Optional context
     * @param ParsCore|null $parser Parser instance for logging
     * @return bool
     */
    protected static function handleGreaterThan(array $params, $context = null, ?ParsCore $parser = null): bool
    {
        $parser?->logger(['Inside handleGreaterThan' => $params]);
        return $params[0] > $params[1];
    }

    /**
     * Check if the first value is less than the second.
     *
     * @param array $params [value1, value2]
     * @param mixed $context Optional context
     * @param ParsCore|null $parser Parser instance for logging
     * @return bool
     */
    protected static function handleLessThan(array $params, $context = null, ?ParsCore $parser = null): bool
    {
        $parser?->logger(['Inside handleLessThan' => $params]);
        return $params[0] < $params[1];
    }
}
