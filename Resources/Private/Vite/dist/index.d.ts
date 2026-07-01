import type { Plugin } from 'vite';
export interface RenderGraphOptions {
    projectRoot?: string;
    roots?: string[];
    outFile?: string;
    sassLoadPaths?: string[];
}
/**
 * Emits, at build time, a JSON map of each Vite entry to the project source files
 * (`.ts`/`.js` transitively via the Rollup module graph, plus every `.scss` and its
 * `@use`/`@import` partials via sass) it is built from — filtered to the configured
 * roots. Consumed together with the Render Dependency Recorder's per-request `assets`
 * to resolve a changed source module to the tests whose pages loaded its entry.
 */
export declare function renderGraph(options?: RenderGraphOptions): Plugin;
