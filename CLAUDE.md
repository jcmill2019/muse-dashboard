# Muse Dashboard

Tableau de bord Next.js connecté au dossier local **Signal.net**.

## Connexion au dossier Signal.net

Le chemin du dossier est configuré dans deux endroits :

1. **`.env.local`** (non versionné, à créer sur chaque machine) :
   ```
   SIGNAL_FOLDER_PATH="/Users/jcm64/Documents/Documents - MacBook Pro de MacBook/Mise a jour/MaJour"
   ```

2. **`signal.config.json`** (versionné, contient les métadonnées) :
   ```json
   {
     "signalFolder": {
       "name": "Signal.net",
       "path": "/Users/jcm64/Documents/...",
       "description": "Dossier principal Signal.net"
     }
   }
   ```

## Démarrage

```bash
npm install
npm run dev
```

Ouvrir http://localhost:3000

## Structure

```
src/
  app/
    page.tsx              # Interface principale du dashboard
    api/
      files/route.ts      # Liste les fichiers du dossier Signal.net
      folder-info/route.ts # Vérifie la connexion au dossier
signal.config.json        # Configuration du dossier
.env.local                # Chemin local (à créer, non versionné)
```
