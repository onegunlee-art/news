export default function CommunityTab() {
  const dummyComments = [
    { user: '익명123', text: '단기적으로는 시장 변동성이 커질 것 같아요.' },
    { user: '관찰자', text: '장기 영향은 아직 불확실해 보입니다.' },
    { user: '리더', text: '정책 대응 속도가 관건일 듯합니다.' },
  ]

  return (
    <div>
      <h3 style={{ marginTop: 0, fontSize: 16 }}>커뮤니티</h3>
      <p style={{ fontSize: 12, color: '#888' }}>검수용 더미 댓글</p>
      {dummyComments.map((c) => (
        <div key={c.user} style={{ borderBottom: '1px solid #eee', padding: '10px 0' }}>
          <strong style={{ fontSize: 12 }}>{c.user}</strong>
          <p style={{ margin: '4px 0 0', fontSize: 13 }}>{c.text}</p>
        </div>
      ))}
    </div>
  )
}
