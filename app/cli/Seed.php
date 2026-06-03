<?php

namespace Cli;

use PDO;

/**
 * Seeder Command
 * 
 * Handles database seeding with test data.
 * 
 * @package Cli
 */
class Seed extends Command
{
    private string $seedersDir;

    public function __construct()
    {
        parent::__construct();
        $this->seedersDir = __DIR__ . '/seeders';

        // Create seeders directory if it doesn't exist
        if (!is_dir($this->seedersDir)) {
            mkdir($this->seedersDir, 0755, true);
        }
    }

    /**
     * Execute seed command
     * 
     * @param array $args Command arguments
     * @return void
     */
    public function execute(array $args): void
    {
        $this->run($args);
    }

    /**
     * Run all seeders
     * 
     * @param array $args Command arguments
     * @return void
     */
    public function run(array $args): void
    {
        // Ensure we're connected to the database
        if (!$this->db) {
            $this->connectDatabase(true);
        }

        $this->info("Running seeders...");

        $seeders = $this->discoverSeeders();

        if (empty($seeders)) {
            $this->warning("No seeders found in cli/seeders directory.");
            $this->info("Create a seeder with: php app/cli.php seed:create SeederName");
            return;
        }

        foreach ($seeders as $seederClass) {
            $this->info("Running: {$seederClass}");

            try {
                $seeder = new $seederClass($this->db);
                $seeder->run();
                $this->success("Completed: {$seederClass}");
            } catch (\Throwable $e) {
                $this->error("Failed: {$seederClass} - " . $e->getMessage());
            }
        }

        $this->success("All seeders completed!");
    }

    /**
     * Create a new seeder file
     * 
     * @param array $args Command arguments
     * @return void
     */
    public function create(array $args): void
    {
        if (empty($args[0])) {
            $this->error("Please provide a seeder name.");
            $this->info("Usage: php app/cli.php seed:create UserSeeder");
            return;
        }

        $name = $args[0];

        // Ensure name ends with 'Seeder'
        if (!str_ends_with($name, 'Seeder')) {
            $name .= 'Seeder';
        }

        $filename = $this->seedersDir . '/' . $name . '.php';

        if (file_exists($filename)) {
            $this->error("Seeder {$name} already exists!");
            return;
        }

        $template = $this->getSeederTemplate($name);
        file_put_contents($filename, $template);

        $this->success("Created seeder: {$filename}");
        $this->info("Edit the file to add your seeding logic.");
    }

    /**
     * Discover all seeder classes
     * 
     * @return array Array of seeder class names
     */
    private function discoverSeeders(): array
    {
        $seeders = [];

        if (!is_dir($this->seedersDir)) {
            return $seeders;
        }

        $files = scandir($this->seedersDir);

        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                $className = 'Cli\\Seeders\\' . pathinfo($file, PATHINFO_FILENAME);

                require_once $this->seedersDir . '/' . $file;

                if (class_exists($className)) {
                    $seeders[] = $className;
                }
            }
        }

        return $seeders;
    }

    /**
     * Get seeder template
     * 
     * @param string $name Seeder name
     * @return string Template content
     */
    private function getSeederTemplate(string $name): string
    {
        return <<<PHP
<?php

namespace Cli\Seeders;

use PDO;

/**
 * {$name}
 * 
 * Seed data for the database.
 */
class {$name}
{
    private PDO \$db;

    public function __construct(PDO \$db)
    {
        \$this->db = \$db;
    }

    /**
     * Run the seeder
     * 
     * @return void
     */
    public function run(): void
    {
        // Example: Insert users
        \$stmt = \$this->db->prepare("
            INSERT INTO users (email, password, created_at) 
            VALUES (?, ?, ?)
        ");

        \$currentTime = date('Y-m-d H:i:s');
        
        \$users = [
            ['user1@example.com', password_hash('password123', PASSWORD_DEFAULT), \$currentTime],
            ['user2@example.com', password_hash('password123', PASSWORD_DEFAULT), \$currentTime],
            ['user3@example.com', password_hash('password123', PASSWORD_DEFAULT), \$currentTime],
        ];

        foreach (\$users as \$user) {
            try {
                \$stmt->execute(\$user);
            } catch (\\PDOException \$e) {
                // Handle duplicate entries or other errors
                echo "Warning: " . \$e->getMessage() . PHP_EOL;
            }
        }

        echo "Seeded " . count(\$users) . " users." . PHP_EOL;
    }
}

PHP;
    }
}
