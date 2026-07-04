import { useState } from 'react'
import EduHomeBoard from './EduHomeBoard'
import EduLandingPage from './EduLandingPage'
import { clearEduToken, getEduToken } from '../../services/eduApi'

/** /edu — 로그인 전: 랜딩 / 로그인 후: 기존 퀘스트 홈 (EduHomeBoard, 변경 없음) */
export default function EduHomePage() {
  const [authed, setAuthed] = useState(!!getEduToken())

  const handleLogout = () => {
    clearEduToken()
    setAuthed(false)
  }

  if (authed) {
    return <EduHomeBoard onLogout={handleLogout} />
  }

  return <EduLandingPage />
}
