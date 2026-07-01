import * as sass from 'sass';
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
/**
 * Emits, at build time, a JSON map of each Vite entry to the project source files
 * (`.ts`/`.js` transitively via the Rollup module graph, plus every `.scss` and its
 * `@use`/`@import` partials via sass) it is built from — filtered to the configured
 * roots. Consumed together with the Render Dependency Recorder's per-request `assets`
 * to resolve a changed source module to the tests whose pages loaded its entry.
 */
export function renderGraph(options = {}) {
    const projectRoot = `${(options.projectRoot ?? process.cwd()).replace(/\/+$/, '')}/`;
    const roots = options.roots ?? ['source/', 'local/'];
    const outFile = options.outFile ?? resolve(projectRoot, 'test/playwright/render-graph.json');
    const sassLoadPaths = options.sassLoadPaths ?? [];
    const toRepoRelative = (id) => {
        const path = id.split('?')[0];
        const relative = path.startsWith(projectRoot) ? path.slice(projectRoot.length) : path;
        return roots.some((root) => relative.startsWith(root)) ? relative : null;
    };
    const sassDeps = new Map();
    const resolveSassDeps = (absolutePath) => {
        const cached = sassDeps.get(absolutePath);
        if (cached !== undefined) {
            return cached;
        }
        let deps = [];
        try {
            const result = sass.compile(absolutePath, { loadPaths: sassLoadPaths, quietDeps: true });
            deps = result.loadedUrls
                .filter((url) => url.protocol === 'file:')
                .map((url) => toRepoRelative(fileURLToPath(url)))
                .filter((path) => path !== null);
        }
        catch {
            deps = [];
        }
        sassDeps.set(absolutePath, deps);
        return deps;
    };
    return {
        name: 'render-graph',
        apply: 'build',
        generateBundle(_options, bundle) {
            const chunkFiles = {};
            for (const output of Object.values(bundle)) {
                if (output.type !== 'chunk') {
                    continue;
                }
                const files = new Set();
                for (const moduleId of Object.keys(output.modules)) {
                    const relative = toRepoRelative(moduleId);
                    if (relative !== null) {
                        files.add(relative);
                    }
                    const path = moduleId.split('?')[0];
                    if (path.endsWith('.scss')) {
                        for (const dep of resolveSassDeps(path)) {
                            files.add(dep);
                        }
                    }
                }
                chunkFiles[output.fileName] = files;
            }
            const graph = {};
            for (const output of Object.values(bundle)) {
                if (output.type !== 'chunk' || !output.isEntry) {
                    continue;
                }
                const entry = (output.facadeModuleId && toRepoRelative(output.facadeModuleId)) || output.name;
                const files = new Set();
                const seen = new Set();
                const walk = (fileName) => {
                    if (seen.has(fileName)) {
                        return;
                    }
                    seen.add(fileName);
                    for (const file of chunkFiles[fileName] ?? []) {
                        files.add(file);
                    }
                    const chunk = bundle[fileName];
                    if (chunk && chunk.type === 'chunk') {
                        chunk.imports.forEach(walk);
                    }
                };
                walk(output.fileName);
                graph[entry] = [...files].sort();
            }
            mkdirSync(dirname(outFile), { recursive: true });
            writeFileSync(outFile, `${JSON.stringify(graph, null, 2)}\n`);
        },
    };
}
