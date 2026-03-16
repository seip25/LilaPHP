<?php

namespace Core;

use Attribute;

/**
 * Attribute for GET HTTP method
 * 
 * @package Core
 */
#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD)]
class GET {}

/**
 * Attribute for POST HTTP method
 * 
 * @package Core
 */
#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD)]
class POST {}

/**
 * Attribute for PUT HTTP method
 * 
 * @package Core
 */
#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD)]
class PUT {}

/**
 * Attribute for DELETE HTTP method
 * 
 * @package Core
 */
#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD)]
class DELETE {}

/**
 * Attribute to enable CSRF protection
 * 
 * @package Core
 */
#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD)]
class CSRF {}

/**
 * Attribute to define middlewares for a route
 * 
 * @package Core
 */
#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
class Middleware
{
    /**
     * @param callable|array|string $callback Middleware callback
     */
    public function __construct(public mixed $callback) {}
}

/**
 * Attribute to enable Response Caching
 * 
 * @package Core
 */
#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD)]
class Cache
{
    public function __construct(public int $seconds = 60) {}
}

/**
 * Attribute to enable Model Validation
 * 
 * @package Core
 */
#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD)]
class Validate
{
    public function __construct(public string $modelClass, public string|bool $langParam = false) {}
}
