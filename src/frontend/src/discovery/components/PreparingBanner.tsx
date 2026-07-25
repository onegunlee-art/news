interface Props {
  message: string
}

export default function PreparingBanner({ message }: Props) {
  return (
    <div className="discovery-boundary" role="status">
      {message}
    </div>
  )
}
