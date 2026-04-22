import type { Metadata } from 'next'
import './globals.css'

export const metadata: Metadata = {
  title: 'Muse Dashboard — Signial.net',
  description: 'Tableau de bord connecté au dossier Signial.net',
}

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="fr">
      <body>{children}</body>
    </html>
  )
}
