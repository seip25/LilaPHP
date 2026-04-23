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

/**
 * Attribute to enable Admin Portal
 * 
 * @package Core
 */
#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD)]
class Admin
{
    /**
     * @param array $models Specific models to show (auto-discovery if empty)
     * @param array $options Additional configuration
     */
    public function __construct(public array $models = [], public array $options = []) {}
}


/**
 * Attribute to define SEO metadata for a route
 * 
 * @package Core
 */
#[Attribute(Attribute::TARGET_FUNCTION | Attribute::TARGET_METHOD)]
class SEO
{
    /**
     * @param string|null $title Page title
     * @param string|null $description Meta description
     * @param string|null $keywords Meta keywords
     * @param string|null $image OG/Share image URL
     * @param string|null $key Centralized SEO translation key
     */
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?string $keywords = null,
        public ?string $image = null,
        public ?string $key = null
    ) {}
}
