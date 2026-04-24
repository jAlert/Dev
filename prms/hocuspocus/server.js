import { Server } from '@hocuspocus/server'
import { Database } from '@hocuspocus/extension-database'
import mysql from 'mysql2/promise'
import fetch from 'node-fetch'

const db = await mysql.createPool({
  host: process.env.DB_HOST || 'mysql',
  database: process.env.DB_DATABASE || 'prms',
  user: process.env.DB_USER || 'prms',
  password: process.env.DB_PASSWORD || 'secret',
  waitForConnections: true,
  connectionLimit: 10,
})

const APP_URL = process.env.APP_URL || 'http://app'

// Parse document name: "record-{id}-field-{slug}"
function parseDocName(name) {
  const match = name.match(/^record-(\d+)-field-(.+)$/)
  if (!match) return null
  return { recordId: match[1], fieldSlug: match[2] }
}

// Table prefix used by Laravel (from DB_PREFIX env or default 'jea_')
const TABLE_PREFIX = process.env.DB_PREFIX || 'jea_'

const server = Server.configure({
  port: 1234,

  async onAuthenticate({ token, documentName }) {
    if (!token) throw new Error('No token provided')

    try {
      const res = await fetch(`${APP_URL}/api/text-editor/validate-token`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json',
        },
        body: JSON.stringify({ document: documentName }),
      })
      if (!res.ok) throw new Error('Unauthorized')
      const data = await res.json()
      return { user: data.user }
    } catch (e) {
      throw new Error('Authentication failed')
    }
  },

  extensions: [
    new Database({
      async fetch({ documentName }) {
        const parsed = parseDocName(documentName)
        if (!parsed) return null

        const [rows] = await db.execute(
          `SELECT binary_state FROM ${TABLE_PREFIX}text_editor_documents WHERE record_id = ? AND field_slug = ? LIMIT 1`,
          [parsed.recordId, parsed.fieldSlug]
        )

        if (!rows.length || !rows[0].binary_state) return null

        // Convert base64 back to Uint8Array
        return Buffer.from(rows[0].binary_state, 'base64')
      },

      async store({ documentName, state }) {
        const parsed = parseDocName(documentName)
        if (!parsed) return

        const base64State = Buffer.from(state).toString('base64')

        try {
          await db.execute(
            `INSERT INTO ${TABLE_PREFIX}text_editor_documents (record_id, field_slug, binary_state, created_at, updated_at)
             VALUES (?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE binary_state = VALUES(binary_state), updated_at = NOW()`,
            [parsed.recordId, parsed.fieldSlug, base64State]
          )
        } catch (err) {
          // FK violation means the record was deleted — skip silently
          if (err.code === 'ER_NO_REFERENCED_ROW_2') return
          console.error('[store] Unexpected DB error:', err.message)
        }
      },
    }),
  ],

  async onChange({ documentName, context, document }) {
    // History logging is handled client-side via Livewire for now
    // Future: send diffs to Laravel API
  },
})

process.on('unhandledRejection', (reason) => {
  console.error('[unhandledRejection]', reason)
})

server.listen()
console.log('Hocuspocus server running on port 1234')
