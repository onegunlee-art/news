# 오디오 플레이 버튼 글자·아이콘 오버랩 검토 보고서

## 1. 현상

- **증상:** 오디오 플레이 버튼을 누르면 글자와 아이콘이 겹쳐 보임.
- **추정:** Google Material Symbols의 `icon_names` 알파벳 순서 문제 가능성.

---

## 2. 관련 코드 위치

| 구분 | 파일 | 설명 |
|------|------|------|
| 폰트 로드 | [src/frontend/index.html](src/frontend/index.html) (17행) | `Material+Symbols+Outlined` + `icon_names=...` |
| 아이콘 컴포넌트 | [src/frontend/src/components/Common/MaterialIcon.tsx](src/frontend/src/components/Common/MaterialIcon.tsx) | `<span>{name}</span>` + ligature 폰트 |
| 오디오 팝업 재생/일시정지 | [src/frontend/src/components/AudioPlayer/AudioPlayerPopup.tsx](src/frontend/src/components/AudioPlayer/AudioPlayerPopup.tsx) (398–402행) | `play_arrow` / `pause` 아이콘 사용 |

---

## 3. icon_names 알파벳 순서 검증

Google Fonts Material Symbols는 `icon_names`로 서브셋 로드 시 **이름을 알파벳 순으로 넣어야** 일부 환경에서 깨지는 버그를 피할 수 있다는 사례가 있음.

현재 `index.html`의 `icon_names` 값:

```
account_circle, arrow_back, arrow_forward, auto_awesome, bar_chart, bookmark, bookmark_border, chat, check, check_circle, chevron_left, chevron_right, close, cognition_2, content_copy, credit_score, delete, description, edit_square, error, expand_less, expand_more, eyeglasses_2, forward_15, group, headphones, help, home, image, ios_share, keyboard_arrow_down, link, menu, menu_book, newspaper, open_in_full, open_in_new, pause, payments, play_arrow, refresh, replay_15, school, search, settings, star, thumb_up, warning
```

- **검증 결과:** 위 목록은 **이미 알파벳 순**으로 정렬되어 있음.
- 오디오 플레이에 쓰는 `play_arrow`, `pause`, `headphones`, `replay_15`, `forward_15`, `close` 모두 목록에 포함되어 있고, 순서도 올바름.

따라서 **“알파벳 순이 아니어서 생긴 문제”로 단정하기는 어렵고**, 순서는 유지한 채 다른 원인을 함께 보는 것이 맞음.

---

## 4. 오버랩 가능 원인

### 4-1. Ligature 미적용 시 텍스트가 그대로 노출·넘침

- `MaterialIcon`은 `<span className="material-symbols-outlined" style={{ width: size, height: size }}>{name}</span>` 구조.
- 정상이면 `name`(예: `play_arrow`)이 **ligature**로 한 글자처럼 그려짐.
- 폰트가 늦게 로드되거나, 서브셋/버그로 해당 glyph가 없으면 **문자열 "play_arrow"가 그대로** 보이고, `width: 24` 안에 들어가지 않아 **옆 요소(제목 "재생 중", 진행 바 등)와 겹칠 수 있음**.

### 4-2. MaterialIcon에 overflow 제어 없음

- 현재 `MaterialIcon`에는 `overflow: hidden`(또는 `overflow: clip`)이 없음.
- Glyph가 실패해 텍스트가 보일 때, 24px 박스를 넘어가도 잘리지 않고 **넘쳐서 오버랩**이 발생할 수 있음.

### 4-3. 레이아웃

- 팝업은 `flex items-center justify-between`으로 왼쪽(제목·썸네일) / 오른쪽(재생 버튼 등)이 나뉨.
- 재생 버튼은 `w-12 h-12`로 고정이지만, **그 안의 span이 넘치면** 오른쪽 영역이 넓어지거나 제목 쪽으로 겹쳐 보일 수 있음.

---

## 5. 결론 및 권장 조치

| 항목 | 내용 |
|------|------|
| **icon_names 순서** | 이미 알파벳 순이며, 오디오 관련 아이콘(play_arrow, pause 등) 모두 포함. “순서 잘못”이 직접 원인일 가능성은 낮음. |
| **오버랩 직접 원인** | 폰트 미적용/지연 시 `play_arrow` 등이 **문자열로 표시**되고, **MaterialIcon span이 overflow를 막지 않아** 옆 텍스트와 겹치는 것으로 보는 것이 타당함. |

**권장 수정:**

1. **MaterialIcon.tsx**  
   - 아이콘 span에 **overflow 숨김** 적용:  
     `overflow: 'hidden'` (또는 `overflow: 'clip'`), 필요 시 `textOverflow: 'clip'` 등으로 텍스트가 박스 밖으로 나가지 않게 처리.  
   - 폰트 로드 전/실패 시에도 글자가 버튼 밖으로 나와 오버랩이 나지 않도록 함.

2. **index.html**  
   - `icon_names`는 **현재 순서 유지** (알파벳 순).  
   - 나중에 아이콘을 추가할 때도 **반드시 알파벳 순으로 삽입**하도록 주석으로 명시해 두면, 같은 유형의 버그 예방에 도움이 됨.

3. **(선택)**  
   - 폰트 로드가 느린 환경을 대비해 `font-display: optional` 또는 `block` 등은 이미 `display=block`으로 로드 중이므로, 필요 시 preload만 추가 검토.

---

## 6. 요약

- **구글 알파벳 순:** 현재 `icon_names`는 알파벳 순이며, 오디오 플레이 관련 아이콘도 모두 포함되어 있어 **순서 문제로 보기 어렵다.**
- **오버랩:** 폰트가 적용되지 않을 때 `play_arrow` 등이 **글자로 보이면서** MaterialIcon 영역을 넘어가고, **overflow 제어가 없어** 제목/다른 텍스트와 겹치는 현상으로 해석하는 것이 타당하다.
- **조치:** `MaterialIcon`에 overflow 숨김을 넣고, `icon_names`는 계속 알파벳 순으로 유지·관리하면 된다.
