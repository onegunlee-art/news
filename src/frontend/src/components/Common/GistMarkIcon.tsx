import { GIST_MARK_SRC } from '../../constants/site'

type GistMarkIconProps = {
  className?: string
}

export default function GistMarkIcon({ className = 'w-[18px] h-[18px]' }: GistMarkIconProps) {
  return (
    <img
      src={GIST_MARK_SRC}
      alt=""
      aria-hidden
      className={`object-contain ${className}`.trim()}
    />
  )
}
