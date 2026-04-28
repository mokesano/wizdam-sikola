<?php

declare(strict_types=1);

namespace Wizdam\Core;

use PDO;
use Twig\Environment;
use Wizdam\Database\DBConnector;
use Wizdam\Services\Core\AuthManager;
use Wizdam\Services\SangiaApi\SangiaGateway;
use Wizdam\Services\ApiKeyManager;
use Wizdam\Jobs\QueueManager;
use Dotenv\Dotenv;

/**
 * Application Container - Mengelola dependency injection dan bootstrap aplikasi.
 */
class App
{
    private static ?App $instance = null;

    private array $config = [];
    private ?PDO $db = null;
    private ?Environment $twig = null;
    private ?AuthManager $auth = null;
    private ?SangiaGateway $apiClient = null;
    private ?ApiKeyManager $apiKeyManager = null;
    private ?QueueManager $queueManager = null;

    private function __construct() {}

    /**
     * Singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Bootstrap aplikasi: load env, config, database, twig, auth, services
     */
    public function bootstrap(string $basePath): self
    {
        // Load environment variables
        $dotenv = Dotenv::createImmutable($basePath);
        $dotenv->safeLoad();

        // Load config
        $this->config = require $basePath . '/config/app.php';

        // Setup timezone
        date_default_timezone_set($this->config['timezone'] ?? 'Asia/Makassar');

        // Init database
        $this->db = DBConnector::getInstance()->getPdo();

        // Init Twig
        $loader = new \Twig\Loader\FilesystemLoader($basePath . '/views');
        $this->twig = new Environment($loader, [
            'cache' => $this->config['twig_cache'] ? $basePath . '/storage/cache/twig' : false,
            'debug' => $this->config['debug'],
            'auto_reload' => $this->config['debug'],
        ]);

        if ($this->config['debug']) {
            $this->twig->addExtension(new \Twig\Extension\DebugExtension());
        }

        // Add global variables
        $this->twig->addGlobal('app', $this->config);
        $this->twig->addGlobal('base_url', $this->config['url'] ?? '/');
        $this->twig->addGlobal('current_year', date('Y'));

        // Init Auth
        $this->auth = new AuthManager($this->db);
        $this->twig->addGlobal('currentUser', $this->auth->isLoggedIn() ? $this->auth->getUserId() : null);
        $this->twig->addGlobal('isLoggedIn', $this->auth->isLoggedIn());

        // Init services (lazy loading)
        // Services akan diinisialisasi saat pertama kali diakses

        return $this;
    }

    /**
     * Getters untuk dependencies
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    public function getDb(): PDO
    {
        return $this->db;
    }

    public function getTwig(): Environment
    {
        return $this->twig;
    }

    public function getAuth(): AuthManager
    {
        return $this->auth;
    }

    public function getAuthService(): AuthManager
    {
        return $this->auth;
    }

    public function getDatabase(): PDO
    {
        return $this->db;
    }

    public function getApiClient(): SangiaGateway
    {
        if ($this->apiClient === null) {
            $this->apiClient = new SangiaGateway();
        }
        return $this->apiClient;
    }

    public function getApiKeyManager(): ApiKeyManager
    {
        if ($this->apiKeyManager === null) {
            $this->apiKeyManager = new ApiKeyManager($this->db);
        }
        return $this->apiKeyManager;
    }

    public function getQueueManager(): QueueManager
    {
        if ($this->queueManager === null) {
            $this->queueManager = new QueueManager($this->db);
        }
        return $this->queueManager;
    }

    /**
     * Helper untuk membuat handler dengan dependency injection otomatis
     */
    public function makeHandler(string $className): object
    {
        $reflection = new \ReflectionClass($className);
        $constructor = $reflection->getConstructor();
        
        if ($constructor === null) {
            return new $className();
        }

        $parameters = $constructor->getParameters();
        $dependencies = [];

        foreach ($parameters as $param) {
            $type = $param->getType();
            
            if ($type === null) {
                continue;
            }

            $typeName = $type->getName();

            switch ($typeName) {
                case 'PDO':
                    $dependencies[] = $this->getDb();
                    break;
                case 'Wizdam\Database\DBConnector':
                    $dependencies[] = DBConnector::getInstance();
                    break;
                case 'Twig\Environment':
                    $dependencies[] = $this->getTwig();
                    break;
                case 'Wizdam\Services\Core\AuthManager':
                    $dependencies[] = $this->getAuth();
                    break;
                case 'Wizdam\Services\SangiaApi\SangiaGateway':
                    $dependencies[] = $this->getApiClient();
                    break;
                case 'Wizdam\Services\ApiKeyManager':
                    $dependencies[] = $this->getApiKeyManager();
                    break;
                case 'Wizdam\Jobs\QueueManager':
                    $dependencies[] = $this->getQueueManager();
                    break;
                default:
                    if ($param->isDefaultValueAvailable()) {
                        $dependencies[] = $param->getDefaultValue();
                    }
                    break;
            }
        }

        return $reflection->newInstanceArgs($dependencies);
    }
}
