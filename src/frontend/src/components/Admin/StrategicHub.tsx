import { useState } from 'react';
import MaterialIcon from '../Common/MaterialIcon';
import WeeklyGist from './WeeklyGist';
import StrategicReports from './StrategicReports';
import SearchMemory from './SearchMemory';

type HubSection = 'strategic' | 'weekly' | 'memory';

type StrategicHubProps = {
  /** 참조 기사 ID로 뉴스 관리 탭에서 해당 기사 편집 열기 */
  onEditNewsArticle?: (newsId: number) => void | Promise<void>;
  /** Admin 사이드바에서 직접 진입 시 초기 섹션 */
  initialSection?: HubSection;
};

const sections: { id: HubSection; label: string; icon: string; description: string }[] = [
  {
    id: 'strategic',
    label: '전략 레포트',
    icon: 'analytics',
    description: '외부 인텔리gence · SCQA · PDF/이메일 배포 · Judgment Moat',
  },
  {
    id: 'weekly',
    label: '위클리 Gist',
    icon: 'summarize',
    description: 'the gist 기사 선별 · 주간 브리핑 · human-in-the-loop 편집',
  },
  {
    id: 'memory',
    label: '검색 메모리',
    icon: 'history',
    description: 'search_reports 저장 · Gist entity/topic Memory Diff (Admin 전용)',
  },
];

export default function StrategicHub({ onEditNewsArticle, initialSection = 'strategic' }: StrategicHubProps) {
  const [section, setSection] = useState<HubSection>(initialSection);

  return (
    <div className="space-y-6">
      <div className="rounded-2xl border border-slate-700/60 bg-slate-900/40 p-6">
        <div className="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h2 className="text-2xl font-bold text-white flex items-center gap-2">
              <MaterialIcon name="hub" className="text-cyan-400" />
              Strategic Hub
            </h2>
            <p className="mt-2 text-sm text-slate-400 max-w-2xl">
              Admin 전용 인텔리gence 워크스페이스입니다. 전략 레포트(Delivery)와 위클리 Gist(Briefing)가
              동일한 Narrative Depth Contract를 공유합니다. 고객 검색 Layer 1~2는 변경하지 않습니다.
            </p>
          </div>
          <div className="flex flex-wrap gap-2">
            {sections.map((item) => (
              <button
                key={item.id}
                type="button"
                onClick={() => setSection(item.id)}
                className={`flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-medium transition ${
                  section === item.id
                    ? 'bg-cyan-500/20 text-cyan-300 ring-1 ring-cyan-500/40'
                    : 'bg-slate-800/80 text-slate-400 hover:bg-slate-700/80 hover:text-slate-200'
                }`}
              >
                <MaterialIcon name={item.icon} className="text-base" />
                {item.label}
              </button>
            ))}
          </div>
        </div>
        <p className="mt-4 text-xs text-slate-500">
          {sections.find((s) => s.id === section)?.description}
        </p>
      </div>

      {section === 'strategic' ? (
        <StrategicReports />
      ) : section === 'weekly' ? (
        <WeeklyGist onEditNewsArticle={onEditNewsArticle} />
      ) : (
        <SearchMemory />
      )}
    </div>
  );
}
