import type { ReactNode } from 'react'

interface Props {
  icon: '●' | '◆'
  children: ReactNode
  suffix?: ReactNode
}

export default function SectionLabel({ icon, children, suffix }: Props) {
  return (
    <div className="discovery-section-label">
      <span className="discovery-section-label-text">
        <span className={icon === '●' ? 'discovery-dot-red' : 'discovery-dot-diamond'}>{icon}</span>
        {children}
      </span>
      {suffix}
    </div>
  )
}
