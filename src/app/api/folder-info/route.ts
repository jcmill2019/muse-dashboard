import { NextResponse } from 'next/server'
import fs from 'fs'
import config from '../../../../signal.config.json'

export async function GET() {
  const folderPath = process.env.SIGNAL_FOLDER_PATH || config.signalFolder.path
  const exists = (() => {
    try {
      fs.accessSync(folderPath)
      return true
    } catch {
      return false
    }
  })()

  return NextResponse.json({
    name: config.signalFolder.name,
    path: folderPath,
    description: config.signalFolder.description,
    connected: exists,
  })
}
