import { ReactNode } from 'react'

interface Props {
  title: string
  subtitle?: string
  children: ReactNode
  footer?: ReactNode
}

export default function PreviewFrame({ title, subtitle, children, footer }: Props) {
  return (
    <div className="discovery-preview-frame">
      <div className="discovery-preview-header">
        <span>{title}</span>
        {subtitle && <span style={{ fontSize: 11, color: '#888' }}>{subtitle}</span>}
      </div>
      <div className="discovery-preview-body">{children}</div>
      {footer}
    </div>
  )
}
