-- B-1: 코치 깊이 레벨 (L1~L5) — student 기본값, blueprint freeze 전까지 적용
alter table edu_students
add column if not exists coach_level int not null default 1
check (coach_level between 1 and 5);
