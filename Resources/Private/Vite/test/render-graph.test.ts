import { mkdtempSync, readFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'
import { renderGraph } from '../src/index'

type FakeChunk = {
    type: 'chunk'
    fileName: string
    name: string
    isEntry: boolean
    facadeModuleId: string | null
    modules: Record<string, unknown>
    imports: string[]
    dynamicImports: string[]
}

function chunk(partial: Partial<FakeChunk> & Pick<FakeChunk, 'fileName'>): FakeChunk {
    return {
        type: 'chunk',
        name: partial.fileName,
        isEntry: false,
        facadeModuleId: null,
        modules: {},
        imports: [],
        dynamicImports: [],
        ...partial,
    }
}

function buildGraph(bundle: Record<string, FakeChunk>): Record<string, string[]> {
    const outFile = join(mkdtempSync(join(tmpdir(), 'render-graph-')), 'graph.json')
    const plugin = renderGraph({ projectRoot: '/root', roots: ['source/'], outFile })
    const generateBundle = plugin.generateBundle as (options: unknown, bundle: unknown) => void
    generateBundle.call(undefined, {}, bundle)

    return JSON.parse(readFileSync(outFile, 'utf-8')) as Record<string, string[]>
}

describe('renderGraph', () => {
    it('attributes statically imported chunks to the entry', () => {
        const graph = buildGraph({
            'entry.js': chunk({
                fileName: 'entry.js',
                isEntry: true,
                facadeModuleId: '/root/source/main.ts',
                modules: { '/root/source/main.ts': {} },
                imports: ['shared.js'],
            }),
            'shared.js': chunk({
                fileName: 'shared.js',
                modules: { '/root/source/shared.ts': {} },
            }),
        })

        expect(graph['source/main.ts']).toEqual(['source/main.ts', 'source/shared.ts'])
    })

    it('attributes dynamically imported chunks to the entry', () => {
        const graph = buildGraph({
            'entry.js': chunk({
                fileName: 'entry.js',
                isEntry: true,
                facadeModuleId: '/root/source/main.ts',
                modules: { '/root/source/main.ts': {} },
                dynamicImports: ['lazy.js'],
            }),
            'lazy.js': chunk({
                fileName: 'lazy.js',
                modules: { '/root/source/lazy.ts': {} },
                dynamicImports: ['nested.js'],
            }),
            'nested.js': chunk({
                fileName: 'nested.js',
                modules: { '/root/source/nested.ts': {} },
            }),
        })

        expect(graph['source/main.ts']).toEqual([
            'source/lazy.ts',
            'source/main.ts',
            'source/nested.ts',
        ])
    })

    it('drops files outside the configured roots', () => {
        const graph = buildGraph({
            'entry.js': chunk({
                fileName: 'entry.js',
                isEntry: true,
                facadeModuleId: '/root/source/main.ts',
                modules: {
                    '/root/source/main.ts': {},
                    '/root/node_modules/lib/index.js': {},
                },
            }),
        })

        expect(graph['source/main.ts']).toEqual(['source/main.ts'])
    })
})
