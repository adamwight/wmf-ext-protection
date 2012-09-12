CREATE TABLE &mw_prefix.protected_titles (
  pt_namespace   NUMBER           DEFAULT 0 NOT NULL,
  pt_title       VARCHAR2(255)    NOT NULL,
  pt_user        NUMBER	          NOT NULL,
  pt_reason      VARCHAR2(255),
  pt_timestamp   TIMESTAMP(6) WITH TIME ZONE  NOT NULL,
  pt_expiry      VARCHAR2(14) NOT NULL,
  pt_create_perm VARCHAR2(60) NOT NULL
);
CREATE UNIQUE INDEX &mw_prefix.protected_titles_u01 ON &mw_prefix.protected_titles (pt_namespace,pt_title);
CREATE INDEX &mw_prefix.protected_titles_i01 ON &mw_prefix.protected_titles (pt_timestamp);
