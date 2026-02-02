<?php

namespace Cli;

use Throwable;

class React
{
  private string $appDir;

  public function __construct()
  {
    // __DIR__ is .../app/cli
    // dirname(__DIR__) is .../app
    $this->appDir = dirname(__DIR__);
  }

  public function install(array $args): void
  {
    $this->info("Initializing React + Vite setup in app/ directory...");

    $this->createStructure();
    $this->createPackageJson();
    $this->createViteConfig();
    $this->createMainJs();
    $this->createExampleComponent();
    $this->createGitIgnore();

    $this->success("React scaffolding created successfully in app/!");
    $this->info("Important: React files are now located in the 'app/' directory.");
    $this->info("To execute commands, navigate to 'app/' first:");
    $this->info("  cd app");
    $this->info("  npm install");
    $this->info("  npm run dev");
    $this->info("  npm run build");
    $this->info("  If you want, for validations you can use our package @seip/validate v0.0.2");
    $this->info("LilaPHP React + Vite setup completed successfully!");
  }

  private function createStructure(): void
  {
    $dirs = [
      '/resources/js/components',
      '/resources/js/pages',
      // public/build remains in the project root, not app/public
    ];

    foreach ($dirs as $dir) {
      $path = $this->appDir . $dir;
      if (!is_dir($path)) {
        mkdir($path, 0755, true);
        $this->info("Created directory: app{$dir}");
      }
    }
  }

  private function createPackageJson(): void
  {
    $file = $this->appDir . '/package.json';
    if (file_exists($file)) {
      $this->warning("app/package.json already exists. Skipping.");
      return;
    }
    $content = json_encode([
      "private" => true,
      "type" => "module",
      "scripts" => [
        "dev" => "vite",
        "build" => "vite build"
      ],
      "devDependencies" => [
        "vite" => "^5.0.0",
        "@vitejs/plugin-react" => "^4.2.0"
      ],
      "dependencies" => [
        "react" => "^18.2.0",
        "react-dom" => "^18.2.0",
        "@seip/validate" => "^0.0.2"
      ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    file_put_contents($file, $content);
    $this->info("Created app/package.json");
  }

  private function createViteConfig(): void
  {
    $file = $this->appDir . '/vite.config.js';
    if (file_exists($file)) {
      $this->warning("app/vite.config.js already exists. Skipping.");
      return;
    }

    // root is relative to the config file (app/). So 'resources/js' is app/resources/js.
    // outDir is relative to root (app/resources/js). 
    // We want outDir to be project_root/public/build.
    // From app/resources/js, we go: ../../ (to app/) ../ (to root) /public/build
    // So path.resolve(__dirname, '../public/build') -> app/../public/build -> root/public/build

    $content = <<<JS
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
  plugins: [react()],
  root: path.resolve(__dirname, 'resources/js'), 
  base: '/build/',
  build: {
    // __dirname is .../app
    // We want .../public/build
    outDir: path.resolve(__dirname, '../public/build'),
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: path.resolve(__dirname, 'resources/js/main.jsx'),
    },
  },
  server: {
    origin: 'http://localhost:5173',
    strictPort: true,
    cors: true,
  },
});
JS;
    file_put_contents($file, $content);
    $this->info("Created app/vite.config.js");
  }

  private function createMainJs(): void
  {
    $file = $this->appDir . '/resources/js/main.jsx';
    if (file_exists($file)) {
      $this->warning("app/resources/js/main.jsx already exists. Skipping.");
      return;
    }

    $content = <<<JS
import React from 'react';
import { createRoot } from 'react-dom/client';

// Auto-import all components from ./components
const componentModules = import.meta.glob('./pages/*.jsx', { eager: true });

const components = Object.keys(componentModules).reduce((acc, path) => {
  const name = path.split('/').pop().replace('.jsx', '');
  acc[name] = componentModules[path].default;
  return acc;
}, {});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-react-component]').forEach(el => {
      const name = el.dataset.reactComponent;
      const props = JSON.parse(el.dataset.props || '{}');
  
      const Component = components[name];
      if (Component) {
        createRoot(el).render(<Component {...props} />);
        console.log(`Hydrated component: \${name}`);
      } else {
        console.warn(`React component "\${name}" not found.`);
      }
    });
  });
