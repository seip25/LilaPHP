import { defineConfig } from "vite";
import react from "@vitejs/plugin-react";
import path from "path";
import fs from "fs";

function generatePhpManifest() {
  return {
    name: "generate-php-manifest",
    closeBundle() {
      const manifestPath = path.resolve(
        __dirname,
        "../assets/build/.vite/manifest.json",
      );
      const phpOutputPath = path.resolve(
        __dirname,
        "../app/lila/cache/build_manifest.php",
      );

      if (!fs.existsSync(manifestPath)) {
        console.error("manifest.json not found");
        return;
      }

      const manifest = JSON.parse(fs.readFileSync(manifestPath, "utf-8"));

      let phpContent = "<?php return [\n";

      for (const key in manifest) {
        const entry = manifest[key];

        if (!entry.isEntry) continue;

        phpContent += `    '${key}' => [\n`;
        phpContent += `        'file' => '${entry.file}',\n`;

        if (entry.css && entry.css.length) {
          phpContent += `        'css' => [${entry.css.map((c) => `'${c}'`).join(", ")}],\n`;
        } else {
          phpContent += `        'css' => [],\n`;
        }

        phpContent += "    ],\n";
      }

      phpContent += "];";

      fs.writeFileSync(phpOutputPath, phpContent);

      console.log("build_manifest.php generated");
    },
  };
}

function liveReloadTwigAndPhp() {
  return {
    name: "live-reload-twig-php",
    handleHotUpdate({ file, server }) {
      if (file.endsWith(".twig") || file.endsWith(".php")) {
        console.log("Vite detected change:", file);
        server.ws.send({ type: "full-reload" });
      }
    },
  };
}

export default defineConfig({
  server: {
    watch: {
      usePolling: true,
      ignored: ["!**/*.php"],
    },
  },
  plugins: [react(), generatePhpManifest(), liveReloadTwigAndPhp()],
  root: path.resolve(__dirname, "resources"),
  base: "./",
  build: {
    outDir: path.resolve(__dirname, "../assets/build"),
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: path.resolve(__dirname, "resources/js/main.jsx"),
    },
  },
});
