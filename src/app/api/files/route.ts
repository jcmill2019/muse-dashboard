import { NextResponse } from 'next/server'
import fs from 'fs'
import path from 'path'

export interface FileEntry {
  name: string
  type: 'file' | 'directory'
  size?: number
  modified?: string
  extension?: string
}

export async function GET(request: Request) {
  const { searchParams } = new URL(request.url)
  const subPath = searchParams.get('path') || ''

  const baseFolder = process.env.SIGNAL_FOLDER_PATH
  if (!baseFolder) {
    return NextResponse.json(
      { error: 'SIGNAL_FOLDER_PATH non configuré dans .env.local' },
      { status: 500 }
    )
  }

  const targetPath = path.join(baseFolder, subPath)

  // Prevent path traversal outside base folder
  if (!targetPath.startsWith(baseFolder)) {
    return NextResponse.json({ error: 'Chemin non autorisé' }, { status: 403 })
  }

  try {
    const entries = fs.readdirSync(targetPath, { withFileTypes: true })
    const files: FileEntry[] = entries.map((entry) => {
      const fullPath = path.join(targetPath, entry.name)
      const isDir = entry.isDirectory()
      let size: number | undefined
      let modified: string | undefined

      try {
        const stat = fs.statSync(fullPath)
        size = isDir ? undefined : stat.size
        modified = stat.mtime.toISOString()
      } catch {
        // ignore stat errors
      }

      return {
        name: entry.name,
        type: isDir ? 'directory' : 'file',
        size,
        modified,
        extension: isDir ? undefined : path.extname(entry.name).toLowerCase(),
      }
    })

    files.sort((a, b) => {
      if (a.type !== b.type) return a.type === 'directory' ? -1 : 1
      return a.name.localeCompare(b.name, 'fr')
    })

    return NextResponse.json({ files, currentPath: subPath })
  } catch (err) {
    const message = err instanceof Error ? err.message : 'Erreur inconnue'
    return NextResponse.json({ error: message }, { status: 500 })
  }
}
