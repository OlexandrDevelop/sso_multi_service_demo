import { useEffect, useState } from 'react'

const apiBase = import.meta.env.VITE_API_BASE || 'http://localhost:8003'
const authBase = import.meta.env.VITE_AUTH_BASE || 'http://localhost:8001'

function readTokenFromHash(): string | null {
  const hash = window.location.hash
  if (!hash) return null
  const m = hash.match(/token=([^&]+)/)
  return m ? decodeURIComponent(m[1]) : null
}

function getStoredToken(): string | null {
  try { return sessionStorage.getItem('sso_token') } catch { return null }
}

function storeToken(token: string) {
  try { sessionStorage.setItem('sso_token', token) } catch {}
}

function clearUrlHash() {
  if (window.location.hash) {
    window.history.replaceState(null, '', window.location.pathname + window.location.search)
  }
}

export default function App() {
  const [loading, setLoading] = useState(true)
  const [user, setUser] = useState<any>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    async function load() {
      try {
        let token = getStoredToken()
        if (!token) {
          const fromHash = readTokenFromHash()
          if (fromHash) {
            token = fromHash
            storeToken(token)
            clearUrlHash()
          }
        }
        if (!token) {
          const tRes = await fetch(`${authBase}/api/auth/token`, { credentials: 'include' })
          if (tRes.ok) {
            const json = await tRes.json()
            token = json.token
            if (token) storeToken(token)
          }
        }
        if (!token) {
          const redirect = encodeURIComponent(window.location.href)
          window.location.href = `${authBase}/login?redirect=${redirect}`
          return
        }

        const r = await fetch(`${apiBase}/api/sso/me`, {
          headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' },
        })
        if (!r.ok) throw new Error('Failed to fetch user')
        setUser(await r.json())
      } catch (e: any) {
        setError(e.message || 'Unknown error')
      } finally {
        setLoading(false)
      }
    }
    load()
  }, [])

  if (loading) return <div style={{ padding: 24 }}>Loading...</div>
  if (error) return <div style={{ padding: 24, color: 'crimson' }}>Error: {error}</div>

  const doLogout = async () => {
    const token = getStoredToken()
    try {
      await fetch(`${authBase}/api/auth/logout`, {
        method: 'POST',
        headers: token ? { Authorization: `Bearer ${token}`, Accept: 'application/json' } : { Accept: 'application/json' },
        credentials: 'include',
      })
    } catch {}
    try { sessionStorage.removeItem('sso_token') } catch {}
    window.location.href = `${authBase}/login`
  }

  return (
    <div style={{ padding: 24, fontFamily: 'system-ui, sans-serif' }}>
      <h2>Service C</h2>
      <pre style={{ background: '#f6f8fa', padding: 12, borderRadius: 8 }}>{JSON.stringify(user, null, 2)}</pre>
      <div style={{ marginTop: 16, display: 'flex', gap: 12 }}>
        <a href="http://localhost:5173" style={{ padding: '8px 12px', background: '#2563eb', color: '#fff', borderRadius: 6, textDecoration: 'none' }}>Go to Service B</a>
        <button onClick={doLogout} style={{ padding: '8px 12px', borderRadius: 6 }}>Logout</button>
      </div>
    </div>
  )
} 