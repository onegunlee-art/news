<?php
header('Content-Type: application/json; charset=utf-8');
$cfg = ['host'=>'localhost','dbname'=>'ailand','username'=>'ailand','password'=>'romi4120!','charset'=>'utf8mb4'];
try {
  $pdo = new PDO('mysql:host='.$cfg['host'].';dbname='.$cfg['dbname'].';charset='.$cfg['charset'], $cfg['username'], $cfg['password']);
  $cols = 'id, title';
  foreach (['narration','content','why_important'] as $c) {
    $chk = $pdo->query("SHOW COLUMNS FROM news LIKE '$c'");
    if ($chk->rowCount() > 0) $cols .= ", $c";
  }
  $stmt = $pdo->query("SELECT $cols FROM news ORDER BY COALESCE(published_at, created_at) DESC LIMIT 1");
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$row) { echo json_encode(['error'=>'no rows']); exit; }
  $out = ['id'=>$row['id'], 'title'=>$row['title']];
  foreach (['narration','content','why_important'] as $f) {
    if (isset($row[$f])) {
      $val = $row[$f] ?? '';
      $out[$f.'_length'] = strlen($val);
      $out[$f.'_first500'] = mb_substr($val, 0, 500, 'UTF-8');
      $out[$f.'_has_lt'] = strpos($val, '&lt;') !== false;
      $out[$f.'_has_angle'] = strpos($val, '<') !== false;
      $out[$f.'_has_div'] = (bool)preg_match('/<div/i', $val);
      $out[$f.'_has_mark'] = (bool)preg_match('/<mark/i', $val);
      $out[$f.'_has_b'] = (bool)preg_match('/<b[\s>]/i', $val);
      $out[$f.'_has_strong'] = (bool)preg_match('/<strong/i', $val);
    }
  }
  echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Exception $e) {
  echo json_encode(['error'=>$e->getMessage()]);
}
