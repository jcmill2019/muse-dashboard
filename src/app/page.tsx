'use client'

import { useEffect, useState } from 'react'

interface FolderInfo {
  name: string
  path: string
  description: string
  connected: boolean
}

interface FileEntry {
  name: string
  type: 'file' | 'directory'
  size?: number
  modified?: string
  extension?: string
}

function formatSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} o`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} Ko`
  return `${(bytes / 1024 / 1024).toFixed(1)} Mo`
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('fr-FR', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

function FileIcon({ type, ext }: { type: string; ext?: string }) {
  if (type === 'directory') return <span style={{ fontSize: 18 }}>📁</span>
  const icons: Record<string, string> = {
    '.pdf': '📄', '.doc': '📝', '.docx': '📝', '.txt': '📃',
    '.jpg': '🖼', '.jpeg': '🖼', '.png': '🖼', '.gif': '🖼',
    '.mp3': '🎵', '.mp4': '🎬', '.mov': '🎬',
    '.zip': '🗜', '.json': '⚙️', '.ts': '⚙️', '.js': '⚙️',
  }
  return <span style={{ fontSize: 18 }}>{icons[ext ?? ''] ?? '📄'}</span>
}

export default function Home() {
  const [info, setInfo] = useState<FolderInfo | null>(null)
  const [files, setFiles] = useState<FileEntry[]>([])
  const [currentPath, setCurrentPath] = useState('')
  const [breadcrumbs, setBreadcrumbs] = useState<string[]>([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    fetch('/api/folder-info')
      .then((r) => r.json())
      .then(setInfo)
      .catch(() => setError('Impossible de récupérer les infos du dossier'))
  }, [])

  useEffect(() => {
    if (info && !info.connected) return
    setLoading(true)
    setError(null)
    fetch(`/api/files?path=${encodeURIComponent(currentPath)}`)
      .then((r) => r.json())
      .then((data) => {
        if (data.error) setError(data.error)
        else setFiles(data.files ?? [])
      })
      .catch(() => setError('Erreur de lecture du dossier'))
      .finally(() => setLoading(false))
  }, [currentPath, info])

  function navigateTo(name: string) {
    const next = currentPath ? `${currentPath}/${name}` : name
    setCurrentPath(next)
    setBreadcrumbs((prev) => [...prev, name])
  }

  function navigateBreadcrumb(index: number) {
    if (index < 0) {
      setCurrentPath('')
      setBreadcrumbs([])
    } else {
      const crumbs = breadcrumbs.slice(0, index + 1)
      setCurrentPath(crumbs.join('/'))
      setBreadcrumbs(crumbs)
    }
  }

  const dirs = files.filter((f) => f.type === 'directory')
  const filesList = files.filter((f) => f.type === 'file')

  return (
    <div style={{ maxWidth: 900, margin: '0 auto', padding: '2rem 1.5rem' }}>
      {/* Header */}
      <div style={{ marginBottom: '2rem' }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: 12 }}>
          <span style={{ fontSize: 32 }}>🎵</span>
          <div>
            <h1 style={{ fontSize: 24, fontWeight: 700, letterSpacing: '-0.5px' }}>
              Muse Dashboard
            </h1>
            <p style={{ fontSize: 13, color: '#888', marginTop: 2 }}>
              Connecté à votre dossier Signal.net
            </p>
          </div>
        </div>
      </div>

      {/* Connection status card */}
      {info && (
        <div style={{
          background: '#1a1a24',
          border: `1px solid ${info.connected ? '#2a6b3a' : '#6b2a2a'}`,
          borderRadius: 10,
          padding: '1rem 1.25rem',
          marginBottom: '1.5rem',
          display: 'flex',
          alignItems: 'flex-start',
          gap: 12,
        }}>
          <span style={{ fontSize: 22, marginTop: 2 }}>{info.connected ? '🟢' : '🔴'}</span>
          <div style={{ flex: 1, minWidth: 0 }}>
            <div style={{ fontWeight: 600, fontSize: 15, marginBottom: 4 }}>
              {info.name}
              <span style={{
                marginLeft: 10, fontSize: 11, padding: '2px 8px', borderRadius: 20,
                background: info.connected ? '#2a6b3a40' : '#6b2a2a40',
                color: info.connected ? '#4caf7d' : '#cf6679',
              }}>
                {info.connected ? 'Connecté' : 'Non trouvé'}
              </span>
            </div>
            <div style={{ fontSize: 12, color: '#666', wordBreak: 'break-all' }}>
              {info.path}
            </div>
            {!info.connected && (
              <div style={{ fontSize: 12, color: '#cf6679', marginTop: 8 }}>
                Vérifiez que le chemin existe sur votre Mac et que le serveur tourne localement.
              </div>
            )}
          </div>
        </div>
      )}

      {/* Breadcrumbs */}
      <div style={{ display: 'flex', alignItems: 'center', gap: 6, marginBottom: '1rem', fontSize: 13 }}>
        <button onClick={() => navigateBreadcrumb(-1)} style={crumbStyle(!breadcrumbs.length)}>
          Racine
        </button>
        {breadcrumbs.map((crumb, i) => (
          <span key={i} style={{ display: 'flex', alignItems: 'center', gap: 6 }}>
            <span style={{ color: '#444' }}>/</span>
            <button onClick={() => navigateBreadcrumb(i)} style={crumbStyle(i === breadcrumbs.length - 1)}>
              {crumb}
            </button>
          </span>
        ))}
      </div>

      {/* File list */}
      {loading && (
        <div style={{ textAlign: 'center', color: '#555', padding: '3rem 0' }}>Chargement…</div>
      )}

      {error && (
        <div style={{
          background: '#6b2a2a20', border: '1px solid #6b2a2a',
          borderRadius: 8, padding: '1rem', color: '#cf6679', fontSize: 13,
        }}>
          {error}
        </div>
      )}

      {!loading && !error && (
        <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
          {dirs.length === 0 && filesList.length === 0 && (
            <div style={{ textAlign: 'center', color: '#555', padding: '3rem 0', fontSize: 14 }}>
              Dossier vide
            </div>
          )}
          {[...dirs, ...filesList].map((entry) => (
            <div
              key={entry.name}
              onClick={entry.type === 'directory' ? () => navigateTo(entry.name) : undefined}
              style={{
                display: 'flex', alignItems: 'center', gap: 12,
                padding: '10px 14px', borderRadius: 8,
                background: '#1a1a24',
                border: '1px solid #2a2a36',
                cursor: entry.type === 'directory' ? 'pointer' : 'default',
                transition: 'border-color 0.15s',
              }}
              onMouseEnter={(e) => {
                if (entry.type === 'directory')
                  (e.currentTarget as HTMLDivElement).style.borderColor = '#4a4a6a'
              }}
              onMouseLeave={(e) => {
                (e.currentTarget as HTMLDivElement).style.borderColor = '#2a2a36'
              }}
            >
              <FileIcon type={entry.type} ext={entry.extension} />
              <span style={{ flex: 1, fontSize: 14, fontWeight: entry.type === 'directory' ? 600 : 400 }}>
                {entry.name}
              </span>
              {entry.size !== undefined && (
                <span style={{ fontSize: 12, color: '#555' }}>{formatSize(entry.size)}</span>
              )}
              {entry.modified && (
                <span style={{ fontSize: 12, color: '#444', minWidth: 120, textAlign: 'right' }}>
                  {formatDate(entry.modified)}
                </span>
              )}
              {entry.type === 'directory' && (
                <span style={{ color: '#555', fontSize: 14 }}>›</span>
              )}
            </div>
          ))}
        </div>
      )}

      {/* Footer */}
      <div style={{ marginTop: '2.5rem', borderTop: '1px solid #1e1e2a', paddingTop: '1rem', fontSize: 11, color: '#444' }}>
        {files.length > 0 && `${dirs.length} dossier(s), ${filesList.length} fichier(s)`}
      </div>
    </div>
  )
}

function crumbStyle(active: boolean): React.CSSProperties {
  return {
    background: 'none', border: 'none', cursor: 'pointer',
    color: active ? '#e2e2e8' : '#666', fontSize: 13,
    padding: '2px 4px', borderRadius: 4,
    fontWeight: active ? 600 : 400,
  }
}
