import { Navigate } from 'react-router-dom'

/** Legacy URL — redirects to /edu/dashboard (Phase 4-B) */
export default function EduOperatorReportsPage() {
  return <Navigate to="/edu/dashboard" replace />
}
