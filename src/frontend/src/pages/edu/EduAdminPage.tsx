import { useCallback, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { eduGame, eduGameClasses } from '../../constants/eduGameTheme'
import {
  eduAdminAssignStudent,
  eduAdminCreateOperator,
  eduAdminCreateOrganization,
  eduAdminListOperators,
  eduAdminListOrganizations,
  eduAdminListStudents,
  eduAdminUpdateOrganization,
  eduAdminVerifyKey,
  type EduAdminOperator,
  type EduAdminStudent,
  type EduOrganization,
} from '../../services/eduAdminApi'
import {
  clearEduAdminKey,
  hasEduAdminKey,
  setEduAdminKey,
} from '../../utils/eduAdminSession'

type Tab = 'orgs' | 'students' | 'operators'

const TYPE_LABEL: Record<string, string> = {
  academy: '학원',
  school: '학교',
}

const ROLE_LABEL: Record<string, string> = {
  owner: '원장',
  teacher: '교사',
}

export default function EduAdminPage() {
  const [authed, setAuthed] = useState(false)
  const [keyInput, setKeyInput] = useState('')
  const [loginError, setLoginError] = useState('')
  const [loginLoading, setLoginLoading] = useState(false)

  const [tab, setTab] = useState<Tab>('orgs')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  const [orgs, setOrgs] = useState<EduOrganization[]>([])
  const [students, setStudents] = useState<EduAdminStudent[]>([])
  const [operators, setOperators] = useState<EduAdminOperator[]>([])

  const [orgForm, setOrgForm] = useState({
    name: '',
    type: 'academy' as 'academy' | 'school',
    slug: '',
    address: '',
    contact: '',
  })

  const [assignOrgId, setAssignOrgId] = useState('')
  const [showUnassignedOnly, setShowUnassignedOnly] = useState(false)
  const [studentSearch, setStudentSearch] = useState('')

  const [opForm, setOpForm] = useState({
    email: '',
    password: '',
    display_name: '',
    organization_id: '',
    role: 'owner' as 'owner' | 'teacher',
  })

  const verifySession = useCallback(async () => {
    if (!hasEduAdminKey()) {
      setAuthed(false)
      return
    }
    await eduAdminVerifyKey()
    setAuthed(true)
  }, [])

  const reloadOrgs = useCallback(async () => {
    const rows = await eduAdminListOrganizations()
    setOrgs(rows)
    if (!assignOrgId && rows[0]?.id) setAssignOrgId(rows[0].id)
    if (!opForm.organization_id && rows[0]?.id) {
      setOpForm((f) => ({ ...f, organization_id: rows[0].id }))
    }
  }, [assignOrgId, opForm.organization_id])

  const reloadStudents = useCallback(async () => {
    const rows = await eduAdminListStudents(
      showUnassignedOnly ? { unassigned: true } : undefined
    )
    setStudents(rows)
  }, [showUnassignedOnly])

  const reloadOperators = useCallback(async () => {
    setOperators(await eduAdminListOperators())
  }, [])

  const reloadTab = useCallback(async () => {
    setError('')
    setLoading(true)
    try {
      if (tab === 'orgs') await reloadOrgs()
      if (tab === 'students') {
        await reloadOrgs()
        await reloadStudents()
      }
      if (tab === 'operators') {
        await reloadOrgs()
        await reloadOperators()
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : 'load failed')
      if (e instanceof Error && (e.message.includes('401') || e.message.includes('Unauthorized'))) {
        clearEduAdminKey()
        setAuthed(false)
      }
    } finally {
      setLoading(false)
    }
  }, [tab, reloadOrgs, reloadStudents, reloadOperators])

  useEffect(() => {
    void verifySession().catch(() => setAuthed(false))
  }, [verifySession])

  useEffect(() => {
    if (!authed) return
    void reloadTab()
  }, [authed, tab, reloadTab])

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault()
    setLoginError('')
    setLoginLoading(true)
    try {
      const key = keyInput.trim()
      if (!key) throw new Error('Admin API Key를 입력하세요')
      setEduAdminKey(key)
      await eduAdminVerifyKey()
      setAuthed(true)
    } catch (err) {
      clearEduAdminKey()
      setLoginError(err instanceof Error ? err.message : '인증 실패')
    } finally {
      setLoginLoading(false)
    }
  }

  const handleLogout = () => {
    clearEduAdminKey()
    setAuthed(false)
    setKeyInput('')
  }

  const handleCreateOrg = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    try {
      const metadata: Record<string, string> = {}
      if (orgForm.address.trim()) metadata.address = orgForm.address.trim()
      if (orgForm.contact.trim()) metadata.contact = orgForm.contact.trim()
      await eduAdminCreateOrganization({
        name: orgForm.name.trim(),
        type: orgForm.type,
        slug: orgForm.slug.trim() || undefined,
        metadata,
      })
      setOrgForm({ name: '', type: 'academy', slug: '', address: '', contact: '' })
      await reloadOrgs()
    } catch (err) {
      setError(err instanceof Error ? err.message : '등록 실패')
    }
  }

  const handleToggleOrg = async (org: EduOrganization) => {
    setError('')
    try {
      await eduAdminUpdateOrganization({ id: org.id, is_active: !org.is_active })
      await reloadOrgs()
    } catch (err) {
      setError(err instanceof Error ? err.message : '수정 실패')
    }
  }

  const handleAssign = async (studentId: string) => {
    if (!assignOrgId) {
      setError('배정할 조직을 선택하세요')
      return
    }
    setError('')
    try {
      await eduAdminAssignStudent(studentId, assignOrgId)
      await reloadStudents()
    } catch (err) {
      setError(err instanceof Error ? err.message : '배정 실패')
    }
  }

  const handleUnassign = async (studentId: string) => {
    setError('')
    try {
      await eduAdminAssignStudent(studentId, null)
      await reloadStudents()
    } catch (err) {
      setError(err instanceof Error ? err.message : '배정 해제 실패')
    }
  }

  const handleCreateOperator = async (e: React.FormEvent) => {
    e.preventDefault()
    setError('')
    try {
      await eduAdminCreateOperator({
        email: opForm.email.trim(),
        password: opForm.password,
        display_name: opForm.display_name.trim() || undefined,
        organization_id: opForm.organization_id,
        role: opForm.role,
      })
      setOpForm((f) => ({ ...f, email: '', password: '', display_name: '' }))
      await reloadOperators()
    } catch (err) {
      setError(err instanceof Error ? err.message : '교사 계정 생성 실패')
    }
  }

  if (!authed) {
    return (
      <div
        className="min-h-screen flex flex-col"
        style={{ backgroundColor: eduGame.bg, color: eduGame.ink, fontFamily: eduGame.fontBody }}
      >
        <header className="px-4 py-3 border-b" style={{ borderColor: eduGame.border }}>
          <p className="text-xs font-bold" style={{ color: eduGame.primary }}>
            gistudy · Super Admin
          </p>
          <h1 className="text-lg font-bold mt-0.5">EDU Admin</h1>
        </header>
        <main className="flex-1 flex items-center justify-center px-4 py-8">
          <form onSubmit={(e) => void handleLogin(e)} className="w-full max-w-sm space-y-4">
            <p className="text-sm" style={{ color: eduGame.muted }}>
              이원근 전용 — EDU Admin API Key만 접근 가능합니다. 학생·교사 계정으로는 들어올 수 없습니다.
            </p>
            <div>
              <label className="block text-xs font-bold mb-1" htmlFor="admin-key">
                Admin API Key
              </label>
              <input
                id="admin-key"
                type="password"
                autoComplete="off"
                value={keyInput}
                onChange={(e) => setKeyInput(e.target.value)}
                className={`w-full px-3 py-2.5 rounded-xl border ${eduGameClasses.input}`}
                style={{ borderColor: eduGame.border }}
                required
              />
            </div>
            {loginError && (
              <p className="text-sm text-red-600" role="alert">
                {loginError}
              </p>
            )}
            <button
              type="submit"
              disabled={loginLoading}
              className={`w-full py-2.5 rounded-xl font-bold text-white ${eduGameClasses.btnPrimary}`}
              style={{ backgroundColor: eduGame.primary }}
            >
              {loginLoading ? '확인 중…' : '로그인'}
            </button>
            <Link to="/edu" className="block text-center text-sm underline" style={{ color: eduGame.muted }}>
              EDU 홈으로
            </Link>
          </form>
        </main>
      </div>
    )
  }

  return (
    <div
      className="min-h-screen flex flex-col"
      style={{ backgroundColor: eduGame.bg, color: eduGame.ink, fontFamily: eduGame.fontBody }}
    >
      <header className="px-4 py-3 border-b flex flex-wrap items-center justify-between gap-2" style={{ borderColor: eduGame.border }}>
        <div>
          <p className="text-xs font-bold" style={{ color: eduGame.primary }}>
            gistudy · Super Admin
          </p>
          <h1 className="text-lg font-bold">EDU Admin</h1>
        </div>
        <div className="flex flex-wrap gap-2 text-sm">
          <Link to="/edu/operator/reports" className="underline" style={{ color: eduGame.muted }}>
            리포트 admin (별도)
          </Link>
          <button type="button" onClick={handleLogout} className="underline" style={{ color: eduGame.muted }}>
            키 삭제·로그아웃
          </button>
        </div>
      </header>

      <nav className="flex border-b px-2" style={{ borderColor: eduGame.border }}>
        {(
          [
            ['orgs', '조직'],
            ['students', '학생 배정'],
            ['operators', '교사 계정'],
          ] as const
        ).map(([id, label]) => (
          <button
            key={id}
            type="button"
            onClick={() => setTab(id)}
            className="px-4 py-2.5 text-sm font-bold border-b-2 -mb-px"
            style={{
              borderColor: tab === id ? eduGame.primary : 'transparent',
              color: tab === id ? eduGame.primary : eduGame.muted,
            }}
          >
            {label}
          </button>
        ))}
      </nav>

      <main className="flex-1 px-4 py-4 max-w-3xl mx-auto w-full space-y-4">
        {error && (
          <p className="text-sm text-red-600 bg-red-50 px-3 py-2 rounded-lg" role="alert">
            {error}
          </p>
        )}
        {loading && <p className="text-sm" style={{ color: eduGame.muted }}>불러오는 중…</p>}

        {tab === 'orgs' && (
          <>
            <form onSubmit={(e) => void handleCreateOrg(e)} className="space-y-3 p-4 rounded-xl border" style={{ borderColor: eduGame.border }}>
              <h2 className="font-bold">학원·학교 등록</h2>
              <input
                placeholder="이름 (예: A학원, B중학교)"
                value={orgForm.name}
                onChange={(e) => setOrgForm((f) => ({ ...f, name: e.target.value }))}
                className={`w-full px-3 py-2 rounded-lg border ${eduGameClasses.input}`}
                required
              />
              <select
                value={orgForm.type}
                onChange={(e) => setOrgForm((f) => ({ ...f, type: e.target.value as 'academy' | 'school' }))}
                className={`w-full px-3 py-2 rounded-lg border ${eduGameClasses.input}`}
              >
                <option value="academy">학원 (academy)</option>
                <option value="school">학교 (school)</option>
              </select>
              <input
                placeholder="slug (비우면 이름에서 자동)"
                value={orgForm.slug}
                onChange={(e) => setOrgForm((f) => ({ ...f, slug: e.target.value }))}
                className={`w-full px-3 py-2 rounded-lg border ${eduGameClasses.input}`}
              />
              <input
                placeholder="주소 (metadata)"
                value={orgForm.address}
                onChange={(e) => setOrgForm((f) => ({ ...f, address: e.target.value }))}
                className={`w-full px-3 py-2 rounded-lg border ${eduGameClasses.input}`}
              />
              <input
                placeholder="담당자 (metadata)"
                value={orgForm.contact}
                onChange={(e) => setOrgForm((f) => ({ ...f, contact: e.target.value }))}
                className={`w-full px-3 py-2 rounded-lg border ${eduGameClasses.input}`}
              />
              <button type="submit" className={`px-4 py-2 rounded-lg font-bold text-white ${eduGameClasses.btnPrimary}`} style={{ backgroundColor: eduGame.primary }}>
                등록
              </button>
            </form>

            <ul className="space-y-2">
              {orgs.map((org) => (
                <li key={org.id} className="p-3 rounded-xl border flex justify-between items-start gap-2" style={{ borderColor: eduGame.border }}>
                  <div>
                    <p className="font-bold">
                      {org.name}{' '}
                      <span className="text-xs font-normal" style={{ color: eduGame.muted }}>
                        ({TYPE_LABEL[org.type] ?? org.type})
                      </span>
                    </p>
                    <p className="text-xs" style={{ color: eduGame.muted }}>
                      {org.slug} · {org.is_active ? '활성' : '비활성'}
                    </p>
                  </div>
                  <button
                    type="button"
                    onClick={() => void handleToggleOrg(org)}
                    className="text-xs underline shrink-0"
                  >
                    {org.is_active ? '비활성화' : '활성화'}
                  </button>
                </li>
              ))}
            </ul>
          </>
        )}

        {tab === 'students' && (
          <>
            <div className="flex flex-wrap gap-3 items-center">
              <label className="text-sm">
                배정 조직
                <select
                  value={assignOrgId}
                  onChange={(e) => setAssignOrgId(e.target.value)}
                  className="ml-2 px-2 py-1 rounded border"
                >
                  {orgs.filter((o) => o.is_active).map((o) => (
                    <option key={o.id} value={o.id}>
                      {o.name}
                    </option>
                  ))}
                </select>
              </label>
              <label className="text-sm flex items-center gap-1">
                <input
                  type="checkbox"
                  checked={showUnassignedOnly}
                  onChange={(e) => setShowUnassignedOnly(e.target.checked)}
                />
                미배정만
              </label>
              <input
                type="search"
                placeholder="학생 검색"
                value={studentSearch}
                onChange={(e) => setStudentSearch(e.target.value)}
                className={`px-2 py-1 rounded border text-sm ${eduGameClasses.input}`}
              />
            </div>
            <ul className="space-y-2">
              {students
                .filter((s) => {
                  const q = studentSearch.trim().toLowerCase()
                  if (!q) return true
                  return s.display_name.toLowerCase().includes(q)
                })
                .map((s) => (
                <li key={s.id} className="p-3 rounded-xl border flex flex-wrap justify-between gap-2" style={{ borderColor: eduGame.border }}>
                  <div>
                    <p className="font-bold">{s.display_name}</p>
                    <p className="text-xs" style={{ color: eduGame.muted }}>
                      완주 {s.completed_count} · {s.organization_name ?? '미배정 (NULL)'}
                    </p>
                  </div>
                  <div className="flex gap-2">
                    <button type="button" className="text-xs underline" onClick={() => void handleAssign(s.id)}>
                      배정
                    </button>
                    {s.organization_id && (
                      <button type="button" className="text-xs underline" onClick={() => void handleUnassign(s.id)}>
                        해제
                      </button>
                    )}
                  </div>
                </li>
              ))}
            </ul>
          </>
        )}

        {tab === 'operators' && (
          <>
            <form onSubmit={(e) => void handleCreateOperator(e)} className="space-y-3 p-4 rounded-xl border" style={{ borderColor: eduGame.border }}>
              <h2 className="font-bold">교사·원장 계정 생성</h2>
              <select
                value={opForm.organization_id}
                onChange={(e) => setOpForm((f) => ({ ...f, organization_id: e.target.value }))}
                className={`w-full px-3 py-2 rounded-lg border ${eduGameClasses.input}`}
                required
              >
                <option value="">조직 선택</option>
                {orgs.filter((o) => o.is_active).map((o) => (
                  <option key={o.id} value={o.id}>
                    {o.name}
                  </option>
                ))}
              </select>
              <select
                value={opForm.role}
                onChange={(e) => setOpForm((f) => ({ ...f, role: e.target.value as 'owner' | 'teacher' }))}
                className={`w-full px-3 py-2 rounded-lg border ${eduGameClasses.input}`}
              >
                <option value="owner">원장 (owner)</option>
                <option value="teacher">교사 (teacher)</option>
              </select>
              <input
                type="email"
                placeholder="이메일"
                value={opForm.email}
                onChange={(e) => setOpForm((f) => ({ ...f, email: e.target.value }))}
                className={`w-full px-3 py-2 rounded-lg border ${eduGameClasses.input}`}
                required
              />
              <input
                type="password"
                placeholder="비밀번호 (8자 이상)"
                value={opForm.password}
                onChange={(e) => setOpForm((f) => ({ ...f, password: e.target.value }))}
                className={`w-full px-3 py-2 rounded-lg border ${eduGameClasses.input}`}
                minLength={8}
                required
              />
              <input
                placeholder="표시 이름 (선택)"
                value={opForm.display_name}
                onChange={(e) => setOpForm((f) => ({ ...f, display_name: e.target.value }))}
                className={`w-full px-3 py-2 rounded-lg border ${eduGameClasses.input}`}
              />
              <button type="submit" className={`px-4 py-2 rounded-lg font-bold text-white ${eduGameClasses.btnPrimary}`} style={{ backgroundColor: eduGame.primary }}>
                계정 생성
              </button>
            </form>

            <ul className="space-y-2">
              {operators.map((op) => (
                <li key={op.id} className="p-3 rounded-xl border" style={{ borderColor: eduGame.border }}>
                  <p className="font-bold">{op.display_name || op.email}</p>
                  <p className="text-xs" style={{ color: eduGame.muted }}>
                    {op.email} · {ROLE_LABEL[op.role ?? ''] ?? op.role ?? '—'} ·{' '}
                    {op.organization?.name ?? '조직 없음'} · {op.status}
                  </p>
                </li>
              ))}
            </ul>
          </>
        )}
      </main>
    </div>
  )
}
