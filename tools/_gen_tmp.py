import os
from pathlib import Path
ROOT = Path(r"c:\Users\My project\News")

def w(rel, content):
    p = ROOT / rel.replace("/", os.sep)
    p.parent.mkdir(parents=True, exist_ok=True)
    p.write_text(content.rstrip() + "\n", encoding="utf-8", newline="\n")
    print("wrote", rel)

# Supabase migration
w("database/migrations/add_intelligence_embeddings.sql", open(r"c:\Users\My project\News\database\migrations\add_intelligence_embeddings.sql").read() if os.path.exists(r"c:\Users\My project\News\database\migrations\add_intelligence_embeddings.sql") else "placeholder")
