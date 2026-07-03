import { getEduAdminKey } from '../utils/eduAdminSession'

export type EduOrganization = {
  id: string
  name: string
  type: 'academy' | 'school'
  slug: string
  metadata: Record<string, unknown>
  is_active: boolean
  created_at: string | null
}

export type EduAdminStudent = {
  id: string
  display_name: string
  grade_band: string
  coach_level: number
  coach_label_ko: string
  completed_count: number
  streak_days: number
  last_active_at: string | null
  organization_id: string | null
  organization_name: string | null
}

export type EduAdminOperator = {
  id: string
  email: string
  display_name: string
  status: string
  organization_id: string | null
  role: string | null
  organization: EduOrganization | null
  last_login_at: string | null
}

async function adminFetch(url: string, init?: RequestInit): Promise<Response> {
  const key = getEduAdminKey()
  const headers = new Headers(init?.headers)
  if (!headers.has('Content-Type') && !(init?.body instanceof FormData)) {
    headers.set('Content-Type', 'application/json')
  }
  if (key) {
    headers.set('X-Edu-Admin-Key', key)
  }
  return fetch(url, { ...init, headers })
}

async function parseJson<T>(res: Response): Promise<T> {
  const data = await res.json()
  if (!res.ok || data.success === false) {
    throw new Error(data.error || data.message || `HTTP ${res.status}`)
  }
  return data as T
}

export async function eduAdminVerifyKey(): Promise<void> {
  const res = await adminFetch('/api/edu/admin/organizations.php')
  await parseJson(res)
}

export async function eduAdminListOrganizations(): Promise<EduOrganization[]> {
  const res = await adminFetch('/api/edu/admin/organizations.php')
  const data = await parseJson<{ organizations: EduOrganization[] }>(res)
  return data.organizations ?? []
}

export async function eduAdminCreateOrganization(input: {
  name: string
  type: 'academy' | 'school'
  slug?: string
  metadata?: Record<string, unknown>
}): Promise<EduOrganization> {
  const res = await adminFetch('/api/edu/admin/organizations.php', {
    method: 'POST',
    body: JSON.stringify(input),
  })
  const data = await parseJson<{ organization: EduOrganization }>(res)
  return data.organization
}

export async function eduAdminUpdateOrganization(input: {
  id: string
  name?: string
  type?: 'academy' | 'school'
  slug?: string
  metadata?: Record<string, unknown>
  is_active?: boolean
}): Promise<EduOrganization> {
  const res = await adminFetch('/api/edu/admin/organizations.php', {
    method: 'PATCH',
    body: JSON.stringify(input),
  })
  const data = await parseJson<{ organization: EduOrganization }>(res)
  return data.organization
}

export async function eduAdminListStudents(params?: {
  unassigned?: boolean
  organizationId?: string
}): Promise<EduAdminStudent[]> {
  const qs = new URLSearchParams()
  if (params?.unassigned) qs.set('unassigned', '1')
  if (params?.organizationId) qs.set('organization_id', params.organizationId)
  const suffix = qs.toString() ? `?${qs.toString()}` : ''
  const res = await adminFetch(`/api/edu/admin/students.php${suffix}`)
  const data = await parseJson<{ students: EduAdminStudent[] }>(res)
  return data.students ?? []
}

export async function eduAdminAssignStudent(
  studentId: string,
  organizationId: string | null
): Promise<void> {
  const res = await adminFetch('/api/edu/admin/students.php', {
    method: 'PATCH',
    body: JSON.stringify({ student_id: studentId, organization_id: organizationId }),
  })
  await parseJson(res)
}

export async function eduAdminListOperators(): Promise<EduAdminOperator[]> {
  const res = await adminFetch('/api/edu/admin/operators.php')
  const data = await parseJson<{ operators: EduAdminOperator[] }>(res)
  return data.operators ?? []
}

export async function eduAdminCreateOperator(input: {
  email: string
  password: string
  display_name?: string
  organization_id: string
  role: 'owner' | 'teacher'
}): Promise<EduAdminOperator> {
  const res = await adminFetch('/api/edu/admin/operators.php', {
    method: 'POST',
    body: JSON.stringify(input),
  })
  const data = await parseJson<{ operator: EduAdminOperator }>(res)
  return data.operator
}
