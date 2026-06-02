import { GIST_MARK_SRC } from '../../constants/site'

type GistMarkIconProps = {
  className?: string
}

export default function GistMarkIcon({ className = 'w-9 h-9' }: GistMarkIconProps) {
  return (
    <img
      src={GIST_MARK_SRC}
      alt=""
      aria-hidden
      className={`object-contain ${className}`.trim()}
    />
  )
}
