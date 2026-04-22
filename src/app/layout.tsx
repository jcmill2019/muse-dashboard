import type { Metadata } from 'next'
import './globals.css'

export const metadata: Metadata = {
  title: 'Muse Dashboard — Signal.net',
  description: 'Tableau de bord connecté au dossier Signal.net',
}

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="fr">
      <body>{children}</body>
    </html>
  )
}
