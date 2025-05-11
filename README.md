# ParsCore for Laravel

A lightweight and extensible rules engine for Laravel. Parse and evaluate conditions with a simple syntax: `fn[param1,param2,...]`. Built with pure PHP for maximum compatibility and zero external dependencies.

## Installation

Install via Composer:

```bash
composer require parscore/laravel-parscore
```

The service provider is automatically registered via Laravel's auto-discovery.
# Usage

```php
use ParsCore\Laravel\ParsCore;

$parser = app(ParsCore::class);
$result = $parser->parse('AND[equals[1,1],greater_than[10,5]]');

if ($result) {
    echo "Condition met!";
}
```

# Example Syntax
- ### Check if values are equal and one is greater than another:
    ```text
    AND[equals[1,1],greater_than[10,5]]
    ```
- ### Nested conditions:
    ```text
    OR[AND[equals[1,1],greater_than[10,5]],NOT[less_than[2,3]]]
    ```
# Supported Commands
- ### Logical Operators:
    - ##### [AND[condition1,condition2,...]](): All conditions must be true.
    - ##### [OR[condition1,condition2,...]](): At least one condition must be true.
    - ##### [NOT[condition]](): Negates the condition.

- ### Conditions:
    - ##### [equals[value1,value2]](): Check if two values are strictly equal.
    - ##### [greater_than[value1,value2]](): Check if value1 is greater than value2.
    - ##### [less_than[value1,value2]](): Check if value1 is less than value2.

# Extending ParsCore
- ### Define custom commands in [app/ParsCore/CustomCommands.php]() to extend functionality without affecting package updates:
    ```php
    <?php

    use ParsCore\Laravel\ParsCore;

    ParsCore::registerCommand('is_positive', function ($params) {
        return $params[0] > 0;
    }, [
        'params' => [
            ['type' => 'any', 'required' => true],
        ],
        'type' => 'condition',
    ]);

    ParsCore::registerCommand('contains', function ($params) {
        return str_contains($params[0], $params[1]);
    }, [
        'params' => [
            ['type' => 'any', 'required' => true],
            ['type' => 'any', 'required' => true],
        ],
        'type' => 'condition',
    ]);
    ```

- ### Then use them:
    ```php 
    $parser = app(ParsCore::class);
    $result = $parser->parse('is_positive[5]'); // true
    $result = $parser->parse('contains[hello world,world]'); // true
    ```

# Contributing
Submit issues or pull requests to the GitHub repository. We welcome contributions to make ParsCore even better!

# License
This package is open-sourced under the .