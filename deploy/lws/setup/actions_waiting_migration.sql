ALTER TABLE vp_actions
  MODIFY status ENUM('pending','running','waiting','retry','done','failed','blocked')
  NOT NULL DEFAULT 'pending';