JS;
    file_put_contents($file, $content);
    $this->info("Created app/resources/js/main.jsx");
  }

  private function createExampleComponent(): void
  {


    $file = $this->appDir . '/resources/js/pages/Header.jsx';
    if (file_exists($file)) {
      $this->warning("app/resources/js/pages/Header.jsx already exists. Skipping.");
      return;
    }

    $content = <<<JSX
import React, { useState } from 'react';

export default function Header({ user, cartCount }) {
  const [count, setCount] = useState(cartCount || 0);

  return (
    <header className="p-4 bg-white shadow-md rounded-lg flex justify-between items-center">
      <h1 className="text-xl font-bold text-gray-800">Hello, {user?.name || 'Guest'}!</h1>
      <button 
        onClick={() => setCount(c => c + 1)}
        className="px-4 py-2 bg-blue-500 text-white rounded"
      >
        Cart: {count}
      </button>
    </header>
  );
}
JSX;
    file_put_contents($file, $content);
    $this->info("Created app/resources/js/pages/Header.jsx");
    $fileButton = $this->appDir . '/resources/js/pages/ButtonPage.jsx';
    $contentButton = <<<JSX
import React, { useState } from 'react';
import Button from '../components/Button';

export default function ButtonPage({ label }) {
  const [newLabel, setNewLabel] = useState(label);
  const handleClick = () => setNewLabel(`Random text \${Math.random()}`)

  return (
    <Button onClick={handleClick}  >
      {newLabel}
    </Button>
  );
}
JSX;
    file_put_contents($fileButton, $contentButton);
    $this->info("Created app/resources/js/pages/ButtonPage.jsx");

    $fileButtonComponent= $this->appDir . '/resources/js/components/Button.jsx';
    $contentButtonComponent= <<<JSX
import React, { useState } from 'react';

export default function Button({  onClick=()=>{},className="rounded-xl mt-4 bg-blue-500 text-gray-50 font-semibold px-4 py-2 rounded hover:bg-blue-200",children,...props }) {

    return (
        <button onClick={onClick} className={className} {...props}>
            {children}
        </button>
    );
}
JSX;
    file_put_contents($fileButtonComponent, $contentButtonComponent);
    $this->info("Created app/resources/js/components/Button.jsx");

    $fileReactIsland = $this->appDir . '/resources/js/pages/ReactExample.jsx';
    $contentReactIsland = <<<JSX
import React, { useEffect } from 'react';
import Button from "../components/Button";

export default function ReactIsland({ csrf, translations }) {
  console.log(csrf);
  console.log(translations);

  const historyBack = () => {
    history.back();
  }
  const exampleFetch = async () => {
    let input = {}
    // input = {
    //   email: "example@example.com",
    //   password: "Mypassword.123",
    //   _csrf: csrf
    // }
    const data = JSON.stringify(input)
    const r = await fetch("login/",
      {
        method: "POST",
        body: data,
        headers: {
          "Content-Type": "application/json"
        }
      });
    if (!r.ok) {
      const errors = await r.json();
      console.log(`Try uncommenting the var input`);
      console.log(errors);
      return;
    }
    const response = await r.json();
    console.log(response)
  }
  useEffect(() => {
    exampleFetch();
  }, []);
  return (
    <div className="flex flex-col min-h-screen justify-center items-center">
      <article className="rounded-xl p-4 bg-white shadow-md rounded-lg  px-8 py-8 mx-auto">
        <h2 className="text-xl font-bold text-gray-800">React full page</h2>
        <p className="mt-2 text-gray-500 text-sm">This is a React page rendered inside a Lila function Render.</p>

        <div className='flex justify-center flex-col mt-4 gap-4'>
          <Button onClick={exampleFetch}  >
            Send fetch example to /login
          </Button>

          <Button onClick={historyBack} className="rounded-xl mt-4 bg-blue-100 text-blue-500 font-semibold px-4 py-2 rounded hover:bg-blue-200">
            Back to index
          </Button>
        </div>


      </article>
    </div>
  );
}
JSX;
    file_put_contents($fileReactIsland, $contentReactIsland);
    $this->info("Created app/resources/js/pages/ReactExample.jsx");
  }

  private function createGitIgnore(): void
  {
    // Update app/.gitignore if it exists, or create one.
    // Actually usually .gitignore is at root. 
    // If we want to ignore app/node_modules, we should ensure root .gitignore has it (which it usually does)
    // or add one in app/

    $file = $this->appDir . '/.gitignore';
    $entry = "\n/node_modules\n";

    if (file_exists($file)) {
      $content = file_get_contents($file);
      if (!str_contains($content, 'node_modules')) {
        file_put_contents($file, $entry, FILE_APPEND);
        $this->info("Updated app/.gitignore");
      }
    } else {
      file_put_contents($file, $entry);
      $this->info("Created app/.gitignore");
    }
  }

  private function info(string $msg): void
  {
    echo "\033[36mℹ {$msg}\033[0m" . PHP_EOL;
  }

  private function success(string $msg): void
  {
    echo "\033[32m✓ {$msg}\033[0m" . PHP_EOL;
  }

  private function warning(string $msg): void
  {
    echo "\033[33m⚠ {$msg}\033[0m" . PHP_EOL;
  }
}
