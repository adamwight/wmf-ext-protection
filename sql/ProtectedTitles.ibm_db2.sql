CREATE TABLE protected_titles (
  pt_namespace   INTEGER NOT NULL,
  pt_title       VARCHAR(255) NOT NULL,
  pt_user        BIGINT NOT NULL DEFAULT 0,
  --       REFERENCES user(user_id) ON DELETE SET NULL,
  pt_reason      VARCHAR(1024),
  pt_timestamp   TIMESTAMP(3) NOT NULL,
  pt_expiry      TIMESTAMP(3),
  pt_create_perm VARCHAR(60) NOT NULL DEFAULT ''
);
CREATE UNIQUE INDEX protected_titles_unique
  ON protected_titles (pt_namespace, pt_title);
