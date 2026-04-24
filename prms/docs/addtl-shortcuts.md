# Additional Shortcuts

 | Skill      |  Trigger  |                                            Purpose     |
  |---------------|--------|----------|
  | /new-migration | Creating/altering schema     | Generates correctly ordered migration with all PRMS gotchas enforced (timestamp ordering, no hyphen slugs, no migrate:fresh) |
  | /docker-css    | After any view/CSS change      | Walks through the prms_build volume sync — the most commonly forgotten step                   |  
  | /deploy        | Deploying to Docker    | Full deploy checklist: build → up → migrate → npm build → cache clear → verify                |
  | /snapshot      | End of sprint          | Updates persistent project memory with current state                                         |
  | /new-module    | Adding a new module    | Scaffolds a complete module: DB record, fields, Spatie permissions, optional stages   

  cd D:/Dev/prms && bash backup.sh for backup
